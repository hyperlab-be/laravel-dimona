<?php

use Hyperlab\Dimona\Data\EmploymentLocationData;
use Hyperlab\Dimona\Enums\DimonaPeriodState;
use Hyperlab\Dimona\Enums\EmploymentLocationCountry;
use Hyperlab\Dimona\Enums\WorkerType;
use Illuminate\Support\Collection;

require_once __DIR__.'/Helpers.php';

beforeEach(function () {
    setupTestContext();
});

it('updates period when employment matches', function () {
    $period = makePeriod([], ['emp-1']);

    syncPeriods(new Collection([
        makeExpectedPeriod(['startHour' => '0900', 'endHour' => '1300']),
    ]));

    $period->refresh();

    expect($period->start_hour)->toBe('0900')
        ->and($period->end_hour)->toBe('1300')
        ->and($period->state)->toBe(DimonaPeriodState::Outdated);
});

it('adds new employment links to existing period', function () {
    $period = makePeriod(['number_of_hours' => 4.0], ['emp-1']);

    syncPeriods(new Collection([
        makeExpectedPeriod([
            'employmentIds' => ['emp-1', 'emp-2', 'emp-3'],
            'numberOfHours' => 8.0,
        ]),
    ]));

    $period->refresh();

    expect(getEmploymentIds($period))->toBe(['emp-1', 'emp-2', 'emp-3'])
        ->and($period->number_of_hours)->toBe(8.0)
        ->and($period->state)->toBe(DimonaPeriodState::Outdated);
});

it('does not duplicate employment links and keeps state as Accepted when nothing changes', function () {
    $period = makePeriod([
        'joint_commission_number' => 304,
        'worker_type' => WorkerType::Flexi,
        'start_date' => '2025-10-01',
        'start_hour' => '0800',
        'end_date' => '2025-10-01',
        'end_hour' => '1200',
        'number_of_hours' => null,
    ], ['emp-1']);

    syncPeriods(new Collection([makeExpectedPeriod()]));

    $period->refresh();

    expect(getEmploymentIds($period))->toBe(['emp-1'])
        ->and($period->state)->toBe(DimonaPeriodState::Accepted);
});

it('updates period even when it has Pending state', function () {
    $period = makePeriod(['state' => DimonaPeriodState::Pending], ['emp-1']);

    syncPeriods(new Collection([
        makeExpectedPeriod(['startHour' => '0900']),
    ]));

    $period->refresh();

    expect($period->start_hour)->toBe('0900')
        ->and($period->state)->toBe(DimonaPeriodState::Outdated);
});

it('does not update location fields', function () {
    $period = makePeriod([
        'location_name' => 'Original Location',
        'location_street' => 'Original Street',
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

    syncPeriods(new Collection([
        makeExpectedPeriod([
            'startHour' => '0900',
            'location' => $differentLocation,
        ]),
    ]));

    $period->refresh();

    expect($period->start_hour)->toBe('0900')
        ->and($period->location_name)->toBe('Original Location')
        ->and($period->location_street)->toBe('Original Street');
});
