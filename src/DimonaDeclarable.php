<?php

namespace Hyperlab\Dimona;

use Hyperlab\Dimona\Data\DimonaData;
use Illuminate\Database\Eloquent\Relations\MorphMany;

interface DimonaDeclarable
{
    public function dimona_periods(): MorphMany;

    public function shouldDeclareDimona(): bool;

    public function getDimonaData(): DimonaData;
}
