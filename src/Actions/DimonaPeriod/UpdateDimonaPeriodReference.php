<?php

namespace Hyperlab\Dimona\Actions\DimonaPeriod;

use Hyperlab\Dimona\Models\DimonaPeriod;
use Illuminate\Support\Facades\DB;

class UpdateDimonaPeriodReference
{
    public static function new(): static
    {
        return app(static::class);
    }

    public function execute(DimonaPeriod $dimonaPeriod, ?string $reference): DimonaPeriod
    {
        return DB::transaction(function () use ($dimonaPeriod, $reference) {
            $dimonaPeriod->update([
                'reference' => $reference,
            ]);

            return $dimonaPeriod;
        });
    }
}
