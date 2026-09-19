<?php

use App\Models\CareerFact;
use App\Models\Claim;
use Illuminate\Contracts\Console\Kernel;

[$script, $ownerId, $factId] = $argv;
require __DIR__.'/../../vendor/autoload.php';
$app = require __DIR__.'/../../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
$fact = CareerFact::query()->findOrFail($factId);
$claims = Claim::query()->where('owner_id', $ownerId)->get();
if ($fact->status !== CareerFact::STATUS_CONFIRMED
    || $claims->count() !== 1
    || $claims->sole()->truth_status !== 'PASS') {
    throw new RuntimeException('Review race did not preserve one confirmed fact and one valid Claim.');
}
echo 'verified: one confirmed transition and one PASS Claim'.PHP_EOL;
