<?php

namespace Hyperlab\Dimona\Data;

use Carbon\CarbonImmutable;
use Hyperlab\Dimona\Enums\WorkerType;

class DimonaPeriodData
{
    public function __construct(
        public array $employmentIds,
        public string $employerEnterpriseNumber,
        public int $jointCommissionNumber,
        public WorkerType $workerType,
        public string $workerSocialSecurityNumber,
        public CarbonImmutable $startsAt,
        public CarbonImmutable $endsAt,
        public EmploymentLocationData $location,
    ) {}
}
