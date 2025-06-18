<?php

namespace Hyperlab\Dimona\Actions\DimonaDeclaration;

use Hyperlab\Dimona\Enums\DimonaDeclarationState;
use Hyperlab\Dimona\Enums\DimonaDeclarationType;
use Hyperlab\Dimona\Models\DimonaDeclaration;
use Hyperlab\Dimona\Models\DimonaPeriod;
use Illuminate\Support\Facades\DB;

use function app;

class CreateDimonaDeclaration
{
    public static function new(): static
    {
        return app(static::class);
    }

    public function execute(DimonaPeriod $dimonaPeriod, DimonaDeclarationType $type, array $payload): DimonaDeclaration
    {
        return DB::transaction(function () use ($dimonaPeriod, $type, $payload) {
            return $dimonaPeriod->dimona_declarations()->create([
                'type' => $type,
                'state' => DimonaDeclarationState::Pending,
                'reference' => null,
                'payload' => $payload,
            ]);
        });
    }
}
