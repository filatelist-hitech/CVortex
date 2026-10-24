<?php

namespace Tests\Unit\Settings;

use App\Settings\SettingDefinitionRegistry;
use PHPUnit\Framework\TestCase;

class SettingDefinitionRegistryTest extends TestCase
{
    private SettingDefinitionRegistry $registry;

    protected function setUp(): void
    {
        parent::setUp();
        $this->registry = new SettingDefinitionRegistry;
    }

    public function test_registry_contains_expected_core_settings(): void
    {
        $this->assertTrue($this->registry->has('general.app_name'));
        $this->assertTrue($this->registry->has('general.default_locale'));
        $this->assertTrue($this->registry->has('general.default_timezone'));
        $this->assertTrue($this->registry->has('auth.invitation_default_expires_days'));
        $this->assertTrue($this->registry->has('ai.providers.openai.api_key'));
        $this->assertTrue($this->registry->has('mail.smtp.password'));
    }

    public function test_secret_definitions_are_flagged_correctly(): void
    {
        $apiKey = $this->registry->get('ai.providers.openai.api_key');
        $this->assertNotNull($apiKey);
        $this->assertTrue($apiKey->isSecret);
        $this->assertSame('secret', $apiKey->type);

        $smtpPassword = $this->registry->get('mail.smtp.password');
        $this->assertNotNull($smtpPassword);
        $this->assertTrue($smtpPassword->isSecret);
    }

    public function test_for_group_returns_only_matching_definitions(): void
    {
        $general = $this->registry->forGroup('general');
        $this->assertNotEmpty($general);
        foreach ($general as $def) {
            $this->assertSame('general', $def->group);
        }
    }

    public function test_groups_list_is_unique_and_sorted(): void
    {
        $groups = $this->registry->groups();
        $this->assertContains('general', $groups);
        $this->assertContains('auth', $groups);
        $this->assertContains('ai_models', $groups);
        $this->assertContains('ai_providers', $groups);
        $this->assertContains('email', $groups);
        $this->assertContains('storage', $groups);
        $this->assertContains('limits', $groups);
    }
}
