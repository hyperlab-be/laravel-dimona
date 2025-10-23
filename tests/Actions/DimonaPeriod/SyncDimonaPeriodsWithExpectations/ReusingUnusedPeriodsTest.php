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
    // Existing: Accepted period (2025-10-01 08:00-12:00, Flexi, JC 304, no employments)
    $period = makePeriod([
        'start_date' => '2025-10-01',
        'start_hour' => '08:00',
        'end_date' => '2025-10-01',
        'end_hour' => '12:00',
        'worker_type' => WorkerType::Flexi,
        'joint_commission_number' => 304,
        'state' => DimonaPeriodState::Accepted,
        'location_name' => 'Old Location',
    ]);

    // Expected: Same period but with different hours and employment attached
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

    // Should reuse period, update hours, add employment, mark as Outdated, keep location
    expect(DimonaPeriod::query()->count())->toBe(1)
        ->and($period->start_hour)->toBe('09:00')
        ->and($period->end_hour)->toBe('13:00')
        ->and($period->location_name)->toBe('Old Location')
        ->and($period->state)->toBe(DimonaPeriodState::Outdated)
        ->and(getEmploymentIds($period))->toBe(['emp-1']);
});

it('reuses unused period with exact match and only adds employments', function () {
    // Existing: Accepted period (2025-10-01 08:00-12:00, Flexi, JC 304, no employments)
    $period = makePeriod([
        'start_date' => '2025-10-01',
        'start_hour' => '08:00',
        'end_date' => '2025-10-01',
        'end_hour' => '12:00',
        'worker_type' => WorkerType::Flexi,
        'joint_commission_number' => 304,
        'number_of_hours' => null,
        'state' => DimonaPeriodState::Accepted,
    ]);

    // Expected: Exact same period with employment attached
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

    // Should reuse period, add employment, keep Accepted state (no changes)
    expect(DimonaPeriod::query()->count())->toBe(1)
        ->and($period->state)->toBe(DimonaPeriodState::Accepted)
        ->and($period->start_date)->toBe('2025-10-01')
        ->and($period->start_hour)->toBe('08:00')
        ->and($period->end_date)->toBe('2025-10-01')
        ->and($period->end_hour)->toBe('12:00')
        ->and($period->worker_type)->toBe(WorkerType::Flexi)
        ->and($period->joint_commission_number)->toBe(304)
        ->and($period->number_of_hours)->toBeNull()
        ->and(getEmploymentIds($period))->toBe(['emp-1']);
});

it('uses first unused period when multiple match', function () {
    // Existing: Two Accepted periods (both 2025-10-01, Flexi, JC 304, no employments)
    $period1 = makePeriod([
        'start_date' => '2025-10-01',
        'start_hour' => '08:00',
        'end_date' => '2025-10-01',
        'end_hour' => '12:00',
        'worker_type' => WorkerType::Flexi,
        'joint_commission_number' => 304,
        'state' => DimonaPeriodState::Accepted,
        'location_name' => 'First Location',
    ]);

    $period2 = makePeriod([
        'start_date' => '2025-10-01',
        'start_hour' => '12:00',
        'end_date' => '2025-10-01',
        'end_hour' => '16:00',
        'worker_type' => WorkerType::Flexi,
        'joint_commission_number' => 304,
        'state' => DimonaPeriodState::Accepted,
        'location_name' => 'Second Location',
    ]);

    // Expected: Period with different start hour
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

    $period1->refresh();
    $period2->refresh();

    // Should reuse first period only
    expect(DimonaPeriod::query()->count())->toBe(2)
        ->and(getEmploymentIds($period1))->toHaveCount(1)
        ->and(getEmploymentIds($period2))->toHaveCount(0)
        ->and($period1->start_hour)->toBe('09:00')
        ->and($period1->end_hour)->toBe('12:00')
        ->and($period1->state)->toBe(DimonaPeriodState::Outdated)
        ->and($period1->location_name)->toBe('First Location')
        ->and($period2->start_hour)->toBe('12:00')
        ->and($period2->end_hour)->toBe('16:00')
        ->and($period2->state)->toBe(DimonaPeriodState::Accepted)
        ->and($period2->location_name)->toBe('Second Location');
});

describe('does not reuse period with different', function () {
    it('worker type', function () {
        // Existing: Accepted period with Student worker type
        $existingPeriod = makePeriod([
            'start_date' => '2025-10-01',
            'start_hour' => '08:00',
            'end_date' => '2025-10-01',
            'end_hour' => '12:00',
            'worker_type' => WorkerType::Student,
            'joint_commission_number' => 304,
            'state' => DimonaPeriodState::Accepted,
        ]);

        // Expected: Period with Flexi worker type
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
        ]));

        // Should create new period instead of reusing
        expect(DimonaPeriod::query()->count())->toBe(2);

        // Verify existing period remains unchanged
        $existingPeriod->refresh();
        expect($existingPeriod->worker_type)->toBe(WorkerType::Student)
            ->and($existingPeriod->state)->toBe(DimonaPeriodState::Accepted)
            ->and(getEmploymentIds($existingPeriod))->toBe([]);

        // Verify new period was created with correct worker type
        $newPeriod = DimonaPeriod::query()->where('worker_type', WorkerType::Flexi)->first();
        expect($newPeriod)->not->toBeNull()
            ->and($newPeriod->worker_type)->toBe(WorkerType::Flexi)
            ->and($newPeriod->state)->toBe(DimonaPeriodState::New)
            ->and(getEmploymentIds($newPeriod))->toBe(['emp-1']);
    });

    it('start date', function () {
        // Existing: Accepted period for 2025-10-02
        $existingPeriod = makePeriod([
            'start_date' => '2025-10-02',
            'start_hour' => '08:00',
            'end_date' => '2025-10-02',
            'end_hour' => '12:00',
            'worker_type' => WorkerType::Flexi,
            'joint_commission_number' => 304,
            'state' => DimonaPeriodState::Accepted,
        ]);

        // Expected: Period for 2025-10-01
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
        ]));

        // Should create new period instead of reusing
        expect(DimonaPeriod::query()->count())->toBe(2);

        // Verify existing period remains unchanged
        $existingPeriod->refresh();
        expect($existingPeriod->start_date)->toBe('2025-10-02')
            ->and($existingPeriod->state)->toBe(DimonaPeriodState::Accepted)
            ->and(getEmploymentIds($existingPeriod))->toBe([]);

        // Verify new period was created with correct date
        $newPeriod = DimonaPeriod::query()->where('start_date', '2025-10-01')->first();
        expect($newPeriod)->not->toBeNull()
            ->and($newPeriod->start_date)->toBe('2025-10-01')
            ->and($newPeriod->state)->toBe(DimonaPeriodState::New)
            ->and(getEmploymentIds($newPeriod))->toBe(['emp-1']);
    });

    it('joint commission number', function () {
        // Existing: Accepted period with JC 302
        $existingPeriod = makePeriod([
            'start_date' => '2025-10-01',
            'start_hour' => '08:00',
            'end_date' => '2025-10-01',
            'end_hour' => '12:00',
            'worker_type' => WorkerType::Flexi,
            'joint_commission_number' => 302,
            'state' => DimonaPeriodState::Accepted,
        ]);

        // Expected: Period with JC 304
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
        ]));

        // Should create new period instead of reusing
        expect(DimonaPeriod::query()->count())->toBe(2);

        // Verify existing period remains unchanged
        $existingPeriod->refresh();
        expect($existingPeriod->joint_commission_number)->toBe(302)
            ->and($existingPeriod->state)->toBe(DimonaPeriodState::Accepted)
            ->and(getEmploymentIds($existingPeriod))->toBe([]);

        // Verify new period was created with correct JC
        $newPeriod = DimonaPeriod::query()->where('joint_commission_number', 304)->first();
        expect($newPeriod)->not->toBeNull()
            ->and($newPeriod->joint_commission_number)->toBe(304)
            ->and($newPeriod->state)->toBe(DimonaPeriodState::New)
            ->and(getEmploymentIds($newPeriod))->toBe(['emp-1']);
    });

    it('employer enterprise number', function () {
        // Existing: Accepted period for different employer
        makePeriod([
            'employer_enterprise_number' => '9876543210',
            'start_date' => '2025-10-01',
            'start_hour' => '08:00',
            'end_date' => '2025-10-01',
            'end_hour' => '12:00',
            'worker_type' => WorkerType::Flexi,
            'joint_commission_number' => 304,
            'state' => DimonaPeriodState::Accepted,
        ]);

        // Expected: Period for default employer (0123456789)
        syncPeriods(new Collection([
            makeExpectedPeriod([
                'employerEnterpriseNumber' => '0123456789',
                'startDate' => '2025-10-01',
                'startHour' => '08:00',
                'endDate' => '2025-10-01',
                'endHour' => '12:00',
                'workerType' => WorkerType::Flexi,
                'jointCommissionNumber' => 304,
                'employmentIds' => ['emp-1'],
            ]),
        ]));

        // Should create new period instead of reusing
        expect(DimonaPeriod::count())->toBe(2);
    });

    it('worker social security number', function () {
        // Existing: Accepted period for different worker
        makePeriod([
            'worker_social_security_number' => '98765432109',
            'start_date' => '2025-10-01',
            'start_hour' => '08:00',
            'end_date' => '2025-10-01',
            'end_hour' => '12:00',
            'worker_type' => WorkerType::Flexi,
            'joint_commission_number' => 304,
            'state' => DimonaPeriodState::Accepted,
        ]);

        // Expected: Period for default worker (12345678901)
        syncPeriods(new Collection([
            makeExpectedPeriod([
                'workerSocialSecurityNumber' => '12345678901',
                'startDate' => '2025-10-01',
                'startHour' => '08:00',
                'endDate' => '2025-10-01',
                'endHour' => '12:00',
                'workerType' => WorkerType::Flexi,
                'jointCommissionNumber' => 304,
                'employmentIds' => ['emp-1'],
            ]),
        ]));

        // Should create new period instead of reusing
        expect(DimonaPeriod::count())->toBe(2);
    });
});

it('reuses period in reusable state', function (DimonaPeriodState $state) {
    // Existing: Period with New or Outdated state
    $existingPeriod = makePeriod([
        'start_date' => '2025-10-01',
        'start_hour' => '08:00',
        'end_date' => '2025-10-01',
        'end_hour' => '12:00',
        'worker_type' => WorkerType::Flexi,
        'joint_commission_number' => 304,
        'state' => $state,
        'location_name' => 'Old Location',
    ]);

    // Expected: Matching period with employment
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

    // Should reuse period
    expect(DimonaPeriod::query()->count())->toBe(1);

    // Verify period was reused and updated
    $existingPeriod->refresh();
    expect($existingPeriod->start_hour)->toBe('09:00')
        ->and($existingPeriod->state)->toBe(DimonaPeriodState::Outdated)
        ->and($existingPeriod->location_name)->toBe('Old Location')
        ->and(getEmploymentIds($existingPeriod))->toBe(['emp-1']);
})->with([
    'New' => DimonaPeriodState::New,
    'Outdated' => DimonaPeriodState::Outdated,
    'Accepted' => DimonaPeriodState::Accepted,
]);

it('does not reuse period in non-reusable state', function (DimonaPeriodState $state) {
    // Existing: Period with non-reusable state
    $existingPeriod = makePeriod([
        'start_date' => '2025-10-01',
        'start_hour' => '08:00',
        'end_date' => '2025-10-01',
        'end_hour' => '12:00',
        'worker_type' => WorkerType::Flexi,
        'joint_commission_number' => 304,
        'state' => $state,
    ]);

    // Expected: Matching period
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
    ]));

    // Should create new period (only New, Outdated, and Accepted periods can be reused)
    expect(DimonaPeriod::query()->count())->toBe(2);

    // Verify existing period remains unchanged
    $existingPeriod->refresh();
    expect($existingPeriod->state)->toBe($state)
        ->and(getEmploymentIds($existingPeriod))->toBe([]);

    // Verify new period was created
    $newPeriod = DimonaPeriod::query()->latest('id')->first();
    expect($newPeriod)->not->toBeNull()
        ->and($newPeriod->state)->toBe(DimonaPeriodState::New)
        ->and(getEmploymentIds($newPeriod))->toBe(['emp-1']);
})->with([
    'Pending' => DimonaPeriodState::Pending,
    'AcceptedWithWarning' => DimonaPeriodState::AcceptedWithWarning,
    'Refused' => DimonaPeriodState::Refused,
    'Waiting' => DimonaPeriodState::Waiting,
    'Cancelled' => DimonaPeriodState::Cancelled,
    'Failed' => DimonaPeriodState::Failed,
]);
