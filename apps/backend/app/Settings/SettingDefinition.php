<?php

namespace App\Settings;

class SettingDefinition
{
    /**
     * @param  list<string|int|array{value: string|int, label: string}>|null  $options
     * @param  list<mixed>  $validationRules
     */
    public function __construct(
        public readonly string $key,
        public readonly string $group,
        public readonly string $label,
        public readonly string $description,
        public readonly string $type,
        public readonly mixed $default,
        public readonly ?string $subgroup = null,
        public readonly ?array $options = null,
        public readonly array $validationRules = [],
        public readonly bool $isSecret = false,
        public readonly bool $adminEditable = true,
        public readonly bool $runtimeMutable = true,
        public readonly bool $requiresRestart = false,
        public readonly ?string $envFallbackKey = null,
        public readonly bool $envLocked = false,
        public readonly bool $dangerous = false,
        public readonly bool $testable = false,
        public readonly ?string $testTarget = null,
        public readonly int $sortOrder = 100,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'key' => $this->key,
            'group' => $this->group,
            'subgroup' => $this->subgroup,
            'label' => $this->label,
            'description' => $this->description,
            'type' => $this->type,
            'default' => $this->isSecret ? null : $this->default,
            'options' => $this->options,
            'is_secret' => $this->isSecret,
            'admin_editable' => $this->adminEditable,
            'runtime_mutable' => $this->runtimeMutable,
            'requires_restart' => $this->requiresRestart,
            'env_fallback_key' => $this->envFallbackKey,
            'env_locked' => $this->envLocked,
            'dangerous' => $this->dangerous,
            'testable' => $this->testable,
            'test_target' => $this->testTarget,
            'sort_order' => $this->sortOrder,
        ];
    }
}
