<?php

use App\Models\CareerFact;
use App\Models\Claim;
use App\Models\ClaimEvidence;
use App\Services\TruthGuard;
use Illuminate\Contracts\Console\Kernel;
use Tests\Support\CareerReviewInvariant;

// Explicitly exit: Laravel's global handler can render an uncaught exception
// from a standalone PHP helper without propagating a failing process status.
try {
    if (count($argv) !== 4) {
        throw new RuntimeException('Expected owner, fact and winning action.');
    }
    [$script, $ownerId, $factId, $winningAction] = $argv;
    require __DIR__.'/../../vendor/autoload.php';
    $app = require __DIR__.'/../../bootstrap/app.php';
    $app->make(Kernel::class)->bootstrap();
    $fact = CareerFact::query()->findOrFail($factId);
    $claims = Claim::query()->where('owner_id', $ownerId)->get();
    CareerReviewInvariant::verify($fact, $claims, $ownerId, $winningAction);
    if ($winningAction === 'confirm') {
        $claim = $claims->sole();
        if (ClaimEvidence::query()->where('claim_id', $claim->id)
            ->where('career_fact_id', $factId)->where('owner_id', $ownerId)->count() !== 1
            || app(TruthGuard::class)->evaluate($claim) !== TruthGuard::PASS) {
            throw new RuntimeException('Confirmed Claim has invalid backing evidence.');
        }
    }
    echo 'verified: '.$winningAction.' transition and valid Claim state'.PHP_EOL;
} catch (Throwable) {
    fwrite(STDERR, 'career-review-verifier: FAIL (invariant or verifier exception)'.PHP_EOL);
    exit(1);
}
