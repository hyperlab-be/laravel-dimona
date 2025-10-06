<?php

namespace Hyperlab\Dimona\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DimonaPeriodEmployment extends Model
{
    public $timestamps = false;

    protected $table = 'dimona_period_employment';

    protected $guarded = [];

    public function dimona_period(): BelongsTo
    {
        return $this->belongsTo(DimonaPeriod::class, 'dimona_period_id');
    }
}
