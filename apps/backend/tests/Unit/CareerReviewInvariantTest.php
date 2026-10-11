<?php

namespace Tests\Unit;

use App\Models\CareerFact;
use App\Models\Claim;
use Illuminate\Support\Collection;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Tests\Support\CareerReviewInvariant;

final class CareerReviewInvariantTest extends TestCase
{
    #[DataProvider('outcomes')]
    public function test_race_outcomes(string $winner, string $status, array $claims, bool $valid): void
    {
        $fact = new CareerFact(['owner_id' => 'owner', 'status' => $status, 'assertion_approved' => 'Synthetic assertion.']);
        $models = new Collection(array_map(fn (array $data) => new Claim($data), $claims));
        if (! $valid) {
            $this->expectException(RuntimeException::class);
        }
        CareerReviewInvariant::verify($fact, $models, 'owner', $winner);
        $this->assertTrue($valid);
    }

    public static function outcomes(): array
    {
        $claim = ['owner_id' => 'owner', 'truth_status' => 'PASS', 'statement' => 'Synthetic assertion.'];

        return [
            'confirm winner' => ['confirm', 'CONFIRMED', [$claim], true],
            'reject winner' => ['reject', 'REJECTED', [], true],
            'confirm without Claim' => ['confirm', 'CONFIRMED', [], false],
            'reject with Claim' => ['reject', 'REJECTED', [$claim], false],
            'wrong winner' => ['confirm', 'REJECTED', [], false],
            'incomplete execution' => ['confirm', 'PENDING', [], false],
            'duplicate Claims' => ['confirm', 'CONFIRMED', [$claim, $claim], false],
            'blocked Claim' => ['confirm', 'CONFIRMED', [array_replace($claim, ['truth_status' => 'BLOCK'])], false],
            'foreign Claim' => ['confirm', 'CONFIRMED', [array_replace($claim, ['owner_id' => 'foreign'])], false],
            'wrong statement' => ['confirm', 'CONFIRMED', [array_replace($claim, ['statement' => 'Unsupported.'])], false],
            'invalid action' => ['unknown', 'CONFIRMED', [$claim], false],
        ];
    }
}
