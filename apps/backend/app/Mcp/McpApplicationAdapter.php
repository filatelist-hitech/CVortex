<?php

namespace App\Mcp;

use App\Models\User;
use App\Models\Vacancy;
use App\Models\VacancyAnalysis;
use App\Models\VacancySnapshot;
use App\Services\ApplicationContextBuilder;
use App\Services\ApplicationPreparationService;
use App\Services\DatabaseOwnerContext;
use App\Services\VacancyMatchingService;
use Illuminate\Validation\ValidationException;

class McpApplicationAdapter
{
    public function __construct(
        private readonly DatabaseOwnerContext $ownerContext,
        private readonly ApplicationContextBuilder $contextBuilder,
        private readonly ApplicationPreparationService $preparations,
        private readonly VacancyMatchingService $matching,
    ) {}

    /** @return array<string, mixed> */
    public function vacancy(User $user, string $vacancyId): array
    {
        return $this->ownerContext->run((string) $user->id, function () use ($user, $vacancyId): array {
            $vacancy = Vacancy::query()->where('owner_id', $user->id)->findOrFail($vacancyId);

            return [
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
            if ($vacancy->analysis_status !== Vacancy::STATUS_COMPLETED) {
                throw ValidationException::withMessages(['vacancy' => 'A completed analysis is required.']);
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

            return [
                'vacancy' => $this->vacancy($user, $vacancyId),
                'requirements' => array_map(fn (array $item): array => [
                    'id' => $item['id'], 'dimension' => $item['dimension'],
                    'importance' => $item['importance'], 'label' => $item['label'],
                ], $context['requirements']),
                'confirmed_claims' => array_map(fn (array $claim): array => [
                    'id' => $claim['id'], 'statement' => $claim['statement'],
                    'confirmed_facts' => array_map(fn (array $fact): array => [
                        'id' => $fact['id'], 'statement' => $fact['statement'],
                    ], $claim['career_facts']),
                ], $context['claims']),
                'untrusted_vacancy_data' => true,
            ];
        });
    }

    /** @param list<array{assertion: string, claim_ids: list<string>}> $claimUsages
     * @return array<string, mixed>
     */
    public function submitDraft(User $user, string $vacancyId, string $variant, string $content, array $claimUsages): array
    {
        return $this->ownerContext->run((string) $user->id, function () use ($user, $vacancyId, $variant, $content, $claimUsages): array {
            $preparation = $this->preparations->open($user, $vacancyId);

            return $this->preparations->submitExternalCover($user, $preparation, $variant, $content, $claimUsages);
        });
    }
}
