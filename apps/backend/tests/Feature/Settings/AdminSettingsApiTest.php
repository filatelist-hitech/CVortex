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
}
