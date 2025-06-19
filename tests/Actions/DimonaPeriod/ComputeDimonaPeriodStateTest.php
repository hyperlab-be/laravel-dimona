<?php

use Hyperlab\Dimona\Actions\DimonaPeriod\ComputeDimonaPeriodState;
use Hyperlab\Dimona\Enums\DimonaDeclarationState;
use Hyperlab\Dimona\Enums\DimonaDeclarationType;
use Hyperlab\Dimona\Enums\DimonaPeriodState;
use Hyperlab\Dimona\Models\DimonaDeclaration;
use Hyperlab\Dimona\Models\DimonaPeriod;
use Hyperlab\Dimona\Tests\Models\TestEmployment;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    // Create a test employment
    $this->employment = TestEmployment::query()->create();

    // Create a dimona period
    $this->dimonaPeriod = DimonaPeriod::query()->create([
        'model_id' => $this->employment->id,
        'model_type' => TestEmployment::class,
        'state' => DimonaPeriodState::New,
    ]);
});

it('returns the dimona period unchanged when no state is computed', function () {
    // Execute the action
    $result = ComputeDimonaPeriodState::new()->execute($this->dimonaPeriod);

    // Assert that the dimona period is returned unchanged
    expect($result->id)->toBe($this->dimonaPeriod->id)
        ->and($result->state)->toBe(DimonaPeriodState::New);
});

it('sets the state to pending when the last declaration is pending', function () {
    // Create a pending declaration
    DimonaDeclaration::query()->create([
        'dimona_period_id' => $this->dimonaPeriod->id,
        'type' => DimonaDeclarationType::In,
        'state' => DimonaDeclarationState::Pending,
        'payload' => [],
    ]);

    // Execute the action
    $result = ComputeDimonaPeriodState::new()->execute($this->dimonaPeriod);

    // Assert that the state is set to pending
    expect($result->state)->toBe(DimonaPeriodState::Pending);
});

it('sets the state to accepted when an in declaration is accepted', function () {
    // Create an accepted in declaration
    DimonaDeclaration::query()->create([
        'dimona_period_id' => $this->dimonaPeriod->id,
        'type' => DimonaDeclarationType::In,
        'state' => DimonaDeclarationState::Accepted,
        'payload' => [],
    ]);

    // Execute the action
    $result = ComputeDimonaPeriodState::new()->execute($this->dimonaPeriod);

    // Assert that the state is set to accepted
    expect($result->state)->toBe(DimonaPeriodState::Accepted);
});

it('sets the state to accepted with warning when an in declaration is accepted with warning and flexi requirements are not met', function () {
    // Create a mock DimonaDeclaration
    $declaration = DimonaDeclaration::query()->create([
        'dimona_period_id' => $this->dimonaPeriod->id,
        'type' => DimonaDeclarationType::In,
        'state' => DimonaDeclarationState::AcceptedWithWarning,
        'payload' => [],
        'anomalies' => ['90017-510'], // Flexi requirements not met
    ]);

    // Execute the action
    $result = ComputeDimonaPeriodState::new()->execute($this->dimonaPeriod);

    // Assert that the state is set to accepted with warning
    expect($result->state)->toBe(DimonaPeriodState::AcceptedWithWarning);
});

it('sets the state to accepted when an in declaration is accepted with warning but flexi requirements are met', function () {
    // Create a mock DimonaDeclaration
    $declaration = DimonaDeclaration::query()->create([
        'dimona_period_id' => $this->dimonaPeriod->id,
        'type' => DimonaDeclarationType::In,
        'state' => DimonaDeclarationState::AcceptedWithWarning,
        'payload' => [],
        'anomalies' => ['some-other-anomaly'], // Not flexi requirements
    ]);

    // Execute the action
    $result = ComputeDimonaPeriodState::new()->execute($this->dimonaPeriod);

    // Assert that the state is set to accepted
    expect($result->state)->toBe(DimonaPeriodState::Accepted);
});

it('sets the state to refused when an in declaration is refused', function () {
    // Create a refused in declaration
    DimonaDeclaration::query()->create([
        'dimona_period_id' => $this->dimonaPeriod->id,
        'type' => DimonaDeclarationType::In,
        'state' => DimonaDeclarationState::Refused,
        'payload' => [],
    ]);

    // Execute the action
    $result = ComputeDimonaPeriodState::new()->execute($this->dimonaPeriod);

    // Assert that the state is set to refused
    expect($result->state)->toBe(DimonaPeriodState::Refused);
});

it('sets the state to waiting when an in declaration is waiting', function () {
    // Create a waiting in declaration
    DimonaDeclaration::query()->create([
        'dimona_period_id' => $this->dimonaPeriod->id,
        'type' => DimonaDeclarationType::In,
        'state' => DimonaDeclarationState::Waiting,
        'payload' => [],
    ]);

    // Execute the action
    $result = ComputeDimonaPeriodState::new()->execute($this->dimonaPeriod);

    // Assert that the state is set to waiting
    expect($result->state)->toBe(DimonaPeriodState::Waiting);
});

it('sets the state to cancelled when a cancel declaration is accepted', function () {
    // Create an accepted cancel declaration
    DimonaDeclaration::query()->create([
        'dimona_period_id' => $this->dimonaPeriod->id,
        'type' => DimonaDeclarationType::Cancel,
        'state' => DimonaDeclarationState::Accepted,
        'payload' => [],
    ]);

    // Execute the action
    $result = ComputeDimonaPeriodState::new()->execute($this->dimonaPeriod);

    // Assert that the state is set to cancelled
    expect($result->state)->toBe(DimonaPeriodState::Cancelled);
});

it('sets the state to cancelled when a cancel declaration is refused but already cancelled', function () {
    // Create a refused cancel declaration with already cancelled anomaly
    DimonaDeclaration::query()->create([
        'dimona_period_id' => $this->dimonaPeriod->id,
        'type' => DimonaDeclarationType::Cancel,
        'state' => DimonaDeclarationState::Refused,
        'payload' => [],
        'anomalies' => ['00913-355'], // Already cancelled
    ]);

    // Execute the action
    $result = ComputeDimonaPeriodState::new()->execute($this->dimonaPeriod);

    // Assert that the state is set to cancelled
    expect($result->state)->toBe(DimonaPeriodState::Cancelled);
});

it('maintains the state when a cancel declaration is refused and not already cancelled', function () {
    // Create an accepted in declaration
    DimonaDeclaration::query()->create([
        'dimona_period_id' => $this->dimonaPeriod->id,
        'type' => DimonaDeclarationType::In,
        'state' => DimonaDeclarationState::Accepted,
        'payload' => [],
    ]);

    // Create a refused cancel declaration without already cancelled anomaly
    DimonaDeclaration::query()->create([
        'dimona_period_id' => $this->dimonaPeriod->id,
        'type' => DimonaDeclarationType::Cancel,
        'state' => DimonaDeclarationState::Refused,
        'payload' => [],
        'anomalies' => ['some-other-anomaly'], // Not already cancelled
    ]);

    // Execute the action
    $result = ComputeDimonaPeriodState::new()->execute($this->dimonaPeriod);

    // Assert that the state is still accepted
    expect($result->state)->toBe(DimonaPeriodState::Accepted);
});

it('processes declarations in chronological order', function () {
    // Create an accepted in declaration
    DimonaDeclaration::query()->create([
        'dimona_period_id' => $this->dimonaPeriod->id,
        'type' => DimonaDeclarationType::In,
        'state' => DimonaDeclarationState::Accepted,
        'payload' => [],
        'created_at' => now()->subDays(2),
    ]);

    // Create an accepted cancel declaration
    DimonaDeclaration::query()->create([
        'dimona_period_id' => $this->dimonaPeriod->id,
        'type' => DimonaDeclarationType::Cancel,
        'state' => DimonaDeclarationState::Accepted,
        'payload' => [],
        'created_at' => now()->subDay(),
    ]);

    // Create a pending in declaration
    DimonaDeclaration::query()->create([
        'dimona_period_id' => $this->dimonaPeriod->id,
        'type' => DimonaDeclarationType::In,
        'state' => DimonaDeclarationState::Pending,
        'payload' => [],
        'created_at' => now(),
    ]);

    // Execute the action
    $result = ComputeDimonaPeriodState::new()->execute($this->dimonaPeriod);

    // Assert that the state is pending (because the last declaration is pending)
    expect($result->state)->toBe(DimonaPeriodState::Pending);
});
