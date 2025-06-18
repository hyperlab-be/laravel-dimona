<?php

namespace Hyperlab\Dimona\Actions\DimonaDeclaration;

use Hyperlab\Dimona\Models\DimonaDeclaration;
use Illuminate\Support\Facades\DB;

class UpdateDimonaDeclarationReference
{
    public static function new(): static
    {
        return app(static::class);
    }

    public function execute(DimonaDeclaration $dimonaDeclaration, string $reference): DimonaDeclaration
    {
        return DB::transaction(function () use ($dimonaDeclaration, $reference) {
            $dimonaDeclaration->update([
                'reference' => $reference,
            ]);

            return $dimonaDeclaration;
        });
    }
}
