<?php

namespace Hyperlab\Dimona\Models;

use Hyperlab\Dimona\Actions\DimonaDeclaration\CreateDimonaDeclaration;
use Hyperlab\Dimona\Actions\DimonaPeriod\UpdateDimonaPeriodReference;
use Hyperlab\Dimona\Actions\DimonaPeriod\UpdateDimonaPeriodState;
use Hyperlab\Dimona\Database\Factories\DimonaPeriodFactory;
use Hyperlab\Dimona\Enums\DimonaDeclarationType;
use Hyperlab\Dimona\Enums\DimonaPeriodState;
use Hyperlab\Dimona\Enums\EmploymentLocationCountry;
use Hyperlab\Dimona\Enums\WorkerType;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class DimonaPeriod extends Model
{
    use HasFactory, HasUlids;

    protected $guarded = [];

    protected $casts = [
        'worker_type' => WorkerType::class,
        'joint_commission_number' => 'integer',
        'number_of_hours' => 'float',
        'state' => DimonaPeriodState::class,
        'location_country' => EmploymentLocationCountry::class,
    ];

    public function dimona_declarations(): HasMany
    {
        return $this->hasMany(DimonaDeclaration::class);
    }

    public function dimona_period_employments(): HasMany
    {
        return $this->hasMany(DimonaPeriodEmployment::class, 'dimona_period_id');
    }

    public function updateState(): self
    {
        return UpdateDimonaPeriodState::new()->execute(
            dimonaPeriod: $this
        );
    }

    public function updateReference(?string $reference): self
    {
        return UpdateDimonaPeriodReference::new()->execute(
            dimonaPeriod: $this,
            reference: $reference,
        );
    }

    public function createDimonaDeclaration(DimonaDeclarationType $type, array $payload): DimonaDeclaration
    {
        return CreateDimonaDeclaration::new()->execute(
            dimonaPeriod: $this,
            type: $type,
            payload: $payload
        );
    }

    protected static function newFactory(): DimonaPeriodFactory
    {
        return DimonaPeriodFactory::new();
    }
}
