<?php

use App\Models\CareerFact;
use App\Models\User;
use App\Services\CareerFactService;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

[$script, $ownerId, $factId, $action, $workerId] = $argv;
require __DIR__.'/../../vendor/autoload.php';
$app = require __DIR__.'/../../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
$user = User::query()->findOrFail($ownerId);
$fact = CareerFact::query()->findOrFail($factId);
$deadline = microtime(true) + 15;
DB::table('career_review_test_barrier')->insert(['worker_id' => $workerId]);
while (DB::table('career_review_test_barrier')->count() < 2) {
    if (microtime(true) > $deadline) {
        fwrite(STDERR, "barrier timeout\n");
        exit(3);
    }
    usleep(10000);
}

try {
    app(CareerFactService::class)->review($user, $fact, $action);
    echo 'applied '.$action.PHP_EOL;
} catch (ValidationException) {
    echo 'conflict '.$action.PHP_EOL;
} catch (QueryException $exception) {
    throw $exception;
}
