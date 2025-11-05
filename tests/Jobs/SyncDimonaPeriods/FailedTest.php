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
            EmploymentDataFactory::new()->create(),
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
                ->workerType(WorkerType::Flexi)
                ->jointCommissionNumber(304)
                ->endsAt(CarbonImmutable::parse('2025-10-01 12:00'))
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

        // Loop 2: Retry with corrected data (different end time)

        (new UniqueLock(app(Cache::class)))->release($job);

        $correctedEmployments = collect([
            EmploymentDataFactory::new()
                ->id('emp-1')
                ->workerType(WorkerType::Flexi)
                ->jointCommissionNumber(304)
                ->endsAt(CarbonImmutable::parse('2025-10-01 15:00'))
                ->create(),
        ]);

        $job = new SyncDimonaPeriodsJob(
            $this->employerEnterpriseNumber,
            $this->workerSocialSecurityNumber,
            $this->period,
            $correctedEmployments
        );

        $job->handle();

        $dimonaPeriod->refresh();
        $allPeriods = DimonaPeriod::query()->get();
        $pendingPeriod = $allPeriods->firstWhere('state', DimonaPeriodState::Pending);
        $newDeclaration = $pendingPeriod?->dimona_declarations()->firstWhere('reference', 'declaration-ref-1');

        // With corrected data, a new period may be created (failed period left in Failed state)
        // OR failed period may be reused depending on matching logic
        expect($allPeriods->count())->toBeGreaterThanOrEqual(1)
            ->and($pendingPeriod)->not->toBeNull()
            ->and($pendingPeriod->dimona_declarations()->count())->toBeGreaterThanOrEqual(1)
            ->and($newDeclaration)->not->toBeNull()
            ->and($newDeclaration->type)->toBe(DimonaDeclarationType::In)
            ->and($newDeclaration->state)->toBe(DimonaDeclarationState::Pending);

        Queue::assertPushed(SyncDimonaPeriodsJob::class, 2);

        // Loop 3: Sync successful declaration

        (new UniqueLock(app(Cache::class)))->release($job);

        $job->handle();

        $pendingPeriod->refresh();
        $newDeclaration->refresh();

        expect($pendingPeriod->state)->toBe(DimonaPeriodState::Accepted)
            ->and($newDeclaration->state)->toBe(DimonaDeclarationState::Accepted);

        Queue::assertPushed(SyncDimonaPeriodsJob::class, 2);
    });

    it('does not retry failed periods with same data (exact match)', function () {
        $employments = collect([
            EmploymentDataFactory::new()->create(),
        ]);

        Http::fake([
            config('dimona.oauth_endpoint') => Http::response([
                'access_token' => 'fake-token',
                'expires_in' => 3600,
            ]),
            '*/declarations' => Http::response(['error' => 'Error 1'], 400),
        ]);

        // Loop 1: First failure - creates a failed period

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

        // Loop 2: Second attempt with same data - should find exact match and not retry

        (new UniqueLock(app(Cache::class)))->release($job);

        $job = new SyncDimonaPeriodsJob(
            $this->employerEnterpriseNumber,
            $this->workerSocialSecurityNumber,
            $this->period,
            $employments
        );

        $job->handle();

        // Should still be the same period, no new period or declaration created
        // Job is not re-dispatched because no pending periods exist
        expect(DimonaPeriod::query()->count())->toBe(1)
            ->and($dimonaPeriod->fresh()->state)->toBe(DimonaPeriodState::Failed)
            ->and($dimonaPeriod->dimona_declarations()->count())->toBe(1)
            ->and(DimonaDeclaration::query()->where('state', DimonaDeclarationState::Failed)->count())->toBe(1);

        Queue::assertPushed(SyncDimonaPeriodsJob::class, 1);

        // Loop 3: Third attempt with same data - still no retry

        (new UniqueLock(app(Cache::class)))->release($job);

        $job = new SyncDimonaPeriodsJob(
            $this->employerEnterpriseNumber,
            $this->workerSocialSecurityNumber,
            $this->period,
            $employments
        );

        $job->handle();

        // Still the same period, no changes
        expect(DimonaPeriod::query()->count())->toBe(1)
            ->and($dimonaPeriod->fresh()->state)->toBe(DimonaPeriodState::Failed)
            ->and($dimonaPeriod->dimona_declarations()->count())->toBe(1)
            ->and(DimonaDeclaration::query()->where('state', DimonaDeclarationState::Failed)->count())->toBe(1);

        Queue::assertPushed(SyncDimonaPeriodsJob::class, 1);
    });
});
