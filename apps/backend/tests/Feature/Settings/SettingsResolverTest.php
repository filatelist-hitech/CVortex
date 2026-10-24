<?php

namespace Tests\Feature\Settings;

use App\Models\SystemSetting;
use App\Models\User;
use App\Settings\Exceptions\SettingConcurrencyException;
use App\Settings\Exceptions\SettingImmutableException;
use App\Settings\Exceptions\SettingNotFoundException;
use App\Settings\SettingsResolver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Crypt;
use Tests\TestCase;

class SettingsResolverTest extends TestCase
{
    use RefreshDatabase;

    private SettingsResolver $resolver;

    protected function setUp(): void
    {
        parent::setUp();
        $this->resolver = app(SettingsResolver::class);
    }

    public function test_resolves_application_default_when_no_env_and_no_db_override(): void
    {
        $setting = $this->resolver->resolve('auth.invitation_default_expires_days');

        $this->assertSame(7, $setting->value);
        $this->assertSame('default', $setting->source);
        $this->assertFalse($setting->isOverridden);
        $this->assertTrue($setting->isConfigured);
    }

    public function test_resolves_env_fallback_when_present(): void
    {
        config(['app.name' => 'Custom CVortex Env']);

        $setting = $this->resolver->resolve('general.app_name');

        $this->assertSame('Custom CVortex Env', $setting->value);
        $this->assertSame('environment', $setting->source);
        $this->assertFalse($setting->isOverridden);
    }

    private function createAdmin(string $email): User
    {
        $user = User::query()->create([
            'email' => $email,
            'password' => 'secret1234567890',
        ]);
        $user->forceFill(['role' => User::ROLE_ADMIN, 'status' => User::STATUS_ACTIVE])->save();

        return $user;
    }

    public function test_db_override_takes_precedence_over_env_and_default(): void
    {
        config(['app.name' => 'Custom CVortex Env']);

        $admin = $this->createAdmin('admin_settings_test@example.com');

        $this->resolver->update('general.app_name', 'DB Overridden Name', $admin);

        $setting = $this->resolver->resolve('general.app_name');

        $this->assertSame('DB Overridden Name', $setting->value);
        $this->assertSame('database', $setting->source);
        $this->assertTrue($setting->isOverridden);
        $this->assertSame('admin_settings_test@example.com', $setting->updatedBy);
    }

    public function test_reset_removes_db_override_and_falls_back(): void
    {
        $admin = $this->createAdmin('admin_reset_test@example.com');

        $this->resolver->update('general.app_name', 'Temporary Name', $admin);
        $this->assertTrue($this->resolver->resolve('general.app_name')->isOverridden);

        $this->resolver->reset('general.app_name', $admin);

        $reset = $this->resolver->resolve('general.app_name');
        $this->assertFalse($reset->isOverridden);
        $this->assertNotSame('database', $reset->source);
    }

    public function test_secret_is_encrypted_at_rest_and_never_exposed_in_array(): void
    {
        $admin = $this->createAdmin('admin_secret_test@example.com');

        $rawSecret = 'sk-test-super-secret-key-12345';
        $this->resolver->update('ai.providers.openai.api_key', $rawSecret, $admin);

        // Check DB model
        $row = SystemSetting::query()->where('key', 'ai.providers.openai.api_key')->first();
        $this->assertNotNull($row);
        $this->assertNull($row->value);
        $this->assertNotNull($row->encrypted_value);
        $this->assertNotSame($rawSecret, $row->getAttributes()['encrypted_value']);
        $this->assertSame($rawSecret, Crypt::decryptString($row->getAttributes()['encrypted_value']));

        // Check internal retrieval
        $this->assertSame($rawSecret, $this->resolver->get('ai.providers.openai.api_key'));

        // Check API/DTO representation
        $effective = $this->resolver->resolve('ai.providers.openai.api_key');
        $array = $effective->toArray();

        $this->assertTrue($array['is_secret']);
        $this->assertTrue($array['is_configured']);
        $this->assertNull($array['value']);
        $this->assertNull($array['default']);
    }

    public function test_concurrency_conflict_throws_exception(): void
    {
        $admin = $this->createAdmin('admin_concurrency@example.com');

        $this->resolver->update('general.app_name', 'First Save', $admin);
        $first = $this->resolver->resolve('general.app_name');

        // Admin 2 saves in the meantime, bumping version
        $this->resolver->update('general.app_name', 'Second Save', $admin, $first->version);

        // Admin 1 attempts to save with stale version
        $this->expectException(SettingConcurrencyException::class);
        $this->resolver->update('general.app_name', 'Stale Save', $admin, $first->version);
    }

    public function test_immutable_setting_cannot_be_updated_or_reset(): void
    {
        $admin = $this->createAdmin('admin_immutable@example.com');

        $this->expectException(SettingImmutableException::class);
        $this->resolver->update('storage.default_disk', 's3', $admin);
    }

    public function test_unknown_setting_throws_not_found(): void
    {
        $this->expectException(SettingNotFoundException::class);
        $this->resolver->resolve('unknown.setting.key');
    }
}
