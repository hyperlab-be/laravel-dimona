<?php

namespace Hyperlab\Dimona;

use Carbon\CarbonPeriodImmutable;
use Hyperlab\Dimona\Data\EmploymentData;
use Hyperlab\Dimona\Jobs\DeclareDimona;
use Hyperlab\Dimona\Services\DimonaApiClientManager;
use Illuminate\Support\Collection;

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
     * Sync the Dimona periods for the given period with the employments.
     *
     * @param  Collection<EmploymentData>  $employments
     */
    public function declare(CarbonPeriodImmutable $period, Collection $employments): void
    {
        DeclareDimona::dispatch($period, $employments, $this->clientId);
    }
}
