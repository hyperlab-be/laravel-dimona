<?php

namespace Hyperlab\Dimona\Enums;

enum DimonaPeriodOperation: string
{
    case Create = 'create';
    case Update = 'update';
    case Link = 'link';
    case Cancel = 'cancel';
}
