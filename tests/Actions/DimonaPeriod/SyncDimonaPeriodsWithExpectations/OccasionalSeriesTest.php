<?php

use Hyperlab\Dimona\Actions\DimonaPeriod\ComputeExpectedDimonaPeriods;
use Hyperlab\Dimona\Enums\DimonaPeriodState;
use Hyperlab\Dimona\Enums\WorkerType;
use Hyperlab\Dimona\Models\DimonaPeriod;
use Hyperlab\Dimona\Tests\Factories\EmploymentDataFactory;

require_once __DIR__.'/Helpers.php';

beforeEach(function () {
    setupTestContext();
});

/**
 * Compute the expected periods for occasional employments from 18:00 to 23:00
 * on each of the given days, and sync them.
 */
function syncOccasionalEmploymentsOnDays(array $days): void
{
    syncPeriods(ComputeExpectedDimonaPeriods::new()->execute(
        test()->employerNumber, test()->workerSsn, EmploymentDataFactory::occasionalOnDays($days)
    ));
}

function makeOccasionalPeriod(string $day, array $overrides = []): DimonaPeriod
{
    return makePeriod(array_merge([
        'joint_commission_number' => 302,
        'worker_type' => WorkerType::Occasional,
        'start_date' => $day,
        'start_hour' => '18:00',
        'end_date' => $day,
        'end_hour' => '23:00',
        'number_of_hours' => null,
    ], $overrides), ["emp-{$day}"]);
}

function makeOtherPeriodForSeries(array $days, array $overrides = []): DimonaPeriod
{
    return makePeriod(array_merge([
        'joint_commission_number' => 302,
        'worker_type' => WorkerType::Other,
        'start_date' => $days[0],
        'start_hour' => null,
        'end_date' => end($days),
        'end_hour' => null,
        'number_of_hours' => null,
    ], $overrides), array_map(fn (string $day) => "emp-{$day}", $days));
}

it('declares a new occasional period per day', function () {
    syncOccasionalEmploymentsOnDays(['2025-10-01', '2025-10-02']);

    $periods = DimonaPeriod::query()->orderBy('start_date')->get();

    expect($periods)->toHaveCount(2)
        ->and($periods->pluck('worker_type')->unique()->all())->toBe([WorkerType::Occasional])
        ->and($periods->pluck('state')->unique()->all())->toBe([DimonaPeriodState::New])
        ->and(getEmploymentIds($periods[0]))->toBe(['emp-2025-10-01'])
        ->and(getEmploymentIds($periods[1]))->toBe(['emp-2025-10-02']);
});

it('leaves accepted occasional periods untouched when nothing changed', function () {
    $day1 = makeOccasionalPeriod('2025-10-01');
    $day2 = makeOccasionalPeriod('2025-10-02');

    syncOccasionalEmploymentsOnDays(['2025-10-01', '2025-10-02']);

    expect(DimonaPeriod::query()->count())->toBe(2)
        ->and($day1->fresh()->state)->toBe(DimonaPeriodState::Accepted)
        ->and($day2->fresh()->state)->toBe(DimonaPeriodState::Accepted)
        ->and(getEmploymentIds($day1))->toBe(['emp-2025-10-01'])
        ->and(getEmploymentIds($day2))->toBe(['emp-2025-10-02']);
});

it('replaces the occasional periods by a single other period when a third consecutive day is added', function () {
    $day1 = makeOccasionalPeriod('2025-10-01');
    $day2 = makeOccasionalPeriod('2025-10-02');

    syncOccasionalEmploymentsOnDays(['2025-10-01', '2025-10-02', '2025-10-03']);

    $other = DimonaPeriod::query()->where('worker_type', WorkerType::Other)->sole();

    expect($other->state)->toBe(DimonaPeriodState::New)
        ->and($other->start_date)->toBe('2025-10-01')
        ->and($other->end_date)->toBe('2025-10-03')
        ->and(getEmploymentIds($other))->toBe(['emp-2025-10-01', 'emp-2025-10-02', 'emp-2025-10-03'])
        // The occasional periods lose their employments, so they get cancelled
        ->and(getEmploymentIds($day1))->toBe([])
        ->and(getEmploymentIds($day2))->toBe([])
        ->and($day1->fresh()->state)->toBe(DimonaPeriodState::Accepted)
        ->and($day2->fresh()->state)->toBe(DimonaPeriodState::Accepted);
});

it('replaces the other period by occasional periods when the series shrinks to two days', function () {
    $other = makeOtherPeriodForSeries(['2025-10-01', '2025-10-02', '2025-10-03']);

    syncOccasionalEmploymentsOnDays(['2025-10-01', '2025-10-02']);

    $occasional = DimonaPeriod::query()->where('worker_type', WorkerType::Occasional)->orderBy('start_date')->get();

    expect($occasional)->toHaveCount(2)
        ->and($occasional->pluck('state')->unique()->all())->toBe([DimonaPeriodState::New])
        ->and(getEmploymentIds($occasional[0]))->toBe(['emp-2025-10-01'])
        ->and(getEmploymentIds($occasional[1]))->toBe(['emp-2025-10-02'])
        // The other period loses its employments, so it gets cancelled
        ->and(getEmploymentIds($other))->toBe([])
        ->and($other->fresh()->state)->toBe(DimonaPeriodState::Accepted);
});

it('extends the other period when a fourth consecutive day is added', function () {
    $other = makeOtherPeriodForSeries(['2025-10-01', '2025-10-02', '2025-10-03']);

    syncOccasionalEmploymentsOnDays(['2025-10-01', '2025-10-02', '2025-10-03', '2025-10-04']);

    $other->refresh();

    expect(DimonaPeriod::query()->count())->toBe(1)
        ->and($other->state)->toBe(DimonaPeriodState::Outdated)
        ->and($other->start_date)->toBe('2025-10-01')
        ->and($other->end_date)->toBe('2025-10-04')
        ->and(getEmploymentIds($other))->toBe(['emp-2025-10-01', 'emp-2025-10-02', 'emp-2025-10-03', 'emp-2025-10-04']);
});

it('shortens the other period when the last day of a longer series is removed', function () {
    $other = makeOtherPeriodForSeries(['2025-10-01', '2025-10-02', '2025-10-03', '2025-10-04']);

    syncOccasionalEmploymentsOnDays(['2025-10-01', '2025-10-02', '2025-10-03']);

    $other->refresh();

    expect(DimonaPeriod::query()->count())->toBe(1)
        ->and($other->state)->toBe(DimonaPeriodState::Outdated)
        ->and($other->end_date)->toBe('2025-10-03')
        ->and(getEmploymentIds($other))->toBe(['emp-2025-10-01', 'emp-2025-10-02', 'emp-2025-10-03']);
});

it('replaces the other period when the first day of a longer series is removed', function () {
    $other = makeOtherPeriodForSeries(['2025-10-01', '2025-10-02', '2025-10-03', '2025-10-04']);

    syncOccasionalEmploymentsOnDays(['2025-10-02', '2025-10-03', '2025-10-04']);

    $new = DimonaPeriod::query()->whereKeyNot($other->id)->sole();

    expect($new->worker_type)->toBe(WorkerType::Other)
        ->and($new->state)->toBe(DimonaPeriodState::New)
        ->and($new->start_date)->toBe('2025-10-02')
        ->and($new->end_date)->toBe('2025-10-04')
        ->and(getEmploymentIds($new))->toBe(['emp-2025-10-02', 'emp-2025-10-03', 'emp-2025-10-04'])
        ->and(getEmploymentIds($other))->toBe([]);
});

it('keeps employments linked to periods that are no longer active', function (DimonaPeriodState $state) {
    $inactive = makePeriod([
        'joint_commission_number' => 302,
        'worker_type' => WorkerType::Flexi,
        'start_date' => '2025-10-01',
        'start_hour' => '18:00',
        'end_date' => '2025-10-01',
        'end_hour' => '23:00',
        'state' => $state,
    ], ['emp-2025-10-01']);

    syncOccasionalEmploymentsOnDays(['2025-10-01']);

    $occasional = DimonaPeriod::query()->where('worker_type', WorkerType::Occasional)->sole();

    expect(getEmploymentIds($occasional))->toBe(['emp-2025-10-01'])
        ->and(getEmploymentIds($inactive))->toBe(['emp-2025-10-01']);
})->with([
    DimonaPeriodState::Refused,
    DimonaPeriodState::AcceptedWithWarning,
    DimonaPeriodState::Cancelled,
]);
