<?php

namespace Hyperlab\Dimona\Exceptions;

use Exception;

class TooManyDimonaPeriodsCreated extends Exception
{
    public function __construct()
    {
        parent::__construct('Too many dimona periods created.', 500);
    }
}
