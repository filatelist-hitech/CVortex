<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;

/** @extends Builder<AuditEvent> */
class AuditEventQueryBuilder extends Builder
{
    /** @param array<string, mixed> $values
     *  @param array<string, mixed> $options */
    public function update(array $values, array $options = []): int
    {
        throw new \LogicException('Audit events are append-only.');
    }

    public function delete(): int
    {
        throw new \LogicException('Audit events are append-only.');
    }
}
