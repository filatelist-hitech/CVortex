<?php

namespace App\AI\Providers;

use App\AI\Data\LlmRequest;
use App\AI\Data\LlmResponse;
use App\AI\Data\ResolvedModel;
use App\AI\Exceptions\LlmProviderException;
use App\AI\ProviderRetryAfter;
use Illuminate\Http\Client\ConnectionException;
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

        try {
            $response = Http::baseUrl($baseUrl)
                ->withHeaders([
                    'x-goog-api-key' => $apiKey,
                ])
                ->acceptJson()
                ->timeout($timeout)
                ->post('/models/'.urlencode($model).':generateContent', $payload);
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
     * Test connection to Gemini with minimal non-domain payload.
     *
     * @return array{success: bool, provider: string, model: string, latency_ms?: int, message: string, error_code?: string, recommendation?: string}
     */
    public function testConnection(?string $apiKey = null, ?string $model = null): array
    {
        $key = $apiKey !== null && trim($apiKey) !== ''
            ? trim($apiKey)
            : (string) config('ai.providers.gemini.api_key');

        $targetModel = $model !== null && trim($model) !== ''
            ? trim($model)
            : (string) config('ai.providers.gemini.test_model', 'gemini-1.5-flash');

        if ($key === '') {
            return [
                'success' => false,
                'provider' => 'gemini',
                'model' => $targetModel,
                'error_code' => 'NOT_CONFIGURED',
                'message' => 'Gemini API key is not configured.',
                'recommendation' => 'Enter a valid Google Gemini API key or configure GEMINI_API_KEY.',
            ];
        }

        $startedAt = hrtime(true);
        $baseUrl = rtrim((string) config('ai.providers.gemini.base_url', 'https://generativelanguage.googleapis.com/v1beta'), '/');

        try {
            $response = Http::baseUrl($baseUrl)
                ->withHeaders([
                    'x-goog-api-key' => $key,
                ])
                ->acceptJson()
                ->timeout(15)
                ->post('/models/'.urlencode($targetModel).':generateContent', [
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
        } catch (Throwable $e) {
            return [
                'success' => false,
                'provider' => 'gemini',
                'model' => $targetModel,
                'latency_ms' => $this->elapsedMilliseconds($startedAt),
                'error_code' => 'TRANSPORT_ERROR',
                'message' => 'Unable to reach Google Gemini endpoint.',
                'recommendation' => 'Check your internet connection and proxy/DNS settings.',
            ];
        }

        $latencyMs = $this->elapsedMilliseconds($startedAt);

        if (! $response->successful()) {
            $status = $response->status();
            $body = $response->json();
            $rawMessage = is_array($body) && is_string($body['error']['message'] ?? null)
                ? $body['error']['message']
                : 'HTTP '.$status;

            [$errorCode, $recommendation] = match (true) {
                $status === 400 => ['INVALID_ARGUMENT', 'Check the specified model name and parameters.'],
                $status === 401 || $status === 403 => ['INVALID_CREDENTIALS', 'Verify that your Gemini API key is valid and has Gemini API enabled.'],
                $status === 429 => ['RATE_LIMITED', 'Gemini quota or rate limit exceeded. Check your plan quota in Google AI Studio.'],
                $status >= 500 => ['PROVIDER_UNAVAILABLE', 'Google Gemini service is temporarily unavailable. Retry later.'],
                default => ['PROVIDER_ERROR', 'The provider returned an unexpected error.'],
            };

            return [
                'success' => false,
                'provider' => 'gemini',
                'model' => $targetModel,
                'latency_ms' => $latencyMs,
                'error_code' => $errorCode,
                'message' => 'Connection test failed: '.$errorCode,
                'recommendation' => $recommendation,
            ];
        }

        return [
            'success' => true,
            'provider' => 'gemini',
            'model' => $targetModel,
            'latency_ms' => $latencyMs,
            'message' => 'Connection to Google Gemini successful.',
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
     * @param  array<string, mixed>  $schema
     * @return array<string, mixed>
     */
    private function sanitizeSchema(array $schema): array
    {
        unset($schema['$schema'], $schema['$id']);

        foreach ($schema as $key => $value) {
            if (is_array($value)) {
                $schema[$key] = $this->sanitizeSchema($value);
            }
        }

        return $schema;
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
