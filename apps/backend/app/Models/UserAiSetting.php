<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;

/**
 * @property string $id
 * @property string $owner_id
 * @property string|null $selected_provider
 * @property string|null $gemini_api_key
 * @property string|null $gemini_model
 * @property string|null $openai_api_key
 * @property string|null $openai_model
 */
class UserAiSetting extends Model
{
    use HasUlids;

    protected $table = 'user_ai_settings';

    protected $fillable = [
        'owner_id',
        'selected_provider',
        'gemini_api_key',
        'gemini_model',
        'openai_api_key',
        'openai_model',
    ];

    protected $hidden = [
        'gemini_api_key',
        'openai_api_key',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'gemini_api_key' => 'encrypted',
            'openai_api_key' => 'encrypted',
        ];
    }
}
