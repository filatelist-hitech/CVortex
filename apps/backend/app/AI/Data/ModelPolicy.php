<?php

namespace App\AI\Data;

final readonly class ModelPolicy
{
    public function __construct(
        public string $id,
        public bool $requiresStructuredOutput,
    ) {}
}
