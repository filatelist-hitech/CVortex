<?php

namespace App\Queue;

final class QueueExecutionContext
{
    private int $depth = 0;

    public function begin(): void
    {
        $this->depth++;
    }

    public function finish(): void
    {
        $this->depth = max(0, $this->depth - 1);
    }

    public function isProcessing(): bool
    {
        return $this->depth > 0;
    }
}
