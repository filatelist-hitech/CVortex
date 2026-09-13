<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;

/** @extends Builder<AuditEvent> */
class AuditEventQueryBuilder extends Builder
{
    private function rejectMutation(): never
    {
        throw new \LogicException('Audit events are append-only.');
    }

    /** @param array<string, mixed> $values
     *  @param array<string, mixed> $options */
    public function update(array $values, array $options = []): int
    {
        $this->rejectMutation();
    }

    public function delete(): int
    {
        $this->rejectMutation();
    }

    public function truncate(): void
    {
        $this->rejectMutation();
    }

    /** @param array<int, array<string, mixed>>|array<string, mixed> $values
     *  @param array<int, string>|string $uniqueBy
     *  @param array<int, string>|null $update */
    public function upsert(array $values, $uniqueBy, $update = null): int
    {
        $this->rejectMutation();
    }

    /** @param array<string, mixed> $attributes
     *  @param array<string, mixed>|callable $values */
    public function updateOrInsert(array $attributes, array|callable $values = []): bool
    {
        $this->rejectMutation();
    }

    public function increment($column, $amount = 1, array $extra = []): int
    {
        $this->rejectMutation();
    }

    public function decrement($column, $amount = 1, array $extra = []): int
    {
        $this->rejectMutation();
    }

    public function incrementEach(array $columns, array $extra = []): int
    {
        $this->rejectMutation();
    }

    public function decrementEach(array $columns, array $extra = []): int
    {
        $this->rejectMutation();
    }

    /** @param array<int, string>|string|null $column */
    public function touch($column = null): int|false
    {
        $this->rejectMutation();
    }
}
