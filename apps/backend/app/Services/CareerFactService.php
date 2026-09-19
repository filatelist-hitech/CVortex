<?php

namespace App\Services;

use App\Exceptions\CareerFactSupersessionConflict;
use App\Exceptions\SafeCareerException;
use App\Models\CareerFact;
use App\Models\CareerFactType;
use App\Models\CareerProfile;
use App\Models\Claim;
use App\Models\ClaimEvidence;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CareerFactService
{
    public function __construct(
        private readonly TruthGuard $truthGuard,
        private readonly CareerOwnerChain $owners,
        private readonly AuditLogger $audit,
        private readonly ClaimResolutionService $resolutions,
    ) {}

    public function profileFor(User $user): CareerProfile
    {
        return CareerProfile::query()->firstOrCreate(['owner_id' => $user->id]);
    }

    public function createManual(User $user, string $factType, string $assertion): CareerFact
    {
        if (CareerFactType::tryFrom($factType) === null) {
            throw ValidationException::withMessages(['fact_type' => 'The selected fact type is not supported.']);
        }

        try {
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
                    'candidate_hash' => hash('sha256', $factType."\0".$assertion),
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
        } catch (QueryException) {
            throw new SafeCareerException;
        }
    }

    public function review(User $user, CareerFact $fact, string $action, ?string $approvedAssertion = null): CareerFact
    {
        if (! hash_equals($user->id, $fact->owner_id)) {
            abort(404);
        }
        if ($fact->status !== CareerFact::STATUS_PENDING) {
            throw ValidationException::withMessages(['action' => 'Only pending facts may be reviewed.']);
        }
        if (! $this->owners->factHasValidProvenance($fact, $user->id)) {
            throw ValidationException::withMessages(['fact' => 'The fact ownership or provenance chain is invalid.']);
        }

        try {
            return DB::transaction(function () use ($user, $fact, $action, $approvedAssertion): CareerFact {
                $fact = CareerFact::query()->whereKey($fact->id)->lockForUpdate()->first();
                if ($fact === null || $fact->status !== CareerFact::STATUS_PENDING
                    || ! $this->owners->factHasValidProvenance($fact, $user->id)) {
                    throw ValidationException::withMessages(['action' => 'Only valid pending facts may be reviewed.']);
                }

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
        } catch (QueryException) {
            throw new SafeCareerException;
        }
    }

    public function deprecate(User $user, CareerFact $fact): CareerFact
    {
        if (! hash_equals($user->id, $fact->owner_id)) {
            abort(404);
        }
        if ($fact->status !== CareerFact::STATUS_CONFIRMED) {
            throw ValidationException::withMessages(['action' => 'Only confirmed facts may be deprecated.']);
        }
        try {
            DB::transaction(function () use ($fact, $user): void {
                $fact->forceFill(['status' => CareerFact::STATUS_DEPRECATED, 'reviewed_by' => $user->id, 'reviewed_at' => now()])->save();
                Claim::query()->whereIn('id', ClaimEvidence::query()->where('career_fact_id', $fact->id)->pluck('claim_id'))
                    ->update(['truth_status' => TruthGuard::BLOCK]);
                $this->audit->record('career_fact.deprecated', 'USER', $user, CareerFact::class, $fact->id);
            });
        } catch (QueryException) {
            throw new SafeCareerException;
        }

        return $fact;
    }

    public function supersede(User $user, CareerFact $original, string $factType, string $assertion): CareerFact
    {
        if (! hash_equals($user->id, (string) $original->owner_id)) {
            abort(404);
        }
        if (CareerFactType::tryFrom($factType) === null) {
            throw ValidationException::withMessages(['fact_type' => 'The selected fact type is not supported.']);
        }

        try {
            return DB::transaction(function () use ($user, $original, $factType, $assertion): CareerFact {
                $lockedOriginal = CareerFact::query()->whereKey($original->id)->lockForUpdate()->first();
                if ($lockedOriginal === null || ! hash_equals($user->id, (string) $lockedOriginal->owner_id)) {
                    abort(404);
                }

                if ($lockedOriginal->status !== CareerFact::STATUS_CONFIRMED
                    || ! $this->owners->factHasValidProvenance($lockedOriginal, $user->id, true)
                    || CareerFact::query()->where('supersedes_fact_id', $lockedOriginal->id)
                        ->where('status', CareerFact::STATUS_CONFIRMED)->exists()) {
                    throw new CareerFactSupersessionConflict;
                }

                $replacement = CareerFact::query()->create([
                    'owner_id' => $user->id,
                    'career_profile_id' => $lockedOriginal->career_profile_id,
                    'career_source_id' => null,
                    'supersedes_fact_id' => $lockedOriginal->id,
                    'provenance_type' => CareerFact::PROVENANCE_MANUAL,
                    'fact_type' => $factType,
                    'assertion_original' => $assertion,
                    'assertion_approved' => $assertion,
                    'source_excerpt' => $assertion,
                    'extracted_by' => 'user_manual',
                    'candidate_hash' => hash('sha256', $factType."\0".$assertion."\0".$original->id),
                    'status' => CareerFact::STATUS_CONFIRMED,
                    'reviewed_by' => $user->id,
                    'reviewed_at' => now(),
                ]);
                $lockedOriginal->forceFill([
                    'status' => CareerFact::STATUS_DEPRECATED,
                ])->save();
                Claim::query()->whereIn('id', ClaimEvidence::query()->where('career_fact_id', $lockedOriginal->id)->pluck('claim_id'))
                    ->update(['truth_status' => TruthGuard::BLOCK]);
                $this->createClaim($replacement);
                $this->audit->record('career_fact.superseded', 'USER', $user, CareerFact::class, $replacement->id, [
                    'supersedes_fact_id' => $lockedOriginal->id,
                ]);

                return $replacement;
            });
        } catch (CareerFactSupersessionConflict $exception) {
            throw $exception;
        } catch (QueryException $exception) {
            if (($exception->errorInfo[0] ?? null) === '23505'
                && str_contains($exception->getMessage(), 'career_facts_one_confirmed_replacement_unique')) {
                throw new CareerFactSupersessionConflict;
            }
            throw new SafeCareerException;
        }
    }

    private function createClaim(CareerFact $fact): Claim
    {
        if (! $this->owners->factHasValidProvenance($fact, (string) $fact->owner_id, true)) {
            throw ValidationException::withMessages(['fact' => 'The fact ownership or provenance chain is invalid.']);
        }
        $claim = Claim::query()->where('owner_id', $fact->owner_id)
            ->where('statement', $fact->approvedAssertion())
            ->whereIn('truth_status', [TruthGuard::PASS, TruthGuard::USER_RESOLUTION_REQUIRED])
            ->first();
        if ($claim !== null) {
            $ambiguityAlreadyRecorded = $claim->truth_status === TruthGuard::USER_RESOLUTION_REQUIRED;
            ClaimEvidence::link($claim, $fact);
            $claim->forceFill(['truth_status' => $this->truthGuard->evaluate($claim)])->save();
            if (! $ambiguityAlreadyRecorded) {
                $this->resolutions->recordAmbiguityIfPresent($claim);
            }

            return $claim;
        }

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
