<?php

use Hyperlab\Dimona\Enums\DimonaPeriodState;
use Hyperlab\Dimona\Enums\WorkerType;
use Illuminate\Support\Collection;

require_once __DIR__.'/Helpers.php';

beforeEach(function () {
    setupTestContext();
});

it('sets state to outdated when start_date changes', function () {
    $period = makePeriod([], ['emp-1']);

    syncPeriods(new Collection([
        makeExpectedPeriod(['startDate' => '2025-10-02']),
    ]));

    $period->refresh();

    expect($period->start_date)->toBe('2025-10-02')
        ->and($period->state)->toBe(DimonaPeriodState::Outdated);
});

it('sets state to outdated when end_date changes', function () {
    $period = makePeriod([], ['emp-1']);

    syncPeriods(new Collection([
        makeExpectedPeriod(['endDate' => '2025-10-02']),
    ]));

    $period->refresh();

    expect($period->end_date)->toBe('2025-10-02')
        ->and($period->state)->toBe(DimonaPeriodState::Outdated);
});

it('sets state to outdated when start_hour changes', function () {
    $period = makePeriod([], ['emp-1']);

    syncPeriods(new Collection([
        makeExpectedPeriod(['startHour' => '0900']),
    ]));

    $period->refresh();

    expect($period->start_hour)->toBe('0900')
        ->and($period->state)->toBe(DimonaPeriodState::Outdated);
});

it('sets state to outdated when end_hour changes', function () {
    $period = makePeriod([], ['emp-1']);

    syncPeriods(new Collection([
        makeExpectedPeriod(['endHour' => '1300']),
    ]));

    $period->refresh();

    expect($period->end_hour)->toBe('1300')
        ->and($period->state)->toBe(DimonaPeriodState::Outdated);
});

it('sets state to outdated when number_of_hours changes from null to value', function () {
    $period = makePeriod([
        'worker_type' => WorkerType::Student,
        'start_hour' => null,
        'end_hour' => null,
        'number_of_hours' => null,
    ], ['emp-1']);

    syncPeriods(new Collection([
        makeExpectedPeriod([
            'workerType' => WorkerType::Student,
            'startHour' => null,
            'endHour' => null,
            'numberOfHours' => 8.0,
        ]),
    ]));

    $period->refresh();

    expect($period->number_of_hours)->toBe(8.0)
        ->and($period->state)->toBe(DimonaPeriodState::Outdated);
});

it('sets state to outdated when number_of_hours changes from value to null', function () {
    $period = makePeriod(['number_of_hours' => 4.0], ['emp-1']);

    syncPeriods(new Collection([makeExpectedPeriod()]));

    $period->refresh();

    expect($period->number_of_hours)->toBeNull()
        ->and($period->state)->toBe(DimonaPeriodState::Outdated);
});

it('keeps state as outdated when updating already outdated period', function () {
    $period = makePeriod(['state' => DimonaPeriodState::Outdated], ['emp-1']);

    syncPeriods(new Collection([
        makeExpectedPeriod(['startHour' => '0900']),
    ]));

    $period->refresh();

    expect($period->start_hour)->toBe('0900')
        ->and($period->state)->toBe(DimonaPeriodState::Outdated);
});
