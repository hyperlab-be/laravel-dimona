<?php

namespace Hyperlab\Dimona\Data;

use Carbon\CarbonImmutable;
use Hyperlab\Dimona\Enums\WorkerType;

class EmploymentData
{
    public function __construct(
        public readonly string $id,
        public readonly int $jointCommissionNumber,
        public WorkerType $workerType,
        public readonly CarbonImmutable $startsAt,
        public readonly CarbonImmutable $endsAt,
        public readonly EmploymentLocationData $location,
    ) {}
}
