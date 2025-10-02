<?php

namespace Hyperlab\Dimona\Enums;

enum DimonaPeriodOperation: string
{
    case Create = 'create';
    case Update = 'update';
    case Cancel = 'cancel';
}
