<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;

class VacancyMatchEvidence extends Model
{
    use HasUlids;

    protected $table = 'vacancy_match_evidence';

    protected $fillable = ['owner_id', 'vacancy_match_dimension_id', 'career_fact_id', 'claim_id'];
}
