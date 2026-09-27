<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Passport\Contracts\OAuthenticatable;
use Laravel\Passport\HasApiTokens;

class User extends Authenticatable implements OAuthenticatable
{
    use HasApiTokens, HasUlids, Notifiable;

    public const ROLE_ADMIN = 'admin';

    public const ROLE_USER = 'user';

    public const STATUS_ACTIVE = 'ACTIVE';

    public const STATUS_DISABLED = 'DISABLED';

    protected $keyType = 'string';

    public $incrementing = false;

    protected $fillable = ['email', 'password'];

    protected $hidden = ['password', 'remember_token'];

    protected function casts(): array
    {
        return ['password' => 'hashed', 'auth_generation' => 'integer'];
    }

    public function isActive(): bool
    {
        return $this->status === self::STATUS_ACTIVE;
    }
}
