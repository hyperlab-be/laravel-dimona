<?php

namespace Hyperlab\Dimona\Data;

use Carbon\CarbonImmutable;
use Hyperlab\Dimona\Enums\WorkerType;

class EmploymentData
{
    public function __construct(
        public string $id,
        public int $jointCommissionNumber,
        public WorkerType $workerType,
        public CarbonImmutable $startsAt,
        public CarbonImmutable $endsAt,
        public EmploymentLocationData $location,
    ) {}
}
