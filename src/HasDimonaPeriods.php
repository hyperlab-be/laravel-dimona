<?php

namespace Hyperlab\Dimona;

use Hyperlab\Dimona\Models\DimonaPeriod;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

trait HasDimonaPeriods
{
    public function dimona_periods(): BelongsToMany
    {
        return $this->belongsToMany(
            DimonaPeriod::class,
            'dimona_period_employment',
            'employment_id',
            'dimona_period_id'
        );
    }

    public function scopeWhereHasDimonaPeriod($query, DimonaPeriod $dimonaPeriod)
    {
        return $query->whereHas('dimona_periods', fn($q) => $q->where('id', $dimonaPeriod->id));
    }
}
