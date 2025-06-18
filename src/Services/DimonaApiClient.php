<?php

namespace Hyperlab\Dimona\Services;

use Carbon\Carbon;
use Exception;
use Firebase\JWT\JWT;
use Hyperlab\Dimona\Exceptions\DimonaDeclarationIsNotYetProcessed;
use Hyperlab\Dimona\Exceptions\DimonaServiceIsDown;
use Illuminate\Http\Client\PendingRequest as HttpClient;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Psr\Http\Message\RequestInterface;

class DimonaApiClient
{
    private HttpClient $httpClient;

    public function __construct(
        public readonly string $clientId,
        public readonly string $privateKeyPath,
    ) {
        $this->httpClient = Http::baseUrl(config('dimona.endpoint'))
            ->throw()
            ->withRequestMiddleware(function (RequestInterface $request) {
                $accessToken = $this->authenticate();

                return $request->withHeader('Authorization', "Bearer {$accessToken}");
            });
    }

    public static function new(?string $clientId = null): static
    {
        return app(DimonaClientManager::class)->client($clientId);
    }

    public function createDeclaration(array $payload): array
    {
        $response = $this->httpClient->post('declarations', $payload);

        $reference = Str::after($response->header('Location'), 'declarations/');

        if (! $reference) {
            throw new Exception('Invalid response from Dimona API: missing reference');
        }

        return [
            'reference' => $reference,
        ];
    }

    public function getDeclaration(string $reference): array
    {
        try {
            $response = $this->httpClient->get("declarations/{$reference}");

            return [
                'reference' => $response->json('declarationStatus.period.id'),
                'result' => $response->json('declarationStatus.result'),
                'anomalies' => $response->json('declarationStatus.anomalies'),
            ];
        } catch (RequestException $exception) {
            if ($exception->response->status() === 404) {
                throw new DimonaDeclarationIsNotYetProcessed;
            }

            if (in_array($exception->response->status(), [400, 405])) {
                throw new Exception('Invalid request to Dimona API', 500, $exception);
            }

            if ($exception->response->serverError()) {
                throw new DimonaServiceIsDown($exception);
            }

            throw $exception;
        }
    }

    private function authenticate(): string
    {
        $accessToken = Cache::get("dimona_access_token_{$this->clientId}");
        $accessTokenExpirationDate = Cache::get("dimona_access_token_expiration_date_{$this->clientId}");

        if ($accessToken && $accessTokenExpirationDate && Carbon::parse(intval($accessTokenExpirationDate))->isFuture()) {
            return $accessToken;
        }

        $oauthEndpoint = config('dimona.oauth_endpoint');
        $now = time();

        $response = Http::asForm()
            ->throw()
            ->post($oauthEndpoint, [
                'grant_type' => 'client_credentials',
                'scope' => 'scope:dimona:declaration:declarant scope:dimona:declaration:consult',
                'client_assertion_type' => 'urn:ietf:params:oauth:client-assertion-type:jwt-bearer',
                'client_assertion' => JWT::encode(
                    payload: [
                        'jti' => (string) Str::uuid(),
                        'iss' => $this->clientId,
                        'sub' => $this->clientId,
                        'aud' => $oauthEndpoint,
                        'exp' => $now + 300,
                        'iat' => $now,
                    ],
                    key: file_get_contents($this->privateKeyPath),
                    alg: 'RS256',
                ),
            ]);

        if (! $response->json('access_token')) {
            throw new Exception('Unable to retrieve authorization token');
        }

        $accessToken = $response->json('access_token');
        $accessTokenExpirationDate = time() + $response->json('expires_in');

        Cache::put("dimona_access_token_{$this->clientId}", $accessToken);
        Cache::put("dimona_access_token_expiration_date_{$this->clientId}", $accessTokenExpirationDate);

        return $accessToken;
    }
}
