<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;

class VacancyRequirement extends Model
{
    use HasUlids;

    public const DIMENSIONS = ['TECHNICAL', 'EXPERIENCE', 'DOMAIN', 'LANGUAGE', 'LOCATION', 'WORK_FORMAT', 'SALARY'];

    protected $fillable = [
        'owner_id', 'vacancy_snapshot_id', 'dimension', 'importance', 'label', 'normalized_value',
        'source_excerpt', 'confidence', 'extracted_by', 'candidate_hash',
    ];

    protected function casts(): array
    {
        return ['confidence' => 'float'];
    }
}
