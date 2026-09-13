<?php

namespace App\AI\Providers;

use App\AI\Contracts\LlmProvider;
use App\AI\Data\LlmRequest;
use App\AI\Data\LlmResponse;
use App\AI\Exceptions\LlmProviderException;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;

class OpenAiResponsesProvider implements LlmProvider
{
    public function generateStructured(LlmRequest $request): LlmResponse
    {
        $apiKey = (string) config('ai.providers.openai.api_key');
        $model = (string) config('ai.providers.openai.model');
        if ($apiKey === '' || $model === '') {
            throw new LlmProviderException('The OpenAI provider is not fully configured.');
        }

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
                            'text' => "UNTRUSTED CAREER SOURCE DATA:\n".$request->untrustedSourceText,
                        ]],
                    ]],
                    'text' => ['format' => [
                        'type' => 'json_schema',
                        'name' => 'career_facts',
                        'strict' => true,
                        'schema' => $request->schema,
                    ]],
                ]);
        } catch (ConnectionException $exception) {
            throw new LlmProviderException('The OpenAI provider could not be reached.', previous: $exception);
        }

        if (! $response->successful()) {
            throw new LlmProviderException('The OpenAI provider returned an unsuccessful response.');
        }

        $body = $response->json();
        if (! is_array($body) || ($body['status'] ?? null) !== 'completed') {
            throw new LlmProviderException('The OpenAI provider returned an incomplete response.');
        }
        $outputText = $this->outputText($body);
        try {
            $output = json_decode($outputText, true, flags: JSON_THROW_ON_ERROR);
        } catch (\JsonException $exception) {
            throw new LlmProviderException('The OpenAI provider returned malformed structured output.', previous: $exception);
        }
        if (! is_array($output)) {
            throw new LlmProviderException('The OpenAI structured output must be an object.');
        }

        return new LlmResponse(
            output: $output,
            provider: 'openai',
            model: (string) ($body['model'] ?? $model),
            inputTokens: is_int($body['usage']['input_tokens'] ?? null) ? $body['usage']['input_tokens'] : null,
            outputTokens: is_int($body['usage']['output_tokens'] ?? null) ? $body['usage']['output_tokens'] : null,
            latencyMs: null,
            providerRequestId: $response->header('x-request-id'),
        );
    }

    /** @param array<string, mixed> $body */
    private function outputText(array $body): string
    {
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

        throw new LlmProviderException('The OpenAI response did not contain structured text output.');
    }
}
