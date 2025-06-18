<?php

namespace Hyperlab\Dimona\Enums;

enum DimonaDeclarationType: string
{
    case In = 'in';
    case Update = 'update';
    case Cancel = 'cancel';
}
