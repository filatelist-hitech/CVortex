<?php

namespace App\Jobs;

use App\AI\Exceptions\CareerOutputException;
use App\AI\Exceptions\LlmProviderException;
use App\AI\ProviderRetryAfter;
use App\Diagnostics\ProviderRetryWarning;
use App\Models\CareerSource;
use App\Models\User;
use App\Queue\PendingJobRecovery;
use App\Services\CareerExtractionService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;

class ExtractCareerSource implements ShouldBeUnique, ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = LlmProviderException::MAX_RETRY_ATTEMPTS;

    public int $uniqueFor = LlmProviderException::UNIQUE_LOCK_SECONDS;

    public function __construct(public readonly string $ownerId, public readonly string $sourceId) {}

    public function uniqueId(): string
    {
        return $this->ownerId.':'.$this->sourceId;
    }

    /** @return list<int> */
    public function backoff(): array
    {
        return [5, 30];
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
        } catch (LlmProviderException $exception) {
            $this->retryOrFail($exception, $source);
        }
    }

    private function retryOrFail(LlmProviderException $exception, CareerSource $source): void
    {
        if (! $exception->isRetryable() || $this->attempts() >= $this->tries) {
            DB::transaction(function () use ($source): void {
                $current = CareerSource::query()->where('owner_id', $this->ownerId)
                    ->lockForUpdate()->find($source->id);
                if ($current === null || ! in_array($current->extraction_status, [CareerSource::STATUS_FAILED, CareerSource::STATUS_RUNNING], true)) {
                    return;
                }
                $current->forceFill([
                    'extraction_status' => CareerSource::STATUS_FAILED,
                    'error_code' => 'PROVIDER_ERROR',
                    'next_attempt_at' => null,
                    'dispatch_recovery_at' => null,
                ])->save();
            });
            $this->fail($exception);

            return;
        }

        $delay = ProviderRetryAfter::boundedSeconds($exception->retryAfterSeconds);
        if ($delay === null) {
            $delays = $this->backoff();
            $delay = $delays[min(max(0, $this->attempts() - 1), count($delays) - 1)] ?? 0;
        }

        DB::transaction(function () use ($source, $delay): void {
            $current = CareerSource::query()->where('owner_id', $this->ownerId)
                ->lockForUpdate()->find($source->id);
            if ($current === null || ! in_array($current->extraction_status, [CareerSource::STATUS_FAILED, CareerSource::STATUS_RUNNING], true)) {
                return;
            }
            $current->forceFill([
                'extraction_status' => CareerSource::STATUS_PENDING,
                'error_code' => null,
            ])->save();
            PendingJobRecovery::reserve($current, $delay);
        });

        ProviderRetryWarning::scheduled('career_text_extraction', $exception, $this->attempts(), $delay);
        $this->release($delay);
    }
}
