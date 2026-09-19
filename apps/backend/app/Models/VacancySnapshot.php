<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;

class VacancySnapshot extends Model
{
    use HasUlids;

    protected $fillable = [
        'owner_id', 'vacancy_id', 'version', 'raw_text', 'source_url', 'content_hash', 'imported_at',
    ];

    protected $hidden = ['content_hash'];

    protected function casts(): array
    {
        return ['imported_at' => 'datetime'];
    }
}
