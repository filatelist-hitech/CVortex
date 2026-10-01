<?php

namespace App\Jobs;

use App\AI\Exceptions\LlmProviderException;
use App\AI\Exceptions\VacancyOutputException;
use App\AI\ProviderRetryAfter;
use App\Diagnostics\ProviderRetryWarning;
use App\Models\User;
use App\Models\Vacancy;
use App\Models\VacancySnapshot;
use App\Queue\PendingJobRecovery;
use App\Services\DatabaseOwnerContext;
use App\Services\VacancyAnalysisService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;

class AnalyzeVacancy implements ShouldBeUnique, ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = LlmProviderException::MAX_RETRY_ATTEMPTS;

    public int $uniqueFor = LlmProviderException::UNIQUE_LOCK_SECONDS;

    public function __construct(public readonly string $ownerId, public readonly string $snapshotId) {}

    public function uniqueId(): string
    {
        return $this->ownerId.':'.$this->snapshotId;
    }

    /** @return list<int> */
    public function backoff(): array
    {
        return [5, 30];
    }

    public function handle(VacancyAnalysisService $service, DatabaseOwnerContext $ownerContext): void
    {
        $ownerContext->run($this->ownerId, function () use ($service): void {
            $user = User::query()->find($this->ownerId);
            $snapshot = VacancySnapshot::query()->where('owner_id', $this->ownerId)->find($this->snapshotId);
            if ($user === null || $snapshot === null) {
                return;
            }
            $latestSnapshotId = VacancySnapshot::query()->where('owner_id', $this->ownerId)
                ->where('vacancy_id', $snapshot->vacancy_id)->latest('version')->value('id');
            if (! hash_equals((string) $snapshot->id, (string) $latestSnapshotId)) {
                return;
            }

            try {
                $service->analyze($user, $snapshot);
            } catch (VacancyOutputException) {
                // Invalid semantic output is terminal until an explicit user retry.
            } catch (LlmProviderException $exception) {
                $this->retryOrFail($exception, $snapshot);
            }
        });
    }

    private function retryOrFail(LlmProviderException $exception, VacancySnapshot $snapshot): void
    {
        if (! $exception->isRetryable() || $this->attempts() >= $this->tries) {
            $this->fail($exception);

            return;
        }

        $delay = ProviderRetryAfter::boundedSeconds($exception->retryAfterSeconds);
        if ($delay === null) {
            $delays = $this->backoff();
            $delay = $delays[min(max(0, $this->attempts() - 1), count($delays) - 1)] ?? 0;
        }

        DB::transaction(function () use ($snapshot, $delay): void {
            $vacancy = Vacancy::query()->whereKey($snapshot->vacancy_id)->where('owner_id', $snapshot->owner_id)
                ->lockForUpdate()->first();
            if ($vacancy === null || $vacancy->analysis_status !== Vacancy::STATUS_FAILED) {
                return;
            }
            $hasNewerSnapshot = VacancySnapshot::query()->where('owner_id', $snapshot->owner_id)
                ->where('vacancy_id', $snapshot->vacancy_id)->where('version', '>', $snapshot->version)->exists();
            if ($hasNewerSnapshot) {
                return;
            }
            $vacancy->forceFill([
                'analysis_status' => Vacancy::STATUS_PENDING,
                'error_code' => null,
            ])->save();
            PendingJobRecovery::reserve($vacancy, $delay);
        });

        ProviderRetryWarning::scheduled('vacancy_requirement_extraction', $exception, $this->attempts(), $delay);
        $this->release($delay);
    }
}
