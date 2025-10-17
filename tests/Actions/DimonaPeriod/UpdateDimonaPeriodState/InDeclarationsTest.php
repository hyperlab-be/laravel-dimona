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

it('returns the dimona period unchanged when no state is updated', function () {
    $result = UpdateDimonaPeriodState::new()->execute($this->dimonaPeriod);

    expect($result->id)->toBe($this->dimonaPeriod->id)
        ->and($result->state)->toBe(DimonaPeriodState::New);
});

it('sets the state to pending when the last declaration is pending', function () {
    DimonaDeclaration::query()->create([
        'dimona_period_id' => $this->dimonaPeriod->id,
        'type' => DimonaDeclarationType::In,
        'state' => DimonaDeclarationState::Pending,
        'payload' => [],
    ]);

    $result = UpdateDimonaPeriodState::new()->execute($this->dimonaPeriod);

    expect($result->state)->toBe(DimonaPeriodState::Pending);
});

it('sets the state to accepted when an in declaration is accepted', function () {
    DimonaDeclaration::query()->create([
        'dimona_period_id' => $this->dimonaPeriod->id,
        'type' => DimonaDeclarationType::In,
        'state' => DimonaDeclarationState::Accepted,
        'payload' => [],
    ]);

    $result = UpdateDimonaPeriodState::new()->execute($this->dimonaPeriod);

    expect($result->state)->toBe(DimonaPeriodState::Accepted);
});

it('sets the state to accepted with warning when an in declaration is accepted with warning and flexi requirements are not met', function () {
    DimonaDeclaration::query()->create([
        'dimona_period_id' => $this->dimonaPeriod->id,
        'type' => DimonaDeclarationType::In,
        'state' => DimonaDeclarationState::AcceptedWithWarning,
        'payload' => [],
        'anomalies' => ['90017-510'],
    ]);

    $result = UpdateDimonaPeriodState::new()->execute($this->dimonaPeriod);

    expect($result->state)->toBe(DimonaPeriodState::AcceptedWithWarning);
});

it('sets the state to accepted when an in declaration is accepted with warning but flexi requirements are met', function () {
    DimonaDeclaration::query()->create([
        'dimona_period_id' => $this->dimonaPeriod->id,
        'type' => DimonaDeclarationType::In,
        'state' => DimonaDeclarationState::AcceptedWithWarning,
        'payload' => [],
        'anomalies' => ['some-other-anomaly'],
    ]);

    $result = UpdateDimonaPeriodState::new()->execute($this->dimonaPeriod);

    expect($result->state)->toBe(DimonaPeriodState::Accepted);
});

it('sets the state to refused when an in declaration is refused', function () {
    DimonaDeclaration::query()->create([
        'dimona_period_id' => $this->dimonaPeriod->id,
        'type' => DimonaDeclarationType::In,
        'state' => DimonaDeclarationState::Refused,
        'payload' => [],
    ]);

    $result = UpdateDimonaPeriodState::new()->execute($this->dimonaPeriod);

    expect($result->state)->toBe(DimonaPeriodState::Refused);
});

it('sets the state to waiting when an in declaration is waiting', function () {
    DimonaDeclaration::query()->create([
        'dimona_period_id' => $this->dimonaPeriod->id,
        'type' => DimonaDeclarationType::In,
        'state' => DimonaDeclarationState::Waiting,
        'payload' => [],
    ]);

    $result = UpdateDimonaPeriodState::new()->execute($this->dimonaPeriod);

    expect($result->state)->toBe(DimonaPeriodState::Waiting);
});

it('sets the state to failed when an in declaration is failed', function () {
    DimonaDeclaration::query()->create([
        'dimona_period_id' => $this->dimonaPeriod->id,
        'type' => DimonaDeclarationType::In,
        'state' => DimonaDeclarationState::Failed,
        'payload' => [],
    ]);

    $result = UpdateDimonaPeriodState::new()->execute($this->dimonaPeriod);

    expect($result->state)->toBe(DimonaPeriodState::Failed);
});

it('sets the state to accepted with warning when an in declaration has multiple anomalies including flexi requirements', function () {
    DimonaDeclaration::query()->create([
        'dimona_period_id' => $this->dimonaPeriod->id,
        'type' => DimonaDeclarationType::In,
        'state' => DimonaDeclarationState::AcceptedWithWarning,
        'payload' => [],
        'anomalies' => ['90017-510', 'some-other-anomaly'],
    ]);

    $result = UpdateDimonaPeriodState::new()->execute($this->dimonaPeriod);

    expect($result->state)->toBe(DimonaPeriodState::AcceptedWithWarning);
});

it('sets the state to accepted when an in declaration has student requirements not met anomaly', function () {
    DimonaDeclaration::query()->create([
        'dimona_period_id' => $this->dimonaPeriod->id,
        'type' => DimonaDeclarationType::In,
        'state' => DimonaDeclarationState::AcceptedWithWarning,
        'payload' => [],
        'anomalies' => ['90017-369'],
    ]);

    $result = UpdateDimonaPeriodState::new()->execute($this->dimonaPeriod);

    expect($result->state)->toBe(DimonaPeriodState::Accepted);
});
