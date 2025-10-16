<?php

namespace Hyperlab\Dimona\Database\Factories;

use Hyperlab\Dimona\Enums\DimonaDeclarationState;
use Hyperlab\Dimona\Enums\DimonaDeclarationType;
use Hyperlab\Dimona\Models\DimonaDeclaration;
use Hyperlab\Dimona\Models\DimonaPeriod;
use Illuminate\Database\Eloquent\Factories\Factory;

class DimonaDeclarationFactory extends Factory
{
    protected $model = DimonaDeclaration::class;

    public function definition(): array
    {
        return [
            'dimona_period_id' => DimonaPeriod::factory(),
            'type' => DimonaDeclarationType::In,
            'state' => DimonaDeclarationState::Pending,
            'payload' => [],
            'reference' => 'declaration-ref-1',
        ];
    }
}
