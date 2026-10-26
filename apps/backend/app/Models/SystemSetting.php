<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property string $id
 * @property string $key
 * @property mixed $value
 * @property string|null $encrypted_value
 * @property string|null $updated_by
 * @property int $version
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read User|null $updatedByUser
 */
class SystemSetting extends Model
{
    use HasUlids;

    protected $table = 'system_settings';

    protected $keyType = 'string';

    public $incrementing = false;

    protected $guarded = ['id'];

    protected $hidden = [
        'encrypted_value',
    ];

    protected function casts(): array
    {
        return [
            'encrypted_value' => 'encrypted',
            'version' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function updatedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    public function getDecodedValue(): mixed
    {
        if ($this->encrypted_value !== null) {
            return $this->encrypted_value;
        }

        if ($this->attributes['value'] === null) {
            return null;
        }

        return json_decode((string) $this->attributes['value'], true);
    }

    public function setDecodedValue(mixed $value, bool $isSecret = false): self
    {
        if ($isSecret) {
            $this->encrypted_value = $value !== null ? (string) $value : null;
            $this->attributes['value'] = null;
        } else {
            $this->encrypted_value = null;
            $this->attributes['value'] = $value !== null ? json_encode($value, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) : null;
        }

        return $this;
    }
}
