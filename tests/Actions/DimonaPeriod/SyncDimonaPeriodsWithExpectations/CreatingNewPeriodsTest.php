<?php

use Hyperlab\Dimona\Enums\DimonaPeriodState;
use Hyperlab\Dimona\Enums\WorkerType;
use Hyperlab\Dimona\Models\DimonaPeriod;
use Illuminate\Support\Collection;

require_once __DIR__.'/Helpers.php';

beforeEach(function () {
    setupTestContext();
});

it('creates a new period when no matching period exists', function () {
    // No existing periods

    // Expected: New period (2025-10-01 08:00-12:00, Flexi, JC 304) with emp-1
    syncPeriods(new Collection([
        makeExpectedPeriod([
            'startDate' => '2025-10-01',
            'startHour' => '08:00',
            'endDate' => '2025-10-01',
            'endHour' => '12:00',
            'workerType' => WorkerType::Flexi,
            'jointCommissionNumber' => 304,
            'employmentIds' => ['emp-1', 'emp-2'],
        ]),
    ]));

    $period = DimonaPeriod::query()->first();

    // Should create new period with state New
    expect($period)->not->toBeNull()
        ->and($period->state)->toBe(DimonaPeriodState::New)
        ->and($period->worker_type)->toBe(WorkerType::Flexi)
        ->and($period->start_date)->toBe('2025-10-01')
        ->and($period->start_hour)->toBe('08:00')
        ->and($period->end_date)->toBe('2025-10-01')
        ->and($period->end_hour)->toBe('12:00')
        ->and(getEmploymentIds($period))->toBe(['emp-1', 'emp-2']);
});

it('creates multiple periods', function () {
    // No existing periods

    // Expected: Two different periods on different dates
    syncPeriods(new Collection([
        makeExpectedPeriod([
            'startDate' => '2025-10-01',
            'startHour' => '08:00',
            'endDate' => '2025-10-01',
            'endHour' => '12:00',
            'workerType' => WorkerType::Flexi,
            'jointCommissionNumber' => 304,
            'employmentIds' => ['emp-1'],
        ]),
        makeExpectedPeriod([
            'startDate' => '2025-10-02',
            'startHour' => '08:00',
            'endDate' => '2025-10-02',
            'endHour' => '12:00',
            'workerType' => WorkerType::Flexi,
            'jointCommissionNumber' => 304,
            'employmentIds' => ['emp-2'],
        ]),
    ]));

    // Should create both periods
    expect(DimonaPeriod::query()->count())->toBe(2);

    // Verify first period
    $period1 = DimonaPeriod::query()->where('start_date', '2025-10-01')->first();
    expect($period1)->not->toBeNull()
        ->and($period1->state)->toBe(DimonaPeriodState::New)
        ->and($period1->worker_type)->toBe(WorkerType::Flexi)
        ->and($period1->start_date)->toBe('2025-10-01')
        ->and($period1->start_hour)->toBe('08:00')
        ->and($period1->end_date)->toBe('2025-10-01')
        ->and($period1->end_hour)->toBe('12:00')
        ->and($period1->joint_commission_number)->toBe(304)
        ->and(getEmploymentIds($period1))->toBe(['emp-1']);

    // Verify second period
    $period2 = DimonaPeriod::query()->where('start_date', '2025-10-02')->first();
    expect($period2)->not->toBeNull()
        ->and($period2->state)->toBe(DimonaPeriodState::New)
        ->and($period2->worker_type)->toBe(WorkerType::Flexi)
        ->and($period2->start_date)->toBe('2025-10-02')
        ->and($period2->start_hour)->toBe('08:00')
        ->and($period2->end_date)->toBe('2025-10-02')
        ->and($period2->end_hour)->toBe('12:00')
        ->and($period2->joint_commission_number)->toBe(304)
        ->and(getEmploymentIds($period2))->toBe(['emp-2']);
});

it('handles empty expected periods collection', function () {
    // No existing periods

    // Expected: No periods
    syncPeriods(new Collection([]));

    // Should not create any periods
    expect(DimonaPeriod::query()->count())->toBe(0);
});
