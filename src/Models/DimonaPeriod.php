<?php

namespace Hyperlab\Dimona\Models;

use Hyperlab\Dimona\Actions\DimonaDeclaration\CreateDimonaDeclaration;
use Hyperlab\Dimona\Actions\DimonaPeriod\UpdateDimonaPeriodReference;
use Hyperlab\Dimona\Actions\DimonaPeriod\UpdateDimonaPeriodState;
use Hyperlab\Dimona\Enums\DimonaDeclarationType;
use Hyperlab\Dimona\Enums\DimonaPeriodState;
use Hyperlab\Dimona\Enums\WorkerType;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class DimonaPeriod extends Model
{
    use HasUlids;

    protected $guarded = [];

    protected $casts = [
        'worker_type' => WorkerType::class,
        'state' => DimonaPeriodState::class,
    ];

    public function model(): MorphTo
    {
        return $this->morphTo();
    }

    public function dimona_declarations(): HasMany
    {
        return $this->hasMany(DimonaDeclaration::class);
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
}
