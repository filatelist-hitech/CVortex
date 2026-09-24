<?php

namespace App\Services;

use App\Models\CareerFact;
use App\Models\Claim;
use App\Models\ClaimEvidence;
use App\Models\User;
use App\Models\VacancyAnalysis;
use App\Models\VacancyMatchDimension;
use App\Models\VacancyMatchEvidence;
use App\Models\VacancyRequirement;
use App\Models\VacancySnapshot;

class ApplicationContextBuilder
{
    public function __construct(private readonly TrustedCareerQuery $career) {}

    /** @return array{requirements: list<array<string, mixed>>, claims: list<array<string, mixed>>, career_signature: string} */
    public function build(User $user, VacancySnapshot $snapshot, VacancyAnalysis $analysis): array
    {
        $requirements = VacancyRequirement::query()->where('owner_id', $user->id)
            ->where('vacancy_snapshot_id', $snapshot->id)->orderBy('created_at')->get();

        $dimensionIds = VacancyMatchDimension::query()->where('owner_id', $user->id)
            ->where('vacancy_analysis_id', $analysis->id)->pluck('id');
        $evidence = VacancyMatchEvidence::query()->where('owner_id', $user->id)
            ->whereIn('vacancy_match_dimension_id', $dimensionIds)->get(['claim_id', 'career_fact_id']);
        $claimIds = $evidence->pluck('claim_id')->filter()->merge(ClaimEvidence::query()
            ->where('owner_id', $user->id)->whereIn('career_fact_id', $evidence->pluck('career_fact_id')->filter())
            ->pluck('claim_id'))->unique();
        $trusted = $this->career->forMatching($user);
        $claims = collect($trusted['claims'])->keyBy(fn (Claim $claim): string => (string) $claim->id)
            ->only($claimIds->map(fn ($id): string => (string) $id)->all())
            ->map(fn (Claim $claim): array => [
                'id' => (string) $claim->id,
                'statement' => $claim->statement,
                'career_facts' => ClaimEvidence::query()->where('owner_id', $user->id)->where('claim_id', $claim->id)
                    ->get()->map(function (ClaimEvidence $link) use ($user): ?array {
                        $fact = CareerFact::query()->where('owner_id', $user->id)->find($link->career_fact_id);
                        if ($fact === null || $fact->status !== CareerFact::STATUS_CONFIRMED) {
                            return null;
                        }

                        return [
                            'id' => (string) $fact->id,
                            'statement' => $fact->approvedAssertion(),
                            'status' => $fact->status,
                            'provenance_type' => $fact->provenance_type,
                            'source_excerpt' => $fact->source_excerpt,
                        ];
                    })->filter()->values()->all(),
            ])
            ->values()->all();

        return [
            'requirements' => $requirements->map(fn (VacancyRequirement $requirement): array => [
                'id' => (string) $requirement->id,
                'dimension' => $requirement->dimension,
                'importance' => $requirement->importance,
                'label' => $requirement->label,
                'source_excerpt' => $requirement->source_excerpt,
            ])->all(),
            'claims' => $claims,
            'career_signature' => $analysis->career_signature,
        ];
    }
}
