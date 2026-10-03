<?php

namespace App\Queue;

use Throwable;
use WeakMap;

final class QueueExecutionContext
{
    private int $depth = 0;

    /** @var list<array<string, mixed>> */
    private array $logContextStack = [];

    /** @var WeakMap<Throwable, bool> */
    private WeakMap $queueExceptions;

    public function __construct()
    {
        $this->queueExceptions = new WeakMap;
    }

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

    public function markQueueException(Throwable $exception): void
    {
        $this->queueExceptions[$exception] = true;
    }

    public function isQueueException(Throwable $exception): bool
    {
        return isset($this->queueExceptions[$exception]);
    }
}
