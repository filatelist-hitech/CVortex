<?php

namespace App\Exceptions;

use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

class CareerFactSupersessionConflict extends ConflictHttpException
{
    public function __construct()
    {
        parent::__construct('This Career Fact has already been superseded.');
    }
}
