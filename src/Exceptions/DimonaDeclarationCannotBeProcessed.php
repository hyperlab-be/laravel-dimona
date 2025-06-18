<?php

namespace Hyperlab\Dimona\Exceptions;

use Exception;
use Illuminate\Http\Client\RequestException;

class DimonaDeclarationCannotBeProcessed extends Exception
{
    public function __construct(
        public RequestException $exception,
    ) {
        parent::__construct('The Dimona declaration cannot be processed.', 500, $this->exception);
    }
}
