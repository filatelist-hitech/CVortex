<?php

namespace App\AI\ChatGpt;

use App\Models\ChatGptConnection;
use App\Models\User;
use App\Services\DatabaseOwnerContext;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Throwable;

final class ConnectionService
{
    public function __construct(
        private readonly HostIdentity $host,
        private readonly OpenIdVerifier $identity,
        private readonly DatabaseOwnerContext $owners,
    ) {}

    public function enabled(): void
    {
        if (! config('chatgpt.enabled')) {
            throw new PlanException('PLAN_PROVIDER_DISABLED', 503);
        }
    }

    public function start(User $user, ?string $connectionId = null, bool $consent = false): string
    {
        $this->enabled();
        $callback = (string) config('chatgpt.callback_uri');
        if (parse_url($callback, PHP_URL_SCHEME) !== 'http' || parse_url($callback, PHP_URL_HOST) !== '127.0.0.1'
            || parse_url($callback, PHP_URL_PATH) !== '/api/v1/chatgpt/callback'
            || parse_url($callback, PHP_URL_QUERY) !== null || parse_url($callback, PHP_URL_FRAGMENT) !== null
            || parse_url($callback, PHP_URL_USER) !== null) {
            throw new PlanException('LOOPBACK_CALLBACK_INVALID', 503);
        }
        $connection = $connectionId === null ? null : $this->owned($user, $connectionId);
        $state = $this->random();
        $nonce = $this->random();
        $verifier = $this->random();
        Cache::put('chatgpt:attempt:'.hash('sha256', $state), Crypt::encryptString(json_encode([
            'owner_id' => (string) $user->id, 'connection_id' => $connectionId,
            'client_id' => $connection?->client_id, 'nonce' => $nonce, 'verifier' => $verifier,
            'generation' => $connection?->oauth_generation,
            'callback' => $callback, 'deadline' => time() + 600,
        ], JSON_THROW_ON_ERROR)), 600);
        $parameters = [
            'client_id' => $connection === null ? 'dynamic_agent_client' : $connection->client_id,
            'ext_agent_host_id' => $this->host->get(), 'response_type' => 'code',
            'redirect_uri' => $callback, 'scope' => config('chatgpt.scopes'),
            'resource' => config('chatgpt.resource'), 'state' => $state, 'nonce' => $nonce,
            'code_challenge_method' => 'S256',
            'code_challenge' => rtrim(strtr(base64_encode(hash('sha256', $verifier, true)), '+/', '-_'), '='),
        ];
        if ($connection === null) {
            $parameters['agent_name_hint'] = 'CVortex';
        }
        if ($consent) {
            $parameters['prompt'] = 'consent';
        }

        // Omit optional id_token_hint: no secret-bearing authorization URL reaches the browser/API/logs.
        return config('chatgpt.authorize_url').'?'.http_build_query($parameters, '', '&', PHP_QUERY_RFC3986);
    }

    /** @param array<string, mixed> $callback */
    public function complete(array $callback): ChatGptConnection
    {
        $this->enabled();
        $state = $callback['state'] ?? null;
        if (! is_string($state) || preg_match('/\A[A-Za-z0-9_-]{43}\z/D', $state) !== 1) {
            throw new PlanException('OAUTH_STATE_INVALID');
        }

        return Cache::lock('chatgpt:callback:'.hash('sha256', $state), 60)->block(1, function () use ($state, $callback): ChatGptConnection {
            $key = 'chatgpt:attempt:'.hash('sha256', $state);
            $stored = Cache::pull($key);
            if (! is_string($stored)) {
                throw new PlanException('OAUTH_STATE_INVALID_OR_EXPIRED');
            }
            $attempt = json_decode(Crypt::decryptString($stored), true, flags: JSON_THROW_ON_ERROR);
            if ($attempt['deadline'] < time()) {
                throw new PlanException('OAUTH_CALLBACK_TIMEOUT');
            }
            if (isset($callback['error'])) {
                $issuedId = $callback['client_id'] ?? null;
                if ($attempt['connection_id'] === null && is_string($issuedId)
                    && preg_match('/\Aoaiapp_[A-Za-z0-9_-]{1,240}\z/D', $issuedId) === 1) {
                    $owner = User::query()->findOrFail($attempt['owner_id']);
                    abort_unless($owner->isActive(), 404);
                    $this->owners->run((string) $owner->id, fn () => ChatGptConnection::query()->firstOrCreate(
                        ['owner_id' => $owner->id, 'client_id' => $issuedId], ['scopes' => []]));
                }
                throw new PlanException($callback['error'] === 'access_denied' ? 'OAUTH_DECLINED' : 'OAUTH_FAILED');
            }
            $user = User::query()->findOrFail($attempt['owner_id']);
            if (! $user->isActive()) {
                abort(404);
            }

            return $this->owners->run((string) $user->id, function () use ($user, $attempt, $callback): ChatGptConnection {
                $clientId = $attempt['client_id'] ?? ($callback['client_id'] ?? null);
                if (! is_string($clientId) || ! preg_match('/\Aoaiapp_[A-Za-z0-9_-]{1,240}\z/D', $clientId)
                    || (isset($callback['client_id']) && $callback['client_id'] !== $clientId)
                    || ! is_string($callback['code'] ?? null) || strlen($callback['code']) > 4096) {
                    throw new PlanException('OAUTH_REGISTRATION_INVALID');
                }
                // Preserve the issued registration even if code exchange fails; never reuse dynamic_agent_client as an issued ID.
                $connection = $attempt['connection_id'] === null
                    ? ChatGptConnection::query()->firstOrCreate(['owner_id' => $user->id, 'client_id' => $clientId], ['scopes' => []])
                    : $this->owned($user, $attempt['connection_id']);
                $tokens = $this->exchange([
                    'grant_type' => 'authorization_code', 'client_id' => $clientId,
                    'code' => $callback['code'], 'code_verifier' => $attempt['verifier'],
                    'redirect_uri' => $attempt['callback'], 'resource' => config('chatgpt.resource'),
                ]);
                $claims = $this->identity->verify($tokens['id_token'] ?? '', $clientId, $attempt['nonce']);

                return DB::transaction(function () use ($user, $connection, $attempt, $claims, $tokens): ChatGptConnection {
                    $current = $this->owned($user, (string) $connection->id, true);
                    if ($attempt['connection_id'] !== null
                        && (($attempt['generation'] ?? null) === null
                            || (int) $current->oauth_generation !== (int) $attempt['generation'])) {
                        throw new PlanException('OAUTH_ATTEMPT_INVALIDATED');
                    }
                    if ($current->subject !== null && ! hash_equals((string) $current->subject, $claims['sub'])) {
                        throw new PlanException('OAUTH_ACCOUNT_MISMATCH');
                    }
                    $current->subject = $claims['sub'];
                    $this->replaceTokens($current, $tokens);

                    return $current;
                });
            });
        });
    }

    public function owned(User $user, string $id, bool $lock = false): ChatGptConnection
    {
        $query = ChatGptConnection::query()->where('owner_id', $user->id)->whereKey($id);
        if ($lock) {
            $query->lockForUpdate();
        }

        return $query->firstOrFail();
    }

    public function accessToken(User $user, string $id): string
    {
        $this->enabled();
        $result = $this->owners->run((string) $user->id, fn () => DB::transaction(function () use ($user, $id): string|PlanException {
            $connection = $this->owned($user, $id, true);
            if ($connection->status !== 'CONNECTED' || ! in_array('chatgpt.tokens.use.direct', $connection->scopes, true)
                || ! in_array('resource.invoke', $connection->scopes, true)) {
                return new PlanException($connection->status === 'PLAN_PERMISSION_MISSING' ? 'PLAN_PERMISSION_MISSING' : 'REAUTHENTICATION_REQUIRED', 401);
            }
            if ($connection->expires_at?->isAfter(now()->addSeconds(60))) {
                return (string) $connection->access_token;
            }
            if ($connection->earliest_refresh_at?->isFuture()) {
                if ($connection->expires_at?->isFuture()) {
                    return (string) $connection->access_token;
                }

                return new PlanException('REFRESH_NOT_YET_ALLOWED', 503);
            }
            if (! is_string($connection->refresh_token) || $connection->refresh_token === '') {
                $this->clearTokens($connection, 'REAUTHENTICATION_REQUIRED');

                return new PlanException('REAUTHENTICATION_REQUIRED', 401);
            }
            try {
                $tokens = $this->exchange([
                    'grant_type' => 'refresh_token', 'client_id' => $connection->client_id,
                    'refresh_token' => $connection->refresh_token, 'resource' => config('chatgpt.resource'),
                ]);
                $this->replaceTokens($connection, $tokens);
            } catch (PlanException $exception) {
                if (in_array($exception->errorCode, ['invalid_grant', 'invalid_refresh_token', 'token_expired', 'refresh_token_expired', 'refresh_token_invalidated', 'refresh_token_reused'], true)) {
                    $this->clearTokens($connection, 'REAUTHENTICATION_REQUIRED');
                }

                return $exception;
            }

            return $connection->status === 'CONNECTED' ? (string) $connection->access_token : new PlanException('PLAN_PERMISSION_MISSING', 403);
        }));
        // Throw after commit, so terminal refresh failures do not roll back credential invalidation.
        if ($result instanceof PlanException) {
            throw $result;
        }

        return $result;
    }

    public function disconnect(User $user, string $id): bool
    {
        return $this->owners->run((string) $user->id, fn (): bool => DB::transaction(function () use ($user, $id): bool {
            $connection = $this->owned($user, $id, true);
            $revoked = $connection->refresh_token === null;
            try {
                $discovery = Http::timeout(10)->get((string) config('chatgpt.discovery_url'));
                $endpoint = $discovery->json('revocation_endpoint');
                if ($connection->refresh_token !== null && $discovery->successful() && is_string($endpoint)
                    && parse_url($endpoint, PHP_URL_HOST) === 'auth.openai.com' && parse_url($endpoint, PHP_URL_SCHEME) === 'https') {
                    $revoked = Http::asForm()->timeout(10)->post($endpoint, [
                        'token' => $connection->refresh_token, 'token_type_hint' => 'refresh_token', 'client_id' => $connection->client_id,
                    ])->status() === 200;
                }
            } catch (Throwable) {
                $revoked = false;
            }
            $connection->oauth_generation = (int) $connection->oauth_generation + 1;
            $this->clearTokens($connection, 'NOT_CONNECTED');

            return $revoked;
        }));
    }

    /** @param array<string, mixed> $payload
     * @return array<string, mixed>
     */
    private function exchange(#[\SensitiveParameter] array $payload): array
    {
        try {
            $response = Http::asForm()->timeout(15)->post((string) config('chatgpt.token_url'), $payload);
        } catch (Throwable) {
            throw new PlanException('OAUTH_PROVIDER_UNAVAILABLE', 503);
        }
        if (! $response->successful()) {
            $code = $response->json('error');
            $allowed = ['invalid_grant', 'invalid_client', 'invalid_refresh_token', 'token_expired', 'refresh_token_expired', 'refresh_token_invalidated', 'refresh_token_reused'];
            throw new PlanException(is_string($code) && in_array($code, $allowed, true) ? $code
                : ($response->status() >= 500 ? 'OAUTH_PROVIDER_UNAVAILABLE' : ($response->status() === 429 ? 'USAGE_LIMIT_REACHED' : 'OAUTH_EXCHANGE_FAILED')),
                $response->status() >= 500 ? 503 : ($response->status() === 429 ? 429 : 400));
        }
        $tokens = $response->json();
        if (! is_array($tokens)) {
            throw new PlanException('OAUTH_RESPONSE_INVALID');
        }

        return $tokens;
    }

    /** @param array<string, mixed> $tokens */
    private function replaceTokens(ChatGptConnection $connection, #[\SensitiveParameter] array $tokens): void
    {
        if (! is_string($tokens['access_token'] ?? null) || $tokens['access_token'] === ''
            || ! is_string($tokens['refresh_token'] ?? null) || $tokens['refresh_token'] === ''
            || ! is_int($tokens['expires_in'] ?? null) || $tokens['expires_in'] < 1 || $tokens['expires_in'] > 86400
            || ! is_string($tokens['scope'] ?? null) || strtolower($tokens['token_type'] ?? '') !== 'bearer'
            || (isset($tokens['earliest_refresh_at']) && ! is_int($tokens['earliest_refresh_at']))) {
            throw new PlanException('OAUTH_RESPONSE_INVALID');
        }
        $scopes = preg_split('/\s+/', trim($tokens['scope'])) ?: [];
        $connection->forceFill([
            'access_token' => $tokens['access_token'], 'refresh_token' => $tokens['refresh_token'],
            'id_token' => null, 'scopes' => $scopes, 'expires_at' => now()->addSeconds($tokens['expires_in']),
            'earliest_refresh_at' => isset($tokens['earliest_refresh_at']) ? date('Y-m-d H:i:s', $tokens['earliest_refresh_at']) : null,
            'status' => in_array('chatgpt.tokens.use.direct', $scopes, true) && in_array('resource.invoke', $scopes, true) ? 'CONNECTED' : 'PLAN_PERMISSION_MISSING',
        ])->save();
    }

    private function clearTokens(ChatGptConnection $connection, string $status): void
    {
        $connection->forceFill(['access_token' => null, 'refresh_token' => null, 'id_token' => null, 'expires_at' => null, 'earliest_refresh_at' => null, 'status' => $status])->save();
    }

    private function random(): string
    {
        return rtrim(strtr(base64_encode(random_bytes(32)), '+/', '-_'), '=');
    }
}
