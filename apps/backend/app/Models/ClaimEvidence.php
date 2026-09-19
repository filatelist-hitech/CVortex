<?php

namespace App\Models;

use App\Services\CareerOwnerChain;
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
        $probe = new self;
        $probe->forceFill([
            'owner_id' => $claim->owner_id,
            'claim_id' => $claim->id,
            'career_fact_id' => $fact->id,
        ]);
        if (! app(CareerOwnerChain::class)->evidenceSupportsClaim($probe, $claim, $fact)) {
            throw ValidationException::withMessages(['evidence' => 'Claim evidence must be same-owner, confirmed, and validly provenanced.']);
        }

        $evidence = $probe;
        $evidence->forceFill([
            'owner_id' => $claim->owner_id,
            'claim_id' => $claim->id,
            'career_fact_id' => $fact->id,
        ])->save();

        return $evidence;
    }
}
