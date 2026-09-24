<?php

namespace App\Services;

use App\AI\Contracts\LlmProvider;
use App\AI\Data\LlmRequest;
use App\AI\Data\LlmResponse;
use App\AI\Data\ModelPolicy;
use App\AI\Exceptions\LlmProviderException;
use App\AI\RuntimeSkillRegistry;
use App\Models\ApplicationLlmRun;
use App\Models\ApplicationPreparation;
use App\Models\Claim;
use App\Models\User;

class ApplicationTruthGuard
{
    private const MAX_REVIEW_ITEMS = 14;

    private const MAX_SEGMENTS_PER_ITEM = 100;

    public function __construct(
        private readonly LlmProvider $provider,
        private readonly RuntimeSkillRegistry $skills,
        private readonly TruthGuard $claims,
    ) {}

    /** @param list<array{assertion: string, claim_ids: list<string>}> $proposedUsages
     * @param  array<string, Claim>  $availableClaims
     * @return array{status: string, usages: list<array{assertion: string, claim_ids: list<string>}>}
     */
    public function validate(User $user, ApplicationPreparation $preparation, string $content, array $proposedUsages, array $availableClaims): array
    {
        return $this->validateMany($user, $preparation, [
            'single' => ['content' => $content, 'proposed_usages' => $proposedUsages],
        ], $availableClaims)['single'];
    }

    /**
     * @param  array<int|string, mixed>  $candidates
     * @param  array<string, Claim>  $availableClaims
     * @return array<string, array{status: string, usages: list<array{assertion: string, claim_ids: list<string>}>}>
     */
    public function validateMany(User $user, ApplicationPreparation $preparation, array $candidates, array $availableClaims): array
    {
        $results = [];
        if ($candidates === [] || count($candidates) > self::MAX_REVIEW_ITEMS) {
            return $this->blockedResults(array_keys($candidates));
        }

        $reviewCandidates = [];
        foreach ($candidates as $itemId => $candidate) {
            if (! is_string($itemId) || trim($itemId) === ''
                || ! is_array($candidate)
                || ! is_string($candidate['content'] ?? null)
                || trim($candidate['content']) === ''
                || mb_strlen($candidate['content']) > 6000
                || ! is_array($candidate['proposed_usages'] ?? null)
                || ! array_is_list($candidate['proposed_usages'])) {
                $results[(string) $itemId] = $this->blocked();

                continue;
            }

            if (! $this->proposedUsagesAreTrusted($candidate['proposed_usages'], $availableClaims)) {
                $results[$itemId] = $this->blocked();

                continue;
            }

            /** @var array{content: string, proposed_usages: list<mixed>} $candidate */
            $reviewCandidates[$itemId] = [
                'content' => $candidate['content'],
                'proposed_usages' => $candidate['proposed_usages'],
            ];
        }

        if ($reviewCandidates === []) {
            return $results;
        }

        try {
            $skill = $this->skills->applicationTruthReview();
        } catch (\Throwable $exception) {
            throw new LlmProviderException(
                LlmProviderException::NOT_CONFIGURED,
                'The application Truth Guard is unavailable.',
                previous: $exception,
            );
        }

        $run = new ApplicationLlmRun;
        $run->forceFill([
            'owner_id' => $user->id,
            'preparation_id' => $preparation->id,
            'workflow' => 'application_truth_review',
            'skill_id' => $skill->id,
            'skill_version' => $skill->version,
            'prompt_version' => $skill->promptVersion,
            'model_policy' => $skill->modelPolicy,
            'status' => 'RUNNING',
            'retry_count' => ApplicationLlmRun::query()->where('owner_id', $user->id)->where('preparation_id', $preparation->id)->count(),
        ])->save();

        try {
            $response = $this->provider->generateStructured(new LlmRequest(
                trustedInstructions: $skill->trustedInstructions,
                untrustedSourceText: json_encode([
                    'candidate_items' => array_map(fn (string $itemId, array $candidate): array => [
                        'item_id' => $itemId,
                        'candidate_content' => $candidate['content'],
                        'proposed_claim_usages' => $candidate['proposed_usages'],
                    ], array_keys($reviewCandidates), array_values($reviewCandidates)),
                    'available_claims' => array_map(fn (Claim $claim): array => ['id' => (string) $claim->id, 'statement' => $claim->statement], array_values($availableClaims)),
                ], JSON_THROW_ON_ERROR),
                schema: $skill->outputSchema,
                modelPolicy: new ModelPolicy($skill->modelPolicy, true),
                schemaName: 'application_truth_review',
                untrustedDataLabel: 'UNTRUSTED APPLICATION CONTENT AND CLAIM DATA',
            ));
        } catch (LlmProviderException $exception) {
            $this->recordFailure($run, $exception);
            throw $exception;
        } catch (\Throwable $exception) {
            $normalized = new LlmProviderException(
                LlmProviderException::PROVIDER,
                'Application Truth Guard failed safely.',
                previous: $exception,
            );
            $this->recordFailure($run, $normalized);
            throw $normalized;
        }

        $reviewById = $this->reviewsById($response->output, array_keys($reviewCandidates));
        if ($reviewById === null) {
            foreach ($reviewCandidates as $itemId => $_candidate) {
                $results[$itemId] = $this->blocked();
            }
            $this->record($run, $response, 'BLOCK');

            return $results;
        }

        foreach ($reviewCandidates as $itemId => $candidate) {
            $results[$itemId] = $this->validateReview(
                $reviewById[$itemId],
                $candidate['content'],
                $candidate['proposed_usages'],
                $availableClaims,
            );
        }

        $statuses = array_column($results, 'status');
        $overallStatus = count(array_filter($statuses, fn (string $status): bool => $status !== TruthGuard::PASS)) === 0
            ? TruthGuard::PASS
            : TruthGuard::BLOCK;
        $this->record($run, $response, $overallStatus);

        return $results;
    }

    /** @param array<string, mixed> $output
     * @param  list<string>  $expectedIds
     * @return array<string, array<string, mixed>>|null
     */
    private function reviewsById(array $output, array $expectedIds): ?array
    {
        if (! $this->hasExactKeys($output, ['reviews'])
            || ! is_array($output['reviews'])
            || ! array_is_list($output['reviews'])
            || count($output['reviews']) !== count($expectedIds)) {
            return null;
        }

        $expected = array_fill_keys($expectedIds, true);
        $reviews = [];
        foreach ($output['reviews'] as $review) {
            if (! is_array($review)
                || ! is_string($review['item_id'] ?? null)
                || ! isset($expected[$review['item_id']])
                || isset($reviews[$review['item_id']])) {
                return null;
            }
            $reviews[$review['item_id']] = $review;
        }
        if (array_diff($expectedIds, array_keys($reviews)) !== []) {
            return null;
        }

        return $reviews;
    }

    /** @param array<string, mixed> $review
     * @param  list<mixed>  $proposedUsages
     * @param  array<string, Claim>  $availableClaims
     * @return array{status: string, usages: list<array{assertion: string, claim_ids: list<string>}>}
     */
    private function validateReview(array $review, string $content, array $proposedUsages, array $availableClaims): array
    {
        if (! $this->hasExactKeys($review, ['item_id', 'status', 'segments'])
            || ! is_string($review['status'] ?? null)
            || ! in_array($review['status'], [TruthGuard::PASS, TruthGuard::BLOCK], true)
            || ! is_array($review['segments'] ?? null)
            || ! array_is_list($review['segments'])
            || count($review['segments']) > self::MAX_SEGMENTS_PER_ITEM) {
            return $this->blocked();
        }

        if ($review['status'] !== TruthGuard::PASS) {
            return ['status' => $review['status'], 'usages' => []];
        }

        $usages = [];
        $coveredContent = '';
        foreach ($review['segments'] as $segment) {
            if (! is_array($segment)
                || ! $this->hasExactKeys($segment, ['text', 'kind', 'claim_ids'])
                || ! is_string($segment['text'] ?? null)
                || $segment['text'] === ''
                || ! is_string($segment['kind'] ?? null)
                || ! in_array($segment['kind'], ['FACTUAL', 'NON_FACTUAL'], true)
                || ! is_array($segment['claim_ids'] ?? null)
                || ! array_is_list($segment['claim_ids'])
                || count($segment['claim_ids']) > 8) {
                return $this->blocked();
            }

            $coveredContent .= $segment['text'];
            if (array_filter($segment['claim_ids'], fn (mixed $id): bool => ! is_string($id)) !== []) {
                return $this->blocked();
            }
            $claimIds = array_values(array_unique($segment['claim_ids']));
            if (($segment['kind'] === 'FACTUAL' && $claimIds === [])
                || ($segment['kind'] === 'NON_FACTUAL' && $claimIds !== [])) {
                return $this->blocked();
            }
            foreach ($claimIds as $claimId) {
                $claim = $availableClaims[$claimId] ?? null;
                if ($claim === null || $this->claims->evaluate($claim) !== TruthGuard::PASS
                    || ! hash_equals((string) $claim->statement, $segment['text'])) {
                    return $this->blocked();
                }
            }
            if ($segment['kind'] === 'FACTUAL') {
                $usages[] = ['assertion' => $segment['text'], 'claim_ids' => $claimIds];
            } elseif (preg_match('/^[\s\p{Z}\p{P}]*$/u', $segment['text']) !== 1) {
                // LLM classifications cannot turn arbitrary candidate wording into non-factual text.
                return $this->blocked();
            }
        }

        if ($usages === [] || ! hash_equals($content, $coveredContent)) {
            return $this->blocked();
        }

        foreach ($proposedUsages as $usage) {
            if (! is_array($usage) || ! $this->hasExactKeys($usage, ['assertion', 'claim_ids'])
                || ! is_string($usage['assertion'] ?? null)
                || ! is_array($usage['claim_ids'] ?? null) || ! array_is_list($usage['claim_ids'])) {
                return $this->blocked();
            }
            $reviewed = collect($usages)->first(fn (array $item): bool => hash_equals($item['assertion'], $usage['assertion']));
            if (! str_contains($content, $usage['assertion']) || $usage['claim_ids'] === []
                || $reviewed === null || array_diff($usage['claim_ids'], $reviewed['claim_ids']) !== []
                || array_diff($reviewed['claim_ids'], $usage['claim_ids']) !== []) {
                return $this->blocked();
            }
        }

        return ['status' => TruthGuard::PASS, 'usages' => $usages];
    }

    /** @param list<mixed> $usages
     * @param  array<string, Claim>  $availableClaims
     */
    private function proposedUsagesAreTrusted(array $usages, array $availableClaims): bool
    {
        foreach ($usages as $usage) {
            if (! is_array($usage)
                || ! $this->hasExactKeys($usage, ['assertion', 'claim_ids'])
                || ! is_string($usage['assertion'] ?? null)
                || trim($usage['assertion']) === ''
                || ! is_array($usage['claim_ids'] ?? null)
                || ! array_is_list($usage['claim_ids'])
                || $usage['claim_ids'] === []) {
                return false;
            }
            foreach ($usage['claim_ids'] as $claimId) {
                if (! is_string($claimId)
                    || ! isset($availableClaims[$claimId])
                    || $this->claims->evaluate($availableClaims[$claimId]) !== TruthGuard::PASS) {
                    return false;
                }
            }
        }

        return true;
    }

    /** @param array<string, mixed> $data
     * @param  list<string>  $expected
     */
    private function hasExactKeys(array $data, array $expected): bool
    {
        return array_diff(array_keys($data), $expected) === []
            && array_diff($expected, array_keys($data)) === [];
    }

    /** @param list<string|int> $ids
     * @return array<string, array{status: string, usages: list<array{assertion: string, claim_ids: list<string>}>}>
     */
    private function blockedResults(array $ids): array
    {
        $results = [];
        foreach ($ids as $id) {
            $results[(string) $id] = $this->blocked();
        }

        return $results;
    }

    /** @return array{status: string, usages: list<array{assertion: string, claim_ids: list<string>}>} */
    private function blocked(): array
    {
        return ['status' => TruthGuard::BLOCK, 'usages' => []];
    }

    private function recordFailure(ApplicationLlmRun $run, LlmProviderException $exception): void
    {
        $run->forceFill([
            'provider' => $exception->providerName,
            'model' => $exception->resolvedModel,
            'provider_request_id' => $exception->providerRequestId,
            'status' => 'FAILED',
            'input_tokens' => $exception->inputTokens,
            'output_tokens' => $exception->outputTokens,
            'latency_ms' => $exception->latencyMs,
            'estimated_cost_micros' => $exception->estimatedCostMicros,
            'validation_result' => 'NOT_VALIDATED',
            'error_category' => $exception->category,
        ])->save();
    }

    private function record(ApplicationLlmRun $run, LlmResponse $response, string $status): void
    {
        $run->forceFill([
            'provider' => $response->provider,
            'model' => $response->model,
            'provider_request_id' => $response->providerRequestId,
            'status' => $status === TruthGuard::PASS ? 'COMPLETED' : 'FAILED',
            'input_tokens' => $response->inputTokens,
            'output_tokens' => $response->outputTokens,
            'latency_ms' => $response->latencyMs,
            'estimated_cost_micros' => $response->estimatedCostMicros,
            'validation_result' => $status,
            'error_category' => $status === TruthGuard::PASS ? null : $status,
        ])->save();
    }
}
