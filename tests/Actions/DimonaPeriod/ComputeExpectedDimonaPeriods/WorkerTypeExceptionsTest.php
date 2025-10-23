<?php

use Carbon\CarbonImmutable;
use Hyperlab\Dimona\Enums\WorkerType;
use Hyperlab\Dimona\Models\DimonaWorkerTypeException;
use Hyperlab\Dimona\Tests\Factories\EmploymentDataFactory;
use Illuminate\Support\Collection;

require_once __DIR__.'/Helpers.php';

it('resolves flexi worker type to Other when exception exists', function () {
    $startsAt = CarbonImmutable::parse('2025-10-01 07:00');

    // Create an exception for this worker
    DimonaWorkerTypeException::query()->create([
        'social_security_number' => WORKER_SSN,
        'worker_type' => WorkerType::Flexi,
        'starts_at' => $startsAt->startOfDay(),
        'ends_at' => $startsAt->endOfDay(),
    ]);

    $employments = new Collection([
        EmploymentDataFactory::new()
            ->id('employment-1')
            ->workerType(WorkerType::Flexi)
            ->startsAt($startsAt)
            ->endsAt('2025-10-01 12:00')
            ->create(),
    ]);

    $result = computeExpectedDimonaPeriods($employments);

    expect($result)->toHaveCount(1)
        ->and($result[0]->workerType)->toBe(WorkerType::Other);
});

it('resolves student worker type to Other when exception exists', function () {
    $startsAt = CarbonImmutable::parse('2025-10-01 07:00');

    // Create an exception for this worker
    DimonaWorkerTypeException::query()->create([
        'social_security_number' => WORKER_SSN,
        'worker_type' => WorkerType::Student,
        'starts_at' => $startsAt->startOfDay(),
        'ends_at' => $startsAt->endOfDay(),
    ]);

    $employments = new Collection([
        EmploymentDataFactory::new()
            ->id('employment-1')
            ->workerType(WorkerType::Student)
            ->startsAt($startsAt)
            ->endsAt('2025-10-01 12:00')
            ->create(),
    ]);

    $result = computeExpectedDimonaPeriods($employments);

    expect($result)->toHaveCount(1)
        ->and($result[0]->workerType)->toBe(WorkerType::Other);
});

it('keeps flexi worker type when no exception exists', function () {
    $startsAt = CarbonImmutable::parse('2025-10-01 07:00');

    // Make sure no exception exists
    DimonaWorkerTypeException::query()->where('social_security_number', WORKER_SSN)->delete();

    $employments = new Collection([
        EmploymentDataFactory::new()
            ->id('employment-1')
            ->workerType(WorkerType::Flexi)
            ->startsAt($startsAt)
            ->endsAt('2025-10-01 12:00')
            ->create(),
    ]);

    $result = computeExpectedDimonaPeriods($employments);

    expect($result)->toHaveCount(1)
        ->and($result[0]->workerType)->toBe(WorkerType::Flexi);
});

it('keeps student worker type when no exception exists', function () {
    $startsAt = CarbonImmutable::parse('2025-10-01 07:00');

    // Make sure no exception exists
    DimonaWorkerTypeException::query()->where('social_security_number', WORKER_SSN)->delete();

    $employments = new Collection([
        EmploymentDataFactory::new()
            ->id('employment-1')
            ->workerType(WorkerType::Student)
            ->startsAt($startsAt)
            ->endsAt('2025-10-01 12:00')
            ->create(),
    ]);

    $result = computeExpectedDimonaPeriods($employments);

    expect($result)->toHaveCount(1)
        ->and($result[0]->workerType)->toBe(WorkerType::Student);
});

it('keeps other worker type', function () {
    $employments = new Collection([
        EmploymentDataFactory::new()
            ->id('employment-1')
            ->workerType(WorkerType::Other)
            ->startsAt('2025-10-01 07:00')
            ->endsAt('2025-10-01 12:00')
            ->create(),
    ]);

    $result = computeExpectedDimonaPeriods($employments);

    expect($result)->toHaveCount(1)
        ->and($result[0]->workerType)->toBe(WorkerType::Other);
});

it('resolves worker type before grouping causing periods to be merged', function () {
    $startsAt = CarbonImmutable::parse('2025-10-01 07:00');

    // Create an exception for flexi worker only
    DimonaWorkerTypeException::query()->create([
        'social_security_number' => WORKER_SSN,
        'worker_type' => WorkerType::Flexi,
        'starts_at' => $startsAt->startOfDay(),
        'ends_at' => $startsAt->endOfDay(),
    ]);

    $baseFactory = EmploymentDataFactory::new()
        ->jointCommissionNumber(304);

    $employments = new Collection([
        // This one should be resolved to Other due to exception
        $baseFactory
            ->id('employment-1')
            ->startsAt($startsAt)
            ->endsAt('2025-10-01 12:00')
            ->workerType(WorkerType::Flexi)
            ->create(),
        $baseFactory
            ->id('employment-2')
            ->startsAt('2025-10-01 12:00')
            ->endsAt('2025-10-01 17:00')
            ->workerType(WorkerType::Other)
            ->create(),
    ]);

    $result = computeExpectedDimonaPeriods($employments);

    // Both employments are Flexi, both get resolved to Other, so they should be merged
    expect($result)->toHaveCount(1)
        ->and($result[0]->workerType)->toBe(WorkerType::Other)
        ->and($result[0]->employmentIds)->toBe(['employment-1', 'employment-2']);
});

it('resolves worker type only for employments within exception date range', function () {
    $day1StartsAt = CarbonImmutable::parse('2025-10-01 07:00');
    $day2StartsAt = CarbonImmutable::parse('2025-10-02 07:00');

    // Create an exception only for day 1
    DimonaWorkerTypeException::query()->create([
        'social_security_number' => WORKER_SSN,
        'worker_type' => WorkerType::Flexi,
        'starts_at' => $day1StartsAt->startOfDay(),
        'ends_at' => $day1StartsAt->endOfDay(),
    ]);

    $employments = new Collection([
        EmploymentDataFactory::new()
            ->id('employment-1')
            ->workerType(WorkerType::Flexi)
            ->startsAt($day1StartsAt)
            ->endsAt('2025-10-01 12:00')
            ->create(),
        EmploymentDataFactory::new()
            ->id('employment-2')
            ->workerType(WorkerType::Flexi)
            ->startsAt($day2StartsAt)
            ->endsAt('2025-10-02 12:00')
            ->create(),
    ]);

    $result = computeExpectedDimonaPeriods($employments);

    // Should create 2 groups: one with Other (day 1 resolved), one with Flexi (day 2 not resolved)
    expect($result)->toHaveCount(2)
        ->and($result[0]->workerType)->toBe(WorkerType::Other)
        ->and($result[0]->employmentIds)->toBe(['employment-1'])
        ->and($result[1]->workerType)->toBe(WorkerType::Flexi)
        ->and($result[1]->employmentIds)->toBe(['employment-2']);
});
