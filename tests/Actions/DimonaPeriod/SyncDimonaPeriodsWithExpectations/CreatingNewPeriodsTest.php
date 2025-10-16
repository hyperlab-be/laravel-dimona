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
    syncPeriods(new Collection([makeExpectedPeriod()]));

    $period = DimonaPeriod::first();

    expect($period)->not->toBeNull()
        ->and($period->state)->toBe(DimonaPeriodState::New)
        ->and($period->worker_type)->toBe(WorkerType::Flexi);
});

it('creates employment links for the new period', function () {
    syncPeriods(new Collection([
        makeExpectedPeriod(['employmentIds' => ['emp-1', 'emp-2']]),
    ]));

    expect(getEmploymentIds(DimonaPeriod::first()))->toBe(['emp-1', 'emp-2']);
});

it('creates multiple periods', function () {
    syncPeriods(new Collection([
        makeExpectedPeriod(['employmentIds' => ['emp-1']]),
        makeExpectedPeriod([
            'employmentIds' => ['emp-2'],
            'startDate' => '2025-10-02',
            'endDate' => '2025-10-02',
        ]),
    ]));

    expect(DimonaPeriod::count())->toBe(2);
});

it('handles empty expected periods collection', function () {
    syncPeriods(new Collection([]));

    expect(DimonaPeriod::count())->toBe(0);
});
