<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;

class VacancyMatchDimension extends Model
{
    use HasUlids;

    protected $fillable = [
        'owner_id', 'vacancy_analysis_id', 'dimension', 'result', 'explanation', 'origin',
        'vacancy_requirement_ids',
    ];

    protected function casts(): array
    {
        return ['vacancy_requirement_ids' => 'array'];
    }
}
