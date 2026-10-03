<?php

namespace App\Services;

use App\Jobs\AnalyzeVacancy;
use App\Models\User;
use App\Models\Vacancy;
use App\Models\VacancySnapshot;
use App\Queue\PendingJobRecovery;
use Illuminate\Bus\UniqueLock;
use Illuminate\Support\Facades\DB;

class VacancyReanalysisService
{
    public function __construct(private readonly DatabaseOwnerContext $ownerContext) {}

    /** @return array{snapshot: VacancySnapshot, status: string} */
    public function queue(User $user, string $vacancyId): array
    {
        $result = $this->ownerContext->run((string) $user->id, function () use ($user, $vacancyId): array {
            return DB::transaction(function () use ($user, $vacancyId): array {
                // Import locks this aggregate before publishing a new snapshot.
                $vacancy = Vacancy::query()->where('owner_id', $user->id)
                    ->lockForUpdate()->findOrFail($vacancyId);
                $snapshot = VacancySnapshot::query()->where('owner_id', $user->id)
                    ->where('vacancy_id', $vacancy->id)->orderByDesc('version')->orderByDesc('id')->firstOrFail();
                $status = $vacancy->analysis_status;
                $staleRunning = $status === Vacancy::STATUS_RUNNING && $vacancy->analysisRunIsStale();
                if ($vacancy->analysis_status === Vacancy::STATUS_RUNNING && ! $staleRunning) {
                    return ['snapshot' => $snapshot, 'status' => $status, 'dispatch' => false];
                }
                if ($status === Vacancy::STATUS_PENDING && ! PendingJobRecovery::recoveryIsDue($vacancy, $snapshot->created_at)) {
                    return ['snapshot' => $snapshot, 'status' => $status, 'dispatch' => false];
                }
                if ($status !== Vacancy::STATUS_PENDING) {
                    $vacancy->forceFill(['analysis_status' => Vacancy::STATUS_PENDING, 'error_code' => null, 'active_run_token' => null])->save();
                }
                PendingJobRecovery::reserve($vacancy);

                return [
                    'snapshot' => $snapshot,
                    'status' => Vacancy::STATUS_PENDING,
                    'dispatch' => true,
                ];
            });
        });

        if ($result['dispatch']) {
            app(UniqueLock::class)->release(new AnalyzeVacancy((string) $user->id, (string) $result['snapshot']->id));
            AnalyzeVacancy::dispatch((string) $user->id, (string) $result['snapshot']->id)->afterCommit();
        }

        return ['snapshot' => $result['snapshot'], 'status' => $result['status']];
    }
}
