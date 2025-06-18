<?php

namespace Hyperlab\Dimona;

use Hyperlab\Dimona\Models\DimonaPeriod;
use Illuminate\Database\Eloquent\Relations\HasMany;

trait HasDimona
{
    public function dimona_periods(): HasMany
    {
        return $this
            ->hasMany(DimonaPeriod::class, 'model_id')
            ->where('model_type', static::class);
    }
}
