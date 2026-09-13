<?php

namespace App\AI\Data;

final readonly class RuntimeSkillDefinition
{
    /** @param array<string, mixed> $outputSchema */
    public function __construct(
        public string $id,
        public string $version,
        public string $promptVersion,
        public string $modelPolicy,
        public string $trustedInstructions,
        public array $outputSchema,
    ) {}
}
