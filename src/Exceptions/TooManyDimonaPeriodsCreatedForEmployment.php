<?php

namespace Hyperlab\Dimona\Exceptions;

use Exception;

class TooManyDimonaPeriodsCreatedForEmployment extends Exception
{
    public function __construct()
    {
        parent::__construct('Too many dimona periods created for employment.', 500);
    }
}
