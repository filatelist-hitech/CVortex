<?php

namespace App\Jobs;

use App\AI\Exceptions\VacancyOutputException;
use App\Models\User;
use App\Models\VacancySnapshot;
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

    public int $uniqueFor = 600;

    public function __construct(public readonly string $ownerId, public readonly string $snapshotId) {}

    public function uniqueId(): string
    {
        return $this->ownerId.':'.$this->snapshotId;
    }

    public function handle(VacancyAnalysisService $service): void
    {
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
        }
    }
}
