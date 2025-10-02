<?php

use Carbon\CarbonImmutable;
use Hyperlab\Dimona\Actions\DimonaPeriod\ComputeDimonaPeriods;
use Hyperlab\Dimona\Enums\WorkerType;
use Hyperlab\Dimona\Models\DimonaWorkerTypeException;
use Hyperlab\Dimona\Tests\Factories\EmploymentDataFactory;
use Illuminate\Support\Collection;

const EMPLOYER_ENTERPRISE_NUMBER = '0123456789';
const WORKER_SSN = '12345678901';

it('merges consecutive employments into single dimona period', function () {
    $baseFactory = EmploymentDataFactory::new()
        ->jointCommissionNumber(304)
        ->workerType(WorkerType::Flexi);

    $employments = new Collection([
        $baseFactory
            ->id('employment-1')
            ->startsAt(CarbonImmutable::parse('2025-10-01 07:00'))
            ->endsAt(CarbonImmutable::parse('2025-10-01 12:00'))
            ->create(),
        $baseFactory
            ->id('employment-2')
            ->startsAt(CarbonImmutable::parse('2025-10-01 12:00'))
            ->endsAt(CarbonImmutable::parse('2025-10-01 17:00'))
            ->create(),
    ]);

    $result = ComputeDimonaPeriods::new()->execute(EMPLOYER_ENTERPRISE_NUMBER, WORKER_SSN, $employments);

    expect($result)->toHaveCount(1)
        ->and($result[0])->toHaveCount(1)
        ->and($result[0][0]->employmentIds)->toBe(['employment-1', 'employment-2'])
        ->and($result[0][0]->startsAt)->toEqual(CarbonImmutable::parse('2025-10-01 07:00'))
        ->and($result[0][0]->endsAt)->toEqual(CarbonImmutable::parse('2025-10-01 17:00'));
});

it('creates separate periods for non-consecutive employments', function () {
    $baseFactory = EmploymentDataFactory::new()
        ->jointCommissionNumber(304)
        ->workerType(WorkerType::Flexi);

    $employments = new Collection([
        $baseFactory
            ->id('employment-1')
            ->startsAt(CarbonImmutable::parse('2025-10-01 07:00'))
            ->endsAt(CarbonImmutable::parse('2025-10-01 12:00'))
            ->create(),
        $baseFactory
            ->id('employment-2')
            ->startsAt(CarbonImmutable::parse('2025-10-01 13:00'))
            ->endsAt(CarbonImmutable::parse('2025-10-01 17:00'))
            ->create(),
    ]);

    $result = ComputeDimonaPeriods::new()->execute(EMPLOYER_ENTERPRISE_NUMBER, WORKER_SSN, $employments);

    expect($result)->toHaveCount(1)
        ->and($result[0])->toHaveCount(2)
        ->and($result[0][0]->employmentIds)->toBe(['employment-1'])
        ->and($result[0][0]->startsAt)->toEqual(CarbonImmutable::parse('2025-10-01 07:00'))
        ->and($result[0][0]->endsAt)->toEqual(CarbonImmutable::parse('2025-10-01 12:00'))
        ->and($result[0][1]->employmentIds)->toBe(['employment-2'])
        ->and($result[0][1]->startsAt)->toEqual(CarbonImmutable::parse('2025-10-01 13:00'))
        ->and($result[0][1]->endsAt)->toEqual(CarbonImmutable::parse('2025-10-01 17:00'));
});

it('merges multiple consecutive employments', function () {
    $baseFactory = EmploymentDataFactory::new()
        ->jointCommissionNumber(304)
        ->workerType(WorkerType::Flexi);

    $employments = new Collection([
        $baseFactory
            ->id('employment-1')
            ->startsAt(CarbonImmutable::parse('2025-10-01 07:00'))
            ->endsAt(CarbonImmutable::parse('2025-10-01 12:00'))
            ->create(),
        $baseFactory
            ->id('employment-2')
            ->startsAt(CarbonImmutable::parse('2025-10-01 12:00'))
            ->endsAt(CarbonImmutable::parse('2025-10-01 15:00'))
            ->create(),
        $baseFactory
            ->id('employment-3')
            ->startsAt(CarbonImmutable::parse('2025-10-01 15:00'))
            ->endsAt(CarbonImmutable::parse('2025-10-01 18:00'))
            ->create(),
    ]);

    $result = ComputeDimonaPeriods::new()->execute(EMPLOYER_ENTERPRISE_NUMBER, WORKER_SSN, $employments);

    expect($result)->toHaveCount(1)
        ->and($result[0])->toHaveCount(1)
        ->and($result[0][0]->employmentIds)->toBe(['employment-1', 'employment-2', 'employment-3'])
        ->and($result[0][0]->startsAt)->toEqual(CarbonImmutable::parse('2025-10-01 07:00'))
        ->and($result[0][0]->endsAt)->toEqual(CarbonImmutable::parse('2025-10-01 18:00'));
});

it('separates periods by different worker types', function () {
    $employments = new Collection([
        EmploymentDataFactory::new()
            ->id('employment-1')
            ->workerType(WorkerType::Flexi)
            ->startsAt(CarbonImmutable::parse('2025-10-01 07:00'))
            ->endsAt(CarbonImmutable::parse('2025-10-01 12:00'))
            ->create(),
        EmploymentDataFactory::new()
            ->id('employment-2')
            ->workerType(WorkerType::Student)
            ->startsAt(CarbonImmutable::parse('2025-10-01 12:00'))
            ->endsAt(CarbonImmutable::parse('2025-10-01 17:00'))
            ->create(),
    ]);

    $result = ComputeDimonaPeriods::new()->execute(EMPLOYER_ENTERPRISE_NUMBER, WORKER_SSN, $employments);

    expect($result)->toHaveCount(2)
        ->and($result[0][0]->workerType)->toBe(WorkerType::Flexi)
        ->and($result[1][0]->workerType)->toBe(WorkerType::Student);
});

it('separates periods by different dates', function () {
    $employments = new Collection([
        EmploymentDataFactory::new()
            ->id('employment-1')
            ->startsAt(CarbonImmutable::parse('2025-10-01 07:00'))
            ->endsAt(CarbonImmutable::parse('2025-10-01 12:00'))
            ->create(),
        EmploymentDataFactory::new()
            ->id('employment-2')
            ->startsAt(CarbonImmutable::parse('2025-10-02 12:00'))
            ->endsAt(CarbonImmutable::parse('2025-10-02 17:00'))
            ->create(),
    ]);

    $result = ComputeDimonaPeriods::new()->execute(EMPLOYER_ENTERPRISE_NUMBER, WORKER_SSN, $employments);

    expect($result)->toHaveCount(2);
});

it('handles unordered employments', function () {
    $baseFactory = EmploymentDataFactory::new()
        ->jointCommissionNumber(304)
        ->workerType(WorkerType::Flexi);

    $employments = new Collection([
        $baseFactory
            ->id('employment-2')
            ->startsAt(CarbonImmutable::parse('2025-10-01 12:00'))
            ->endsAt(CarbonImmutable::parse('2025-10-01 17:00'))
            ->create(),
        $baseFactory
            ->id('employment-1')
            ->startsAt(CarbonImmutable::parse('2025-10-01 07:00'))
            ->endsAt(CarbonImmutable::parse('2025-10-01 12:00'))
            ->create(),
    ]);

    $result = ComputeDimonaPeriods::new()->execute(EMPLOYER_ENTERPRISE_NUMBER, WORKER_SSN, $employments);

    expect($result)->toHaveCount(1)
        ->and($result[0])->toHaveCount(1)
        ->and($result[0][0]->employmentIds)->toBe(['employment-1', 'employment-2'])
        ->and($result[0][0]->startsAt)->toEqual(CarbonImmutable::parse('2025-10-01 07:00'))
        ->and($result[0][0]->endsAt)->toEqual(CarbonImmutable::parse('2025-10-01 17:00'));
});

it('handles empty collection', function () {
    $employments = new Collection([]);

    $result = ComputeDimonaPeriods::new()->execute(EMPLOYER_ENTERPRISE_NUMBER, WORKER_SSN, $employments);

    expect($result)->toBeEmpty();
});

it('handles single employment', function () {
    $employment = EmploymentDataFactory::new()
        ->id('employment-1')
        ->startsAt(CarbonImmutable::parse('2025-10-01 07:00'))
        ->endsAt(CarbonImmutable::parse('2025-10-01 12:00'))
        ->create();

    $result = ComputeDimonaPeriods::new()->execute(EMPLOYER_ENTERPRISE_NUMBER, WORKER_SSN, new Collection([$employment]));

    expect($result)->toHaveCount(1)
        ->and($result[0])->toHaveCount(1)
        ->and($result[0][0]->employmentIds)->toBe(['employment-1'])
        ->and($result[0][0]->startsAt)->toEqual(CarbonImmutable::parse('2025-10-01 07:00'))
        ->and($result[0][0]->endsAt)->toEqual(CarbonImmutable::parse('2025-10-01 12:00'));
});

it('separates periods by different joint commission numbers', function () {
    $baseFactory = EmploymentDataFactory::new()
        ->workerType(WorkerType::Flexi);

    $employments = new Collection([
        $baseFactory
            ->id('employment-1')
            ->jointCommissionNumber(304)
            ->startsAt(CarbonImmutable::parse('2025-10-01 07:00'))
            ->endsAt(CarbonImmutable::parse('2025-10-01 12:00'))
            ->create(),
        $baseFactory
            ->id('employment-2')
            ->jointCommissionNumber(302)
            ->startsAt(CarbonImmutable::parse('2025-10-01 12:00'))
            ->endsAt(CarbonImmutable::parse('2025-10-01 17:00'))
            ->create(),
    ]);

    $result = ComputeDimonaPeriods::new()->execute(EMPLOYER_ENTERPRISE_NUMBER, WORKER_SSN, $employments);

    expect($result)->toHaveCount(2)
        ->and($result[0][0]->jointCommissionNumber)->toBe(304)
        ->and($result[1][0]->jointCommissionNumber)->toBe(302);
});

it('merges employments across midnight when start date is same', function () {
    $baseFactory = EmploymentDataFactory::new()
        ->jointCommissionNumber(304)
        ->workerType(WorkerType::Flexi);

    $employments = new Collection([
        $baseFactory
            ->id('employment-1')
            ->startsAt(CarbonImmutable::parse('2025-10-01 22:00'))
            ->endsAt(CarbonImmutable::parse('2025-10-01 23:59'))
            ->create(),
        $baseFactory
            ->id('employment-2')
            ->startsAt(CarbonImmutable::parse('2025-10-01 23:59'))
            ->endsAt(CarbonImmutable::parse('2025-10-02 02:00'))
            ->create(),
    ]);

    $result = ComputeDimonaPeriods::new()->execute(EMPLOYER_ENTERPRISE_NUMBER, WORKER_SSN, $employments);

    // Note: Grouped by start date (Y-m-d), both start on 2025-10-01 so same group
    expect($result)->toHaveCount(1)
        ->and($result[0])->toHaveCount(1)
        ->and($result[0][0]->employmentIds)->toBe(['employment-1', 'employment-2'])
        ->and($result[0][0]->startsAt)->toEqual(CarbonImmutable::parse('2025-10-01 22:00'))
        ->and($result[0][0]->endsAt)->toEqual(CarbonImmutable::parse('2025-10-02 02:00'));
});

it('handles complex scenario with multiple groups and gaps', function () {
    $factory1 = EmploymentDataFactory::new()
        ->jointCommissionNumber(304)
        ->workerType(WorkerType::Flexi);

    $factory2 = EmploymentDataFactory::new()
        ->jointCommissionNumber(302)
        ->workerType(WorkerType::Student);

    $employments = new Collection([
        // Employer 1 - consecutive
        $factory1->id('e1-1')->startsAt(CarbonImmutable::parse('2025-10-01 07:00'))->endsAt(CarbonImmutable::parse('2025-10-01 12:00'))->create(),
        $factory1->id('e1-2')->startsAt(CarbonImmutable::parse('2025-10-01 12:00'))->endsAt(CarbonImmutable::parse('2025-10-01 15:00'))->create(),
        // Employer 1 - gap
        $factory1->id('e1-3')->startsAt(CarbonImmutable::parse('2025-10-01 16:00'))->endsAt(CarbonImmutable::parse('2025-10-01 20:00'))->create(),
        // Employer 2 - single
        $factory2->id('e2-1')->startsAt(CarbonImmutable::parse('2025-10-01 08:00'))->endsAt(CarbonImmutable::parse('2025-10-01 14:00'))->create(),
    ]);

    $result = ComputeDimonaPeriods::new()->execute(EMPLOYER_ENTERPRISE_NUMBER, WORKER_SSN, $employments);

    expect($result)->toHaveCount(2)
        ->and($result[0])->toHaveCount(2)
        ->and($result[0][0]->employmentIds)->toBe(['e1-1', 'e1-2'])
        ->and($result[0][1]->employmentIds)->toBe(['e1-3'])
        ->and($result[1])->toHaveCount(1)
        ->and($result[1][0]->employmentIds)->toBe(['e2-1']);
});

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
            ->endsAt(CarbonImmutable::parse('2025-10-01 12:00'))
            ->create(),
    ]);

    $result = ComputeDimonaPeriods::new()->execute(EMPLOYER_ENTERPRISE_NUMBER, WORKER_SSN, $employments);

    expect($result)->toHaveCount(1)
        ->and($result[0])->toHaveCount(1)
        ->and($result[0][0]->workerType)->toBe(WorkerType::Other);
});

it('resolves student worker type to Other when exception exists', function () {
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
            ->endsAt(CarbonImmutable::parse('2025-10-01 12:00'))
            ->create(),
    ]);

    $result = ComputeDimonaPeriods::new()->execute(EMPLOYER_ENTERPRISE_NUMBER, WORKER_SSN, $employments);

    expect($result)->toHaveCount(1)
        ->and($result[0])->toHaveCount(1)
        ->and($result[0][0]->workerType)->toBe(WorkerType::Other);
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
            ->endsAt(CarbonImmutable::parse('2025-10-01 12:00'))
            ->create(),
    ]);

    $result = ComputeDimonaPeriods::new()->execute(EMPLOYER_ENTERPRISE_NUMBER, WORKER_SSN, $employments);

    expect($result)->toHaveCount(1)
        ->and($result[0])->toHaveCount(1)
        ->and($result[0][0]->workerType)->toBe(WorkerType::Flexi);
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
            ->endsAt(CarbonImmutable::parse('2025-10-01 12:00'))
            ->create(),
    ]);

    $result = ComputeDimonaPeriods::new()->execute(EMPLOYER_ENTERPRISE_NUMBER, WORKER_SSN, $employments);

    expect($result)->toHaveCount(1)
        ->and($result[0])->toHaveCount(1)
        ->and($result[0][0]->workerType)->toBe(WorkerType::Student);
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
            ->endsAt(CarbonImmutable::parse('2025-10-01 12:00'))
            ->create(),
    ]);

    $result = ComputeDimonaPeriods::new()->execute(EMPLOYER_ENTERPRISE_NUMBER, WORKER_SSN, $employments);

    expect($result)->toHaveCount(1)
        ->and($result[0])->toHaveCount(1)
        ->and($result[0][0]->workerType)->toBe(WorkerType::Other);
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
            ->endsAt(CarbonImmutable::parse('2025-10-01 12:00'))
            ->create(),
        // This one should also be resolved to Other (consecutive but different worker type after resolution)
        $baseFactory
            ->id('employment-2')
            ->startsAt(CarbonImmutable::parse('2025-10-01 12:00'))
            ->endsAt(CarbonImmutable::parse('2025-10-01 17:00'))
            ->create(),
    ]);

    $result = ComputeDimonaPeriods::new()->execute(EMPLOYER_ENTERPRISE_NUMBER, WORKER_SSN, $employments);

    // Both employments are Flexi, both get resolved to Other, so they should be merged
    expect($result)->toHaveCount(1)
        ->and($result[0])->toHaveCount(1)
        ->and($result[0][0]->workerType)->toBe(WorkerType::Other)
        ->and($result[0][0]->employmentIds)->toBe(['employment-1', 'employment-2']);
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
            ->endsAt(CarbonImmutable::parse('2025-10-01 12:00'))
            ->create(),
        EmploymentDataFactory::new()
            ->id('employment-2')
            ->workerType(WorkerType::Flexi)
            ->startsAt($day2StartsAt)
            ->endsAt(CarbonImmutable::parse('2025-10-02 12:00'))
            ->create(),
    ]);

    $result = ComputeDimonaPeriods::new()->execute(EMPLOYER_ENTERPRISE_NUMBER, WORKER_SSN, $employments);

    // Should create 2 groups: one with Other (day 1 resolved), one with Flexi (day 2 not resolved)
    expect($result)->toHaveCount(2)
        ->and($result[0][0]->workerType)->toBe(WorkerType::Other)
        ->and($result[0][0]->employmentIds)->toBe(['employment-1'])
        ->and($result[1][0]->workerType)->toBe(WorkerType::Flexi)
        ->and($result[1][0]->employmentIds)->toBe(['employment-2']);
});
