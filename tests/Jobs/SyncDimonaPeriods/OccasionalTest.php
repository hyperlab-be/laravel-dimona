<?php

use Carbon\CarbonPeriodImmutable;
use Hyperlab\Dimona\Enums\DimonaPeriodState;
use Hyperlab\Dimona\Enums\WorkerType;
use Hyperlab\Dimona\Jobs\SyncDimonaPeriodsJob;
use Hyperlab\Dimona\Models\DimonaPeriod;
use Hyperlab\Dimona\Tests\Factories\EmploymentDataFactory;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;

beforeEach(function () {
    Http::preventStrayRequests();
    Queue::fake();

    $this->employerEnterpriseNumber = '0123456789';
    $this->workerSocialSecurityNumber = '12345678901';
    $this->period = CarbonPeriodImmutable::create('2025-10-01', '2025-10-31');

    // Every declaration is accepted as soon as it is sent
    $this->sentPayloads = new Collection;

    Http::fake(function (Request $request) {
        if ($request->url() === config('dimona.oauth_endpoint')) {
            return Http::response(['access_token' => 'fake-token', 'expires_in' => 3600]);
        }

        if ($request->method() === 'POST') {
            $this->sentPayloads->push($request->data());

            return Http::response([], 201, ['Location' => 'declarations/declaration-ref-'.$this->sentPayloads->count()]);
        }

        return Http::response([
            'declarationStatus' => [
                'period' => ['id' => '100'.$this->sentPayloads->count()],
                'result' => 'A',
                'anomalies' => [],
            ],
        ]);
    });
});

function occasionalEmploymentsFor(array $days): Collection
{
    return collect($days)->map(
        fn (string $day) => EmploymentDataFactory::new()
            ->id("emp-{$day}")
            ->jointCommissionNumber(302)
            ->workerType(WorkerType::Occasional)
            ->startsAt("{$day} 18:00")
            ->endsAt("{$day} 23:00")
            ->create()
    );
}

/**
 * Run the job, and the jobs it dispatches, until there is nothing left to do.
 */
function runSyncUntilDone(Collection $employments): void
{
    for ($loop = 0; $loop < 10; $loop++) {
        $pushedBefore = Queue::pushed(SyncDimonaPeriodsJob::class)->count();

        (new SyncDimonaPeriodsJob(
            test()->employerEnterpriseNumber,
            test()->workerSocialSecurityNumber,
            test()->period,
            $employments,
        ))->handle();

        if (Queue::pushed(SyncDimonaPeriodsJob::class)->count() === $pushedBefore) {
            return;
        }
    }

    throw new RuntimeException('The sync did not settle.');
}

it('declares occasional periods as EXT with hours', function () {
    runSyncUntilDone(occasionalEmploymentsFor(['2025-10-01', '2025-10-02']));

    expect($this->sentPayloads)->toHaveCount(2)
        ->and($this->sentPayloads->pluck('dimonaIn.features.workerType')->all())->toBe(['EXT', 'EXT'])
        ->and($this->sentPayloads[0]['dimonaIn'])->toMatchArray([
            'startDate' => '2025-10-01',
            'startHour' => '1800',
            'endDate' => '2025-10-01',
            'endHour' => '2300',
        ])
        ->and(DimonaPeriod::query()->pluck('state')->unique()->all())->toBe([DimonaPeriodState::Accepted]);
});

it('cancels the EXT periods and declares a single OTH when a third consecutive day is added', function () {
    runSyncUntilDone(occasionalEmploymentsFor(['2025-10-01', '2025-10-02']));
    $this->sentPayloads = new Collection;

    runSyncUntilDone(occasionalEmploymentsFor(['2025-10-01', '2025-10-02', '2025-10-03']));

    expect($this->sentPayloads)->toHaveCount(3)
        ->and($this->sentPayloads[0])->toHaveKey('dimonaCancel')
        ->and($this->sentPayloads[1])->toHaveKey('dimonaCancel')
        ->and($this->sentPayloads[2]['dimonaIn'])->toBe([
            'features' => [
                'jointCommissionNumber' => 'XXX',
                'workerType' => 'OTH',
            ],
            'startDate' => '2025-10-01',
            'endDate' => '2025-10-03',
        ]);

    $occasional = DimonaPeriod::query()->where('worker_type', WorkerType::Occasional)->get();
    $other = DimonaPeriod::query()->where('worker_type', WorkerType::Other)->sole();

    expect($occasional->pluck('state')->unique()->all())->toBe([DimonaPeriodState::Cancelled])
        ->and($other->state)->toBe(DimonaPeriodState::Accepted);
});

it('cancels the OTH period and declares EXT periods when the series shrinks to two days', function () {
    runSyncUntilDone(occasionalEmploymentsFor(['2025-10-01', '2025-10-02', '2025-10-03']));
    $this->sentPayloads = new Collection;

    runSyncUntilDone(occasionalEmploymentsFor(['2025-10-01', '2025-10-02']));

    expect($this->sentPayloads)->toHaveCount(3)
        ->and($this->sentPayloads[0])->toHaveKey('dimonaCancel')
        ->and($this->sentPayloads[1]['dimonaIn']['features']['workerType'])->toBe('EXT')
        ->and($this->sentPayloads[2]['dimonaIn']['features']['workerType'])->toBe('EXT')
        ->and(collect([$this->sentPayloads[1], $this->sentPayloads[2]])->pluck('dimonaIn.startDate')->sort()->values()->all())
        ->toBe(['2025-10-01', '2025-10-02']);

    $other = DimonaPeriod::query()->where('worker_type', WorkerType::Other)->sole();
    $occasional = DimonaPeriod::query()->where('worker_type', WorkerType::Occasional)->get();

    expect($other->state)->toBe(DimonaPeriodState::Cancelled)
        ->and($occasional)->toHaveCount(2)
        ->and($occasional->pluck('state')->unique()->all())->toBe([DimonaPeriodState::Accepted]);
});

it('updates the end date of the OTH period when a fourth consecutive day is added', function () {
    runSyncUntilDone(occasionalEmploymentsFor(['2025-10-01', '2025-10-02', '2025-10-03']));
    $this->sentPayloads = new Collection;

    runSyncUntilDone(occasionalEmploymentsFor(['2025-10-01', '2025-10-02', '2025-10-03', '2025-10-04']));

    $other = DimonaPeriod::query()->sole();

    expect($this->sentPayloads)->toHaveCount(1)
        ->and($this->sentPayloads[0]['dimonaUpdate'])->toBe([
            'periodId' => intval($other->reference),
            'startDate' => '2025-10-01',
            'endDate' => '2025-10-04',
        ])
        ->and($other->state)->toBe(DimonaPeriodState::Accepted)
        ->and($other->end_date)->toBe('2025-10-04');
});
