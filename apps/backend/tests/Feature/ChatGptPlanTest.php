<?php

namespace Tests\Feature;

use App\AI\ChatGpt\ConnectionService;
use App\AI\ChatGpt\HostIdentity;
use App\AI\ChatGpt\OpenIdVerifier;
use App\AI\ChatGpt\PlanException;
use App\AI\Providers\OpenAiChatGptPlanProvider;
use App\Models\ChatGptConnection;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Factory;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use phpseclib4\Crypt\RSA;
use phpseclib4\Crypt\RSA\PrivateKey;
use Tests\TestCase;

class ChatGptPlanTest extends TestCase
{
    use RefreshDatabase;

    private static ?PrivateKey $signingKey = null;

    private string $hostFile;

    protected function setUp(): void
    {
        parent::setUp();
        $this->hostFile = sys_get_temp_dir().'/cvortex-host-'.bin2hex(random_bytes(8));
        config(['chatgpt.enabled' => true, 'chatgpt.host_file' => $this->hostFile,
            'app.key' => 'base64:AAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAA=']);
        self::$signingKey ??= RSA::createKey(2048);
        Http::preventStrayRequests();
    }

    protected function tearDown(): void
    {
        if (is_file($this->hostFile)) {
            unlink($this->hostFile);
        }
        parent::tearDown();
    }

    public function test_host_identity_is_stable_across_instances_and_signins(): void
    {
        $first = (new HostIdentity)->get();
        $this->assertSame($first, (new HostIdentity)->get());
        $this->assertMatchesRegularExpression('/^urn:uuid:.*-4/', $first);
        $this->assertSame(0600, fileperms($this->hostFile) & 0777);
        $user = $this->user('host');
        $this->assertSame($this->parameters($user)['ext_agent_host_id'], $this->parameters($user)['ext_agent_host_id']);
    }

    public function test_pkce_connection_identity_and_issued_client_are_persisted_and_secrets_hidden(): void
    {
        $user = $this->user('connect');
        $parameters = $this->parameters($user);
        $this->assertSame('dynamic_agent_client', $parameters['client_id']);
        $this->assertSame('http://127.0.0.1:8080/api/v1/chatgpt/callback', $parameters['redirect_uri']);
        $this->assertSame('S256', $parameters['code_challenge_method']);
        $this->fakeOAuth($parameters);
        $connection = app(ConnectionService::class)->complete(['state' => $parameters['state'], 'code' => 'test-code', 'client_id' => 'oaiapp_fixture']);
        Http::assertSent(function ($request) use ($parameters): bool {
            if ($request->url() !== config('chatgpt.token_url')) {
                return false;
            }
            $this->assertSame('oaiapp_fixture', $request['client_id']);
            $this->assertSame($parameters['code_challenge'], $this->b64(hash('sha256', $request['code_verifier'], true)));
            $this->assertSame($parameters['redirect_uri'], $request['redirect_uri']);
            $this->assertSame('https://api.openai.com/v1', $request['resource']);

            return true;
        });
        $this->assertSame('CONNECTED', $connection->status);
        $this->assertSame('subject-fixture', $connection->subject);
        $this->assertSame('access-one', $connection->access_token);
        $this->assertNotSame('access-one', DB::table('chatgpt_connections')->value('access_token'));
        $this->assertStringNotContainsString('access-one', $connection->toJson());
        $this->assertNull($connection->id_token);
        parse_str(parse_url(app(ConnectionService::class)->start($user, $connection->id), PHP_URL_QUERY), $returning);
        $this->assertSame('oaiapp_fixture', $returning['client_id']);
        $this->assertArrayNotHasKey('agent_name_hint', $returning);
        $this->assertArrayNotHasKey('id_token_hint', $returning);
    }

    public function test_state_replay_invalid_callback_and_expiration_are_rejected_without_exchange(): void
    {
        $parameters = $this->parameters($this->user('state'));
        foreach (['wrong', str_repeat('A', 43)] as $state) {
            $this->expectPlanError(fn () => app(ConnectionService::class)->complete(['state' => $state]), 'OAUTH_STATE_INVALID');
        }
        Cache::forget('chatgpt:attempt:'.hash('sha256', $parameters['state']));
        $this->expectPlanError(fn () => app(ConnectionService::class)->complete(['state' => $parameters['state']]), 'OAUTH_STATE_INVALID_OR_EXPIRED');
        Http::assertNothingSent();
        config(['chatgpt.callback_uri' => 'http://localhost:8080/api/v1/chatgpt/callback']);
        $this->expectPlanError(fn () => app(ConnectionService::class)->start($this->user('wrong-loopback')), 'LOOPBACK_CALLBACK_INVALID');
    }

    public function test_decline_consumes_state_and_never_exchanges_a_code(): void
    {
        $parameters = $this->parameters($this->user('decline'));
        $args = ['state' => $parameters['state'], 'error' => 'access_denied', 'client_id' => 'oaiapp_declined'];
        $this->expectPlanError(fn () => app(ConnectionService::class)->complete($args), 'OAUTH_DECLINED');
        $this->expectPlanError(fn () => app(ConnectionService::class)->complete($args), 'OAUTH_STATE_INVALID_OR_EXPIRED');
        Http::assertNothingSent();
        $this->assertDatabaseHas('chatgpt_connections', ['client_id' => 'oaiapp_declined', 'status' => 'NOT_CONNECTED']);
    }

    public function test_nonce_audience_signature_issuer_and_expiration_are_validated(): void
    {
        $this->fakeKeys();
        foreach ([['nonce' => 'wrong'], ['aud' => 'other'], ['iss' => 'https://attacker.test'], ['exp' => time() - 5], ['sub' => '']] as $bad) {
            $this->expectPlanError(fn () => app(OpenIdVerifier::class)->verify($this->jwt($bad), 'oaiapp_fixture', 'nonce-fixture'), 'IDENTITY_INVALID');
        }
        $this->expectPlanError(fn () => app(OpenIdVerifier::class)->verify($this->jwt().'x', 'oaiapp_fixture', 'nonce-fixture'), 'IDENTITY_INVALID');
        $this->assertSame('subject-fixture', app(OpenIdVerifier::class)->verify($this->jwt(), 'oaiapp_fixture', 'nonce-fixture')['sub']);
    }

    public function test_valid_identity_without_sharing_scope_is_saved_but_cannot_infer(): void
    {
        $user = $this->user('missing-scope');
        $parameters = $this->parameters($user);
        $this->fakeOAuth($parameters, ['scope' => 'openid profile email offline_access resource.invoke']);
        $connection = app(ConnectionService::class)->complete(['state' => $parameters['state'], 'code' => 'code', 'client_id' => 'oaiapp_fixture']);
        $this->assertSame('PLAN_PERMISSION_MISSING', $connection->status);
        $this->expectPlanError(fn () => app(ConnectionService::class)->accessToken($user, $connection->id), 'PLAN_PERMISSION_MISSING');
    }

    public function test_reconnect_rejects_different_account_and_callback_client(): void
    {
        $user = $this->user('reconnect');
        $connection = $this->connection($user);
        $parameters = $this->parameters($user, $connection->id);
        $this->fakeOAuth($parameters, [], ['sub' => 'another-account']);
        $this->expectPlanError(fn () => app(ConnectionService::class)->complete(['state' => $parameters['state'], 'code' => 'code']), 'OAUTH_ACCOUNT_MISMATCH');
        $this->assertSame('access-one', $connection->fresh()->access_token);
        $parameters = $this->parameters($user, $connection->id);
        $this->expectPlanError(fn () => app(ConnectionService::class)->complete(['state' => $parameters['state'], 'code' => 'code', 'client_id' => 'oaiapp_other']), 'OAUTH_REGISTRATION_INVALID');
        $parameters = $this->parameters($user, $connection->id);
        $this->fakeOAuth($parameters, ['access_token' => 'reconnected']);
        $this->assertSame('reconnected', app(ConnectionService::class)->complete(['state' => $parameters['state'], 'code' => 'code'])->access_token);
    }

    public function test_refresh_replaces_token_chain_once_and_respects_earliest_refresh_time(): void
    {
        $user = $this->user('refresh');
        $connection = $this->connection($user, ['expires_at' => now()->subSecond()]);
        Http::fake([config('chatgpt.token_url') => Http::response($this->tokens(['access_token' => 'access-two', 'refresh_token' => 'refresh-two']))]);
        $service = app(ConnectionService::class);
        $this->assertSame('access-two', $service->accessToken($user, $connection->id));
        $this->assertSame('access-two', $service->accessToken($user, $connection->id));
        Http::assertSentCount(1);
        Http::assertSent(fn ($request): bool => $request['refresh_token'] === 'refresh-one' && $request['client_id'] === 'oaiapp_fixture' && ! isset($request['scope']));
        $this->assertSame('refresh-two', $connection->fresh()->refresh_token);
        $connection->refresh()->update(['expires_at' => now()->subSecond(), 'earliest_refresh_at' => now()->addMinute()]);
        $this->expectPlanError(fn () => $service->accessToken($user, $connection->id), 'REFRESH_NOT_YET_ALLOWED');
        Http::assertSentCount(1);
    }

    public function test_terminal_refresh_error_clears_tokens_but_transient_failure_preserves_them(): void
    {
        $user = $this->user('refresh-error');
        $connection = $this->connection($user, ['expires_at' => now()->subSecond()]);
        Http::fakeSequence(config('chatgpt.token_url'))->push(['error' => 'temporary'], 503)->push(['error' => 'invalid_grant'], 400);
        $this->expectPlanError(fn () => app(ConnectionService::class)->accessToken($user, $connection->id), 'OAUTH_PROVIDER_UNAVAILABLE');
        $this->assertSame('refresh-one', $connection->fresh()->refresh_token);
        $this->expectPlanError(fn () => app(ConnectionService::class)->accessToken($user, $connection->id), 'invalid_grant');
        $this->assertNull($connection->fresh()->refresh_token);
        $this->assertSame('REAUTHENTICATION_REQUIRED', $connection->fresh()->status);
    }

    public function test_current_model_catalog_and_responses_contract_without_api_key(): void
    {
        $user = $this->user('provider');
        $connection = $this->connection($user);
        config(['ai.providers.openai.api_key' => null]);
        $events = "data: {\"type\":\"response.output_text.delta\",\"delta\":\"Hello\"}\r\n\r\n".
            "data: {\"type\":\"response.completed\",\"response\":{\"status\":\"completed\"}}\n\n";
        Http::fake([
            'https://api.openai.com/v1/models' => Http::response(['models' => [
                ['slug' => 'current-account-model', 'display_name' => 'Current model', 'visibility' => 'list'],
                ['slug' => 'hidden', 'display_name' => 'Hidden', 'visibility' => 'hide'],
            ]]),
            'https://api.openai.com/v1/responses' => Http::response($events, 200, ['x-request-id' => 'request-fixture']),
        ]);
        $provider = app(OpenAiChatGptPlanProvider::class);
        $this->assertSame([['slug' => 'current-account-model', 'display_name' => 'Current model']], $provider->models($user, $connection->id));
        $result = iterator_to_array($provider->stream($user, $connection->id, 'current-account-model', 'trusted', [['role' => 'user', 'content' => 'hello']]));
        $this->assertSame(['delta', 'completed'], array_column($result, 'type'));
        Http::assertSent(function ($request): bool {
            if (! str_ends_with($request->url(), '/responses')) {
                return false;
            }
            $this->assertSame(['Bearer access-one'], $request->header('Authorization'));
            $this->assertFalse($request['store']);
            $this->assertTrue($request['stream']);
            $this->assertSame(['model', 'instructions', 'input', 'store', 'stream'], array_keys($request->data()));

            return true;
        });
    }

    public function test_connection_proof_rejects_a_model_policy_violation_before_provider_access(): void
    {
        $user = $this->user('proof-policy');
        $connection = $this->connection($user);
        config(['ai.model_policies.user_selected_chatgpt_plan.selection' => 'fixed']);
        Http::preventStrayRequests();

        $this->actingAs($user)->postJson('/api/v1/chatgpt/connections/'.$connection->id.'/proof', ['model' => 'valid-looking-model'])
            ->assertUnprocessable()->assertJsonPath('error.code', 'MODEL_NOT_ALLOWED');

        Http::assertNothingSent();
    }

    public function test_stream_interruption_failed_usage_limit_and_unsupported_capability(): void
    {
        $user = $this->user('stream-failure');
        $connection = $this->connection($user);
        $responses = [
            ["data: {\"type\":\"response.output_text.delta\",\"delta\":\"partial\"}\n\n", 200],
            ["data: {\"type\":\"response.failed\",\"response\":{\"error\":{\"code\":\"subscription_sharing_usage_limit_exceeded\"}}}\n\n", 200],
            [json_encode(['error' => ['code' => 'subscription_sharing_unsupported_capability']], JSON_THROW_ON_ERROR), 400],
            [json_encode(['detail' => 'private admission diagnostic'], JSON_THROW_ON_ERROR), 403],
        ];
        $responseIndex = 0;
        Http::fake(function ($request) use (&$responseIndex, $responses) {
            if (str_ends_with($request->url(), '/models')) {
                return Http::response(['models' => [['slug' => 'model', 'display_name' => 'Model', 'visibility' => 'list']]]);
            }
            if (str_ends_with($request->url(), '/responses')) {
                [$body, $status] = $responses[$responseIndex++];

                return Http::response($body, $status);
            }

            return Http::response([], 404);
        });
        foreach (['STREAM_INTERRUPTED', 'subscription_sharing_usage_limit_exceeded', 'subscription_sharing_unsupported_capability', 'PLAN_UNAVAILABLE'] as $code) {
            $this->expectPlanError(fn () => iterator_to_array(app(OpenAiChatGptPlanProvider::class)->stream($user, $connection->id, 'model', 'trusted', [])), $code);
        }
    }

    public function test_other_user_cannot_list_use_reconnect_or_delete_credentials(): void
    {
        $owner = $this->user('owner');
        $other = $this->user('other');
        $connection = $this->connection($owner);
        $this->actingAs($other)->getJson('/api/v1/chatgpt/connections')->assertOk()->assertJsonPath('data', []);
        $this->actingAs($other)->getJson('/api/v1/chatgpt/connections/'.$connection->id.'/models')->assertNotFound();
        $this->actingAs($other)->postJson('/api/v1/chatgpt/connections', ['connection_id' => $connection->id])->assertNotFound();
        $this->actingAs($other)->postJson('/api/v1/chatgpt/connections/'.$connection->id.'/proof', ['model' => 'test'])->assertNotFound();
        $this->actingAs($other)->deleteJson('/api/v1/chatgpt/connections/'.$connection->id)->assertNotFound();
        $this->assertSame('access-one', $connection->fresh()->access_token);
        Http::assertNothingSent();
    }

    public function test_disconnect_retains_registration_and_reports_unconfirmed_revocation(): void
    {
        $user = $this->user('disconnect');
        $connection = $this->connection($user);
        Http::fake([config('chatgpt.discovery_url') => Http::response([], 503)]);
        $this->assertFalse(app(ConnectionService::class)->disconnect($user, $connection->id));
        $this->assertSame('oaiapp_fixture', $connection->fresh()->client_id);
        $this->assertNull($connection->fresh()->access_token);
    }

    public function test_disconnect_invalidates_a_pending_reconnect_callback(): void
    {
        $user = $this->user('disconnect-pending');
        $connection = $this->connection($user);
        $parameters = $this->parameters($user, $connection->id);
        $this->fakeOAuth($parameters);
        Http::fake([config('chatgpt.discovery_url') => Http::response([], 503)]);

        $this->assertFalse(app(ConnectionService::class)->disconnect($user, $connection->id));
        $this->expectPlanError(
            fn () => app(ConnectionService::class)->complete(['state' => $parameters['state'], 'code' => 'code']),
            'OAUTH_ATTEMPT_INVALIDATED',
        );
        Http::assertNotSent(fn ($request): bool => $request->url() === config('chatgpt.token_url'));
        $this->assertSame(1, $connection->fresh()->oauth_generation);
        $this->assertSame('NOT_CONNECTED', $connection->fresh()->status);
        $this->assertNull($connection->fresh()->access_token);
    }

    public function test_disconnect_invalidates_a_pending_initial_registration_callback(): void
    {
        $user = $this->user('disconnect-initial-pending');
        $connection = $this->connection($user, [
            'access_token' => null, 'expires_at' => null, 'status' => 'NOT_CONNECTED',
        ]);
        $parameters = $this->parameters($user);
        $this->fakeOAuth($parameters);
        Http::fake([config('chatgpt.discovery_url') => Http::response([], 503)]);

        $this->assertFalse(app(ConnectionService::class)->disconnect($user, $connection->id));
        $this->expectPlanError(
            fn () => app(ConnectionService::class)->complete([
                'state' => $parameters['state'], 'code' => 'code', 'client_id' => 'oaiapp_fixture',
            ]),
            'OAUTH_ATTEMPT_INVALIDATED',
        );
        Http::assertNotSent(fn ($request): bool => $request->url() === config('chatgpt.token_url'));
        $this->assertSame(1, $connection->fresh()->oauth_generation);
        $this->assertSame('NOT_CONNECTED', $connection->fresh()->status);
        $this->assertNull($connection->fresh()->access_token);
    }

    private function user(string $name): User
    {
        return User::query()->create(['email' => $name.'@example.test', 'password' => 'long-fixture-password', 'role' => 'user', 'status' => 'ACTIVE'])->fresh();
    }

    private function parameters(User $user, ?string $id = null): array
    {
        parse_str(parse_url(app(ConnectionService::class)->start($user, $id), PHP_URL_QUERY), $parameters);

        return $parameters;
    }

    private function connection(User $user, array $extra = []): ChatGptConnection
    {
        return ChatGptConnection::query()->create(array_merge([
            'owner_id' => $user->id, 'client_id' => 'oaiapp_fixture', 'subject' => 'subject-fixture',
            'scopes' => ['resource.invoke', 'chatgpt.tokens.use.direct'], 'access_token' => 'access-one',
            'refresh_token' => 'refresh-one', 'status' => 'CONNECTED', 'expires_at' => now()->addHour(),
        ], $extra));
    }

    private function tokens(array $extra = []): array
    {
        return array_merge(['access_token' => 'access-one', 'refresh_token' => 'refresh-one', 'token_type' => 'Bearer',
            'scope' => 'openid profile email offline_access resource.invoke chatgpt.tokens.use.direct', 'expires_in' => 3600], $extra);
    }

    private function fakeOAuth(array $parameters, array $extra = [], array $claims = []): void
    {
        $this->fakeKeys([config('chatgpt.token_url') => Http::response($this->tokens(array_merge([
            'id_token' => $this->jwt(array_merge(['nonce' => $parameters['nonce']], $claims)),
        ], $extra)))]);
    }

    private function fakeKeys(array $additional = []): void
    {
        Http::swap(new Factory);
        Http::preventStrayRequests();
        $jwk = json_decode(self::$signingKey->getPublicKey()->toString('JWK'), true)['keys'][0];
        Http::fake(array_merge([
            config('chatgpt.discovery_url') => Http::response(['issuer' => 'https://auth.openai.com', 'jwks_uri' => 'https://auth.openai.com/jwks']),
            'https://auth.openai.com/jwks' => Http::response(['keys' => [array_merge($jwk, ['kid' => 'fixture', 'alg' => 'RS256'])]]),
        ], $additional));
    }

    private function jwt(array $overrides = []): string
    {
        $head = $this->b64(json_encode(['alg' => 'RS256', 'kid' => 'fixture']));
        $claims = $this->b64(json_encode(array_merge(['iss' => 'https://auth.openai.com', 'aud' => 'oaiapp_fixture',
            'sub' => 'subject-fixture', 'exp' => time() + 3600, 'nonce' => 'nonce-fixture'], $overrides)));

        return $head.'.'.$claims.'.'.$this->b64(self::$signingKey->withHash('sha256')->withPadding(RSA::SIGNATURE_PKCS1)->sign($head.'.'.$claims));
    }

    private function b64(string $value): string
    {
        return rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
    }

    private function expectPlanError(callable $operation, string $code): void
    {
        try {
            $operation();
            $this->fail('Expected controlled failure.');
        } catch (PlanException $exception) {
            $this->assertStringStartsWith($code, $exception->errorCode);
        }
    }
}
