<?php

use Hyperlab\Dimona\Enums\WorkerType;
use Hyperlab\Dimona\Tests\Factories\EmploymentDataFactory;
use Illuminate\Support\Collection;

require_once __DIR__.'/Helpers.php';

it('merges other worker employments on same day', function () {
    $baseFactory = EmploymentDataFactory::new()
        ->jointCommissionNumber(304)
        ->workerType(WorkerType::Other);

    $employments = new Collection([
        $baseFactory
            ->id('employment-1')
            ->startsAt('2025-10-01 08:00')
            ->endsAt('2025-10-01 12:00')
            ->create(),
        $baseFactory
            ->id('employment-2')
            ->startsAt('2025-10-01 17:00')
            ->endsAt('2025-10-01 20:00')
            ->create(),
    ]);

    $result = computeExpectedDimonaPeriods($employments);

    expect($result)->toHaveCount(1)
        ->and($result[0]->employmentIds)->toBe(['employment-1', 'employment-2'])
        ->and($result[0]->numberOfHours)->toBeNull()
        ->and($result[0]->startDate)->toBe('2025-10-01')
        ->and($result[0]->endDate)->toBe('2025-10-01')
        ->and($result[0]->startHour)->toBeNull()
        ->and($result[0]->endHour)->toBeNull();
});

it('creates separate periods for other worker employments on different days', function () {
    $baseFactory = EmploymentDataFactory::new()
        ->jointCommissionNumber(304)
        ->workerType(WorkerType::Other);

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

    $result = computeExpectedDimonaPeriods($employments);

    expect($result)->toHaveCount(2)
        ->and($result[0]->employmentIds)->toBe(['employment-1'])
        ->and($result[1]->employmentIds)->toBe(['employment-2']);
});

it('handles other worker employment spanning multiple days (across midnight)', function () {
    $employment = EmploymentDataFactory::new()
        ->id('employment-1')
        ->jointCommissionNumber(304)
        ->workerType(WorkerType::Other)
        ->startsAt('2025-10-01 23:00')
        ->endsAt('2025-10-02 03:00')
        ->create();

    $result = computeExpectedDimonaPeriods(new Collection([$employment]));

    expect($result)->toHaveCount(1)
        ->and($result[0]->employmentIds)->toBe(['employment-1'])
        ->and($result[0]->startDate)->toBe('2025-10-01')
        ->and($result[0]->endDate)->toBe('2025-10-02')
        ->and($result[0]->numberOfHours)->toBeNull()
        ->and($result[0]->startHour)->toBeNull()
        ->and($result[0]->endHour)->toBeNull();
});
