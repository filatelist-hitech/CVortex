<?php

namespace App\AI\Data;

final readonly class ResolvedModel
{
    public function __construct(
        public string $policyId,
        public string $provider,
        public string $model,
        public ?int $inputCostMicrosPerMillionTokens = null,
        public ?int $outputCostMicrosPerMillionTokens = null,
    ) {}
}
