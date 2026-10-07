<?php

namespace App\AI\ChatGpt;

use RuntimeException;

final class PlanException extends RuntimeException
{
    public function __construct(
        public readonly string $errorCode,
        public readonly int $httpStatus = 400,
        public readonly ?string $requestId = null,
    ) {
        // Never attach an upstream HTTP exception: it can retain credentials or bodies.
        parent::__construct('ChatGPT plan operation failed: '.$errorCode);
    }
}
