<?php

namespace Hyperlab\Dimona\Exceptions;

use Exception;
use Illuminate\Http\Client\RequestException;

class DimonaDeclarationCannotBeCreated extends Exception
{
    public function __construct(
        public RequestException $exception,
    ) {
        parent::__construct('The Dimona declaration cannot be created.', 500, $this->exception);
    }
}
