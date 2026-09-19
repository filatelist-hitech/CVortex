<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;

/**
 * @property string|null $validation_result
 * @property string|null $error_category
 */
class LlmRun extends Model
{
    use HasUlids;

    protected $fillable = [
        'owner_id', 'career_source_id', 'workflow', 'skill_id', 'skill_version', 'prompt_version',
        'model_policy', 'provider', 'model', 'provider_request_id', 'status', 'input_tokens', 'output_tokens', 'latency_ms',
        'retry_count', 'validation_result', 'error_category', 'estimated_cost_micros',
    ];
}
