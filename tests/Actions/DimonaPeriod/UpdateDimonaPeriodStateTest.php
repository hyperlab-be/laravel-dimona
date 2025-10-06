<?php

use Carbon\CarbonImmutable;
use Hyperlab\Dimona\Actions\DimonaPeriod\UpdateDimonaPeriodState;
use Hyperlab\Dimona\Enums\DimonaDeclarationState;
use Hyperlab\Dimona\Enums\DimonaDeclarationType;
use Hyperlab\Dimona\Enums\DimonaPeriodState;
use Hyperlab\Dimona\Enums\WorkerType;
use Hyperlab\Dimona\Events\DimonaPeriodStateUpdated;
use Hyperlab\Dimona\Models\DimonaDeclaration;
use Hyperlab\Dimona\Models\DimonaPeriod;
use Illuminate\Support\Facades\Event;

beforeEach(function () {
    $this->dimonaPeriod = DimonaPeriod::query()->create([
        'employer_enterprise_number' => '0123456789',
        'worker_social_security_number' => '12345678901',
        'joint_commission_number' => 200,
        'worker_type' => WorkerType::Student,
        'starts_at' => CarbonImmutable::parse('2025-10-01 08:00'),
        'ends_at' => CarbonImmutable::parse('2025-10-01 17:00'),
        'state' => DimonaPeriodState::New,
    ]);
});

it('returns the dimona period unchanged when no state is updated', function () {
    // Execute the action
    $result = UpdateDimonaPeriodState::new()->execute($this->dimonaPeriod);

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
    $result = UpdateDimonaPeriodState::new()->execute($this->dimonaPeriod);

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
    $result = UpdateDimonaPeriodState::new()->execute($this->dimonaPeriod);

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
    $result = UpdateDimonaPeriodState::new()->execute($this->dimonaPeriod);

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
    $result = UpdateDimonaPeriodState::new()->execute($this->dimonaPeriod);

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
    $result = UpdateDimonaPeriodState::new()->execute($this->dimonaPeriod);

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
    $result = UpdateDimonaPeriodState::new()->execute($this->dimonaPeriod);

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
    $result = UpdateDimonaPeriodState::new()->execute($this->dimonaPeriod);

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
    $result = UpdateDimonaPeriodState::new()->execute($this->dimonaPeriod);

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
    $result = UpdateDimonaPeriodState::new()->execute($this->dimonaPeriod);

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
    $result = UpdateDimonaPeriodState::new()->execute($this->dimonaPeriod);

    // Assert that the state is pending (because the last declaration is pending)
    expect($result->state)->toBe(DimonaPeriodState::Pending);
});

it('fires DimonaPeriodStateUpdated event when state changes', function () {
    // Fake events
    Event::fake();

    // Create an accepted in declaration
    DimonaDeclaration::query()->create([
        'dimona_period_id' => $this->dimonaPeriod->id,
        'type' => DimonaDeclarationType::In,
        'state' => DimonaDeclarationState::Accepted,
        'payload' => [],
    ]);

    // Execute the action
    UpdateDimonaPeriodState::new()->execute($this->dimonaPeriod);

    // Assert that the DimonaPeriodStateUpdated event was dispatched
    Event::assertDispatched(DimonaPeriodStateUpdated::class, function ($event) {
        return $event->dimonaPeriod->id === $this->dimonaPeriod->id;
    });
});

it('does not fire events when state remains the same', function () {
    // First change the state to Accepted
    $this->dimonaPeriod->update(['state' => DimonaPeriodState::Accepted]);

    // Create an accepted in declaration
    DimonaDeclaration::query()->create([
        'dimona_period_id' => $this->dimonaPeriod->id,
        'type' => DimonaDeclarationType::In,
        'state' => DimonaDeclarationState::Accepted,
        'payload' => [],
    ]);

    // Fake events
    Event::fake();

    // Execute the action again (state should remain Accepted)
    UpdateDimonaPeriodState::new()->execute($this->dimonaPeriod);

    // Assert that no events were dispatched
    Event::assertNotDispatched(DimonaPeriodStateUpdated::class);
});
