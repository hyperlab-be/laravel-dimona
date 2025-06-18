<?php

namespace Hyperlab\Dimona;

use Hyperlab\Dimona\Data\DimonaData;
use Illuminate\Database\Eloquent\Relations\HasMany;

interface Employment
{
    public function dimona_periods(): HasMany;

    public function shouldDeclareDimona(): bool;

    public function getDimonaData(): DimonaData;
}
