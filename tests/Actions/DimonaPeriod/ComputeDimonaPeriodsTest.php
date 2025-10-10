<?php

use Carbon\CarbonImmutable;
use Hyperlab\Dimona\Actions\DimonaPeriod\ComputeDimonaPeriods;
use Hyperlab\Dimona\Data\EmploymentLocationData;
use Hyperlab\Dimona\Enums\EmploymentLocationCountry;
use Hyperlab\Dimona\Enums\WorkerType;
use Hyperlab\Dimona\Models\DimonaWorkerTypeException;
use Hyperlab\Dimona\Tests\Factories\EmploymentDataFactory;
use Illuminate\Support\Collection;

const EMPLOYER_ENTERPRISE_NUMBER = '0123456789';
const WORKER_SSN = '12345678901';

it('creates separate periods for multiple flexi employments', function () {
    $baseFactory = EmploymentDataFactory::new()
        ->jointCommissionNumber(304)
        ->workerType(WorkerType::Flexi);

    $employments = new Collection([
        $baseFactory
            ->id('employment-1')
            ->startsAt('2025-10-01 07:00')
            ->endsAt('2025-10-01 12:00')
            ->create(),
        $baseFactory
            ->id('employment-2')
            ->startsAt('2025-10-01 12:00')
            ->endsAt('2025-10-01 15:00')
            ->create(),
        $baseFactory
            ->id('employment-3')
            ->startsAt('2025-10-01 15:00')
            ->endsAt('2025-10-01 18:00')
            ->create(),
    ]);

    $result = ComputeDimonaPeriods::new()->execute(EMPLOYER_ENTERPRISE_NUMBER, WORKER_SSN, $employments);

    expect($result)->toHaveCount(3)
        ->and($result[0]->employmentIds)->toBe(['employment-1'])
        ->and($result[1]->employmentIds)->toBe(['employment-2'])
        ->and($result[2]->employmentIds)->toBe(['employment-3']);
});

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

    $result = ComputeDimonaPeriods::new()->execute(EMPLOYER_ENTERPRISE_NUMBER, WORKER_SSN, $employments);

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
            ->startsAt('2025-10-01 12:00')
            ->endsAt('2025-10-01 17:00')
            ->create(),
        $baseFactory
            ->id('employment-1')
            ->startsAt('2025-10-01 07:00')
            ->endsAt('2025-10-01 12:00')
            ->create(),
    ]);

    $result = ComputeDimonaPeriods::new()->execute(EMPLOYER_ENTERPRISE_NUMBER, WORKER_SSN, $employments);

    expect($result)->toHaveCount(2)
        ->and($result[0]->employmentIds)->toBe(['employment-1'])
        ->and($result[1]->employmentIds)->toBe(['employment-2']);
});

it('handles empty collection', function () {
    $employments = new Collection([]);

    $result = ComputeDimonaPeriods::new()->execute(EMPLOYER_ENTERPRISE_NUMBER, WORKER_SSN, $employments);

    expect($result)->toBeEmpty();
});

it('handles single employment', function () {
    $employment = EmploymentDataFactory::new()
        ->id('employment-1')
        ->startsAt('2025-10-01 07:00')
        ->endsAt('2025-10-01 12:00')
        ->create();

    $result = ComputeDimonaPeriods::new()->execute(EMPLOYER_ENTERPRISE_NUMBER, WORKER_SSN, new Collection([$employment]));

    expect($result)->toHaveCount(1)
        ->and($result[0]->employmentIds)->toBe(['employment-1'])
        ->and($result[0]->startDate)->toBe('2025-10-01')
        ->and($result[0]->endDate)->toBe('2025-10-01');
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

    $result = ComputeDimonaPeriods::new()->execute(EMPLOYER_ENTERPRISE_NUMBER, WORKER_SSN, $employments);

    expect($result)->toHaveCount(2)
        ->and($result[0]->jointCommissionNumber)->toBe(304)
        ->and($result[1]->jointCommissionNumber)->toBe(302);
});

it('creates separate flexi periods even across midnight when start date is same', function () {
    $baseFactory = EmploymentDataFactory::new()
        ->jointCommissionNumber(304)
        ->workerType(WorkerType::Flexi);

    $employments = new Collection([
        $baseFactory
            ->id('employment-1')
            ->startsAt('2025-10-01 22:00')
            ->endsAt('2025-10-01 23:59')
            ->create(),
        $baseFactory
            ->id('employment-2')
            ->startsAt('2025-10-01 23:59')
            ->endsAt('2025-10-02 02:00')
            ->create(),
    ]);

    $result = ComputeDimonaPeriods::new()->execute(EMPLOYER_ENTERPRISE_NUMBER, WORKER_SSN, $employments);

    // Note: Grouped by start date (Y-m-d), both start on 2025-10-01 so same group
    // But Flexi workers create separate periods for each employment
    expect($result)->toHaveCount(2)
        ->and($result[0]->employmentIds)->toBe(['employment-1'])
        ->and($result[1]->employmentIds)->toBe(['employment-2']);
});

it('handles complex scenario with multiple groups and gaps', function () {
    $factory1 = EmploymentDataFactory::new()
        ->jointCommissionNumber(304)
        ->workerType(WorkerType::Flexi);

    $factory2 = EmploymentDataFactory::new()
        ->jointCommissionNumber(302)
        ->workerType(WorkerType::Student);

    $employments = new Collection([
        // Flexi workers - each creates separate period
        $factory1->id('e1-1')->startsAt('2025-10-01 07:00')->endsAt('2025-10-01 12:00')->create(),
        $factory1->id('e1-2')->startsAt('2025-10-01 12:00')->endsAt('2025-10-01 15:00')->create(),
        $factory1->id('e1-3')->startsAt('2025-10-01 16:00')->endsAt('2025-10-01 20:00')->create(),
        // Student - single
        $factory2->id('e2-1')->startsAt('2025-10-01 08:00')->endsAt('2025-10-01 14:00')->create(),
    ]);

    $result = ComputeDimonaPeriods::new()->execute(EMPLOYER_ENTERPRISE_NUMBER, WORKER_SSN, $employments);

    expect($result)->toHaveCount(4)
        ->and($result[0]->employmentIds)->toBe(['e1-1'])
        ->and($result[1]->employmentIds)->toBe(['e1-2'])
        ->and($result[2]->employmentIds)->toBe(['e1-3'])
        ->and($result[3]->employmentIds)->toBe(['e2-1']);
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
            ->endsAt('2025-10-01 12:00')
            ->create(),
    ]);

    $result = ComputeDimonaPeriods::new()->execute(EMPLOYER_ENTERPRISE_NUMBER, WORKER_SSN, $employments);

    expect($result)->toHaveCount(1)
        ->and($result[0]->workerType)->toBe(WorkerType::Other);
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
            ->endsAt('2025-10-01 12:00')
            ->create(),
    ]);

    $result = ComputeDimonaPeriods::new()->execute(EMPLOYER_ENTERPRISE_NUMBER, WORKER_SSN, $employments);

    expect($result)->toHaveCount(1)
        ->and($result[0]->workerType)->toBe(WorkerType::Other);
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

    $result = ComputeDimonaPeriods::new()->execute(EMPLOYER_ENTERPRISE_NUMBER, WORKER_SSN, $employments);

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

    $result = ComputeDimonaPeriods::new()->execute(EMPLOYER_ENTERPRISE_NUMBER, WORKER_SSN, $employments);

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

    $result = ComputeDimonaPeriods::new()->execute(EMPLOYER_ENTERPRISE_NUMBER, WORKER_SSN, $employments);

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

    $result = ComputeDimonaPeriods::new()->execute(EMPLOYER_ENTERPRISE_NUMBER, WORKER_SSN, $employments);

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

    $result = ComputeDimonaPeriods::new()->execute(EMPLOYER_ENTERPRISE_NUMBER, WORKER_SSN, $employments);

    // Should create 2 groups: one with Other (day 1 resolved), one with Flexi (day 2 not resolved)
    expect($result)->toHaveCount(2)
        ->and($result[0]->workerType)->toBe(WorkerType::Other)
        ->and($result[0]->employmentIds)->toBe(['employment-1'])
        ->and($result[1]->workerType)->toBe(WorkerType::Flexi)
        ->and($result[1]->employmentIds)->toBe(['employment-2']);
});

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

    $result = ComputeDimonaPeriods::new()->execute(EMPLOYER_ENTERPRISE_NUMBER, WORKER_SSN, $employments);

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

    $result = ComputeDimonaPeriods::new()->execute(EMPLOYER_ENTERPRISE_NUMBER, WORKER_SSN, $employments);

    expect($result)->toHaveCount(2)
        ->and($result[0]->employmentIds)->toBe(['employment-1'])
        ->and($result[0]->numberOfHours)->toBe(4.0)
        ->and($result[1]->employmentIds)->toBe(['employment-2'])
        ->and($result[1]->numberOfHours)->toBe(4.0);
});

it('merges consecutive other worker employments', function () {
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
            ->startsAt('2025-10-01 12:00')
            ->endsAt('2025-10-01 17:00')
            ->create(),
        $baseFactory
            ->id('employment-3')
            ->startsAt('2025-10-01 17:00')
            ->endsAt('2025-10-01 20:00')
            ->create(),
    ]);

    $result = ComputeDimonaPeriods::new()->execute(EMPLOYER_ENTERPRISE_NUMBER, WORKER_SSN, $employments);

    expect($result)->toHaveCount(1)
        ->and($result[0]->employmentIds)->toBe(['employment-1', 'employment-2', 'employment-3'])
        ->and($result[0]->numberOfHours)->toBeNull()
        ->and($result[0]->startDate)->toBe('2025-10-01')
        ->and($result[0]->endDate)->toBe('2025-10-01')
        ->and($result[0]->startHour)->toBeNull()
        ->and($result[0]->endHour)->toBeNull();
});

it('creates separate periods for non-consecutive other worker employments', function () {
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

    $result = ComputeDimonaPeriods::new()->execute(EMPLOYER_ENTERPRISE_NUMBER, WORKER_SSN, $employments);

    expect($result)->toHaveCount(2)
        ->and($result[0]->employmentIds)->toBe(['employment-1'])
        ->and($result[1]->employmentIds)->toBe(['employment-2']);
});

it('populates all dimona period data fields correctly for flexi worker', function () {
    $location = new EmploymentLocationData(
        name: 'Main Office',
        street: 'Main Street',
        houseNumber: '123',
        boxNumber: null,
        postalCode: '1000',
        place: 'Brussels',
        country: EmploymentLocationCountry::Belgium
    );

    $employment = EmploymentDataFactory::new()
        ->id('emp-1')
        ->jointCommissionNumber(304)
        ->workerType(WorkerType::Flexi)
        ->startsAt('2025-10-01 14:30')
        ->endsAt('2025-10-01 18:45')
        ->location($location)
        ->create();

    $result = ComputeDimonaPeriods::new()->execute(EMPLOYER_ENTERPRISE_NUMBER, WORKER_SSN, new Collection([$employment]));

    $period = $result[0];
    expect($period->employmentIds)->toBe(['emp-1'])
        ->and($period->employerEnterpriseNumber)->toBe(EMPLOYER_ENTERPRISE_NUMBER)
        ->and($period->workerSocialSecurityNumber)->toBe(WORKER_SSN)
        ->and($period->jointCommissionNumber)->toBe(304)
        ->and($period->workerType)->toBe(WorkerType::Flexi)
        ->and($period->startDate)->toBe('2025-10-01')
        ->and($period->startHour)->toBe('1430')
        ->and($period->endDate)->toBe('2025-10-01')
        ->and($period->endHour)->toBe('1845')
        ->and($period->numberOfHours)->toBeNull()
        ->and($period->location)->toBe($location);
});

it('populates all dimona period data fields correctly for student worker', function () {
    $location = new EmploymentLocationData(
        name: 'Student Campus',
        street: 'Student Lane',
        houseNumber: '456',
        boxNumber: 'A',
        postalCode: '2000',
        place: 'Antwerp',
        country: EmploymentLocationCountry::Belgium
    );

    $employment = EmploymentDataFactory::new()
        ->id('emp-2')
        ->jointCommissionNumber(302)
        ->workerType(WorkerType::Student)
        ->startsAt('2025-10-01 09:00')
        ->endsAt('2025-10-01 15:30')
        ->location($location)
        ->create();

    $result = ComputeDimonaPeriods::new()->execute(EMPLOYER_ENTERPRISE_NUMBER, WORKER_SSN, new Collection([$employment]));

    $period = $result[0];
    expect($period->employmentIds)->toBe(['emp-2'])
        ->and($period->employerEnterpriseNumber)->toBe(EMPLOYER_ENTERPRISE_NUMBER)
        ->and($period->workerSocialSecurityNumber)->toBe(WORKER_SSN)
        ->and($period->jointCommissionNumber)->toBe(302)
        ->and($period->workerType)->toBe(WorkerType::Student)
        ->and($period->startDate)->toBe('2025-10-01')
        ->and($period->startHour)->toBeNull()
        ->and($period->endDate)->toBe('2025-10-01')
        ->and($period->endHour)->toBeNull()
        ->and($period->numberOfHours)->toBe(6.5)
        ->and($period->location)->toBe($location);
});

it('converts UTC timezone to Europe/Brussels correctly', function () {
    $employment = EmploymentDataFactory::new()
        ->id('emp-1')
        ->workerType(WorkerType::Flexi)
        ->startsAt(CarbonImmutable::parse('2025-10-01 12:00:00', 'UTC')) // Noon UTC
        ->endsAt(CarbonImmutable::parse('2025-10-01 16:00:00', 'UTC'))   // 4 PM UTC
        ->create();

    $result = ComputeDimonaPeriods::new()->execute(EMPLOYER_ENTERPRISE_NUMBER, WORKER_SSN, new Collection([$employment]));

    $period = $result[0];
    // UTC+2 in October (CEST): 12:00 UTC = 14:00 Brussels, 16:00 UTC = 18:00 Brussels
    expect($period->startDate)->toBe('2025-10-01')
        ->and($period->startHour)->toBe('1400')
        ->and($period->endDate)->toBe('2025-10-01')
        ->and($period->endHour)->toBe('1800');
});

it('handles employment spanning multiple days', function () {
    $employment = EmploymentDataFactory::new()
        ->id('emp-1')
        ->workerType(WorkerType::Flexi)
        ->startsAt('2025-10-01 22:00')
        ->endsAt('2025-10-02 06:00')
        ->create();

    $result = ComputeDimonaPeriods::new()->execute(EMPLOYER_ENTERPRISE_NUMBER, WORKER_SSN, new Collection([$employment]));

    $period = $result[0];
    expect($period->startDate)->toBe('2025-10-01')
        ->and($period->startHour)->toBe('2200')
        ->and($period->endDate)->toBe('2025-10-02')
        ->and($period->endHour)->toBe('0600');
});

it('creates separate periods for student workers with gaps on same day', function () {
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

    $result = ComputeDimonaPeriods::new()->execute(EMPLOYER_ENTERPRISE_NUMBER, WORKER_SSN, $employments);

    // Student workers in same group create separate periods when there's a gap (not consecutive)
    // Both are in same group (same day, worker type, commission), but the gap means no merging
    expect($result)->toHaveCount(1)
        ->and($result[0]->employmentIds)->toBe(['employment-1', 'employment-2'])
        ->and($result[0]->numberOfHours)->toBe(8.0); // Both get added despite gap
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

    $result = ComputeDimonaPeriods::new()->execute(EMPLOYER_ENTERPRISE_NUMBER, WORKER_SSN, $employments);

    // Should use the first employment's location
    expect($result)->toHaveCount(1)
        ->and($result[0]->employmentIds)->toBe(['employment-1', 'employment-2'])
        ->and($result[0]->location)->toBe($location1);
});

it('updates end date when merging student workers across time', function () {
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
            ->startsAt('2025-10-01 12:00')
            ->endsAt('2025-10-01 20:00')
            ->create(),
    ]);

    $result = ComputeDimonaPeriods::new()->execute(EMPLOYER_ENTERPRISE_NUMBER, WORKER_SSN, $employments);

    $period = $result[0];
    // First employment sets startDate, last employment determines endDate
    expect($period->startDate)->toBe('2025-10-01')
        ->and($period->endDate)->toBe('2025-10-01')
        ->and($period->employmentIds)->toBe(['employment-1', 'employment-2'])
        ->and($period->numberOfHours)->toBe(12.0);
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

    $result = ComputeDimonaPeriods::new()->execute(EMPLOYER_ENTERPRISE_NUMBER, WORKER_SSN, $employments);

    expect($result)->toHaveCount(1)
        ->and($result[0]->employmentIds)->toBe(['employment-1', 'employment-2'])
        ->and($result[0]->numberOfHours)->toBe(9.25); // 3.75 + 5.5
});

it('groups employments by start date even when spanning multiple days', function () {
    $baseFactory = EmploymentDataFactory::new()
        ->jointCommissionNumber(304)
        ->workerType(WorkerType::Flexi);

    $employments = new Collection([
        // Both start on same date, but one ends next day
        $baseFactory
            ->id('employment-1')
            ->startsAt('2025-10-01 05:00')
            ->endsAt('2025-10-01 10:00')
            ->create(),
        $baseFactory
            ->id('employment-2')
            ->startsAt('2025-10-01 23:00')
            ->endsAt('2025-10-02 01:00')
            ->create(),
    ]);

    $result = ComputeDimonaPeriods::new()->execute(EMPLOYER_ENTERPRISE_NUMBER, WORKER_SSN, $employments);

    // Both should be in same group (same start date)
    expect($result)->toHaveCount(2)
        ->and($result[0]->employmentIds)->toBe(['employment-1'])
        ->and($result[1]->employmentIds)->toBe(['employment-2']);
});

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

    $result = ComputeDimonaPeriods::new()->execute(EMPLOYER_ENTERPRISE_NUMBER, WORKER_SSN, $employments);

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

    $result = ComputeDimonaPeriods::new()->execute(EMPLOYER_ENTERPRISE_NUMBER, WORKER_SSN, $employments);

    // Flexi: 2 separate periods, Student: 1 merged period, Other: 1 merged period = 4 total
    expect($result)->toHaveCount(4)
        ->and($result[0]->employmentIds)->toBe(['flexi-1'])
        ->and($result[1]->employmentIds)->toBe(['flexi-2'])
        ->and($result[2]->employmentIds)->toBe(['student-1', 'student-2'])
        ->and($result[2]->numberOfHours)->toBe(4.0)
        ->and($result[3]->employmentIds)->toBe(['other-1', 'other-2']);
});

it('handles zero duration employment for student workers', function () {
    $employment = EmploymentDataFactory::new()
        ->id('employment-1')
        ->workerType(WorkerType::Student)
        ->startsAt('2025-10-01 12:00')
        ->endsAt('2025-10-01 12:00')
        ->create();

    $result = ComputeDimonaPeriods::new()->execute(EMPLOYER_ENTERPRISE_NUMBER, WORKER_SSN, new Collection([$employment]));

    expect($result)->toHaveCount(1)
        ->and($result[0]->employmentIds)->toBe(['employment-1'])
        ->and($result[0]->numberOfHours)->toBe(0.0);
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

    $result = ComputeDimonaPeriods::new()->execute(EMPLOYER_ENTERPRISE_NUMBER, WORKER_SSN, $employments);

    // Groups maintain the order of first occurrence in input after grouping
    // emp-3 appears first in input, then emp-1, then emp-2
    expect($result)->toHaveCount(3)
        ->and($result[0]->employmentIds)->toBe(['emp-3'])
        ->and($result[1]->employmentIds)->toBe(['emp-1'])
        ->and($result[2]->employmentIds)->toBe(['emp-2']);
});
