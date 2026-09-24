<?php

namespace App\Services;

use App\AI\Contracts\LlmProvider;
use App\AI\Data\LlmRequest;
use App\AI\Data\LlmResponse;
use App\AI\Data\ModelPolicy;
use App\AI\Data\RuntimeSkillDefinition;
use App\AI\Exceptions\LlmProviderException;
use App\AI\RuntimeSkillRegistry;
use App\Models\ApplicationLlmRun;
use App\Models\ApplicationPreparation;
use App\Models\Claim;
use App\Models\User;

class ApplicationTruthGuard
{
    public function __construct(
        private readonly LlmProvider $provider,
        private readonly RuntimeSkillRegistry $skills,
        private readonly TruthGuard $claims,
    ) {}

    /** @param list<array{assertion: string, claim_ids: list<string>}> $proposedUsages
     * @param  array<string, Claim>  $availableClaims
     * @return array{status: string, usages: list<array{assertion: string, claim_ids: list<string>}>, response: ?LlmResponse, skill: ?RuntimeSkillDefinition}
     */
    public function validate(User $user, ApplicationPreparation $preparation, string $content, array $proposedUsages, array $availableClaims): array
    {
        foreach ($proposedUsages as $usage) {
            foreach ($usage['claim_ids'] as $claimId) {
                $claim = $availableClaims[$claimId] ?? null;
                if ($claim === null || $this->claims->evaluate($claim) !== TruthGuard::PASS) {
                    return ['status' => TruthGuard::BLOCK, 'usages' => [], 'response' => null, 'skill' => null];
                }
            }
        }

        try {
            $skill = $this->skills->applicationTruthReview();
            $run = new ApplicationLlmRun;
            $run->forceFill([
                'owner_id' => $user->id,
                'preparation_id' => $preparation->id,
                'workflow' => 'application_truth_review',
                'skill_id' => $skill->id,
                'skill_version' => $skill->version,
                'prompt_version' => $skill->promptVersion,
                'model_policy' => $skill->modelPolicy,
                'status' => 'RUNNING',
                'retry_count' => ApplicationLlmRun::query()->where('owner_id', $user->id)->where('preparation_id', $preparation->id)->count(),
            ]);
            if (! $run->save()) {
                throw new LlmProviderException(LlmProviderException::PROVIDER, 'Application Truth Guard setup failed safely.');
            }
            $response = $this->provider->generateStructured(new LlmRequest(
                trustedInstructions: $skill->trustedInstructions,
                untrustedSourceText: json_encode([
                    'candidate_content' => $content,
                    'proposed_claim_usages' => $proposedUsages,
                    'available_claims' => array_map(fn (Claim $claim): array => ['id' => (string) $claim->id, 'statement' => $claim->statement], array_values($availableClaims)),
                ], JSON_THROW_ON_ERROR),
                schema: $skill->outputSchema,
                modelPolicy: new ModelPolicy($skill->modelPolicy, true),
                schemaName: 'application_truth_review',
                untrustedDataLabel: 'UNTRUSTED APPLICATION CONTENT AND CLAIM DATA',
            ));
        } catch (LlmProviderException $exception) {
            if (isset($run)) {
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
            }
            throw $exception;
        }

        $output = $response->output;
        if (array_diff(array_keys($output), ['status', 'assertions']) !== [] || array_diff(['status', 'assertions'], array_keys($output)) !== [] || ! in_array($output['status'] ?? null, [
            TruthGuard::PASS,
            TruthGuard::BLOCK,
            TruthGuard::USER_RESOLUTION_REQUIRED,
        ], true) || ! is_array($output['assertions'] ?? null) || ! array_is_list($output['assertions'])) {
            $this->record($run, $response, 'BLOCK');

            return ['status' => TruthGuard::BLOCK, 'usages' => [], 'response' => $response, 'skill' => $skill];
        }

        $usages = [];
        foreach ($output['assertions'] as $assertion) {
            if (! is_array($assertion) || array_diff(array_keys($assertion), ['text', 'claim_ids']) !== []
                || array_diff(['text', 'claim_ids'], array_keys($assertion)) !== []
                || ! is_string($assertion['text']) || trim($assertion['text']) === ''
                || ! str_contains($content, $assertion['text'])
                || ! is_array($assertion['claim_ids']) || ! array_is_list($assertion['claim_ids'])) {
                $this->record($run, $response, 'BLOCK');

                return ['status' => TruthGuard::BLOCK, 'usages' => [], 'response' => $response, 'skill' => $skill];
            }
            $ids = array_values(array_unique(array_map('strval', $assertion['claim_ids'])));
            if ($ids === []) {
                $this->record($run, $response, 'BLOCK');

                return ['status' => TruthGuard::BLOCK, 'usages' => [], 'response' => $response, 'skill' => $skill];
            }
            foreach ($ids as $id) {
                $claim = $availableClaims[$id] ?? null;
                if ($claim === null || $this->claims->evaluate($claim) !== TruthGuard::PASS) {
                    $this->record($run, $response, 'BLOCK');

                    return ['status' => TruthGuard::BLOCK, 'usages' => [], 'response' => $response, 'skill' => $skill];
                }
            }
            $usages[] = ['assertion' => $assertion['text'], 'claim_ids' => $ids];
        }

        if ($output['status'] === TruthGuard::PASS) {
            foreach ($proposedUsages as $usage) {
                $reviewed = collect($usages)->first(fn (array $item): bool => hash_equals($item['assertion'], $usage['assertion']));
                if (! str_contains($content, $usage['assertion']) || $usage['claim_ids'] === []
                    || $reviewed === null || array_diff($usage['claim_ids'], $reviewed['claim_ids']) !== []) {
                    $this->record($run, $response, 'BLOCK');

                    return ['status' => TruthGuard::BLOCK, 'usages' => [], 'response' => $response, 'skill' => $skill];
                }
            }
        }

        $this->record($run, $response, $output['status']);

        return ['status' => $output['status'], 'usages' => $usages, 'response' => $response, 'skill' => $skill];
    }

    private function record(ApplicationLlmRun $run, LlmResponse $response, string $status): void
    {
        $run->forceFill([
            'provider' => $response->provider,
            'model' => $response->model,
            'provider_request_id' => $response->providerRequestId,
            'status' => $status === TruthGuard::PASS ? 'COMPLETED' : 'FAILED',
            'input_tokens' => $response->inputTokens,
            'output_tokens' => $response->outputTokens,
            'latency_ms' => $response->latencyMs,
            'estimated_cost_micros' => $response->estimatedCostMicros,
            'validation_result' => $status,
            'error_category' => $status === TruthGuard::PASS ? null : $status,
        ])->save();
    }
}
