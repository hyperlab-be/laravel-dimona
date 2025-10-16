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

it('handles accepted with warning state with flexi anomaly', function () {
    Http::fake([
        config('dimona.oauth_endpoint') => Http::response([
            'access_token' => 'fake-token',
            'expires_in' => 3600,
        ]),
        '*/declarations' => Http::sequence()
            ->push([], 201, ['Location' => 'declarations/declaration-ref-1'])
            ->push([], 201, ['Location' => 'declarations/declaration-ref-2'])
            ->push([], 201, ['Location' => 'declarations/declaration-ref-3']),
        '*/declarations/declaration-ref-1' => Http::response([
            'declarationStatus' => [
                'period' => ['id' => 'period-ref-1'],
                'result' => 'W',
                'anomalies' => [
                    ['code' => '90017-510', 'description' => 'Toegangsvoorwaarden voor flexi-jobs niet gerespecteerd'],
                ],
            ],
        ]),
        '*/declarations/declaration-ref-2' => Http::sequence()
            ->push([], 404)  // Loop 3: cancel pending
            ->push([         // Loop 4: cancel accepted
                'declarationStatus' => [
                    'period' => ['id' => 'period-ref-1'],
                    'result' => 'A',
                    'anomalies' => [],
                ],
            ]),
        '*/declarations/declaration-ref-3' => Http::sequence()
            ->push([], 404)  // Loop 6: new create pending
            ->push([         // Loop 7: new create accepted
                'declarationStatus' => [
                    'period' => ['id' => 'period-ref-2'],
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
            ->workerType(WorkerType::Flexi)
            ->jointCommissionNumber(202)
            ->create(),
    ]);

    $job = new SyncDimonaPeriodsJob(
        $this->employerEnterpriseNumber,
        $this->workerSocialSecurityNumber,
        $this->period,
        $employments
    );

    // Loop 1: Create declaration with Flexi worker type

    $job->handle();

    $dimonaPeriod1 = DimonaPeriod::query()->latest('id')->first();
    $declaration1 = $dimonaPeriod1->dimona_declarations()->firstWhere('reference', 'declaration-ref-1');

    expect($dimonaPeriod1->state)->toBe(DimonaPeriodState::Pending)
        ->and($dimonaPeriod1->worker_type)->toBe(WorkerType::Flexi)
        ->and($dimonaPeriod1->dimona_declarations()->count())->toBe(1)
        ->and($declaration1->type)->toBe(DimonaDeclarationType::In)
        ->and($declaration1->state)->toBe(DimonaDeclarationState::Pending);

    Queue::assertPushed(SyncDimonaPeriodsJob::class, 1);

    // Loop 2: Sync - accepted with warning (flexi requirements not met) -> triggers auto-cancel

    (new UniqueLock(app(Cache::class)))->release($job);

    $job->handle();

    $dimonaPeriod1->refresh();
    $declaration1->refresh();
    $declaration2 = $dimonaPeriod1->dimona_declarations()->firstWhere('reference', 'declaration-ref-2');

    expect($dimonaPeriod1->state)->toBe(DimonaPeriodState::Pending)
        ->and($dimonaPeriod1->dimona_declarations()->count())->toBe(2)
        ->and($declaration1->state)->toBe(DimonaDeclarationState::AcceptedWithWarning)
        ->and($declaration1->anomalies)->toHaveCount(1)
        ->and($declaration2->type)->toBe(DimonaDeclarationType::Cancel)
        ->and($declaration2->state)->toBe(DimonaDeclarationState::Pending);

    Queue::assertPushed(SyncDimonaPeriodsJob::class, 2);

    // Loop 3: Sync cancel - still pending

    (new UniqueLock(app(Cache::class)))->release($job);

    $job->handle();

    $dimonaPeriod1->refresh();
    $declaration2->refresh();

    expect($dimonaPeriod1->state)->toBe(DimonaPeriodState::Pending)
        ->and($dimonaPeriod1->dimona_declarations()->count())->toBe(2)
        ->and($declaration2->state)->toBe(DimonaDeclarationState::Pending);

    Queue::assertPushed(SyncDimonaPeriodsJob::class, 3);

    // Loop 4: Cancel accepted + Create new period with corrected worker type

    (new UniqueLock(app(Cache::class)))->release($job);

    $job->handle();

    $dimonaPeriod1->refresh();
    $declaration2->refresh();
    $dimonaPeriod2 = DimonaPeriod::query()->latest('id')->first();
    $declaration3 = $dimonaPeriod2->dimona_declarations()->firstWhere('reference', 'declaration-ref-3');

    expect($dimonaPeriod1->state)->toBe(DimonaPeriodState::Cancelled)
        ->and($dimonaPeriod1->dimona_declarations()->count())->toBe(2)
        ->and($declaration2->state)->toBe(DimonaDeclarationState::Accepted)
        ->and($dimonaPeriod2->state)->toBe(DimonaPeriodState::Pending)
        ->and($dimonaPeriod2->worker_type)->toBe(WorkerType::Other)
        ->and($dimonaPeriod2->dimona_declarations()->count())->toBe(1)
        ->and($declaration3->type)->toBe(DimonaDeclarationType::In)
        ->and($declaration3->state)->toBe(DimonaDeclarationState::Pending);

    Queue::assertPushed(SyncDimonaPeriodsJob::class, 4);

    // Loop 5: Sync new declaration - still pending

    (new UniqueLock(app(Cache::class)))->release($job);

    $job->handle();

    $dimonaPeriod2->refresh();
    $declaration3->refresh();

    expect($dimonaPeriod2->state)->toBe(DimonaPeriodState::Pending)
        ->and($dimonaPeriod2->dimona_declarations()->count())->toBe(1)
        ->and($declaration3->state)->toBe(DimonaDeclarationState::Pending);

    Queue::assertPushed(SyncDimonaPeriodsJob::class, 5);

    // Loop 6: New declaration accepted

    (new UniqueLock(app(Cache::class)))->release($job);

    $job->handle();

    $dimonaPeriod2->refresh();
    $declaration3->refresh();

    expect($dimonaPeriod2->state)->toBe(DimonaPeriodState::Accepted)
        ->and($dimonaPeriod2->dimona_declarations()->count())->toBe(1)
        ->and($declaration3->state)->toBe(DimonaDeclarationState::Accepted);

    Queue::assertPushed(SyncDimonaPeriodsJob::class, 5);
});

it('handles accepted with warning state with student anomaly', function () {
    Http::fake([
        config('dimona.oauth_endpoint') => Http::response([
            'access_token' => 'fake-token',
            'expires_in' => 3600,
        ]),
        '*/declarations' => Http::sequence()
            ->push([], 201, ['Location' => 'declarations/declaration-ref-1'])
            ->push([], 201, ['Location' => 'declarations/declaration-ref-2'])
            ->push([], 201, ['Location' => 'declarations/declaration-ref-3']),
        '*/declarations/declaration-ref-1' => Http::response([
            'declarationStatus' => [
                'period' => ['id' => 'period-ref-1'],
                'result' => 'W',
                'anomalies' => [
                    ['code' => '90017-369', 'description' => 'Overschrijding van het contingent'],
                ],
            ],
        ]),
        '*/declarations/declaration-ref-2' => Http::sequence()
            ->push([], 404)  // Loop 3: cancel pending
            ->push([         // Loop 4: cancel accepted
                'declarationStatus' => [
                    'period' => ['id' => 'period-ref-1'],
                    'result' => 'A',
                    'anomalies' => [],
                ],
            ]),
        '*/declarations/declaration-ref-3' => Http::sequence()
            ->push([], 404)  // Loop 6: new create pending
            ->push([         // Loop 7: new create accepted
                'declarationStatus' => [
                    'period' => ['id' => 'period-ref-2'],
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

    // Loop 1: Create declaration with Student worker type

    $job->handle();

    $dimonaPeriod1 = DimonaPeriod::query()->latest('id')->first();
    $declaration1 = $dimonaPeriod1->dimona_declarations()->firstWhere('reference', 'declaration-ref-1');

    expect($dimonaPeriod1->state)->toBe(DimonaPeriodState::Pending)
        ->and($dimonaPeriod1->worker_type)->toBe(WorkerType::Student)
        ->and($dimonaPeriod1->dimona_declarations()->count())->toBe(1)
        ->and($declaration1->type)->toBe(DimonaDeclarationType::In)
        ->and($declaration1->state)->toBe(DimonaDeclarationState::Pending);

    Queue::assertPushed(SyncDimonaPeriodsJob::class, 1);

    // Loop 2: Sync - accepted with warning (student requirements not met) -> triggers auto-cancel

    (new UniqueLock(app(Cache::class)))->release($job);

    $job->handle();

    $dimonaPeriod1->refresh();
    $declaration1->refresh();
    $declaration2 = $dimonaPeriod1->dimona_declarations()->firstWhere('reference', 'declaration-ref-2');

    expect($dimonaPeriod1->state)->toBe(DimonaPeriodState::Pending)
        ->and($dimonaPeriod1->dimona_declarations()->count())->toBe(2)
        ->and($declaration1->state)->toBe(DimonaDeclarationState::AcceptedWithWarning)
        ->and($declaration1->anomalies)->toHaveCount(1)
        ->and($declaration2->type)->toBe(DimonaDeclarationType::Cancel)
        ->and($declaration2->state)->toBe(DimonaDeclarationState::Pending);

    Queue::assertPushed(SyncDimonaPeriodsJob::class, 2);

    // Loop 3: Sync cancel - still pending

    (new UniqueLock(app(Cache::class)))->release($job);

    $job->handle();

    $dimonaPeriod1->refresh();
    $declaration2->refresh();

    expect($dimonaPeriod1->state)->toBe(DimonaPeriodState::Pending)
        ->and($dimonaPeriod1->dimona_declarations()->count())->toBe(2)
        ->and($declaration2->state)->toBe(DimonaDeclarationState::Pending);

    Queue::assertPushed(SyncDimonaPeriodsJob::class, 3);

    // Loop 4: Cancel accepted + Create new period with corrected worker type

    (new UniqueLock(app(Cache::class)))->release($job);

    $job->handle();

    $dimonaPeriod1->refresh();
    $declaration2->refresh();
    $dimonaPeriod2 = DimonaPeriod::query()->latest('id')->first();
    $declaration3 = $dimonaPeriod2->dimona_declarations()->firstWhere('reference', 'declaration-ref-3');

    expect($dimonaPeriod1->state)->toBe(DimonaPeriodState::Cancelled)
        ->and($dimonaPeriod1->dimona_declarations()->count())->toBe(2)
        ->and($declaration2->state)->toBe(DimonaDeclarationState::Accepted)
        ->and($dimonaPeriod2->state)->toBe(DimonaPeriodState::Pending)
        ->and($dimonaPeriod2->worker_type)->toBe(WorkerType::Other)
        ->and($dimonaPeriod2->dimona_declarations()->count())->toBe(1)
        ->and($declaration3->type)->toBe(DimonaDeclarationType::In)
        ->and($declaration3->state)->toBe(DimonaDeclarationState::Pending);

    Queue::assertPushed(SyncDimonaPeriodsJob::class, 4);

    // Loop 5: Sync new declaration - still pending

    (new UniqueLock(app(Cache::class)))->release($job);

    $job->handle();

    $dimonaPeriod2->refresh();
    $declaration3->refresh();

    expect($dimonaPeriod2->state)->toBe(DimonaPeriodState::Pending)
        ->and($dimonaPeriod2->dimona_declarations()->count())->toBe(1)
        ->and($declaration3->state)->toBe(DimonaDeclarationState::Pending);

    Queue::assertPushed(SyncDimonaPeriodsJob::class, 5);

    // Loop 6: New declaration accepted

    (new UniqueLock(app(Cache::class)))->release($job);

    $job->handle();

    $dimonaPeriod2->refresh();
    $declaration3->refresh();

    expect($dimonaPeriod2->state)->toBe(DimonaPeriodState::Accepted)
        ->and($dimonaPeriod2->dimona_declarations()->count())->toBe(1)
        ->and($declaration3->state)->toBe(DimonaDeclarationState::Accepted);

    Queue::assertPushed(SyncDimonaPeriodsJob::class, 5);
});

it('handles accepted with warning state without flexi or student anomaly', function () {
    Http::fake([
        config('dimona.oauth_endpoint') => Http::response([
            'access_token' => 'fake-token',
            'expires_in' => 3600,
        ]),
        '*/declarations' => Http::response([], 201, ['Location' => 'declarations/declaration-ref-1']),
        '*/declarations/declaration-ref-1' => Http::response([
            'declarationStatus' => [
                'period' => ['id' => 'period-ref-1'],
                'result' => 'W',
                'anomalies' => [
                    ['code' => 'W001', 'description' => 'Some other warning'],
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
        ->and($dimonaPeriod->worker_type)->toBe(WorkerType::Student)
        ->and($dimonaPeriod->dimona_declarations()->count())->toBe(1)
        ->and($declaration->type)->toBe(DimonaDeclarationType::In)
        ->and($declaration->state)->toBe(DimonaDeclarationState::Pending);

    Queue::assertPushed(SyncDimonaPeriodsJob::class, 1);

    // Loop 2: Sync - accepted with warning but no auto-cancel anomaly, should become Accepted

    (new UniqueLock(app(Cache::class)))->release($job);

    $job->handle();

    $dimonaPeriod->refresh();
    $declaration->refresh();

    expect($declaration->state)->toBe(DimonaDeclarationState::AcceptedWithWarning)
        ->and($declaration->anomalies)->toHaveCount(1)
        ->and($dimonaPeriod->state)->toBe(DimonaPeriodState::Accepted)
        ->and($dimonaPeriod->dimona_declarations()->count())->toBe(1);

    Queue::assertPushed(SyncDimonaPeriodsJob::class, 1);
});
