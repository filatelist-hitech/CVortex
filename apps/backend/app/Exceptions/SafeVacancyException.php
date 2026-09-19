<?php

namespace App\Exceptions;

class SafeVacancyException extends \RuntimeException
{
    public function __construct()
    {
        parent::__construct('The vacancy operation failed safely.');
    }
}
