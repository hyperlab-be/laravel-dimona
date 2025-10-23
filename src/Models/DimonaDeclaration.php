<?php

namespace Hyperlab\Dimona\Models;

use Hyperlab\Dimona\Actions\DimonaDeclaration\UpdateDimonaDeclarationReference;
use Hyperlab\Dimona\Actions\DimonaDeclaration\UpdateDimonaDeclarationState;
use Hyperlab\Dimona\Database\Factories\DimonaDeclarationFactory;
use Hyperlab\Dimona\Enums\DimonaDeclarationState;
use Hyperlab\Dimona\Enums\DimonaDeclarationType;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DimonaDeclaration extends Model
{
    use HasFactory, HasUlids;

    protected $guarded = [];

    protected $casts = [
        'type' => DimonaDeclarationType::class,
        'state' => DimonaDeclarationState::class,
        'payload' => 'json',
        'anomalies' => 'json',
    ];

    public function dimona_period(): BelongsTo
    {
        return $this->belongsTo(DimonaPeriod::class);
    }

    public function anomalies(): DimonaAnomalies
    {
        return new DimonaAnomalies($this->anomalies ?? []);
    }

    public function updateReference(string $reference): self
    {
        return UpdateDimonaDeclarationReference::new()->execute(
            dimonaDeclaration: $this,
            reference: $reference,
        );
    }

    public function updateState(DimonaDeclarationState $state, ?array $anomalies): self
    {
        return UpdateDimonaDeclarationState::new()->execute(
            dimonaDeclaration: $this,
            state: $state,
            anomalies: $anomalies,
        );
    }

    protected static function newFactory(): DimonaDeclarationFactory
    {
        return DimonaDeclarationFactory::new();
    }
}
