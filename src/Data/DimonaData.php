<?php

namespace Hyperlab\Dimona\Data;

use Carbon\CarbonImmutable;
use Hyperlab\Dimona\Enums\WorkerType;

class DimonaData
{
    public function __construct(
        public readonly string $employerEnterpriseNumber,
        public readonly int $jointCommissionNumber,
        public WorkerType $workerType,
        public readonly string $workerSocialSecurityNumber,
        public readonly CarbonImmutable $startsAt,
        public readonly CarbonImmutable $endsAt,
        public readonly DimonaLocationData $location,
    ) {}
}
