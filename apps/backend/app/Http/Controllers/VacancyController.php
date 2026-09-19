<?php

namespace App\Http\Controllers;

use App\Jobs\AnalyzeVacancy;
use App\Models\CareerFact;
use App\Models\Claim;
use App\Models\Vacancy;
use App\Models\VacancyAnalysis;
use App\Models\VacancyLlmRun;
use App\Models\VacancyMatchDimension;
use App\Models\VacancyMatchEvidence;
use App\Models\VacancyRequirement;
use App\Models\VacancySnapshot;
use App\Services\VacancyIngestionService;
use App\Services\VacancyMatchingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class VacancyController extends Controller
{
    public function index(Request $request, VacancyMatchingService $matching): JsonResponse
    {
        $ownerId = $request->user()->id;
        $signature = $matching->careerSignature($request->user());
        $vacancies = Vacancy::query()->where('owner_id', $ownerId)->latest()->get()->map(function (Vacancy $vacancy) use ($ownerId, $signature): array {
            $snapshot = VacancySnapshot::query()->where('owner_id', $ownerId)->where('vacancy_id', $vacancy->id)->latest('version')->first();
            $analysis = $snapshot === null ? null : VacancyAnalysis::query()
                ->where('owner_id', $ownerId)->where('vacancy_snapshot_id', $snapshot->id)->latest()->first();

            return [
                'id' => $vacancy->id,
                'title' => $vacancy->title,
                'company' => $vacancy->company,
                'source_url' => $vacancy->source_url,
                'analysis_status' => $vacancy->analysis_status,
                'error_code' => $vacancy->error_code,
                'snapshot_version' => $snapshot?->version,
                'recommendation' => $analysis?->recommendation,
                'analysis_stale' => $analysis !== null && ! hash_equals($analysis->career_signature, $signature),
                'created_at' => $vacancy->created_at,
            ];
        });

        return response()->json(['data' => $vacancies]);
    }

    public function store(Request $request, VacancyIngestionService $service): JsonResponse
    {
        $data = $request->validate([
            'source_text' => ['required', 'string', 'max:'.config('ai.vacancy_extraction.max_source_characters')],
            'source_url' => ['nullable', 'string', 'max:2048', 'url:http,https'],
        ]);
        $result = $service->queue($request->user(), $data['source_text'], $data['source_url'] ?? null);

        return response()->json(['data' => [
            'id' => $result['vacancy']->id,
            'snapshot_id' => $result['snapshot']->id,
            'snapshot_version' => $result['snapshot']->version,
            'analysis_status' => $result['vacancy']->analysis_status,
            'duplicate' => $result['duplicate'],
        ]], $result['duplicate'] ? 200 : 202);
    }

    public function show(Request $request, string $id, VacancyMatchingService $matching): JsonResponse
    {
        $ownerId = $request->user()->id;
        $vacancy = Vacancy::query()->where('owner_id', $ownerId)->findOrFail($id);
        $snapshot = VacancySnapshot::query()->where('owner_id', $ownerId)->where('vacancy_id', $vacancy->id)->latest('version')->firstOrFail();
        $requirements = VacancyRequirement::query()->where('owner_id', $ownerId)
            ->where('vacancy_snapshot_id', $snapshot->id)->orderBy('created_at')->get();
        $analysis = VacancyAnalysis::query()->where('owner_id', $ownerId)
            ->where('vacancy_snapshot_id', $snapshot->id)->latest()->first();
        $run = VacancyLlmRun::query()->where('owner_id', $ownerId)
            ->where('vacancy_snapshot_id', $snapshot->id)->latest()->first();

        return response()->json(['data' => [
            'id' => $vacancy->id,
            'title' => $vacancy->title,
            'company' => $vacancy->company,
            'source_type' => $vacancy->source_type,
            'source_url' => $vacancy->source_url,
            'analysis_status' => $vacancy->analysis_status,
            'error_code' => $vacancy->error_code,
            'snapshot' => [
                'id' => $snapshot->id,
                'version' => $snapshot->version,
                'raw_text' => $snapshot->raw_text,
                'source_url' => $snapshot->source_url,
                'imported_at' => $snapshot->imported_at,
            ],
            'requirements' => $requirements->map(fn (VacancyRequirement $requirement): array => [
                'id' => $requirement->id,
                'dimension' => $requirement->dimension,
                'importance' => $requirement->importance,
                'label' => $requirement->label,
                'normalized_value' => $requirement->normalized_value,
                'source_excerpt' => $requirement->source_excerpt,
                'confidence' => $requirement->confidence,
                'inference' => 'CVORTEX_EXTRACTION',
            ]),
            'analysis' => $analysis === null ? null : [
                'id' => $analysis->id,
                'recommendation' => $analysis->recommendation,
                'key_reasons' => $analysis->key_reasons,
                'material_gaps' => $analysis->material_gaps,
                'uncertainties' => $analysis->uncertainties,
                'analysis_version' => $analysis->analysis_version,
                'stale' => ! hash_equals($analysis->career_signature, $matching->careerSignature($request->user())),
                'dimensions' => $this->dimensions($analysis, $ownerId),
            ],
            'run' => $run === null ? null : [
                'skill_id' => $run->skill_id,
                'skill_version' => $run->skill_version,
                'prompt_version' => $run->prompt_version,
                'model_policy' => $run->model_policy,
                'provider' => $run->provider,
                'model' => $run->model,
                'status' => $run->status,
                'validation_result' => $run->validation_result,
                'error_category' => $run->error_category,
            ],
        ]]);
    }

    public function reanalyze(Request $request, string $id): JsonResponse
    {
        $vacancy = Vacancy::query()->where('owner_id', $request->user()->id)->findOrFail($id);
        $snapshot = VacancySnapshot::query()->where('owner_id', $request->user()->id)
            ->where('vacancy_id', $vacancy->id)->latest('version')->firstOrFail();
        $vacancy->forceFill(['analysis_status' => Vacancy::STATUS_PENDING, 'error_code' => null])->save();
        AnalyzeVacancy::dispatch((string) $request->user()->id, (string) $snapshot->id)->afterCommit();

        return response()->json(['data' => ['id' => $vacancy->id, 'analysis_status' => Vacancy::STATUS_PENDING]], 202);
    }

    /** @return list<array<string, mixed>> */
    private function dimensions(VacancyAnalysis $analysis, string $ownerId): array
    {
        return VacancyMatchDimension::query()->where('owner_id', $ownerId)
            ->where('vacancy_analysis_id', $analysis->id)->get()
            ->sortBy(fn (VacancyMatchDimension $dimension): int => array_search($dimension->dimension, VacancyRequirement::DIMENSIONS, true))
            ->map(function (VacancyMatchDimension $dimension) use ($ownerId): array {
                $evidence = VacancyMatchEvidence::query()->where('owner_id', $ownerId)
                    ->where('vacancy_match_dimension_id', $dimension->id)->get()->map(function (VacancyMatchEvidence $item) use ($ownerId): ?array {
                        if ($item->career_fact_id !== null) {
                            $fact = CareerFact::query()->where('owner_id', $ownerId)->find($item->career_fact_id);

                            return $fact === null ? null : [
                                'type' => 'CareerFact', 'id' => $fact->id, 'statement' => $fact->approvedAssertion(),
                                'status' => $fact->status, 'provenance_type' => $fact->provenance_type,
                            ];
                        }
                        $claim = Claim::query()->where('owner_id', $ownerId)->find($item->claim_id);

                        return $claim === null ? null : [
                            'type' => 'Claim', 'id' => $claim->id, 'statement' => $claim->statement,
                            'status' => $claim->truth_status,
                        ];
                    })->filter()->values()->all();

                return [
                    'dimension' => $dimension->dimension,
                    'result' => $dimension->result,
                    'explanation' => $dimension->explanation,
                    'origin' => $dimension->origin,
                    'vacancy_requirement_ids' => $dimension->vacancy_requirement_ids,
                    'candidate_evidence' => $evidence,
                ];
            })->values()->all();
    }
}
