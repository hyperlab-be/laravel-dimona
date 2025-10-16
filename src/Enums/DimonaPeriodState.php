<?php

namespace Hyperlab\Dimona\Enums;

enum DimonaPeriodState: string
{
    case New = 'new';
    case Outdated = 'outdated';
    case Pending = 'pending';
    case Accepted = 'accepted';
    case AcceptedWithWarning = 'accepted_with_warning';
    case Refused = 'refused';
    case Waiting = 'waiting';
    case Cancelled = 'cancelled';
    case Failed = 'failed';
}
