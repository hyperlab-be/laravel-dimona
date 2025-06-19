<?php

namespace Hyperlab\Dimona\Exceptions;

use Exception;
use Illuminate\Http\Client\RequestException;

class InvalidDimonaApiRequest extends Exception
{
    public function __construct(RequestException $exception)
    {
        parent::__construct('Invalid Dimona API request.', 500, $exception);
    }
}
