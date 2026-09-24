<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;

class ApplicationDraftRevision extends Model
{
    use HasUlids;

    public $timestamps = false;

    protected $guarded = ['*'];

    protected function casts(): array
    {
        return [
            'claim_usages' => 'array',
            'created_at' => 'datetime',
        ];
    }
}
