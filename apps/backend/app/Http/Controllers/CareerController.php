<?php

namespace App\Http\Controllers;

use App\Models\CareerFact;
use App\Models\CareerFactType;
use App\Models\CareerSource;
use App\Models\Claim;
use App\Models\LlmRun;
use App\Services\CareerFactService;
use App\Services\ClaimResolutionService;
use App\Services\TrustedCareerQuery;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class CareerController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $ownerId = $request->user()->id;
        $facts = CareerFact::query()->where('owner_id', $ownerId)->latest()->get();
        $claims = Claim::query()->where('owner_id', $ownerId)->latest()->get()->map(function (Claim $claim): array {
            return [
                'id' => $claim->id,
                'statement' => $claim->statement,
                'truth_status' => $claim->truth_status,
                'fact_ids' => DB::table('claim_evidence')
                    ->join('career_facts', 'career_facts.id', '=', 'claim_evidence.career_fact_id')
                    ->where('claim_evidence.claim_id', $claim->id)
                    ->where('claim_evidence.owner_id', $claim->owner_id)
                    ->where('career_facts.owner_id', $claim->owner_id)
                    ->pluck('claim_evidence.career_fact_id'),
            ];
        });
        $sources = CareerSource::query()->where('owner_id', $ownerId)->latest()->get()
            ->map(fn (CareerSource $source): array => $this->sourceSummary($source));

        return response()->json(['data' => [
            'facts' => $facts,
            'claims' => $claims,
            'sources' => $sources,
        ]]);
    }

    public function source(Request $request, string $id): JsonResponse
    {
        $source = CareerSource::query()->where('owner_id', $request->user()->id)->findOrFail($id);
        $run = LlmRun::query()->where('owner_id', $request->user()->id)->where('career_source_id', $source->id)->latest()->first();

        return response()->json(['data' => [
            ...$this->sourceSummary($source),
            'source_text' => $source->source_text,
            'run' => $run === null ? null : [
                'id' => $run->id,
                'workflow' => $run->workflow,
                'skill_id' => $run->skill_id,
                'skill_version' => $run->skill_version,
                'prompt_version' => $run->prompt_version,
                'model_policy' => $run->model_policy,
                'provider' => $run->provider,
                'model' => $run->model,
                'status' => $run->status,
                'input_tokens' => $run->input_tokens,
                'output_tokens' => $run->output_tokens,
                'latency_ms' => $run->latency_ms,
                'retry_count' => $run->retry_count,
                'validation_result' => $run->validation_result,
                'error_category' => $run->error_category,
                'estimated_cost_micros' => $run->estimated_cost_micros,
            ],
        ]]);
    }

    public function trusted(Request $request, TrustedCareerQuery $query): JsonResponse
    {
        return response()->json(['data' => $query->forMatching($request->user())]);
    }

    public function manual(Request $request, CareerFactService $service): JsonResponse
    {
        $data = $request->validate([
            'fact_type' => ['required', 'string', Rule::enum(CareerFactType::class)],
            'assertion' => ['required', 'string', 'max:1000'],
        ]);
        $fact = $service->createManual($request->user(), $data['fact_type'], trim($data['assertion']));

        return response()->json(['data' => $fact], 201);
    }

    public function review(Request $request, string $id, CareerFactService $service): JsonResponse
    {
        $fact = CareerFact::query()->where('owner_id', $request->user()->id)->findOrFail($id);
        $data = $request->validate([
            'action' => ['required', 'in:confirm,edit_confirm,reject'],
            'assertion' => ['nullable', 'string', 'max:1000'],
        ]);
        $fact = $service->review($request->user(), $fact, $data['action'], $data['assertion'] ?? null);

        return response()->json(['data' => $fact]);
    }

    public function deprecate(Request $request, string $id, CareerFactService $service): JsonResponse
    {
        $fact = CareerFact::query()->where('owner_id', $request->user()->id)->findOrFail($id);

        return response()->json(['data' => $service->deprecate($request->user(), $fact)]);
    }

    public function supersede(Request $request, string $id, CareerFactService $service): JsonResponse
    {
        $fact = CareerFact::query()->where('owner_id', $request->user()->id)->findOrFail($id);
        $data = $request->validate([
            'fact_type' => ['required', 'string', Rule::enum(CareerFactType::class)],
            'assertion' => ['required', 'string', 'max:1000'],
        ]);

        return response()->json(['data' => $service->supersede(
            $request->user(),
            $fact,
            $data['fact_type'],
            trim($data['assertion']),
        )], 201);
    }

    public function resolveClaim(Request $request, string $id, ClaimResolutionService $service): JsonResponse
    {
        $claim = Claim::query()->where('owner_id', $request->user()->id)->findOrFail($id);
        $data = $request->validate([
            'career_fact_id' => ['required', 'ulid'],
        ]);
        $selectedFact = CareerFact::query()
            ->where('owner_id', $request->user()->id)
            ->findOrFail($data['career_fact_id']);

        return response()->json(['data' => $service->resolve($request->user(), $claim, $selectedFact)]);
    }

    /** @return array<string, mixed> */
    private function sourceSummary(CareerSource $source): array
    {
        return [
            'id' => $source->id,
            'kind' => $source->kind,
            'extraction_status' => $source->extraction_status,
            'error_code' => $source->error_code,
            'created_at' => $source->created_at,
        ];
    }
}
