<?php

namespace App\Http\Controllers;

use App\AI\Providers\GeminiResponsesProvider;
use App\AI\Providers\OpenAiResponsesProvider;
use App\Models\User;
use App\Models\UserAiSetting;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;

class AiSettingsController extends Controller
{
    public function __construct(
        private readonly GeminiResponsesProvider $gemini,
        private readonly OpenAiResponsesProvider $openai,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        $setting = $user && Schema::hasTable('user_ai_settings')
            ? UserAiSetting::query()->where('owner_id', $user->id)->first()
            : null;

        return response()->json([
            'data' => $this->formatSettings($setting),
        ]);
    }

    public function update(Request $request): JsonResponse
    {
        $user = $request->user();
        if (! $user instanceof User) {
            return response()->json(['message' => 'Unauthenticated.'], 401);
        }

        $validated = $request->validate([
            'selected_provider' => ['nullable', 'string', 'in:openai,gemini,system_default'],
            'gemini_api_key' => ['nullable', 'string', 'max:512'],
            'clear_gemini_key' => ['nullable', 'boolean'],
            'gemini_model' => ['nullable', 'string', 'max:128'],
            'openai_api_key' => ['nullable', 'string', 'max:512'],
            'clear_openai_key' => ['nullable', 'boolean'],
            'openai_model' => ['nullable', 'string', 'max:128'],
        ]);

        $setting = UserAiSetting::query()->firstOrNew(['owner_id' => $user->id]);

        if (array_key_exists('selected_provider', $validated)) {
            $provider = $validated['selected_provider'];
            $setting->selected_provider = ($provider === 'system_default' || $provider === null) ? null : $provider;
        }

        if (! empty($validated['clear_gemini_key'])) {
            $setting->gemini_api_key = null;
        } elseif (isset($validated['gemini_api_key']) && trim($validated['gemini_api_key']) !== '') {
            $setting->gemini_api_key = trim($validated['gemini_api_key']);
        }

        if (array_key_exists('gemini_model', $validated)) {
            $setting->gemini_model = filled($validated['gemini_model']) ? trim((string) $validated['gemini_model']) : null;
        }

        if (! empty($validated['clear_openai_key'])) {
            $setting->openai_api_key = null;
        } elseif (isset($validated['openai_api_key']) && trim($validated['openai_api_key']) !== '') {
            $setting->openai_api_key = trim($validated['openai_api_key']);
        }

        if (array_key_exists('openai_model', $validated)) {
            $setting->openai_model = filled($validated['openai_model']) ? trim((string) $validated['openai_model']) : null;
        }

        $setting->save();

        return response()->json([
            'data' => $this->formatSettings($setting),
        ]);
    }

    public function testConnection(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'provider' => ['required', 'string', 'in:gemini,openai'],
            'api_key' => ['nullable', 'string', 'max:512'],
            'model' => ['nullable', 'string', 'max:128'],
        ]);

        $user = $request->user();
        $setting = $user && Schema::hasTable('user_ai_settings')
            ? UserAiSetting::query()->where('owner_id', $user->id)->first()
            : null;

        $provider = $validated['provider'];
        $model = $validated['model'] ?? null;

        if ($provider === 'gemini') {
            $apiKey = isset($validated['api_key']) && trim($validated['api_key']) !== ''
                ? trim($validated['api_key'])
                : $setting?->gemini_api_key;

            $result = $this->gemini->testConnection($apiKey, $model);
        } else {
            $apiKey = isset($validated['api_key']) && trim($validated['api_key']) !== ''
                ? trim($validated['api_key'])
                : $setting?->openai_api_key;

            $result = $this->openai->testConnection($apiKey, $model);
        }

        return response()->json($result);
    }

    /**
     * @return array<string, mixed>
     */
    private function formatSettings(?UserAiSetting $setting): array
    {
        $hasUserGeminiKey = filled($setting?->gemini_api_key);
        $hasSystemGeminiKey = filled(config('ai.providers.gemini.api_key'));
        $hasUserOpenAiKey = filled($setting?->openai_api_key);
        $hasSystemOpenAiKey = filled(config('ai.providers.openai.api_key'));

        $defaultProvider = (string) config('ai.default_provider', 'openai');
        $selectedProvider = $setting?->selected_provider;
        $activeProvider = $selectedProvider ?: $defaultProvider;

        return [
            'selected_provider' => $selectedProvider ?? 'system_default',
            'active_provider' => $activeProvider,
            'default_provider' => $defaultProvider,
            'providers' => [
                'gemini' => [
                    'id' => 'gemini',
                    'name' => 'Google Gemini',
                    'configured' => $hasUserGeminiKey || $hasSystemGeminiKey,
                    'has_user_key' => $hasUserGeminiKey,
                    'has_system_key' => $hasSystemGeminiKey,
                    'model' => $setting?->gemini_model ?: config('ai.providers.gemini.default_model', 'gemini-1.5-flash'),
                    'available_models' => config('ai.providers.gemini.available_models', [
                        ['id' => 'gemini-1.5-flash', 'name' => 'Gemini 1.5 Flash (Fast & Cost-effective)'],
                        ['id' => 'gemini-1.5-pro', 'name' => 'Gemini 1.5 Pro (Complex Reasoning)'],
                        ['id' => 'gemini-2.0-flash', 'name' => 'Gemini 2.0 Flash (Next-gen Fast)'],
                    ]),
                ],
                'openai' => [
                    'id' => 'openai',
                    'name' => 'OpenAI',
                    'configured' => $hasUserOpenAiKey || $hasSystemOpenAiKey,
                    'has_user_key' => $hasUserOpenAiKey,
                    'has_system_key' => $hasSystemOpenAiKey,
                    'model' => $setting?->openai_model ?: config('ai.providers.openai.default_model', 'gpt-4o-mini'),
                    'available_models' => [
                        ['id' => 'gpt-4o-mini', 'name' => 'GPT-4o mini'],
                        ['id' => 'gpt-4o', 'name' => 'GPT-4o'],
                    ],
                ],
            ],
        ];
    }
}
