<?php

namespace App\Settings;

use App\Models\AuditEvent;
use App\Models\SystemSetting;
use App\Models\User;
use App\Services\AuditLogger;
use App\Settings\Exceptions\SettingImmutableException;
use App\Settings\Exceptions\SettingNotFoundException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Validator;

class SettingsResolver
{
    private const CACHE_KEY = 'cvortex:system_settings:overrides';

    /** @var Collection<string, SystemSetting>|null */
    private ?Collection $memoizedOverrides = null;

    public function __construct(
        private readonly SettingDefinitionRegistry $registry,
        private readonly SettingsRepository $repository,
        private readonly AuditLogger $audit,
    ) {}

    public function get(string $key, mixed $default = null): mixed
    {
        $definition = $this->registry->get($key);
        if ($definition === null) {
            return $default;
        }

        return $this->resolve($key)->value;
    }

    public function resolve(string $key): EffectiveSetting
    {
        $definition = $this->registry->get($key);
        if ($definition === null) {
            throw new SettingNotFoundException($key);
        }

        $overrides = $this->getOverrides();
        /** @var SystemSetting|null $override */
        $override = $overrides->get($key);

        // 1. Hard environment lock
        if ($definition->envLocked) {
            $envVal = $this->resolveEnvFallback($definition);
            $val = $envVal !== null ? $this->castValue($envVal, $definition) : $definition->default;

            return new EffectiveSetting(
                definition: $definition,
                value: $val,
                source: 'environment_locked',
                isOverridden: false,
                isConfigured: $val !== null,
            );
        }

        // 2. Database runtime override
        if ($override !== null) {
            $val = $override->getDecodedValue();
            $isConfigured = $definition->isSecret ? filled($override->encrypted_value) : ($val !== null);
            $user = $override->updatedByUser;

            return new EffectiveSetting(
                definition: $definition,
                value: $val,
                source: 'database',
                isOverridden: true,
                isConfigured: $isConfigured,
                version: $override->version,
                updatedAt: $override->updated_at?->toIso8601String(),
                updatedBy: $user !== null ? $user->email : $override->updated_by,
            );
        }

        // 3. Environment fallback
        $envVal = $this->resolveEnvFallback($definition);
        if ($envVal !== null) {
            $val = $this->castValue($envVal, $definition);
            $isConfigured = $definition->isSecret ? filled($val) : ($val !== null);

            return new EffectiveSetting(
                definition: $definition,
                value: $val,
                source: 'environment',
                isOverridden: false,
                isConfigured: $isConfigured,
            );
        }

        // 4. Application default
        $isConfigured = $definition->isSecret ? false : ($definition->default !== null);

        return new EffectiveSetting(
            definition: $definition,
            value: $definition->default,
            source: 'default',
            isOverridden: false,
            isConfigured: $isConfigured,
        );
    }

    /**
     * @return list<EffectiveSetting>
     */
    public function resolveAll(): array
    {
        $results = [];
        foreach ($this->registry->all() as $key => $definition) {
            $results[] = $this->resolve($key);
        }

        usort($results, fn (EffectiveSetting $a, EffectiveSetting $b) => $a->definition->sortOrder <=> $b->definition->sortOrder);

        return $results;
    }

    public function update(string $key, mixed $value, ?User $actor = null, ?int $expectedVersion = null): EffectiveSetting
    {
        $definition = $this->registry->get($key);
        if ($definition === null) {
            throw new SettingNotFoundException($key);
        }

        if (! $definition->adminEditable || $definition->envLocked) {
            throw new SettingImmutableException($key);
        }

        // Validate
        if (! empty($definition->validationRules)) {
            Validator::make(['value' => $value], ['value' => $definition->validationRules])->validate();
        }

        $oldEffective = $this->resolve($key);
        $castVal = $value !== null ? $this->castValue($value, $definition) : null;

        $setting = $this->repository->save(
            key: $key,
            value: $castVal,
            isSecret: $definition->isSecret,
            userId: $actor?->id,
            expectedVersion: $expectedVersion,
        );

        $this->invalidateCache();

        $auditMetadata = [
            'key' => $key,
            'source' => 'database',
            'old_source' => $oldEffective->source,
            'redacted' => $definition->isSecret,
        ];

        $this->audit->record(
            eventType: 'setting.updated',
            actorType: $actor !== null ? AuditEvent::ACTOR_USER : AuditEvent::ACTOR_SYSTEM,
            actorUser: $actor,
            subjectType: SystemSetting::class,
            subjectId: $setting->id,
            metadata: $auditMetadata,
        );

        return $this->resolve($key);
    }

    public function reset(string $key, ?User $actor = null): EffectiveSetting
    {
        $definition = $this->registry->get($key);
        if ($definition === null) {
            throw new SettingNotFoundException($key);
        }

        if (! $definition->adminEditable || $definition->envLocked) {
            throw new SettingImmutableException($key);
        }

        $oldEffective = $this->resolve($key);
        $this->repository->delete($key);
        $this->invalidateCache();

        $newEffective = $this->resolve($key);

        $this->audit->record(
            eventType: 'setting.reset',
            actorType: $actor !== null ? AuditEvent::ACTOR_USER : AuditEvent::ACTOR_SYSTEM,
            actorUser: $actor,
            subjectType: SystemSetting::class,
            subjectId: null,
            metadata: [
                'key' => $key,
                'old_source' => $oldEffective->source,
                'new_source' => $newEffective->source,
                'redacted' => $definition->isSecret,
            ],
        );

        return $newEffective;
    }

    public function invalidateCache(): void
    {
        $this->memoizedOverrides = null;
        Cache::forget(self::CACHE_KEY);
    }

    /**
     * @return Collection<string, SystemSetting>
     */
    private function getOverrides(): Collection
    {
        if ($this->memoizedOverrides !== null) {
            return $this->memoizedOverrides;
        }

        /** @var mixed $cached */
        $cached = Cache::remember(self::CACHE_KEY, 3600, fn () => $this->repository->all());
        if (! ($cached instanceof Collection)) {
            $cached = $this->repository->all();
            Cache::put(self::CACHE_KEY, $cached, 3600);
        }
        /** @var Collection<string, SystemSetting> $overrides */
        $overrides = $cached;
        $this->memoizedOverrides = $overrides;

        return $overrides;
    }

    private function resolveEnvFallback(SettingDefinition $definition): mixed
    {
        if ($definition->envFallbackKey === null) {
            return null;
        }

        // Config mapping check first (allows runtime and test configuration)
        $configMap = [
            'APP_NAME' => 'app.name',
            'APP_LOCALE' => 'app.locale',
            'APP_TIMEZONE' => 'app.timezone',
            'AUTH_LOGIN_RATE_LIMIT_ATTEMPTS' => 'auth.login_rate_limit.attempts',
            'AUTH_LOGIN_RATE_LIMIT_DECAY_SECONDS' => 'auth.login_rate_limit.decay_seconds',
            'OPENAI_CAREER_EXTRACTION_MODEL' => 'ai.model_policies.low_cost_structured_extraction.model',
            'OPENAI_APPLICATION_DRAFT_MODEL' => 'ai.model_policies.standard_semantic_generation.model',
            'OPENAI_CAREER_INPUT_COST_MICROS_PER_MILLION_TOKENS' => 'ai.model_policies.low_cost_structured_extraction.input_cost_micros_per_million_tokens',
            'OPENAI_CAREER_OUTPUT_COST_MICROS_PER_MILLION_TOKENS' => 'ai.model_policies.low_cost_structured_extraction.output_cost_micros_per_million_tokens',
            'OPENAI_APPLICATION_INPUT_COST_MICROS_PER_MILLION_TOKENS' => 'ai.model_policies.standard_semantic_generation.input_cost_micros_per_million_tokens',
            'OPENAI_APPLICATION_OUTPUT_COST_MICROS_PER_MILLION_TOKENS' => 'ai.model_policies.standard_semantic_generation.output_cost_micros_per_million_tokens',
            'OPENAI_TIMEOUT_SECONDS' => 'ai.providers.openai.timeout_seconds',
            'OPENAI_BASE_URL' => 'ai.providers.openai.base_url',
            'OPENAI_API_KEY' => 'ai.providers.openai.api_key',
            'CHATGPT_PLAN_ENABLED' => 'chatgpt.enabled',
            'MCP_ENABLED' => 'mcp.enabled',
            'MAIL_MAILER' => 'mail.default',
            'MAIL_HOST' => 'mail.mailers.smtp.host',
            'MAIL_PORT' => 'mail.mailers.smtp.port',
            'MAIL_USERNAME' => 'mail.mailers.smtp.username',
            'MAIL_PASSWORD' => 'mail.mailers.smtp.password',
            'MAIL_FROM_ADDRESS' => 'mail.from.address',
            'MAIL_FROM_NAME' => 'mail.from.name',
            'FILESYSTEM_DISK' => 'filesystems.default',
            'DIAGNOSTIC_OCCURRENCE_DAYS' => 'diagnostics.occurrence_days',
            'DIAGNOSTIC_CLOSED_DAYS' => 'diagnostics.closed_incident_days',
        ];

        if (isset($configMap[$definition->envFallbackKey])) {
            $configVal = config($configMap[$definition->envFallbackKey]);
            if ($configVal !== null && $configVal !== '') {
                return $configVal;
            }
        }

        return null;
    }

    private function castValue(mixed $value, SettingDefinition $definition): mixed
    {
        if ($value === null) {
            return null;
        }

        return match ($definition->type) {
            'integer' => (int) $value,
            'boolean' => filter_var($value, FILTER_VALIDATE_BOOLEAN),
            default => (string) $value,
        };
    }
}
