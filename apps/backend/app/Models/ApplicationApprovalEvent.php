<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;

class ApplicationApprovalEvent extends Model
{
    use HasUlids;

    public $timestamps = false;

    protected $guarded = ['*'];

    protected function casts(): array
    {
        return ['revision_number' => 'integer', 'created_at' => 'datetime'];
    }
}
