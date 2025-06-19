<?php

namespace Hyperlab\Dimona\Exceptions;

use Exception;

class DimonaClientNotConfigured extends Exception
{
    public function __construct(string $clientId)
    {
        parent::__construct("Dimona client [{$clientId}] is not configured.");
    }
}
