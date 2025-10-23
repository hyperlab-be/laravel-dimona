<?php

use Hyperlab\Dimona\Data\EmploymentLocationData;
use Hyperlab\Dimona\Enums\DimonaPeriodState;
use Hyperlab\Dimona\Enums\EmploymentLocationCountry;
use Hyperlab\Dimona\Enums\WorkerType;
use Hyperlab\Dimona\Models\DimonaPeriod;
use Illuminate\Support\Collection;

require_once __DIR__.'/Helpers.php';

beforeEach(function () {
    setupTestContext();
});

it('updates period when employment matches', function () {
    // Existing: Accepted period (2025-10-01 08:00-12:00, Flexi, JC 304) linked to emp-1
    $period = makePeriod([
        'start_date' => '2025-10-01',
        'start_hour' => '08:00',
        'end_date' => '2025-10-01',
        'end_hour' => '12:00',
        'worker_type' => WorkerType::Flexi,
        'joint_commission_number' => 304,
        'state' => DimonaPeriodState::Accepted,
    ], ['emp-1']);

    // Expected: Same period with different hours
    syncPeriods(new Collection([
        makeExpectedPeriod([
            'startDate' => '2025-10-01',
            'startHour' => '09:00',
            'endDate' => '2025-10-01',
            'endHour' => '13:00',
            'workerType' => WorkerType::Flexi,
            'jointCommissionNumber' => 304,
            'employmentIds' => ['emp-1'],
        ]),
    ]));

    $period->refresh();

    // Should update hours and mark as Outdated
    expect($period->start_hour)->toBe('09:00')
        ->and($period->end_hour)->toBe('13:00')
        ->and($period->state)->toBe(DimonaPeriodState::Outdated)
        ->and($period->start_date)->toBe('2025-10-01')
        ->and($period->end_date)->toBe('2025-10-01')
        ->and($period->worker_type)->toBe(WorkerType::Flexi)
        ->and($period->joint_commission_number)->toBe(304)
        ->and(getEmploymentIds($period))->toBe(['emp-1'])
        ->and($period->employer_enterprise_number)->toBe(test()->employerNumber)
        ->and($period->worker_social_security_number)->toBe(test()->workerSsn);
});

it('adds new employment links to existing period', function () {
    // Existing: Accepted period (2025-10-01 08:00-12:00, 4 hours) linked to emp-1
    $period = makePeriod([
        'start_date' => '2025-10-01',
        'start_hour' => '08:00',
        'end_date' => '2025-10-01',
        'end_hour' => '12:00',
        'number_of_hours' => 4.0,
        'worker_type' => WorkerType::Flexi,
        'joint_commission_number' => 304,
        'state' => DimonaPeriodState::Accepted,
    ], ['emp-1']);

    // Expected: Same period with more employments and different hours count
    syncPeriods(new Collection([
        makeExpectedPeriod([
            'startDate' => '2025-10-01',
            'startHour' => '08:00',
            'endDate' => '2025-10-01',
            'endHour' => '12:00',
            'numberOfHours' => 8.0,
            'workerType' => WorkerType::Flexi,
            'jointCommissionNumber' => 304,
            'employmentIds' => ['emp-1', 'emp-2', 'emp-3'],
        ]),
    ]));

    $period->refresh();

    // Should add new employment links, update hours, mark as Outdated
    expect(getEmploymentIds($period))->toBe(['emp-1', 'emp-2', 'emp-3'])
        ->and($period->number_of_hours)->toBe(8.0)
        ->and($period->state)->toBe(DimonaPeriodState::Outdated)
        ->and($period->start_hour)->toBe('08:00')
        ->and($period->end_hour)->toBe('12:00')
        ->and($period->start_date)->toBe('2025-10-01')
        ->and($period->end_date)->toBe('2025-10-01')
        ->and($period->worker_type)->toBe(WorkerType::Flexi)
        ->and($period->joint_commission_number)->toBe(304)
        ->and($period->employer_enterprise_number)->toBe(test()->employerNumber)
        ->and($period->worker_social_security_number)->toBe(test()->workerSsn);
});

it('does not duplicate employment links and keeps state as Accepted when nothing changes', function () {
    // Existing: Accepted period (2025-10-01 08:00-12:00, Flexi, JC 304) linked to emp-1
    $period = makePeriod([
        'start_date' => '2025-10-01',
        'start_hour' => '08:00',
        'end_date' => '2025-10-01',
        'end_hour' => '12:00',
        'worker_type' => WorkerType::Flexi,
        'joint_commission_number' => 304,
        'number_of_hours' => null,
        'state' => DimonaPeriodState::Accepted,
    ], ['emp-1']);

    // Expected: Exact same period (no changes)
    syncPeriods(new Collection([
        makeExpectedPeriod([
            'startDate' => '2025-10-01',
            'startHour' => '08:00',
            'endDate' => '2025-10-01',
            'endHour' => '12:00',
            'workerType' => WorkerType::Flexi,
            'jointCommissionNumber' => 304,
            'numberOfHours' => null,
            'employmentIds' => ['emp-1'],
        ]),
    ]));

    $period->refresh();

    // Should not duplicate employment link or change state
    expect(getEmploymentIds($period))->toBe(['emp-1'])
        ->and($period->state)->toBe(DimonaPeriodState::Accepted)
        ->and($period->start_hour)->toBe('08:00')
        ->and($period->end_hour)->toBe('12:00')
        ->and($period->start_date)->toBe('2025-10-01')
        ->and($period->end_date)->toBe('2025-10-01')
        ->and($period->worker_type)->toBe(WorkerType::Flexi)
        ->and($period->joint_commission_number)->toBe(304)
        ->and($period->number_of_hours)->toBeNull()
        ->and($period->employer_enterprise_number)->toBe(test()->employerNumber)
        ->and($period->worker_social_security_number)->toBe(test()->workerSsn);
});

it('does not update location fields', function () {
    // Existing: Accepted period with specific location
    $period = makePeriod([
        'start_date' => '2025-10-01',
        'start_hour' => '08:00',
        'end_date' => '2025-10-01',
        'end_hour' => '12:00',
        'worker_type' => WorkerType::Flexi,
        'joint_commission_number' => 304,
        'state' => DimonaPeriodState::Accepted,
        'location_name' => 'Original Location',
        'location_street' => 'Original Street',
        'location_house_number' => '999',
        'location_box_number' => 'A',
        'location_postal_code' => '9999',
        'location_place' => 'Original Place',
        'location_country' => EmploymentLocationCountry::Belgium,
    ], ['emp-1']);

    $differentLocation = new EmploymentLocationData(
        name: 'New Location',
        street: 'New Street',
        houseNumber: '111',
        boxNumber: 'B',
        postalCode: '1111',
        place: 'New Place',
        country: EmploymentLocationCountry::Netherlands
    );

    // Expected: Same period with different hours and different location
    syncPeriods(new Collection([
        makeExpectedPeriod([
            'startDate' => '2025-10-01',
            'startHour' => '09:00',
            'endDate' => '2025-10-01',
            'endHour' => '12:00',
            'workerType' => WorkerType::Flexi,
            'jointCommissionNumber' => 304,
            'employmentIds' => ['emp-1'],
            'location' => $differentLocation,
        ]),
    ]));

    $period->refresh();

    // Should update start_hour but NOT location fields
    expect($period->start_hour)->toBe('09:00')
        ->and($period->location_name)->toBe('Original Location')
        ->and($period->location_street)->toBe('Original Street')
        ->and($period->location_house_number)->toBe('999')
        ->and($period->location_box_number)->toBe('A')
        ->and($period->location_postal_code)->toBe('9999')
        ->and($period->location_place)->toBe('Original Place')
        ->and($period->location_country)->toBe(EmploymentLocationCountry::Belgium)
        ->and($period->state)->toBe(DimonaPeriodState::Outdated)
        ->and($period->end_hour)->toBe('12:00')
        ->and($period->start_date)->toBe('2025-10-01')
        ->and($period->end_date)->toBe('2025-10-01')
        ->and($period->worker_type)->toBe(WorkerType::Flexi)
        ->and($period->joint_commission_number)->toBe(304)
        ->and(getEmploymentIds($period))->toBe(['emp-1'])
        ->and($period->employer_enterprise_number)->toBe(test()->employerNumber)
        ->and($period->worker_social_security_number)->toBe(test()->workerSsn);
});

it('does not update period in non-Accepted state', function (DimonaPeriodState $state) {
    // Existing: Period with non-Accepted state but linked to employment
    $existingPeriod = makePeriod([
        'start_date' => '2025-10-01',
        'start_hour' => '08:00',
        'end_date' => '2025-10-01',
        'end_hour' => '12:00',
        'worker_type' => WorkerType::Flexi,
        'joint_commission_number' => 304,
        'state' => $state,
    ], ['emp-1']);

    // Expected: Matching period with different hours
    syncPeriods(new Collection([
        makeExpectedPeriod([
            'startDate' => '2025-10-01',
            'startHour' => '09:00',
            'endDate' => '2025-10-01',
            'endHour' => '13:00',
            'workerType' => WorkerType::Flexi,
            'jointCommissionNumber' => 304,
            'employmentIds' => ['emp-1'],
        ]),
    ]));

    // Should create new period (only Accepted periods can be updated)
    expect(DimonaPeriod::query()->count())->toBe(2);

    // Verify existing period with non-Accepted state remains unchanged
    $existingPeriod->refresh();
    expect($existingPeriod->state)->toBe($state)
        ->and($existingPeriod->start_hour)->toBe('08:00')
        ->and($existingPeriod->end_hour)->toBe('12:00')
        ->and(getEmploymentIds($existingPeriod))->toBe(['emp-1']);

    // Verify new period was created
    $newPeriod = DimonaPeriod::query()->latest('id')->first();
    expect($newPeriod->id)->not->toBe($existingPeriod->id)
        ->and($newPeriod->state)->toBe(DimonaPeriodState::New)
        ->and($newPeriod->start_hour)->toBe('09:00')
        ->and($newPeriod->end_hour)->toBe('13:00')
        ->and(getEmploymentIds($newPeriod))->toBe(['emp-1']);
})->with([
    'Pending' => DimonaPeriodState::Pending,
    'AcceptedWithWarning' => DimonaPeriodState::AcceptedWithWarning,
    'Refused' => DimonaPeriodState::Refused,
    'Waiting' => DimonaPeriodState::Waiting,
    'Cancelled' => DimonaPeriodState::Cancelled,
    'Failed' => DimonaPeriodState::Failed,
]);
