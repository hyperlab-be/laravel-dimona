<?php

namespace Hyperlab\Dimona\Exceptions;

use Exception;

class DimonaDeclarationIsNotYetProcessed extends Exception
{
    public function __construct()
    {
        parent::__construct('The Dimona declaration is not yet processed.', 404);
    }
}
