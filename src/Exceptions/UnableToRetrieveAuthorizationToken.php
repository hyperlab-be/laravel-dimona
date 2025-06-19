<?php

namespace Hyperlab\Dimona\Exceptions;

use Exception;

class UnableToRetrieveAuthorizationToken extends Exception
{
    public function __construct()
    {
        parent::__construct('Unable to retrieve authorization token', 500);
    }
}
