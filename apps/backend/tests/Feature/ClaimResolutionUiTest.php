<?php

namespace Tests\Feature;

use App\Models\CareerFact;
use App\Models\Claim;
use App\Models\User;
use App\Services\CareerFactService;
use App\Services\TrustedCareerQuery;
use App\Services\TruthGuard;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class ClaimResolutionUiTest extends TestCase
{
    use RefreshDatabase;

    private function user(): User
    {
        return User::query()->create(['email' => Str::ulid().'@qa004.example.test', 'password' => 'synthetic safe test passphrase'])->fresh();
    }

    /** @return array{User, Claim, CareerFact} */
    private function ambiguity(): array
    {
        $owner = $this->user();
        $service = app(CareerFactService::class);
        $service->createManual($owner, 'skill', 'Synthetic supported assertion.');
        $selected = $service->createManual($owner, 'experience', 'Synthetic supported assertion.');
        $claim = Claim::query()->where('owner_id', $owner->id)->sole();
        $this->assertSame(TruthGuard::USER_RESOLUTION_REQUIRED, $claim->truth_status);

        return [$owner, $claim, $selected];
    }

    public function test_owner_can_resolve_and_repeat_without_creating_or_confirming_facts(): void
    {
        [$owner, $claim, $selected] = $this->ambiguity();
        $this->actingAs($owner)->getJson('/api/v1/career')->assertOk()
            ->assertJsonCount(2, 'data.claims.0.fact_ids');
        $before = CareerFact::query()->get()->toArray();
        foreach ([1, 2] as $attempt) {
            $this->actingAs($owner)->patchJson('/api/v1/career/claims/'.$claim->id.'/resolve', [
                'career_fact_id' => $selected->id,
            ])->assertOk()->assertJsonPath('data.truth_status', TruthGuard::PASS)
                ->assertJsonPath('data.resolved_career_fact_id', $selected->id);
            $this->assertSame($before, CareerFact::query()->get()->toArray());
        }
        $this->assertSame(TruthGuard::PASS, app(TruthGuard::class)->evaluate($claim->fresh()));
        $this->actingAs($owner)->getJson('/api/v1/career')->assertJsonPath('data.claims.0.truth_status', TruthGuard::PASS);
    }

    /** @return array<string, array{string, int}> */
    public static function invalidChoices(): array
    {
        return [
            'foreign' => ['foreign', 404],
            'unrelated' => ['unrelated', 422],
            'pending' => ['PENDING', 422],
            'rejected' => ['REJECTED', 422],
            'deprecated' => ['DEPRECATED', 422],
            'missing' => ['missing', 422],
            'deleted' => ['deleted', 404],
            'superseded' => ['superseded', 422],
            'stale nonambiguity' => ['stale', 422],
        ];
    }

    #[DataProvider('invalidChoices')]
    public function test_invalid_evidence_never_resolves(string $scenario, int $status): void
    {
        [$owner, $claim, $selected] = $this->ambiguity();
        $service = app(CareerFactService::class);
        $factId = $selected->id;
        if ($scenario === 'foreign') {
            $foreign = $this->user();
            $factId = $service->createManual($foreign, 'skill', 'Synthetic supported assertion.')->id;
            $overview = $this->actingAs($owner)->getJson('/api/v1/career')->assertOk()->json('data');
            $this->assertNotContains($factId, array_column($overview['facts'], 'id'));
            $this->assertNotContains($factId, $overview['claims'][0]['fact_ids']);
        } elseif ($scenario === 'unrelated') {
            $factId = $service->createManual($owner, 'skill', 'Unrelated synthetic assertion.')->id;
        } elseif (in_array($scenario, ['PENDING', 'REJECTED', 'DEPRECATED'], true)) {
            // Synthetic stale fixture: resolution must check current state, not a previous GET.
            $selected->forceFill(['status' => $scenario])->save();
        } elseif ($scenario === 'deleted') {
            $factId = (string) Str::ulid();
        } elseif ($scenario === 'superseded') {
            $service->supersede($owner, $selected, 'experience', 'Replacement synthetic assertion.');
        } elseif ($scenario === 'stale') {
            $claim->forceFill(['resolution_reason' => null])->save();
        }
        $beforeFacts = CareerFact::query()->get()->toArray();
        $this->actingAs($owner)->patchJson('/api/v1/career/claims/'.$claim->id.'/resolve',
            $scenario === 'missing' ? [] : ['career_fact_id' => $factId])->assertStatus($status);
        $this->assertNull($claim->fresh()->resolved_at);
        $this->assertNotSame(TruthGuard::PASS, $claim->fresh()->truth_status);
        $this->assertSame($beforeFacts, CareerFact::query()->get()->toArray());
    }

    public function test_missing_claim_foreign_claim_and_unauthenticated_resolution_are_hidden(): void
    {
        [$owner, $claim, $selected] = $this->ambiguity();
        $path = '/api/v1/career/claims/'.$claim->id.'/resolve';
        $this->patchJson($path, ['career_fact_id' => $selected->id])->assertUnauthorized();
        $this->actingAs($this->user())->patchJson($path, ['career_fact_id' => $selected->id])->assertNotFound();
        $this->actingAs($owner)->patchJson('/api/v1/career/claims/'.Str::ulid().'/resolve', ['career_fact_id' => $selected->id])->assertNotFound();
        $this->assertNull($claim->fresh()->resolved_at);
    }

    public function test_valid_selection_cannot_override_other_invalid_evidence(): void
    {
        [$owner, $claim, $selected] = $this->ambiguity();
        $other = CareerFact::query()->where('owner_id', $owner->id)->where('id', '!=', $selected->id)->sole();
        app(CareerFactService::class)->deprecate($owner, $other);
        $this->actingAs($owner)->patchJson('/api/v1/career/claims/'.$claim->id.'/resolve', ['career_fact_id' => $selected->id])
            ->assertOk()->assertJsonPath('data.truth_status', TruthGuard::BLOCK);
        $this->assertSame(TruthGuard::BLOCK, app(TruthGuard::class)->evaluate($claim->fresh()));
    }

    public function test_unresolved_ambiguous_claim_blocks_downstream_matching_until_explicit_resolution(): void
    {
        [$owner, $claim, $selected] = $this->ambiguity();
        $truthGuard = app(TruthGuard::class);
        $trustedQuery = app(TrustedCareerQuery::class);

        $this->assertSame(TruthGuard::USER_RESOLUTION_REQUIRED, $truthGuard->evaluate($claim));

        $beforeMatching = $trustedQuery->forMatching($owner);
        $this->assertEmpty($beforeMatching['claims'], 'Unresolved ambiguous claim must be blocked from downstream matching.');

        $this->actingAs($owner)->patchJson('/api/v1/career/claims/'.$claim->id.'/resolve', [
            'career_fact_id' => $selected->id,
        ])->assertOk()->assertJsonPath('data.truth_status', TruthGuard::PASS);

        $this->assertSame(TruthGuard::PASS, $truthGuard->evaluate($claim->fresh()));

        $afterMatching = $trustedQuery->forMatching($owner);
        $this->assertCount(1, $afterMatching['claims'], 'Explicitly resolved claim must unlock downstream matching.');
        $this->assertSame($claim->id, $afterMatching['claims'][0]->id);
    }
}
