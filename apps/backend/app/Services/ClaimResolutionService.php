<?php

namespace App\Services;

use App\Models\CareerFact;
use App\Models\Claim;
use App\Models\ClaimEvidence;
use App\Models\User;
use Illuminate\Validation\ValidationException;

class ClaimResolutionService
{
    public function __construct(
        private readonly TruthGuard $truthGuard,
        private readonly AuditLogger $audit,
    ) {}

    public function requireValidEvidenceResolution(Claim $claim, string $reason = 'VALID_EVIDENCE_AMBIGUITY'): Claim
    {
        $factTypes = ClaimEvidence::query()
            ->join('career_facts', 'career_facts.id', '=', 'claim_evidence.career_fact_id')
            ->where('claim_evidence.claim_id', $claim->id)
            ->where('claim_evidence.owner_id', $claim->owner_id)
            ->distinct()
            ->pluck('career_facts.fact_type');
        if ($this->truthGuard->evaluate($claim) !== TruthGuard::PASS
            || $reason !== 'VALID_EVIDENCE_AMBIGUITY'
            || $factTypes->count() < 2) {
            throw ValidationException::withMessages(['claim' => 'Only a valid-evidence ambiguity may require user resolution.']);
        }

        $claim->forceFill([
            'resolution_reason' => $reason,
            'resolution_requested_at' => now(),
            'resolved_by' => null,
            'resolved_at' => null,
            'resolved_career_fact_id' => null,
            'truth_status' => TruthGuard::USER_RESOLUTION_REQUIRED,
        ])->save();

        return $claim;
    }

    public function resolve(User $user, Claim $claim, CareerFact $selectedFact): Claim
    {
        if (! hash_equals($user->id, (string) $claim->owner_id)) {
            abort(404);
        }
        if ($claim->resolution_reason !== 'VALID_EVIDENCE_AMBIGUITY' || $claim->resolution_requested_at === null) {
            throw ValidationException::withMessages(['claim' => 'The claim does not require a valid-evidence resolution.']);
        }
        $selectedEvidence = ClaimEvidence::query()
            ->where('owner_id', $user->id)
            ->where('claim_id', $claim->id)
            ->where('career_fact_id', $selectedFact->id)
            ->first();
        if ($selectedEvidence === null
            || ! app(CareerOwnerChain::class)->evidenceSupportsClaim($selectedEvidence, $claim, $selectedFact)) {
            throw ValidationException::withMessages(['career_fact_id' => 'The selected fact is not valid evidence for this claim.']);
        }

        $claim->forceFill([
            'resolved_by' => $user->id,
            'resolved_at' => now(),
            'resolved_career_fact_id' => $selectedFact->id,
        ])->save();
        $claim->forceFill(['truth_status' => $this->truthGuard->evaluate($claim)])->save();
        $this->audit->record('claim.valid_evidence_ambiguity_resolved', 'USER', $user, Claim::class, $claim->id, [
            'resolution_reason' => 'VALID_EVIDENCE_AMBIGUITY',
            'resolved_career_fact_id' => $selectedFact->id,
        ]);

        return $claim;
    }
}
