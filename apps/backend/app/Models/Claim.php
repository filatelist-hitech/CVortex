<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;

class Claim extends Model
{
    use HasUlids;

    protected $fillable = [
        'owner_id', 'statement', 'truth_status', 'resolution_reason', 'resolution_requested_at',
        'resolved_by', 'resolved_at', 'resolved_career_fact_id',
    ];

    protected function casts(): array
    {
        return ['resolution_requested_at' => 'datetime', 'resolved_at' => 'datetime'];
    }
}
