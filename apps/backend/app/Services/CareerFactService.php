<?php

namespace App\Services;

use App\Models\CareerFact;
use App\Models\CareerProfile;
use App\Models\Claim;
use App\Models\ClaimEvidence;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CareerFactService
{
    public function __construct(
        private readonly TruthGuard $truthGuard,
        private readonly AuditLogger $audit,
    ) {}

    public function profileFor(User $user): CareerProfile
    {
        return CareerProfile::query()->firstOrCreate(['owner_id' => $user->id]);
    }

    public function createManual(User $user, string $factType, string $assertion): CareerFact
    {
        return DB::transaction(function () use ($user, $factType, $assertion): CareerFact {
            $fact = CareerFact::query()->create([
                'owner_id' => $user->id,
                'career_profile_id' => $this->profileFor($user)->id,
                'provenance_type' => CareerFact::PROVENANCE_MANUAL,
                'fact_type' => $factType,
                'assertion_original' => $assertion,
                'assertion_approved' => $assertion,
                'source_excerpt' => $assertion,
                'extracted_by' => 'user_manual',
                'status' => CareerFact::STATUS_CONFIRMED,
                'reviewed_by' => $user->id,
                'reviewed_at' => now(),
            ]);
            $this->createClaim($fact);
            $this->audit->record('career_fact.manual_confirmed', 'USER', $user, CareerFact::class, $fact->id, [
                'provenance_type' => CareerFact::PROVENANCE_MANUAL,
            ]);

            return $fact;
        });
    }

    public function review(User $user, CareerFact $fact, string $action, ?string $approvedAssertion = null): CareerFact
    {
        if (! hash_equals($user->id, $fact->owner_id)) {
            abort(404);
        }
        if ($fact->status !== CareerFact::STATUS_PENDING) {
            throw ValidationException::withMessages(['action' => 'Only pending facts may be reviewed.']);
        }

        return DB::transaction(function () use ($user, $fact, $action, $approvedAssertion): CareerFact {
            if ($action === 'reject') {
                $fact->forceFill([
                    'status' => CareerFact::STATUS_REJECTED,
                    'reviewed_by' => $user->id,
                    'reviewed_at' => now(),
                ])->save();
                $this->audit->record('career_fact.rejected', 'USER', $user, CareerFact::class, $fact->id);

                return $fact;
            }

            if ($action === 'edit_confirm' && ($approvedAssertion === null || trim($approvedAssertion) === '')) {
                throw ValidationException::withMessages(['assertion' => 'An edited assertion is required.']);
            }

            $fact->forceFill([
                'assertion_approved' => $action === 'edit_confirm' ? trim((string) $approvedAssertion) : $fact->assertion_original,
                'status' => CareerFact::STATUS_CONFIRMED,
                'reviewed_by' => $user->id,
                'reviewed_at' => now(),
            ])->save();
            $this->createClaim($fact);
            $this->audit->record(
                $action === 'edit_confirm' ? 'career_fact.edited_and_confirmed' : 'career_fact.confirmed',
                'USER',
                $user,
                CareerFact::class,
                $fact->id,
            );

            return $fact;
        });
    }

    public function deprecate(User $user, CareerFact $fact): CareerFact
    {
        if (! hash_equals($user->id, $fact->owner_id)) {
            abort(404);
        }
        if ($fact->status !== CareerFact::STATUS_CONFIRMED) {
            throw ValidationException::withMessages(['action' => 'Only confirmed facts may be deprecated.']);
        }
        DB::transaction(function () use ($fact, $user): void {
            $fact->forceFill(['status' => CareerFact::STATUS_DEPRECATED, 'reviewed_by' => $user->id, 'reviewed_at' => now()])->save();
            Claim::query()->whereIn('id', ClaimEvidence::query()->where('career_fact_id', $fact->id)->pluck('claim_id'))
                ->update(['truth_status' => TruthGuard::BLOCK]);
            $this->audit->record('career_fact.deprecated', 'USER', $user, CareerFact::class, $fact->id);
        });

        return $fact;
    }

    private function createClaim(CareerFact $fact): Claim
    {
        $claim = Claim::query()->create([
            'owner_id' => $fact->owner_id,
            'statement' => $fact->approvedAssertion(),
            'truth_status' => TruthGuard::BLOCK,
        ]);
        ClaimEvidence::link($claim, $fact);
        $claim->forceFill(['truth_status' => $this->truthGuard->evaluate($claim)])->save();

        return $claim;
    }
}
