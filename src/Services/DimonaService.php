<?php

namespace Hyperlab\Dimona\Services;

use Hyperlab\Dimona\Enums\DimonaDeclarationState;
use Hyperlab\Dimona\Enums\DimonaDeclarationType;
use Hyperlab\Dimona\Exceptions\DimonaDeclarationIsNotYetProcessed;
use Hyperlab\Dimona\Exceptions\DimonaServiceIsDown;
use Hyperlab\Dimona\Exceptions\InvalidDimonaApiRequest;
use Hyperlab\Dimona\Models\DimonaDeclaration;
use Hyperlab\Dimona\Models\DimonaPeriod;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\DB;

use function in_array;

class DimonaService
{
    private DimonaApiClient $apiClient;

    private DimonaPayloadBuilder $payloadBuilder;

    private readonly WorkerTypeExceptionService $workerTypeExceptionService;

    public function __construct(?string $clientId = null)
    {
        $this->apiClient = DimonaApiClient::new($clientId);
        $this->payloadBuilder = DimonaPayloadBuilder::new();
        $this->workerTypeExceptionService = WorkerTypeExceptionService::new();
    }

    public function createDimonaPeriod(DimonaPeriod $dimonaPeriod): void
    {
        $payload = $this->payloadBuilder->buildCreatePayload($dimonaPeriod);

        $this->createDimonaDeclaration(
            dimonaPeriod: $dimonaPeriod,
            type: DimonaDeclarationType::In,
            payload: $payload
        );
    }

    public function updateDimonaPeriod(DimonaPeriod $dimonaPeriod): void
    {
        $payload = $this->payloadBuilder->buildUpdatePayload($dimonaPeriod);

        $this->createDimonaDeclaration(
            dimonaPeriod: $dimonaPeriod,
            type: DimonaDeclarationType::Update,
            payload: $payload
        );
    }

    public function cancelDimonaPeriod(DimonaPeriod $dimonaPeriod): void
    {
        $payload = $this->payloadBuilder->buildCancelPayload(
            dimonaPeriod: $dimonaPeriod
        );

        $this->createDimonaDeclaration(
            dimonaPeriod: $dimonaPeriod,
            type: DimonaDeclarationType::Cancel,
            payload: $payload
        );
    }

    public function syncDimonaPeriod(DimonaPeriod $dimonaPeriod): bool
    {
        /** @var DimonaDeclaration $pendingDeclaration */
        $pendingDeclaration = $dimonaPeriod->dimona_declarations->first();

        $this->syncDimonaDeclaration($pendingDeclaration);

        // Check if still pending or waiting after sync
        return in_array($pendingDeclaration->state, [DimonaDeclarationState::Pending, DimonaDeclarationState::Waiting]);
    }

    private function createDimonaDeclaration(
        DimonaPeriod $dimonaPeriod, DimonaDeclarationType $type, array $payload
    ): void {
        $dimonaDeclaration = $dimonaPeriod->createDimonaDeclaration(
            type: $type,
            payload: $payload
        );

        try {
            $result = $this->apiClient->createDeclaration($payload);
            $dimonaDeclaration->updateReference($result['reference']);
        } catch (RequestException $exception) {
            $dimonaDeclaration->updateState(
                state: DimonaDeclarationState::Failed,
                anomalies: $exception->response->json(),
            );
        }

        $dimonaPeriod->updateState();
    }

    private function syncDimonaDeclaration(DimonaDeclaration $declaration): void
    {
        try {
            $response = $this->apiClient->getDeclaration($declaration->reference);

            DB::transaction(function () use ($declaration, $response) {
                if ($declaration->dimona_period->reference === null) {
                    $declaration->dimona_period->updateReference($response['reference']);
                }

                $declaration->updateState(
                    state: match ($response['result']) {
                        'A' => DimonaDeclarationState::Accepted,
                        'W' => DimonaDeclarationState::AcceptedWithWarning,
                        'B' => DimonaDeclarationState::Refused,
                        'S' => DimonaDeclarationState::Waiting,
                        default => DimonaDeclarationState::Failed,
                    },
                    anomalies: $response['anomalies']
                );

                $declaration->refresh();

                // Handle worker type exceptions based on anomalies (may create cancel declarations)
                $this->workerTypeExceptionService->handleExceptions($declaration);

                // Update period state after handling exceptions
                $declaration->dimona_period->updateState();
            });
        } catch (DimonaDeclarationIsNotYetProcessed|DimonaServiceIsDown) {
            // Declaration not yet processed or service is down, keep it pending
            // Will be retried on next job dispatch
        } catch (InvalidDimonaApiRequest $exception) {
            DB::transaction(function () use ($declaration, $exception) {
                $declaration->updateState(
                    state: DimonaDeclarationState::Failed,
                    anomalies: $exception->getPrevious()->response->json(),
                );

                $declaration->dimona_period->updateState();
            });
        } catch (RequestException $exception) {
            DB::transaction(function () use ($declaration, $exception) {
                $declaration->updateState(
                    state: DimonaDeclarationState::Failed,
                    anomalies: $exception->response->json(),
                );

                $declaration->dimona_period->updateState();
            });
        }

        $declaration->refresh();
    }
}
