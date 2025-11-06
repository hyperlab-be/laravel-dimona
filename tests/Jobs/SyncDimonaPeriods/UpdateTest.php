<?php

use Carbon\CarbonPeriodImmutable;
use Hyperlab\Dimona\Data\EmploymentData;
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

it('updates an existing dimona period when employment changes', function () {
    Http::fake([
        config('dimona.oauth_endpoint') => Http::response([
            'access_token' => 'fake-token',
            'expires_in' => 3600,
        ]),
        '*/declarations' => Http::sequence()
            ->push([], 201, ['Location' => 'declarations/declaration-ref-1'])
            ->push([], 201, ['Location' => 'declarations/update-declaration-ref']),
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
            ->push([         // Loop 6: update accepted
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
            ->create(),
    ]);

    $job = new SyncDimonaPeriodsJob(
        $this->employerEnterpriseNumber,
        $this->workerSocialSecurityNumber,
        $this->period,
        $employments
    );

    // Loop 1: Create initial declaration

    $job->handle();

    $dimonaPeriod = DimonaPeriod::query()->first();
    $declaration = $dimonaPeriod->dimona_declarations()->firstWhere('reference', 'declaration-ref-1');

    expect($dimonaPeriod->state)->toBe(DimonaPeriodState::Pending)
        ->and($dimonaPeriod->dimona_declarations()->count())->toBe(1)
        ->and($declaration->type)->toBe(DimonaDeclarationType::In)
        ->and($declaration->state)->toBe(DimonaDeclarationState::Pending);

    Queue::assertPushed(SyncDimonaPeriodsJob::class, 1);

    // Loop 2: Sync pending declaration - still pending

    $job->handle();

    $dimonaPeriod->refresh();
    $declaration->refresh();

    expect($dimonaPeriod->state)->toBe(DimonaPeriodState::Pending)
        ->and($dimonaPeriod->dimona_declarations()->count())->toBe(1)
        ->and($declaration->state)->toBe(DimonaDeclarationState::Pending);

    Queue::assertPushed(SyncDimonaPeriodsJob::class, 2);

    // Loop 3: Declaration is now accepted

    $job->handle();

    $dimonaPeriod->refresh();
    $declaration->refresh();

    expect($dimonaPeriod->state)->toBe(DimonaPeriodState::Accepted)
        ->and($dimonaPeriod->dimona_declarations()->count())->toBe(1)
        ->and($declaration->state)->toBe(DimonaDeclarationState::Accepted);

    Queue::assertPushed(SyncDimonaPeriodsJob::class, 2);

    // Loop 4: Employment changes, triggering update

    /** @var EmploymentData $updatedEmployment */
    $updatedEmployment = clone $employments->first();
    $updatedEmployment->startsAt = $updatedEmployment->startsAt->addHour();

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

    $dimonaPeriod->refresh();
    $updateDeclaration = $dimonaPeriod->dimona_declarations()->firstWhere('reference', 'update-declaration-ref');

    expect($dimonaPeriod->state)->toBe(DimonaPeriodState::Pending)
        ->and($dimonaPeriod->dimona_declarations()->count())->toBe(2)
        ->and($updateDeclaration->type)->toBe(DimonaDeclarationType::Update)
        ->and($updateDeclaration->state)->toBe(DimonaDeclarationState::Pending);

    Queue::assertPushed(SyncDimonaPeriodsJob::class, 3);

    // Loop 5: Sync update declaration - still pending

    $job->handle();

    $dimonaPeriod->refresh();
    $updateDeclaration->refresh();

    expect($dimonaPeriod->state)->toBe(DimonaPeriodState::Pending)
        ->and($dimonaPeriod->dimona_declarations()->count())->toBe(2)
        ->and($updateDeclaration->state)->toBe(DimonaDeclarationState::Pending);

    Queue::assertPushed(SyncDimonaPeriodsJob::class, 4);

    // Loop 6: Update declaration is accepted

    $job->handle();

    $dimonaPeriod->refresh();
    $updateDeclaration->refresh();

    expect($dimonaPeriod->state)->toBe(DimonaPeriodState::Accepted)
        ->and($dimonaPeriod->dimona_declarations()->count())->toBe(2)
        ->and($updateDeclaration->state)->toBe(DimonaDeclarationState::Accepted);

    Queue::assertPushed(SyncDimonaPeriodsJob::class, 4);
});

it('updates existing dimona period when employment switches and details change', function () {
    Http::fake([
        config('dimona.oauth_endpoint') => Http::response([
            'access_token' => 'fake-token',
            'expires_in' => 3600,
        ]),
        '*/declarations' => Http::sequence()
            ->push([], 201, ['Location' => 'declarations/declaration-ref-1'])
            ->push([], 201, ['Location' => 'declarations/update-declaration-ref']),
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
            ->push([         // Loop 6: update accepted
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
            ->create(),
    ]);

    $job = new SyncDimonaPeriodsJob(
        $this->employerEnterpriseNumber,
        $this->workerSocialSecurityNumber,
        $this->period,
        $employments
    );

    // Loop 1: Create initial declaration

    $job->handle();

    $dimonaPeriod = DimonaPeriod::query()->first();
    $declaration = $dimonaPeriod->dimona_declarations()->firstWhere('reference', 'declaration-ref-1');

    expect($dimonaPeriod->state)->toBe(DimonaPeriodState::Pending)
        ->and($dimonaPeriod->dimona_declarations()->count())->toBe(1)
        ->and($declaration->type)->toBe(DimonaDeclarationType::In)
        ->and($declaration->state)->toBe(DimonaDeclarationState::Pending);

    Queue::assertPushed(SyncDimonaPeriodsJob::class, 1);

    // Loop 2: Sync pending declaration - still pending

    $job->handle();

    $dimonaPeriod->refresh();
    $declaration->refresh();

    expect($dimonaPeriod->state)->toBe(DimonaPeriodState::Pending)
        ->and($dimonaPeriod->dimona_declarations()->count())->toBe(1)
        ->and($declaration->state)->toBe(DimonaDeclarationState::Pending);

    Queue::assertPushed(SyncDimonaPeriodsJob::class, 2);

    // Loop 3: Declaration is now accepted

    $job->handle();

    $dimonaPeriod->refresh();
    $declaration->refresh();

    expect($dimonaPeriod->state)->toBe(DimonaPeriodState::Accepted)
        ->and($dimonaPeriod->dimona_declarations()->count())->toBe(1)
        ->and($dimonaPeriod->dimona_period_employments->pluck('employment_id')->toArray())->toBe(['emp-1'])
        ->and($declaration->state)->toBe(DimonaDeclarationState::Accepted);

    Queue::assertPushed(SyncDimonaPeriodsJob::class, 2);

    // Loop 4: Employment switches

    /** @var EmploymentData $updatedEmployment */
    $updatedEmployment = clone $employments->first();
    $updatedEmployment->id = 'emp-2';
    $updatedEmployment->startsAt = $updatedEmployment->startsAt->addHour();

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

    $dimonaPeriod->refresh();
    $updateDeclaration = $dimonaPeriod->dimona_declarations()->firstWhere('reference', 'update-declaration-ref');

    expect($dimonaPeriod->state)->toBe(DimonaPeriodState::Pending)
        ->and($dimonaPeriod->dimona_declarations()->count())->toBe(2)
        ->and($updateDeclaration->type)->toBe(DimonaDeclarationType::Update)
        ->and($updateDeclaration->state)->toBe(DimonaDeclarationState::Pending);

    Queue::assertPushed(SyncDimonaPeriodsJob::class, 3);

    // Loop 5: Sync update declaration - still pending

    $job->handle();

    $dimonaPeriod->refresh();
    $updateDeclaration->refresh();

    expect($dimonaPeriod->state)->toBe(DimonaPeriodState::Pending)
        ->and($dimonaPeriod->dimona_declarations()->count())->toBe(2)
        ->and($updateDeclaration->state)->toBe(DimonaDeclarationState::Pending);

    Queue::assertPushed(SyncDimonaPeriodsJob::class, 4);

    // Loop 6: Update declaration is accepted

    $job->handle();

    $dimonaPeriod->refresh();
    $updateDeclaration->refresh();

    expect($dimonaPeriod->state)->toBe(DimonaPeriodState::Accepted)
        ->and($dimonaPeriod->dimona_declarations()->count())->toBe(2)
        ->and($updateDeclaration->state)->toBe(DimonaDeclarationState::Accepted);

    Queue::assertPushed(SyncDimonaPeriodsJob::class, 4);
});

it('keeps existing dimona period when employment switches but details stay the same', function () {
    Http::fake([
        config('dimona.oauth_endpoint') => Http::response([
            'access_token' => 'fake-token',
            'expires_in' => 3600,
        ]),
        '*/declarations' => Http::sequence()
            ->push([], 201, ['Location' => 'declarations/declaration-ref-1']),
        '*/declarations/declaration-ref-1' => Http::sequence()
            ->push([], 404)  // Loop 2: still pending
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
            ->create(),
    ]);

    $job = new SyncDimonaPeriodsJob(
        $this->employerEnterpriseNumber,
        $this->workerSocialSecurityNumber,
        $this->period,
        $employments
    );

    // Loop 1: Create initial declaration

    $job->handle();

    $dimonaPeriod = DimonaPeriod::query()->first();
    $declaration = $dimonaPeriod->dimona_declarations()->firstWhere('reference', 'declaration-ref-1');

    expect($dimonaPeriod->state)->toBe(DimonaPeriodState::Pending)
        ->and($dimonaPeriod->dimona_declarations()->count())->toBe(1)
        ->and($declaration->type)->toBe(DimonaDeclarationType::In)
        ->and($declaration->state)->toBe(DimonaDeclarationState::Pending);

    Queue::assertPushed(SyncDimonaPeriodsJob::class, 1);

    // Loop 2: Sync pending declaration - still pending

    $job->handle();

    $dimonaPeriod->refresh();
    $declaration->refresh();

    expect($dimonaPeriod->state)->toBe(DimonaPeriodState::Pending)
        ->and($dimonaPeriod->dimona_declarations()->count())->toBe(1)
        ->and($declaration->state)->toBe(DimonaDeclarationState::Pending);

    Queue::assertPushed(SyncDimonaPeriodsJob::class, 2);

    // Loop 3: Declaration is now accepted

    $job->handle();

    $dimonaPeriod->refresh();
    $declaration->refresh();

    expect($dimonaPeriod->state)->toBe(DimonaPeriodState::Accepted)
        ->and($dimonaPeriod->dimona_declarations()->count())->toBe(1)
        ->and($dimonaPeriod->dimona_period_employments->pluck('employment_id')->toArray())->toBe(['emp-1'])
        ->and($declaration->state)->toBe(DimonaDeclarationState::Accepted);

    Queue::assertPushed(SyncDimonaPeriodsJob::class, 2);

    // Loop 4: Employment switches

    /** @var EmploymentData $updatedEmployment */
    $updatedEmployment = clone $employments->first();
    $updatedEmployment->id = 'emp-2';

    $updatedEmployments = collect([
        $updatedEmployment,
    ]);

    $job = new SyncDimonaPeriodsJob(
        $this->employerEnterpriseNumber,
        $this->workerSocialSecurityNumber,
        $this->period,
        $updatedEmployments
    );

    $job->handle(4);

    $dimonaPeriod->refresh();

    expect($dimonaPeriod->state)->toBe(DimonaPeriodState::Accepted)
        ->and($dimonaPeriod->dimona_declarations()->count())->toBe(1)
        ->and($dimonaPeriod->dimona_period_employments->pluck('employment_id')->toArray())->toBe(['emp-2']);

    Queue::assertPushed(SyncDimonaPeriodsJob::class, 2);
});
