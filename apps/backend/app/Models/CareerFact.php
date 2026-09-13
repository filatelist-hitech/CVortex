<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;

class CareerFact extends Model
{
    use HasUlids;

    public const STATUS_PENDING = 'PENDING';

    public const STATUS_CONFIRMED = 'CONFIRMED';

    public const STATUS_REJECTED = 'REJECTED';

    public const STATUS_DEPRECATED = 'DEPRECATED';

    public const PROVENANCE_EXTRACTION = 'paste_extraction';

    public const PROVENANCE_MANUAL = 'user_manual';

    protected $fillable = [
        'owner_id', 'career_profile_id', 'career_source_id', 'supersedes_fact_id', 'provenance_type',
        'fact_type', 'assertion_original', 'assertion_approved', 'source_excerpt', 'extracted_by',
        'extraction_confidence', 'status', 'reviewed_by', 'reviewed_at',
    ];

    protected function casts(): array
    {
        return ['reviewed_at' => 'datetime', 'extraction_confidence' => 'float'];
    }

    public function approvedAssertion(): string
    {
        return $this->assertion_approved ?? $this->assertion_original;
    }
}
