<?php

namespace App\Models;

use App\AI\Exceptions\LlmProviderException;
use App\Queue\PendingJobRecovery;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;

class Vacancy extends Model
{
    use HasUlids;

    public const STATUS_PENDING = 'PENDING';

    public const STATUS_RUNNING = 'RUNNING';

    public const STATUS_COMPLETED = 'COMPLETED';

    public const STATUS_FAILED = 'FAILED';

    public const ANALYSIS_JOB_UNIQUE_FOR_SECONDS = LlmProviderException::UNIQUE_LOCK_SECONDS;

    protected $fillable = [
        'owner_id', 'source_type', 'source_url', 'title', 'company', 'analysis_status', 'error_code',
    ];

    protected $hidden = ['next_attempt_at', 'dispatch_recovery_at', 'active_run_token'];

    protected function casts(): array
    {
        return [
            'next_attempt_at' => 'immutable_datetime',
            'dispatch_recovery_at' => 'immutable_datetime',
        ];
    }

    public function analysisRunIsStale(): bool
    {
        if ($this->analysis_status !== self::STATUS_RUNNING || $this->updated_at === null) {
            return false;
        }

        return PendingJobRecovery::runIsStale($this->updated_at);
    }
}
