<?php

use Carbon\CarbonImmutable;
use Carbon\CarbonPeriodImmutable;
use Hyperlab\Dimona\Enums\DimonaDeclarationState;
use Hyperlab\Dimona\Enums\DimonaDeclarationType;
use Hyperlab\Dimona\Enums\DimonaPeriodState;
use Hyperlab\Dimona\Jobs\SyncDimonaPeriodsJob;
use Hyperlab\Dimona\Models\DimonaDeclaration;
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

it('creates a new dimona period when no periods exist', function () {
    Http::fake([
        config('dimona.oauth_endpoint') => Http::response([
            'access_token' => 'fake-token',
            'expires_in' => 3600,
        ]),
        '*/declarations' => Http::response([], 201, ['Location' => 'declarations/new-declaration-ref']),
        '*/declarations/new-declaration-ref' => Http::response([
            'declarationStatus' => [
                'period' => ['id' => 'period-ref-1'],
                'result' => 'A',
                'anomalies' => [],
            ],
        ]),
    ]);

    $employments = collect([
        EmploymentDataFactory::new()->create(),
    ]);

    $job = new SyncDimonaPeriodsJob(
        $this->employerEnterpriseNumber,
        $this->workerSocialSecurityNumber,
        $this->period,
        $employments
    );

    // Loop 1: Create declaration

    $job->handle();

    $dimonaPeriod = DimonaPeriod::query()->first();
    $declaration = $dimonaPeriod->dimona_declarations()->firstWhere('reference', 'new-declaration-ref');

    expect($dimonaPeriod)->not->toBeNull()
        ->and($dimonaPeriod->employer_enterprise_number)->toBe($this->employerEnterpriseNumber)
        ->and($dimonaPeriod->worker_social_security_number)->toBe($this->workerSocialSecurityNumber)
        ->and($dimonaPeriod->state)->toBe(DimonaPeriodState::Pending)
        ->and($dimonaPeriod->dimona_declarations()->count())->toBe(1)
        ->and($declaration)->not->toBeNull()
        ->and($declaration->type)->toBe(DimonaDeclarationType::In)
        ->and($declaration->state)->toBe(DimonaDeclarationState::Pending)
        ->and($declaration->reference)->toBe('new-declaration-ref');

    Queue::assertPushed(SyncDimonaPeriodsJob::class, 1);

    // Loop 2: Sync pending declaration - accepted

    (new UniqueLock(app(Cache::class)))->release($job);

    $job->handle();

    $dimonaPeriod->refresh();
    $declaration->refresh();

    expect($declaration->state)->toBe(DimonaDeclarationState::Accepted)
        ->and($dimonaPeriod->state)->toBe(DimonaPeriodState::Accepted);

    Queue::assertPushed(SyncDimonaPeriodsJob::class, 1);
});

it('creates multiple dimona periods for different time slots', function () {
    Http::fake([
        config('dimona.oauth_endpoint') => Http::response([
            'access_token' => 'fake-token',
            'expires_in' => 3600,
        ]),
        '*/declarations' => Http::sequence()
            ->push([], 201, ['Location' => 'declarations/declaration-ref-1'])
            ->push([], 201, ['Location' => 'declarations/declaration-ref-2']),
        '*/declarations/declaration-ref-1' => Http::sequence()
            ->push([], 404)  // Loop 2: still pending
            ->push([         // Loop 3: accepted
                'declarationStatus' => [
                    'period' => ['id' => 'period-ref-1'],
                    'result' => 'A',
                    'anomalies' => [],
                ],
            ]),
        '*/declarations/declaration-ref-2' => Http::sequence()
            ->push([], 404)  // Loop 2: still pending
            ->push([         // Loop 3: accepted
                'declarationStatus' => [
                    'period' => ['id' => 'period-ref-2'],
                    'result' => 'A',
                    'anomalies' => [],
                ],
            ]),
    ]);

    $employments = collect([
        EmploymentDataFactory::new()
            ->id('emp-1')
            ->create(),
        EmploymentDataFactory::new()
            ->id('emp-2')
            ->startsAt(CarbonImmutable::parse('2025-10-03 07:00'))
            ->create(),
    ]);

    $job = new SyncDimonaPeriodsJob(
        $this->employerEnterpriseNumber,
        $this->workerSocialSecurityNumber,
        $this->period,
        $employments
    );

    // Loop 1: Create two declarations

    $job->handle();

    expect(DimonaPeriod::count())->toBe(2)
        ->and(DimonaDeclaration::count())->toBe(2);

    Queue::assertPushed(SyncDimonaPeriodsJob::class, 1);

    // Loop 2: Sync both pending declarations - still pending

    (new UniqueLock(app(Cache::class)))->release($job);

    $job->handle();

    $declarations = DimonaDeclaration::query()->get();
    expect($declarations->where('reference', 'declaration-ref-1')->first()->state)->toBe(DimonaDeclarationState::Pending)
        ->and($declarations->where('reference', 'declaration-ref-2')->first()->state)->toBe(DimonaDeclarationState::Pending);

    Queue::assertPushed(SyncDimonaPeriodsJob::class, 2);

    // Loop 3: Both declarations accepted

    (new UniqueLock(app(Cache::class)))->release($job);

    $job->handle();

    expect(DimonaDeclaration::query()->where('state', DimonaDeclarationState::Accepted)->count())->toBe(2)
        ->and(DimonaPeriod::query()->where('state', DimonaPeriodState::Accepted)->count())->toBe(2);

    Queue::assertPushed(SyncDimonaPeriodsJob::class, 2);
});

it('handles 500 API exceptions when creating declarations', function () {
    Http::fake([
        config('dimona.oauth_endpoint') => Http::response([
            'access_token' => 'fake-token',
            'expires_in' => 3600,
        ]),
        '*/declarations' => Http::response(['error' => 'Internal server error'], 500),
    ]);

    $employments = collect([
        EmploymentDataFactory::new()->create(),
    ]);

    $job = new SyncDimonaPeriodsJob(
        $this->employerEnterpriseNumber,
        $this->workerSocialSecurityNumber,
        $this->period,
        $employments
    );

    $job->handle();

    $declaration = DimonaDeclaration::query()->first();
    expect($declaration)->not->toBeNull()
        ->and($declaration->state)->toBe(DimonaDeclarationState::Failed)
        ->and($declaration->anomalies)->toBe(['error' => 'Internal server error']);

    Queue::assertPushed(SyncDimonaPeriodsJob::class, 1);
});

it('handles 400 bad request when creating declarations', function () {
    Http::fake([
        config('dimona.oauth_endpoint') => Http::response([
            'access_token' => 'fake-token',
            'expires_in' => 3600,
        ]),
        '*/declarations' => Http::response(['error' => 'Bad request', 'field' => 'invalid'], 400),
    ]);

    $employments = collect([
        EmploymentDataFactory::new()->create(),
    ]);

    $job = new SyncDimonaPeriodsJob(
        $this->employerEnterpriseNumber,
        $this->workerSocialSecurityNumber,
        $this->period,
        $employments
    );

    $job->handle();

    $declaration = DimonaDeclaration::query()->first();
    expect($declaration)->not->toBeNull()
        ->and($declaration->state)->toBe(DimonaDeclarationState::Failed)
        ->and($declaration->anomalies)->toHaveKey('error');

    Queue::assertPushed(SyncDimonaPeriodsJob::class, 1);
});

it('handles 422 unprocessable entity when creating declarations', function () {
    Http::fake([
        config('dimona.oauth_endpoint') => Http::response([
            'access_token' => 'fake-token',
            'expires_in' => 3600,
        ]),
        '*/declarations' => Http::response([
            'message' => 'Validation failed',
            'errors' => ['field' => ['Field is required']],
        ], 422),
    ]);

    $employments = collect([
        EmploymentDataFactory::new()->create(),
    ]);

    $job = new SyncDimonaPeriodsJob(
        $this->employerEnterpriseNumber,
        $this->workerSocialSecurityNumber,
        $this->period,
        $employments
    );

    $job->handle();

    $declaration = DimonaDeclaration::query()->first();
    expect($declaration)->not->toBeNull()
        ->and($declaration->state)->toBe(DimonaDeclarationState::Failed)
        ->and($declaration->anomalies)->toHaveKey('message');

    Queue::assertPushed(SyncDimonaPeriodsJob::class, 1);
});

it('handles multiple pending declarations across different periods', function () {
    Http::fake([
        config('dimona.oauth_endpoint') => Http::response([
            'access_token' => 'fake-token',
            'expires_in' => 3600,
        ]),
        '*/declarations' => Http::sequence()
            ->push([], 201, ['Location' => 'declarations/declaration-ref-1'])
            ->push([], 201, ['Location' => 'declarations/declaration-ref-2']),
        '*/declarations/declaration-ref-1' => Http::sequence()
            ->push([], 404)  // Loop 2: sync decl-1 after create - pending
            ->push([], 404)  // Loop 3: sync decl-1 - still pending
            ->push([         // Loop 4: decl-1 accepted
                'declarationStatus' => [
                    'period' => ['id' => 'period-ref-1'],
                    'result' => 'A',
                    'anomalies' => [],
                ],
            ]),
        '*/declarations/declaration-ref-2' => Http::sequence()
            ->push([], 404)  // Loop 2: sync decl-2 after create - pending
            ->push([         // Loop 3: decl-2 accepted
                'declarationStatus' => [
                    'period' => ['id' => 'period-ref-2'],
                    'result' => 'A',
                    'anomalies' => [],
                ],
            ]),
    ]);

    $employments = collect([
        EmploymentDataFactory::new()
            ->id('emp-1')
            ->create(),
        EmploymentDataFactory::new()
            ->id('emp-2')
            ->startsAt(CarbonImmutable::parse('2025-10-05 07:00'))
            ->create(),
    ]);

    $job = new SyncDimonaPeriodsJob(
        $this->employerEnterpriseNumber,
        $this->workerSocialSecurityNumber,
        $this->period,
        $employments
    );

    // Loop 1: Create two declarations

    $job->handle();

    expect(DimonaPeriod::query()->count())->toBe(2)
        ->and(DimonaDeclaration::query()->count())->toBe(2);

    Queue::assertPushed(SyncDimonaPeriodsJob::class, 1);

    // Loop 2: Sync both declarations - still pending

    (new UniqueLock(app(Cache::class)))->release($job);

    $job->handle();

    $declarations = DimonaDeclaration::query()->get();
    expect($declarations->where('reference', 'declaration-ref-1')->first()->state)->toBe(DimonaDeclarationState::Pending)
        ->and($declarations->where('reference', 'declaration-ref-2')->first()->state)->toBe(DimonaDeclarationState::Pending);

    Queue::assertPushed(SyncDimonaPeriodsJob::class, 2);

    // Loop 3: First is still pending, second is accepted

    (new UniqueLock(app(Cache::class)))->release($job);

    $job->handle();

    $declarations = DimonaDeclaration::query()->get();
    expect($declarations->where('reference', 'declaration-ref-1')->first()->state)->toBe(DimonaDeclarationState::Pending)
        ->and($declarations->where('reference', 'declaration-ref-2')->first()->state)->toBe(DimonaDeclarationState::Accepted);

    Queue::assertPushed(SyncDimonaPeriodsJob::class, 3);

    // Loop 4: First is now accepted too

    (new UniqueLock(app(Cache::class)))->release($job);

    $job->handle();

    expect(DimonaDeclaration::query()->where('state', DimonaDeclarationState::Accepted)->count())->toBe(2);

    Queue::assertPushed(SyncDimonaPeriodsJob::class, 3);
});
