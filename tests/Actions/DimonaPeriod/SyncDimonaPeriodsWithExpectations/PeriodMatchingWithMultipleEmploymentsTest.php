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
    $period = makePeriod([
        'worker_type' => WorkerType::Flexi,
        'joint_commission_number' => 304,
        'start_date' => '2025-10-01',
        'start_hour' => '08:00',
        'end_date' => '2025-10-01',
        'end_hour' => '12:00',
        'state' => DimonaPeriodState::Accepted,
    ], ['emp-2']);

    syncPeriods(new Collection([
        makeExpectedPeriod([
            'workerType' => WorkerType::Flexi,
            'jointCommissionNumber' => 304,
            'startDate' => '2025-10-01',
            'startHour' => '09:00',
            'endDate' => '2025-10-01',
            'endHour' => '12:00',
            'employmentIds' => ['emp-1', 'emp-2'],
        ]),
    ]));

    expect(DimonaPeriod::count())->toBe(1)
        ->and($period->refresh()->start_hour)->toBe('09:00')
        ->and(getEmploymentIds($period))->toBe(['emp-1', 'emp-2']);
});

it('prefers linked period over unused period', function () {
    // Create an unused period that matches all criteria but has no employment links
    $unusedPeriod = makePeriod([
        'worker_type' => WorkerType::Student,
        'joint_commission_number' => 304,
        'start_date' => '2025-10-02',
        'start_hour' => '08:00',
        'end_date' => '2025-10-02',
        'end_hour' => '12:00',
        'state' => DimonaPeriodState::Accepted,
        'location_name' => 'Unused Location',
    ]);

    // Create a linked period with same matching criteria but different location
    $linkedPeriod = makePeriod([
        'worker_type' => WorkerType::Student,
        'joint_commission_number' => 304,
        'start_date' => '2025-10-02',
        'start_hour' => '08:00',
        'end_date' => '2025-10-02',
        'end_hour' => '12:00',
        'state' => DimonaPeriodState::Accepted,
        'location_name' => 'Linked Location',
    ], ['emp-1']);

    syncPeriods(new Collection([
        makeExpectedPeriod([
            'workerType' => WorkerType::Student,
            'jointCommissionNumber' => 304,
            'startDate' => '2025-10-02',
            'startHour' => '09:00',
            'endDate' => '2025-10-02',
            'endHour' => '12:00',
            'employmentIds' => ['emp-1'],
        ]),
    ]));

    $linkedPeriod->refresh();
    $unusedPeriod->refresh();

    // Should update the linked period, not the unused one
    expect(DimonaPeriod::count())->toBe(2)
        ->and($linkedPeriod->start_hour)->toBe('09:00')
        ->and($linkedPeriod->state)->toBe(DimonaPeriodState::Outdated)
        ->and($unusedPeriod->start_hour)->toBe('08:00')
        ->and($unusedPeriod->state)->toBe(DimonaPeriodState::Accepted);
});
