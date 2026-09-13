<?php

namespace App\Services;

use App\Models\CareerFact;
use App\Models\Claim;
use Illuminate\Support\Facades\DB;

class TruthGuard
{
    public const PASS = 'PASS';

    public const BLOCK = 'BLOCK';

    public const USER_RESOLUTION_REQUIRED = 'USER_RESOLUTION_REQUIRED';

    public function evaluate(Claim $claim): string
    {
        $evidence = DB::table('claim_evidence')
            ->join('career_facts', 'career_facts.id', '=', 'claim_evidence.career_fact_id')
            ->where('claim_evidence.claim_id', $claim->id)
            ->get([
                'claim_evidence.owner_id as evidence_owner_id',
                'career_facts.owner_id as fact_owner_id',
                'career_facts.status',
                'career_facts.provenance_type',
                'career_facts.career_source_id',
                'career_facts.source_excerpt',
                'career_facts.assertion_original',
                'career_facts.assertion_approved',
                'career_facts.reviewed_by',
                'career_facts.reviewed_at',
            ]);

        if ($evidence->isEmpty()) {
            return self::BLOCK;
        }

        foreach ($evidence as $item) {
            $sameOwner = hash_equals($claim->owner_id, $item->evidence_owner_id)
                && hash_equals($claim->owner_id, $item->fact_owner_id);
            $validProvenance = ($item->provenance_type === CareerFact::PROVENANCE_MANUAL
                    && trim((string) $item->source_excerpt) !== '')
                || ($item->provenance_type === CareerFact::PROVENANCE_EXTRACTION
                    && $item->career_source_id !== null
                    && trim((string) $item->source_excerpt) !== '');
            $humanReviewed = $item->reviewed_by !== null
                && hash_equals($claim->owner_id, $item->reviewed_by)
                && $item->reviewed_at !== null;
            $supportedStatement = hash_equals(
                (string) $claim->statement,
                (string) ($item->assertion_approved ?? $item->assertion_original),
            );

            if (! $sameOwner || $item->status !== CareerFact::STATUS_CONFIRMED || ! $validProvenance || ! $humanReviewed || ! $supportedStatement) {
                return self::BLOCK;
            }
        }

        return self::PASS;
    }
}
