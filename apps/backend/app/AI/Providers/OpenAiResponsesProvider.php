<?php

namespace App\AI\Providers;

use App\AI\Data\LlmRequest;
use App\AI\Data\LlmResponse;
use App\AI\Data\ResolvedModel;
use App\AI\Exceptions\LlmProviderException;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;

class OpenAiResponsesProvider
{
    public function generateResolved(LlmRequest $request, ResolvedModel $resolved): LlmResponse
    {
        $apiKey = (string) config('ai.providers.openai.api_key');
        $model = $resolved->model;
        if ($apiKey === '' || $model === '') {
            throw new LlmProviderException(LlmProviderException::NOT_CONFIGURED, 'The OpenAI provider is not fully configured.', 'openai', $model);
        }

        $startedAt = hrtime(true);
        try {
            $response = Http::baseUrl(rtrim((string) config('ai.providers.openai.base_url'), '/'))
                ->withToken($apiKey)
                ->acceptJson()
                ->timeout((int) config('ai.providers.openai.timeout_seconds'))
                ->post('/responses', [
                    'model' => $model,
                    'store' => false,
                    'instructions' => $request->trustedInstructions,
                    'input' => [[
                        'role' => 'user',
                        'content' => [[
                            'type' => 'input_text',
                            'text' => $request->untrustedDataLabel.":\n".$request->untrustedSourceText,
                        ]],
                    ]],
                    'text' => ['format' => [
                        'type' => 'json_schema',
                        'name' => $request->schemaName,
                        'strict' => true,
                        'schema' => $request->schema,
                    ]],
                ]);
        } catch (ConnectionException $exception) {
            throw new LlmProviderException(
                LlmProviderException::TRANSPORT,
                'The OpenAI provider could not be reached.',
                'openai',
                $model,
                $exception,
                latencyMs: $this->elapsedMilliseconds($startedAt),
            );
        }

        $latencyMs = $this->elapsedMilliseconds($startedAt);
        $requestId = $response->header('x-request-id');
        $body = $response->json();
        $inputTokens = is_array($body) && is_int($body['usage']['input_tokens'] ?? null) ? $body['usage']['input_tokens'] : null;
        $outputTokens = is_array($body) && is_int($body['usage']['output_tokens'] ?? null) ? $body['usage']['output_tokens'] : null;
        $estimatedCostMicros = $this->estimateCost($resolved, $inputTokens, $outputTokens);
        if (! $response->successful()) {
            throw new LlmProviderException(
                LlmProviderException::PROVIDER,
                'The OpenAI provider returned an unsuccessful response.',
                'openai',
                $model,
                providerRequestId: $requestId,
                inputTokens: $inputTokens,
                outputTokens: $outputTokens,
                latencyMs: $latencyMs,
                estimatedCostMicros: $estimatedCostMicros,
            );
        }

        if (! is_array($body)) {
            throw new LlmProviderException(
                LlmProviderException::MALFORMED_OUTPUT,
                'The OpenAI provider returned malformed output.',
                'openai',
                $model,
                providerRequestId: $requestId,
                latencyMs: $latencyMs,
            );
        }
        if ($this->containsRefusal($body)) {
            throw new LlmProviderException(
                LlmProviderException::REFUSAL,
                'The OpenAI provider refused the request.',
                'openai',
                $model,
                providerRequestId: $requestId,
                inputTokens: $inputTokens,
                outputTokens: $outputTokens,
                latencyMs: $latencyMs,
                estimatedCostMicros: $estimatedCostMicros,
            );
        }
        if (($body['status'] ?? null) !== 'completed') {
            throw new LlmProviderException(
                LlmProviderException::INCOMPLETE,
                'The OpenAI provider returned an incomplete response.',
                'openai',
                $model,
                providerRequestId: $requestId,
                inputTokens: $inputTokens,
                outputTokens: $outputTokens,
                latencyMs: $latencyMs,
                estimatedCostMicros: $estimatedCostMicros,
            );
        }
        $outputText = $this->outputText(
            $body,
            $model,
            $requestId,
            $inputTokens,
            $outputTokens,
            $latencyMs,
            $estimatedCostMicros,
        );
        try {
            $output = json_decode($outputText, true, flags: JSON_THROW_ON_ERROR);
        } catch (\JsonException $exception) {
            throw new LlmProviderException(
                LlmProviderException::MALFORMED_OUTPUT,
                'The OpenAI provider returned malformed structured output.',
                'openai',
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
                'The OpenAI structured output must be an object.',
                'openai',
                $model,
                providerRequestId: $requestId,
                inputTokens: $inputTokens,
                outputTokens: $outputTokens,
                latencyMs: $latencyMs,
                estimatedCostMicros: $estimatedCostMicros,
            );
        }

        return new LlmResponse(
            output: $output,
            provider: 'openai',
            model: (string) ($body['model'] ?? $model),
            inputTokens: $inputTokens,
            outputTokens: $outputTokens,
            latencyMs: $latencyMs,
            providerRequestId: $requestId,
            estimatedCostMicros: $estimatedCostMicros,
        );
    }

    /** @param array<string, mixed> $body */
    private function containsRefusal(array $body): bool
    {
        foreach ($body['output'] ?? [] as $item) {
            foreach (is_array($item) ? ($item['content'] ?? []) : [] as $content) {
                if (is_array($content) && ($content['type'] ?? null) === 'refusal') {
                    return true;
                }
            }
        }

        return false;
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

    /** @param array<string, mixed> $body */
    private function outputText(
        array $body,
        string $model,
        ?string $requestId,
        ?int $inputTokens,
        ?int $outputTokens,
        int $latencyMs,
        ?int $estimatedCostMicros,
    ): string {
        foreach ($body['output'] ?? [] as $item) {
            if (! is_array($item)) {
                continue;
            }
            foreach ($item['content'] ?? [] as $content) {
                if (is_array($content) && ($content['type'] ?? null) === 'output_text' && is_string($content['text'] ?? null)) {
                    return $content['text'];
                }
            }
        }

        throw new LlmProviderException(
            LlmProviderException::INCOMPLETE,
            'The OpenAI response did not contain structured text output.',
            'openai',
            $model,
            providerRequestId: $requestId,
            inputTokens: $inputTokens,
            outputTokens: $outputTokens,
            latencyMs: $latencyMs,
            estimatedCostMicros: $estimatedCostMicros,
        );
    }

    private function elapsedMilliseconds(int $startedAt): int
    {
        return (int) round((hrtime(true) - $startedAt) / 1_000_000);
    }
}
