<?php

namespace Hyperlab\Dimona;

use Hyperlab\Dimona\Models\DimonaPeriod;
use Illuminate\Database\Eloquent\Relations\MorphMany;

trait HasDimona
{
    public function dimona_periods(): MorphMany
    {
        return $this->morphMany(DimonaPeriod::class, 'model');
    }
}
