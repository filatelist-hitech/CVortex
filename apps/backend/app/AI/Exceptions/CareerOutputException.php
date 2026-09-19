<?php

namespace App\AI\Exceptions;

class CareerOutputException extends \RuntimeException
{
    public const SCHEMA_INVALID = 'SCHEMA_INVALID';

    public const SEMANTIC_REJECTED = 'SEMANTIC_REJECTED';

    public function __construct(public readonly string $category)
    {
        parent::__construct('The extraction result could not be accepted.');
    }
}
