<?php

use Hyperlab\Dimona\Enums\DimonaPeriodState;
use Hyperlab\Dimona\Enums\WorkerType;
use Hyperlab\Dimona\Models\DimonaPeriod;
use Illuminate\Support\Collection;

require_once __DIR__.'/Helpers.php';

beforeEach(function () {
    setupTestContext();
});

it('finds and updates period when any employment id matches', function () {
    $period = makePeriod([], ['emp-2']);

    syncPeriods(new Collection([
        makeExpectedPeriod([
            'employmentIds' => ['emp-1', 'emp-2'],
            'startHour' => '0900',
        ]),
    ]));

    expect(DimonaPeriod::count())->toBe(1)
        ->and(getEmploymentIds($period))->toBe(['emp-1', 'emp-2']);
});

it('updates first matching period when multiple periods match same employment', function () {
    $period1 = makePeriod([], ['emp-1']);
    $period2 = makePeriod([
        'worker_type' => WorkerType::Student,
        'start_date' => '2025-10-02',
        'end_date' => '2025-10-02',
    ], ['emp-1']);

    syncPeriods(new Collection([
        makeExpectedPeriod(['startHour' => '0900']),
    ]));

    $period1->refresh();
    $period2->refresh();

    expect($period1->start_hour)->toBe('0900')
        ->and($period1->state)->toBe(DimonaPeriodState::Outdated)
        ->and($period2->state)->toBe(DimonaPeriodState::Accepted);
});

it('prefers linked period over unused period', function () {
    makePeriod(['location_name' => 'Unused Location']);
    $linkedPeriod = makePeriod([
        'worker_type' => WorkerType::Student,
        'start_date' => '2025-10-02',
        'end_date' => '2025-10-02',
        'location_name' => 'Linked Location',
    ], ['emp-1']);

    syncPeriods(new Collection([
        makeExpectedPeriod(['startHour' => '0900']),
    ]));

    $linkedPeriod->refresh();

    expect(DimonaPeriod::count())->toBe(2)
        ->and($linkedPeriod->start_hour)->toBe('0900')
        ->and($linkedPeriod->location_name)->toBe('Linked Location');
});
