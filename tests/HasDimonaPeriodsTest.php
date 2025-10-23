<?php

use Hyperlab\Dimona\Models\DimonaPeriod;
use Hyperlab\Dimona\Tests\Models\Employment;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

it('has a relationship to dimona periods', function () {
    $employment = Employment::query()->create();
    $relationship = $employment->dimona_periods();

    expect($relationship)->toBeInstanceOf(BelongsToMany::class)
        ->and($relationship->getRelated())->toBeInstanceOf(DimonaPeriod::class);
});

it('only returns dimona periods that are attached via pivot table', function () {
    $employment = Employment::query()->create();
    $otherEmployment = Employment::query()->create();

    // Create a DimonaPeriod that includes this employment
    $period = DimonaPeriod::factory()->create();
    $employment->dimona_periods()->attach($period);

    // Create a DimonaPeriod that includes both employments
    $sharedPeriod = DimonaPeriod::factory()->create();
    $employment->dimona_periods()->attach($sharedPeriod);
    $otherEmployment->dimona_periods()->attach($sharedPeriod);

    // Create a DimonaPeriod for a different employment
    $otherPeriod = DimonaPeriod::factory()->create();
    $otherEmployment->dimona_periods()->attach($otherPeriod);

    $periods = $employment->dimona_periods;

    expect($periods)->toHaveCount(2)
        ->and($periods->pluck('id')->toArray())->toContain($period->id, $sharedPeriod->id)
        ->and($periods->pluck('id')->toArray())->not->toContain($otherPeriod->id);
});
