<?php

namespace Tests\Feature;

use App\AI\Contracts\LlmProvider;
use App\AI\Data\LlmRequest;
use App\AI\Data\LlmResponse;
use App\Mcp\CvortexServer;
use App\Mcp\McpApplicationAdapter;
use App\Mcp\Tools\ApplicationContextGet;
use App\Mcp\Tools\ApplicationDraftSubmit;
use App\Mcp\Tools\VacancyGet;
use App\Models\User;
use App\Services\CareerFactService;
use App\Services\UserStatusService;
use App\Services\VacancyAnalysisService;
use App\Services\VacancyIngestionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\RateLimiter;
use Laravel\Passport\ClientRepository;
use Laravel\Passport\Contracts\AuthorizationViewResponse;
use Laravel\Passport\Passport;
use phpseclib4\Crypt\RSA;
use Tests\TestCase;

class McpGatewayTest extends TestCase
{
    use RefreshDatabase;

    private static ?string $passportPrivateKey = null;

    private static ?string $passportPublicKey = null;

    private int $id = 1;

    protected function setUp(): void
    {
        parent::setUp();
        config(['app.key' => 'base64:AAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAA=']);
        if (self::$passportPrivateKey === null) {
            $key = RSA::createKey(2048);
            self::$passportPrivateKey = (string) $key;
            self::$passportPublicKey = (string) $key->getPublicKey();
        }
        config(['passport.private_key' => self::$passportPrivateKey, 'passport.public_key' => self::$passportPublicKey]);
        Queue::fake();
        $this->app->instance(LlmProvider::class, new McpGatewayFakeProvider);
    }

    public function test_tool_discovery_is_bounded_and_schemas_are_strict(): void
    {
        CvortexServer::tools()->assertRegistered([
            VacancyGet::class,
            ApplicationContextGet::class,
            ApplicationDraftSubmit::class,
        ]);
        $tools = [new VacancyGet, new ApplicationContextGet, new ApplicationDraftSubmit];
        foreach ($tools as $tool) {
            $this->assertFalse($tool->toArray()['inputSchema']['additionalProperties']);
            $this->assertSame(['mcp:use'], $tool->toArray()['securitySchemes'][0]['scopes']);
        }
    }

    public function test_http_requires_bearer_scope_and_owner_for_reads_and_writes(): void
    {
        [$owner, $vacancyId] = $this->vacancy('mcp-owner@example.test');
        [$other, $foreignId] = $this->vacancy('mcp-other@example.test');

        $this->postJson('/mcp/v1', $this->mcpCall('vacancy_get', ['vacancy_id' => $vacancyId]))->assertUnauthorized();
        $this->withToken('invalid')->postJson('/mcp/v1', $this->mcpCall('vacancy_get', ['vacancy_id' => $vacancyId]))->assertUnauthorized();
        Passport::actingAs($owner, [], 'api');
        $this->withToken('test-token')->postJson('/mcp/v1', $this->mcpCall('vacancy_get', ['vacancy_id' => $vacancyId]))->assertForbidden();

        Passport::actingAs($owner, ['mcp:use'], 'api');
        $own = $this->withToken('test-token')->postJson('/mcp/v1', $this->mcpCall('vacancy_get', ['vacancy_id' => $vacancyId]))->assertOk()->json();
        $this->assertSame($vacancyId, $own['result']['structuredContent']['id']);
        $foreign = $this->postJson('/mcp/v1', $this->mcpCall('vacancy_get', ['vacancy_id' => $foreignId]))->assertOk()->json();
        $this->assertTrue($foreign['result']['isError']);
        $this->assertSame('NOT_FOUND', $foreign['result']['content'][0]['text']);
        $write = $this->postJson('/mcp/v1', $this->mcpCall('application_draft_submit', [
            'vacancy_id' => $foreignId, 'variant' => 'SHORT', 'content' => 'Built Laravel APIs.', 'claim_usages' => [],
        ]))->assertOk()->json();
        $this->assertSame('NOT_FOUND', $write['result']['content'][0]['text']);
        $this->assertDatabaseMissing('application_preparations', ['owner_id' => $owner->id, 'vacancy_id' => $foreignId]);
        $this->assertNotSame($owner->id, $other->id);

        RateLimiter::clear('mcp:write:'.$owner->id);
        for ($attempt = 0; $attempt < 10; $attempt++) {
            RateLimiter::hit('mcp:write:'.$owner->id, 60);
        }
        $this->postJson('/mcp/v1', $this->mcpCall('application_draft_submit', [
            'vacancy_id' => $vacancyId, 'variant' => 'SHORT', 'content' => 'Built Laravel APIs.', 'claim_usages' => [],
        ]))->assertTooManyRequests();
        RateLimiter::clear('mcp:write:'.$owner->id);
    }

    public function test_context_and_draft_preserve_truth_guard_and_human_approval(): void
    {
        [$owner, $vacancyId] = $this->vacancy('mcp-draft@example.test');
        Passport::actingAs($owner, ['mcp:use'], 'api');
        $this->withToken('test-token');
        $context = $this->postJson('/mcp/v1', $this->mcpCall('application_context_get', ['vacancy_id' => $vacancyId]))->assertOk()->json('result.structuredContent');
        $this->assertSame($vacancyId, $context['vacancy']['id']);
        $this->assertArrayNotHasKey('source_excerpt', $context['requirements'][0]);
        $claim = $context['confirmed_claims'][0];
        $this->assertArrayNotHasKey('source_excerpt', $claim['confirmed_facts'][0]);

        $blocked = $this->postJson('/mcp/v1', $this->mcpCall('application_draft_submit', [
            'vacancy_id' => $vacancyId, 'variant' => 'SHORT', 'content' => 'Led 100 engineers.',
            'claim_usages' => [['assertion' => 'Led 100 engineers.', 'claim_ids' => [$claim['id']]]],
        ]))->assertOk()->json();
        $this->assertSame('TRUTH_GUARD_BLOCKED', $blocked['result']['content'][0]['text']);
        $this->assertDatabaseCount('application_draft_items', 0);

        $forgedOwner = $this->postJson('/mcp/v1', $this->mcpCall('application_draft_submit', [
            'vacancy_id' => $vacancyId, 'variant' => 'SHORT', 'content' => 'Built Laravel APIs.',
            'claim_usages' => [['assertion' => 'Built Laravel APIs.', 'claim_ids' => [$claim['id']]]],
            'user_id' => '01m3av06gw5mw14e68cr0mwky9',
        ]))->assertOk()->json();
        $this->assertSame('VALIDATION_FAILED', $forgedOwner['result']['content'][0]['text']);
        $this->assertDatabaseCount('application_draft_items', 0);

        $passed = $this->postJson('/mcp/v1', $this->mcpCall('application_draft_submit', [
            'vacancy_id' => $vacancyId, 'variant' => 'SHORT', 'content' => 'Built Laravel APIs.',
            'claim_usages' => [['assertion' => 'Built Laravel APIs.', 'claim_ids' => [$claim['id']]]],
        ]))->assertOk()->json('result.structuredContent');
        $this->assertSame('PENDING_REVIEW', $passed['review_state']);
        $this->assertTrue($passed['requires_cvortex_approval']);
        $this->assertDatabaseHas('application_draft_items', ['id' => $passed['draft_id'], 'status' => 'DRAFT']);
        $this->assertDatabaseMissing('application_approval_events', ['draft_item_id' => $passed['draft_id'], 'action' => 'APPROVED']);
        $this->assertDatabaseMissing('career_facts', ['owner_id' => $owner->id, 'assertion_approved' => 'Led 100 engineers.']);

        /** @var McpGatewayFakeProvider $provider */
        $provider = app(LlmProvider::class);
        $factId = $claim['confirmed_facts'][0]['id'];
        $provider->afterReview = fn () => DB::table('career_facts')->where('id', $factId)->update(['status' => 'PENDING']);
        $stale = $this->postJson('/mcp/v1', $this->mcpCall('application_draft_submit', [
            'vacancy_id' => $vacancyId, 'variant' => 'STANDARD', 'content' => 'Built Laravel APIs.',
            'claim_usages' => [['assertion' => 'Built Laravel APIs.', 'claim_ids' => [$claim['id']]]],
        ]))->assertOk()->json();
        $this->assertSame('TRUTH_GUARD_BLOCKED', $stale['result']['content'][0]['text']);
        $this->assertDatabaseMissing('application_draft_items', ['preparation_id' => $passed['preparation_id'], 'variant' => 'STANDARD']);
    }

    public function test_real_passport_tokens_are_rejected_after_revocation_or_disable(): void
    {
        app(ClientRepository::class)->createPersonalAccessGrantClient('MCP test');
        [$user, $vacancyId] = $this->vacancy('mcp-token@example.test');
        $issued = $user->createToken('test', ['mcp:use']);
        $this->withToken($issued->accessToken)->postJson('/mcp/v1', $this->mcpCall('vacancy_get', ['vacancy_id' => $vacancyId]))->assertOk();
        DB::table('oauth_access_tokens')->where('id', $issued->token->id)->update(['revoked' => true]);
        Auth::forgetGuards();
        $this->postJson('/mcp/v1', $this->mcpCall('vacancy_get', ['vacancy_id' => $vacancyId]))->assertUnauthorized();

        $active = $user->createToken('second', ['mcp:use']);
        $normalExpiry = Passport::personalAccessTokensExpireIn();
        Passport::personalAccessTokensExpireIn(now()->subMinute());
        try {
            $expired = $user->createToken('expired', ['mcp:use']);
        } finally {
            Passport::personalAccessTokensExpireIn($normalExpiry);
        }
        Auth::forgetGuards();
        $this->withToken($expired->accessToken)->postJson('/mcp/v1', $this->mcpCall('vacancy_get', ['vacancy_id' => $vacancyId]))->assertUnauthorized();
        app(UserStatusService::class)->disable($user);
        $this->assertDatabaseHas('oauth_access_tokens', ['id' => $active->token->id, 'revoked' => true]);
        Auth::forgetGuards();
        $this->withToken($active->accessToken)->postJson('/mcp/v1', $this->mcpCall('vacancy_get', ['vacancy_id' => $vacancyId]))->assertUnauthorized();
    }

    public function test_oauth_metadata_and_registration_allowlist(): void
    {
        $this->getJson('/.well-known/oauth-protected-resource/mcp/v1')->assertOk()
            ->assertJsonPath('scopes_supported.0', 'mcp:use');
        $this->getJson('/.well-known/oauth-authorization-server')->assertOk()
            ->assertJsonPath('code_challenge_methods_supported.0', 'S256');
        $this->postJson('/oauth/register', [
            'client_name' => 'Untrusted', 'redirect_uris' => ['https://attacker.example.test/callback'],
        ])->assertBadRequest()->assertJsonPath('error', 'invalid_redirect_uri');
        $this->postJson('/oauth/register', [
            'client_name' => 'ChatGPT test', 'redirect_uris' => ['https://chatgpt.com/connector/oauth/test-callback'],
        ])->assertCreated()->assertJsonPath('scope', 'mcp:use')
            ->assertJsonPath('token_endpoint_auth_method', 'none');
        $this->postJson('/oauth/register', [
            'client_name' => 'Unsupported callback', 'redirect_uris' => ['https://chatgpt.com/connector_platform_oauth_redirect'],
        ])->assertBadRequest()->assertJsonPath('error', 'invalid_redirect_uri');
    }

    public function test_oauth_consent_uses_the_cvortex_view(): void
    {
        $user = User::query()->create(['email' => 'mcp-consent@example.test', 'password' => 'a very long safe passphrase']);
        $view = app(AuthorizationViewResponse::class)->withParameters([
            'client' => (object) ['id' => 'test-client', 'name' => 'ChatGPT test'],
            'user' => $user,
            'scopes' => [],
            'request' => (object) ['state' => 'test-state'],
            'authToken' => 'test-auth-token',
        ]);

        $response = $view->toResponse(request());
        $this->assertStringContainsString('Authorize ChatGPT test?', $response->getContent());
        $this->assertStringContainsString('It cannot approve or send applications', $response->getContent());
    }

    public function test_internal_errors_do_not_leak_secrets_or_paths(): void
    {
        $user = User::query()->create(['email' => 'mcp-error@example.test', 'password' => 'a very long safe passphrase'])->fresh();
        $adapter = \Mockery::mock(McpApplicationAdapter::class);
        $adapter->shouldReceive('vacancy')->once()->andThrow(new \RuntimeException('private-token /private/path'));
        $this->app->instance(McpApplicationAdapter::class, $adapter);
        Passport::actingAs($user, ['mcp:use'], 'api');

        $response = $this->withToken('test-token')->postJson('/mcp/v1', $this->mcpCall('vacancy_get', [
            'vacancy_id' => '01m3av06gw5mw14e68cr0mwky9',
        ]))->assertOk();
        $this->assertSame('INTERNAL_ERROR', $response->json('result.content.0.text'));
        $this->assertStringNotContainsString('private-token', $response->getContent());
        $this->assertStringNotContainsString('/private/path', $response->getContent());
    }

    private function vacancy(string $email): array
    {
        $user = User::query()->create(['email' => $email, 'password' => 'a very long safe passphrase'])->fresh();
        app(CareerFactService::class)->createManual($user, 'skill', 'Built Laravel APIs.');
        $queued = app(VacancyIngestionService::class)->queue($user, 'Backend Engineer. Laravel is required.', null);
        app(VacancyAnalysisService::class)->analyze($user, $queued['snapshot']);

        return [$user, (string) $queued['vacancy']->id];
    }

    private function mcpCall(string $name, array $arguments): array
    {
        return ['jsonrpc' => '2.0', 'id' => $this->id++, 'method' => 'tools/call', 'params' => [
            'name' => $name, 'arguments' => $arguments,
        ]];
    }
}

class McpGatewayFakeProvider implements LlmProvider
{
    public ?\Closure $afterReview = null;

    public function generateStructured(LlmRequest $request): LlmResponse
    {
        if ($request->schemaName === 'vacancy_requirements') {
            $output = ['requirements' => [[
                'dimension' => 'TECHNICAL', 'importance' => 'MANDATORY', 'label' => 'Laravel',
                'normalized_value' => 'laravel', 'source_excerpt' => 'Laravel is required.', 'confidence' => 0.9,
            ]]];
        } elseif ($request->schemaName === 'application_truth_review') {
            $input = json_decode($request->untrustedSourceText, true, flags: JSON_THROW_ON_ERROR);
            $candidate = $input['candidate_items'][0];
            $content = $candidate['candidate_content'];
            $claimIds = $candidate['proposed_claim_usages'][0]['claim_ids'] ?? [];
            $output = ['reviews' => [[
                'item_id' => $candidate['item_id'], 'status' => 'PASS',
                'segments' => [['text' => $content, 'kind' => 'FACTUAL', 'claim_ids' => $claimIds]],
            ]]];
            $callback = $this->afterReview;
            $this->afterReview = null;
            $callback?->__invoke();
        } else {
            throw new \LogicException('Unexpected skill.');
        }

        return new LlmResponse($output, 'fake', 'fake-structured', 20, 10, 3, 'mcp-test', 7);
    }
}
