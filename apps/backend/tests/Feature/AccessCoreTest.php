<?php

namespace Tests\Feature;

use App\Authorization\OwnershipPolicy;
use App\Models\AuditEvent;
use App\Models\Invitation;
use App\Models\User;
use App\Services\AuditLogger;
use App\Services\InvitationService;
use App\Services\UserStatusService;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class AccessCoreTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['sanctum.stateful' => ['localhost']]);
        config(['session.driver' => 'database']);
        foreach (['missing@example.test', 'login@example.test', 'limit@example.test', 'isolated@example.test', 'sessions@example.test', 'flow@example.test', 'enable@example.test'] as $email) {
            foreach (['127.0.0.1', '::1'] as $ip) {
                $this->clearLoginLimiter($ip, $email);
            }
        }
        Schema::create('test_private_resources', function ($table): void {
            $table->ulid('id')->primary();
            $table->ulid('owner_id')->index();
            $table->string('value');
        });
        Route::middleware(['auth:sanctum', 'active-user'])->prefix('api/v1/_test')->group(function (): void {
            Route::get('/private', fn (Request $request) => ['data' => \DB::table('test_private_resources')->where('owner_id', $request->user()->id)->get()]);
            Route::post('/private', function (Request $request) {
                $data = $request->validate(['value' => ['required', 'string']]);
                $id = (string) Str::ulid();
                \DB::table('test_private_resources')->insert(['id' => $id, 'owner_id' => $request->user()->id, 'value' => $data['value']]);

                return response()->json(['data' => \DB::table('test_private_resources')->where('id', $id)->first()], 201);
            });
            Route::get('/private/{id}', function (Request $request, string $id) {
                $resource = \DB::table('test_private_resources')->where('id', $id)->first();
                abort_if($resource === null || ! app(OwnershipPolicy::class)->owns($request->user(), $resource->owner_id), 404);

                return ['data' => $resource];
            });
            Route::put('/private/{id}', function (Request $request, string $id) {
                $resource = \DB::table('test_private_resources')->where('id', $id)->first();
                abort_if($resource === null || ! app(OwnershipPolicy::class)->owns($request->user(), $resource->owner_id), 404);
                \DB::table('test_private_resources')->where('id', $id)->update(['value' => $request->validate(['value' => ['required', 'string']])['value']]);

                return response()->noContent();
            });
            Route::delete('/private/{id}', function (Request $request, string $id) {
                $resource = \DB::table('test_private_resources')->where('id', $id)->first();
                abort_if($resource === null || ! app(OwnershipPolicy::class)->owns($request->user(), $resource->owner_id), 404);
                \DB::table('test_private_resources')->where('id', $id)->delete();

                return response()->noContent();
            });
            Route::get('/known-capability', fn () => abort(403));
        });
    }

    public function test_targeted_invitation_registers_normalized_email_once(): void
    {
        ['invitation' => $invitation, 'token' => $token] = app(InvitationService::class)->create('Person@Example.test', 7);
        $user = app(InvitationService::class)->register($token, ' person@example.test ', 'a very long safe passphrase');

        $this->assertSame('person@example.test', $user->email);
        $this->assertSame(User::ROLE_USER, $user->role);
        $this->assertSame(User::STATUS_ACTIVE, $user->status);
        $this->assertSame(1, $invitation->fresh()->uses);
        $this->assertDatabaseCount('audit_events', 3);
    }

    public function test_target_mismatch_and_duplicate_email_do_not_consume_invitation(): void
    {
        ['invitation' => $targeted, 'token' => $token] = app(InvitationService::class)->create('right@example.test', 7);
        try {
            app(InvitationService::class)->register($token, 'wrong@example.test', 'a very long safe passphrase');
        } catch (ValidationException) {
        }
        $this->assertSame(0, $targeted->fresh()->uses);

        User::query()->create(['email' => 'right@example.test', 'password' => Hash::make('a very long safe passphrase')]);
        try {
            app(InvitationService::class)->register($token, 'right@example.test', 'a very long safe passphrase');
        } catch (ValidationException) {
        }
        $this->assertSame(0, $targeted->fresh()->uses);
    }

    public function test_invitation_token_is_hashed_and_not_serialized(): void
    {
        ['invitation' => $invitation, 'token' => $token] = app(InvitationService::class)->create(null, 7);
        $this->assertNotSame($token, $invitation->token_hash);
        $this->assertArrayNotHasKey('token_hash', $invitation->toArray());
        $this->assertDatabaseHas('audit_events', ['event_type' => 'invitation.created', 'actor_type' => AuditEvent::ACTOR_OPERATOR]);
    }

    public function test_generic_invitation_cli_creates_usable_one_time_invitation_and_never_reveals_it_again(): void
    {
        $this->assertSame(0, Artisan::call('invitation:create', ['--expires' => 7]));
        $output = Artisan::output();
        preg_match('/\/register#token=([a-f0-9]{64})/', $output, $matches);
        $this->assertCount(2, $matches);
        $token = $matches[1];
        $invitation = Invitation::query()->sole();
        $this->assertNull($invitation->target_email);
        $user = app(InvitationService::class)->register($token, 'generic@example.test', 'a very long safe passphrase');
        $this->assertSame('generic@example.test', $user->email);
        $this->assertSame(1, $invitation->fresh()->uses);
        $this->assertStringNotContainsString($token, $invitation->fresh()->toJson());
        $this->assertStringNotContainsString($token, implode('|', AuditEvent::query()->pluck('metadata')->map(fn ($metadata) => json_encode($metadata))->all()));
    }

    public function test_registration_rolls_back_user_invitation_and_audit_when_completion_fails(): void
    {
        ['invitation' => $invitation, 'token' => $token] = app(InvitationService::class)->create(null, 7);
        $audit = $this->mock(AuditLogger::class);
        $audit->shouldReceive('record')->once()->andReturn(new AuditEvent);
        $audit->shouldReceive('record')->once()->andThrow(new \RuntimeException('injected audit failure'));
        $this->app->instance(AuditLogger::class, $audit);

        $this->expectException(\RuntimeException::class);
        app(InvitationService::class)->register($token, 'rollback@example.test', 'a very long safe passphrase');

        $this->assertDatabaseMissing('users', ['email' => 'rollback@example.test']);
        $this->assertSame(0, $invitation->fresh()->uses);
        $this->assertDatabaseMissing('audit_events', ['event_type' => 'user.registered']);
    }

    public function test_normal_application_logs_are_redacted_for_credentials(): void
    {
        Log::spy();
        ['invitation' => $invitation, 'token' => $token] = app(InvitationService::class)->create(null, 7);
        $password = 'a very long safe passphrase';
        $user = app(InvitationService::class)->register($token, 'logs@example.test', $password);
        $cookies = $this->csrfCookies();
        $this->withoutMiddleware(ValidateCsrfToken::class)->withHeader('Origin', 'http://localhost')->withCookie(config('session.cookie'), $cookies['session'])->postJson('/api/v1/auth/login', ['email' => $user->email, 'password' => $password])->assertOk();
        Log::shouldHaveReceived('info')->once()->withArgs(function (string $message, array $context) use ($token, $password): bool {
            $serialized = json_encode([$message, $context]);

            return ! str_contains($serialized, $token)
                && ! str_contains($serialized, $password)
                && ! str_contains($serialized, 'session_id')
                && ! str_contains($serialized, 'csrf')
                && ! str_contains($serialized, 'authorization');
        });
        $this->assertNotNull($invitation->fresh());
    }

    public function test_disabled_user_loses_access_and_sole_admin_is_preserved(): void
    {
        $admin = new User;
        $admin->forceFill(['email' => 'admin@example.test', 'password' => Hash::make('a very long safe passphrase'), 'role' => User::ROLE_ADMIN, 'status' => User::STATUS_ACTIVE])->save();
        $user = User::query()->create(['email' => 'user@example.test', 'password' => Hash::make('a very long safe passphrase')]);
        app(UserStatusService::class)->disable($user);
        $this->assertSame(User::STATUS_DISABLED, $user->fresh()->status);
        $this->expectException(ValidationException::class);
        app(UserStatusService::class)->disable($admin);
    }

    public function test_disabling_user_invalidates_multiple_database_sessions(): void
    {
        $user = User::query()->create(['email' => 'sessions@example.test', 'password' => Hash::make('a very long safe passphrase')]);
        $first = $this->loginSession($user);
        $second = $this->loginSession($user);

        $this->assertGreaterThanOrEqual(2, \DB::table('sessions')->where('user_id', $user->id)->count());
        app(UserStatusService::class)->disable($user);
        $this->assertSame(0, \DB::table('sessions')->where('user_id', $user->id)->count());
        $this->app['auth']->forgetGuards();
        $firstResponse = $this->withCookie(config('session.cookie'), $first)->getJson('/api/v1/me');
        $this->assertContains($firstResponse->status(), [401, 403]);
        $this->app['auth']->forgetGuards();
        $secondResponse = $this->withCookie(config('session.cookie'), $second)->getJson('/api/v1/me');
        $this->assertContains($secondResponse->status(), [401, 403]);
    }

    public function test_expired_revoked_and_exhausted_invitations_are_rejected(): void
    {
        ['invitation' => $expired, 'token' => $expiredToken] = app(InvitationService::class)->create(null, 1);
        $expired->forceFill(['expires_at' => now()->subSecond()])->save();
        ['invitation' => $revoked, 'token' => $revokedToken] = app(InvitationService::class)->create(null, 1);
        app(InvitationService::class)->revoke($revoked->id);
        ['invitation' => $used, 'token' => $usedToken] = app(InvitationService::class)->create(null, 1);
        $used->forceFill(['uses' => 1, 'consumed_at' => now()])->save();

        foreach ([[$expiredToken, 'expired'], [$revokedToken, 'revoked'], [$usedToken, 'used']] as [$token, $email]) {
            try {
                app(InvitationService::class)->register($token, $email.'@example.test', 'a very long safe passphrase');
                $this->fail('The invitation must be rejected.');
            } catch (ValidationException) {
                $this->addToAssertionCount(1);
            }
        }
    }

    public function test_http_registration_rejects_missing_csrf_before_any_user_is_created(): void
    {
        ['token' => $token] = app(InvitationService::class)->create(null, 7);
        $payload = ['invitation_token' => $token, 'email' => 'web@example.test', 'password' => 'a very long safe passphrase', 'password_confirmation' => 'a very long safe passphrase', 'role' => 'admin', 'status' => 'DISABLED'];
        $this->withHeader('Origin', 'http://localhost')->postJson('/api/v1/auth/register', $payload)->assertStatus(419);
        ['csrf' => $csrf] = $this->csrfCookies();
        $this->assertNotEmpty($csrf);
        $this->assertDatabaseMissing('users', ['email' => 'web@example.test']);
    }

    public function test_login_errors_do_not_enumerate_and_disabled_account_is_rejected(): void
    {
        $user = User::query()->create(['email' => 'login@example.test', 'password' => Hash::make('a very long safe passphrase')]);
        foreach ([['missing@example.test', 'wrong'], ['login@example.test', 'wrong']] as [$email, $password]) {
            $this->postJson('/api/v1/auth/login', ['email' => $email, 'password' => $password])->assertUnprocessable()->assertJsonPath('errors.email.0', 'The provided credentials are incorrect.');
        }
        $user->forceFill(['status' => User::STATUS_DISABLED])->save();
        $this->postJson('/api/v1/auth/login', ['email' => 'login@example.test', 'password' => 'a very long safe passphrase'])->assertUnprocessable()->assertJsonPath('errors.email.0', 'This account is disabled.');
    }

    public function test_login_rate_limit_is_configurable_deterministic_and_isolated_by_email_and_ip(): void
    {
        config(['auth.login_rate_limit.attempts' => 2, 'auth.login_rate_limit.decay_seconds' => 60]);
        $this->clearLoginLimiter('127.0.0.1', 'limit@example.test');
        $this->clearLoginLimiter('127.0.0.1', 'isolated@example.test');

        $payload = ['email' => 'limit@example.test', 'password' => 'wrong password'];
        $this->postJson('/api/v1/auth/login', $payload)->assertUnprocessable();
        $this->postJson('/api/v1/auth/login', $payload)->assertUnprocessable();
        $this->postJson('/api/v1/auth/login', $payload)->assertTooManyRequests();

        $this->postJson('/api/v1/auth/login', ['email' => 'isolated@example.test', 'password' => 'wrong password'])->assertUnprocessable();
    }

    public function test_login_regenerates_session_and_controller_uses_stateful_session_boundary(): void
    {
        $user = User::query()->create(['email' => 'flow@example.test', 'password' => Hash::make('a very long safe passphrase')]);
        $before = $this->csrfCookies();
        $login = $this->withoutMiddleware(ValidateCsrfToken::class)->withHeader('Origin', 'http://localhost')->withHeader('Accept', 'application/json')->withCookie(config('session.cookie'), $before['session'])->postJson('/api/v1/auth/login', ['email' => ' FLOW@EXAMPLE.TEST ', 'password' => 'a very long safe passphrase'])->assertOk();
        $after = $login->getCookie(config('session.cookie'))?->getValue();
        $this->assertNotSame($before['session'], $after);
        $this->assertNotEmpty($after);
    }

    public function test_enable_cli_restores_authentication_without_creating_a_session(): void
    {
        $user = User::query()->create(['email' => 'enable@example.test', 'password' => Hash::make('a very long safe passphrase'), 'status' => User::STATUS_DISABLED]);
        $this->artisan('user:enable '.$user->id)->expectsOutput('User enabled.')->assertExitCode(0);
        $this->assertSame(User::STATUS_ACTIVE, $user->fresh()->status);
        $this->assertDatabaseCount('sessions', 0);
        $cookies = $this->csrfCookies();
        $this->withHeader('Origin', 'http://localhost')->withCookie(config('session.cookie'), $cookies['session'])->withHeader('X-CSRF-TOKEN', $cookies['session_token'])->postJson('/api/v1/auth/login', ['email' => $user->email, 'password' => 'a very long safe passphrase'])->assertOk();
    }

    public function test_test_only_ownership_fixture_hides_foreign_resources_and_admin_has_no_bypass(): void
    {
        $owner = User::query()->create(['email' => 'owner@example.test', 'password' => Hash::make('a very long safe passphrase')])->fresh();
        $foreign = User::query()->create(['email' => 'foreign@example.test', 'password' => Hash::make('a very long safe passphrase')])->fresh();
        $admin = new User;
        $admin->forceFill(['email' => 'other-admin@example.test', 'password' => Hash::make('a very long safe passphrase'), 'role' => User::ROLE_ADMIN, 'status' => User::STATUS_ACTIVE])->save();
        $id = (string) Str::ulid();
        \DB::table('test_private_resources')->insert(['id' => $id, 'owner_id' => $owner->id, 'value' => 'private']);

        $this->as($owner)->getJson('/api/v1/_test/private/'.$id)->assertOk();
        $this->as($owner)->putJson('/api/v1/_test/private/'.$id, ['value' => 'updated', 'owner_id' => $foreign->id, 'user_id' => $admin->id])->assertNoContent();
        $this->assertDatabaseHas('test_private_resources', ['id' => $id, 'owner_id' => $owner->id, 'value' => 'updated']);
        $this->as($foreign)->getJson('/api/v1/_test/private/'.$id)->assertNotFound();
        $this->as($foreign)->getJson('/api/v1/_test/private')->assertExactJson(['data' => []]);
        $this->as($admin)->getJson('/api/v1/_test/private/'.$id)->assertNotFound();
        $this->as($foreign)->putJson('/api/v1/_test/private/'.$id, ['value' => 'tampered', 'owner_id' => $owner->id, 'user_id' => $owner->id])->assertNotFound();
        $this->as($foreign)->deleteJson('/api/v1/_test/private/'.$id)->assertNotFound();
        $this->as($foreign)->postJson('/api/v1/_test/private', ['value' => 'foreign-owned', 'owner_id' => $owner->id, 'user_id' => $owner->id])->assertCreated()->assertJsonPath('data.owner_id', $foreign->id);
        $this->as($owner)->postJson('/api/v1/_test/private', ['value' => 'owner-owned', 'owner_id' => $foreign->id, 'user_id' => $admin->id])->assertCreated()->assertJsonPath('data.owner_id', $owner->id);
        $this->as($foreign)->getJson('/api/v1/_test/known-capability')->assertForbidden();
        $this->assertDatabaseHas('test_private_resources', ['id' => $id, 'owner_id' => $owner->id]);
    }

    public function test_audit_redacts_sensitive_metadata_and_refuses_mutation(): void
    {
        $actor = User::query()->create(['email' => 'actor@example.test', 'password' => Hash::make('a very long safe passphrase')]);
        $event = app(AuditLogger::class)->record('test.event', AuditEvent::ACTOR_USER, $actor, metadata: ['safe' => 'value', 'password' => 'nope', 'invitation_token' => 'nope', 'session_id' => 'nope']);
        $this->assertSame(['safe' => 'value'], $event->metadata);
        $this->assertSame($actor->id, $event->actor_user_id);
        $this->expectException(\LogicException::class);
        $event->forceFill(['event_type' => 'altered'])->save();
    }

    public function test_bootstrap_admin_is_one_time_and_never_echoes_password(): void
    {
        $password = 'a very long safe passphrase';
        $this->artisan('user:bootstrap-admin admin@example.test')
            ->expectsQuestion('Password (15-128 characters)', $password)
            ->doesntExpectOutputToContain($password)
            ->expectsOutput('First admin created.')
            ->assertExitCode(0);
        $this->assertDatabaseHas('audit_events', ['event_type' => 'user.bootstrap_admin', 'actor_type' => AuditEvent::ACTOR_OPERATOR]);
        $this->artisan('user:bootstrap-admin another@example.test')
            ->expectsQuestion('Password (15-128 characters)', $password)
            ->expectsOutput('An admin account already exists.')
            ->assertExitCode(1);
        $this->assertDatabaseCount('users', 1);
    }

    public function test_operator_invitation_command_shows_token_once_without_persisting_it(): void
    {
        $this->artisan('invitation:create --email=invitee@example.test --expires=7')
            ->expectsOutputToContain('Invitation ULID: ')
            ->expectsOutputToContain('Invitation URL (show once): /register#token=')
            ->assertExitCode(0);
        $invitation = Invitation::query()->sole();
        $this->assertSame('invitee@example.test', $invitation->target_email);
        $this->assertNotEmpty($invitation->token_hash);
        $this->assertArrayNotHasKey('token_hash', $invitation->toArray());
    }

    public function test_operator_can_capture_invitation_ulid_revoke_it_and_registration_is_rejected(): void
    {
        $this->assertSame(0, Artisan::call('invitation:create --expires=7'));
        $output = Artisan::output();
        preg_match('/Invitation ULID: ([0-9A-HJKMNP-TV-Za-hjkmnp-tv-z]{26})/', $output, $idMatches);
        preg_match('/\/register#token=([a-f0-9]{64})/', $output, $tokenMatches);
        $this->assertCount(2, $idMatches);
        $this->assertCount(2, $tokenMatches);

        $this->artisan('invitation:revoke '.$idMatches[1])->expectsOutput('Invitation revoked.')->assertExitCode(0);
        $this->expectException(ValidationException::class);
        app(InvitationService::class)->register($tokenMatches[1], 'revoked@example.test', 'a very long safe passphrase');
        $this->assertDatabaseMissing('users', ['email' => 'revoked@example.test']);
    }

    public function test_operator_can_revoke_an_invitation_and_a_rejected_rerevoke_changes_nothing(): void
    {
        ['invitation' => $invitation] = app(InvitationService::class)->create(null, 7);
        $this->artisan('invitation:revoke '.$invitation->id)->expectsOutput('Invitation revoked.')->assertExitCode(0);
        $this->assertNotNull($invitation->fresh()->revoked_at);
        $eventCount = AuditEvent::query()->count();
        $this->artisan('invitation:revoke '.$invitation->id)->expectsOutput('Invitation is already revoked.')->assertExitCode(1);
        $this->assertSame($eventCount, AuditEvent::query()->count());
    }

    private function as(User $user): static
    {
        $this->app['auth']->forgetGuards();

        return $this->actingAs($user, 'web');
    }

    private function loginSession(User $user): string
    {
        $this->clearLoginLimiter('127.0.0.1', $user->email);
        $this->clearLoginLimiter('::1', $user->email);
        $csrf = $this->csrfCookies();
        $response = $this->withoutMiddleware(ValidateCsrfToken::class)
            ->withHeader('Origin', 'http://localhost')
            ->withCookie(config('session.cookie'), $csrf['session'])
            ->postJson('/api/v1/auth/login', ['email' => $user->email, 'password' => 'a very long safe passphrase']);
        $response->assertOk();

        return (string) $response->getCookie(config('session.cookie'))?->getValue();
    }

    private function clearLoginLimiter(string $ip, string $email): void
    {
        RateLimiter::clear(md5('loginlogin:'.$ip.'|'.$email));
    }

    /** @return array{csrf: string, session: string, session_token: string} */
    private function csrfCookies(): array
    {
        $response = $this->withHeader('Origin', 'http://localhost')->get('/sanctum/csrf-cookie');

        return [
            'csrf' => $response->getCookie('XSRF-TOKEN')->getValue(),
            'session' => $response->getCookie(config('session.cookie'))->getValue(),
            'session_token' => $this->app['session']->token(),
        ];
    }
}
