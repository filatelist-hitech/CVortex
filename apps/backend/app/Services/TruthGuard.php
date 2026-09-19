<?php

namespace App\Services;

use App\Models\CareerFact;
use App\Models\Claim;
use App\Models\ClaimEvidence;

class TruthGuard
{
    public function __construct(private readonly CareerOwnerChain $owners) {}

    public const PASS = 'PASS';

    public const BLOCK = 'BLOCK';

    public const USER_RESOLUTION_REQUIRED = 'USER_RESOLUTION_REQUIRED';

    public function evaluate(Claim $claim): string
    {
        $evidence = ClaimEvidence::query()->where('claim_id', $claim->id)->get();

        if ($evidence->isEmpty()) {
            return self::BLOCK;
        }

        foreach ($evidence as $item) {
            $fact = CareerFact::query()->find($item->career_fact_id);
            if ($fact === null || ! $this->owners->evidenceSupportsClaim($item, $claim, $fact)) {
                return self::BLOCK;
            }
        }

        if ($claim->resolution_reason === 'VALID_EVIDENCE_AMBIGUITY'
            && $claim->resolution_requested_at !== null
            && $claim->resolved_at === null) {
            return self::USER_RESOLUTION_REQUIRED;
        }
        if ($claim->resolution_reason === 'VALID_EVIDENCE_AMBIGUITY'
            && ($claim->resolved_at === null
                || $claim->resolved_by === null
                || $claim->resolved_career_fact_id === null
                || ! $evidence->contains(
                    fn (ClaimEvidence $item): bool => hash_equals(
                        (string) $claim->resolved_career_fact_id,
                        (string) $item->career_fact_id,
                    ),
                ))) {
            return self::BLOCK;
        }

        return self::PASS;
    }
}
