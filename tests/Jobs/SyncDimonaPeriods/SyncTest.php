<?php

use Carbon\CarbonImmutable;
use Carbon\CarbonPeriodImmutable;
use Hyperlab\Dimona\Enums\DimonaDeclarationState;
use Hyperlab\Dimona\Enums\DimonaDeclarationType;
use Hyperlab\Dimona\Enums\DimonaPeriodState;
use Hyperlab\Dimona\Enums\WorkerType;
use Hyperlab\Dimona\Jobs\SyncDimonaPeriodsJob;
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

describe('Sync pending declarations', function () {
    it('syncs pending declarations and marks them as accepted', function () {
        Http::fake([
            config('dimona.oauth_endpoint') => Http::response([
                'access_token' => 'fake-token',
                'expires_in' => 3600,
            ]),
            '*/declarations' => Http::response([], 201, ['Location' => 'declarations/declaration-ref-1']),
            '*/declarations/declaration-ref-1' => Http::response([
                'declarationStatus' => [
                    'period' => ['id' => 'period-ref-1'],
                    'result' => 'A',
                    'anomalies' => [],
                ],
            ]),
        ]);

        $employmentId = 'emp-1';

        $employments = collect([
            EmploymentDataFactory::new()
                ->id($employmentId)
                ->startsAt(CarbonImmutable::parse('2025-10-01 07:00:00'))
                ->endsAt(CarbonImmutable::parse('2025-10-01 12:00:00'))
                ->workerType(WorkerType::Student)
                ->jointCommissionNumber(202)
                ->create(),
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
        $declaration = $dimonaPeriod->dimona_declarations()->firstWhere('reference', 'declaration-ref-1');

        expect($dimonaPeriod->state)->toBe(DimonaPeriodState::Pending)
            ->and($dimonaPeriod->dimona_declarations()->count())->toBe(1)
            ->and($declaration->type)->toBe(DimonaDeclarationType::In)
            ->and($declaration->state)->toBe(DimonaDeclarationState::Pending);

        Queue::assertPushed(SyncDimonaPeriodsJob::class, 1);

        // Loop 2: Sync pending declaration and mark as accepted

        (new UniqueLock(app(Cache::class)))->release($job);

        $job->handle(2);

        $dimonaPeriod->refresh();
        $declaration->refresh();

        expect($declaration->state)->toBe(DimonaDeclarationState::Accepted)
            ->and($dimonaPeriod->state)->toBe(DimonaPeriodState::Accepted);

        Queue::assertPushed(SyncDimonaPeriodsJob::class, 1);
    });

    it('re-dispatches when declarations are still pending after sync', function () {
        Http::fake([
            config('dimona.oauth_endpoint') => Http::response([
                'access_token' => 'fake-token',
                'expires_in' => 3600,
            ]),
            '*/declarations' => Http::response([], 201, ['Location' => 'declarations/declaration-ref-1']),
            '*/declarations/declaration-ref-1' => Http::sequence()
                ->push([], 404)  // Loop 2: still pending
                ->push([], 404)  // Loop 3: still pending
                ->push([         // Loop 4: accepted
                    'declarationStatus' => [
                        'period' => ['id' => 'period-ref-1'],
                        'result' => 'A',
                        'anomalies' => [],
                    ],
                ]),
        ]);

        $employmentId = 'emp-1';

        $employments = collect([
            EmploymentDataFactory::new()
                ->id($employmentId)
                ->startsAt(CarbonImmutable::parse('2025-10-01 07:00:00'))
                ->endsAt(CarbonImmutable::parse('2025-10-01 12:00:00'))
                ->workerType(WorkerType::Student)
                ->jointCommissionNumber(202)
                ->create(),
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
        $declaration = $dimonaPeriod->dimona_declarations()->firstWhere('reference', 'declaration-ref-1');

        expect($dimonaPeriod->state)->toBe(DimonaPeriodState::Pending)
            ->and($declaration->state)->toBe(DimonaDeclarationState::Pending);

        Queue::assertPushed(SyncDimonaPeriodsJob::class, 1);

        // Loop 2: Sync - still pending, redispatch

        (new UniqueLock(app(Cache::class)))->release($job);

        $job->handle();

        $declaration->refresh();

        expect($declaration->state)->toBe(DimonaDeclarationState::Pending);

        Queue::assertPushed(SyncDimonaPeriodsJob::class, 2);

        // Loop 3: Sync - still pending, redispatch

        (new UniqueLock(app(Cache::class)))->release($job);

        $job->handle();

        $declaration->refresh();

        expect($declaration->state)->toBe(DimonaDeclarationState::Pending);

        Queue::assertPushed(SyncDimonaPeriodsJob::class, 3);

        // Loop 4: Sync - now accepted, no redispatch

        (new UniqueLock(app(Cache::class)))->release($job);

        $job->handle();

        $declaration->refresh();
        $dimonaPeriod->refresh();

        expect($declaration->state)->toBe(DimonaDeclarationState::Accepted)
            ->and($dimonaPeriod->state)->toBe(DimonaPeriodState::Accepted);

        Queue::assertPushed(SyncDimonaPeriodsJob::class, 3);
    });

    it('handles service down exceptions gracefully', function () {
        Http::fake([
            config('dimona.oauth_endpoint') => Http::response([
                'access_token' => 'fake-token',
                'expires_in' => 3600,
            ]),
            '*/declarations' => Http::response([], 201, ['Location' => 'declarations/declaration-ref-1']),
            '*/declarations/declaration-ref-1' => Http::sequence()
                ->push([], 500)  // Loop 2: service down
                ->push([         // Loop 3: accepted
                    'declarationStatus' => [
                        'period' => ['id' => 'period-ref-1'],
                        'result' => 'A',
                        'anomalies' => [],
                    ],
                ]),
        ]);

        $employmentId = 'emp-1';

        $employments = collect([
            EmploymentDataFactory::new()
                ->id($employmentId)
                ->startsAt(CarbonImmutable::parse('2025-10-01 07:00:00'))
                ->endsAt(CarbonImmutable::parse('2025-10-01 12:00:00'))
                ->workerType(WorkerType::Student)
                ->jointCommissionNumber(202)
                ->create(),
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
        $declaration = $dimonaPeriod->dimona_declarations()->firstWhere('reference', 'declaration-ref-1');

        expect($dimonaPeriod->state)->toBe(DimonaPeriodState::Pending)
            ->and($dimonaPeriod->dimona_declarations()->count())->toBe(1)
            ->and($declaration->state)->toBe(DimonaDeclarationState::Pending);

        Queue::assertPushed(SyncDimonaPeriodsJob::class, 1);

        // Loop 2: Sync - service down, remains pending and redispatches

        (new UniqueLock(app(Cache::class)))->release($job);

        $job->handle();

        $declaration->refresh();

        expect($declaration->state)->toBe(DimonaDeclarationState::Pending);

        Queue::assertPushed(SyncDimonaPeriodsJob::class, 2);

        // Loop 3: Sync - now accepted

        (new UniqueLock(app(Cache::class)))->release($job);

        $job->handle();

        $declaration->refresh();
        $dimonaPeriod->refresh();

        expect($declaration->state)->toBe(DimonaDeclarationState::Accepted)
            ->and($dimonaPeriod->state)->toBe(DimonaPeriodState::Accepted);

        Queue::assertPushed(SyncDimonaPeriodsJob::class, 2);
    });

    it('marks declarations as failed on invalid API request', function () {
        Http::fake([
            config('dimona.oauth_endpoint') => Http::response([
                'access_token' => 'fake-token',
                'expires_in' => 3600,
            ]),
            '*/declarations' => Http::response([], 201, ['Location' => 'declarations/declaration-ref-1']),
            '*/declarations/declaration-ref-1' => Http::response(['error' => 'invalid request'], 400),
        ]);

        $employmentId = 'emp-1';

        $employments = collect([
            EmploymentDataFactory::new()
                ->id($employmentId)
                ->startsAt(CarbonImmutable::parse('2025-10-01 07:00:00'))
                ->endsAt(CarbonImmutable::parse('2025-10-01 12:00:00'))
                ->workerType(WorkerType::Student)
                ->jointCommissionNumber(202)
                ->create(),
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
        $declaration = $dimonaPeriod->dimona_declarations()->firstWhere('reference', 'declaration-ref-1');

        expect($dimonaPeriod->state)->toBe(DimonaPeriodState::Pending)
            ->and($dimonaPeriod->dimona_declarations()->count())->toBe(1)
            ->and($declaration->state)->toBe(DimonaDeclarationState::Pending);

        Queue::assertPushed(SyncDimonaPeriodsJob::class, 1);

        // Loop 2: Sync - invalid request, marked as failed

        (new UniqueLock(app(Cache::class)))->release($job);

        $job->handle();

        $declaration->refresh();
        $dimonaPeriod->refresh();

        expect($declaration->state)->toBe(DimonaDeclarationState::Failed)
            ->and($declaration->anomalies)->toBe(['error' => 'invalid request'])
            ->and($dimonaPeriod->state)->toBe(DimonaPeriodState::Failed);

        Queue::assertPushed(SyncDimonaPeriodsJob::class, 1);
    });

    it('handles refused declarations correctly', function () {
        Http::fake([
            config('dimona.oauth_endpoint') => Http::response([
                'access_token' => 'fake-token',
                'expires_in' => 3600,
            ]),
            '*/declarations' => Http::response([], 201, ['Location' => 'declarations/declaration-ref-1']),
            '*/declarations/declaration-ref-1' => Http::response([
                'declarationStatus' => [
                    'period' => ['id' => 'period-ref-1'],
                    'result' => 'B',
                    'anomalies' => [
                        ['code' => 'E001', 'description' => 'Refused for some reason'],
                    ],
                ],
            ]),
        ]);

        $employmentId = 'emp-1';

        $employments = collect([
            EmploymentDataFactory::new()
                ->id($employmentId)
                ->startsAt(CarbonImmutable::parse('2025-10-01 07:00:00'))
                ->endsAt(CarbonImmutable::parse('2025-10-01 12:00:00'))
                ->workerType(WorkerType::Student)
                ->jointCommissionNumber(202)
                ->create(),
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
        $declaration = $dimonaPeriod->dimona_declarations()->firstWhere('reference', 'declaration-ref-1');

        expect($dimonaPeriod->state)->toBe(DimonaPeriodState::Pending)
            ->and($dimonaPeriod->dimona_declarations()->count())->toBe(1)
            ->and($declaration->state)->toBe(DimonaDeclarationState::Pending);

        Queue::assertPushed(SyncDimonaPeriodsJob::class, 1);

        // Loop 2: Sync - refused

        (new UniqueLock(app(Cache::class)))->release($job);

        $job->handle();

        $declaration->refresh();
        $dimonaPeriod->refresh();

        expect($declaration->state)->toBe(DimonaDeclarationState::Refused)
            ->and($declaration->anomalies)->toHaveCount(1)
            ->and($dimonaPeriod->state)->toBe(DimonaPeriodState::Refused);

        Queue::assertPushed(SyncDimonaPeriodsJob::class, 1);
    });

    it('handles waiting state correctly', function () {
        Http::fake([
            config('dimona.oauth_endpoint') => Http::response([
                'access_token' => 'fake-token',
                'expires_in' => 3600,
            ]),
            '*/declarations' => Http::response([], 201, ['Location' => 'declarations/declaration-ref-1']),
            '*/declarations/declaration-ref-1' => Http::sequence()
                ->push([         // Loop 2: waiting
                    'declarationStatus' => [
                        'period' => ['id' => 'period-ref-1'],
                        'result' => 'S',
                        'anomalies' => [],
                    ],
                ])
                ->push([         // Loop 3: still waiting
                    'declarationStatus' => [
                        'period' => ['id' => 'period-ref-1'],
                        'result' => 'S',
                        'anomalies' => [],
                    ],
                ])
                ->push([         // Loop 4: accepted
                    'declarationStatus' => [
                        'period' => ['id' => 'period-ref-1'],
                        'result' => 'A',
                        'anomalies' => [],
                    ],
                ]),
        ]);

        $employmentId = 'emp-1';

        $employments = collect([
            EmploymentDataFactory::new()
                ->id($employmentId)
                ->startsAt(CarbonImmutable::parse('2025-10-01 07:00:00'))
                ->endsAt(CarbonImmutable::parse('2025-10-01 12:00:00'))
                ->workerType(WorkerType::Student)
                ->jointCommissionNumber(202)
                ->create(),
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
        $declaration = $dimonaPeriod->dimona_declarations()->firstWhere('reference', 'declaration-ref-1');

        expect($dimonaPeriod->state)->toBe(DimonaPeriodState::Pending)
            ->and($dimonaPeriod->dimona_declarations()->count())->toBe(1)
            ->and($declaration->state)->toBe(DimonaDeclarationState::Pending);

        Queue::assertPushed(SyncDimonaPeriodsJob::class, 1);

        // Loop 2: Sync - waiting state

        (new UniqueLock(app(Cache::class)))->release($job);

        $job->handle();

        $declaration->refresh();
        $dimonaPeriod->refresh();

        expect($declaration->state)->toBe(DimonaDeclarationState::Waiting)
            ->and($dimonaPeriod->state)->toBe(DimonaPeriodState::Waiting);

        Queue::assertPushed(SyncDimonaPeriodsJob::class, 2);

        // Loop 3: Sync - still waiting, continue to redispatch

        (new UniqueLock(app(Cache::class)))->release($job);

        $job->handle();

        $declaration->refresh();
        $dimonaPeriod->refresh();

        expect($declaration->state)->toBe(DimonaDeclarationState::Waiting)
            ->and($dimonaPeriod->state)->toBe(DimonaPeriodState::Waiting);

        Queue::assertPushed(SyncDimonaPeriodsJob::class, 3);

        // Loop 4: Sync - now accepted

        (new UniqueLock(app(Cache::class)))->release($job);

        $job->handle();

        $declaration->refresh();
        $dimonaPeriod->refresh();

        expect($declaration->state)->toBe(DimonaDeclarationState::Accepted)
            ->and($dimonaPeriod->state)->toBe(DimonaPeriodState::Accepted);

        Queue::assertPushed(SyncDimonaPeriodsJob::class, 3);
    });

    it('handles waiting to refused transition', function () {
        Http::fake([
            config('dimona.oauth_endpoint') => Http::response([
                'access_token' => 'fake-token',
                'expires_in' => 3600,
            ]),
            '*/declarations' => Http::response([], 201, ['Location' => 'declarations/declaration-ref-1']),
            '*/declarations/declaration-ref-1' => Http::sequence()
                ->push([         // Loop 2: waiting
                    'declarationStatus' => [
                        'period' => ['id' => 'period-ref-1'],
                        'result' => 'S',
                        'anomalies' => [],
                    ],
                ])
                ->push([         // Loop 3: refused
                    'declarationStatus' => [
                        'period' => ['id' => 'period-ref-1'],
                        'result' => 'B',
                        'anomalies' => [
                            ['code' => 'E002', 'description' => 'Refused after waiting'],
                        ],
                    ],
                ]),
        ]);

        $employmentId = 'emp-1';

        $employments = collect([
            EmploymentDataFactory::new()
                ->id($employmentId)
                ->startsAt(CarbonImmutable::parse('2025-10-01 07:00:00'))
                ->endsAt(CarbonImmutable::parse('2025-10-01 12:00:00'))
                ->workerType(WorkerType::Student)
                ->jointCommissionNumber(202)
                ->create(),
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
        $declaration = $dimonaPeriod->dimona_declarations()->firstWhere('reference', 'declaration-ref-1');

        expect($dimonaPeriod->state)->toBe(DimonaPeriodState::Pending)
            ->and($dimonaPeriod->dimona_declarations()->count())->toBe(1)
            ->and($declaration->state)->toBe(DimonaDeclarationState::Pending);

        Queue::assertPushed(SyncDimonaPeriodsJob::class, 1);

        // Loop 2: Sync - waiting state

        (new UniqueLock(app(Cache::class)))->release($job);

        $job->handle();

        $declaration->refresh();
        $dimonaPeriod->refresh();

        expect($declaration->state)->toBe(DimonaDeclarationState::Waiting)
            ->and($dimonaPeriod->state)->toBe(DimonaPeriodState::Waiting);

        Queue::assertPushed(SyncDimonaPeriodsJob::class, 2);

        // Loop 3: Sync - refused

        (new UniqueLock(app(Cache::class)))->release($job);

        $job->handle();

        $declaration->refresh();
        $dimonaPeriod->refresh();

        expect($declaration->state)->toBe(DimonaDeclarationState::Refused)
            ->and($declaration->anomalies)->toHaveCount(1)
            ->and($dimonaPeriod->state)->toBe(DimonaPeriodState::Refused);

        Queue::assertPushed(SyncDimonaPeriodsJob::class, 2);
    });

    it('stops re-dispatching when all work is complete', function () {
        Http::fake([
            config('dimona.oauth_endpoint') => Http::response([
                'access_token' => 'fake-token',
                'expires_in' => 3600,
            ]),
            '*/declarations' => Http::response([], 201, ['Location' => 'declarations/declaration-ref-1']),
            '*/declarations/declaration-ref-1' => Http::sequence()
                ->push([], 404)  // Loop 2: sync after create - still pending
                ->push([         // Loop 3: accepted
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

        $job = new SyncDimonaPeriodsJob(
            $this->employerEnterpriseNumber,
            $this->workerSocialSecurityNumber,
            $this->period,
            $employments
        );

        // Loop 1: Create declaration

        $job->handle();

        $dimonaPeriod = DimonaPeriod::query()->first();
        $declaration = $dimonaPeriod->dimona_declarations()->firstWhere('reference', 'declaration-ref-1');

        expect($dimonaPeriod->state)->toBe(DimonaPeriodState::Pending)
            ->and($dimonaPeriod->dimona_declarations()->count())->toBe(1)
            ->and($declaration->state)->toBe(DimonaDeclarationState::Pending);

        Queue::assertPushed(SyncDimonaPeriodsJob::class, 1);

        // Loop 2: Sync pending declaration - still pending

        (new UniqueLock(app(Cache::class)))->release($job);

        $job->handle();

        $declaration->refresh();

        expect($declaration->state)->toBe(DimonaDeclarationState::Pending);

        Queue::assertPushed(SyncDimonaPeriodsJob::class, 2);

        // Loop 3: Declaration is now accepted

        (new UniqueLock(app(Cache::class)))->release($job);

        $job->handle();

        $declaration->refresh();
        $dimonaPeriod->refresh();

        expect($declaration->state)->toBe(DimonaDeclarationState::Accepted)
            ->and($dimonaPeriod->state)->toBe(DimonaPeriodState::Accepted);

        Queue::assertPushed(SyncDimonaPeriodsJob::class, 2);

        // Loop 4: Already accepted, no pending work - should not re-dispatch

        (new UniqueLock(app(Cache::class)))->release($job);

        $job->handle();

        $dimonaPeriod->refresh();

        expect($dimonaPeriod->state)->toBe(DimonaPeriodState::Accepted);

        Queue::assertPushed(SyncDimonaPeriodsJob::class, 2);
    });
});
