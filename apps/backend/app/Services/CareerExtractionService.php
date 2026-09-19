<?php

namespace App\Services;

use App\AI\Contracts\LlmProvider;
use App\AI\Data\LlmRequest;
use App\AI\Data\ModelPolicy;
use App\AI\Exceptions\CareerOutputException;
use App\AI\Exceptions\LlmProviderException;
use App\AI\RuntimeSkillRegistry;
use App\Exceptions\SafeCareerException;
use App\Models\CareerFact;
use App\Models\CareerFactType;
use App\Models\CareerSource;
use App\Models\LlmRun;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

class CareerExtractionService
{
    public function __construct(
        private readonly LlmProvider $provider,
        private readonly CareerFactService $facts,
        private readonly RuntimeSkillRegistry $skills,
        private readonly CareerSemanticValidator $semantics,
    ) {}

    public function extract(User $user, string $sourceText): CareerSource
    {
        try {
            return $this->performExtraction($user, $sourceText);
        } catch (CareerOutputException|LlmProviderException $exception) {
            throw $exception;
        } catch (QueryException) {
            throw new SafeCareerException;
        }
    }

    private function performExtraction(User $user, string $sourceText): CareerSource
    {
        $sourceText = trim($sourceText);
        $hash = hash('sha256', $sourceText);
        $profile = $this->facts->profileFor($user);
        $source = CareerSource::query()->firstOrCreate(
            ['owner_id' => $user->id, 'content_hash' => $hash],
            [
                'career_profile_id' => $profile->id,
                'kind' => 'PASTED_TEXT',
                'source_text' => $sourceText,
                'extraction_status' => CareerSource::STATUS_PENDING,
            ],
        );

        if (! $source->wasRecentlyCreated && $source->extraction_status === CareerSource::STATUS_COMPLETED) {
            return $source;
        }

        $claimed = CareerSource::query()->whereKey($source->id)
            ->whereIn('extraction_status', [CareerSource::STATUS_PENDING, CareerSource::STATUS_FAILED])
            ->update(['extraction_status' => CareerSource::STATUS_RUNNING, 'error_code' => null, 'updated_at' => now()]);
        if ($claimed === 0) {
            return $source->fresh();
        }
        $source->refresh();
        $skill = $this->skills->careerFactExtraction();
        $retryCount = LlmRun::query()->where('career_source_id', $source->id)->count();
        $run = LlmRun::query()->create([
            'owner_id' => $user->id,
            'career_source_id' => $source->id,
            'workflow' => 'career_text_extraction',
            'skill_id' => $skill->id,
            'skill_version' => $skill->version,
            'prompt_version' => $skill->promptVersion,
            'model_policy' => $skill->modelPolicy,
            'status' => 'RUNNING',
            'retry_count' => $retryCount,
        ]);

        try {
            $response = $this->provider->generateStructured(new LlmRequest(
                trustedInstructions: $skill->trustedInstructions,
                untrustedSourceText: $sourceText,
                schema: $skill->outputSchema,
                modelPolicy: new ModelPolicy($skill->modelPolicy, true),
            ));
            $candidates = $this->validateOutput($response->output, $sourceText);

            DB::transaction(function () use ($candidates, $source, $user, $profile, $run, $response, $skill): void {
                CareerFact::query()->where('career_source_id', $source->id)->where('status', CareerFact::STATUS_PENDING)->delete();
                foreach ($candidates as $candidate) {
                    CareerFact::query()->create([
                        'owner_id' => $user->id,
                        'career_profile_id' => $profile->id,
                        'career_source_id' => $source->id,
                        'provenance_type' => CareerFact::PROVENANCE_EXTRACTION,
                        'fact_type' => $candidate['fact_type'],
                        'assertion_original' => $candidate['assertion'],
                        'source_excerpt' => $candidate['source_excerpt'],
                        'extracted_by' => $skill->id.'@'.$skill->version,
                        'extraction_confidence' => $candidate['confidence'],
                        'candidate_hash' => hash('sha256', $candidate['fact_type']."\0".$candidate['assertion']."\0".$candidate['source_excerpt']),
                        'status' => CareerFact::STATUS_PENDING,
                    ]);
                }
                $source->forceFill(['extraction_status' => CareerSource::STATUS_COMPLETED, 'error_code' => null])->save();
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
        } catch (CareerOutputException $exception) {
            $source->forceFill(['extraction_status' => CareerSource::STATUS_FAILED, 'error_code' => 'SCHEMA_INVALID'])->save();
            $run->forceFill([
                'status' => 'FAILED',
                'validation_result' => $exception->category,
                'error_category' => $exception->category,
            ])->save();
            throw $exception;
        } catch (LlmProviderException $exception) {
            $source->forceFill(['extraction_status' => CareerSource::STATUS_FAILED, 'error_code' => 'PROVIDER_ERROR'])->save();
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

        return $source->fresh();
    }

    /** @param array<string, mixed> $output
     * @return list<array{fact_type: string, assertion: string, source_excerpt: string, confidence: float}>
     */
    private function validateOutput(array $output, string $sourceText): array
    {
        $facts = $output['facts'] ?? null;
        if (array_keys($output) !== ['facts'] || ! is_array($facts) || ! array_is_list($facts) || count($facts) > 50) {
            throw new CareerOutputException(CareerOutputException::SCHEMA_INVALID);
        }

        $validated = [];
        foreach ($facts as $candidate) {
            if (! is_array($candidate)
                || count($candidate) !== 4
                || array_diff(['fact_type', 'assertion', 'source_excerpt', 'confidence'], array_keys($candidate)) !== []
                || ! isset($candidate['fact_type'], $candidate['assertion'], $candidate['source_excerpt'], $candidate['confidence'])
                || ! is_string($candidate['fact_type'])
                || ! is_string($candidate['assertion'])
                || trim($candidate['assertion']) === ''
                || mb_strlen($candidate['assertion']) > 1000
                || ! is_string($candidate['source_excerpt'])
                || trim($candidate['source_excerpt']) === ''
                || mb_strlen($candidate['source_excerpt']) > 2000
                || ! is_numeric($candidate['confidence'])
                || (float) $candidate['confidence'] < 0
                || (float) $candidate['confidence'] > 1
                || ! str_contains($sourceText, $candidate['source_excerpt'])
                || ! str_contains($candidate['source_excerpt'], $candidate['assertion'])) {
                throw new CareerOutputException(CareerOutputException::SCHEMA_INVALID);
            }
            $type = CareerFactType::tryFrom($candidate['fact_type']);
            if ($type === null || ! $this->semantics->supports($type, $candidate['assertion'])) {
                throw new CareerOutputException(CareerOutputException::SEMANTIC_REJECTED);
            }
            $validated[] = [
                'fact_type' => $candidate['fact_type'],
                'assertion' => trim($candidate['assertion']),
                'source_excerpt' => $candidate['source_excerpt'],
                'confidence' => (float) $candidate['confidence'],
            ];
        }

        return $validated;
    }
}
