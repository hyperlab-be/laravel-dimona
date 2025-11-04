<?php

use Hyperlab\Dimona\Enums\DimonaPeriodState;
use Hyperlab\Dimona\Enums\WorkerType;
use Hyperlab\Dimona\Events\DimonaPeriodCreated;
use Hyperlab\Dimona\Events\DimonaPeriodUpdated;
use Hyperlab\Dimona\Models\DimonaPeriod;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Event;

require_once __DIR__.'/Helpers.php';

beforeEach(function () {
    setupTestContext();
});

it('dispatches DimonaPeriodCreated event when creating a new period', function () {
    Event::fake();

    // No existing periods
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

    Event::assertDispatched(DimonaPeriodCreated::class, function ($event) {
        return $event->dimonaPeriod instanceof DimonaPeriod
            && $event->dimonaPeriod->start_date === '2025-10-01'
            && $event->dimonaPeriod->worker_type === WorkerType::Flexi;
    });
});

it('dispatches multiple DimonaPeriodCreated events when creating multiple periods', function () {
    Event::fake();

    syncPeriods(new Collection([
        makeExpectedPeriod([
            'startDate' => '2025-10-01',
            'employmentIds' => ['emp-1'],
        ]),
        makeExpectedPeriod([
            'startDate' => '2025-10-02',
            'employmentIds' => ['emp-2'],
        ]),
    ]));

    Event::assertDispatched(DimonaPeriodCreated::class, 2);
});

it('dispatches DimonaPeriodUpdated event when period fields are updated', function () {
    Event::fake();

    // Create an existing accepted period
    makePeriod([
        'start_date' => '2025-10-01',
        'start_hour' => '08:00',
        'end_date' => '2025-10-01',
        'end_hour' => '12:00',
        'state' => DimonaPeriodState::Accepted,
    ], ['emp-1']);

    // Sync with updated end time
    syncPeriods(new Collection([
        makeExpectedPeriod([
            'startDate' => '2025-10-01',
            'startHour' => '08:00',
            'endDate' => '2025-10-01',
            'endHour' => '16:00', // Changed from 12:00 to 16:00
            'employmentIds' => ['emp-1'],
        ]),
    ]));

    Event::assertDispatched(DimonaPeriodUpdated::class, function ($event) {
        return $event->dimonaPeriod instanceof DimonaPeriod
            && $event->dimonaPeriod->end_hour === '16:00'
            && $event->dimonaPeriod->state === DimonaPeriodState::Outdated;
    });
});

it('dispatches DimonaPeriodUpdated event when employments are added to existing period', function () {
    Event::fake();

    // Create an existing accepted period with one employment
    makePeriod([
        'start_date' => '2025-10-01',
        'start_hour' => '08:00',
        'end_date' => '2025-10-01',
        'end_hour' => '12:00',
        'state' => DimonaPeriodState::Accepted,
    ], ['emp-1']);

    // Sync with an additional employment (emp-2)
    syncPeriods(new Collection([
        makeExpectedPeriod([
            'startDate' => '2025-10-01',
            'startHour' => '08:00',
            'endDate' => '2025-10-01',
            'endHour' => '12:00',
            'employmentIds' => ['emp-1', 'emp-2'], // Added emp-2
        ]),
    ]));

    Event::assertDispatched(DimonaPeriodUpdated::class, function ($event) {
        $employmentIds = getEmploymentIds($event->dimonaPeriod);

        return $event->dimonaPeriod instanceof DimonaPeriod
            && in_array('emp-1', $employmentIds)
            && in_array('emp-2', $employmentIds);
    });
});

it('dispatches DimonaPeriodUpdated event when both fields and employments change', function () {
    Event::fake();

    // Create an existing accepted period
    makePeriod([
        'start_date' => '2025-10-01',
        'start_hour' => '08:00',
        'end_date' => '2025-10-01',
        'end_hour' => '12:00',
        'state' => DimonaPeriodState::Accepted,
    ], ['emp-1']);

    // Sync with updated fields and additional employment
    syncPeriods(new Collection([
        makeExpectedPeriod([
            'startDate' => '2025-10-01',
            'startHour' => '08:00',
            'endDate' => '2025-10-01',
            'endHour' => '16:00', // Changed time
            'employmentIds' => ['emp-1', 'emp-2'], // Added employment
        ]),
    ]));

    Event::assertDispatched(DimonaPeriodUpdated::class, 1);
});

it('does not dispatch DimonaPeriodUpdated event when nothing changes', function () {
    Event::fake();

    // Create an existing accepted period
    makePeriod([
        'start_date' => '2025-10-01',
        'start_hour' => '08:00',
        'end_date' => '2025-10-01',
        'end_hour' => '12:00',
        'number_of_hours' => null,
        'state' => DimonaPeriodState::Accepted,
    ], ['emp-1']);

    // Sync with identical data
    syncPeriods(new Collection([
        makeExpectedPeriod([
            'startDate' => '2025-10-01',
            'startHour' => '08:00',
            'endDate' => '2025-10-01',
            'endHour' => '12:00',
            'employmentIds' => ['emp-1'], // Same employment
        ]),
    ]));

    Event::assertNotDispatched(DimonaPeriodUpdated::class);
});

it('does not dispatch DimonaPeriodCreated event when exact match already exists', function () {
    Event::fake();

    // Create an existing new period
    makePeriod([
        'start_date' => '2025-10-01',
        'start_hour' => '08:00',
        'end_date' => '2025-10-01',
        'end_hour' => '12:00',
        'state' => DimonaPeriodState::New,
    ], ['emp-1']);

    // Sync with identical data
    syncPeriods(new Collection([
        makeExpectedPeriod([
            'startDate' => '2025-10-01',
            'startHour' => '08:00',
            'endDate' => '2025-10-01',
            'endHour' => '12:00',
            'employmentIds' => ['emp-1'],
        ]),
    ]));

    // Should not create a new period or dispatch event
    Event::assertNotDispatched(DimonaPeriodCreated::class);
    expect(DimonaPeriod::query()->count())->toBe(1);
});

it('dispatches DimonaPeriodUpdated when reusing an unused period', function () {
    Event::fake();

    // Create an unused accepted period (no employments linked)
    makePeriod([
        'start_date' => '2025-10-01',
        'start_hour' => '08:00',
        'end_date' => '2025-10-01',
        'end_hour' => '12:00',
        'state' => DimonaPeriodState::Accepted,
    ], []); // No employments

    // Sync with same period but add employment
    syncPeriods(new Collection([
        makeExpectedPeriod([
            'startDate' => '2025-10-01',
            'startHour' => '08:00',
            'endDate' => '2025-10-01',
            'endHour' => '12:00',
            'employmentIds' => ['emp-1'], // Add employment to unused period
        ]),
    ]));

    // Should dispatch updated event when linking employment to unused period
    Event::assertDispatched(DimonaPeriodUpdated::class, 1);
    Event::assertNotDispatched(DimonaPeriodCreated::class);
});
