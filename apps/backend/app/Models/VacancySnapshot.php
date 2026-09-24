<?php

namespace App\Models;

use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use LogicException;

class VacancySnapshot extends Model
{
    use HasUlids;

    protected $guarded = ['*'];

    protected $hidden = ['content_hash'];

    protected function casts(): array
    {
        return ['imported_at' => 'datetime'];
    }

    protected static function booted(): void
    {
        static::updating(function (): void {
            throw new LogicException('Vacancy snapshots are immutable. Create a new snapshot instead.');
        });
    }

    public static function record(
        string $ownerId,
        string $vacancyId,
        int $version,
        string $rawText,
        ?string $sourceUrl,
        string $contentHash,
        CarbonInterface $importedAt,
    ): self {
        $snapshot = new self;
        $snapshot->forceFill([
            'owner_id' => $ownerId,
            'vacancy_id' => $vacancyId,
            'version' => $version,
            'raw_text' => $rawText,
            'source_url' => $sourceUrl,
            'content_hash' => $contentHash,
            'imported_at' => $importedAt,
        ]);
        $snapshot->save();

        return $snapshot;
    }
}
