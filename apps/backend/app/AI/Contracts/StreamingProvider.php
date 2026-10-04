<?php

namespace App\AI\Contracts;

use App\Models\User;
use Generator;

interface StreamingProvider
{
    /** @return list<array{slug: string, display_name: string}> */
    public function models(User $user, string $connectionId): array;

    /** @param list<array{role: string, content: string}> $input
     * @return Generator<int, array<string, mixed>>
     */
    public function stream(User $user, string $connectionId, string $model, string $instructions, array $input): Generator;
}
