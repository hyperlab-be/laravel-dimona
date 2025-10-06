<?php

use Carbon\CarbonImmutable;
use Carbon\CarbonPeriodImmutable;
use Hyperlab\Dimona\Enums\DimonaDeclarationState;
use Hyperlab\Dimona\Enums\DimonaDeclarationType;
use Hyperlab\Dimona\Enums\DimonaPeriodState;
use Hyperlab\Dimona\Enums\WorkerType;
use Hyperlab\Dimona\Jobs\SyncDimonaPeriods;
use Hyperlab\Dimona\Models\DimonaPeriod;
use Hyperlab\Dimona\Tests\Factories\EmploymentDataFactory;
use Illuminate\Bus\UniqueLock;
use Illuminate\Contracts\Cache\Repository as Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;

beforeEach(function () {
    Http::preventStrayRequests();
    Queue::fake();

    $this->employerEnterpriseNumber = '0123456789';
    $this->workerSocialSecurityNumber = '12345678901';
    $this->period = CarbonPeriodImmutable::create('2025-10-01', '2025-10-31');
});

it('cancels a dimona period when employment is removed', function () {
    Http::fake([
        config('dimona.oauth_endpoint') => Http::response([
            'access_token' => 'fake-token',
            'expires_in' => 3600,
        ]),
        '*/declarations' => Http::sequence()
            ->push([], 201, ['Location' => 'declarations/declaration-ref-1'])
            ->push([], 201, ['Location' => 'declarations/cancel-declaration-ref']),
        '*/declarations/declaration-ref-1' => Http::sequence()
            ->push([], 404)  // Loop 2: still pending
            ->push([         // Loop 3: accepted
                'declarationStatus' => [
                    'period' => ['id' => 'period-ref-1'],
                    'result' => 'A',
                    'anomalies' => [],
                ],
            ]),
        '*/declarations/cancel-declaration-ref' => Http::sequence()
            ->push([], 404)  // Loop 5: cancel still pending
            ->push([         // Loop 6: cancel accepted
                'declarationStatus' => [
                    'period' => ['id' => 'period-ref-1'],
                    'result' => 'A',
                    'anomalies' => [],
                ],
            ]),
    ]);

    $employments = collect([
        EmploymentDataFactory::new()
            ->id('emp-1')
            ->startsAt(CarbonImmutable::parse('2025-10-01 07:00'))
            ->endsAt(CarbonImmutable::parse('2025-10-01 12:00'))
            ->workerType(WorkerType::Student)
            ->jointCommissionNumber(202)
            ->create(),
    ]);

    $job = new SyncDimonaPeriods(
        $this->employerEnterpriseNumber,
        $this->workerSocialSecurityNumber,
        $this->period,
        $employments
    );

    // Loop 1: Create initial declaration

    $job->handle();

    $dimonaPeriod = DimonaPeriod::query()->first();
    $declaration1 = $dimonaPeriod->dimona_declarations()->firstWhere('reference', 'declaration-ref-1');

    expect($dimonaPeriod->state)->toBe(DimonaPeriodState::Pending)
        ->and($dimonaPeriod->dimona_declarations()->count())->toBe(1)
        ->and($declaration1->type)->toBe(DimonaDeclarationType::In)
        ->and($declaration1->state)->toBe(DimonaDeclarationState::Pending);

    Queue::assertPushed(SyncDimonaPeriods::class, 1);

    // Loop 2: Sync pending declaration - still pending

    (new UniqueLock(app(Cache::class)))->release($job);

    $job->handle();

    $dimonaPeriod->refresh();
    $declaration1->refresh();

    expect($dimonaPeriod->state)->toBe(DimonaPeriodState::Pending)
        ->and($dimonaPeriod->dimona_declarations()->count())->toBe(1)
        ->and($declaration1->state)->toBe(DimonaDeclarationState::Pending);

    Queue::assertPushed(SyncDimonaPeriods::class, 2);

    // Loop 3: Declaration is now accepted

    (new UniqueLock(app(Cache::class)))->release($job);

    $job->handle();

    $dimonaPeriod->refresh();
    $declaration1->refresh();

    expect($dimonaPeriod->state)->toBe(DimonaPeriodState::Accepted)
        ->and($dimonaPeriod->dimona_declarations()->count())->toBe(1)
        ->and($declaration1->state)->toBe(DimonaDeclarationState::Accepted);

    Queue::assertPushed(SyncDimonaPeriods::class, 2);

    // Loop 4: Employment removed, triggering cancel

    (new UniqueLock(app(Cache::class)))->release($job);

    $job = new SyncDimonaPeriods(
        $this->employerEnterpriseNumber,
        $this->workerSocialSecurityNumber,
        $this->period,
        collect([])
    );

    $job->handle();

    $dimonaPeriod->refresh();
    $declaration2 = $dimonaPeriod->dimona_declarations()->firstWhere('reference', 'cancel-declaration-ref');

    expect($dimonaPeriod->state)->toBe(DimonaPeriodState::Pending)
        ->and($dimonaPeriod->dimona_declarations()->count())->toBe(2)
        ->and($declaration2->type)->toBe(DimonaDeclarationType::Cancel)
        ->and($declaration2->state)->toBe(DimonaDeclarationState::Pending);

    Queue::assertPushed(SyncDimonaPeriods::class, 3);

    // Loop 5: Sync cancel declaration - still pending

    (new UniqueLock(app(Cache::class)))->release($job);

    $job->handle();

    $dimonaPeriod->refresh();
    $declaration2->refresh();

    expect($dimonaPeriod->state)->toBe(DimonaPeriodState::Pending)
        ->and($dimonaPeriod->dimona_declarations()->count())->toBe(2)
        ->and($declaration2->state)->toBe(DimonaDeclarationState::Pending);

    Queue::assertPushed(SyncDimonaPeriods::class, 4);

    // Loop 6: Cancel declaration is accepted

    (new UniqueLock(app(Cache::class)))->release($job);

    $job->handle();

    $dimonaPeriod->refresh();
    $declaration2->refresh();

    expect($dimonaPeriod->state)->toBe(DimonaPeriodState::Cancelled)
        ->and($dimonaPeriod->dimona_declarations()->count())->toBe(2)
        ->and($declaration2->state)->toBe(DimonaDeclarationState::Accepted);

    Queue::assertPushed(SyncDimonaPeriods::class, 4);
});
