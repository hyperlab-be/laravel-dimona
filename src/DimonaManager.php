<?php

namespace Hyperlab\Dimona;

use Hyperlab\Dimona\Jobs\DeclareDimona;
use Hyperlab\Dimona\Services\DimonaApiClientManager;

class DimonaManager
{
    /**
     * The client ID to use for API calls.
     */
    protected ?string $clientId = null;

    public function __construct(
        protected DimonaApiClientManager $clientManager
    ) {}

    /**
     * Set the client ID to use for API calls.
     *
     * @return $this
     */
    public function client(?string $clientId = null): self
    {
        $this->clientId = $clientId;

        return $this;
    }

    /**
     * Declare a Dimona period.
     */
    public function declare(DimonaDeclarable $dimonaDeclarable): void
    {
        DeclareDimona::dispatch($dimonaDeclarable, $this->clientId);
    }
}
