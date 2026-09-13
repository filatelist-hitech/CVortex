<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Query\Builder as QueryBuilder;

class AuditEvent extends Model
{
    use HasUlids;

    public const ACTOR_USER = 'USER';

    public const ACTOR_OPERATOR = 'OPERATOR';

    public const ACTOR_SYSTEM = 'SYSTEM';

    protected $keyType = 'string';

    public $incrementing = false;

    public $timestamps = false;

    protected $guarded = [];

    protected static function booted(): void
    {
        static::updating(static fn () => throw new \LogicException('Audit events are append-only.'));
        static::deleting(static fn () => throw new \LogicException('Audit events are append-only.'));
    }

    /** @param QueryBuilder $query */
    public function newEloquentBuilder($query): AuditEventQueryBuilder
    {
        return new AuditEventQueryBuilder($query);
    }

    protected function casts(): array
    {
        return ['metadata' => 'array', 'created_at' => 'immutable_datetime'];
    }
}
