<?php

namespace Hyperlab\Dimona\Enums;

enum DimonaDeclarationState: string
{
    case Pending = 'pending';
    case Accepted = 'accepted';
    case AcceptedWithWarning = 'accepted_with_warning';
    case Refused = 'refused';
    case Waiting = 'waiting';
    case Failed = 'failed';
}
