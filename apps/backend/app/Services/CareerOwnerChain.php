<?php

namespace App\Services;

use App\Models\CareerFact;
use App\Models\CareerProfile;
use App\Models\CareerSource;
use App\Models\Claim;
use App\Models\ClaimEvidence;

class CareerOwnerChain
{
    public function factHasValidProvenance(CareerFact $fact, string $ownerId, bool $requireConfirmed = false): bool
    {
        if (! $fact->exists
            || ! hash_equals($ownerId, (string) $fact->owner_id)
            || ($requireConfirmed && $fact->status !== CareerFact::STATUS_CONFIRMED)) {
            return false;
        }

        $profile = CareerProfile::query()->find($fact->career_profile_id);
        if ($profile === null || ! hash_equals($ownerId, (string) $profile->owner_id)) {
            return false;
        }

        if ($fact->provenance_type === CareerFact::PROVENANCE_MANUAL) {
            return $fact->career_source_id === null
                && trim((string) $fact->source_excerpt) !== '';
        }

        if ($fact->provenance_type !== CareerFact::PROVENANCE_EXTRACTION || $fact->career_source_id === null) {
            return false;
        }

        $source = CareerSource::query()->find($fact->career_source_id);

        return $source !== null
            && hash_equals($ownerId, (string) $source->owner_id)
            && hash_equals((string) $fact->career_profile_id, (string) $source->career_profile_id)
            && trim((string) $fact->source_excerpt) !== ''
            && str_contains((string) $source->source_text, (string) $fact->source_excerpt);
    }

    public function evidenceSupportsClaim(ClaimEvidence $evidence, Claim $claim, CareerFact $fact): bool
    {
        $ownerId = (string) $claim->owner_id;

        return hash_equals($ownerId, (string) $evidence->owner_id)
            && hash_equals((string) $claim->id, (string) $evidence->claim_id)
            && hash_equals((string) $fact->id, (string) $evidence->career_fact_id)
            && $this->factHasValidProvenance($fact, $ownerId, true)
            && $fact->reviewed_by !== null
            && hash_equals($ownerId, (string) $fact->reviewed_by)
            && $fact->reviewed_at !== null
            && hash_equals((string) $claim->statement, $fact->approvedAssertion())
            && ! CareerFact::query()->where('owner_id', $ownerId)
                ->where('supersedes_fact_id', $fact->id)
                ->where('status', CareerFact::STATUS_CONFIRMED)
                ->exists();
    }
}
