<?php

namespace App\AI\Exceptions;

class LlmProviderException extends \RuntimeException
{
    public const MAX_RETRY_ATTEMPTS = 3;

    public const MAX_RETRY_AFTER_SECONDS = 86400;

    public const UNIQUE_LOCK_BUFFER_SECONDS = 600;

    public const UNIQUE_LOCK_SECONDS = (self::MAX_RETRY_ATTEMPTS - 1) * self::MAX_RETRY_AFTER_SECONDS + self::UNIQUE_LOCK_BUFFER_SECONDS;

    public const REFUSAL = 'REFUSAL';

    public const INCOMPLETE = 'INCOMPLETE';

    public const MALFORMED_OUTPUT = 'MALFORMED_OUTPUT';

    public const TRANSPORT = 'TRANSPORT';

    public const PROVIDER = 'PROVIDER_ERROR';

    public const NOT_CONFIGURED = 'NOT_CONFIGURED';

    public const RATE_LIMITED = 'RATE_LIMITED';

    public const TEMPORARY_UNAVAILABLE = 'TEMPORARY_UNAVAILABLE';

    public const INVALID_CONFIGURATION = 'INVALID_CONFIGURATION';

    private bool $diagnosticRecorded = false;

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
        public readonly ?int $retryAfterSeconds = null,
    ) {
        parent::__construct($message, previous: $previous);
    }

    public function isRetryable(): bool
    {
        return in_array($this->category, [self::TRANSPORT, self::RATE_LIMITED, self::TEMPORARY_UNAVAILABLE], true);
    }

    public function requiresConfiguration(): bool
    {
        return in_array($this->category, [self::NOT_CONFIGURED, self::INVALID_CONFIGURATION], true);
    }

    public function markDiagnosticRecorded(): void
    {
        $this->diagnosticRecorded = true;
    }

    public function diagnosticRecorded(): bool
    {
        return $this->diagnosticRecorded;
    }
}
