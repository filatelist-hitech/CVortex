<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\ValidationException;

class ClaimEvidence extends Model
{
    use HasUlids;

    protected $table = 'claim_evidence';

    protected $guarded = ['*'];

    public static function link(Claim $claim, CareerFact $fact): self
    {
        $validProvenance = $fact->provenance_type === CareerFact::PROVENANCE_MANUAL
            || ($fact->provenance_type === CareerFact::PROVENANCE_EXTRACTION && $fact->career_source_id !== null);
        if (! hash_equals($claim->owner_id, $fact->owner_id)
            || $fact->status !== CareerFact::STATUS_CONFIRMED
            || ! $validProvenance
            || trim((string) $fact->source_excerpt) === ''
            || $fact->reviewed_by === null
            || ! hash_equals($fact->owner_id, $fact->reviewed_by)
            || $fact->reviewed_at === null
            || ! hash_equals($claim->statement, $fact->approvedAssertion())) {
            throw ValidationException::withMessages(['evidence' => 'Claim evidence must be same-owner, confirmed, and validly provenanced.']);
        }

        $evidence = new self;
        $evidence->forceFill([
            'owner_id' => $claim->owner_id,
            'claim_id' => $claim->id,
            'career_fact_id' => $fact->id,
        ])->save();

        return $evidence;
    }
}
