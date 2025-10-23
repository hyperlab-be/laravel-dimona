<?php

use Hyperlab\Dimona\Enums\DimonaPeriodState;
use Hyperlab\Dimona\Enums\WorkerType;
use Illuminate\Support\Collection;

require_once __DIR__.'/Helpers.php';

beforeEach(function () {
    setupTestContext();
});

describe('basic employment detachment', function () {
    it('removes employment link that is not in the current employments list', function () {
        // Existing: Accepted period (2025-10-01 08:00-12:00) linked to emp-1, emp-2, emp-3
        $period = makePeriod([
            'start_date' => '2025-10-01',
            'start_hour' => '08:00',
            'end_date' => '2025-10-01',
            'end_hour' => '12:00',
            'worker_type' => WorkerType::Flexi,
            'joint_commission_number' => 304,
            'state' => DimonaPeriodState::Accepted,
        ], ['emp-1', 'emp-2', 'emp-3']);

        // Expected: Same period but only with emp-1 and emp-2
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

        // Should remove emp-3, keep emp-1 and emp-2
        expect(getEmploymentIds($period->refresh()))->toBe(['emp-1', 'emp-2']);
    });

    it('removes all employment links when employments list is empty', function () {
        // Existing: Accepted period (2025-10-01 08:00-12:00) linked to emp-1, emp-2
        $period = makePeriod([
            'start_date' => '2025-10-01',
            'start_hour' => '08:00',
            'end_date' => '2025-10-01',
            'end_hour' => '12:00',
            'worker_type' => WorkerType::Flexi,
            'joint_commission_number' => 304,
            'state' => DimonaPeriodState::Accepted,
        ], ['emp-1', 'emp-2']);

        // Expected: No periods
        syncPeriods(new Collection([]));

        // Should remove all employment links
        expect(getEmploymentIds($period->refresh()))->toBe([]);
    });

    it('keeps all employment links when all are in the current list', function () {
        // Existing: Accepted period (2025-10-01 08:00-12:00) linked to emp-1, emp-2
        $period = makePeriod([
            'start_date' => '2025-10-01',
            'start_hour' => '08:00',
            'end_date' => '2025-10-01',
            'end_hour' => '12:00',
            'worker_type' => WorkerType::Flexi,
            'joint_commission_number' => 304,
            'state' => DimonaPeriodState::Accepted,
        ], ['emp-1', 'emp-2']);

        // Expected: Same period with same employments
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

        // Should keep all employment links
        expect(getEmploymentIds($period->refresh()))->toBe(['emp-1', 'emp-2']);
    });

    it('handles multiple periods with mixed employment links', function () {
        // Existing: Two periods on different dates with emp-deleted
        $period1 = makePeriod([
            'start_date' => '2025-10-01',
            'start_hour' => '08:00',
            'end_date' => '2025-10-01',
            'end_hour' => '12:00',
            'worker_type' => WorkerType::Flexi,
            'joint_commission_number' => 304,
            'state' => DimonaPeriodState::Accepted,
        ], ['emp-1', 'emp-deleted']);

        $period2 = makePeriod([
            'start_date' => '2025-10-02',
            'start_hour' => '08:00',
            'end_date' => '2025-10-02',
            'end_hour' => '12:00',
            'worker_type' => WorkerType::Flexi,
            'joint_commission_number' => 304,
            'state' => DimonaPeriodState::Accepted,
        ], ['emp-2', 'emp-deleted']);

        // Expected: Two periods on different dates, emp-deleted removed
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

        $period1Links = getEmploymentIds($period1->refresh());
        $period2Links = getEmploymentIds($period2->refresh());

        // Should remove emp-deleted from both periods
        expect($period1Links)->toBe(['emp-1'])
            ->and($period2Links)->toBe(['emp-2']);
    });
});

describe('state filtering', function () {
    it('affects periods in all states', function () {
        // Existing: Periods in all possible states linked to emp-deleted
        $states = [
            DimonaPeriodState::New,
            DimonaPeriodState::Pending,
            DimonaPeriodState::Waiting,
            DimonaPeriodState::Accepted,
            DimonaPeriodState::AcceptedWithWarning,
            DimonaPeriodState::Refused,
            DimonaPeriodState::Cancelled,
            DimonaPeriodState::Failed,
            DimonaPeriodState::Outdated,
        ];

        $periods = [];
        foreach ($states as $state) {
            $periods[] = makePeriod([
                'start_date' => '2025-10-01',
                'start_hour' => '08:00',
                'end_date' => '2025-10-01',
                'end_hour' => '12:00',
                'worker_type' => WorkerType::Flexi,
                'joint_commission_number' => 304,
                'state' => $state,
            ], ['emp-deleted']);
        }

        // Expected: No periods (employments should be removed)
        syncPeriods(new Collection([]));

        // Should remove employments from all periods regardless of state
        foreach ($periods as $period) {
            $count = count(getEmploymentIds($period->refresh()));
            expect($count)->toBe(0);
        }
    });
});

describe('scope filtering', function () {
    it('only affects periods within the specified date range', function () {
        // Existing: Two periods, one in range (2025-10-15), one out of range (2025-11-02)
        $periodInRange = makePeriod([
            'start_date' => '2025-10-15',
            'start_hour' => '08:00',
            'end_date' => '2025-10-15',
            'end_hour' => '12:00',
            'worker_type' => WorkerType::Flexi,
            'joint_commission_number' => 304,
            'state' => DimonaPeriodState::Accepted,
        ], ['emp-deleted']);

        $periodOutOfRange = makePeriod([
            'start_date' => '2025-11-02',
            'start_hour' => '08:00',
            'end_date' => '2025-11-02',
            'end_hour' => '12:00',
            'worker_type' => WorkerType::Flexi,
            'joint_commission_number' => 304,
            'state' => DimonaPeriodState::Accepted,
        ], ['emp-deleted']);

        // Expected: No periods (sync period is 2025-09-01 to 2025-11-01)
        syncPeriods(new Collection([]));

        $inRangeCount = count(getEmploymentIds($periodInRange->refresh()));
        $outOfRangeCount = count(getEmploymentIds($periodOutOfRange->refresh()));

        // Should only affect period within sync period range
        expect($inRangeCount)->toBe(0)
            ->and($outOfRangeCount)->toBe(1);
    });

    it('only affects periods for the specified employer', function () {
        // Existing: Two periods, one for default employer, one for different employer
        $matchingPeriod = makePeriod([
            'employer_enterprise_number' => '0123456789',
            'start_date' => '2025-10-01',
            'start_hour' => '08:00',
            'end_date' => '2025-10-01',
            'end_hour' => '12:00',
            'worker_type' => WorkerType::Flexi,
            'joint_commission_number' => 304,
            'state' => DimonaPeriodState::Accepted,
        ], ['emp-deleted']);

        $otherEmployerPeriod = makePeriod([
            'employer_enterprise_number' => '9999999999',
            'start_date' => '2025-10-01',
            'start_hour' => '08:00',
            'end_date' => '2025-10-01',
            'end_hour' => '12:00',
            'worker_type' => WorkerType::Flexi,
            'joint_commission_number' => 304,
            'state' => DimonaPeriodState::Accepted,
        ], ['emp-deleted']);

        // Expected: No periods (for default employer 0123456789)
        syncPeriods(new Collection([]));

        $matchingCount = count(getEmploymentIds($matchingPeriod->refresh()));
        $otherCount = count(getEmploymentIds($otherEmployerPeriod->refresh()));

        // Should only affect period for the specified employer
        expect($matchingCount)->toBe(0)
            ->and($otherCount)->toBe(1);
    });

    it('only affects periods for the specified worker', function () {
        // Existing: Two periods, one for default worker, one for different worker
        $matchingPeriod = makePeriod([
            'worker_social_security_number' => '12345678901',
            'start_date' => '2025-10-01',
            'start_hour' => '08:00',
            'end_date' => '2025-10-01',
            'end_hour' => '12:00',
            'worker_type' => WorkerType::Flexi,
            'joint_commission_number' => 304,
            'state' => DimonaPeriodState::Accepted,
        ], ['emp-deleted']);

        $otherWorkerPeriod = makePeriod([
            'worker_social_security_number' => '99999999999',
            'start_date' => '2025-10-01',
            'start_hour' => '08:00',
            'end_date' => '2025-10-01',
            'end_hour' => '12:00',
            'worker_type' => WorkerType::Flexi,
            'joint_commission_number' => 304,
            'state' => DimonaPeriodState::Accepted,
        ], ['emp-deleted']);

        // Expected: No periods (for default worker 12345678901)
        syncPeriods(new Collection([]));

        $matchingCount = count(getEmploymentIds($matchingPeriod->refresh()));
        $otherCount = count(getEmploymentIds($otherWorkerPeriod->refresh()));

        // Should only affect period for the specified worker
        expect($matchingCount)->toBe(0)
            ->and($otherCount)->toBe(1);
    });
});
