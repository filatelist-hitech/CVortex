<?php

namespace App\Settings;

class EffectiveSetting
{
    public function __construct(
        public readonly SettingDefinition $definition,
        public readonly mixed $value,
        public readonly string $source,
        public readonly bool $isOverridden,
        public readonly bool $isConfigured,
        public readonly int $version = 1,
        public readonly ?string $updatedAt = null,
        public readonly ?string $updatedBy = null,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'key' => $this->definition->key,
            'group' => $this->definition->group,
            'subgroup' => $this->definition->subgroup,
            'label' => $this->definition->label,
            'description' => $this->definition->description,
            'type' => $this->definition->type,
            'options' => $this->definition->options,
            'default' => $this->definition->isSecret ? null : $this->definition->default,
            'value' => $this->definition->isSecret ? null : $this->value,
            'source' => $this->source,
            'is_secret' => $this->definition->isSecret,
            'is_configured' => $this->isConfigured,
            'is_overridden' => $this->isOverridden,
            'admin_editable' => $this->definition->adminEditable,
            'runtime_mutable' => $this->definition->runtimeMutable,
            'requires_restart' => $this->definition->requiresRestart,
            'env_locked' => $this->definition->envLocked || $this->source === 'environment_locked',
            'env_fallback_key' => $this->definition->envFallbackKey,
            'dangerous' => $this->definition->dangerous,
            'testable' => $this->definition->testable,
            'sort_order' => $this->definition->sortOrder,
            'version' => $this->version,
            'updated_at' => $this->updatedAt,
            'updated_by' => $this->updatedBy,
        ];
    }
}
