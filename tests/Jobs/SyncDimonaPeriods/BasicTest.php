<?php

use Carbon\CarbonImmutable;
use Carbon\CarbonPeriodImmutable;
use Hyperlab\Dimona\Enums\DimonaDeclarationState;
use Hyperlab\Dimona\Enums\DimonaDeclarationType;
use Hyperlab\Dimona\Enums\WorkerType;
use Hyperlab\Dimona\Jobs\SyncDimonaPeriodsJob;
use Hyperlab\Dimona\Models\DimonaDeclaration;
use Hyperlab\Dimona\Models\DimonaPeriod;
use Hyperlab\Dimona\Tests\Factories\EmploymentDataFactory;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;

beforeEach(function () {
    Http::preventStrayRequests();
    Queue::fake();

    $this->employerEnterpriseNumber = '0123456789';
    $this->workerSocialSecurityNumber = '12345678901';
    $this->period = CarbonPeriodImmutable::create('2025-10-01', '2025-10-31');
});

it('can be instantiated', function () {
    $employments = collect([
        EmploymentDataFactory::new()->create(),
    ]);

    $job = new SyncDimonaPeriodsJob(
        $this->employerEnterpriseNumber,
        $this->workerSocialSecurityNumber,
        $this->period,
        $employments
    );

    expect($job)->toBeInstanceOf(SyncDimonaPeriodsJob::class)
        ->and($job->employerEnterpriseNumber)->toBe($this->employerEnterpriseNumber)
        ->and($job->workerSocialSecurityNumber)->toBe($this->workerSocialSecurityNumber)
        ->and($job->clientId)->toBeNull();
});

it('can be instantiated with a client ID', function () {
    $employments = collect([
        EmploymentDataFactory::new()->create(),
    ]);

    $clientId = 'test-client';
    $job = new SyncDimonaPeriodsJob(
        $this->employerEnterpriseNumber,
        $this->workerSocialSecurityNumber,
        $this->period,
        $employments,
        $clientId
    );

    expect($job->clientId)->toBe($clientId);
});

it('returns unique ID based on employer and worker', function () {
    $employments = collect([
        EmploymentDataFactory::new()->create(),
    ]);

    $job = new SyncDimonaPeriodsJob(
        $this->employerEnterpriseNumber,
        $this->workerSocialSecurityNumber,
        $this->period,
        $employments
    );

    expect($job->uniqueId())->toBe("{$this->employerEnterpriseNumber}-{$this->workerSocialSecurityNumber}");
});

describe('Backoff calculation', function () {
    it('uses 1 second delay for recent pending declarations', function () {
        Http::fake([
            config('dimona.oauth_endpoint') => Http::response([
                'access_token' => 'fake-token',
                'expires_in' => 3600,
            ]),
            '*/declarations/declaration-ref-1' => Http::response([], 404),
            '*/declarations' => Http::response([], 201, ['Location' => 'declarations/new-ref']),
        ]);

        $employmentId = 'emp-1';

        $dimonaPeriod = DimonaPeriod::factory()
            ->withEmployments([$employmentId])
            ->create();

        DimonaDeclaration::factory()->create([
            'dimona_period_id' => $dimonaPeriod->id,
            'created_at' => now()->subSeconds(10),
        ]);

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

        $job->handle();

        Queue::assertPushed(SyncDimonaPeriodsJob::class, function ($job) {
            return $job->delay === 1;
        });
    });

    it('uses 60 second delay for moderately old pending declarations', function () {
        Http::fake([
            config('dimona.oauth_endpoint') => Http::response([
                'access_token' => 'fake-token',
                'expires_in' => 3600,
            ]),
            '*/declarations/declaration-ref-1' => Http::response([], 404),
            '*/declarations' => Http::response([], 201, ['Location' => 'declarations/new-ref']),
        ]);

        $employmentId = 'emp-1';

        $dimonaPeriod = DimonaPeriod::factory()
            ->withEmployments([$employmentId])
            ->create();

        DimonaDeclaration::query()->create([
            'dimona_period_id' => $dimonaPeriod->id,
            'type' => DimonaDeclarationType::In,
            'state' => DimonaDeclarationState::Pending,
            'payload' => [],
            'reference' => 'declaration-ref-1',
            'created_at' => now()->subSeconds(100),
        ]);

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

        $job->handle();

        Queue::assertPushed(SyncDimonaPeriodsJob::class, function ($job) {
            return $job->delay === 60;
        });
    });

    it('uses 3600 second delay for very old pending declarations', function () {
        Http::fake([
            config('dimona.oauth_endpoint') => Http::response([
                'access_token' => 'fake-token',
                'expires_in' => 3600,
            ]),
            '*/declarations/declaration-ref-1' => Http::response([], 404),
            '*/declarations' => Http::response([], 201, ['Location' => 'declarations/new-ref']),
        ]);

        $employmentId = 'emp-1';

        $dimonaPeriod = DimonaPeriod::factory()
            ->withEmployments([$employmentId])
            ->create();

        DimonaDeclaration::query()->create([
            'dimona_period_id' => $dimonaPeriod->id,
            'type' => DimonaDeclarationType::In,
            'state' => DimonaDeclarationState::Pending,
            'payload' => [],
            'reference' => 'declaration-ref-1',
            'created_at' => now()->subSeconds(2000),
        ]);

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

        $job->handle();

        Queue::assertPushed(SyncDimonaPeriodsJob::class, function ($job) {
            return $job->delay === 3600;
        });
    });

    it('uses default 5 second delay for new operations without pending declarations', function () {
        Http::fake([
            config('dimona.oauth_endpoint') => Http::response([
                'access_token' => 'fake-token',
                'expires_in' => 3600,
            ]),
            '*/declarations' => Http::response([], 201, ['Location' => 'declarations/declaration-ref-1']),
        ]);

        $employments = collect([
            EmploymentDataFactory::new()
                ->startsAt(CarbonImmutable::parse('2025-10-01 07:00'))
                ->endsAt(CarbonImmutable::parse('2025-10-01 12:00'))
                ->create(),
        ]);

        $job = new SyncDimonaPeriodsJob(
            $this->employerEnterpriseNumber,
            $this->workerSocialSecurityNumber,
            $this->period,
            $employments
        );

        $job->handle();

        Queue::assertPushed(SyncDimonaPeriodsJob::class, function ($job) {
            return $job->delay === 1; // Recent pending declaration gets 1s delay
        });
    });
});
