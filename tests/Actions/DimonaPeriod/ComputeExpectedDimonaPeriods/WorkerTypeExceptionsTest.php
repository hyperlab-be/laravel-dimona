<?php

use Carbon\CarbonImmutable;
use Hyperlab\Dimona\Actions\DimonaPeriod\ComputeExpectedDimonaPeriods;
use Hyperlab\Dimona\Enums\WorkerType;
use Hyperlab\Dimona\Models\DimonaWorkerTypeException;
use Hyperlab\Dimona\Tests\Factories\EmploymentDataFactory;
use Illuminate\Support\Collection;

require_once __DIR__.'/Helpers.php';

it('resolves flexi worker type to Other when exception exists', function () {
    $startsAt = CarbonImmutable::parse('2025-10-01 07:00');

    // Create an exception for this worker
    DimonaWorkerTypeException::create([
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

    $result = ComputeExpectedDimonaPeriods::new()->execute(EMPLOYER_ENTERPRISE_NUMBER, WORKER_SSN, $employments);

    expect($result)->toHaveCount(1)
        ->and($result[0]->workerType)->toBe(WorkerType::Other);
});

it('keeps student worker type when exception exists', function () {
    $startsAt = CarbonImmutable::parse('2025-10-01 07:00');

    // Create an exception for this worker
    DimonaWorkerTypeException::create([
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

    $result = ComputeExpectedDimonaPeriods::new()->execute(EMPLOYER_ENTERPRISE_NUMBER, WORKER_SSN, $employments);

    expect($result)->toHaveCount(1)
        ->and($result[0]->workerType)->toBe(WorkerType::Student);
});

it('keeps flexi worker type when no exception exists', function () {
    $startsAt = CarbonImmutable::parse('2025-10-01 07:00');

    // Make sure no exception exists
    DimonaWorkerTypeException::where('social_security_number', WORKER_SSN)->delete();

    $employments = new Collection([
        EmploymentDataFactory::new()
            ->id('employment-1')
            ->workerType(WorkerType::Flexi)
            ->startsAt($startsAt)
            ->endsAt('2025-10-01 12:00')
            ->create(),
    ]);

    $result = ComputeExpectedDimonaPeriods::new()->execute(EMPLOYER_ENTERPRISE_NUMBER, WORKER_SSN, $employments);

    expect($result)->toHaveCount(1)
        ->and($result[0]->workerType)->toBe(WorkerType::Flexi);
});

it('keeps student worker type when no exception exists', function () {
    $startsAt = CarbonImmutable::parse('2025-10-01 07:00');

    // Make sure no exception exists
    DimonaWorkerTypeException::where('social_security_number', WORKER_SSN)->delete();

    $employments = new Collection([
        EmploymentDataFactory::new()
            ->id('employment-1')
            ->workerType(WorkerType::Student)
            ->startsAt($startsAt)
            ->endsAt('2025-10-01 12:00')
            ->create(),
    ]);

    $result = ComputeExpectedDimonaPeriods::new()->execute(EMPLOYER_ENTERPRISE_NUMBER, WORKER_SSN, $employments);

    expect($result)->toHaveCount(1)
        ->and($result[0]->workerType)->toBe(WorkerType::Student);
});

it('does not resolve other worker types even when exception exists', function () {
    $startsAt = CarbonImmutable::parse('2025-10-01 07:00');

    // Create an exception for Other worker type (which shouldn't have any effect)
    DimonaWorkerTypeException::create([
        'social_security_number' => WORKER_SSN,
        'worker_type' => WorkerType::Other,
        'starts_at' => $startsAt->startOfDay(),
        'ends_at' => $startsAt->endOfDay(),
    ]);

    $employments = new Collection([
        EmploymentDataFactory::new()
            ->id('employment-1')
            ->workerType(WorkerType::Other)
            ->startsAt($startsAt)
            ->endsAt('2025-10-01 12:00')
            ->create(),
    ]);

    $result = ComputeExpectedDimonaPeriods::new()->execute(EMPLOYER_ENTERPRISE_NUMBER, WORKER_SSN, $employments);

    expect($result)->toHaveCount(1)
        ->and($result[0]->workerType)->toBe(WorkerType::Other);
});

it('resolves worker type before grouping causing periods to be separated', function () {
    $startsAt = CarbonImmutable::parse('2025-10-01 07:00');

    // Create an exception for flexi worker only
    DimonaWorkerTypeException::create([
        'social_security_number' => WORKER_SSN,
        'worker_type' => WorkerType::Flexi,
        'starts_at' => $startsAt->startOfDay(),
        'ends_at' => $startsAt->endOfDay(),
    ]);

    $baseFactory = EmploymentDataFactory::new()
        ->jointCommissionNumber(304)
        ->workerType(WorkerType::Flexi);

    $employments = new Collection([
        // This one should be resolved to Other due to exception
        $baseFactory
            ->id('employment-1')
            ->startsAt($startsAt)
            ->endsAt('2025-10-01 12:00')
            ->create(),
        // This one should also be resolved to Other (consecutive but different worker type after resolution)
        $baseFactory
            ->id('employment-2')
            ->startsAt('2025-10-01 12:00')
            ->endsAt('2025-10-01 17:00')
            ->create(),
    ]);

    $result = ComputeExpectedDimonaPeriods::new()->execute(EMPLOYER_ENTERPRISE_NUMBER, WORKER_SSN, $employments);

    // Both employments are Flexi, both get resolved to Other, so they should be merged
    expect($result)->toHaveCount(1)
        ->and($result[0]->workerType)->toBe(WorkerType::Other)
        ->and($result[0]->employmentIds)->toBe(['employment-1', 'employment-2']);
});

it('resolves worker type only for employments within exception date range', function () {
    $day1StartsAt = CarbonImmutable::parse('2025-10-01 07:00');
    $day2StartsAt = CarbonImmutable::parse('2025-10-02 07:00');

    // Create an exception only for day 1
    DimonaWorkerTypeException::create([
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

    $result = ComputeExpectedDimonaPeriods::new()->execute(EMPLOYER_ENTERPRISE_NUMBER, WORKER_SSN, $employments);

    // Should create 2 groups: one with Other (day 1 resolved), one with Flexi (day 2 not resolved)
    expect($result)->toHaveCount(2)
        ->and($result[0]->workerType)->toBe(WorkerType::Other)
        ->and($result[0]->employmentIds)->toBe(['employment-1'])
        ->and($result[1]->workerType)->toBe(WorkerType::Flexi)
        ->and($result[1]->employmentIds)->toBe(['employment-2']);
});
