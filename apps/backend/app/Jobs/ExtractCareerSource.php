<?php

namespace App\Jobs;

use App\AI\Exceptions\CareerOutputException;
use App\Models\CareerSource;
use App\Models\User;
use App\Services\CareerExtractionService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class ExtractCareerSource implements ShouldBeUnique, ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public int $uniqueFor = 600;

    public function __construct(public readonly string $ownerId, public readonly string $sourceId) {}

    public function uniqueId(): string
    {
        return $this->ownerId.':'.$this->sourceId;
    }

    public function handle(CareerExtractionService $service): void
    {
        $user = User::query()->find($this->ownerId);
        $source = CareerSource::query()->where('owner_id', $this->ownerId)->find($this->sourceId);
        if ($user === null || $source === null) {
            return;
        }

        try {
            $service->extract($user, (string) $source->source_text);
        } catch (CareerOutputException) {
            // Invalid model output is terminal for this source; the service has
            // already persisted a retryable FAILED state for an explicit retry.
        }
    }
}
