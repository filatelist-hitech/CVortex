<?php

namespace App\Models;

use App\Queue\PendingJobRecovery;
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

    protected $hidden = ['source_text', 'content_hash', 'next_attempt_at', 'dispatch_recovery_at'];

    protected function casts(): array
    {
        return [
            'next_attempt_at' => 'immutable_datetime',
            'dispatch_recovery_at' => 'immutable_datetime',
        ];
    }

    public function extractionRunIsStale(): bool
    {
        return $this->extraction_status === self::STATUS_RUNNING
            && PendingJobRecovery::runIsStale($this->updated_at);
    }
}
