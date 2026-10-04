<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;

/**
 * @property string $dimension
 * @property string $importance
 * @property string $label
 * @property string|null $normalized_value
 * @property string $source_excerpt
 * @property float $confidence
 */
class VacancyAnalysisDraftRequirement extends Model
{
    use HasUlids;

    protected $table = 'vacancy_analysis_draft_requirements';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['confidence' => 'float'];
    }
}
