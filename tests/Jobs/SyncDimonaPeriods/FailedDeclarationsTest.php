<?php

use Carbon\CarbonImmutable;
use Carbon\CarbonPeriodImmutable;
use Hyperlab\Dimona\Enums\DimonaDeclarationState;
use Hyperlab\Dimona\Enums\DimonaDeclarationType;
use Hyperlab\Dimona\Enums\DimonaPeriodState;
use Hyperlab\Dimona\Enums\WorkerType;
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

describe('Failed declaration handling', function () {
    it('handles failed create declaration and period remains in failed state', function () {
        Http::fake([
            config('dimona.oauth_endpoint') => Http::response([
                'access_token' => 'fake-token',
                'expires_in' => 3600,
            ]),
            '*/declarations' => Http::response(['error' => 'Invalid data'], 400),
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

        $job->handle();

        $dimonaPeriod = DimonaPeriod::query()->first();
        $declaration = $dimonaPeriod->dimona_declarations()->first();

        expect($dimonaPeriod->state)->toBe(DimonaPeriodState::Failed)
            ->and($dimonaPeriod->dimona_declarations()->count())->toBe(1)
            ->and($declaration)->not->toBeNull()
            ->and($declaration->state)->toBe(DimonaDeclarationState::Failed);

        Queue::assertPushed(SyncDimonaPeriodsJob::class, 1);
    });

    it('handles failed update declaration and period remains in previous state', function () {
        Http::fake([
            config('dimona.oauth_endpoint') => Http::response([
                'access_token' => 'fake-token',
                'expires_in' => 3600,
            ]),
            '*/declarations' => Http::sequence()
                ->push([], 201, ['Location' => 'declarations/declaration-ref-1'])
                ->push(['error' => 'Update failed'], 400),
            '*/declarations/declaration-ref-1' => Http::response([
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
        $declaration1 = $dimonaPeriod->dimona_declarations()->firstWhere('reference', 'declaration-ref-1');

        expect($dimonaPeriod->state)->toBe(DimonaPeriodState::Pending)
            ->and($dimonaPeriod->dimona_declarations()->count())->toBe(1)
            ->and($declaration1->type)->toBe(DimonaDeclarationType::In)
            ->and($declaration1->state)->toBe(DimonaDeclarationState::Pending);

        Queue::assertPushed(SyncDimonaPeriodsJob::class, 1);

        // Loop 2: Declaration accepted

        (new UniqueLock(app(Cache::class)))->release($job);

        $job->handle();

        $dimonaPeriod->refresh();
        $declaration1->refresh();

        expect($dimonaPeriod->state)->toBe(DimonaPeriodState::Accepted)
            ->and($dimonaPeriod->dimona_declarations()->count())->toBe(1)
            ->and($declaration1->state)->toBe(DimonaDeclarationState::Accepted);

        Queue::assertPushed(SyncDimonaPeriodsJob::class, 1);

        // Loop 3: Trigger update which fails

        (new UniqueLock(app(Cache::class)))->release($job);

        $updatedEmployments = collect([
            EmploymentDataFactory::new()
                ->id('emp-1')
                ->startsAt(CarbonImmutable::parse('2025-10-01 07:00'))
                ->endsAt(CarbonImmutable::parse('2025-10-01 15:00'))
                ->workerType(WorkerType::Student)
                ->jointCommissionNumber(202)
                ->create(),
        ]);

        $job = new SyncDimonaPeriodsJob(
            $this->employerEnterpriseNumber,
            $this->workerSocialSecurityNumber,
            $this->period,
            $updatedEmployments
        );

        $job->handle();

        $dimonaPeriod->refresh();
        $updateDeclaration = $dimonaPeriod->dimona_declarations()->where('type', DimonaDeclarationType::Update)->first();

        expect($dimonaPeriod->state)->toBe(DimonaPeriodState::Accepted)
            ->and($dimonaPeriod->dimona_declarations()->count())->toBe(2)
            ->and($updateDeclaration)->not->toBeNull()
            ->and($updateDeclaration->type)->toBe(DimonaDeclarationType::Update)
            ->and($updateDeclaration->state)->toBe(DimonaDeclarationState::Failed);

        Queue::assertPushed(SyncDimonaPeriodsJob::class, 2);
    });

    it('allows retry after failed create with corrected data', function () {
        Http::fake([
            config('dimona.oauth_endpoint') => Http::response([
                'access_token' => 'fake-token',
                'expires_in' => 3600,
            ]),
            '*/declarations' => Http::sequence()
                ->push(['error' => 'Invalid data'], 400)
                ->push([], 201, ['Location' => 'declarations/declaration-ref-1']),
            '*/declarations/declaration-ref-1' => Http::response([
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

        // Loop 1: First attempt fails

        $job->handle();

        $dimonaPeriod = DimonaPeriod::query()->first();
        $failedDeclaration = $dimonaPeriod->dimona_declarations()->first();

        expect($dimonaPeriod->state)->toBe(DimonaPeriodState::Failed)
            ->and($dimonaPeriod->dimona_declarations()->count())->toBe(1)
            ->and($failedDeclaration->state)->toBe(DimonaDeclarationState::Failed);

        Queue::assertPushed(SyncDimonaPeriodsJob::class, 1);

        // Loop 2: Retry with corrected data (simulated by running job again)

        (new UniqueLock(app(Cache::class)))->release($job);

        $job = new SyncDimonaPeriodsJob(
            $this->employerEnterpriseNumber,
            $this->workerSocialSecurityNumber,
            $this->period,
            $employments
        );

        $job->handle();

        $dimonaPeriod2 = DimonaPeriod::query()->latest('id')->first();
        $newDeclaration = $dimonaPeriod2->dimona_declarations()->firstWhere('reference', 'declaration-ref-1');

        expect($dimonaPeriod2->state)->toBe(DimonaPeriodState::Pending)
            ->and($dimonaPeriod2->dimona_declarations()->count())->toBe(1)
            ->and($newDeclaration)->not->toBeNull()
            ->and($newDeclaration->type)->toBe(DimonaDeclarationType::In)
            ->and($newDeclaration->state)->toBe(DimonaDeclarationState::Pending);

        Queue::assertPushed(SyncDimonaPeriodsJob::class, 2);

        // Loop 3: Sync successful declaration

        (new UniqueLock(app(Cache::class)))->release($job);

        $job->handle();

        $dimonaPeriod2->refresh();
        $newDeclaration->refresh();

        expect($dimonaPeriod2->state)->toBe(DimonaPeriodState::Accepted)
            ->and($dimonaPeriod2->dimona_declarations()->count())->toBe(1)
            ->and($newDeclaration->state)->toBe(DimonaDeclarationState::Accepted);

        Queue::assertPushed(SyncDimonaPeriodsJob::class, 2);
    });

    it('handles multiple consecutive failures', function () {
        Http::fake([
            config('dimona.oauth_endpoint') => Http::response([
                'access_token' => 'fake-token',
                'expires_in' => 3600,
            ]),
            '*/declarations' => Http::sequence()
                ->push(['error' => 'Error 1'], 400)
                ->push(['error' => 'Error 2'], 500)
                ->push(['error' => 'Error 3'], 422),
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

        // Loop 1: First failure

        $job = new SyncDimonaPeriodsJob(
            $this->employerEnterpriseNumber,
            $this->workerSocialSecurityNumber,
            $this->period,
            $employments
        );

        $job->handle();

        $dimonaPeriod = DimonaPeriod::query()->first();

        expect($dimonaPeriod->state)->toBe(DimonaPeriodState::Failed)
            ->and($dimonaPeriod->dimona_declarations()->count())->toBe(1)
            ->and(DimonaDeclaration::query()->where('state', DimonaDeclarationState::Failed)->count())->toBe(1);

        Queue::assertPushed(SyncDimonaPeriodsJob::class, 1);

        // Loop 2: Second failure

        (new UniqueLock(app(Cache::class)))->release($job);

        $job = new SyncDimonaPeriodsJob(
            $this->employerEnterpriseNumber,
            $this->workerSocialSecurityNumber,
            $this->period,
            $employments
        );

        $job->handle();

        $dimonaPeriod2 = DimonaPeriod::query()->latest('id')->first();

        expect($dimonaPeriod2->state)->toBe(DimonaPeriodState::Failed)
            ->and($dimonaPeriod2->dimona_declarations()->count())->toBe(1)
            ->and(DimonaDeclaration::query()->where('state', DimonaDeclarationState::Failed)->count())->toBe(2);

        Queue::assertPushed(SyncDimonaPeriodsJob::class, 2);

        // Loop 3: Third failure

        (new UniqueLock(app(Cache::class)))->release($job);

        $job = new SyncDimonaPeriodsJob(
            $this->employerEnterpriseNumber,
            $this->workerSocialSecurityNumber,
            $this->period,
            $employments
        );

        $job->handle();

        $dimonaPeriod3 = DimonaPeriod::query()->latest('id')->first();

        expect($dimonaPeriod3->state)->toBe(DimonaPeriodState::Failed)
            ->and($dimonaPeriod3->dimona_declarations()->count())->toBe(1)
            ->and(DimonaDeclaration::query()->where('state', DimonaDeclarationState::Failed)->count())->toBe(3);

        Queue::assertPushed(SyncDimonaPeriodsJob::class, 3);
    });
});
