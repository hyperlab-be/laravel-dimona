<?php

use Hyperlab\Dimona\Actions\DimonaPeriod\UpdateDimonaPeriodState;
use Hyperlab\Dimona\Enums\DimonaDeclarationState;
use Hyperlab\Dimona\Enums\DimonaDeclarationType;
use Hyperlab\Dimona\Enums\DimonaPeriodState;
use Hyperlab\Dimona\Events\DimonaPeriodStateUpdated;
use Hyperlab\Dimona\Models\DimonaDeclaration;
use Illuminate\Support\Facades\Event;

beforeEach(function () {
    require_once __DIR__.'/Helpers.php';
    $this->dimonaPeriod = createDimonaPeriod();
});

it('fires DimonaPeriodStateUpdated event when state changes', function () {
    Event::fake();

    DimonaDeclaration::query()->create([
        'dimona_period_id' => $this->dimonaPeriod->id,
        'type' => DimonaDeclarationType::In,
        'state' => DimonaDeclarationState::Accepted,
        'payload' => [],
    ]);

    UpdateDimonaPeriodState::new()->execute($this->dimonaPeriod);

    Event::assertDispatched(DimonaPeriodStateUpdated::class, function ($event) {
        return $event->dimonaPeriod->id === $this->dimonaPeriod->id;
    });
});

it('does not fire events when state remains the same', function () {
    $this->dimonaPeriod->update(['state' => DimonaPeriodState::Accepted]);

    DimonaDeclaration::query()->create([
        'dimona_period_id' => $this->dimonaPeriod->id,
        'type' => DimonaDeclarationType::In,
        'state' => DimonaDeclarationState::Accepted,
        'payload' => [],
    ]);

    Event::fake();

    UpdateDimonaPeriodState::new()->execute($this->dimonaPeriod);

    Event::assertNotDispatched(DimonaPeriodStateUpdated::class);
});
