<?php

namespace App\Jobs;

use App\AI\Exceptions\LlmProviderException;
use App\AI\Exceptions\VacancyOutputException;
use App\Models\User;
use App\Models\Vacancy;
use App\Models\VacancySnapshot;
use App\Services\DatabaseOwnerContext;
use App\Services\VacancyAnalysisService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class AnalyzeVacancy implements ShouldBeUnique, ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public int $uniqueFor = Vacancy::ANALYSIS_JOB_UNIQUE_FOR_SECONDS;

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

        Vacancy::query()->whereKey($snapshot->vacancy_id)->where('owner_id', $snapshot->owner_id)
            ->where('analysis_status', Vacancy::STATUS_FAILED)
            ->whereRaw(
                'NOT EXISTS (SELECT 1 FROM vacancy_snapshots AS newer_snapshot WHERE newer_snapshot.owner_id = vacancies.owner_id AND newer_snapshot.vacancy_id = vacancies.id AND newer_snapshot.version > ?)',
                [$snapshot->version],
            )
            ->update([
                'analysis_status' => Vacancy::STATUS_PENDING,
                'error_code' => null,
                'updated_at' => now(),
            ]);

        $delay = $exception->retryAfterSeconds;
        if ($delay === null || $delay < 0 || $delay > 86400) {
            $delays = $this->backoff();
            $delay = $delays[min(max(0, $this->attempts() - 1), count($delays) - 1)] ?? 0;
        }

        $this->release($delay);
    }
}
