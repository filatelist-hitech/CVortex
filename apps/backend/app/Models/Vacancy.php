<?php

namespace App\Models;

use App\AI\Exceptions\LlmProviderException;
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

    public function analysisRunIsStale(): bool
    {
        if ($this->analysis_status !== self::STATUS_RUNNING || $this->updated_at === null) {
            return false;
        }

        $connection = (string) config('queue.default', 'redis');
        $retryAfter = (int) config('queue.connections.'.$connection.'.retry_after', 90);
        $workerTimeout = (int) config('horizon.defaults.supervisor-1.timeout', config('horizon.defaults.timeout', 60));
        // A provider retry leaves the analysis PENDING; unique-lock lifetime
        // must not delay recovery of a worker that was stranded in RUNNING.
        $staleAfter = max(180, $retryAfter * 2, $workerTimeout * 2);

        return $this->updated_at->lte(now()->subSeconds($staleAfter));
    }
}
