<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;

class VacancyAnalysis extends Model
{
    use HasUlids;

    protected $fillable = [
        'owner_id', 'vacancy_id', 'vacancy_snapshot_id', 'career_signature', 'recommendation',
        'key_reasons', 'material_gaps', 'uncertainties', 'analysis_version',
    ];

    protected function casts(): array
    {
        return ['key_reasons' => 'array', 'material_gaps' => 'array', 'uncertainties' => 'array'];
    }
}
