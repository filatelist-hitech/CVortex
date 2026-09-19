<?php

use App\Exceptions\CareerFactSupersessionConflict;
use App\Models\CareerFact;
use App\Models\Claim;
use App\Models\User;
use App\Services\CareerFactService;
use App\Services\TrustedCareerQuery;
use App\Services\TruthGuard;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Database\QueryException;

[$script, $ownerId, $originalId] = $argv;
require __DIR__.'/../../vendor/autoload.php';
$app = require __DIR__.'/../../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
$user = User::query()->findOrFail($ownerId);
$original = CareerFact::query()->findOrFail($originalId);
$children = CareerFact::query()->where('supersedes_fact_id', $originalId)->where('status', CareerFact::STATUS_CONFIRMED)->get();
if ($children->count() !== 1 || $original->status !== CareerFact::STATUS_DEPRECATED) {
    throw new RuntimeException('Expected one confirmed replacement and a deprecated original.');
}
$replacement = $children->sole();
if ($original->reviewed_by !== $ownerId || $original->reviewed_at === null) {
    throw new RuntimeException('Original review history was not preserved.');
}

$duplicateRejected = false;
try {
    app(CareerFactService::class)->supersede($user, $original, 'skill', 'Direct duplicate attempt.');
} catch (CareerFactSupersessionConflict) {
    $duplicateRejected = true;
}
if (! $duplicateRejected) {
    throw new RuntimeException('A duplicate replacement operation was not rejected.');
}

$duplicateRejectedByIndex = false;
try {
    CareerFact::query()->create([
        'owner_id' => $user->id,
        'career_profile_id' => $replacement->career_profile_id,
        'supersedes_fact_id' => $original->id,
        'provenance_type' => CareerFact::PROVENANCE_MANUAL,
        'fact_type' => 'skill',
        'assertion_original' => 'Direct database duplicate.',
        'assertion_approved' => 'Direct database duplicate.',
        'source_excerpt' => 'Direct database duplicate.',
        'extracted_by' => 'user_manual',
        'candidate_hash' => hash('sha256', 'direct-db-duplicate'),
        'status' => CareerFact::STATUS_CONFIRMED,
        'reviewed_by' => $user->id,
        'reviewed_at' => now(),
    ]);
} catch (QueryException $exception) {
    if (($exception->errorInfo[0] ?? null) !== '23505') {
        throw $exception;
    }
    $duplicateRejectedByIndex = true;
}
if (! $duplicateRejectedByIndex) {
    throw new RuntimeException('The PostgreSQL unique index accepted a duplicate confirmed replacement.');
}

$oldClaim = Claim::query()->where('statement', 'Original concurrency assertion.')->sole();
$replacementClaim = Claim::query()->where('statement', $replacement->approvedAssertion())->sole();
if (app(TruthGuard::class)->evaluate($oldClaim) !== TruthGuard::BLOCK
    || app(TruthGuard::class)->evaluate($replacementClaim) !== TruthGuard::PASS) {
    throw new RuntimeException('Claim or Truth Guard state is inconsistent after supersession.');
}
$trusted = app(TrustedCareerQuery::class)->forMatching($user)['facts'];
if (count($trusted) !== 1 || $trusted[0]->id !== $replacement->id) {
    throw new RuntimeException('TrustedCareerQuery returned duplicate or incorrect current facts.');
}

$next = app(CareerFactService::class)->supersede($user, $replacement, 'skill', 'Second historical correction.');
$chain = CareerFact::query()->whereIn('id', [$original->id, $replacement->id, $next->id])->orderBy('created_at')->get();
if ($chain->count() !== 3
    || $chain[0]->status !== CareerFact::STATUS_DEPRECATED
    || $chain[1]->supersedes_fact_id !== $original->id
    || $chain[1]->status !== CareerFact::STATUS_DEPRECATED
    || $chain[2]->supersedes_fact_id !== $replacement->id
    || $chain[2]->status !== CareerFact::STATUS_CONFIRMED) {
    throw new RuntimeException('Historical A → B → C supersession chain is invalid.');
}
$trustedAfterChain = app(TrustedCareerQuery::class)->forMatching($user)['facts'];
if (count($trustedAfterChain) !== 1 || $trustedAfterChain[0]->id !== $next->id) {
    throw new RuntimeException('TrustedCareerQuery did not expose only the chain tip.');
}
echo 'verified: one race winner, index rejection, Claim/Truth Guard, trusted query, A-B-C history'.PHP_EOL;
