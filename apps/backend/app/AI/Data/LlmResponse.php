<?php

namespace App\AI\Data;

final readonly class LlmResponse
{
    /** @param array<string, mixed> $output */
    public function __construct(
        public array $output,
        public string $provider,
        public string $model,
        public ?int $inputTokens = null,
        public ?int $outputTokens = null,
        public ?int $latencyMs = null,
        public ?string $providerRequestId = null,
    ) {}
}
