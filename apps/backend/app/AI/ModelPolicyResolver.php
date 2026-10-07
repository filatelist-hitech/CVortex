<?php

namespace App\AI;

use App\AI\Data\ModelPolicy;
use App\AI\Data\ResolvedModel;
use App\AI\Exceptions\LlmProviderException;
use App\AI\Exceptions\ModelSelectionException;

class ModelPolicyResolver
{
    public function resolveSelected(ModelPolicy $policy, string $model): ResolvedModel
    {
        $mapping = config('ai.model_policies.'.$policy->id);
        if (! is_array($mapping) || ($mapping['selection'] ?? null) !== 'account_catalog'
            || ($mapping['provider'] ?? null) !== 'openai_chatgpt_plan'
            || preg_match('/\A[A-Za-z0-9._:-]{1,128}\z/D', $model) !== 1) {
            throw new ModelSelectionException(ModelSelectionException::NOT_ALLOWED);
        }

        return new ResolvedModel(policyId: $policy->id, provider: $mapping['provider'], model: $model,
            inputCostMicrosPerMillionTokens: null, outputCostMicrosPerMillionTokens: null);
    }

    /** @param list<array{slug?: mixed}> $availableModels */
    public function assertAvailable(ResolvedModel $resolved, array $availableModels): void
    {
        $slugs = array_column($availableModels, 'slug');
        if (! in_array($resolved->model, $slugs, true)) {
            throw new ModelSelectionException(ModelSelectionException::NOT_AVAILABLE);
        }
    }

    public function resolve(ModelPolicy $policy): ResolvedModel
    {
        $mapping = config('ai.model_policies.'.$policy->id);
        if (! is_array($mapping)
            || ! is_string($mapping['provider'] ?? null)
            || trim($mapping['provider']) === ''
            || $mapping['provider'] === 'none'
            || ! is_string($mapping['model'] ?? null)
            || trim($mapping['model']) === '') {
            throw new LlmProviderException(
                LlmProviderException::NOT_CONFIGURED,
                'No usable LLM mapping is configured for the requested model policy.',
            );
        }

        return new ResolvedModel(
            policyId: $policy->id,
            provider: $mapping['provider'],
            model: $mapping['model'],
            inputCostMicrosPerMillionTokens: is_int($mapping['input_cost_micros_per_million_tokens'] ?? null)
                ? $mapping['input_cost_micros_per_million_tokens'] : null,
            outputCostMicrosPerMillionTokens: is_int($mapping['output_cost_micros_per_million_tokens'] ?? null)
                ? $mapping['output_cost_micros_per_million_tokens'] : null,
        );
    }
}
