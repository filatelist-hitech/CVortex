<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;

class ApplicationDraftItem extends Model
{
    use HasUlids;

    public const KIND_RECOMMENDATION = 'RESUME_RECOMMENDATION';

    public const KIND_COVER = 'COVER_DRAFT';

    protected $guarded = ['*'];

    protected function casts(): array
    {
        return ['validated_at' => 'datetime'];
    }
}
