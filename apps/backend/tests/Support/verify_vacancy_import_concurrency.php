<?php

use App\Models\User;
use App\Models\Vacancy;
use App\Models\VacancySnapshot;
use App\Services\DatabaseOwnerContext;
use Illuminate\Contracts\Console\Kernel;

[$script, $ownerId, $sourceUrl] = $argv;
require __DIR__.'/../../vendor/autoload.php';
$app = require __DIR__.'/../../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

$user = User::query()->findOrFail($ownerId);
app(DatabaseOwnerContext::class)->run((string) $user->id, function () use ($user, $sourceUrl): void {
    $vacancies = Vacancy::query()
        ->where('owner_id', $user->id)
        ->where('source_url', $sourceUrl)
        ->get();
    if ($vacancies->count() !== 1) {
        throw new RuntimeException('Concurrent imports created duplicate Vacancy aggregates.');
    }

    $snapshots = VacancySnapshot::query()
        ->where('owner_id', $user->id)
        ->where('vacancy_id', $vacancies->sole()->id)
        ->orderBy('version')
        ->get();
    if ($snapshots->count() !== 2 || $snapshots->pluck('version')->all() !== [1, 2]) {
        throw new RuntimeException('Concurrent snapshot versions are not unique and monotonic.');
    }
    if ($snapshots->pluck('content_hash')->unique()->count() !== 2) {
        throw new RuntimeException('Concurrent changed-content imports did not preserve both snapshots.');
    }
});

echo 'verified: one Vacancy aggregate, two changed-content snapshots, versions 1 and 2'.PHP_EOL;
