<?php

namespace App\AI;

use App\AI\Data\ModelPolicy;
use App\AI\Data\ResolvedModel;
use App\AI\Exceptions\LlmProviderException;
use App\AI\Exceptions\ModelSelectionException;
use App\Models\User;
use App\Models\UserAiSetting;
use App\Settings\SettingsResolver;

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
        $userSetting = ($user instanceof User)
            ? UserAiSetting::query()->where('owner_id', $user->id)->first()
            : null;

        if ($userSetting && filled($userSetting->selected_provider)) {
            return $this->resolveForUserProvider($policy, $userSetting->selected_provider, $userSetting);
        }

        $mapping = config('ai.model_policies.'.$policy->id);
        $dynamicOverrides = $this->resolveDynamicPolicyOverrides($policy);
        $model = $dynamicOverrides['model'] ?? (is_string($mapping['model'] ?? null) ? $mapping['model'] : null);

        if (! is_array($mapping)
            || ! is_string($mapping['provider'] ?? null)
            || trim($mapping['provider']) === ''
            || $mapping['provider'] === 'none'
            || ! is_string($model)
            || trim($model) === '') {
            throw new LlmProviderException(
                LlmProviderException::NOT_CONFIGURED,
                'No usable LLM mapping is configured for the requested model policy.',
            );
        }

        $byokKey = match ($mapping['provider']) {
            'gemini' => $userSetting?->gemini_api_key,
            'openai' => $userSetting?->openai_api_key,
            default => null,
        };

        $inputCost = $dynamicOverrides['input_cost_micros'] ?? (is_int($mapping['input_cost_micros_per_million_tokens'] ?? null)
            ? $mapping['input_cost_micros_per_million_tokens'] : null);
        $outputCost = $dynamicOverrides['output_cost_micros'] ?? (is_int($mapping['output_cost_micros_per_million_tokens'] ?? null)
            ? $mapping['output_cost_micros_per_million_tokens'] : null);

        return new ResolvedModel(
            policyId: $policy->id,
            provider: $mapping['provider'],
            model: $model,
            inputCostMicrosPerMillionTokens: $inputCost,
            outputCostMicrosPerMillionTokens: $outputCost,
            apiKey: $byokKey,
        );
    }

    private function resolveForUserProvider(ModelPolicy $policy, string $provider, UserAiSetting $setting): ResolvedModel
    {
        if ($provider === 'gemini') {
            $defaultModel = match ($policy->id) {
                'low_cost_structured_extraction' => (string) config('ai.providers.gemini.extraction_model', 'gemini-1.5-flash'),
                'standard_semantic_generation' => (string) config('ai.providers.gemini.generation_model', 'gemini-1.5-flash'),
                default => (string) config('ai.providers.gemini.default_model', 'gemini-1.5-flash'),
            };

            $model = filled($setting->gemini_model)
                ? (string) $setting->gemini_model
                : $defaultModel;

            $isDefaultModel = ($model === $defaultModel);

            $perModelInput = config('ai.providers.gemini.models.'.$model.'.input_cost_micros_per_million_tokens');
            $perModelOutput = config('ai.providers.gemini.models.'.$model.'.output_cost_micros_per_million_tokens');

            if (is_int($perModelInput)) {
                $inputCost = $perModelInput;
            } elseif ($isDefaultModel) {
                $rawInput = config('ai.providers.gemini.input_cost_micros_per_million_tokens');
                if ($rawInput === null && config('ai.model_policies.'.$policy->id.'.provider') === 'gemini') {
                    $rawInput = config('ai.model_policies.'.$policy->id.'.input_cost_micros_per_million_tokens');
                }
                $inputCost = is_int($rawInput) ? $rawInput : null;
            } else {
                $inputCost = null;
            }

            if (is_int($perModelOutput)) {
                $outputCost = $perModelOutput;
            } elseif ($isDefaultModel) {
                $rawOutput = config('ai.providers.gemini.output_cost_micros_per_million_tokens');
                if ($rawOutput === null && config('ai.model_policies.'.$policy->id.'.provider') === 'gemini') {
                    $rawOutput = config('ai.model_policies.'.$policy->id.'.output_cost_micros_per_million_tokens');
                }
                $outputCost = is_int($rawOutput) ? $rawOutput : null;
            } else {
                $outputCost = null;
            }

            return new ResolvedModel(
                policyId: $policy->id,
                provider: 'gemini',
                model: $model,
                inputCostMicrosPerMillionTokens: $inputCost,
                outputCostMicrosPerMillionTokens: $outputCost,
                apiKey: $setting->gemini_api_key,
            );
        }

        if ($provider === 'openai') {
            $mapping = config('ai.model_policies.'.$policy->id);
            $dynamicOverrides = $this->resolveDynamicPolicyOverrides($policy);
            $defaultModel = $dynamicOverrides['model']
                ?? (is_string($mapping['model'] ?? null) && trim($mapping['model']) !== ''
                    ? $mapping['model']
                    : (string) config('ai.providers.openai.default_model', 'gpt-4o-mini'));

            $model = filled($setting->openai_model)
                ? (string) $setting->openai_model
                : $defaultModel;

            $isDefaultModel = ($model === $defaultModel);

            $perModelInput = config('ai.providers.openai.models.'.$model.'.input_cost_micros_per_million_tokens');
            $perModelOutput = config('ai.providers.openai.models.'.$model.'.output_cost_micros_per_million_tokens');

            if (is_int($perModelInput)) {
                $inputCost = $perModelInput;
            } elseif ($isDefaultModel) {
                $inputCost = $dynamicOverrides['input_cost_micros'] ?? (($mapping['provider'] ?? null) === 'openai'
                    ? ($mapping['input_cost_micros_per_million_tokens'] ?? null)
                    : match ($policy->id) {
                        'low_cost_structured_extraction' => config('ai.providers.openai.extraction_input_cost_micros'),
                        'standard_semantic_generation' => config('ai.providers.openai.draft_input_cost_micros'),
                        default => null,
                    });
                $inputCost = is_int($inputCost) ? $inputCost : null;
            } else {
                $inputCost = null;
            }

            if (is_int($perModelOutput)) {
                $outputCost = $perModelOutput;
            } elseif ($isDefaultModel) {
                $outputCost = $dynamicOverrides['output_cost_micros'] ?? (($mapping['provider'] ?? null) === 'openai'
                    ? ($mapping['output_cost_micros_per_million_tokens'] ?? null)
                    : match ($policy->id) {
                        'low_cost_structured_extraction' => config('ai.providers.openai.extraction_output_cost_micros'),
                        'standard_semantic_generation' => config('ai.providers.openai.draft_output_cost_micros'),
                        default => null,
                    });
                $outputCost = is_int($outputCost) ? $outputCost : null;
            } else {
                $outputCost = null;
            }

            return new ResolvedModel(
                policyId: $policy->id,
                provider: 'openai',
                model: $model,
                inputCostMicrosPerMillionTokens: $inputCost,
                outputCostMicrosPerMillionTokens: $outputCost,
                apiKey: $setting->openai_api_key,
            );
        }

        throw new LlmProviderException(
            LlmProviderException::INVALID_CONFIGURATION,
            'The selected AI provider is unsupported.',
            $provider,
        );
    }

    /**
     * @return array{model: string|null, input_cost_micros: int|null, output_cost_micros: int|null}
     */
    private function resolveDynamicPolicyOverrides(ModelPolicy $policy): array
    {
        if (! app()->bound(SettingsResolver::class)) {
            return ['model' => null, 'input_cost_micros' => null, 'output_cost_micros' => null];
        }

        /** @var SettingsResolver $settings */
        $settings = app(SettingsResolver::class);
        $prefix = match ($policy->id) {
            'low_cost_structured_extraction' => 'ai.model_policies.low_cost',
            'standard_semantic_generation' => 'ai.model_policies.standard',
            default => null,
        };

        if ($prefix === null) {
            return ['model' => null, 'input_cost_micros' => null, 'output_cost_micros' => null];
        }

        $model = $settings->get("{$prefix}.model");
        $inputCost = $settings->get("{$prefix}.input_cost_micros");
        $outputCost = $settings->get("{$prefix}.output_cost_micros");

        return [
            'model' => is_string($model) && trim($model) !== '' ? trim($model) : null,
            'input_cost_micros' => is_int($inputCost) ? $inputCost : null,
            'output_cost_micros' => is_int($outputCost) ? $outputCost : null,
        ];
    }
}
