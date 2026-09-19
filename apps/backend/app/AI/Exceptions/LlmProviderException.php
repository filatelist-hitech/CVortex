<?php

namespace App\AI\Exceptions;

class LlmProviderException extends \RuntimeException
{
    public const REFUSAL = 'REFUSAL';

    public const INCOMPLETE = 'INCOMPLETE';

    public const MALFORMED_OUTPUT = 'MALFORMED_OUTPUT';

    public const TRANSPORT = 'TRANSPORT';

    public const PROVIDER = 'PROVIDER_ERROR';

    public const NOT_CONFIGURED = 'NOT_CONFIGURED';

    public function __construct(
        public readonly string $category,
        string $message = 'The configured LLM provider failed.',
        public readonly ?string $providerName = null,
        public readonly ?string $resolvedModel = null,
        ?\Throwable $previous = null,
        public readonly ?string $providerRequestId = null,
        public readonly ?int $inputTokens = null,
        public readonly ?int $outputTokens = null,
        public readonly ?int $latencyMs = null,
        public readonly ?int $estimatedCostMicros = null,
    ) {
        parent::__construct($message, previous: $previous);
    }
}
