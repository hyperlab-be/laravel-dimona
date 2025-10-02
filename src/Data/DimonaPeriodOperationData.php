<?php

namespace Hyperlab\Dimona\Data;

use Hyperlab\Dimona\Enums\DimonaPeriodOperation;
use Hyperlab\Dimona\Models\DimonaPeriod;

class DimonaPeriodOperationData
{
    public function __construct(
        public DimonaPeriodOperation $type,
        public ?DimonaPeriodData $expected,
        public ?DimonaPeriod $actual,
    ) {}
}
