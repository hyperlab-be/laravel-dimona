<?php

use Hyperlab\Dimona\Models\DimonaDeclaration;
use Hyperlab\Dimona\Tests\Models\Employment;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;

it('has a morphTo relationship to a model', function () {
    $employment = Employment::query()->create();

    $dimonaPeriod = $employment->dimona_periods()->create([
        'state' => 'pending',
    ]);

    $relationship = $dimonaPeriod->model();

    expect($relationship)->toBeInstanceOf(MorphTo::class);
    expect($relationship->getMorphType())->toBe('model_type');
    expect($relationship->getForeignKeyName())->toBe('model_id');

    // Test that the relationship returns the correct model
    expect($dimonaPeriod->model)->toBeInstanceOf(Employment::class);
    expect($dimonaPeriod->model->id)->toBe($employment->id);
});

it('has a hasMany relationship to dimona declarations', function () {
    $employment = Employment::query()->create();

    $dimonaPeriod = $employment->dimona_periods()->create([
        'state' => 'pending',
    ]);

    // Create a DimonaDeclaration for this period
    $declaration = DimonaDeclaration::query()->create([
        'dimona_period_id' => $dimonaPeriod->id,
        'type' => 'in',
        'state' => 'pending',
        'payload' => ['test' => 'data'],
    ]);

    $relationship = $dimonaPeriod->dimona_declarations();

    expect($relationship)->toBeInstanceOf(HasMany::class);
    expect($relationship->getRelated())->toBeInstanceOf(DimonaDeclaration::class);
    expect($relationship->getForeignKeyName())->toBe('dimona_period_id');

    // Test that the relationship returns the correct declarations
    expect($dimonaPeriod->dimona_declarations)->toHaveCount(1);
    expect($dimonaPeriod->dimona_declarations->first()->id)->toBe($declaration->id);
});
