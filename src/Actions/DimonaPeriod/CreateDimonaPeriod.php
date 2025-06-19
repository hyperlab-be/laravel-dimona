<?php

namespace Hyperlab\Dimona\Actions\DimonaPeriod;

use Hyperlab\Dimona\DimonaDeclarable;
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

    public function execute(DimonaDeclarable $dimonaDeclarable, WorkerType $workerType): DimonaPeriod
    {
        return DB::transaction(function () use ($dimonaDeclarable, $workerType) {
            $dimonaPeriod = $dimonaDeclarable->dimona_periods()->create([
                'worker_type' => $workerType,
                'state' => DimonaPeriodState::New,
            ]);

            event(new DimonaPeriodCreated($dimonaPeriod));

            return $dimonaPeriod;
        });
    }
}
