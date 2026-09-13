<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;

class CareerSource extends Model
{
    use HasUlids;

    public const STATUS_PENDING = 'PENDING';

    public const STATUS_RUNNING = 'RUNNING';

    public const STATUS_COMPLETED = 'COMPLETED';

    public const STATUS_FAILED = 'FAILED';

    protected $fillable = ['owner_id', 'career_profile_id', 'kind', 'source_text', 'content_hash', 'extraction_status', 'error_code'];

    protected $hidden = ['source_text', 'content_hash'];
}
