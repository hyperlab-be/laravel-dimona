<?php

namespace Hyperlab\Dimona;

use Hyperlab\Dimona\Jobs\DeclareDimona;
use Hyperlab\Dimona\Services\DimonaClientManager;

class DimonaManager
{
    /**
     * The client ID to use for API calls.
     */
    protected ?string $clientId = null;

    public function __construct(
        protected DimonaClientManager $clientManager
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
     * Declare a Dimona period for an employment contract.
     */
    public function declare(Employment $employment): Employment
    {
        DeclareDimona::dispatch($employment, $this->clientId);

        return $employment;
    }
}
