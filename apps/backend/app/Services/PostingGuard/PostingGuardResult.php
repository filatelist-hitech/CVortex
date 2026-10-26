<?php

declare(strict_types=1);

namespace App\Services\PostingGuard;

final class PostingGuardResult
{
    public const THREAT_CLEAN = 'CLEAN';

    public const THREAT_SUSPICIOUS = 'SUSPICIOUS';

    public const THREAT_HIGH_RISK = 'HIGH_RISK';

    /**
     * @param  array<string, mixed>  $findings
     */
    public function __construct(
        public readonly string $cleanText,
        public readonly string $threatLevel,
        public readonly array $findings,
        public readonly bool $isSafeForLlm,
        public readonly ?string $threatSummary = null,
    ) {}

    public function isClean(): bool
    {
        return $this->threatLevel === self::THREAT_CLEAN;
    }

    public function hasInjections(): bool
    {
        return ! empty($this->findings['injections']);
    }

    public function hasScamIndicators(): bool
    {
        return ! empty($this->findings['scams']);
    }

    public function hasStrippedHiddenContent(): bool
    {
        return ($this->findings['stripped_invisible_count'] ?? 0) > 0
            || ($this->findings['stripped_html_blocks'] ?? 0) > 0;
    }
}
