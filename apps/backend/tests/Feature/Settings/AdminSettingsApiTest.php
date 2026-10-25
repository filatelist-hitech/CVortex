<?php

namespace Tests\Feature\Settings;

use App\Models\AuditEvent;
use App\Models\User;
use App\Settings\SettingsResolver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminSettingsApiTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private User $normalUser;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::query()->create([
            'email' => 'admin_api@example.com',
            'password' => 'secret1234567890',
        ]);
        $this->admin->forceFill([
            'role' => User::ROLE_ADMIN,
            'status' => User::STATUS_ACTIVE,
        ])->save();

        $this->normalUser = User::query()->create([
            'email' => 'user_api@example.com',
            'password' => 'secret1234567890',
        ]);
        $this->normalUser->forceFill([
            'role' => User::ROLE_USER,
            'status' => User::STATUS_ACTIVE,
        ])->save();
    }

    public function test_unauthenticated_request_is_unauthorized(): void
    {
        $response = $this->getJson('/api/v1/admin/settings');
        $response->assertStatus(401);
    }

    public function test_non_admin_user_is_forbidden(): void
    {
        $this->actingAs($this->normalUser);

        $response = $this->getJson('/api/v1/admin/settings');
        $response->assertStatus(403);

        $patch = $this->patchJson('/api/v1/admin/settings', [
            'settings' => [['key' => 'general.app_name', 'value' => 'Hacked Name']],
        ]);
        $patch->assertStatus(403);

        $reset = $this->postJson('/api/v1/admin/settings/reset', [
            'key' => 'general.app_name',
        ]);
        $reset->assertStatus(403);

        $test = $this->postJson('/api/v1/admin/settings/test', [
            'target' => 'openai',
        ]);
        $test->assertStatus(403);

        $env = $this->getJson('/api/v1/admin/settings/environment');
        $env->assertStatus(403);
    }

    public function test_admin_can_list_settings_with_secrets_sanitized(): void
    {
        $this->actingAs($this->admin);

        $response = $this->getJson('/api/v1/admin/settings');
        $response->assertStatus(200);
        $response->assertJsonStructure([
            'data' => [
                '*' => [
                    'key',
                    'group',
                    'label',
                    'description',
                    'type',
                    'value',
                    'source',
                    'is_secret',
                    'is_configured',
                    'is_overridden',
                    'admin_editable',
                    'version',
                ],
            ],
            'meta' => [
                'total',
                'overrides_count',
            ],
        ]);

        /** @var list<array<string, mixed>> $items */
        $items = $response->json('data');
        $apiKey = collect($items)->first(fn (array $item) => $item['key'] === 'ai.providers.openai.api_key');
        $this->assertNotNull($apiKey);
        $this->assertTrue($apiKey['is_secret']);
        $this->assertNull($apiKey['value']);
    }

    public function test_admin_can_update_settings_and_audit_event_is_logged_safely(): void
    {
        $this->actingAs($this->admin);

        $response = $this->patchJson('/api/v1/admin/settings', [
            'settings' => [
                [
                    'key' => 'general.app_name',
                    'value' => 'CVortex Production',
                ],
                [
                    'key' => 'ai.providers.openai.api_key',
                    'value' => 'sk-new-admin-test-key-999',
                ],
            ],
        ]);

        $response->assertStatus(200);

        // Verify resolver sees effective values
        /** @var SettingsResolver $resolver */
        $resolver = app(SettingsResolver::class);
        $this->assertSame('CVortex Production', $resolver->get('general.app_name'));
        $this->assertSame('sk-new-admin-test-key-999', $resolver->get('ai.providers.openai.api_key'));

        // Verify audit events
        $appNameAudit = AuditEvent::query()
            ->where('event_type', 'setting.updated')
            ->where('metadata->key', 'general.app_name')
            ->first();
        $this->assertNotNull($appNameAudit);
        $this->assertSame($this->admin->id, $appNameAudit->actor_user_id);
        $appMetadata = is_array($appNameAudit->metadata) ? $appNameAudit->metadata : json_decode((string) $appNameAudit->metadata, true);
        $this->assertIsArray($appMetadata);
        $this->assertFalse($appMetadata['redacted'] ?? null);

        $secretAudit = AuditEvent::query()
            ->where('event_type', 'setting.updated')
            ->where('metadata->key', 'ai.providers.openai.api_key')
            ->first();
        $this->assertNotNull($secretAudit);
        $secretMetadata = is_array($secretAudit->metadata) ? $secretAudit->metadata : json_decode((string) $secretAudit->metadata, true);
        $this->assertIsArray($secretMetadata);
        $this->assertTrue($secretMetadata['redacted'] ?? null);
        $this->assertStringNotContainsString('sk-new-admin-test-key-999', json_encode($secretAudit->metadata));
    }

    public function test_admin_cannot_update_immutable_setting(): void
    {
        $this->actingAs($this->admin);

        $response = $this->patchJson('/api/v1/admin/settings', [
            'settings' => [
                [
                    'key' => 'storage.default_disk',
                    'value' => 's3',
                ],
            ],
        ]);

        $response->assertStatus(422);
        $response->assertJsonPath('error.code', 'SETTING_IMMUTABLE');
    }

    public function test_admin_can_reset_setting_to_default(): void
    {
        $this->actingAs($this->admin);

        $this->patchJson('/api/v1/admin/settings', [
            'settings' => [
                ['key' => 'general.app_name', 'value' => 'Temporary Name'],
            ],
        ]);

        $response = $this->postJson('/api/v1/admin/settings/reset', [
            'key' => 'general.app_name',
        ]);

        $response->assertStatus(200);
        $this->assertFalse($response->json('data.is_overridden'));
        $this->assertNotSame('Temporary Name', $response->json('data.value'));

        $resetAudit = AuditEvent::query()
            ->where('event_type', 'setting.reset')
            ->where('metadata->key', 'general.app_name')
            ->first();
        $this->assertNotNull($resetAudit);
    }

    public function test_admin_can_read_environment_diagnostics(): void
    {
        $this->actingAs($this->admin);

        $response = $this->getJson('/api/v1/admin/settings/environment');

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'data' => [
                'app_name',
                'app_env',
                'app_debug',
                'php_version',
                'laravel_version',
                'database' => [
                    'default',
                    'status',
                ],
                'redis' => [
                    'status',
                ],
                'storage' => [
                    'default',
                ],
                'encryption' => [
                    'configured',
                ],
            ],
        ]);

        // Never expose APP_KEY
        $content = $response->getContent();
        $this->assertStringNotContainsString(config('app.key'), (string) $content);
    }

    public function test_admin_patch_with_stale_expected_version_returns_409_concurrency_conflict(): void
    {
        $this->actingAs($this->admin);

        // First update creates override with version 2
        $first = $this->patchJson('/api/v1/admin/settings', [
            'settings' => [
                ['key' => 'general.app_name', 'value' => 'Version 2 Name', 'expected_version' => 1],
            ],
        ]);
        $first->assertStatus(200);

        // Second update with stale expected_version 1 triggers HTTP 409
        $conflict = $this->patchJson('/api/v1/admin/settings', [
            'settings' => [
                ['key' => 'general.app_name', 'value' => 'Conflict Attempt', 'expected_version' => 1],
            ],
        ]);

        $conflict->assertStatus(409);
        $conflict->assertJsonPath('error.code', 'SETTING_CONCURRENCY_CONFLICT');
    }

    public function test_admin_patch_with_invalid_value_returns_422_validation_failed(): void
    {
        $this->actingAs($this->admin);

        // auth.invitation_default_expires_days must be between 1 and 30
        $response = $this->patchJson('/api/v1/admin/settings', [
            'settings' => [
                ['key' => 'auth.invitation_default_expires_days', 'value' => 999],
            ],
        ]);

        $response->assertStatus(422);
        $response->assertJsonPath('error.code', 'VALIDATION_FAILED');
    }

    public function test_admin_reset_nonexistent_setting_returns_404_not_found(): void
    {
        $this->actingAs($this->admin);

        $response = $this->postJson('/api/v1/admin/settings/reset', [
            'key' => 'nonexistent.unknown.key',
        ]);

        $response->assertStatus(404);
        $response->assertJsonPath('error.code', 'SETTING_NOT_FOUND');
    }

    public function test_admin_can_execute_storage_connection_test(): void
    {
        $this->actingAs($this->admin);

        $response = $this->postJson('/api/v1/admin/settings/test', [
            'target' => 'storage',
        ]);

        $response->assertStatus(200);
        $response->assertJsonPath('data.success', true);
        $this->assertIsInt($response->json('data.latency_ms'));

        $testAudit = AuditEvent::query()
            ->where('event_type', 'setting.test_executed')
            ->where('metadata->target', 'storage')
            ->first();
        $this->assertNotNull($testAudit);
    }

    public function test_admin_patch_nonexistent_setting_returns_404_not_found(): void
    {
        $this->actingAs($this->admin);

        $response = $this->patchJson('/api/v1/admin/settings', [
            'settings' => [
                ['key' => 'nonexistent.unknown.key', 'value' => 'some-value'],
            ],
        ]);

        $response->assertStatus(404);
        $response->assertJsonPath('error.code', 'SETTING_NOT_FOUND');
    }

    public function test_admin_patch_initial_override_with_stale_expected_version_returns_409(): void
    {
        $this->actingAs($this->admin);

        // Attempting to create override when no DB record exists, but sending expected_version 99 instead of 1
        $response = $this->patchJson('/api/v1/admin/settings', [
            'settings' => [
                ['key' => 'general.app_name', 'value' => 'Stale Init Attempt', 'expected_version' => 99],
            ],
        ]);

        $response->assertStatus(409);
        $response->assertJsonPath('error.code', 'SETTING_CONCURRENCY_CONFLICT');
    }

    public function test_admin_patch_batch_is_atomic_and_rolls_back_on_failure(): void
    {
        $this->actingAs($this->admin);

        /** @var SettingsResolver $resolver */
        $resolver = app(SettingsResolver::class);
        $originalName = $resolver->get('general.app_name');

        // Batch contains 1 valid setting and 1 invalid setting
        $response = $this->patchJson('/api/v1/admin/settings', [
            'settings' => [
                ['key' => 'general.app_name', 'value' => 'Rolled Back Name'],
                ['key' => 'auth.invitation_default_expires_days', 'value' => 999], // fails validation
            ],
        ]);

        $response->assertStatus(422);
        $response->assertJsonPath('error.code', 'VALIDATION_FAILED');

        // Verify general.app_name was rolled back and not persisted
        $this->assertSame($originalName, $resolver->get('general.app_name'));
    }

    public function test_reset_setting_then_recreate_override_preserves_concurrency_semantics(): void
    {
        $this->actingAs($this->admin);

        // 1. Initial creation
        $res1 = $this->patchJson('/api/v1/admin/settings', [
            'settings' => [
                ['key' => 'general.app_name', 'value' => 'Step 1 Name', 'expected_version' => 1],
            ],
        ]);
        $res1->assertStatus(200);

        // 2. Reset setting
        $res2 = $this->postJson('/api/v1/admin/settings/reset', [
            'key' => 'general.app_name',
        ]);
        $res2->assertStatus(200);
        $this->assertSame(1, $res2->json('data.version'));

        // 3. Stale save with old version 2 is rejected with 409
        $conflict = $this->patchJson('/api/v1/admin/settings', [
            'settings' => [
                ['key' => 'general.app_name', 'value' => 'Stale Post-Reset', 'expected_version' => 2],
            ],
        ]);
        $conflict->assertStatus(409);
        $conflict->assertJsonPath('error.code', 'SETTING_CONCURRENCY_CONFLICT');

        // 4. Fresh save with version 1 succeeds
        $res3 = $this->patchJson('/api/v1/admin/settings', [
            'settings' => [
                ['key' => 'general.app_name', 'value' => 'Valid Post-Reset', 'expected_version' => 1],
            ],
        ]);
        $res3->assertStatus(200);
        $this->assertSame('Valid Post-Reset', app(SettingsResolver::class)->get('general.app_name'));
    }

    public function test_admin_test_with_unsupported_target_returns_422_validation_failed(): void
    {
        $this->actingAs($this->admin);

        $response = $this->postJson('/api/v1/admin/settings/test', [
            'target' => 'unsupported_provider',
        ]);

        $response->assertStatus(422);
    }
}
