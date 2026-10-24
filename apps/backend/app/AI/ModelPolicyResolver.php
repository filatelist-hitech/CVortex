<?php

namespace App\AI;

use App\AI\Data\ModelPolicy;
use App\AI\Data\ResolvedModel;
use App\AI\Exceptions\LlmProviderException;
use App\AI\Exceptions\ModelSelectionException;
use App\Models\User;
use App\Models\UserAiSetting;
use Illuminate\Support\Facades\Schema;

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

    public function resolve(ModelPolicy $policy, ?User $user = null): ResolvedModel
    {
        $user ??= auth()->user();
        if ($user instanceof User && Schema::hasTable('user_ai_settings')) {
            $userSetting = UserAiSetting::query()->where('owner_id', $user->id)->first();
            if ($userSetting && filled($userSetting->selected_provider)) {
                return $this->resolveForUserProvider($policy, $userSetting->selected_provider, $userSetting);
            }
        }

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

    private function resolveForUserProvider(ModelPolicy $policy, string $provider, UserAiSetting $setting): ResolvedModel
    {
        if ($provider === 'gemini') {
            $model = filled($setting->gemini_model)
                ? (string) $setting->gemini_model
                : match ($policy->id) {
                    'low_cost_structured_extraction' => (string) config('ai.providers.gemini.extraction_model', 'gemini-1.5-flash'),
                    'standard_semantic_generation' => (string) config('ai.providers.gemini.generation_model', 'gemini-1.5-flash'),
                    default => (string) config('ai.providers.gemini.default_model', 'gemini-1.5-flash'),
                };

            return new ResolvedModel(
                policyId: $policy->id,
                provider: 'gemini',
                model: $model,
                inputCostMicrosPerMillionTokens: is_int(config('ai.model_policies.'.$policy->id.'.input_cost_micros_per_million_tokens'))
                    ? (int) config('ai.model_policies.'.$policy->id.'.input_cost_micros_per_million_tokens') : null,
                outputCostMicrosPerMillionTokens: is_int(config('ai.model_policies.'.$policy->id.'.output_cost_micros_per_million_tokens'))
                    ? (int) config('ai.model_policies.'.$policy->id.'.output_cost_micros_per_million_tokens') : null,
            );
        }

        if ($provider === 'openai') {
            $mapping = config('ai.model_policies.'.$policy->id);
            $model = filled($setting->openai_model)
                ? (string) $setting->openai_model
                : (is_string($mapping['model'] ?? null) && trim($mapping['model']) !== ''
                    ? $mapping['model']
                    : (string) config('ai.providers.openai.default_model', 'gpt-4o-mini'));

            return new ResolvedModel(
                policyId: $policy->id,
                provider: 'openai',
                model: $model,
                inputCostMicrosPerMillionTokens: is_int($mapping['input_cost_micros_per_million_tokens'] ?? null)
                    ? $mapping['input_cost_micros_per_million_tokens'] : null,
                outputCostMicrosPerMillionTokens: is_int($mapping['output_cost_micros_per_million_tokens'] ?? null)
                    ? $mapping['output_cost_micros_per_million_tokens'] : null,
            );
        }

        throw new LlmProviderException(
            LlmProviderException::INVALID_CONFIGURATION,
            'The selected AI provider is unsupported.',
            $provider,
        );
    }
}
