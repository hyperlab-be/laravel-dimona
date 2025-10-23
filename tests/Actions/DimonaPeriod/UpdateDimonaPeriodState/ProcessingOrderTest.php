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

it('processes declarations in chronological order', function () {
    DimonaDeclaration::query()->create([
        'dimona_period_id' => $this->dimonaPeriod->id,
        'type' => DimonaDeclarationType::In,
        'state' => DimonaDeclarationState::Accepted,
        'payload' => [],
        'created_at' => now()->subDays(2),
    ]);

    DimonaDeclaration::query()->create([
        'dimona_period_id' => $this->dimonaPeriod->id,
        'type' => DimonaDeclarationType::Cancel,
        'state' => DimonaDeclarationState::Accepted,
        'payload' => [],
        'created_at' => now()->subDay(),
    ]);

    DimonaDeclaration::query()->create([
        'dimona_period_id' => $this->dimonaPeriod->id,
        'type' => DimonaDeclarationType::In,
        'state' => DimonaDeclarationState::Pending,
        'payload' => [],
        'created_at' => now(),
    ]);

    $result = UpdateDimonaPeriodState::new()->execute($this->dimonaPeriod);

    expect($result->state)->toBe(DimonaPeriodState::Pending);
});
