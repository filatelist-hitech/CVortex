<?php

namespace App\AI\Data;

final readonly class LlmRequest
{
    /** @param array<string, mixed> $schema */
    public function __construct(
        public string $trustedInstructions,
        public string $untrustedSourceText,
        public array $schema,
        public ModelPolicy $modelPolicy,
    ) {}
}
