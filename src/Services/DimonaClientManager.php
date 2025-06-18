<?php

namespace Hyperlab\Dimona\Services;

use Exception;
use Illuminate\Support\Facades\Config;

class DimonaClientManager
{
    /**
     * The cached API clients.
     *
     * @var array<string, DimonaApiClient>
     */
    protected array $clients = [];

    /**
     * Get a Dimona API client by its identifier.
     *
     * @param  string|null  $clientId  The client identifier
     * @return DimonaApiClient The Dimona API client
     *
     * @throws Exception If the client is not configured
     */
    public function client(?string $clientId = null): DimonaApiClient
    {
        $clientId = $clientId ?? Config::get('dimona.default_client');

        if (isset($this->clients[$clientId])) {
            return $this->clients[$clientId];
        }

        $config = Config::get("dimona.clients.{$clientId}");

        if (! $config) {
            throw new Exception("Dimona client [{$clientId}] is not configured.");
        }

        return $this->clients[$clientId] = new DimonaApiClient(
            clientId: $config['client_id'],
            privateKeyPath: $config['private_key_path'],
        );
    }
}
