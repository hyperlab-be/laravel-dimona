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

it('filters employments by specific dimona period', function () {
    $employment1 = Employment::query()->create();
    $employment2 = Employment::query()->create();
    $employment3 = Employment::query()->create();

    $period = DimonaPeriod::factory()->create();
    $employment1->dimona_periods()->attach($period);
    $employment2->dimona_periods()->attach($period);

    $otherPeriod = DimonaPeriod::factory()->create();
    $employment3->dimona_periods()->attach($otherPeriod);

    $results = Employment::query()->whereHasDimonaPeriod($period)->get();

    expect($results)->toHaveCount(2)
        ->and($results->pluck('id')->toArray())->toContain($employment1->id, $employment2->id)
        ->and($results->pluck('id')->toArray())->not->toContain($employment3->id);
});

it('returns empty collection when no employments have the specified dimona period', function () {
    Employment::query()->create();
    Employment::query()->create();

    $period = DimonaPeriod::factory()->create();

    $results = Employment::query()->whereHasDimonaPeriod($period)->get();

    expect($results)->toHaveCount(0);
});

it('can be chained with other query constraints', function () {
    $employment1 = Employment::query()->create(['cancelled' => false]);
    $employment2 = Employment::query()->create(['cancelled' => true]);
    $employment3 = Employment::query()->create(['cancelled' => false]);

    $period = DimonaPeriod::factory()->create();
    $employment1->dimona_periods()->attach($period);
    $employment2->dimona_periods()->attach($period);
    $employment3->dimona_periods()->attach($period);

    $results = Employment::query()
        ->whereHasDimonaPeriod($period)
        ->where('cancelled', false)
        ->get();

    expect($results)->toHaveCount(2)
        ->and($results->pluck('id')->toArray())->toContain($employment1->id, $employment3->id)
        ->and($results->pluck('id')->toArray())->not->toContain($employment2->id);
});

it('works correctly when employment has multiple periods but we filter by one', function () {
    $employment = Employment::query()->create();

    $period1 = DimonaPeriod::factory()->create();
    $period2 = DimonaPeriod::factory()->create();
    $employment->dimona_periods()->attach([$period1->id, $period2->id]);

    $results = Employment::query()->whereHasDimonaPeriod($period1)->get();

    expect($results)->toHaveCount(1)
        ->and($results->first()->id)->toBe($employment->id);
});
