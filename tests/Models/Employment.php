<?php

namespace Hyperlab\Dimona\Tests\Models;

use Hyperlab\Dimona\HasDimonaPeriods;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;

class Employment extends Model
{
    use HasDimonaPeriods, HasUlids;

    protected $guarded = [];

    protected $casts = [
        'cancelled' => 'boolean',
    ];
}
