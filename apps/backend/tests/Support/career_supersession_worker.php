<?php

use App\Exceptions\CareerFactSupersessionConflict;
use App\Models\CareerFact;
use App\Models\User;
use App\Services\CareerFactService;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\DB;

[$script, $ownerId, $factId, $assertion, $workerId] = $argv;
require __DIR__.'/../../vendor/autoload.php';
$app = require __DIR__.'/../../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
User::query()->findOrFail($ownerId);
CareerFact::query()->findOrFail($factId);
$deadline = microtime(true) + 15;
DB::table('career_supersession_test_barrier')->insert(['worker_id' => $workerId]);
while (DB::table('career_supersession_test_barrier')->count() < 2) {
    if (microtime(true) > $deadline) {
        fwrite(STDERR, "barrier timeout\n");
        exit(3);
    }
    usleep(10000);
}

try {
    $replacement = app(CareerFactService::class)->supersede(
        User::query()->findOrFail($ownerId),
        CareerFact::query()->findOrFail($factId),
        'skill',
        $assertion,
    );
    echo 'created '.$replacement->id.PHP_EOL;
    exit(0);
} catch (CareerFactSupersessionConflict) {
    echo 'conflict'.PHP_EOL;
    exit(2);
}
