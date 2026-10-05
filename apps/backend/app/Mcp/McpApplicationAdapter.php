<?php

namespace App\Mcp;

use App\Models\User;
use App\Models\Vacancy;
use App\Models\VacancyAnalysis;
use App\Models\VacancySnapshot;
use App\Services\ApplicationContextBuilder;
use App\Services\DatabaseOwnerContext;
use App\Services\VacancyChatContextBuilder;
use App\Services\VacancyMatchingService;
use Illuminate\Validation\ValidationException;

class McpApplicationAdapter
{
    private const MAX_REQUIREMENTS = 50;

    private const MAX_CLAIMS = 25;

    private const MAX_FACTS_PER_CLAIM = 20;

    public function __construct(
        private readonly DatabaseOwnerContext $ownerContext,
        private readonly ApplicationContextBuilder $contextBuilder,
        private readonly VacancyMatchingService $matching,
        private readonly VacancyChatContextBuilder $chatContext,
    ) {}

    /** @return array<string, mixed> */
    public function vacancy(User $user, string $vacancyId): array
    {
        return $this->ownerContext->run((string) $user->id, function () use ($user, $vacancyId): array {
            $vacancy = Vacancy::query()->where('owner_id', $user->id)->findOrFail($vacancyId);

            $snapshot = VacancySnapshot::query()->where('owner_id', $user->id)->where('vacancy_id', $vacancyId)->latest('version')->firstOrFail();

            return [
                'snapshot_id' => (string) $snapshot->id, 'snapshot_version' => (int) $snapshot->version,
                'raw_text' => mb_substr($snapshot->raw_text, 0, 25000), 'source_truncated' => mb_strlen($snapshot->raw_text) > 25000,
                'id' => (string) $vacancy->id,
                'title' => (string) $vacancy->title,
                'company' => (string) $vacancy->company,
                'analysis_status' => (string) $vacancy->analysis_status,
                'untrusted_data' => true,
            ];
        });
    }

    /** @return array<string, mixed> */
    public function context(User $user, string $vacancyId): array
    {
        return $this->ownerContext->run((string) $user->id, function () use ($user, $vacancyId): array {
            $vacancy = Vacancy::query()->where('owner_id', $user->id)->findOrFail($vacancyId);
            $bounded = $this->chatContext->build($user, $vacancy, 'Analyze vacancy');
            $data = json_decode($bounded['input'][0]['content'], true);
            $facts = $data['confirmed_facts'];
            $sourceTruncated = $data['vacancy']['source_truncated'];
            if ($vacancy->analysis_status !== Vacancy::STATUS_COMPLETED) {
                return [
                    'vacancy' => $this->vacancy($user, $vacancyId),
                    'requirements' => [],
                    'confirmed_claims' => [],
                    'confirmed_facts' => $facts,
                    'career_signature' => $bounded['career_signature'],
                    'untrusted_vacancy_data' => true,
                    'context_truncated' => $sourceTruncated,
                ];
            }
            $snapshot = VacancySnapshot::query()->where('owner_id', $user->id)->where('vacancy_id', $vacancy->id)
                ->orderByDesc('version')->orderByDesc('id')->firstOrFail();
            $analysis = VacancyAnalysis::query()->where('owner_id', $user->id)->where('vacancy_id', $vacancy->id)
                ->where('vacancy_snapshot_id', $snapshot->id)
                ->forCareerSignature($this->matching->careerSignature($user))->deterministicLatest()->first();
            if ($analysis === null) {
                throw ValidationException::withMessages(['vacancy' => 'Reanalysis is required.']);
            }

            $context = $this->contextBuilder->build($user, $snapshot, $analysis);
            $requirements = $context['requirements'];
            $claims = $context['claims'];
            $truncated = $sourceTruncated || count($requirements) > self::MAX_REQUIREMENTS || count($claims) > self::MAX_CLAIMS;
            $boundedClaims = array_slice($claims, 0, self::MAX_CLAIMS);

            return [
                'vacancy' => $this->vacancy($user, $vacancyId),
                'confirmed_facts' => $facts,
                'career_signature' => $context['career_signature'],
                'requirements' => array_map(fn (array $item): array => [
                    'id' => $item['id'], 'dimension' => $item['dimension'],
                    'importance' => $item['importance'], 'label' => $item['label'],
                ], array_slice($requirements, 0, self::MAX_REQUIREMENTS)),
                'confirmed_claims' => array_map(function (array $claim) use (&$truncated): array {
                    $facts = $claim['career_facts'];
                    $truncated = $truncated || count($facts) > self::MAX_FACTS_PER_CLAIM;

                    return [
                        'id' => $claim['id'], 'statement' => $claim['statement'],
                        'confirmed_facts' => array_map(fn (array $fact): array => [
                            'id' => $fact['id'], 'statement' => $fact['statement'],
                        ], array_slice($facts, 0, self::MAX_FACTS_PER_CLAIM)),
                    ];
                }, $boundedClaims),
                'untrusted_vacancy_data' => true,
                'context_truncated' => $truncated,
            ];
        });
    }
}
