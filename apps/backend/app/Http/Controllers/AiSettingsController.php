<?php

namespace App\Http\Controllers;

use App\AI\Providers\GeminiResponsesProvider;
use App\AI\Providers\OpenAiResponsesProvider;
use App\Models\User;
use App\Models\UserAiSetting;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Validation\ValidationException;

class AiSettingsController extends Controller
{
    public function __construct(
        private readonly GeminiResponsesProvider $gemini,
        private readonly OpenAiResponsesProvider $openai,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        $setting = $user
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
            'default_provider' => ['nullable', 'string', 'in:openai,gemini,system_default'],
            'gemini_api_key' => ['nullable', 'string', 'max:512'],
            'clear_gemini_key' => ['nullable', 'boolean'],
            'gemini_model' => ['nullable', 'string', 'max:128'],
            'openai_api_key' => ['nullable', 'string', 'max:512'],
            'clear_openai_key' => ['nullable', 'boolean'],
            'openai_model' => ['nullable', 'string', 'max:128'],
        ]);

        if (! empty($validated['clear_gemini_key']) && isset($validated['gemini_api_key']) && trim((string) $validated['gemini_api_key']) !== '') {
            throw ValidationException::withMessages([
                'clear_gemini_key' => 'Cannot specify a new Gemini API key and clear_gemini_key simultaneously.',
            ]);
        }

        if (! empty($validated['clear_openai_key']) && isset($validated['openai_api_key']) && trim((string) $validated['openai_api_key']) !== '') {
            throw ValidationException::withMessages([
                'clear_openai_key' => 'Cannot specify a new OpenAI API key and clear_openai_key simultaneously.',
            ]);
        }

        $setting = UserAiSetting::query()->firstOrNew(['owner_id' => $user->id]);

        $selectedProvider = $validated['selected_provider'] ?? $validated['default_provider'] ?? null;
        if ($request->has('selected_provider') || $request->has('default_provider')) {
            $setting->selected_provider = ($selectedProvider === 'system_default' || $selectedProvider === null) ? null : $selectedProvider;
        }

        $clearGemini = ! empty($validated['clear_gemini_key'])
            || ($request->has('gemini_api_key') && ($request->input('gemini_api_key') === null || trim((string) $request->input('gemini_api_key')) === ''));
        if ($clearGemini) {
            $setting->gemini_api_key = null;
        } elseif (isset($validated['gemini_api_key']) && trim($validated['gemini_api_key']) !== '') {
            $setting->gemini_api_key = trim($validated['gemini_api_key']);
        }

        if (array_key_exists('gemini_model', $validated)) {
            $setting->gemini_model = filled($validated['gemini_model']) ? trim((string) $validated['gemini_model']) : null;
        }

        $clearOpenai = ! empty($validated['clear_openai_key'])
            || ($request->has('openai_api_key') && ($request->input('openai_api_key') === null || trim((string) $request->input('openai_api_key')) === ''));
        if ($clearOpenai) {
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
        $setting = $user
            ? UserAiSetting::query()->where('owner_id', $user->id)->first()
            : null;

        $provider = $validated['provider'];
        $model = $validated['model'] ?? null;

        try {
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
        } catch (\Throwable $e) {
            $result = [
                'success' => false,
                'status' => 'failed',
                'provider' => $provider,
                'model' => (string) $model,
                'error_code' => 'INTERNAL_ERROR',
                'message' => 'Connection test failed: '.$e->getMessage(),
                'recommendation' => 'Check provider settings and credentials.',
            ];
        }

        return response()->json($result);
    }

    public function models(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'provider' => ['required', 'string', 'in:gemini,openai'],
            'api_key' => ['nullable', 'string', 'max:512'],
        ]);

        $user = $request->user();
        $setting = $user
            ? UserAiSetting::query()->where('owner_id', $user->id)->first()
            : null;

        $provider = $validated['provider'];
        $apiKey = isset($validated['api_key']) && trim($validated['api_key']) !== ''
            ? trim($validated['api_key'])
            : ($provider === 'gemini' ? $setting?->gemini_api_key : $setting?->openai_api_key);

        $models = $provider === 'gemini'
            ? $this->gemini->listModels($apiKey)
            : [];

        if (empty($models)) {
            $models = $provider === 'gemini'
                ? config('ai.providers.gemini.available_models', [
                    ['id' => 'gemini-1.5-flash', 'name' => 'Gemini 1.5 Flash (Fast & Cost-effective)'],
                    ['id' => 'gemini-1.5-pro', 'name' => 'Gemini 1.5 Pro (Complex Reasoning)'],
                    ['id' => 'gemini-2.0-flash', 'name' => 'Gemini 2.0 Flash (Next-gen Fast)'],
                ])
                : [
                    ['id' => 'gpt-4o-mini', 'name' => 'GPT-4o mini'],
                    ['id' => 'gpt-4o', 'name' => 'GPT-4o'],
                ];
        }

        return response()->json([
            'data' => [
                'provider' => $provider,
                'models' => $models,
            ],
        ]);
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

        $geminiModel = $setting?->gemini_model ?: (string) config('ai.providers.gemini.default_model', 'gemini-1.5-flash');
        $openaiModel = $setting?->openai_model ?: (string) config('ai.providers.openai.default_model', 'gpt-4o-mini');

        $geminiAvailableModelObjects = null;
        if ($hasUserGeminiKey || $hasSystemGeminiKey) {
            $cacheKey = 'gemini_models_list_'.md5((string) ($setting?->gemini_api_key ?: config('ai.providers.gemini.api_key')));
            /** @var mixed $cached */
            $cached = Cache::get($cacheKey);
            if (is_array($cached) && ! empty($cached)) {
                /** @var list<array{id: string, name: string, description?: string}> $cached */
                $geminiAvailableModelObjects = $cached;
            }
        }
        if (empty($geminiAvailableModelObjects)) {
            $geminiAvailableModelObjects = config('ai.providers.gemini.available_models', [
                ['id' => 'gemini-1.5-flash', 'name' => 'Gemini 1.5 Flash (Fast & Cost-effective)'],
                ['id' => 'gemini-1.5-pro', 'name' => 'Gemini 1.5 Pro (Complex Reasoning)'],
                ['id' => 'gemini-2.0-flash', 'name' => 'Gemini 2.0 Flash (Next-gen Fast)'],
            ]);
        }
        /** @var list<string> $geminiAvailableModelIds */
        $geminiAvailableModelIds = array_column((array) $geminiAvailableModelObjects, 'id');

        $openaiAvailableModelObjects = [
            ['id' => 'gpt-4o-mini', 'name' => 'GPT-4o mini'],
            ['id' => 'gpt-4o', 'name' => 'GPT-4o'],
        ];
        $openaiAvailableModelIds = array_column($openaiAvailableModelObjects, 'id');

        $maskKey = function (?string $key): ?string {
            if ($key === null || trim($key) === '') {
                return null;
            }
            $len = strlen($key);
            if ($len <= 8) {
                return str_repeat('•', $len);
            }

            return substr($key, 0, 6).'...'.substr($key, -4);
        };

        $geminiMaskedKey = $maskKey($setting?->gemini_api_key);
        $openaiMaskedKey = $maskKey($setting?->openai_api_key);

        return [
            'selected_provider' => $selectedProvider ?? 'system_default',
            'active_provider' => $activeProvider,
            'default_provider' => $activeProvider,
            'providers' => [
                'gemini' => [
                    'id' => 'gemini',
                    'name' => 'Google Gemini',
                    'configured' => $hasUserGeminiKey || $hasSystemGeminiKey,
                    'has_user_key' => $hasUserGeminiKey,
                    'has_system_key' => $hasSystemGeminiKey,
                    'has_key' => $hasUserGeminiKey,
                    'masked_key' => $geminiMaskedKey,
                    'model' => $geminiModel,
                    'default_model' => $geminiModel,
                    'available_models' => $geminiAvailableModelObjects,
                    'env_key_configured' => $hasSystemGeminiKey,
                ],
                'openai' => [
                    'id' => 'openai',
                    'name' => 'OpenAI',
                    'configured' => $hasUserOpenAiKey || $hasSystemOpenAiKey,
                    'has_user_key' => $hasUserOpenAiKey,
                    'has_system_key' => $hasSystemOpenAiKey,
                    'has_key' => $hasUserOpenAiKey,
                    'masked_key' => $openaiMaskedKey,
                    'model' => $openaiModel,
                    'default_model' => $openaiModel,
                    'available_models' => $openaiAvailableModelObjects,
                    'env_key_configured' => $hasSystemOpenAiKey,
                ],
            ],
            'gemini' => [
                'has_key' => $hasUserGeminiKey,
                'masked_key' => $geminiMaskedKey,
                'default_model' => $geminiModel,
                'available_models' => $geminiAvailableModelIds,
                'env_key_configured' => $hasSystemGeminiKey,
            ],
            'openai' => [
                'has_key' => $hasUserOpenAiKey,
                'masked_key' => $openaiMaskedKey,
                'default_model' => $openaiModel,
                'available_models' => $openaiAvailableModelIds,
                'env_key_configured' => $hasSystemOpenAiKey,
            ],
        ];
    }
}
