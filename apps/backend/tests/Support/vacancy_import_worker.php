<?php

use App\Models\User;
use App\Services\VacancyIngestionService;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\DB;

[$script, $ownerId, $sourceUrl, $sourceText, $workerId] = $argv;
require __DIR__.'/../../vendor/autoload.php';
$app = require __DIR__.'/../../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

DB::table('vacancy_import_test_barrier')->insert(['worker_id' => $workerId]);
$deadline = microtime(true) + 15;
while (DB::table('vacancy_import_test_barrier')->count() < 2) {
    if (microtime(true) > $deadline) {
        fwrite(STDERR, "barrier timeout\n");
        exit(3);
    }
    usleep(10000);
}

$result = app(VacancyIngestionService::class)->queue(
    User::query()->findOrFail($ownerId),
    $sourceText,
    $sourceUrl,
);

echo implode(' ', [
    $result['vacancy']->id,
    $result['snapshot']->id,
    (string) $result['snapshot']->version,
]).PHP_EOL;
