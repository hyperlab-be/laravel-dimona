<?php

use Carbon\CarbonPeriodImmutable;
use Hyperlab\Dimona\Enums\DimonaDeclarationState;
use Hyperlab\Dimona\Enums\DimonaDeclarationType;
use Hyperlab\Dimona\Enums\DimonaPeriodState;
use Hyperlab\Dimona\Enums\WorkerType;
use Hyperlab\Dimona\Jobs\SyncDimonaPeriodsJob;
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

    $employments = collect([
        EmploymentDataFactory::new()
            ->workerType(WorkerType::Flexi)
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

    $job->handle();

    $dimonaPeriod1->refresh();
    $declaration2->refresh();

    expect($dimonaPeriod1->state)->toBe(DimonaPeriodState::Pending)
        ->and($dimonaPeriod1->dimona_declarations()->count())->toBe(2)
        ->and($declaration2->state)->toBe(DimonaDeclarationState::Pending);

    Queue::assertPushed(SyncDimonaPeriodsJob::class, 3);

    // Loop 4: Cancel accepted + Create new period with corrected worker type

    $job->handle(4);

    $dimonaPeriod1->refresh();
    $declaration2->refresh();
    $dimonaPeriod2 = DimonaPeriod::query()->latest('id')->first();
    $declaration3 = $dimonaPeriod2->dimona_declarations()->firstWhere('reference', 'declaration-ref-3');

    expect($dimonaPeriod1->state)->toBe(DimonaPeriodState::Cancelled)
        ->and($dimonaPeriod1->dimona_declarations()->count())->toBe(2)
        ->and($declaration2->state)->toBe(DimonaDeclarationState::Accepted)
        ->and(DimonaPeriod::query()->count())->toBe(2)
        ->and($dimonaPeriod2->state)->toBe(DimonaPeriodState::Pending)
        ->and($dimonaPeriod2->worker_type)->toBe(WorkerType::Other)
        ->and($dimonaPeriod2->dimona_declarations()->count())->toBe(1)
        ->and($declaration3->type)->toBe(DimonaDeclarationType::In)
        ->and($declaration3->state)->toBe(DimonaDeclarationState::Pending);

    Queue::assertPushed(SyncDimonaPeriodsJob::class, 4);

    // Loop 6: Sync new declaration - still pending

    $job->handle();

    $dimonaPeriod2->refresh();
    $declaration3->refresh();

    expect($dimonaPeriod2->state)->toBe(DimonaPeriodState::Pending)
        ->and($dimonaPeriod2->dimona_declarations()->count())->toBe(1)
        ->and($declaration3->state)->toBe(DimonaDeclarationState::Pending);

    Queue::assertPushed(SyncDimonaPeriodsJob::class, 5);

    // Loop 7: New declaration accepted

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

    $employments = collect([
        EmploymentDataFactory::new()
            ->workerType(WorkerType::Student)
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

    // Loop 2: Sync - accepted with warning (student contingent exceeded) -> triggers auto-cancel

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

    $job->handle();

    $dimonaPeriod1->refresh();
    $declaration2->refresh();

    expect($dimonaPeriod1->state)->toBe(DimonaPeriodState::Pending)
        ->and($dimonaPeriod1->dimona_declarations()->count())->toBe(2)
        ->and($declaration2->state)->toBe(DimonaDeclarationState::Pending);

    Queue::assertPushed(SyncDimonaPeriodsJob::class, 3);

    // Loop 4: Cancel accepted + Create new period with corrected worker type

    $job->handle(4);

    $dimonaPeriod1->refresh();
    $declaration2->refresh();
    $dimonaPeriod2 = DimonaPeriod::query()->latest('id')->first();
    $declaration3 = $dimonaPeriod2->dimona_declarations()->firstWhere('reference', 'declaration-ref-3');

    expect($dimonaPeriod1->state)->toBe(DimonaPeriodState::Cancelled)
        ->and($dimonaPeriod1->dimona_declarations()->count())->toBe(2)
        ->and($declaration2->state)->toBe(DimonaDeclarationState::Accepted)
        ->and(DimonaPeriod::query()->count())->toBe(2)
        ->and($dimonaPeriod2->state)->toBe(DimonaPeriodState::Pending)
        ->and($dimonaPeriod2->worker_type)->toBe(WorkerType::Other)
        ->and($dimonaPeriod2->dimona_declarations()->count())->toBe(1)
        ->and($declaration3->type)->toBe(DimonaDeclarationType::In)
        ->and($declaration3->state)->toBe(DimonaDeclarationState::Pending);

    Queue::assertPushed(SyncDimonaPeriodsJob::class, 4);

    // Loop 6: Sync new declaration - still pending

    $job->handle();

    $dimonaPeriod2->refresh();
    $declaration3->refresh();

    expect($dimonaPeriod2->state)->toBe(DimonaPeriodState::Pending)
        ->and($dimonaPeriod2->dimona_declarations()->count())->toBe(1)
        ->and($declaration3->state)->toBe(DimonaDeclarationState::Pending);

    Queue::assertPushed(SyncDimonaPeriodsJob::class, 5);

    // Loop 7: New declaration accepted

    $job->handle();

    $dimonaPeriod2->refresh();
    $declaration3->refresh();

    expect($dimonaPeriod2->state)->toBe(DimonaPeriodState::Accepted)
        ->and($dimonaPeriod2->dimona_declarations()->count())->toBe(1)
        ->and($declaration3->state)->toBe(DimonaDeclarationState::Accepted);

    Queue::assertPushed(SyncDimonaPeriodsJob::class, 5);
});

it('handles accepted with warning state with student anomaly on update declaration', function () {
    Http::fake([
        config('dimona.oauth_endpoint') => Http::response([
            'access_token' => 'fake-token',
            'expires_in' => 3600,
        ]),
        '*/declarations' => Http::sequence()
            ->push([], 201, ['Location' => 'declarations/declaration-ref-1'])
            ->push([], 201, ['Location' => 'declarations/update-declaration-ref'])
            ->push([], 201, ['Location' => 'declarations/cancel-declaration-ref'])
            ->push([], 201, ['Location' => 'declarations/new-declaration-ref']),
        '*/declarations/declaration-ref-1' => Http::sequence()
            ->push([], 404)  // Loop 2: still pending
            ->push([         // Loop 3: accepted
                'declarationStatus' => [
                    'period' => ['id' => 'period-ref-1'],
                    'result' => 'A',
                    'anomalies' => [],
                ],
            ]),
        '*/declarations/update-declaration-ref' => Http::sequence()
            ->push([], 404)  // Loop 5: update still pending
            ->push([         // Loop 6: update accepted with warning
                'declarationStatus' => [
                    'period' => ['id' => 'period-ref-1'],
                    'result' => 'W',
                    'anomalies' => [
                        ['code' => '90017-369', 'description' => 'Overschrijding van het contingent'],
                    ],
                ],
            ]),
        '*/declarations/cancel-declaration-ref' => Http::sequence()
            ->push([], 404)  // Loop 7: cancel pending
            ->push([         // Loop 8: cancel accepted
                'declarationStatus' => [
                    'period' => ['id' => 'period-ref-1'],
                    'result' => 'A',
                    'anomalies' => [],
                ],
            ]),
        '*/declarations/new-declaration-ref' => Http::sequence()
            ->push([], 404)  // Loop 9: new create pending
            ->push([         // Loop 10: new create accepted
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
            ->workerType(WorkerType::Student)
            ->startsAt('2025-10-01 07:00')
            ->endsAt('2025-10-01 12:00')
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

    // Loop 2: Sync pending declaration - still pending

    $job->handle();

    $dimonaPeriod1->refresh();
    $declaration1->refresh();

    expect($dimonaPeriod1->state)->toBe(DimonaPeriodState::Pending)
        ->and($dimonaPeriod1->dimona_declarations()->count())->toBe(1)
        ->and($declaration1->state)->toBe(DimonaDeclarationState::Pending);

    Queue::assertPushed(SyncDimonaPeriodsJob::class, 2);

    // Loop 3: Declaration is now accepted

    $job->handle();

    $dimonaPeriod1->refresh();
    $declaration1->refresh();

    expect($dimonaPeriod1->state)->toBe(DimonaPeriodState::Accepted)
        ->and($dimonaPeriod1->dimona_declarations()->count())->toBe(1)
        ->and($declaration1->state)->toBe(DimonaDeclarationState::Accepted);

    Queue::assertPushed(SyncDimonaPeriodsJob::class, 2);

    // Loop 4: Employment hours increase, triggering update

    $updatedEmployment = clone $employments->first();
    $updatedEmployment->endsAt = $updatedEmployment->endsAt->addHours(3);  // Increased from 12:00 to 15:00

    $updatedEmployments = collect([
        $updatedEmployment,
    ]);

    $job = new SyncDimonaPeriodsJob(
        $this->employerEnterpriseNumber,
        $this->workerSocialSecurityNumber,
        $this->period,
        $updatedEmployments
    );

    $job->handle();

    $dimonaPeriod1->refresh();
    $updateDeclaration = $dimonaPeriod1->dimona_declarations()->firstWhere('reference', 'update-declaration-ref');

    expect($dimonaPeriod1->state)->toBe(DimonaPeriodState::Pending)
        ->and($dimonaPeriod1->dimona_declarations()->count())->toBe(2)
        ->and($updateDeclaration->type)->toBe(DimonaDeclarationType::Update)
        ->and($updateDeclaration->state)->toBe(DimonaDeclarationState::Pending);

    Queue::assertPushed(SyncDimonaPeriodsJob::class, 3);

    // Loop 5: Sync update declaration - still pending

    $job->handle();

    $dimonaPeriod1->refresh();
    $updateDeclaration->refresh();

    expect($dimonaPeriod1->state)->toBe(DimonaPeriodState::Pending)
        ->and($dimonaPeriod1->dimona_declarations()->count())->toBe(2)
        ->and($updateDeclaration->state)->toBe(DimonaDeclarationState::Pending);

    Queue::assertPushed(SyncDimonaPeriodsJob::class, 4);

    // Loop 6: Sync - update accepted with warning (student contingent exceeded) -> triggers auto-cancel

    $job->handle();

    $dimonaPeriod1->refresh();
    $updateDeclaration->refresh();
    $cancelDeclaration = $dimonaPeriod1->dimona_declarations()->firstWhere('reference', 'cancel-declaration-ref');

    expect($dimonaPeriod1->state)->toBe(DimonaPeriodState::Pending)
        ->and($dimonaPeriod1->dimona_declarations()->count())->toBe(3)
        ->and($updateDeclaration->state)->toBe(DimonaDeclarationState::AcceptedWithWarning)
        ->and($updateDeclaration->anomalies)->toHaveCount(1)
        ->and($cancelDeclaration->type)->toBe(DimonaDeclarationType::Cancel)
        ->and($cancelDeclaration->state)->toBe(DimonaDeclarationState::Pending);

    Queue::assertPushed(SyncDimonaPeriodsJob::class, 5);

    // Loop 7: Sync cancel - still pending

    $job->handle();

    $dimonaPeriod1->refresh();
    $cancelDeclaration->refresh();

    expect($dimonaPeriod1->state)->toBe(DimonaPeriodState::Pending)
        ->and($dimonaPeriod1->dimona_declarations()->count())->toBe(3)
        ->and($cancelDeclaration->state)->toBe(DimonaDeclarationState::Pending);

    Queue::assertPushed(SyncDimonaPeriodsJob::class, 6);

    // Loop 8: Cancel accepted + Create new period with corrected worker type

    $job->handle();

    $dimonaPeriod1->refresh();
    $cancelDeclaration->refresh();
    $dimonaPeriod2 = DimonaPeriod::query()->latest('id')->first();
    $newDeclaration = $dimonaPeriod2->dimona_declarations()->firstWhere('reference', 'new-declaration-ref');

    expect($dimonaPeriod1->state)->toBe(DimonaPeriodState::Cancelled)
        ->and($dimonaPeriod1->dimona_declarations()->count())->toBe(3)
        ->and($cancelDeclaration->state)->toBe(DimonaDeclarationState::Accepted)
        ->and(DimonaPeriod::query()->count())->toBe(2)
        ->and($dimonaPeriod2->state)->toBe(DimonaPeriodState::Pending)
        ->and($dimonaPeriod2->worker_type)->toBe(WorkerType::Other)
        ->and($dimonaPeriod2->dimona_declarations()->count())->toBe(1)
        ->and($newDeclaration->type)->toBe(DimonaDeclarationType::In)
        ->and($newDeclaration->state)->toBe(DimonaDeclarationState::Pending);

    Queue::assertPushed(SyncDimonaPeriodsJob::class, 7);

    // Loop 9: Sync new declaration - still pending

    $job->handle();

    $dimonaPeriod2->refresh();
    $newDeclaration->refresh();

    expect($dimonaPeriod2->state)->toBe(DimonaPeriodState::Pending)
        ->and($dimonaPeriod2->dimona_declarations()->count())->toBe(1)
        ->and($newDeclaration->state)->toBe(DimonaDeclarationState::Pending);

    Queue::assertPushed(SyncDimonaPeriodsJob::class, 8);

    // Loop 10: New declaration accepted

    $job->handle();

    $dimonaPeriod2->refresh();
    $newDeclaration->refresh();

    expect($dimonaPeriod2->state)->toBe(DimonaPeriodState::Accepted)
        ->and($dimonaPeriod2->dimona_declarations()->count())->toBe(1)
        ->and($newDeclaration->state)->toBe(DimonaDeclarationState::Accepted);

    Queue::assertPushed(SyncDimonaPeriodsJob::class, 8);
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

    $employments = collect([
        EmploymentDataFactory::new()
            ->workerType(WorkerType::Student)
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

    $job->handle();

    $dimonaPeriod->refresh();
    $declaration->refresh();

    expect($declaration->state)->toBe(DimonaDeclarationState::AcceptedWithWarning)
        ->and($declaration->anomalies)->toHaveCount(1)
        ->and($dimonaPeriod->state)->toBe(DimonaPeriodState::Accepted)
        ->and($dimonaPeriod->dimona_declarations()->count())->toBe(1);

    Queue::assertPushed(SyncDimonaPeriodsJob::class, 1);
});
