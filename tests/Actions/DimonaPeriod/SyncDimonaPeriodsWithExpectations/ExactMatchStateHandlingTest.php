<?php

use Hyperlab\Dimona\Enums\DimonaPeriodState;
use Hyperlab\Dimona\Enums\WorkerType;
use Hyperlab\Dimona\Models\DimonaPeriod;
use Illuminate\Support\Collection;
use LogicException;

require_once __DIR__.'/Helpers.php';

beforeEach(function () {
    setupTestContext();
});

describe('exact match with Pending or Waiting state', function () {
    it('throws LogicException when exact match exists in Pending state', function () {
        // Existing: Period in Pending state with exact match
        makePeriod([
            'start_date' => '2025-10-01',
            'start_hour' => '08:00',
            'end_date' => '2025-10-01',
            'end_hour' => '12:00',
            'number_of_hours' => null,
            'worker_type' => WorkerType::Flexi,
            'joint_commission_number' => 304,
            'state' => DimonaPeriodState::Pending,
        ], ['emp-1']);

        // Expected: Exact same period
        expect(function () {
            syncPeriods(new Collection([
                makeExpectedPeriod([
                    'startDate' => '2025-10-01',
                    'startHour' => '08:00',
                    'endDate' => '2025-10-01',
                    'endHour' => '12:00',
                    'numberOfHours' => null,
                    'workerType' => WorkerType::Flexi,
                    'jointCommissionNumber' => 304,
                    'employmentIds' => ['emp-1'],
                ]),
            ]));
        })->toThrow(LogicException::class, 'Pending and waiting states should be resolved before syncing.');
    });

    it('throws LogicException when exact match exists in Waiting state', function () {
        // Existing: Period in Waiting state with exact match
        makePeriod([
            'start_date' => '2025-10-01',
            'start_hour' => '08:00',
            'end_date' => '2025-10-01',
            'end_hour' => '12:00',
            'number_of_hours' => null,
            'worker_type' => WorkerType::Flexi,
            'joint_commission_number' => 304,
            'state' => DimonaPeriodState::Waiting,
        ], ['emp-1']);

        // Expected: Exact same period
        expect(function () {
            syncPeriods(new Collection([
                makeExpectedPeriod([
                    'startDate' => '2025-10-01',
                    'startHour' => '08:00',
                    'endDate' => '2025-10-01',
                    'endHour' => '12:00',
                    'numberOfHours' => null,
                    'workerType' => WorkerType::Flexi,
                    'jointCommissionNumber' => 304,
                    'employmentIds' => ['emp-1'],
                ]),
            ]));
        })->toThrow(LogicException::class, 'Pending and waiting states should be resolved before syncing.');
    });

    it('does not throw when match is not exact but period is Pending', function () {
        // Existing: Period in Pending state
        $pendingPeriod = makePeriod([
            'start_date' => '2025-10-01',
            'start_hour' => '08:00',
            'end_date' => '2025-10-01',
            'end_hour' => '12:00',
            'worker_type' => WorkerType::Flexi,
            'joint_commission_number' => 304,
            'state' => DimonaPeriodState::Pending,
        ], ['emp-1']);

        // Expected: Different hours (not exact match)
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

        // Should create new period instead of throwing
        expect(DimonaPeriod::query()->count())->toBe(2);
        expect($pendingPeriod->refresh()->state)->toBe(DimonaPeriodState::Pending);
    });
});

describe('exact match with Failed state', function () {
    it('leaves failed period unchanged when exact match exists', function () {
        // Existing: Period in Failed state
        $failedPeriod = makePeriod([
            'start_date' => '2025-10-01',
            'start_hour' => '08:00',
            'end_date' => '2025-10-01',
            'end_hour' => '12:00',
            'number_of_hours' => null,
            'worker_type' => WorkerType::Flexi,
            'joint_commission_number' => 304,
            'state' => DimonaPeriodState::Failed,
        ], ['emp-1']);

        // Expected: Exact same period
        syncPeriods(new Collection([
            makeExpectedPeriod([
                'startDate' => '2025-10-01',
                'startHour' => '08:00',
                'endDate' => '2025-10-01',
                'endHour' => '12:00',
                'numberOfHours' => null,
                'workerType' => WorkerType::Flexi,
                'jointCommissionNumber' => 304,
                'employmentIds' => ['emp-1'],
            ]),
        ]));

        // Should not change state or create new period
        expect(DimonaPeriod::query()->count())->toBe(1)
            ->and($failedPeriod->refresh()->state)->toBe(DimonaPeriodState::Failed)
            ->and(getEmploymentIds($failedPeriod))->toBe(['emp-1']);
    });

    it('creates new period when data changes but failed period exists', function () {
        // Existing: Period in Failed state
        $failedPeriod = makePeriod([
            'start_date' => '2025-10-01',
            'start_hour' => '08:00',
            'end_date' => '2025-10-01',
            'end_hour' => '12:00',
            'worker_type' => WorkerType::Flexi,
            'joint_commission_number' => 304,
            'state' => DimonaPeriodState::Failed,
        ], ['emp-1']);

        // Expected: Different hours
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

        // Should create new period, leave failed period unchanged
        expect(DimonaPeriod::query()->count())->toBe(2)
            ->and($failedPeriod->refresh()->state)->toBe(DimonaPeriodState::Failed)
            ->and($failedPeriod->start_hour)->toBe('08:00');

        $newPeriod = DimonaPeriod::query()->where('state', DimonaPeriodState::New)->first();
        expect($newPeriod)->not->toBeNull()
            ->and($newPeriod->start_hour)->toBe('09:00')
            ->and(getEmploymentIds($newPeriod))->toBe(['emp-1']);
    });
});

describe('exact match with AcceptedWithWarning state', function () {
    it('creates new period when exact match has AcceptedWithWarning state', function () {
        // Existing: Period in AcceptedWithWarning state (should be replaced)
        $periodWithWarning = makePeriod([
            'start_date' => '2025-10-01',
            'start_hour' => '08:00',
            'end_date' => '2025-10-01',
            'end_hour' => '12:00',
            'number_of_hours' => null,
            'worker_type' => WorkerType::Flexi,
            'joint_commission_number' => 304,
            'state' => DimonaPeriodState::AcceptedWithWarning,
        ], ['emp-1']);

        // Expected: Exact same period data
        syncPeriods(new Collection([
            makeExpectedPeriod([
                'startDate' => '2025-10-01',
                'startHour' => '08:00',
                'endDate' => '2025-10-01',
                'endHour' => '12:00',
                'numberOfHours' => null,
                'workerType' => WorkerType::Flexi,
                'jointCommissionNumber' => 304,
                'employmentIds' => ['emp-1'],
            ]),
        ]));

        // Should create new period to replace the AcceptedWithWarning one
        expect(DimonaPeriod::query()->count())->toBe(2)
            ->and($periodWithWarning->refresh()->state)->toBe(DimonaPeriodState::AcceptedWithWarning);

        $newPeriod = DimonaPeriod::query()->where('state', DimonaPeriodState::New)->first();
        expect($newPeriod)->not->toBeNull()
            ->and($newPeriod->start_hour)->toBe('08:00')
            ->and(getEmploymentIds($newPeriod))->toBe(['emp-1']);
    });

    it('creates new period with different data when AcceptedWithWarning exists', function () {
        // Existing: Period in AcceptedWithWarning state
        $periodWithWarning = makePeriod([
            'start_date' => '2025-10-01',
            'start_hour' => '08:00',
            'end_date' => '2025-10-01',
            'end_hour' => '12:00',
            'worker_type' => WorkerType::Flexi,
            'joint_commission_number' => 304,
            'state' => DimonaPeriodState::AcceptedWithWarning,
        ], ['emp-1']);

        // Expected: Different hours
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

        // Should create new period
        expect(DimonaPeriod::query()->count())->toBe(2);

        $newPeriod = DimonaPeriod::query()->where('state', DimonaPeriodState::New)->first();
        expect($newPeriod)->not->toBeNull()
            ->and($newPeriod->start_hour)->toBe('09:00')
            ->and($newPeriod->end_hour)->toBe('13:00')
            ->and(getEmploymentIds($newPeriod))->toBe(['emp-1']);

        // Old period should remain unchanged
        expect($periodWithWarning->refresh()->start_hour)->toBe('08:00');
    });
});

describe('exact match with Cancelled state', function () {
    it('creates new period when exact match has Cancelled state', function () {
        // Existing: Period in Cancelled state (should be replaced)
        $cancelledPeriod = makePeriod([
            'start_date' => '2025-10-01',
            'start_hour' => '08:00',
            'end_date' => '2025-10-01',
            'end_hour' => '12:00',
            'number_of_hours' => null,
            'worker_type' => WorkerType::Flexi,
            'joint_commission_number' => 304,
            'state' => DimonaPeriodState::Cancelled,
        ], ['emp-1']);

        // Expected: Exact same period data
        syncPeriods(new Collection([
            makeExpectedPeriod([
                'startDate' => '2025-10-01',
                'startHour' => '08:00',
                'endDate' => '2025-10-01',
                'endHour' => '12:00',
                'numberOfHours' => null,
                'workerType' => WorkerType::Flexi,
                'jointCommissionNumber' => 304,
                'employmentIds' => ['emp-1'],
            ]),
        ]));

        // Should create new period to replace the Cancelled one
        expect(DimonaPeriod::query()->count())->toBe(2)
            ->and($cancelledPeriod->refresh()->state)->toBe(DimonaPeriodState::Cancelled);

        $newPeriod = DimonaPeriod::query()->where('state', DimonaPeriodState::New)->first();
        expect($newPeriod)->not->toBeNull()
            ->and($newPeriod->start_hour)->toBe('08:00')
            ->and(getEmploymentIds($newPeriod))->toBe(['emp-1']);
    });
});
