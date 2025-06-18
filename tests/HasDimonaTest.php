<?php

use Hyperlab\Dimona\Models\DimonaPeriod;
use Hyperlab\Dimona\Tests\Models\TestEmployment;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('has a relationship to dimona periods', function () {
    $employment = TestEmployment::query()->create();
    $relationship = $employment->dimona_periods();

    expect($relationship)->toBeInstanceOf(HasMany::class);
    expect($relationship->getRelated())->toBeInstanceOf(DimonaPeriod::class);
    expect($relationship->getLocalKeyName())->toBe('id');
    expect($relationship->getForeignKeyName())->toBe('model_id');
});

it('only returns dimona periods for the current model type', function () {
    $employment = TestEmployment::query()->create();

    // Create a DimonaPeriod for this employment
    $period = DimonaPeriod::query()->create([
        'model_id' => $employment->id,
        'model_type' => TestEmployment::class,
        'state' => 'pending',
    ]);

    // Create a DimonaPeriod for a different model type
    $otherPeriod = DimonaPeriod::query()->create([
        'model_id' => $employment->id,
        'model_type' => 'OtherModelType',
        'state' => 'pending',
    ]);

    $periods = $employment->dimona_periods;

    expect($periods)->toHaveCount(1);
    expect($periods->first()->id)->toBe($period->id);
});
