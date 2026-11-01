<?php

namespace App\AI\Providers;

use App\AI\Data\LlmRequest;
use App\AI\Data\LlmResponse;
use App\AI\Data\ResolvedModel;
use App\AI\Exceptions\LlmProviderException;
use App\AI\ProviderRetryAfter;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use JsonException;
use Throwable;

class GeminiResponsesProvider
{
    public function generateResolved(
        LlmRequest $request,
        ResolvedModel $resolved,
        ?string $apiKeyOverride = null,
    ): LlmResponse {
        $apiKey = $apiKeyOverride !== null && trim($apiKeyOverride) !== ''
            ? trim($apiKeyOverride)
            : (string) config('ai.providers.gemini.api_key');

        $model = $resolved->model;
        if ($apiKey === '' || $model === '') {
            throw new LlmProviderException(
                LlmProviderException::NOT_CONFIGURED,
                'The Gemini provider is not fully configured.',
                'gemini',
                $model,
            );
        }

        $startedAt = hrtime(true);
        $payload = [
            'contents' => [
                [
                    'role' => 'user',
                    'parts' => [
                        [
                            'text' => $request->untrustedDataLabel.":\n".$request->untrustedSourceText,
                        ],
                    ],
                ],
            ],
            'generationConfig' => [
                'responseMimeType' => 'application/json',
                'responseSchema' => $this->sanitizeSchema($request->schema),
            ],
        ];

        if (trim($request->trustedInstructions) !== '') {
            $payload['systemInstruction'] = [
                'parts' => [
                    ['text' => $request->trustedInstructions],
                ],
            ];
        }

        $baseUrl = rtrim((string) config('ai.providers.gemini.base_url', 'https://generativelanguage.googleapis.com/v1beta'), '/');
        $timeout = (int) config('ai.providers.gemini.timeout_seconds', 45);
        $normalizedModel = preg_replace('#^models/#', '', trim($model));
        $proxy = config('ai.providers.gemini.proxy', config('ai.proxy'));

        try {
            $client = Http::baseUrl($baseUrl)
                ->withHeaders([
                    'x-goog-api-key' => $apiKey,
                ])
                ->acceptJson()
                ->timeout($timeout);

            if (filled($proxy)) {
                $client = $client->withOptions(['proxy' => (string) $proxy]);
            }

            $response = $client->post('/models/'.urlencode($normalizedModel).':generateContent', $payload);
        } catch (ConnectionException $exception) {
            throw new LlmProviderException(
                LlmProviderException::TRANSPORT,
                'The Gemini provider could not be reached.',
                'gemini',
                $model,
                $exception,
                latencyMs: $this->elapsedMilliseconds($startedAt),
            );
        }

        $latencyMs = $this->elapsedMilliseconds($startedAt);
        $requestId = $response->header('x-goog-request-id') ?: $response->header('x-request-id');
        $body = $response->json();

        $inputTokens = is_array($body) && is_int($body['usageMetadata']['promptTokenCount'] ?? null)
            ? $body['usageMetadata']['promptTokenCount']
            : null;
        $outputTokens = is_array($body) && is_int($body['usageMetadata']['candidatesTokenCount'] ?? null)
            ? $body['usageMetadata']['candidatesTokenCount']
            : null;
        $estimatedCostMicros = $this->estimateCost($resolved, $inputTokens, $outputTokens);

        if (! $response->successful()) {
            $status = $response->status();
            $category = match (true) {
                $status === 429 => LlmProviderException::RATE_LIMITED,
                $status === 408 || $status === 425 || $status >= 500 => LlmProviderException::TEMPORARY_UNAVAILABLE,
                $status === 400 || $status === 401 || $status === 403 => LlmProviderException::INVALID_CONFIGURATION,
                default => LlmProviderException::PROVIDER,
            };

            $retryAfterSeconds = in_array($category, [LlmProviderException::RATE_LIMITED, LlmProviderException::TEMPORARY_UNAVAILABLE], true)
                ? ProviderRetryAfter::parse($response->header('retry-after'))
                : null;

            throw new LlmProviderException(
                $category,
                'The Gemini provider returned an unsuccessful response.',
                'gemini',
                $model,
                providerRequestId: $requestId,
                inputTokens: $inputTokens,
                outputTokens: $outputTokens,
                latencyMs: $latencyMs,
                estimatedCostMicros: $estimatedCostMicros,
                retryAfterSeconds: $retryAfterSeconds,
            );
        }

        if (! is_array($body)) {
            throw new LlmProviderException(
                LlmProviderException::MALFORMED_OUTPUT,
                'The Gemini provider returned malformed output.',
                'gemini',
                $model,
                providerRequestId: $requestId,
                latencyMs: $latencyMs,
            );
        }

        if (! empty($body['promptFeedback']['blockReason'])) {
            throw new LlmProviderException(
                LlmProviderException::REFUSAL,
                'The Gemini provider blocked the prompt due to safety policy.',
                'gemini',
                $model,
                providerRequestId: $requestId,
                inputTokens: $inputTokens,
                outputTokens: $outputTokens,
                latencyMs: $latencyMs,
                estimatedCostMicros: $estimatedCostMicros,
            );
        }

        $candidates = $body['candidates'] ?? [];
        if (! is_array($candidates) || empty($candidates)) {
            throw new LlmProviderException(
                LlmProviderException::INCOMPLETE,
                'The Gemini provider returned an empty response.',
                'gemini',
                $model,
                providerRequestId: $requestId,
                inputTokens: $inputTokens,
                outputTokens: $outputTokens,
                latencyMs: $latencyMs,
                estimatedCostMicros: $estimatedCostMicros,
            );
        }

        $candidate = $candidates[0];
        $finishReason = (string) ($candidate['finishReason'] ?? 'STOP');
        if (in_array($finishReason, ['SAFETY', 'RECITATION', 'BLOCKLIST'], true)) {
            throw new LlmProviderException(
                LlmProviderException::REFUSAL,
                'The Gemini provider refused the request.',
                'gemini',
                $model,
                providerRequestId: $requestId,
                inputTokens: $inputTokens,
                outputTokens: $outputTokens,
                latencyMs: $latencyMs,
                estimatedCostMicros: $estimatedCostMicros,
            );
        }

        if ($finishReason === 'MAX_TOKENS') {
            throw new LlmProviderException(
                LlmProviderException::INCOMPLETE,
                'The Gemini provider response reached token limit before completion.',
                'gemini',
                $model,
                providerRequestId: $requestId,
                inputTokens: $inputTokens,
                outputTokens: $outputTokens,
                latencyMs: $latencyMs,
                estimatedCostMicros: $estimatedCostMicros,
            );
        }

        if ($finishReason !== 'STOP' && $finishReason !== '') {
            throw new LlmProviderException(
                LlmProviderException::INCOMPLETE,
                'The Gemini provider returned an incomplete response.',
                'gemini',
                $model,
                providerRequestId: $requestId,
                inputTokens: $inputTokens,
                outputTokens: $outputTokens,
                latencyMs: $latencyMs,
                estimatedCostMicros: $estimatedCostMicros,
            );
        }

        $outputText = $this->extractOutputText(
            $candidate,
            $model,
            $requestId,
            $inputTokens,
            $outputTokens,
            $latencyMs,
            $estimatedCostMicros,
        );

        try {
            $output = json_decode($outputText, true, flags: JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            throw new LlmProviderException(
                LlmProviderException::MALFORMED_OUTPUT,
                'The Gemini provider returned malformed structured output.',
                'gemini',
                $model,
                $exception,
                $requestId,
                $inputTokens,
                $outputTokens,
                $latencyMs,
                $estimatedCostMicros,
            );
        }

        if (! is_array($output)) {
            throw new LlmProviderException(
                LlmProviderException::MALFORMED_OUTPUT,
                'The Gemini structured output must be an object.',
                'gemini',
                $model,
                providerRequestId: $requestId,
                inputTokens: $inputTokens,
                outputTokens: $outputTokens,
                latencyMs: $latencyMs,
                estimatedCostMicros: $estimatedCostMicros,
            );
        }

        $responseModel = (string) ($body['modelVersion'] ?? $model);

        return new LlmResponse(
            output: $output,
            provider: 'gemini',
            model: $responseModel,
            inputTokens: $inputTokens,
            outputTokens: $outputTokens,
            latencyMs: $latencyMs,
            providerRequestId: $requestId,
            estimatedCostMicros: $estimatedCostMicros,
        );
    }

    /**
     * List all available Gemini models for the given API key, filtered to models supporting generateContent.
     *
     * @return list<array{id: string, name: string, description: string}>
     */
    public function listModels(?string $apiKey = null): array
    {
        $key = $apiKey !== null && trim($apiKey) !== ''
            ? trim($apiKey)
            : (string) config('ai.providers.gemini.api_key');

        if ($key === '') {
            return [];
        }

        $cacheKey = 'gemini_models_list_'.md5($key);
        /** @var mixed $cached */
        $cached = Cache::get($cacheKey);
        if (is_array($cached) && ! empty($cached)) {
            /** @var list<array{id: string, name: string, description: string}> $cached */
            return $cached;
        }

        $baseUrl = rtrim((string) config('ai.providers.gemini.base_url', 'https://generativelanguage.googleapis.com/v1beta'), '/');
        $proxy = config('ai.providers.gemini.proxy', config('ai.proxy'));

        try {
            $client = Http::baseUrl($baseUrl)
                ->withHeaders([
                    'x-goog-api-key' => $key,
                ])
                ->acceptJson()
                ->timeout(15);

            if (filled($proxy)) {
                $client = $client->withOptions(['proxy' => (string) $proxy]);
            }

            $response = $client->get('/models', ['pageSize' => 100]);

            if (! $response->successful()) {
                return [];
            }

            $data = $response->json();
            if (! is_array($data) || ! isset($data['models']) || ! is_array($data['models'])) {
                return [];
            }

            $models = [];
            foreach ($data['models'] as $item) {
                if (! is_array($item)) {
                    continue;
                }

                $rawName = (string) ($item['name'] ?? '');
                $id = preg_replace('#^models/#', '', $rawName);
                if ($id === '') {
                    continue;
                }

                $methods = (array) ($item['supportedGenerationMethods'] ?? []);
                if (! in_array('generateContent', $methods, true)) {
                    continue;
                }

                $displayName = (string) ($item['displayName'] ?? $id);
                $name = $displayName !== '' && $displayName !== $id
                    ? "{$displayName} ({$id})"
                    : $id;

                $models[] = [
                    'id' => $id,
                    'name' => $name,
                    'description' => (string) ($item['description'] ?? ''),
                ];
            }

            usort($models, function (array $a, array $b): int {
                $priority = function (string $id): int {
                    if (str_contains($id, '2.0-flash')) {
                        return 1;
                    }
                    if (str_contains($id, '1.5-flash')) {
                        return 2;
                    }
                    if (str_contains($id, 'flash')) {
                        return 3;
                    }
                    if (str_contains($id, 'pro')) {
                        return 4;
                    }

                    return 5;
                };
                $pA = $priority($a['id']);
                $pB = $priority($b['id']);
                if ($pA !== $pB) {
                    return $pA <=> $pB;
                }

                return strcmp($a['id'], $b['id']);
            });

            if (! empty($models)) {
                Cache::put($cacheKey, $models, 180);
            }

            return $models;
        } catch (Throwable) {
            return [];
        }
    }

    /**
     * Test connection to Gemini with minimal non-domain payload.
     *
     * @return array{success: bool, provider: string, model: string, latency_ms?: int, message: string, error_code?: string, recommendation?: string, available_models?: list<array{id: string, name: string, description: string}>}
     */
    public function testConnection(?string $apiKey = null, ?string $model = null): array
    {
        $key = $apiKey !== null && trim($apiKey) !== ''
            ? trim($apiKey)
            : (string) config('ai.providers.gemini.api_key');

        $initialTarget = $model !== null && trim($model) !== ''
            ? trim($model)
            : (string) config('ai.providers.gemini.test_model', 'gemini-1.5-flash');

        $normalizedTarget = preg_replace('#^models/#', '', $initialTarget);

        if ($key === '') {
            return [
                'success' => false,
                'status' => 'failed',
                'provider' => 'gemini',
                'model' => $normalizedTarget,
                'error_code' => 'NOT_CONFIGURED',
                'message' => 'Gemini API key is not configured.',
                'recommendation' => 'Enter a valid Google Gemini API key or configure GEMINI_API_KEY.',
            ];
        }

        $startedAt = hrtime(true);
        $baseUrl = rtrim((string) config('ai.providers.gemini.base_url', 'https://generativelanguage.googleapis.com/v1beta'), '/');
        $proxy = config('ai.providers.gemini.proxy', config('ai.proxy'));

        $sendPing = function (string $target) use ($baseUrl, $key, $proxy) {
            $client = Http::baseUrl($baseUrl)
                ->withHeaders([
                    'x-goog-api-key' => $key,
                ])
                ->acceptJson()
                ->timeout(25);

            if (filled($proxy)) {
                $client = $client->withOptions(['proxy' => (string) $proxy]);
            }

            return $client->post('/models/'.urlencode($target).':generateContent', [
                'contents' => [
                    [
                        'role' => 'user',
                        'parts' => [
                            ['text' => 'ping'],
                        ],
                    ],
                ],
                'generationConfig' => [
                    'maxOutputTokens' => 5,
                ],
            ]);
        };

        $modelToTest = $normalizedTarget;

        try {
            $response = $sendPing($modelToTest);
        } catch (Throwable $e) {
            $msg = $e->getMessage();
            $isTimeout = str_contains($msg, 'cURL error 28') || str_contains(strtolower($msg), 'timed out');

            return [
                'success' => false,
                'status' => 'failed',
                'provider' => 'gemini',
                'model' => $modelToTest,
                'latency_ms' => $this->elapsedMilliseconds($startedAt),
                'error_code' => $isTimeout ? 'TIMEOUT' : 'TRANSPORT_ERROR',
                'message' => $isTimeout
                    ? 'Превышено время ожидания ответа от Google Gemini (таймаут запроса).'
                    : 'Не удалось связаться с Google Gemini: '.$msg,
                'recommendation' => $isTimeout
                    ? 'Модель '.$modelToTest.' отвечает дольше обычного. Попробуйте выбрать быструю модель (gemini-1.5-flash или gemini-2.0-flash).'
                    : 'Проверьте сетевой доступ к generativelanguage.googleapis.com, настройки прокси/VPN или DNS.',
            ];
        }

        $latencyMs = $this->elapsedMilliseconds($startedAt);

        // Fallback retry only if initial ping returned 404 or 400
        $availableModels = [];
        if ($response->status() === 404 || $response->status() === 400) {
            $availableModels = $this->listModels($key);
            if (! empty($availableModels)) {
                $fallbackModel = $availableModels[0]['id'];
                if ($fallbackModel !== $modelToTest) {
                    try {
                        $fallbackResponse = $sendPing($fallbackModel);
                        if ($fallbackResponse->successful()) {
                            return [
                                'success' => true,
                                'status' => 'healthy',
                                'provider' => 'gemini',
                                'model' => $fallbackModel,
                                'latency_ms' => $this->elapsedMilliseconds($startedAt),
                                'message' => "Модель {$normalizedTarget} не найдена по ключу. Подключение успешно проверено с доступной моделью {$fallbackModel}.",
                                'available_models' => $availableModels,
                            ];
                        }
                    } catch (Throwable) {
                        // proceed with original failure response
                    }
                }
            }
        }

        if (! $response->successful()) {
            $status = $response->status();
            $body = $response->json();

            if (empty($availableModels)) {
                $availableModels = $this->listModels($key);
            }

            [$errorCode, $recommendation] = match (true) {
                $status === 400 => ['INVALID_ARGUMENT', 'Check the specified model name and parameters.'],
                $status === 401 || $status === 403 => ['INVALID_CREDENTIALS', 'Verify that your Gemini API key is valid and has Gemini API enabled.'],
                $status === 404 => ['MODEL_NOT_FOUND', ! empty($availableModels)
                    ? 'Запрошенная модель не найдена. Доступные модели по вашему ключу: '.implode(', ', array_column(array_slice($availableModels, 0, 8), 'id'))
                    : 'The specified Gemini model was not found. Check the model name.'],
                $status === 429 => ['RATE_LIMITED', 'Gemini quota or rate limit exceeded. Check your plan quota in Google AI Studio.'],
                $status >= 500 => ['PROVIDER_UNAVAILABLE', 'Google Gemini service is temporarily unavailable. Retry later.'],
                default => ['PROVIDER_ERROR', 'The provider returned an unexpected error.'],
            };

            return [
                'success' => false,
                'status' => 'failed',
                'provider' => 'gemini',
                'model' => $modelToTest,
                'latency_ms' => $latencyMs,
                'error_code' => $errorCode,
                'message' => 'Connection test failed: '.$errorCode,
                'recommendation' => $recommendation,
                'available_models' => $availableModels,
            ];
        }

        $availableModels = $this->listModels($key);

        return [
            'success' => true,
            'status' => 'healthy',
            'provider' => 'gemini',
            'model' => $modelToTest,
            'latency_ms' => $latencyMs,
            'message' => 'Подключение к Google Gemini успешно проверено.',
            'available_models' => $availableModels,
        ];
    }

    /**
     * @param  array<string, mixed>  $candidate
     */
    private function extractOutputText(
        array $candidate,
        string $model,
        ?string $requestId,
        ?int $inputTokens,
        ?int $outputTokens,
        int $latencyMs,
        ?int $estimatedCostMicros,
    ): string {
        $parts = $candidate['content']['parts'] ?? [];
        if (is_array($parts)) {
            foreach ($parts as $part) {
                if (is_array($part) && isset($part['text']) && is_string($part['text'])) {
                    return $part['text'];
                }
            }
        }

        throw new LlmProviderException(
            LlmProviderException::INCOMPLETE,
            'The Gemini response did not contain structured text output.',
            'gemini',
            $model,
            providerRequestId: $requestId,
            inputTokens: $inputTokens,
            outputTokens: $outputTokens,
            latencyMs: $latencyMs,
            estimatedCostMicros: $estimatedCostMicros,
        );
    }

    /**
     * Sanitize JSON Schema for Gemini OpenAPI 3.0 responseSchema requirements.
     *
     * - Inlines local $ref references (#/$defs/... or #/definitions/...)
     * - Removes schema meta-keywords ($schema, $id, $defs, definitions)
     * - Normalizes nullable union types (e.g. type: ["string", "null"] -> type: "string", nullable: true)
     * - Removes additionalProperties keyword without damaging user properties named 'additionalProperties'
     * - Preserves required, properties, items, and enum (stripping unsupported keywords minItems, maxItems, minimum, maximum, minLength, maxLength, pattern, default, title, examples which are enforced downstream by deterministic backend validators)
     *
     * @param  array<string, mixed>  $schema
     * @return array<string, mixed>
     */
    public function sanitizeSchema(array $schema): array
    {
        $root = $schema;

        return $this->normalizeSchemaNode($schema, $root, []);
    }

    /**
     * @param  array<string, mixed>  $node
     * @param  array<string, mixed>  $root
     * @param  list<string>  $refStack
     * @return array<string, mixed>
     */
    private function normalizeSchemaNode(array $node, array $root, array $refStack): array
    {
        // 1. Resolve $ref if present
        if (isset($node['$ref']) && is_string($node['$ref'])) {
            $ref = $node['$ref'];
            if (in_array($ref, $refStack, true)) {
                throw new LlmProviderException(
                    LlmProviderException::INVALID_CONFIGURATION,
                    'Circular schema reference detected: '.$ref,
                    'gemini',
                );
            }

            $resolved = $this->resolveLocalRef($ref, $root);
            if (! is_array($resolved)) {
                throw new LlmProviderException(
                    LlmProviderException::INVALID_CONFIGURATION,
                    'Unresolvable schema reference: '.$ref,
                    'gemini',
                );
            }

            // Merge resolved definition with any local overrides (excluding $ref itself)
            $localOverrides = $node;
            unset($localOverrides['$ref']);

            $merged = array_merge($resolved, $localOverrides);

            return $this->normalizeSchemaNode($merged, $root, [...$refStack, $ref]);
        }

        // 2. Remove meta keys and validation keywords unsupported by Gemini
        unset(
            $node['$schema'],
            $node['$id'],
            $node['$defs'],
            $node['definitions'],
            $node['additionalProperties'],
            $node['maxItems'],
            $node['minItems'],
            $node['minimum'],
            $node['maximum'],
            $node['minLength'],
            $node['maxLength'],
            $node['pattern'],
            $node['default'],
            $node['title'],
            $node['examples'],
        );

        // 3. Normalize nullable union types: e.g. ["string", "null"] => type: "string", nullable: true
        if (isset($node['type']) && is_array($node['type'])) {
            $types = $node['type'];
            $hasNull = in_array('null', $types, true);
            $nonNullTypes = array_values(array_filter($types, fn ($t) => $t !== 'null'));

            if ($hasNull && count($nonNullTypes) === 1) {
                $node['type'] = (string) $nonNullTypes[0];
                $node['nullable'] = true;
            } elseif (count($nonNullTypes) === 1) {
                $node['type'] = (string) $nonNullTypes[0];
            } else {
                throw new LlmProviderException(
                    LlmProviderException::INVALID_CONFIGURATION,
                    'Gemini does not support multi-type unions: '.json_encode($types),
                    'gemini',
                );
            }
        }

        // 4. Process properties map carefully: keys in 'properties' are field names, not schema keywords!
        if (isset($node['properties']) && is_array($node['properties'])) {
            $normalizedProperties = [];
            foreach ($node['properties'] as $propName => $propSchema) {
                if (is_array($propSchema)) {
                    $normalizedProperties[$propName] = $this->normalizeSchemaNode($propSchema, $root, $refStack);
                } else {
                    $normalizedProperties[$propName] = $propSchema;
                }
            }
            $node['properties'] = $normalizedProperties;
        }

        // 5. Process items (for array types)
        if (isset($node['items']) && is_array($node['items'])) {
            $node['items'] = $this->normalizeSchemaNode($node['items'], $root, $refStack);
        }

        return $node;
    }

    /**
     * @param  array<string, mixed>  $root
     * @return array<string, mixed>|null
     */
    private function resolveLocalRef(string $ref, array $root): ?array
    {
        if (! str_starts_with($ref, '#/')) {
            return null; // External refs are unsupported
        }

        $parts = explode('/', ltrim(substr($ref, 2), '/'));
        $current = $root;
        foreach ($parts as $part) {
            $part = str_replace(['~1', '~0'], ['/', '~'], $part);
            if (! is_array($current) || ! array_key_exists($part, $current)) {
                return null;
            }
            $current = $current[$part];
        }

        return is_array($current) ? $current : null;
    }

    private function estimateCost(ResolvedModel $resolved, ?int $inputTokens, ?int $outputTokens): ?int
    {
        if ($inputTokens === null || $outputTokens === null
            || $resolved->inputCostMicrosPerMillionTokens === null
            || $resolved->outputCostMicrosPerMillionTokens === null) {
            return null;
        }

        return (int) round(
            ($inputTokens * $resolved->inputCostMicrosPerMillionTokens
                + $outputTokens * $resolved->outputCostMicrosPerMillionTokens) / 1_000_000,
        );
    }

    private function elapsedMilliseconds(int $startedAt): int
    {
        return (int) round((hrtime(true) - $startedAt) / 1_000_000);
    }
}
