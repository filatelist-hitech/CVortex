<?php

namespace App\Services;

use App\Models\CareerFact;
use App\Models\Claim;
use App\Models\User;

class TrustedCareerQuery
{
    public function __construct(
        private readonly CareerOwnerChain $owners,
        private readonly TruthGuard $truthGuard,
    ) {}

    /** @return array{facts: list<CareerFact>, claims: list<Claim>} */
    public function forMatching(User $user): array
    {
        $facts = CareerFact::query()
            ->where('owner_id', $user->id)
            ->where('status', CareerFact::STATUS_CONFIRMED)
            ->whereNotExists(function ($query): void {
                $query->selectRaw('1')->from('career_facts as replacements')
                    ->whereColumn('replacements.supersedes_fact_id', 'career_facts.id')
                    ->whereColumn('replacements.owner_id', 'career_facts.owner_id')
                    ->where('replacements.status', CareerFact::STATUS_CONFIRMED);
            })
            ->latest()
            ->get()
            ->filter(fn (CareerFact $fact): bool => $this->owners->factHasValidProvenance($fact, $user->id, true))
            ->values();

        $claims = Claim::query()->where('owner_id', $user->id)->latest()->get()
            ->filter(fn (Claim $claim): bool => $this->truthGuard->evaluate($claim) === TruthGuard::PASS)
            ->values();

        return ['facts' => $facts->all(), 'claims' => $claims->all()];
    }
}
