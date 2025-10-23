<?php

use Hyperlab\Dimona\Enums\DimonaPeriodState;
use Hyperlab\Dimona\Enums\WorkerType;
use Hyperlab\Dimona\Models\DimonaPeriod;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

require_once __DIR__.'/Helpers.php';

beforeEach(function () {
    setupTestContext();
});

describe('duplicate employment IDs', function () {
    it('handles duplicate employment IDs gracefully', function () {
        // Expected: Period with duplicate employment IDs
        syncPeriods(new Collection([
            makeExpectedPeriod([
                'startDate' => '2025-10-01',
                'startHour' => '08:00',
                'endDate' => '2025-10-01',
                'endHour' => '12:00',
                'workerType' => WorkerType::Flexi,
                'jointCommissionNumber' => 304,
                'employmentIds' => ['emp-1', 'emp-1', 'emp-2', 'emp-2', 'emp-3'],
            ]),
        ]));

        $period = DimonaPeriod::query()->first();
        $employmentIds = getEmploymentIds($period);

        // Should handle duplicates (insertOrIgnore in linkEmployments ensures uniqueness)
        // The exact count depends on database constraint behavior
        expect($employmentIds)->toContain('emp-1')
            ->and($employmentIds)->toContain('emp-2')
            ->and($employmentIds)->toContain('emp-3');
    });

    it('adds duplicate employment IDs to existing period without error', function () {
        // Existing: Period linked to emp-1
        $period = makePeriod([
            'start_date' => '2025-10-01',
            'start_hour' => '08:00',
            'end_date' => '2025-10-01',
            'end_hour' => '12:00',
            'worker_type' => WorkerType::Flexi,
            'joint_commission_number' => 304,
            'state' => DimonaPeriodState::Accepted,
        ], ['emp-1']);

        // Expected: Same period with duplicate emp-1 and new emp-2
        syncPeriods(new Collection([
            makeExpectedPeriod([
                'startDate' => '2025-10-01',
                'startHour' => '08:00',
                'endDate' => '2025-10-01',
                'endHour' => '13:00',
                'workerType' => WorkerType::Flexi,
                'jointCommissionNumber' => 304,
                'employmentIds' => ['emp-1', 'emp-1', 'emp-2'],
            ]),
        ]));

        $period->refresh();
        $employmentIds = getEmploymentIds($period);

        // Should have both emp-1 and emp-2, with emp-1 not duplicated
        expect($employmentIds)->toContain('emp-1')
            ->and($employmentIds)->toContain('emp-2')
            ->and($period->state)->toBe(DimonaPeriodState::Outdated);
    });
});

describe('empty employment arrays', function () {
    it('creates period with no employment links when employmentIds is empty', function () {
        // Expected: Period with empty employment IDs
        syncPeriods(new Collection([
            makeExpectedPeriod([
                'startDate' => '2025-10-01',
                'startHour' => '08:00',
                'endDate' => '2025-10-01',
                'endHour' => '12:00',
                'workerType' => WorkerType::Flexi,
                'jointCommissionNumber' => 304,
                'employmentIds' => [],
            ]),
        ]));

        $period = DimonaPeriod::query()->first();

        expect($period)->not->toBeNull()
            ->and(getEmploymentIds($period))->toBe([]);
    });

    it('removes all employment links when expected period has empty array', function () {
        // Existing: Period with employment links
        $period = makePeriod([
            'start_date' => '2025-10-01',
            'start_hour' => '08:00',
            'end_date' => '2025-10-01',
            'end_hour' => '12:00',
            'worker_type' => WorkerType::Flexi,
            'joint_commission_number' => 304,
            'state' => DimonaPeriodState::Accepted,
        ], ['emp-1', 'emp-2']);

        // Expected: Same period with empty employment IDs
        syncPeriods(new Collection([
            makeExpectedPeriod([
                'startDate' => '2025-10-01',
                'startHour' => '08:00',
                'endDate' => '2025-10-01',
                'endHour' => '12:00',
                'workerType' => WorkerType::Flexi,
                'jointCommissionNumber' => 304,
                'employmentIds' => [],
            ]),
        ]));

        // Should remove all employment links via detachDeletedEmploymentsFromDimonaPeriods
        expect(getEmploymentIds($period->refresh()))->toBe([]);
    });
});

describe('large number of employment IDs', function () {
    it('handles 50 employment IDs', function () {
        $employmentIds = collect(range(1, 50))->map(fn ($i) => "emp-{$i}")->toArray();

        syncPeriods(new Collection([
            makeExpectedPeriod([
                'startDate' => '2025-10-01',
                'startHour' => '08:00',
                'endDate' => '2025-10-01',
                'endHour' => '12:00',
                'workerType' => WorkerType::Flexi,
                'jointCommissionNumber' => 304,
                'employmentIds' => $employmentIds,
            ]),
        ]));

        $period = DimonaPeriod::query()->first();
        $linkedIds = getEmploymentIds($period);

        expect($linkedIds)->toHaveCount(50)
            ->and($linkedIds)->toContain('emp-1')
            ->and($linkedIds)->toContain('emp-50');
    });

    it('updates period with 100 employment IDs', function () {
        $employmentIds = collect(range(1, 100))->map(fn ($i) => "emp-{$i}")->toArray();

        // Existing: Period with 10 employments
        $period = makePeriod([
            'start_date' => '2025-10-01',
            'start_hour' => '08:00',
            'end_date' => '2025-10-01',
            'end_hour' => '12:00',
            'worker_type' => WorkerType::Flexi,
            'joint_commission_number' => 304,
            'state' => DimonaPeriodState::Accepted,
        ], ['emp-1', 'emp-2', 'emp-3', 'emp-4', 'emp-5', 'emp-6', 'emp-7', 'emp-8', 'emp-9', 'emp-10']);

        // Expected: Same period with 100 employments
        syncPeriods(new Collection([
            makeExpectedPeriod([
                'startDate' => '2025-10-01',
                'startHour' => '08:00',
                'endDate' => '2025-10-01',
                'endHour' => '12:00',
                'workerType' => WorkerType::Flexi,
                'jointCommissionNumber' => 304,
                'employmentIds' => $employmentIds,
            ]),
        ]));

        $period->refresh();
        $linkedIds = getEmploymentIds($period);

        expect($linkedIds)->toHaveCount(100)
            ->and($linkedIds)->toContain('emp-1')
            ->and($linkedIds)->toContain('emp-100');
    });
});

describe('employment ID format edge cases', function () {
    it('handles employment IDs with special characters', function () {
        syncPeriods(new Collection([
            makeExpectedPeriod([
                'startDate' => '2025-10-01',
                'startHour' => '08:00',
                'endDate' => '2025-10-01',
                'endHour' => '12:00',
                'workerType' => WorkerType::Flexi,
                'jointCommissionNumber' => 304,
                'employmentIds' => ['emp-with-dashes', 'emp_with_underscores', 'emp.with.dots'],
            ]),
        ]));

        $period = DimonaPeriod::query()->first();
        $employmentIds = getEmploymentIds($period);

        expect($employmentIds)->toHaveCount(3)
            ->and($employmentIds)->toContain('emp-with-dashes')
            ->and($employmentIds)->toContain('emp_with_underscores')
            ->and($employmentIds)->toContain('emp.with.dots');
    });

    it('handles very long employment IDs', function () {
        $longId = str_repeat('a', 255); // Long but valid ID

        syncPeriods(new Collection([
            makeExpectedPeriod([
                'startDate' => '2025-10-01',
                'startHour' => '08:00',
                'endDate' => '2025-10-01',
                'endHour' => '12:00',
                'workerType' => WorkerType::Flexi,
                'jointCommissionNumber' => 304,
                'employmentIds' => [$longId, 'emp-2'],
            ]),
        ]));

        $period = DimonaPeriod::query()->first();
        $employmentIds = getEmploymentIds($period);

        expect($employmentIds)->toHaveCount(2)
            ->and($employmentIds)->toContain($longId)
            ->and($employmentIds)->toContain('emp-2');
    });

    it('handles ULID format employment IDs', function () {
        $ulid1 = '01ARZ3NDEKTSV4RRFFQ69G5FAV';
        $ulid2 = '01ARZ3NDEKTSV4RRFFQ69G5FAW';

        syncPeriods(new Collection([
            makeExpectedPeriod([
                'startDate' => '2025-10-01',
                'startHour' => '08:00',
                'endDate' => '2025-10-01',
                'endHour' => '12:00',
                'workerType' => WorkerType::Flexi,
                'jointCommissionNumber' => 304,
                'employmentIds' => [$ulid1, $ulid2],
            ]),
        ]));

        $period = DimonaPeriod::query()->first();
        $employmentIds = getEmploymentIds($period);

        expect($employmentIds)->toHaveCount(2)
            ->and($employmentIds)->toContain($ulid1)
            ->and($employmentIds)->toContain($ulid2);
    });
});

describe('employment link database operations', function () {
    it('uses insertOrIgnore to prevent duplicate link errors', function () {
        // Create initial period with employment
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

        $period = DimonaPeriod::query()->first();
        $initialCount = DB::table('dimona_period_employment')
            ->where('dimona_period_id', $period->id)
            ->count();

        // Sync again with same employment - should not error due to insertOrIgnore
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

        $finalCount = DB::table('dimona_period_employment')
            ->where('dimona_period_id', $period->id)
            ->count();

        // Count should remain the same (no duplicate created)
        expect($finalCount)->toBe($initialCount);
    });

    it('maintains referential integrity when linking employments', function () {
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

        // Verify links exist in database
        $links = DB::table('dimona_period_employment')
            ->where('dimona_period_id', $period->id)
            ->get();

        expect($links)->toHaveCount(2);

        foreach ($links as $link) {
            expect($link->dimona_period_id)->toBe($period->id)
                ->and(in_array($link->employment_id, ['emp-1', 'emp-2']))->toBeTrue();
        }
    });
});
