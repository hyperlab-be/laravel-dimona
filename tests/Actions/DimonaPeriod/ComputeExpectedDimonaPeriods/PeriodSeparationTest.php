<?php

use Hyperlab\Dimona\Actions\DimonaPeriod\ComputeExpectedDimonaPeriods;
use Hyperlab\Dimona\Enums\WorkerType;
use Hyperlab\Dimona\Tests\Factories\EmploymentDataFactory;
use Illuminate\Support\Collection;

require_once __DIR__.'/Helpers.php';

it('separates periods by different worker types', function () {
    $employments = new Collection([
        EmploymentDataFactory::new()
            ->id('employment-1')
            ->workerType(WorkerType::Flexi)
            ->startsAt('2025-10-01 07:00')
            ->endsAt('2025-10-01 12:00')
            ->create(),
        EmploymentDataFactory::new()
            ->id('employment-2')
            ->workerType(WorkerType::Student)
            ->startsAt('2025-10-01 12:00')
            ->endsAt('2025-10-01 17:00')
            ->create(),
    ]);

    $result = ComputeExpectedDimonaPeriods::new()->execute(EMPLOYER_ENTERPRISE_NUMBER, WORKER_SSN, $employments);

    expect($result)->toHaveCount(2)
        ->and($result[0]->workerType)->toBe(WorkerType::Flexi)
        ->and($result[1]->workerType)->toBe(WorkerType::Student);
});

it('separates periods by different dates', function () {
    $employments = new Collection([
        EmploymentDataFactory::new()
            ->id('employment-1')
            ->startsAt('2025-10-01 07:00')
            ->endsAt('2025-10-01 12:00')
            ->create(),
        EmploymentDataFactory::new()
            ->id('employment-2')
            ->startsAt('2025-10-02 12:00')
            ->endsAt('2025-10-02 17:00')
            ->create(),
    ]);

    $result = ComputeExpectedDimonaPeriods::new()->execute(EMPLOYER_ENTERPRISE_NUMBER, WORKER_SSN, $employments);

    expect($result)->toHaveCount(2);
});

it('separates periods by different joint commission numbers', function () {
    $baseFactory = EmploymentDataFactory::new()
        ->workerType(WorkerType::Flexi);

    $employments = new Collection([
        $baseFactory
            ->id('employment-1')
            ->jointCommissionNumber(304)
            ->startsAt('2025-10-01 07:00')
            ->endsAt('2025-10-01 12:00')
            ->create(),
        $baseFactory
            ->id('employment-2')
            ->jointCommissionNumber(302)
            ->startsAt('2025-10-01 12:00')
            ->endsAt('2025-10-01 17:00')
            ->create(),
    ]);

    $result = ComputeExpectedDimonaPeriods::new()->execute(EMPLOYER_ENTERPRISE_NUMBER, WORKER_SSN, $employments);

    expect($result)->toHaveCount(2)
        ->and($result[0]->jointCommissionNumber)->toBe(304)
        ->and($result[1]->jointCommissionNumber)->toBe(302);
});
