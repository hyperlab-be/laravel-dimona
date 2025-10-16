<?php

use Hyperlab\Dimona\Data\EmploymentLocationData;
use Hyperlab\Dimona\Enums\EmploymentLocationCountry;
use Hyperlab\Dimona\Enums\WorkerType;
use Hyperlab\Dimona\Models\DimonaPeriod;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

require_once __DIR__.'/Helpers.php';

beforeEach(function () {
    setupTestContext();
});

it('processes multiple expected periods independently', function () {
    syncPeriods(new Collection([
        makeExpectedPeriod(['employmentIds' => ['emp-1']]),
        makeExpectedPeriod([
            'employmentIds' => ['emp-2'],
            'jointCommissionNumber' => 302,
            'workerType' => WorkerType::Student,
            'startDate' => '2025-10-02',
            'endDate' => '2025-10-02',
        ]),
        makeExpectedPeriod([
            'employmentIds' => ['emp-3'],
            'jointCommissionNumber' => 306,
            'workerType' => WorkerType::Other,
            'startDate' => '2025-10-03',
            'endDate' => '2025-10-03',
        ]),
    ]));

    $periods = DimonaPeriod::orderBy('start_date')->get();

    expect(DimonaPeriod::count())->toBe(3)
        ->and($periods[0]->worker_type)->toBe(WorkerType::Flexi)
        ->and($periods[1]->worker_type)->toBe(WorkerType::Student)
        ->and($periods[2]->worker_type)->toBe(WorkerType::Other);
});

it('handles empty employmentIds array', function () {
    syncPeriods(new Collection([
        makeExpectedPeriod(['employmentIds' => []]),
    ]));

    $period = DimonaPeriod::first();
    $employmentCount = DB::table('dimona_period_employment')
        ->where('dimona_period_id', $period->id)
        ->count();

    expect(DimonaPeriod::count())->toBe(1)
        ->and($employmentCount)->toBe(0);
});

it('does not duplicate employments when action is run multiple times', function () {
    $expectedPeriods = new Collection([
        makeExpectedPeriod(['employmentIds' => ['emp-1', 'emp-2']]),
    ]);

    syncPeriods($expectedPeriods);
    syncPeriods($expectedPeriods);

    $period = DimonaPeriod::first();
    $employmentCount = DB::table('dimona_period_employment')
        ->where('dimona_period_id', $period->id)
        ->count();

    expect(DimonaPeriod::count())->toBe(1)
        ->and($employmentCount)->toBe(2);
});

it('handles partial employment overlap', function () {
    $period = makePeriod([], ['emp-1', 'emp-2']);

    syncPeriods(new Collection([
        makeExpectedPeriod(['employmentIds' => ['emp-2', 'emp-3']]),
    ]));

    expect(DimonaPeriod::count())->toBe(1)
        ->and(getEmploymentIds($period))->toBe(['emp-1', 'emp-2', 'emp-3']);
});

it('handles same employment across multiple expected periods', function () {
    syncPeriods(new Collection([
        makeExpectedPeriod(),
        makeExpectedPeriod([
            'jointCommissionNumber' => 302,
            'workerType' => WorkerType::Student,
            'startDate' => '2025-10-02',
            'startHour' => null,
            'endDate' => '2025-10-02',
            'endHour' => null,
            'numberOfHours' => 6.0,
        ]),
    ]));

    $period = DimonaPeriod::first();

    expect(DimonaPeriod::count())->toBe(1)
        ->and($period->start_date)->toBe('2025-10-02')
        ->and($period->start_hour)->toBeNull()
        ->and($period->number_of_hours)->toBe(6.0)
        ->and(getEmploymentIds($period))->toBe(['emp-1']);
});

it('creates new period with box_number when provided', function () {
    $locationWithBox = new EmploymentLocationData(
        name: 'Test Office',
        street: 'Test Street',
        houseNumber: '123',
        boxNumber: 'B',
        postalCode: '1000',
        place: 'Brussels',
        country: EmploymentLocationCountry::Belgium
    );

    syncPeriods(new Collection([
        makeExpectedPeriod(['location' => $locationWithBox]),
    ]));

    expect(DimonaPeriod::first()->location_box_number)->toBe('B');
});

it('verifies location fields are not updated when period is updated', function () {
    $period = makePeriod([
        'location_name' => 'Original Location',
        'location_street' => 'Original Street',
        'location_house_number' => '999',
        'location_box_number' => 'A',
        'location_postal_code' => '9999',
        'location_place' => 'Original Place',
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
        ->and($period->location_street)->toBe('Original Street')
        ->and($period->location_house_number)->toBe('999')
        ->and($period->location_box_number)->toBe('A')
        ->and($period->location_postal_code)->toBe('9999')
        ->and($period->location_place)->toBe('Original Place')
        ->and($period->location_country)->toBe(EmploymentLocationCountry::Belgium);
});
