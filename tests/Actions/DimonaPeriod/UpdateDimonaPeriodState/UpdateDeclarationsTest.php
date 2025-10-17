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

it('maintains the accepted state when an update declaration is accepted', function () {
    DimonaDeclaration::query()->create([
        'dimona_period_id' => $this->dimonaPeriod->id,
        'type' => DimonaDeclarationType::In,
        'state' => DimonaDeclarationState::Accepted,
        'payload' => [],
        'created_at' => now()->subDay(),
    ]);

    DimonaDeclaration::query()->create([
        'dimona_period_id' => $this->dimonaPeriod->id,
        'type' => DimonaDeclarationType::Update,
        'state' => DimonaDeclarationState::Accepted,
        'payload' => [],
        'created_at' => now(),
    ]);

    $result = UpdateDimonaPeriodState::new()->execute($this->dimonaPeriod);

    expect($result->state)->toBe(DimonaPeriodState::Accepted);
});

it('sets the state to pending when an update declaration is pending', function () {
    DimonaDeclaration::query()->create([
        'dimona_period_id' => $this->dimonaPeriod->id,
        'type' => DimonaDeclarationType::In,
        'state' => DimonaDeclarationState::Accepted,
        'payload' => [],
        'created_at' => now()->subDay(),
    ]);

    DimonaDeclaration::query()->create([
        'dimona_period_id' => $this->dimonaPeriod->id,
        'type' => DimonaDeclarationType::Update,
        'state' => DimonaDeclarationState::Pending,
        'payload' => [],
        'created_at' => now(),
    ]);

    $result = UpdateDimonaPeriodState::new()->execute($this->dimonaPeriod);

    expect($result->state)->toBe(DimonaPeriodState::Pending);
});

it('maintains the accepted state when an update declaration is accepted with warning but flexi requirements are met', function () {
    DimonaDeclaration::query()->create([
        'dimona_period_id' => $this->dimonaPeriod->id,
        'type' => DimonaDeclarationType::In,
        'state' => DimonaDeclarationState::Accepted,
        'payload' => [],
        'created_at' => now()->subDay(),
    ]);

    DimonaDeclaration::query()->create([
        'dimona_period_id' => $this->dimonaPeriod->id,
        'type' => DimonaDeclarationType::Update,
        'state' => DimonaDeclarationState::AcceptedWithWarning,
        'payload' => [],
        'anomalies' => ['some-other-anomaly'],
        'created_at' => now(),
    ]);

    $result = UpdateDimonaPeriodState::new()->execute($this->dimonaPeriod);

    expect($result->state)->toBe(DimonaPeriodState::Accepted);
});

it('sets the state to accepted with warning when an update declaration is accepted with warning and flexi requirements are not met', function () {
    DimonaDeclaration::query()->create([
        'dimona_period_id' => $this->dimonaPeriod->id,
        'type' => DimonaDeclarationType::In,
        'state' => DimonaDeclarationState::Accepted,
        'payload' => [],
        'created_at' => now()->subDay(),
    ]);

    DimonaDeclaration::query()->create([
        'dimona_period_id' => $this->dimonaPeriod->id,
        'type' => DimonaDeclarationType::Update,
        'state' => DimonaDeclarationState::AcceptedWithWarning,
        'payload' => [],
        'anomalies' => ['90017-510'],
        'created_at' => now(),
    ]);

    $result = UpdateDimonaPeriodState::new()->execute($this->dimonaPeriod);

    expect($result->state)->toBe(DimonaPeriodState::AcceptedWithWarning);
});

it('sets the state to refused when an update declaration is refused', function () {
    DimonaDeclaration::query()->create([
        'dimona_period_id' => $this->dimonaPeriod->id,
        'type' => DimonaDeclarationType::In,
        'state' => DimonaDeclarationState::Accepted,
        'payload' => [],
        'created_at' => now()->subDay(),
    ]);

    DimonaDeclaration::query()->create([
        'dimona_period_id' => $this->dimonaPeriod->id,
        'type' => DimonaDeclarationType::Update,
        'state' => DimonaDeclarationState::Refused,
        'payload' => [],
        'created_at' => now(),
    ]);

    $result = UpdateDimonaPeriodState::new()->execute($this->dimonaPeriod);

    expect($result->state)->toBe(DimonaPeriodState::Refused);
})->note('Is this the correct behavior?');

it('sets the state to waiting when an update declaration is waiting', function () {
    DimonaDeclaration::query()->create([
        'dimona_period_id' => $this->dimonaPeriod->id,
        'type' => DimonaDeclarationType::In,
        'state' => DimonaDeclarationState::Accepted,
        'payload' => [],
        'created_at' => now()->subDay(),
    ]);

    DimonaDeclaration::query()->create([
        'dimona_period_id' => $this->dimonaPeriod->id,
        'type' => DimonaDeclarationType::Update,
        'state' => DimonaDeclarationState::Waiting,
        'payload' => [],
        'created_at' => now(),
    ]);

    $result = UpdateDimonaPeriodState::new()->execute($this->dimonaPeriod);

    expect($result->state)->toBe(DimonaPeriodState::Waiting);
});

it('sets the state to failed when an update declaration is failed', function () {
    DimonaDeclaration::query()->create([
        'dimona_period_id' => $this->dimonaPeriod->id,
        'type' => DimonaDeclarationType::In,
        'state' => DimonaDeclarationState::Accepted,
        'payload' => [],
        'created_at' => now()->subDay(),
    ]);

    DimonaDeclaration::query()->create([
        'dimona_period_id' => $this->dimonaPeriod->id,
        'type' => DimonaDeclarationType::Update,
        'state' => DimonaDeclarationState::Failed,
        'payload' => [],
        'created_at' => now(),
    ]);

    $result = UpdateDimonaPeriodState::new()->execute($this->dimonaPeriod);

    expect($result->state)->toBe(DimonaPeriodState::Failed);
})->note('Is this the correct behavior?');
