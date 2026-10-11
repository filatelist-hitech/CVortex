<?php

namespace Tests\Support;

use App\Models\CareerFact;
use Illuminate\Support\Collection;
use RuntimeException;

final class CareerReviewInvariant
{
    public static function verify(CareerFact $fact, Collection $claims, string $ownerId, string $winningAction): void
    {
        if (! in_array($winningAction, ['confirm', 'reject'], true) || $fact->owner_id !== $ownerId) {
            throw new RuntimeException('Invalid winner or fact owner.');
        }
        if ($winningAction === 'reject') {
            if ($fact->status !== CareerFact::STATUS_REJECTED || $claims->isNotEmpty()) {
                throw new RuntimeException('Rejected race must not create a Claim.');
            }

            return;
        }
        if ($fact->status !== CareerFact::STATUS_CONFIRMED || $claims->count() !== 1
            || $claims->sole()->owner_id !== $ownerId || $claims->sole()->truth_status !== 'PASS'
            || $claims->sole()->statement !== $fact->approvedAssertion()) {
            throw new RuntimeException('Confirmed race must preserve one valid owned Claim.');
        }
    }
}
