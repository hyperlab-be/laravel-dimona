<?php

use Hyperlab\Dimona\Enums\DimonaDeclarationState;
use Hyperlab\Dimona\Enums\DimonaDeclarationType;
use Hyperlab\Dimona\Enums\DimonaPeriodState;
use Hyperlab\Dimona\Enums\WorkerType;
use Hyperlab\Dimona\Jobs\DeclareDimona;
use Hyperlab\Dimona\Jobs\SyncDimonaDeclaration;
use Hyperlab\Dimona\Models\DimonaDeclaration;
use Hyperlab\Dimona\Models\DimonaPeriod;
use Hyperlab\Dimona\Services\DimonaApiClient;
use Hyperlab\Dimona\Tests\Mocks\MockDimonaApiClient;
use Hyperlab\Dimona\Tests\Models\TestEmployment;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;

uses(RefreshDatabase::class);

beforeEach(function () {
    // Create a test employment
    $this->employment = TestEmployment::query()->create();

    // Create and register the mock DimonaApiClient
    $this->apiClient = new MockDimonaApiClient;
    $this->apiClient->register();

    // Fake the queue
    Queue::fake();
});

it('can be instantiated', function () {
    $job = new DeclareDimona($this->employment);

    expect($job)->toBeInstanceOf(DeclareDimona::class)
        ->and($job->employment)->toBe($this->employment)
        ->and($job->clientId)->toBeNull();
});

it('can be instantiated with a client ID', function () {
    $clientId = 'test-client';
    $job = new DeclareDimona($this->employment, $clientId);

    expect($job)->toBeInstanceOf(DeclareDimona::class)
        ->and($job->employment)->toBe($this->employment)
        ->and($job->clientId)->toBe($clientId);
});

it('returns the employment ID as unique ID', function () {
    $job = new DeclareDimona($this->employment);

    expect($job->uniqueId())->toBe($this->employment->id);
});

it('syncs a dimona period when it should be synced', function () {
    // Create a dimona period with pending state
    $dimonaPeriod = DimonaPeriod::query()->create([
        'model_id' => $this->employment->id,
        'model_type' => TestEmployment::class,
        'state' => DimonaPeriodState::Pending,
    ]);

    // Create a dimona declaration
    $dimonaDeclaration = DimonaDeclaration::query()->create([
        'dimona_period_id' => $dimonaPeriod->id,
        'type' => DimonaDeclarationType::In,
        'state' => DimonaDeclarationState::Pending,
        'payload' => [],
    ]);

    // Create the job
    $job = new DeclareDimona($this->employment);

    // Call the handle method
    $job->handle();

    // Assert that SyncDimonaDeclaration was dispatched
    Queue::assertPushed(SyncDimonaDeclaration::class, function (SyncDimonaDeclaration $job) use ($dimonaDeclaration) {
        return $job->employment === $this->employment && $job->dimonaDeclaration->id === $dimonaDeclaration->id;
    });
});

it('creates a dimona period when it should be created', function () {
    // Create a real employment for this test
    $employment = TestEmployment::query()->create();

    // Mock the createDeclaration method to return a reference
    $this->apiClient->mockCreateDeclaration('test-reference');

    // Create the job and inject mocks
    $job = new DeclareDimona($employment);

    // Call the handle method
    $job->handle();

    // Assert that SyncDimonaDeclaration was dispatched
    Queue::assertPushed(SyncDimonaDeclaration::class, function (SyncDimonaDeclaration $job) use ($employment) {
        return $job->employment->id === $employment->id &&
            $job->dimonaDeclaration->type === DimonaDeclarationType::In &&
            $job->dimonaDeclaration->state === DimonaDeclarationState::Pending;
    });
});

it('cancels a dimona period when it should be cancelled', function () {
    // Create a real employment for this test that should not declare Dimona
    $employment = TestEmployment::query()->create(['cancelled' => true]);

    // Create a real DimonaPeriod with accepted state
    DimonaPeriod::query()->create([
        'model_id' => $employment->id,
        'model_type' => TestEmployment::class,
        'worker_type' => WorkerType::Student,
        'state' => DimonaPeriodState::Accepted,
    ]);

    // Mock the API client to return a reference
    $this->apiClient->mockCreateDeclaration('test-reference');

    // Create the job and inject mocks
    $job = new DeclareDimona($employment);

    // Call the handle method
    $job->handle();

    // Assert that SyncDimonaDeclaration was dispatched
    Queue::assertPushed(SyncDimonaDeclaration::class, function (SyncDimonaDeclaration $job) use ($employment) {
        return $job->employment->id === $employment->id &&
            $job->dimonaDeclaration->type === DimonaDeclarationType::Cancel &&
            $job->dimonaDeclaration->state === DimonaDeclarationState::Pending;
    });
});

it('handles API exceptions when creating a declaration', function () {
    // Create a real employment for this test
    $employment = TestEmployment::query()->create();

    // Mock a request exception for createDeclaration
    $this->apiClient->mockCreateDeclarationException(['error' => 'test error']);

    // Create the job and inject mocks
    $job = new DeclareDimona($employment);

    // Call the handle method
    $job->handle();

    // Assert that DeclareDimona was dispatched again
    Queue::assertPushed(DeclareDimona::class, function (DeclareDimona $job) use ($employment) {
        return $job->employment->id === $employment->id;
    });
    Queue::assertNotPushed(SyncDimonaDeclaration::class);
});

it('throws an exception when too many dimona periods are created', function () {
    // Create a real employment for this test
    $employment = TestEmployment::query()->create();

    // Create 5 real DimonaPeriod instances
    for ($i = 0; $i < 5; $i++) {
        DimonaPeriod::query()->create([
            'model_id' => $employment->id,
            'model_type' => TestEmployment::class,
            'worker_type' => WorkerType::Student,
            'state' => DimonaPeriodState::Failed,
        ]);
    }

    // Create the job
    $job = new DeclareDimona($employment);

    // Call the handle method
    $job->handle();
})->throws(Exception::class, 'Too many dimona periods created for employment.');
