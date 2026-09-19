<?php

namespace App\Services;

use App\Exceptions\SafeVacancyException;
use App\Jobs\AnalyzeVacancy;
use App\Models\User;
use App\Models\Vacancy;
use App\Models\VacancySnapshot;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

class VacancyIngestionService
{
    /** @return array{vacancy: Vacancy, snapshot: VacancySnapshot, duplicate: bool} */
    public function queue(User $user, string $sourceText, ?string $sourceUrl): array
    {
        $sourceUrl = filled($sourceUrl) ? trim((string) $sourceUrl) : null;
        $contentHash = hash('sha256', $this->canonicalText($sourceText));

        try {
            $result = DB::transaction(function () use ($user, $sourceText, $sourceUrl, $contentHash): array {
                $existing = VacancySnapshot::query()
                    ->where('owner_id', $user->id)
                    ->where('content_hash', $contentHash)
                    ->first();
                if ($existing !== null) {
                    $vacancy = Vacancy::query()->where('owner_id', $user->id)->findOrFail($existing->vacancy_id);

                    return ['vacancy' => $vacancy, 'snapshot' => $existing, 'duplicate' => true];
                }

                $vacancy = $sourceUrl === null ? null : Vacancy::query()
                    ->where('owner_id', $user->id)
                    ->where('source_url', $sourceUrl)
                    ->latest()
                    ->first();
                if ($vacancy === null) {
                    $vacancy = Vacancy::query()->create([
                        'owner_id' => $user->id,
                        'source_type' => 'PASTED_TEXT',
                        'source_url' => $sourceUrl,
                        'title' => $this->deterministicTitle($sourceText),
                        'company' => $this->deterministicCompany($sourceText),
                        'analysis_status' => Vacancy::STATUS_PENDING,
                    ]);
                } else {
                    $vacancy->forceFill([
                        'analysis_status' => Vacancy::STATUS_PENDING,
                        'error_code' => null,
                        'title' => $this->deterministicTitle($sourceText) ?? $vacancy->title,
                        'company' => $this->deterministicCompany($sourceText) ?? $vacancy->company,
                    ])->save();
                }

                $version = ((int) VacancySnapshot::query()->where('vacancy_id', $vacancy->id)->max('version')) + 1;
                $snapshot = VacancySnapshot::query()->create([
                    'owner_id' => $user->id,
                    'vacancy_id' => $vacancy->id,
                    'version' => $version,
                    'raw_text' => $sourceText,
                    'source_url' => $sourceUrl,
                    'content_hash' => $contentHash,
                    'imported_at' => now(),
                ]);

                return ['vacancy' => $vacancy, 'snapshot' => $snapshot, 'duplicate' => false];
            });
        } catch (QueryException) {
            $existing = VacancySnapshot::query()->where('owner_id', $user->id)->where('content_hash', $contentHash)->first();
            if ($existing === null) {
                throw new SafeVacancyException;
            }
            $result = [
                'vacancy' => Vacancy::query()->where('owner_id', $user->id)->findOrFail($existing->vacancy_id),
                'snapshot' => $existing,
                'duplicate' => true,
            ];
        }

        if (! $result['duplicate'] || in_array($result['vacancy']->analysis_status, [Vacancy::STATUS_PENDING, Vacancy::STATUS_FAILED], true)) {
            AnalyzeVacancy::dispatch((string) $user->id, (string) $result['snapshot']->id)->afterCommit();
        }

        return $result;
    }

    public function canonicalText(string $text): string
    {
        $text = preg_replace('/^\xEF\xBB\xBF/', '', $text) ?? $text;

        return trim(str_replace(["\r\n", "\r"], "\n", $text));
    }

    private function deterministicTitle(string $text): ?string
    {
        foreach (preg_split('/\R/u', $text) ?: [] as $line) {
            $line = trim($line);
            if ($line !== '' && mb_strlen($line) <= 160) {
                return $line;
            }
        }

        return null;
    }

    private function deterministicCompany(string $text): ?string
    {
        foreach (preg_split('/\R/u', $text) ?: [] as $line) {
            if (preg_match('/^(?:company|компания)\s*:\s*(.+)$/iu', trim($line), $match) === 1) {
                return mb_substr(trim($match[1]), 0, 255);
            }
        }

        return null;
    }
}
