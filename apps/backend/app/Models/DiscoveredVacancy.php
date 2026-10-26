<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property string $id
 * @property string $owner_id
 * @property string $portal
 * @property string|null $external_id
 * @property string $title
 * @property string $company
 * @property string|null $location
 * @property string $source_url
 * @property string|null $salary_text
 * @property string|null $snippet
 * @property string|null $description
 * @property int $fit_score
 * @property string $fit_level
 * @property array<string, mixed>|null $fit_reasons
 * @property string $status
 * @property string $security_threat_level
 * @property CarbonImmutable|null $published_at
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 * @property-read User $owner
 */
class DiscoveredVacancy extends Model
{
    use HasUlids;

    public const STATUS_INBOX = 'INBOX';

    public const STATUS_ACCEPTED = 'ACCEPTED';

    public const STATUS_DISMISSED = 'DISMISSED';

    public const FIT_HIGH = 'HIGH';

    public const FIT_MEDIUM = 'MEDIUM';

    public const FIT_LOW = 'LOW';

    public const FIT_UNKNOWN = 'UNKNOWN';

    public const SECURITY_CLEAN = 'CLEAN';

    public const SECURITY_SUSPICIOUS = 'SUSPICIOUS';

    public const SECURITY_MALICIOUS = 'MALICIOUS';

    protected $table = 'discovered_vacancies';

    protected $fillable = [
        'owner_id',
        'portal',
        'external_id',
        'title',
        'company',
        'location',
        'source_url',
        'salary_text',
        'snippet',
        'description',
        'fit_score',
        'fit_level',
        'fit_reasons',
        'status',
        'security_threat_level',
        'published_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'fit_score' => 'integer',
            'fit_reasons' => 'array',
            'published_at' => 'immutable_datetime',
            'created_at' => 'immutable_datetime',
            'updated_at' => 'immutable_datetime',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id');
    }
}
