<?php

namespace Hyperlab\Dimona\Events;

use Hyperlab\Dimona\Models\DimonaPeriod;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class DimonaPeriodCreated implements ShouldDispatchAfterCommit
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public DimonaPeriod $dimonaPeriod,
    ) {}
}
