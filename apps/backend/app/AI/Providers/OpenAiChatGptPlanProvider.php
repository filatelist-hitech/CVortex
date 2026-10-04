<?php

namespace App\AI\Providers;

use App\AI\ChatGpt\ConnectionService;
use App\AI\ChatGpt\PlanException;
use App\AI\Contracts\StreamingProvider;
use App\AI\Exceptions\ModelSelectionException;
use App\Models\User;
use Generator;
use Illuminate\Support\Facades\Http;
use Throwable;

final class OpenAiChatGptPlanProvider implements StreamingProvider
{
    public function __construct(private readonly ConnectionService $connections) {}

    public function models(User $user, string $connectionId): array
    {
        $token = $this->connections->accessToken($user, $connectionId);
        try {
            $response = Http::withToken($token)
                ->timeout(15)->get('https://api.openai.com/v1/models');
        } catch (PlanException $exception) {
            throw $exception;
        } catch (Throwable) {
            throw new PlanException('PROVIDER_UNAVAILABLE', 503);
        }
        if (! $response->successful()) {
            throw $this->error($response->json(), $response->status(), $response->header('x-request-id'));
        }
        $models = [];
        foreach ($response->json('models', []) as $entry) {
            if (($entry['visibility'] ?? null) === 'list' && is_string($entry['slug'] ?? null) && is_string($entry['display_name'] ?? null)) {
                $models[] = ['slug' => $entry['slug'], 'display_name' => $entry['display_name']];
            }
        }

        return $models;
    }

    public function stream(User $user, string $connectionId, string $model, string $instructions, array $input): Generator
    {
        $started = hrtime(true);
        $models = $this->models($user, $connectionId);
        if (! in_array($model, array_column($models, 'slug'), true)) {
            throw new ModelSelectionException(ModelSelectionException::NOT_AVAILABLE);
        }
        $token = $this->connections->accessToken($user, $connectionId);
        try {
            $response = Http::withToken($token)
                ->withOptions(['stream' => true, 'read_timeout' => 30])->timeout(120)
                ->withHeaders(['Accept' => 'text/event-stream'])
                ->post('https://api.openai.com/v1/responses', [
                    'model' => $model, 'instructions' => $instructions, 'input' => $input,
                    'store' => false, 'stream' => true,
                ]);
            if (! $response->successful()) {
                throw $this->error($response->json(), $response->status(), $response->header('x-request-id'));
            }
            $body = $response->toPsrResponse()->getBody();
            $buffer = '';
            $data = [];
            $outputLength = 0;
            try {
                while (! $body->eof()) {
                    $buffer .= $body->read(4096);
                    if (strlen($buffer) > 262144 || (hrtime(true) - $started) / 1e9 > 120) {
                        throw new PlanException('STREAM_INTERRUPTED');
                    }
                    while (($position = strpos($buffer, "\n")) !== false) {
                        $line = rtrim(substr($buffer, 0, $position), "\r");
                        $buffer = substr($buffer, $position + 1);
                        if (str_starts_with($line, 'data:')) {
                            $data[] = ltrim(substr($line, 5));
                        } elseif ($line === '' && $data !== []) {
                            $raw = implode("\n", $data);
                            $data = [];
                            if ($raw === '[DONE]') {
                                continue;
                            }
                            $event = json_decode($raw, true, flags: JSON_THROW_ON_ERROR);
                            if (($event['type'] ?? null) === 'response.output_text.delta') {
                                $delta = $event['delta'] ?? '';
                                if (! is_string($delta) || ($outputLength += strlen($delta)) > 131072) {
                                    throw new PlanException('OUTPUT_LIMIT_REACHED');
                                }
                                yield ['type' => 'delta', 'text' => $delta];
                            } elseif (($event['type'] ?? null) === 'response.completed') {
                                if (($event['response']['status'] ?? null) !== 'completed') {
                                    throw new PlanException('STREAM_INTERRUPTED');
                                }
                                yield ['type' => 'completed', 'provider' => 'openai_chatgpt_plan', 'model' => $model,
                                    'request_id' => $response->header('x-request-id'),
                                    'latency_ms' => (int) ((hrtime(true) - $started) / 1e6)];

                                return;
                            } elseif (($event['type'] ?? null) === 'response.incomplete') {
                                throw new PlanException('STREAM_INTERRUPTED', 400, $response->header('x-request-id'));
                            } elseif (in_array($event['type'] ?? null, ['response.failed', 'error'], true)) {
                                throw $this->error($event['response'] ?? $event, 400, $response->header('x-request-id'));
                            }
                        }
                    }
                }
                throw new PlanException('STREAM_INTERRUPTED', 400, $response->header('x-request-id'));
            } finally {
                $body->close();
            }
        } catch (PlanException $exception) {
            throw $exception;
        } catch (Throwable) {
            throw new PlanException('STREAM_INTERRUPTED');
        }
    }

    private function error(mixed $body, int $status, ?string $requestId): PlanException
    {
        $code = is_array($body) ? ($body['error']['code'] ?? $body['code'] ?? null) : null;
        $allowed = ['subscription_sharing_user_not_eligible', 'subscription_sharing_usage_limit_exceeded',
            'subscription_sharing_usage_unavailable', 'subscription_sharing_unsupported_capability',
            'subscription_sharing_route_not_supported', 'subscription_sharing_invalid_user',
            'chatpass_v2_scope_not_authorized', 'chatpass_v2_invalid_authorization_context',
            'subscription_sharing_user_unavailable', 'model_not_found'];

        return new PlanException(is_string($code) && in_array($code, $allowed, true) ? $code : match ($status) {
            401 => 'REAUTHENTICATION_REQUIRED', 403 => 'PLAN_UNAVAILABLE', 429 => 'USAGE_LIMIT_REACHED',
            default => $status >= 500 ? 'PROVIDER_UNAVAILABLE' : 'RESPONSE_FAILED',
        }, $status, $requestId);
    }
}
