<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;

/**
 * @property string $vacancy_id
 * @property string $vacancy_snapshot_id
 * @property string $career_signature
 * @property string $payload_hash
 * @property string $status
 * @property string $provider
 * @property string|null $model
 * @property string|null $message_id
 * @property string|null $run_id
 * @property string|null $approved_analysis_id
 * @property list<array{requirement_index: int, career_fact_ids: list<string>}> $proposed_matches
 * @property list<string> $gaps
 * @property list<string> $risks
 * @property list<string> $questions
 * @property list<string> $recommendations
 */
class VacancyAnalysisDraft extends Model
{
    use HasUlids;

    protected $table = 'vacancy_analysis_drafts';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['proposed_matches' => 'array', 'gaps' => 'array', 'risks' => 'array', 'questions' => 'array', 'recommendations' => 'array', 'approved_at' => 'immutable_datetime'];
    }
}
