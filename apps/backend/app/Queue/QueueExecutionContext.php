<?php

namespace App\Queue;

final class QueueExecutionContext
{
    private int $depth = 0;

    /** @var list<array<string, mixed>> */
    private array $logContextStack = [];

    /** @param array<string, mixed> $previousLogContext */
    public function begin(array $previousLogContext = []): void
    {
        $this->logContextStack[] = $previousLogContext;
        $this->depth++;
    }

    /** @return array<string, mixed> */
    public function finish(): array
    {
        $this->depth = max(0, $this->depth - 1);

        return array_pop($this->logContextStack) ?? [];
    }

    public function isProcessing(): bool
    {
        return $this->depth > 0;
    }
}
