<?php

namespace App\Services;

use App\Jobs\AnalyzeVacancy;
use App\Models\User;
use App\Models\Vacancy;
use App\Models\VacancySnapshot;
use Illuminate\Support\Facades\DB;

class VacancyReanalysisService
{
    public function __construct(private readonly DatabaseOwnerContext $ownerContext) {}

    /** @return array{snapshot: VacancySnapshot, status: string} */
    public function queue(User $user, string $vacancyId): array
    {
        return $this->ownerContext->run((string) $user->id, function () use ($user, $vacancyId): array {
            return DB::transaction(function () use ($user, $vacancyId): array {
                // Import locks this aggregate before publishing a new snapshot.
                $vacancy = Vacancy::query()->where('owner_id', $user->id)
                    ->lockForUpdate()->findOrFail($vacancyId);
                $snapshot = VacancySnapshot::query()->where('owner_id', $user->id)
                    ->where('vacancy_id', $vacancy->id)->orderByDesc('version')->orderByDesc('id')->firstOrFail();
                if ($vacancy->analysis_status === Vacancy::STATUS_RUNNING && ! $vacancy->analysisRunIsStale()) {
                    return ['snapshot' => $snapshot, 'status' => $vacancy->analysis_status];
                }
                if ($vacancy->analysis_status !== Vacancy::STATUS_PENDING) {
                    $vacancy->forceFill(['analysis_status' => Vacancy::STATUS_PENDING, 'error_code' => null])->save();
                }
                // A second dispatch is harmless under the snapshot-unique
                // job policy and can recover stale or missing queued work.
                AnalyzeVacancy::dispatch((string) $user->id, (string) $snapshot->id)->afterCommit();

                return ['snapshot' => $snapshot, 'status' => Vacancy::STATUS_PENDING];
            });
        });
    }
}
