<?php

namespace App\Services\Discovery;

use App\Models\DiscoveredVacancy;
use App\Models\User;
use App\Services\DatabaseOwnerContext;
use App\Services\PostingGuard\PostingGuardResult;
use App\Services\PostingGuard\PostingGuardService;
use App\Services\VacancyIngestionService;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class JobDiscoveryService
{
    public function __construct(
        private readonly FastFitScorer $fitScorer,
        private readonly PostingGuardService $postingGuard,
        private readonly VacancyIngestionService $vacancyIngestion,
        private readonly DatabaseOwnerContext $ownerContext,
    ) {}

    /**
     * @param  list<string>  $portals
     * @return Collection<int, DiscoveredVacancy>
     */
    public function search(
        User $user,
        string $query,
        array $portals = ['freehire', 'headhunter', 'habr', 'linkedin'],
        ?string $location = null,
        int $limit = 15,
        bool $remote = false,
    ): Collection {
        $workerUrl = config('services.worker.url', 'http://worker:3001');

        try {
            $response = Http::timeout(30)->post("{$workerUrl}/scrapers/search", [
                'query' => $query,
                'location' => $location ?? '',
                'limit' => $limit,
                'remote' => $remote,
                'portals' => $portals,
            ]);

            if (! $response->successful()) {
                Log::warning('Worker scrapers/search failed', [
                    'status' => $response->status(),
                    'body' => $response->body(),
                ]);

                return new Collection;
            }

            $data = $response->json();
            $rawJobs = $data['jobs'] ?? [];
        } catch (\Throwable $e) {
            Log::error('Worker scrapers/search connection error', [
                'message' => $e->getMessage(),
            ]);

            return new Collection;
        }

        return $this->ownerContext->run((string) $user->id, function () use ($user, $rawJobs): Collection {
            $saved = new Collection;

            foreach ($rawJobs as $job) {
                $sourceUrl = trim((string) ($job['source_url'] ?? ''));
                if (empty($sourceUrl)) {
                    continue;
                }

                $rawTitle = (string) ($job['title'] ?? 'Без названия');
                $rawCompany = (string) ($job['company'] ?? 'Не указана');
                $location = isset($job['location']) ? (string) $job['location'] : null;
                $salaryText = isset($job['salary_text']) ? (string) $job['salary_text'] : null;
                $description = isset($job['description']) ? (string) $job['description'] : null;
                $snippet = isset($job['snippet']) ? (string) $job['snippet'] : null;
                $portal = (string) ($job['portal'] ?? 'unknown');
                $externalId = isset($job['external_id']) ? (string) $job['external_id'] : null;

                // Inspect each content part with PostingGuard to sanitize invisible chars & HTML tags
                $titleGuard = $this->postingGuard->inspect($rawTitle);
                $companyGuard = $this->postingGuard->inspect($rawCompany);
                $title = trim($titleGuard->cleanText);
                if ($title === '') {
                    $title = 'Без названия';
                }
                $company = trim($companyGuard->cleanText);
                if ($company === '') {
                    $company = 'Не указана';
                }

                $cleanLocation = $location !== null ? trim($this->postingGuard->inspect($location)->cleanText) : null;
                $cleanSalaryText = $salaryText !== null ? trim($this->postingGuard->inspect($salaryText)->cleanText) : null;

                $descGuard = $description !== null ? $this->postingGuard->inspect($description) : null;
                $cleanDescription = $descGuard?->cleanText;

                $snippetGuard = $snippet !== null ? $this->postingGuard->inspect($snippet) : null;
                $cleanSnippet = $snippetGuard?->cleanText;

                // Overall security threat analysis combining all posting surfaces
                $rawTextToInspect = trim($rawTitle."\n".($description ?? $snippet ?? '')."\n".$rawCompany);
                $guardResult = $this->postingGuard->inspect($rawTextToInspect);

                $securityLevel = match ($guardResult->threatLevel) {
                    PostingGuardResult::THREAT_HIGH_RISK => DiscoveredVacancy::SECURITY_MALICIOUS,
                    PostingGuardResult::THREAT_SUSPICIOUS => DiscoveredVacancy::SECURITY_SUSPICIOUS,
                    default => DiscoveredVacancy::SECURITY_CLEAN,
                };

                // Fast Fit Scoring using sanitized clean text
                $fitText = $cleanDescription ?? $cleanSnippet ?? $title;
                $fitResult = $this->fitScorer->score($user, $title, $fitText);

                $publishedAt = null;
                if (! empty($job['published_at'])) {
                    try {
                        if (is_string($job['published_at'])) {
                            $publishedAt = CarbonImmutable::parse($job['published_at']);
                        } elseif (is_array($job['published_at']) && isset($job['published_at']['date'])) {
                            $publishedAt = CarbonImmutable::parse($job['published_at']['date']);
                        }
                    } catch (\Throwable) {
                        $publishedAt = null;
                    }
                }

                $discovered = DiscoveredVacancy::query()->updateOrCreate(
                    [
                        'owner_id' => $user->id,
                        'source_url' => $sourceUrl,
                    ],
                    [
                        'portal' => $portal,
                        'external_id' => $externalId,
                        'title' => $title,
                        'company' => $company,
                        'location' => $cleanLocation,
                        'salary_text' => $cleanSalaryText,
                        'snippet' => $cleanSnippet,
                        'description' => $cleanDescription,
                        'fit_score' => $fitResult['score'],
                        'fit_level' => $fitResult['level'],
                        'fit_reasons' => $fitResult['reasons'],
                        'security_threat_level' => $securityLevel,
                        'published_at' => $publishedAt,
                    ]
                );

                $saved->push($discovered);
            }

            return $saved;
        });
    }

    /**
     * @return array{discovered: DiscoveredVacancy, ingestion: array<string, mixed>}
     */
    public function accept(User $user, string $discoveredId): array
    {
        return $this->ownerContext->run((string) $user->id, function () use ($user, $discoveredId): array {
            $discovered = DiscoveredVacancy::query()
                ->where('owner_id', $user->id)
                ->findOrFail($discoveredId);

            $sourceText = "Позиция: {$discovered->title}\nКомпания: {$discovered->company}\n";
            if ($discovered->location) {
                $sourceText .= "Локация: {$discovered->location}\n";
            }
            if ($discovered->salary_text) {
                $sourceText .= "Зарплата: {$discovered->salary_text}\n";
            }
            $sourceText .= "\n".($discovered->description ?? $discovered->snippet ?? $discovered->title);

            $ingestionResult = $this->vacancyIngestion->queue(
                $user,
                $sourceText,
                $discovered->source_url,
            );

            $discovered->update(['status' => DiscoveredVacancy::STATUS_ACCEPTED]);

            return [
                'discovered' => $discovered,
                'ingestion' => $ingestionResult,
            ];
        });
    }

    public function dismiss(User $user, string $discoveredId): DiscoveredVacancy
    {
        return $this->ownerContext->run((string) $user->id, function () use ($user, $discoveredId): DiscoveredVacancy {
            $discovered = DiscoveredVacancy::query()
                ->where('owner_id', $user->id)
                ->findOrFail($discoveredId);

            $discovered->update(['status' => DiscoveredVacancy::STATUS_DISMISSED]);

            return $discovered;
        });
    }

    /**
     * @return LengthAwarePaginator<int, DiscoveredVacancy>
     */
    public function list(
        User $user,
        ?string $status = null,
        ?string $portal = null,
        int $perPage = 20,
    ): LengthAwarePaginator {
        return $this->ownerContext->run((string) $user->id, function () use ($user, $status, $portal, $perPage) {
            $query = DiscoveredVacancy::query()
                ->where('owner_id', $user->id);

            if ($status !== null && in_array($status, [
                DiscoveredVacancy::STATUS_INBOX,
                DiscoveredVacancy::STATUS_ACCEPTED,
                DiscoveredVacancy::STATUS_DISMISSED,
            ], true)) {
                $query->where('status', $status);
            }

            if ($portal !== null && $portal !== '') {
                $query->where('portal', $portal);
            }

            return $query
                ->orderByDesc('fit_score')
                ->orderByDesc('created_at')
                ->paginate($perPage);
        });
    }
}
