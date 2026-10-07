<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;

/**
 * @property string $client_id
 * @property int $oauth_generation
 * @property string|null $subject
 * @property string|null $access_token
 * @property string|null $refresh_token
 * @property string $status
 * @property list<string> $scopes
 * @property CarbonImmutable|null $expires_at
 * @property CarbonImmutable|null $earliest_refresh_at
 */
class ChatGptConnection extends Model
{
    use HasUlids;

    protected $table = 'chatgpt_connections';

    protected $guarded = ['id'];

    protected $hidden = ['access_token', 'refresh_token', 'id_token', 'subject'];

    protected function casts(): array
    {
        return [
            'access_token' => 'encrypted', 'refresh_token' => 'encrypted', 'id_token' => 'encrypted',
            'oauth_generation' => 'integer', 'scopes' => 'array', 'expires_at' => 'immutable_datetime',
            'earliest_refresh_at' => 'immutable_datetime',
        ];
    }
}
