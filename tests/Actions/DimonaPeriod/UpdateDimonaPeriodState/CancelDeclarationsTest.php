<?php

use Hyperlab\Dimona\Actions\DimonaPeriod\UpdateDimonaPeriodState;
use Hyperlab\Dimona\Enums\DimonaDeclarationState;
use Hyperlab\Dimona\Enums\DimonaDeclarationType;
use Hyperlab\Dimona\Enums\DimonaPeriodState;
use Hyperlab\Dimona\Models\DimonaDeclaration;

beforeEach(function () {
    require_once __DIR__.'/Helpers.php';
    $this->dimonaPeriod = createDimonaPeriod();
});

it('sets the state to cancelled when a cancel declaration is accepted', function () {
    DimonaDeclaration::query()->create([
        'dimona_period_id' => $this->dimonaPeriod->id,
        'type' => DimonaDeclarationType::Cancel,
        'state' => DimonaDeclarationState::Accepted,
        'payload' => [],
    ]);

    $result = UpdateDimonaPeriodState::new()->execute($this->dimonaPeriod);

    expect($result->state)->toBe(DimonaPeriodState::Cancelled);
});

it('sets the state to cancelled when a cancel declaration is refused but already cancelled', function () {
    DimonaDeclaration::query()->create([
        'dimona_period_id' => $this->dimonaPeriod->id,
        'type' => DimonaDeclarationType::Cancel,
        'state' => DimonaDeclarationState::Refused,
        'payload' => [],
        'anomalies' => ['00913-355'],
    ]);

    $result = UpdateDimonaPeriodState::new()->execute($this->dimonaPeriod);

    expect($result->state)->toBe(DimonaPeriodState::Cancelled);
});

it('maintains the state when a cancel declaration is refused and not already cancelled', function () {
    DimonaDeclaration::query()->create([
        'dimona_period_id' => $this->dimonaPeriod->id,
        'type' => DimonaDeclarationType::In,
        'state' => DimonaDeclarationState::Accepted,
        'payload' => [],
    ]);

    DimonaDeclaration::query()->create([
        'dimona_period_id' => $this->dimonaPeriod->id,
        'type' => DimonaDeclarationType::Cancel,
        'state' => DimonaDeclarationState::Refused,
        'payload' => [],
        'anomalies' => ['some-other-anomaly'],
    ]);

    $result = UpdateDimonaPeriodState::new()->execute($this->dimonaPeriod);

    expect($result->state)->toBe(DimonaPeriodState::Accepted);
});

it('sets the state to pending when a cancel declaration is pending as the last declaration', function () {
    DimonaDeclaration::query()->create([
        'dimona_period_id' => $this->dimonaPeriod->id,
        'type' => DimonaDeclarationType::In,
        'state' => DimonaDeclarationState::Accepted,
        'payload' => [],
        'created_at' => now()->subDay(),
    ]);

    DimonaDeclaration::query()->create([
        'dimona_period_id' => $this->dimonaPeriod->id,
        'type' => DimonaDeclarationType::Cancel,
        'state' => DimonaDeclarationState::Pending,
        'payload' => [],
        'created_at' => now(),
    ]);

    $result = UpdateDimonaPeriodState::new()->execute($this->dimonaPeriod);

    expect($result->state)->toBe(DimonaPeriodState::Pending);
});

it('sets the state to waiting when a cancel declaration is waiting as the last declaration', function () {
    DimonaDeclaration::query()->create([
        'dimona_period_id' => $this->dimonaPeriod->id,
        'type' => DimonaDeclarationType::In,
        'state' => DimonaDeclarationState::Accepted,
        'payload' => [],
        'created_at' => now()->subDay(),
    ]);

    DimonaDeclaration::query()->create([
        'dimona_period_id' => $this->dimonaPeriod->id,
        'type' => DimonaDeclarationType::Cancel,
        'state' => DimonaDeclarationState::Waiting,
        'payload' => [],
        'created_at' => now(),
    ]);

    $result = UpdateDimonaPeriodState::new()->execute($this->dimonaPeriod);

    expect($result->state)->toBe(DimonaPeriodState::Waiting);
});
