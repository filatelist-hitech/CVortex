<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;

class Invitation extends Model
{
    use HasUlids;

    protected $keyType = 'string';

    public $incrementing = false;

    protected $guarded = [];

    protected $hidden = ['token_hash'];

    protected function casts(): array
    {
        return ['expires_at' => 'immutable_datetime', 'revoked_at' => 'immutable_datetime', 'consumed_at' => 'immutable_datetime'];
    }

    public function isUsable(): bool
    {
        return $this->revoked_at === null && now()->lt($this->expires_at) && $this->uses < $this->max_uses;
    }
}
