<?php

use Hyperlab\Dimona\Enums\DimonaPeriodState;
use Hyperlab\Dimona\Enums\WorkerType;
use Illuminate\Support\Collection;

require_once __DIR__.'/Helpers.php';

beforeEach(function () {
    setupTestContext();
});

it('sets state to outdated when end_date changes', function () {
    // Existing: Accepted period (2025-10-01 08:00-12:00) linked to emp-1
    $period = makePeriod([
        'start_date' => '2025-10-01',
        'start_hour' => '08:00',
        'end_date' => '2025-10-01',
        'end_hour' => '12:00',
        'worker_type' => WorkerType::Flexi,
        'joint_commission_number' => 304,
        'state' => DimonaPeriodState::Accepted,
    ], ['emp-1']);

    // Expected: Same period but end date changed to 2025-10-02
    syncPeriods(new Collection([
        makeExpectedPeriod([
            'startDate' => '2025-10-01',
            'startHour' => '08:00',
            'endDate' => '2025-10-02',
            'endHour' => '12:00',
            'workerType' => WorkerType::Flexi,
            'jointCommissionNumber' => 304,
            'employmentIds' => ['emp-1'],
        ]),
    ]));

    $period->refresh();

    // Should update end_date and mark as Outdated
    expect($period->end_date)->toBe('2025-10-02')
        ->and($period->state)->toBe(DimonaPeriodState::Outdated);
});

it('sets state to outdated when start_hour changes', function () {
    // Existing: Accepted period (2025-10-01 08:00-12:00) linked to emp-1
    $period = makePeriod([
        'start_date' => '2025-10-01',
        'start_hour' => '08:00',
        'end_date' => '2025-10-01',
        'end_hour' => '12:00',
        'worker_type' => WorkerType::Flexi,
        'joint_commission_number' => 304,
        'state' => DimonaPeriodState::Accepted,
    ], ['emp-1']);

    // Expected: Same period but start hour changed to 09:00
    syncPeriods(new Collection([
        makeExpectedPeriod([
            'startDate' => '2025-10-01',
            'startHour' => '09:00',
            'endDate' => '2025-10-01',
            'endHour' => '12:00',
            'workerType' => WorkerType::Flexi,
            'jointCommissionNumber' => 304,
            'employmentIds' => ['emp-1'],
        ]),
    ]));

    $period->refresh();

    // Should update start_hour and mark as Outdated
    expect($period->start_hour)->toBe('09:00')
        ->and($period->state)->toBe(DimonaPeriodState::Outdated);
});

it('sets state to outdated when end_hour changes', function () {
    // Existing: Accepted period (2025-10-01 08:00-12:00) linked to emp-1
    $period = makePeriod([
        'start_date' => '2025-10-01',
        'start_hour' => '08:00',
        'end_date' => '2025-10-01',
        'end_hour' => '12:00',
        'worker_type' => WorkerType::Flexi,
        'joint_commission_number' => 304,
        'state' => DimonaPeriodState::Accepted,
    ], ['emp-1']);

    // Expected: Same period but end hour changed to 13:00
    syncPeriods(new Collection([
        makeExpectedPeriod([
            'startDate' => '2025-10-01',
            'startHour' => '08:00',
            'endDate' => '2025-10-01',
            'endHour' => '13:00',
            'workerType' => WorkerType::Flexi,
            'jointCommissionNumber' => 304,
            'employmentIds' => ['emp-1'],
        ]),
    ]));

    $period->refresh();

    // Should update end_hour and mark as Outdated
    expect($period->end_hour)->toBe('13:00')
        ->and($period->state)->toBe(DimonaPeriodState::Outdated);
});

it('sets state to outdated when number_of_hours changes', function () {
    $period = makePeriod([
        'start_date' => '2025-10-01',
        'start_hour' => null,
        'end_date' => '2025-10-01',
        'end_hour' => null,
        'number_of_hours' => 6.0,
        'worker_type' => WorkerType::Student,
        'joint_commission_number' => 304,
        'state' => DimonaPeriodState::Accepted,
    ], ['emp-1']);

    // Expected: Same period but now with 8.0 hours specified
    syncPeriods(new Collection([
        makeExpectedPeriod([
            'startDate' => '2025-10-01',
            'startHour' => null,
            'endDate' => '2025-10-01',
            'endHour' => null,
            'numberOfHours' => 8.0,
            'workerType' => WorkerType::Student,
            'jointCommissionNumber' => 304,
            'employmentIds' => ['emp-1'],
        ]),
    ]));

    $period->refresh();

    // Should update number_of_hours and mark as Outdated
    expect($period->number_of_hours)->toBe(8.0)
        ->and($period->state)->toBe(DimonaPeriodState::Outdated);
});

it('keeps state as outdated when updating already outdated period', function () {
    // Existing: Outdated period (2025-10-01 08:00-12:00) linked to emp-1
    $period = makePeriod([
        'start_date' => '2025-10-01',
        'start_hour' => '08:00',
        'end_date' => '2025-10-01',
        'end_hour' => '12:00',
        'worker_type' => WorkerType::Flexi,
        'joint_commission_number' => 304,
        'state' => DimonaPeriodState::Outdated,
    ], ['emp-1']);

    // Expected: Same period but start hour changed to 09:00
    syncPeriods(new Collection([
        makeExpectedPeriod([
            'startDate' => '2025-10-01',
            'startHour' => '09:00',
            'endDate' => '2025-10-01',
            'endHour' => '12:00',
            'workerType' => WorkerType::Flexi,
            'jointCommissionNumber' => 304,
            'employmentIds' => ['emp-1'],
        ]),
    ]));

    $period->refresh();

    // Should update start_hour and keep state as Outdated
    expect($period->start_hour)->toBe('09:00')
        ->and($period->state)->toBe(DimonaPeriodState::Outdated);
});
