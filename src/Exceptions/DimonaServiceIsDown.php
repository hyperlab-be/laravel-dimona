<?php

namespace Hyperlab\Dimona\Exceptions;

use Exception;
use Illuminate\Http\Client\RequestException;

class DimonaServiceIsDown extends Exception
{
    public function __construct(RequestException $exception)
    {
        parent::__construct('The Dimona service is down.', 500, $exception);
    }
}
