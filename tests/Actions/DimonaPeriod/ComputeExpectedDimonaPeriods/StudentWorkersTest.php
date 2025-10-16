<?php

use Hyperlab\Dimona\Actions\DimonaPeriod\ComputeExpectedDimonaPeriods;
use Hyperlab\Dimona\Data\EmploymentLocationData;
use Hyperlab\Dimona\Enums\EmploymentLocationCountry;
use Hyperlab\Dimona\Enums\WorkerType;
use Hyperlab\Dimona\Tests\Factories\EmploymentDataFactory;
use Illuminate\Support\Collection;

require_once __DIR__.'/Helpers.php';

it('merges consecutive student employments and accumulates hours', function () {
    $baseFactory = EmploymentDataFactory::new()
        ->jointCommissionNumber(304)
        ->workerType(WorkerType::Student);

    $employments = new Collection([
        $baseFactory
            ->id('employment-1')
            ->startsAt('2025-10-01 08:00')
            ->endsAt('2025-10-01 12:00') // 4 hours
            ->create(),
        $baseFactory
            ->id('employment-2')
            ->startsAt('2025-10-01 12:00')
            ->endsAt('2025-10-01 17:00') // 5 hours
            ->create(),
        $baseFactory
            ->id('employment-3')
            ->startsAt('2025-10-01 17:00')
            ->endsAt('2025-10-01 20:00') // 3 hours
            ->create(),
    ]);

    $result = ComputeExpectedDimonaPeriods::new()->execute(EMPLOYER_ENTERPRISE_NUMBER, WORKER_SSN, $employments);

    expect($result)->toHaveCount(1)
        ->and($result[0]->employmentIds)->toBe(['employment-1', 'employment-2', 'employment-3'])
        ->and($result[0]->numberOfHours)->toBe(12.0) // 4 + 5 + 3
        ->and($result[0]->startDate)->toBe('2025-10-01')
        ->and($result[0]->endDate)->toBe('2025-10-01')
        ->and($result[0]->startHour)->toBeNull()
        ->and($result[0]->endHour)->toBeNull();
});

it('creates separate periods for non-consecutive student employments', function () {
    $baseFactory = EmploymentDataFactory::new()
        ->jointCommissionNumber(304)
        ->workerType(WorkerType::Student);

    $employments = new Collection([
        $baseFactory
            ->id('employment-1')
            ->startsAt('2025-10-01 08:00')
            ->endsAt('2025-10-01 12:00')
            ->create(),
        $baseFactory
            ->id('employment-2')
            ->startsAt('2025-10-02 08:00')
            ->endsAt('2025-10-02 12:00')
            ->create(),
    ]);

    $result = ComputeExpectedDimonaPeriods::new()->execute(EMPLOYER_ENTERPRISE_NUMBER, WORKER_SSN, $employments);

    expect($result)->toHaveCount(2)
        ->and($result[0]->employmentIds)->toBe(['employment-1'])
        ->and($result[0]->numberOfHours)->toBe(4.0)
        ->and($result[1]->employmentIds)->toBe(['employment-2'])
        ->and($result[1]->numberOfHours)->toBe(4.0);
});

it('merges student workers with gaps on same day', function () {
    $baseFactory = EmploymentDataFactory::new()
        ->jointCommissionNumber(304)
        ->workerType(WorkerType::Student);

    $employments = new Collection([
        $baseFactory
            ->id('employment-1')
            ->startsAt('2025-10-01 08:00')
            ->endsAt('2025-10-01 12:00')
            ->create(),
        $baseFactory
            ->id('employment-2')
            ->startsAt('2025-10-01 14:00') // 2-hour gap
            ->endsAt('2025-10-01 18:00')
            ->create(),
    ]);

    $result = ComputeExpectedDimonaPeriods::new()->execute(EMPLOYER_ENTERPRISE_NUMBER, WORKER_SSN, $employments);

    // Student workers in same group are merged even when there's a gap (non-consecutive times)
    // Both are in same group (same day, worker type, commission), so they get merged
    expect($result)->toHaveCount(1)
        ->and($result[0]->employmentIds)->toBe(['employment-1', 'employment-2'])
        ->and($result[0]->numberOfHours)->toBe(8.0); // Both hours get accumulated
});

it('uses first employment location when merging student workers', function () {
    $location1 = new EmploymentLocationData(
        name: 'Office A',
        street: 'Street A',
        houseNumber: '1',
        boxNumber: null,
        postalCode: '1000',
        place: 'Brussels',
        country: EmploymentLocationCountry::Belgium
    );

    $location2 = new EmploymentLocationData(
        name: 'Office B',
        street: 'Street B',
        houseNumber: '2',
        boxNumber: null,
        postalCode: '2000',
        place: 'Antwerp',
        country: EmploymentLocationCountry::Belgium
    );

    $baseFactory = EmploymentDataFactory::new()
        ->jointCommissionNumber(304)
        ->workerType(WorkerType::Student);

    $employments = new Collection([
        $baseFactory
            ->id('employment-1')
            ->startsAt('2025-10-01 08:00')
            ->endsAt('2025-10-01 12:00')
            ->location($location1)
            ->create(),
        $baseFactory
            ->id('employment-2')
            ->startsAt('2025-10-01 12:00')
            ->endsAt('2025-10-01 16:00')
            ->location($location2)
            ->create(),
    ]);

    $result = ComputeExpectedDimonaPeriods::new()->execute(EMPLOYER_ENTERPRISE_NUMBER, WORKER_SSN, $employments);

    // Should use the first employment's location
    expect($result)->toHaveCount(1)
        ->and($result[0]->employmentIds)->toBe(['employment-1', 'employment-2'])
        ->and($result[0]->location)->toBe($location1);
});

it('handles student workers with partial hours', function () {
    $baseFactory = EmploymentDataFactory::new()
        ->jointCommissionNumber(304)
        ->workerType(WorkerType::Student);

    $employments = new Collection([
        $baseFactory
            ->id('employment-1')
            ->startsAt('2025-10-01 08:30')
            ->endsAt('2025-10-01 12:15') // 3.75 hours
            ->create(),
        $baseFactory
            ->id('employment-2')
            ->startsAt('2025-10-01 12:15')
            ->endsAt('2025-10-01 17:45') // 5.5 hours
            ->create(),
    ]);

    $result = ComputeExpectedDimonaPeriods::new()->execute(EMPLOYER_ENTERPRISE_NUMBER, WORKER_SSN, $employments);

    expect($result)->toHaveCount(1)
        ->and($result[0]->employmentIds)->toBe(['employment-1', 'employment-2'])
        ->and($result[0]->numberOfHours)->toBe(9.25); // 3.75 + 5.5
});

it('handles student employment spanning multiple days (across midnight)', function () {
    $employment = EmploymentDataFactory::new()
        ->id('employment-1')
        ->jointCommissionNumber(304)
        ->workerType(WorkerType::Student)
        ->startsAt('2025-10-01 22:00')
        ->endsAt('2025-10-02 02:00') // 4 hours spanning midnight
        ->create();

    $result = ComputeExpectedDimonaPeriods::new()->execute(EMPLOYER_ENTERPRISE_NUMBER, WORKER_SSN, new Collection([$employment]));

    expect($result)->toHaveCount(1)
        ->and($result[0]->employmentIds)->toBe(['employment-1'])
        ->and($result[0]->startDate)->toBe('2025-10-01')
        ->and($result[0]->endDate)->toBe('2025-10-02')
        ->and($result[0]->numberOfHours)->toBe(4.0)
        ->and($result[0]->startHour)->toBeNull()
        ->and($result[0]->endHour)->toBeNull();
});

it('merges student employments and updates end date correctly', function () {
    $baseFactory = EmploymentDataFactory::new()
        ->jointCommissionNumber(304)
        ->workerType(WorkerType::Student);

    $employments = new Collection([
        $baseFactory
            ->id('employment-1')
            ->startsAt('2025-10-01 08:00')
            ->endsAt('2025-10-01 12:00')
            ->create(),
        $baseFactory
            ->id('employment-2')
            ->startsAt('2025-10-01 13:00')
            ->endsAt('2025-10-01 17:00')
            ->create(),
        $baseFactory
            ->id('employment-3')
            ->startsAt('2025-10-01 18:00')
            ->endsAt('2025-10-01 22:30')
            ->create(),
    ]);

    $result = ComputeExpectedDimonaPeriods::new()->execute(EMPLOYER_ENTERPRISE_NUMBER, WORKER_SSN, $employments);

    expect($result)->toHaveCount(1)
        ->and($result[0]->employmentIds)->toBe(['employment-1', 'employment-2', 'employment-3'])
        ->and($result[0]->startDate)->toBe('2025-10-01')
        ->and($result[0]->endDate)->toBe('2025-10-01')
        ->and($result[0]->numberOfHours)->toBe(12.5); // 4 + 4 + 4.5
});
