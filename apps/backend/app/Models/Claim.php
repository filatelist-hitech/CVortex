<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;

class Claim extends Model
{
    use HasUlids;

    protected $fillable = ['owner_id', 'statement', 'truth_status'];
}
