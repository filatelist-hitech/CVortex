<?php

namespace App\AI\Providers;

use App\AI\Contracts\LlmProvider;
use App\AI\Data\LlmRequest;
use App\AI\Data\LlmResponse;
use App\AI\Exceptions\LlmProviderException;
use App\AI\ModelPolicyResolver;
use App\Models\User;
use App\Models\UserAiSetting;
use Illuminate\Support\Facades\Schema;

class ConfiguredLlmProvider implements LlmProvider
{
    public function __construct(
        private readonly ModelPolicyResolver $resolver,
        private readonly OpenAiResponsesProvider $openAi,
        private readonly GeminiResponsesProvider $gemini,
    ) {}

    public function generateStructured(LlmRequest $request): LlmResponse
    {
        $resolved = $this->resolver->resolve($request->modelPolicy);
        $byokKey = $this->resolveByokKey($resolved->provider);

        return match ($resolved->provider) {
            'openai' => $this->openAi->generateResolved($request, $resolved, $byokKey),
            'gemini' => $this->gemini->generateResolved($request, $resolved, $byokKey),
            default => throw new LlmProviderException(
                LlmProviderException::NOT_CONFIGURED,
                'The resolved LLM provider is not available.',
                $resolved->provider,
                $resolved->model,
            ),
        };
    }

    private function resolveByokKey(string $provider): ?string
    {
        $user = auth()->user();
        if (! $user instanceof User) {
            return null;
        }

        if (! Schema::hasTable('user_ai_settings')) {
            return null;
        }

        $setting = UserAiSetting::query()->where('owner_id', $user->id)->first();
        if (! $setting) {
            return null;
        }

        return match ($provider) {
            'gemini' => $setting->gemini_api_key,
            'openai' => $setting->openai_api_key,
            default => null,
        };
    }
}
