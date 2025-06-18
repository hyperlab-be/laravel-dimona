<?php

namespace Hyperlab\Dimona\Actions\DimonaDeclaration;

use Hyperlab\Dimona\Enums\DimonaDeclarationState;
use Hyperlab\Dimona\Models\DimonaDeclaration;
use Illuminate\Support\Facades\DB;

class UpdateDimonaDeclarationState
{
    public static function new(): static
    {
        return app(static::class);
    }

    public function execute(
        DimonaDeclaration $dimonaDeclaration, DimonaDeclarationState $state, ?array $anomalies
    ): DimonaDeclaration {
        return DB::transaction(function () use ($dimonaDeclaration, $state, $anomalies) {
            $dimonaDeclaration->update([
                'state' => $state,
                'anomalies' => $anomalies,
            ]);

            return $dimonaDeclaration;
        });
    }
}
