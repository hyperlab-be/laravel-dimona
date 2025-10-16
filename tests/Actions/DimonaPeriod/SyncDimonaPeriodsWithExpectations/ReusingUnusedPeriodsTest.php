<?php

use Hyperlab\Dimona\Enums\DimonaPeriodState;
use Hyperlab\Dimona\Enums\WorkerType;
use Hyperlab\Dimona\Models\DimonaPeriod;
use Illuminate\Support\Collection;

require_once __DIR__.'/Helpers.php';

beforeEach(function () {
    setupTestContext();
});

it('reuses accepted period with matching details when no employments attached', function () {
    $period = makePeriod(['location_name' => 'Old Location']);

    syncPeriods(new Collection([
        makeExpectedPeriod(['startHour' => '0900', 'endHour' => '1300']),
    ]));

    $period->refresh();

    expect(DimonaPeriod::count())->toBe(1)
        ->and($period->start_hour)->toBe('0900')
        ->and($period->location_name)->toBe('Old Location')
        ->and($period->state)->toBe(DimonaPeriodState::Outdated)
        ->and(getEmploymentIds($period))->toBe(['emp-1']);
});

it('reuses unused period with exact match and only adds employments', function () {
    $period = makePeriod([
        'joint_commission_number' => 304,
        'worker_type' => WorkerType::Flexi,
        'start_date' => '2025-10-01',
        'start_hour' => '0800',
        'end_date' => '2025-10-01',
        'end_hour' => '1200',
        'number_of_hours' => null,
    ]);

    syncPeriods(new Collection([makeExpectedPeriod()]));

    $period->refresh();

    expect(DimonaPeriod::count())->toBe(1)
        ->and($period->state)->toBe(DimonaPeriodState::Accepted)
        ->and(getEmploymentIds($period))->toBe(['emp-1']);
});

it('uses first unused period when multiple match', function () {
    $period1 = makePeriod(['location_name' => 'First Location']);
    $period2 = makePeriod(['location_name' => 'Second Location']);

    syncPeriods(new Collection([
        makeExpectedPeriod(['startHour' => '0900']),
    ]));

    $period1->refresh();

    expect(DimonaPeriod::count())->toBe(2)
        ->and(getEmploymentIds($period1))->toHaveCount(1)
        ->and(getEmploymentIds($period2))->toHaveCount(0)
        ->and($period1->start_hour)->toBe('0900');
});

describe('does not reuse period with different', function () {
    it('worker type', function () {
        makePeriod(['worker_type' => WorkerType::Student]);

        syncPeriods(new Collection([makeExpectedPeriod()]));

        expect(DimonaPeriod::count())->toBe(2);
    });

    it('start date', function () {
        makePeriod(['start_date' => '2025-10-02', 'end_date' => '2025-10-02']);

        syncPeriods(new Collection([makeExpectedPeriod()]));

        expect(DimonaPeriod::count())->toBe(2);
    });

    it('joint commission number', function () {
        makePeriod(['joint_commission_number' => 302]);

        syncPeriods(new Collection([makeExpectedPeriod()]));

        expect(DimonaPeriod::count())->toBe(2);
    });

    it('employer enterprise number', function () {
        makePeriod(['employer_enterprise_number' => '9876543210']);

        syncPeriods(new Collection([makeExpectedPeriod()]));

        expect(DimonaPeriod::count())->toBe(2);
    });

    it('worker social security number', function () {
        makePeriod(['worker_social_security_number' => '98765432109']);

        syncPeriods(new Collection([makeExpectedPeriod()]));

        expect(DimonaPeriod::count())->toBe(2);
    });
});

describe('does not reuse period with state', function () {
    it('Pending', function () {
        makePeriod(['state' => DimonaPeriodState::Pending]);

        syncPeriods(new Collection([makeExpectedPeriod()]));

        expect(DimonaPeriod::count())->toBe(2);
    });

    it('Outdated', function () {
        makePeriod(['state' => DimonaPeriodState::Outdated]);

        syncPeriods(new Collection([makeExpectedPeriod()]));

        expect(DimonaPeriod::count())->toBe(2);
    });

    it('Cancelled', function () {
        makePeriod(['state' => DimonaPeriodState::Cancelled]);

        syncPeriods(new Collection([makeExpectedPeriod()]));

        expect(DimonaPeriod::count())->toBe(2);
    });

    it('Failed', function () {
        makePeriod(['state' => DimonaPeriodState::Failed]);

        syncPeriods(new Collection([makeExpectedPeriod()]));

        expect(DimonaPeriod::count())->toBe(2);
    });

    it('Waiting', function () {
        makePeriod(['state' => DimonaPeriodState::Waiting]);

        syncPeriods(new Collection([makeExpectedPeriod()]));

        expect(DimonaPeriod::count())->toBe(2);
    });

    it('AcceptedWithWarning', function () {
        makePeriod(['state' => DimonaPeriodState::AcceptedWithWarning]);

        syncPeriods(new Collection([makeExpectedPeriod()]));

        expect(DimonaPeriod::count())->toBe(2);
    });
});

it('does not reuse period that already has employments attached', function () {
    makePeriod([], ['other-emp']);

    syncPeriods(new Collection([makeExpectedPeriod()]));

    expect(DimonaPeriod::count())->toBe(2);
});
