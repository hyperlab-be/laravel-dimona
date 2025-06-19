<?php

namespace Hyperlab\Dimona\Actions\DimonaPeriod;

use Hyperlab\Dimona\Employment;
use Hyperlab\Dimona\Enums\DimonaPeriodState;
use Hyperlab\Dimona\Enums\WorkerType;
use Hyperlab\Dimona\Events\DimonaPeriodCreated;
use Hyperlab\Dimona\Models\DimonaPeriod;
use Illuminate\Support\Facades\DB;

class CreateDimonaPeriod
{
    public static function new(): static
    {
        return app(static::class);
    }

    public function execute(Employment $employment, WorkerType $workerType): DimonaPeriod
    {
        return DB::transaction(function () use ($employment, $workerType) {
            $dimonaPeriod = $employment->dimona_periods()->create([
                'worker_type' => $workerType,
                'state' => DimonaPeriodState::New,
            ]);

            event(new DimonaPeriodCreated($dimonaPeriod));

            return $dimonaPeriod;
        });
    }
}
