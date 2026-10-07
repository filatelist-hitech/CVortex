<?php

namespace App\AI\Exceptions;

use RuntimeException;

final class ModelSelectionException extends RuntimeException
{
    public const NOT_AVAILABLE = 'MODEL_NOT_AVAILABLE';

    public const NOT_ALLOWED = 'MODEL_NOT_ALLOWED';

    public function __construct(public readonly string $errorCode)
    {
        parent::__construct('The selected model is unavailable for this request.');
    }
}
