<?php

use Hyperlab\Dimona\Actions\DimonaPeriod\ComputeExpectedDimonaPeriods;
use Hyperlab\Dimona\Enums\WorkerType;
use Hyperlab\Dimona\Tests\Factories\EmploymentDataFactory;
use Illuminate\Support\Collection;

require_once __DIR__.'/Helpers.php';

it('handles mixed worker types on same day', function () {
    $baseFactory = EmploymentDataFactory::new()
        ->jointCommissionNumber(304);

    $employments = new Collection([
        $baseFactory
            ->id('employment-1')
            ->workerType(WorkerType::Flexi)
            ->startsAt('2025-10-01 08:00')
            ->endsAt('2025-10-01 12:00')
            ->create(),
        $baseFactory
            ->id('employment-2')
            ->workerType(WorkerType::Student)
            ->startsAt('2025-10-01 12:00')
            ->endsAt('2025-10-01 16:00')
            ->create(),
        $baseFactory
            ->id('employment-3')
            ->workerType(WorkerType::Flexi)
            ->startsAt('2025-10-01 16:00')
            ->endsAt('2025-10-01 20:00')
            ->create(),
    ]);

    $result = ComputeExpectedDimonaPeriods::new()->execute(EMPLOYER_ENTERPRISE_NUMBER, WORKER_SSN, $employments);

    // Should create 3 periods: Flexi (2 separate) + Student (1)
    // Flexi workers with same date create separate periods
    // Order reflects the input order: emp-1 (Flexi), emp-2 (Flexi), emp-3 (Student)
    expect($result)->toHaveCount(3)
        ->and($result[0]->workerType)->toBe(WorkerType::Flexi)
        ->and($result[0]->employmentIds)->toBe(['employment-1'])
        ->and($result[1]->workerType)->toBe(WorkerType::Flexi)
        ->and($result[1]->employmentIds)->toBe(['employment-3'])
        ->and($result[2]->workerType)->toBe(WorkerType::Student)
        ->and($result[2]->employmentIds)->toBe(['employment-2']);
});

it('handles all three worker types together', function () {
    $baseFactory = EmploymentDataFactory::new()
        ->jointCommissionNumber(304);

    $employments = new Collection([
        $baseFactory
            ->id('flexi-1')
            ->workerType(WorkerType::Flexi)
            ->startsAt('2025-10-01 08:00')
            ->endsAt('2025-10-01 10:00')
            ->create(),
        $baseFactory
            ->id('flexi-2')
            ->workerType(WorkerType::Flexi)
            ->startsAt('2025-10-01 10:00')
            ->endsAt('2025-10-01 12:00')
            ->create(),
        $baseFactory
            ->id('student-1')
            ->workerType(WorkerType::Student)
            ->startsAt('2025-10-01 12:00')
            ->endsAt('2025-10-01 14:00')
            ->create(),
        $baseFactory
            ->id('student-2')
            ->workerType(WorkerType::Student)
            ->startsAt('2025-10-01 14:00')
            ->endsAt('2025-10-01 16:00')
            ->create(),
        $baseFactory
            ->id('other-1')
            ->workerType(WorkerType::Other)
            ->startsAt('2025-10-01 16:00')
            ->endsAt('2025-10-01 18:00')
            ->create(),
        $baseFactory
            ->id('other-2')
            ->workerType(WorkerType::Other)
            ->startsAt('2025-10-01 18:00')
            ->endsAt('2025-10-01 20:00')
            ->create(),
    ]);

    $result = ComputeExpectedDimonaPeriods::new()->execute(EMPLOYER_ENTERPRISE_NUMBER, WORKER_SSN, $employments);

    // Flexi: 2 separate periods, Student: 1 merged period, Other: 1 merged period = 4 total
    expect($result)->toHaveCount(4)
        ->and($result[0]->employmentIds)->toBe(['flexi-1'])
        ->and($result[1]->employmentIds)->toBe(['flexi-2'])
        ->and($result[2]->employmentIds)->toBe(['student-1', 'student-2'])
        ->and($result[2]->numberOfHours)->toBe(4.0)
        ->and($result[3]->employmentIds)->toBe(['other-1', 'other-2']);
});
