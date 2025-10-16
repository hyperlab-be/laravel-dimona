<?php

use Hyperlab\Dimona\Actions\DimonaPeriod\ComputeExpectedDimonaPeriods;
use Hyperlab\Dimona\Enums\WorkerType;
use Hyperlab\Dimona\Tests\Factories\EmploymentDataFactory;
use Illuminate\Support\Collection;

require_once __DIR__.'/Helpers.php';

it('handles empty collection', function () {
    $employments = new Collection([]);

    $result = ComputeExpectedDimonaPeriods::new()->execute(EMPLOYER_ENTERPRISE_NUMBER, WORKER_SSN, $employments);

    expect($result)->toBeEmpty();
});

it('handles single employment', function () {
    $employment = EmploymentDataFactory::new()
        ->id('employment-1')
        ->startsAt('2025-10-01 07:00')
        ->endsAt('2025-10-01 12:00')
        ->create();

    $result = ComputeExpectedDimonaPeriods::new()->execute(EMPLOYER_ENTERPRISE_NUMBER, WORKER_SSN, new Collection([$employment]));

    expect($result)->toHaveCount(1)
        ->and($result[0]->employmentIds)->toBe(['employment-1'])
        ->and($result[0]->startDate)->toBe('2025-10-01')
        ->and($result[0]->endDate)->toBe('2025-10-01');
});

it('handles unordered employments', function () {
    $baseFactory = EmploymentDataFactory::new()
        ->jointCommissionNumber(304)
        ->workerType(WorkerType::Flexi);

    $employments = new Collection([
        $baseFactory
            ->id('employment-2')
            ->startsAt('2025-10-01 12:00')
            ->endsAt('2025-10-01 17:00')
            ->create(),
        $baseFactory
            ->id('employment-1')
            ->startsAt('2025-10-01 07:00')
            ->endsAt('2025-10-01 12:00')
            ->create(),
    ]);

    $result = ComputeExpectedDimonaPeriods::new()->execute(EMPLOYER_ENTERPRISE_NUMBER, WORKER_SSN, $employments);

    expect($result)->toHaveCount(2)
        ->and($result[0]->employmentIds)->toBe(['employment-1'])
        ->and($result[1]->employmentIds)->toBe(['employment-2']);
});

it('returns groups in consistent order', function () {
    // Create employments in random order
    $employments = new Collection([
        EmploymentDataFactory::new()
            ->id('emp-3')
            ->jointCommissionNumber(306)
            ->workerType(WorkerType::Other)
            ->startsAt('2025-10-03 08:00')
            ->endsAt('2025-10-03 12:00')
            ->create(),
        EmploymentDataFactory::new()
            ->id('emp-1')
            ->jointCommissionNumber(304)
            ->workerType(WorkerType::Flexi)
            ->startsAt('2025-10-01 08:00')
            ->endsAt('2025-10-01 12:00')
            ->create(),
        EmploymentDataFactory::new()
            ->id('emp-2')
            ->jointCommissionNumber(305)
            ->workerType(WorkerType::Student)
            ->startsAt('2025-10-02 08:00')
            ->endsAt('2025-10-02 12:00')
            ->create(),
    ]);

    $result = ComputeExpectedDimonaPeriods::new()->execute(EMPLOYER_ENTERPRISE_NUMBER, WORKER_SSN, $employments);

    // Groups maintain the order of first occurrence in input after grouping
    // emp-3 appears first in input, then emp-1, then emp-2
    expect($result)->toHaveCount(3)
        ->and($result[0]->employmentIds)->toBe(['emp-3'])
        ->and($result[1]->employmentIds)->toBe(['emp-1'])
        ->and($result[2]->employmentIds)->toBe(['emp-2']);
});
