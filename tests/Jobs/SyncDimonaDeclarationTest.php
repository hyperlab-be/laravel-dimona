<?php

use Hyperlab\Dimona\Enums\DimonaDeclarationState;
use Hyperlab\Dimona\Enums\DimonaDeclarationType;
use Hyperlab\Dimona\Enums\DimonaPeriodState;
use Hyperlab\Dimona\Facades\Dimona;
use Hyperlab\Dimona\Jobs\SyncDimonaDeclaration;
use Hyperlab\Dimona\Models\DimonaDeclaration;
use Hyperlab\Dimona\Models\DimonaPeriod;
use Hyperlab\Dimona\Tests\Mocks\MockDimonaApiClient;
use Hyperlab\Dimona\Tests\Models\Employment;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Queue;

beforeEach(function () {
    // Create a test employment
    $this->employment = Employment::query()->create();

    // Create a dimona period
    $this->dimonaPeriod = DimonaPeriod::create([
        'worker_type' => $this->employment->getDimonaData()->workerType,
        'reference' => null,
        'model_type' => get_class($this->employment),
        'model_id' => $this->employment->id,
        'state' => DimonaPeriodState::Pending,
    ]);

    // Create a dimona declaration
    $this->dimonaDeclaration = DimonaDeclaration::create([
        'dimona_period_id' => $this->dimonaPeriod->id,
        'type' => DimonaDeclarationType::In,
        'reference' => '12345',
        'state' => DimonaDeclarationState::Waiting,
        'payload' => [],
        'anomalies' => [],
        'created_at' => Carbon::now(),
    ]);

    // Create and register the mock DimonaApiClient
    $this->apiClient = new MockDimonaApiClient;
    $this->apiClient->register();

    // Fake the queue
    Queue::fake();
});

afterEach(function () {
    // Clean up any mocks
    Mockery::close();
});

it('can be instantiated', function () {
    $job = new SyncDimonaDeclaration(
        dimonaDeclarable: $this->employment,
        dimonaDeclaration: $this->dimonaDeclaration,
        clientId: 'test-client'
    );

    expect($job)->toBeInstanceOf(SyncDimonaDeclaration::class)
        ->and($job->dimonaDeclarable)->toBe($this->employment)
        ->and($job->dimonaDeclaration)->toBe($this->dimonaDeclaration)
        ->and($job->clientId)->toBe('test-client');
});

it('returns the declaration id as unique id', function () {
    $job = new SyncDimonaDeclaration(
        dimonaDeclarable: $this->employment,
        dimonaDeclaration: $this->dimonaDeclaration,
        clientId: 'test-client'
    );

    expect($job->uniqueId())->toBe($this->dimonaDeclaration->id);
});

it('calculates backoff based on runtime', function () {
    // Test for runtime <= 30 seconds
    $this->dimonaDeclaration->created_at = Carbon::now()->subSeconds(15);
    $this->dimonaDeclaration->save();

    $job = new SyncDimonaDeclaration(
        dimonaDeclarable: $this->employment,
        dimonaDeclaration: $this->dimonaDeclaration,
        clientId: 'test-client'
    );

    expect($job->calculateBackoff())->toBe(1);

    // Test for runtime <= 1200 seconds (20 minutes)
    $this->dimonaDeclaration->created_at = Carbon::now()->subSeconds(600);
    $this->dimonaDeclaration->save();

    $job = new SyncDimonaDeclaration(
        dimonaDeclarable: $this->employment,
        dimonaDeclaration: $this->dimonaDeclaration,
        clientId: 'test-client'
    );

    expect($job->calculateBackoff())->toBe(60);

    // Test for runtime > 1200 seconds
    $this->dimonaDeclaration->created_at = Carbon::now()->subSeconds(1800);
    $this->dimonaDeclaration->save();

    $job = new SyncDimonaDeclaration(
        dimonaDeclarable: $this->employment,
        dimonaDeclaration: $this->dimonaDeclaration,
        clientId: 'test-client'
    );

    expect($job->calculateBackoff())->toBe(3600);
});

it('updates declaration state to Accepted when API returns A result', function () {
    // Set up the mock response
    $this->apiClient->mockGetDeclaration('12345', 'A', []);

    // Create the job and inject mocks
    $job = new SyncDimonaDeclaration(
        dimonaDeclarable: $this->employment,
        dimonaDeclaration: $this->dimonaDeclaration,
        clientId: 'test-client'
    );

    // Call the handle method
    $job->handle();

    // Refresh the declaration from the database
    $this->dimonaDeclaration->refresh();

    // Assert the declaration state was updated correctly
    expect($this->dimonaDeclaration->state)->toBe(DimonaDeclarationState::Accepted);

    // Assert the period reference was updated
    expect($this->dimonaDeclaration->dimona_period->reference)->toBe('12345');
});

it('updates declaration state to AcceptedWithWarning when API returns W result', function () {
    // Set up the mock response
    $this->apiClient->mockGetDeclaration('12345', 'W', ['some-warning']);

    // Create the job and inject mocks
    $job = new SyncDimonaDeclaration(
        dimonaDeclarable: $this->employment,
        dimonaDeclaration: $this->dimonaDeclaration,
        clientId: 'test-client'
    );

    // Call the handle method
    $job->handle();

    // Refresh the declaration from the database
    $this->dimonaDeclaration->refresh();

    // Assert the declaration state was updated correctly
    expect($this->dimonaDeclaration->state)->toBe(DimonaDeclarationState::AcceptedWithWarning);

    // Assert the anomalies were updated
    expect($this->dimonaDeclaration->anomalies)->toBe(['some-warning']);
});

it('updates declaration state to Refused when API returns B result', function () {
    // Set up the mock response
    $this->apiClient->mockGetDeclaration('12345', 'B', ['some-error']);

    // Create the job and inject mocks
    $job = new SyncDimonaDeclaration(
        dimonaDeclarable: $this->employment,
        dimonaDeclaration: $this->dimonaDeclaration,
        clientId: 'test-client'
    );

    // Call the handle method
    $job->handle();

    // Refresh the declaration from the database
    $this->dimonaDeclaration->refresh();

    // Assert the declaration state was updated correctly
    expect($this->dimonaDeclaration->state)->toBe(DimonaDeclarationState::Refused);

    // Assert the anomalies were updated
    expect($this->dimonaDeclaration->anomalies)->toBe(['some-error']);
});

it('updates declaration state to Waiting when API returns S result', function () {
    // Set up the mock response
    $this->apiClient->mockGetDeclaration('12345', 'S', []);

    // Create the job and inject mocks
    $job = new SyncDimonaDeclaration(
        dimonaDeclarable: $this->employment,
        dimonaDeclaration: $this->dimonaDeclaration,
        clientId: 'test-client'
    );

    // Call the handle method
    $job->handle();

    // Refresh the declaration from the database
    $this->dimonaDeclaration->refresh();

    // Assert the declaration state was updated correctly
    expect($this->dimonaDeclaration->state)->toBe(DimonaDeclarationState::Waiting);
});

it('updates declaration state to Failed when API returns unknown result', function () {
    // Set up the mock response
    $this->apiClient->mockGetDeclaration('12345', 'X', ['unknown-result']); // Unknown result code

    // Create the job and inject mocks
    $job = new SyncDimonaDeclaration(
        dimonaDeclarable: $this->employment,
        dimonaDeclaration: $this->dimonaDeclaration,
        clientId: 'test-client'
    );

    // Call the handle method
    $job->handle();

    // Refresh the declaration from the database
    $this->dimonaDeclaration->refresh();

    // Assert the declaration state was updated correctly
    expect($this->dimonaDeclaration->state)->toBe(DimonaDeclarationState::Failed);

    // Assert the anomalies were updated
    expect($this->dimonaDeclaration->anomalies)->toBe(['unknown-result']);
});

it('releases the job when DimonaDeclarationIsNotYetProcessed exception is thrown', function () {
    // Set up the mock to throw an exception
    $this->apiClient->mockDeclarationNotYetProcessed('12345');

    // Create a real job instance
    $job = new SyncDimonaDeclaration(
        dimonaDeclarable: $this->employment,
        dimonaDeclaration: $this->dimonaDeclaration,
        clientId: 'test-client'
    );

    // We can't easily test the release method directly, so we'll just verify
    // that the job doesn't change the declaration state when the exception is thrown
    $job->handle();

    // Refresh the declaration from the database
    $this->dimonaDeclaration->refresh();

    // Assert the declaration state was not changed
    expect($this->dimonaDeclaration->state)->toBe(DimonaDeclarationState::Waiting);
});

it('releases the job when DimonaServiceIsDown exception is thrown', function () {
    // Set up the mock to throw an exception
    $this->apiClient->mockServiceIsDown('12345');

    // Create a real job instance
    $job = new SyncDimonaDeclaration(
        dimonaDeclarable: $this->employment,
        dimonaDeclaration: $this->dimonaDeclaration,
        clientId: 'test-client'
    );

    // We can't easily test the release method directly, so we'll just verify
    // that the job doesn't change the declaration state when the exception is thrown
    $job->handle();

    // Refresh the declaration from the database
    $this->dimonaDeclaration->refresh();

    // Assert the declaration state was not changed
    expect($this->dimonaDeclaration->state)->toBe(DimonaDeclarationState::Waiting);
});

it('updates declaration state to Failed when RequestException is thrown', function () {
    // Set up the mock to throw the exception
    $this->apiClient->mockRequestException('12345', ['error' => 'test-error']);

    // Create the job and inject mocks
    $job = new SyncDimonaDeclaration(
        dimonaDeclarable: $this->employment,
        dimonaDeclaration: $this->dimonaDeclaration,
        clientId: 'test-client'
    );

    // Call the handle method
    $job->handle();

    // Refresh the declaration from the database
    $this->dimonaDeclaration->refresh();

    // Assert the declaration state was updated correctly
    expect($this->dimonaDeclaration->state)->toBe(DimonaDeclarationState::Failed);

    // Assert the anomalies were updated
    expect($this->dimonaDeclaration->anomalies)->toBe(['error' => 'test-error']);
});
