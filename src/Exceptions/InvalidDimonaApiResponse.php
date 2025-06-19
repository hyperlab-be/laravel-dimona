<?php

namespace Hyperlab\Dimona\Exceptions;

use Exception;

class InvalidDimonaApiResponse extends Exception
{
    public function __construct()
    {
        parent::__construct('Invalid Dimona API response: missing reference', 500);
    }
}
