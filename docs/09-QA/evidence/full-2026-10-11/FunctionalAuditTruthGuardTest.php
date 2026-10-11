<?php

namespace Tests\Feature;

use App\Models\CareerFact;
use App\Models\Claim;
use App\Models\User;
use App\Services\CareerFactService;
use App\Services\TruthGuard;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class FunctionalAuditTruthGuardTest extends TestCase
{
    use RefreshDatabase;

    /** @return array<string, array{string, string}> */
    public static function evidenceStates(): array
    {
        return [
            'confirmed' => [CareerFact::STATUS_CONFIRMED, TruthGuard::PASS],
            'pending' => [CareerFact::STATUS_PENDING, TruthGuard::BLOCK],
            'rejected' => [CareerFact::STATUS_REJECTED, TruthGuard::BLOCK],
            'deprecated' => [CareerFact::STATUS_DEPRECATED, TruthGuard::BLOCK],
        ];
    }

    #[DataProvider('evidenceStates')]
    public function test_existing_claim_cannot_retain_eligibility_without_confirmed_backing_evidence(string $status, string $expected): void
    {
        $user = User::query()->create([
            'email' => 'audit-truth@example.test',
            'password' => Hash::make('synthetic audit passphrase'),
        ]);
        $fact = app(CareerFactService::class)->createManual($user, 'skill', 'Built Laravel APIs.');
        $claim = Claim::query()->where('owner_id', $user->id)->sole();
        $this->assertSame(TruthGuard::PASS, app(TruthGuard::class)->evaluate($claim));

        // A corrupt/replayed status fixture must fail closed even with an existing evidence link.
        // This fixture bypasses lifecycle services only inside the disposable SQLite test database.
        $fact->forceFill(['status' => $status])->save();

        $this->assertSame($status, $fact->fresh()->status);
        $this->assertSame($expected, app(TruthGuard::class)->evaluate($claim->fresh()));
    }
}
