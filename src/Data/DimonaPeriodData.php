<?php

namespace Hyperlab\Dimona\Data;

use Hyperlab\Dimona\Enums\WorkerType;

class DimonaPeriodData
{
    public function __construct(
        public array $employmentIds,
        public string $employerEnterpriseNumber,
        public string $workerSocialSecurityNumber,
        public int $jointCommissionNumber,
        public WorkerType $workerType,
        public string $startDate,
        public ?string $startHour,
        public string $endDate,
        public ?string $endHour,
        public ?float $numberOfHours,
        public EmploymentLocationData $location,
    ) {}
}
