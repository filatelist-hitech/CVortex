<?php

namespace App\Exceptions;

class SafeCareerException extends \RuntimeException
{
    public function __construct()
    {
        parent::__construct('The Career operation failed safely.');
    }
}
