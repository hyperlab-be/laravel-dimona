<?php

namespace Hyperlab\Dimona\Models;

use Hyperlab\Dimona\Enums\WorkerType;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;

class DimonaWorkerTypeException extends Model
{
    use HasUlids;

    protected $guarded = [];

    protected $casts = [
        'worker_type' => WorkerType::class,
        'starts_at' => 'datetime',
        'ends_at' => 'datetime',
    ];
}
