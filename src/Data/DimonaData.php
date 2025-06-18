<?php

namespace Hyperlab\Dimona\Data;

use Hyperlab\Dimona\Enums\WorkerType;
use Illuminate\Support\Carbon;

class DimonaData
{
    public function __construct(
        public readonly string $employerEnterpriseNumber,
        public readonly int $jointCommissionNumber,
        public WorkerType $workerType,
        public readonly string $workerSocialSecurityNumber,
        public readonly Carbon $startsAt,
        public readonly Carbon $endsAt,
        public readonly DimonaLocationData $location,
    ) {}
}
