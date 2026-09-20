<?php

namespace App\Services;

use App\AI\Contracts\LlmProvider;
use App\AI\Data\LlmRequest;
use App\AI\Data\ModelPolicy;
use App\AI\Exceptions\LlmProviderException;
use App\AI\Exceptions\VacancyOutputException;
use App\AI\RuntimeSkillRegistry;
use App\Exceptions\SafeVacancyException;
use App\Models\User;
use App\Models\Vacancy;
use App\Models\VacancyAnalysis;
use App\Models\VacancyLlmRun;
use App\Models\VacancyRequirement;
use App\Models\VacancySnapshot;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Throwable;

class VacancyAnalysisService
{
    public function __construct(
        private readonly LlmProvider $provider,
        private readonly RuntimeSkillRegistry $skills,
        private readonly VacancyRequirementValidator $validator,
        private readonly VacancyMatchingService $matching,
        private readonly DatabaseOwnerContext $ownerContext,
    ) {}

    public function analyze(User $user, VacancySnapshot $snapshot): VacancyAnalysis
    {
        return $this->ownerContext->run(
            (string) $user->id,
            fn (): VacancyAnalysis => $this->analyzeForOwner($user, $snapshot),
        );
    }

    private function analyzeForOwner(User $user, VacancySnapshot $snapshot): VacancyAnalysis
    {
        $vacancy = Vacancy::query()->where('owner_id', $user->id)->findOrFail($snapshot->vacancy_id);
        if (! hash_equals((string) $snapshot->owner_id, (string) $user->id)) {
            abort(404);
        }
        $signature = $this->matching->careerSignature($user);
        $existing = VacancyAnalysis::query()
            ->where('owner_id', $user->id)
            ->where('vacancy_snapshot_id', $snapshot->id)
            ->forCareerSignature($signature)
            ->deterministicLatest()
            ->first();
        if ($existing !== null && $vacancy->analysis_status === Vacancy::STATUS_COMPLETED) {
            return $existing;
        }

        $claimed = Vacancy::query()->whereKey($vacancy->id)
            ->whereIn('analysis_status', [Vacancy::STATUS_PENDING, Vacancy::STATUS_FAILED, Vacancy::STATUS_COMPLETED])
            ->update(['analysis_status' => Vacancy::STATUS_RUNNING, 'error_code' => null, 'updated_at' => now()]);
        if ($claimed === 0) {
            $current = VacancyAnalysis::query()->where('owner_id', $user->id)
                ->where('vacancy_snapshot_id', $snapshot->id)->forCareerSignature($signature)
                ->deterministicLatest()->first();
            if ($current !== null) {
                return $current;
            }
            throw new SafeVacancyException;
        }
        $vacancy->refresh();

        try {
            if (! VacancyLlmRun::query()->where('vacancy_snapshot_id', $snapshot->id)->where('status', 'COMPLETED')->exists()) {
                $this->extractRequirements($user, $vacancy, $snapshot);
            }
            $analysis = $this->matching->analyze($user, $vacancy, $snapshot);
            if ($this->isCurrentSnapshot($snapshot)) {
                $vacancy->forceFill(['analysis_status' => Vacancy::STATUS_COMPLETED, 'error_code' => null])->save();
            }

            return $analysis;
        } catch (VacancyOutputException $exception) {
            if ($this->isCurrentSnapshot($snapshot)) {
                $vacancy->forceFill(['analysis_status' => Vacancy::STATUS_FAILED, 'error_code' => 'INVALID_EXTRACTION_RESULT'])->save();
            }
            throw $exception;
        } catch (LlmProviderException $exception) {
            if ($this->isCurrentSnapshot($snapshot)) {
                $vacancy->forceFill(['analysis_status' => Vacancy::STATUS_FAILED, 'error_code' => 'PROVIDER_ERROR'])->save();
            }
            throw $exception;
        } catch (QueryException) {
            if ($this->isCurrentSnapshot($snapshot)) {
                $vacancy->forceFill(['analysis_status' => Vacancy::STATUS_FAILED, 'error_code' => 'ANALYSIS_ERROR'])->save();
            }
            throw new SafeVacancyException;
        }
    }

    private function isCurrentSnapshot(VacancySnapshot $snapshot): bool
    {
        $latestId = VacancySnapshot::query()->where('owner_id', $snapshot->owner_id)
            ->where('vacancy_id', $snapshot->vacancy_id)->latest('version')->value('id');

        return hash_equals((string) $snapshot->id, (string) $latestId);
    }

    private function extractRequirements(User $user, Vacancy $vacancy, VacancySnapshot $snapshot): void
    {
        try {
            $skill = $this->skills->vacancyRequirementExtraction();
            $retryCount = VacancyLlmRun::query()->where('vacancy_snapshot_id', $snapshot->id)->count();
            $run = VacancyLlmRun::query()->create([
                'owner_id' => $user->id,
                'vacancy_snapshot_id' => $snapshot->id,
                'workflow' => 'vacancy_requirement_extraction',
                'skill_id' => $skill->id,
                'skill_version' => $skill->version,
                'prompt_version' => $skill->promptVersion,
                'model_policy' => $skill->modelPolicy,
                'status' => 'RUNNING',
                'retry_count' => $retryCount,
            ]);
        } catch (Throwable $exception) {
            $vacancy->forceFill(['analysis_status' => Vacancy::STATUS_FAILED, 'error_code' => 'SETUP_ERROR'])->save();
            throw $exception;
        }

        try {
            $response = $this->provider->generateStructured(new LlmRequest(
                trustedInstructions: $skill->trustedInstructions,
                untrustedSourceText: $snapshot->raw_text,
                schema: $skill->outputSchema,
                modelPolicy: new ModelPolicy($skill->modelPolicy, true),
                schemaName: 'vacancy_requirements',
                untrustedDataLabel: 'UNTRUSTED VACANCY SOURCE DATA',
            ));
            $requirements = $this->validator->validate($response->output, $snapshot->raw_text);

            DB::transaction(function () use ($requirements, $snapshot, $user, $skill, $run, $response): void {
                VacancyRequirement::query()->where('vacancy_snapshot_id', $snapshot->id)->delete();
                foreach ($requirements as $requirement) {
                    VacancyRequirement::query()->create([
                        'owner_id' => $user->id,
                        'vacancy_snapshot_id' => $snapshot->id,
                        ...$requirement,
                        'extracted_by' => $skill->id.'@'.$skill->version,
                        'candidate_hash' => hash('sha256', implode("\0", [
                            $requirement['dimension'], $requirement['importance'], $requirement['label'], $requirement['source_excerpt'],
                        ])),
                    ]);
                }
                $run->forceFill([
                    'provider' => $response->provider,
                    'model' => $response->model,
                    'provider_request_id' => $response->providerRequestId,
                    'status' => 'COMPLETED',
                    'input_tokens' => $response->inputTokens,
                    'output_tokens' => $response->outputTokens,
                    'latency_ms' => $response->latencyMs,
                    'estimated_cost_micros' => $response->estimatedCostMicros,
                    'validation_result' => 'PASS',
                    'error_category' => null,
                ])->save();
            });
        } catch (VacancyOutputException $exception) {
            $run->forceFill([
                'status' => 'FAILED',
                'validation_result' => $exception->category,
                'error_category' => $exception->category,
            ])->save();
            throw $exception;
        } catch (LlmProviderException $exception) {
            $run->forceFill([
                'provider' => $exception->providerName,
                'model' => $exception->resolvedModel,
                'provider_request_id' => $exception->providerRequestId,
                'status' => 'FAILED',
                'input_tokens' => $exception->inputTokens,
                'output_tokens' => $exception->outputTokens,
                'latency_ms' => $exception->latencyMs,
                'estimated_cost_micros' => $exception->estimatedCostMicros,
                'validation_result' => 'NOT_VALIDATED',
                'error_category' => $exception->category,
            ])->save();
            throw $exception;
        }
    }
}
