<?php

namespace App\Services;

use App\AI\Contracts\LlmProvider;
use App\AI\Data\LlmRequest;
use App\AI\Data\LlmResponse;
use App\AI\Data\ModelPolicy;
use App\AI\Data\RuntimeSkillDefinition;
use App\AI\Exceptions\LlmProviderException;
use App\AI\RuntimeSkillRegistry;
use App\Models\ApplicationApprovalEvent;
use App\Models\ApplicationClaimUsage;
use App\Models\ApplicationDraftItem;
use App\Models\ApplicationDraftRevision;
use App\Models\ApplicationLlmRun;
use App\Models\ApplicationPreparation;
use App\Models\CareerFact;
use App\Models\Claim;
use App\Models\ClaimEvidence;
use App\Models\User;
use App\Models\Vacancy;
use App\Models\VacancyAnalysis;
use App\Models\VacancySnapshot;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ApplicationPreparationService
{
    public function __construct(
        private readonly LlmProvider $provider,
        private readonly RuntimeSkillRegistry $skills,
        private readonly ApplicationContextBuilder $contextBuilder,
        private readonly ApplicationTruthGuard $truthGuard,
        private readonly TruthGuard $claims,
        private readonly VacancyMatchingService $matching,
        private readonly DatabaseOwnerContext $ownerContext,
    ) {}

    public function open(User $user, string $vacancyId): ApplicationPreparation
    {
        return $this->ownerContext->run((string) $user->id, function () use ($user, $vacancyId): ApplicationPreparation {
            $vacancy = Vacancy::query()->where('owner_id', $user->id)->findOrFail($vacancyId);
            if ($vacancy->analysis_status !== Vacancy::STATUS_COMPLETED) {
                throw ValidationException::withMessages(['vacancy' => 'A completed vacancy analysis is required.']);
            }
            $snapshot = VacancySnapshot::query()->where('owner_id', $user->id)->where('vacancy_id', $vacancy->id)
                ->orderByDesc('version')->orderByDesc('id')->firstOrFail();
            $signature = $this->matching->careerSignature($user);
            $analysis = VacancyAnalysis::query()->where('owner_id', $user->id)->where('vacancy_id', $vacancy->id)
                ->where('vacancy_snapshot_id', $snapshot->id)->forCareerSignature($signature)->deterministicLatest()->first();
            if ($analysis === null) {
                throw ValidationException::withMessages(['vacancy' => 'Reanalyze the vacancy against the current confirmed Career Facts first.']);
            }

            try {
                $attributes = [
                    'owner_id' => $user->id,
                    'vacancy_snapshot_id' => $snapshot->id,
                    'career_signature' => $signature,
                ];
                $preparation = ApplicationPreparation::query()->where($attributes)->first();
                if ($preparation !== null) {
                    return $preparation;
                }
                $preparation = new ApplicationPreparation;
                $preparation->forceFill([
                    ...$attributes,
                    'vacancy_id' => $vacancy->id,
                    'vacancy_analysis_id' => $analysis->id,
                    'status' => ApplicationPreparation::STATUS_DRAFT,
                ])->save();

                return $preparation;
            } catch (QueryException) {
                return ApplicationPreparation::query()->where('owner_id', $user->id)
                    ->where('vacancy_snapshot_id', $snapshot->id)->where('career_signature', $signature)->firstOrFail();
            }
        });
    }

    /** @return array<string, mixed> */
    public function generate(User $user, ApplicationPreparation $preparation): array
    {
        $this->assertOwner($user, $preparation);
        $this->assertCurrent($user, $preparation);
        $existing = $this->items($user, $preparation);
        if ($existing !== []) {
            return $this->resource($user, $preparation);
        }

        $snapshot = VacancySnapshot::query()->where('owner_id', $user->id)
            ->where('vacancy_id', $preparation->vacancy_id)->findOrFail($preparation->vacancy_snapshot_id);
        $analysis = VacancyAnalysis::query()->where('owner_id', $user->id)->findOrFail($preparation->vacancy_analysis_id);
        $context = $this->contextBuilder->build($user, $snapshot, $analysis);
        $allowedClaims = $this->claimMap($user, $context['claims']);
        try {
            $skill = $this->skills->applicationDraftGeneration();
        } catch (\Throwable $exception) {
            throw new LlmProviderException(
                LlmProviderException::NOT_CONFIGURED,
                'Application draft generation is unavailable.',
                previous: $exception,
            );
        }
        $run = $this->newRun($user, $preparation, 'application_draft_generation', $skill);
        try {
            $response = $this->provider->generateStructured(new LlmRequest(
                trustedInstructions: $skill->trustedInstructions,
                untrustedSourceText: json_encode([
                    'vacancy' => [
                        'title' => $this->ownerScopedVacancyTitle($user, $preparation),
                        'company' => $this->ownerScopedVacancyCompany($user, $preparation),
                        'requirements' => $context['requirements'],
                    ],
                    'confirmed_claims' => $context['claims'],
                ], JSON_THROW_ON_ERROR),
                schema: $skill->outputSchema,
                modelPolicy: new ModelPolicy($skill->modelPolicy, true),
                schemaName: 'application_drafts',
                untrustedDataLabel: 'UNTRUSTED VACANCY AND CONFIRMED CAREER DATA',
            ));
            $items = $this->validateGeneration($response->output, $context, $allowedClaims);
            $reviewCandidates = [];
            foreach ($items as $index => $item) {
                $reviewCandidates['generation-'.$index] = [
                    'content' => $item['content'],
                    'proposed_usages' => $item['claim_usages'],
                ];
            }
            $reviews = $this->truthGuard->validateMany($user, $preparation, $reviewCandidates, $allowedClaims);
            foreach ($items as $index => &$item) {
                $guard = $reviews['generation-'.$index] ?? ['status' => TruthGuard::BLOCK, 'usages' => []];
                $item['claim_usages'] = $guard['usages'];
                $item['validation_result'] = $guard['status'];
                $item['status'] = $guard['status'] === TruthGuard::PASS ? 'DRAFT' : 'BLOCKED';
            }
            unset($item);
            $generationStatus = collect($items)->every(fn (array $item): bool => $item['validation_result'] === TruthGuard::PASS)
                ? 'PASS' : 'BLOCK';
            $this->recordRun($run, $response, $generationStatus);

            DB::transaction(function () use ($user, $preparation, $items, $allowedClaims): void {
                $locked = ApplicationPreparation::query()->where('owner_id', $user->id)->lockForUpdate()->findOrFail($preparation->id);
                $this->assertCurrent($user, $locked);
                if (ApplicationDraftItem::query()->where('owner_id', $user->id)->where('preparation_id', $locked->id)->exists()) {
                    return;
                }
                $this->lockClaims($user, array_keys($allowedClaims));
                foreach ($items as $item) {
                    $draft = new ApplicationDraftItem;
                    $draft->forceFill([
                        'owner_id' => $user->id,
                        'preparation_id' => $locked->id,
                        'vacancy_requirement_id' => $item['vacancy_requirement_id'],
                        'kind' => $item['kind'],
                        'variant' => $item['variant'],
                        'section' => $item['section'],
                        'before_text' => $item['before_text'],
                        'content' => $item['content'],
                        'reason' => $item['reason'],
                        'risk' => $item['risk'],
                        'status' => $item['status'],
                        'validation_result' => $item['validation_result'],
                        'revision_number' => 1,
                        'validated_content_hash' => $item['validation_result'] === TruthGuard::PASS ? hash('sha256', $item['content']) : null,
                        'validated_at' => $item['validation_result'] === TruthGuard::PASS ? now() : null,
                    ])->save();
                    $this->replaceUsages($user, $draft, $item['claim_usages'], $allowedClaims);
                    $this->recordRevision($user, $draft, 'GENERATED', $item['validation_result'], $item['claim_usages']);
                }
            });
        } catch (\Throwable $exception) {
            if ($run->status === 'RUNNING') {
                $run->forceFill(['status' => 'FAILED', 'validation_result' => 'BLOCK', 'error_category' => $exception instanceof LlmProviderException ? $exception->category : 'OUTPUT_REJECTED'])->save();
            }
            if ($exception instanceof ValidationException || $exception instanceof LlmProviderException) {
                throw $exception;
            }
            throw new LlmProviderException(LlmProviderException::PROVIDER, 'Application draft generation failed safely.');
        }

        return $this->resource($user, $preparation);
    }

    /** @return array<string, mixed> */
    public function edit(User $user, ApplicationDraftItem $item, string $content): array
    {
        $this->assertOwner($user, $item);
        $preparation = ApplicationPreparation::query()->where('owner_id', $user->id)->findOrFail($item->preparation_id);
        $this->assertCurrent($user, $preparation);
        if (in_array($item->status, ['REJECTED', 'APPROVED'], true)) {
            throw ValidationException::withMessages(['action' => 'Rejected or approved content cannot be edited.']);
        }
        $available = $this->linkedClaims($user, $item);
        $guard = $this->truthGuard->validate($user, $preparation, $content, [], $available);
        DB::transaction(function () use ($user, $preparation, $item, $content, $guard, $available): void {
            $lockedPreparation = ApplicationPreparation::query()->where('owner_id', $user->id)->lockForUpdate()->findOrFail($preparation->id);
            $locked = ApplicationDraftItem::query()->where('owner_id', $user->id)->lockForUpdate()->findOrFail($item->id);
            if (! hash_equals(hash('sha256', $item->content), hash('sha256', $locked->content))) {
                throw ValidationException::withMessages(['content' => 'This draft changed during review. Reload before editing again.']);
            }
            if ($locked->status !== $item->status || ! in_array($locked->status, ['DRAFT', 'ACCEPTED', 'BLOCKED'], true)) {
                throw ValidationException::withMessages(['action' => 'This draft changed state during review. Reload before editing again.']);
            }
            $this->assertCurrent($user, $lockedPreparation);
            $this->lockClaims($user, array_keys($available));
            $revisionNumber = (int) $locked->revision_number + 1;
            $locked->forceFill([
                'content' => trim($content),
                'status' => $guard['status'] === TruthGuard::PASS ? 'DRAFT' : 'BLOCKED',
                'validation_result' => $guard['status'],
                'revision_number' => $revisionNumber,
                'validated_content_hash' => $guard['status'] === TruthGuard::PASS ? hash('sha256', trim($content)) : null,
                'validated_at' => now(),
            ])->save();
            $this->replaceUsages($user, $locked, $guard['usages'], $available);
            $this->recordRevision($user, $locked, 'EDITED', $guard['status'], $guard['usages']);
            $this->recordAction($user, $locked, 'EDITED', $guard['status']);
            $lockedPreparation->forceFill(['status' => ApplicationPreparation::STATUS_DRAFT])->save();
        });

        return $this->resource($user, $preparation);
    }

    /** @return array<string, mixed> */
    public function decide(User $user, ApplicationDraftItem $item, string $action): array
    {
        $this->assertOwner($user, $item);
        $preparation = ApplicationPreparation::query()->where('owner_id', $user->id)->findOrFail($item->preparation_id);
        $this->assertCurrent($user, $preparation);
        if ($action === 'reject') {
            DB::transaction(function () use ($user, $item, $preparation): void {
                $lockedPreparation = ApplicationPreparation::query()->where('owner_id', $user->id)->lockForUpdate()->findOrFail($preparation->id);
                $locked = ApplicationDraftItem::query()->where('owner_id', $user->id)->lockForUpdate()->findOrFail($item->id);
                if (! hash_equals(hash('sha256', $item->content), hash('sha256', $locked->content))) {
                    throw ValidationException::withMessages(['content' => 'This draft changed during review. Reload before rejecting it.']);
                }
                if ($locked->status !== $item->status || ! in_array($locked->status, ['DRAFT', 'ACCEPTED', 'BLOCKED'], true)) {
                    throw ValidationException::withMessages(['action' => 'This draft changed state during review. Reload before rejecting it.']);
                }
                $this->assertCurrent($user, $lockedPreparation);
                $locked->forceFill(['status' => 'REJECTED', 'validation_result' => 'NOT_VALIDATED', 'validated_content_hash' => null, 'validated_at' => null])->save();
                $this->recordAction($user, $locked, 'REJECTED', 'BLOCK');
                $lockedPreparation->forceFill(['status' => ApplicationPreparation::STATUS_DRAFT])->save();
            });

            return $this->resource($user, $preparation);
        }
        if ($action !== 'accept') {
            throw ValidationException::withMessages(['action' => 'The requested review action is not supported.']);
        }
        if (in_array($item->status, ['REJECTED', 'APPROVED'], true)) {
            throw ValidationException::withMessages(['action' => 'Rejected or approved content cannot be accepted again.']);
        }

        $available = $this->linkedClaims($user, $item);
        $guard = $this->truthGuard->validate($user, $preparation, $item->content, $this->storedUsages($user, $item), $available);
        DB::transaction(function () use ($user, $preparation, $item, $guard, $available): void {
            $lockedPreparation = ApplicationPreparation::query()->where('owner_id', $user->id)->lockForUpdate()->findOrFail($preparation->id);
            $locked = ApplicationDraftItem::query()->where('owner_id', $user->id)->lockForUpdate()->findOrFail($item->id);
            if (! hash_equals(hash('sha256', $item->content), hash('sha256', $locked->content))) {
                throw ValidationException::withMessages(['content' => 'This draft changed during review. Reload before accepting it.']);
            }
            if ($locked->status !== $item->status || ! in_array($locked->status, ['DRAFT', 'ACCEPTED', 'BLOCKED'], true)) {
                throw ValidationException::withMessages(['action' => 'This draft changed state during review. Reload before accepting it.']);
            }
            $this->assertCurrent($user, $lockedPreparation);
            $this->lockClaims($user, array_keys($available));
            $locked->forceFill([
                'status' => $guard['status'] === TruthGuard::PASS ? 'ACCEPTED' : 'BLOCKED',
                'validation_result' => $guard['status'],
                'validated_content_hash' => $guard['status'] === TruthGuard::PASS ? hash('sha256', $locked->content) : null,
                'validated_at' => now(),
            ])->save();
            $this->replaceUsages($user, $locked, $guard['usages'], $available);
            $this->recordAction($user, $locked, 'ACCEPTED', $guard['status']);
        });

        return $this->resource($user, $preparation);
    }

    /** @return array<string, mixed> */
    public function approve(User $user, ApplicationDraftItem $item): array
    {
        $this->assertOwner($user, $item);
        $preparation = ApplicationPreparation::query()->where('owner_id', $user->id)->findOrFail($item->preparation_id);
        $this->assertCurrent($user, $preparation);
        if ($item->status !== 'ACCEPTED') {
            throw ValidationException::withMessages(['approval' => 'Accept and validate this draft before approving it.']);
        }
        $available = $this->linkedClaims($user, $item);
        $guard = $this->truthGuard->validate($user, $preparation, $item->content, $this->storedUsages($user, $item), $available);
        $hash = hash('sha256', $item->content);
        if ($guard['status'] !== TruthGuard::PASS
            || $item->validation_result !== 'PASS'
            || ! hash_equals((string) $item->validated_content_hash, $hash)) {
            DB::transaction(function () use ($user, $item, $hash, $guard): void {
                ApplicationPreparation::query()->where('owner_id', $user->id)->lockForUpdate()->findOrFail($item->preparation_id);
                $lockedItem = ApplicationDraftItem::query()->where('owner_id', $user->id)->lockForUpdate()->findOrFail($item->id);
                if ($lockedItem->status === 'ACCEPTED' && hash_equals($hash, hash('sha256', $lockedItem->content))) {
                    $lockedItem->forceFill([
                        'status' => 'BLOCKED', 'validation_result' => $guard['status'],
                        'validated_content_hash' => null, 'validated_at' => now(),
                    ])->save();
                }
            });
            throw ValidationException::withMessages(['approval' => 'Current content or evidence did not pass Truth Guard.']);
        }

        $approved = DB::transaction(function () use ($user, $item, $preparation, $hash, $available): bool {
            $lockedPreparation = ApplicationPreparation::query()->where('owner_id', $user->id)->lockForUpdate()->findOrFail($preparation->id);
            $lockedItem = ApplicationDraftItem::query()->where('owner_id', $user->id)->lockForUpdate()->findOrFail($item->id);
            $this->lockClaims($user, array_keys($available));
            $currentHash = hash('sha256', $lockedItem->content);
            $claimsStillValid = collect($available)->every(fn (Claim $claim): bool => $this->claims->evaluate($claim) === TruthGuard::PASS);
            $isValid = $lockedItem->status === 'ACCEPTED'
                && $lockedItem->validation_result === 'PASS'
                && hash_equals((string) $lockedItem->validated_content_hash, $currentHash)
                && hash_equals($hash, $currentHash)
                && $claimsStillValid;
            try {
                $this->assertCurrent($user, $lockedPreparation);
            } catch (ValidationException) {
                $isValid = false;
            }
            if (! $isValid) {
                if ($lockedItem->status === 'ACCEPTED') {
                    $lockedItem->forceFill(['status' => 'BLOCKED', 'validation_result' => 'BLOCK', 'validated_content_hash' => null, 'validated_at' => now()])->save();
                }

                return false;
            }

            $lockedItem->forceFill(['status' => 'APPROVED'])->save();
            $this->recordAction($user, $lockedItem, 'APPROVED', 'PASS');
            $lockedPreparation->forceFill(['status' => ApplicationPreparation::STATUS_APPROVED])->save();

            return true;
        });

        if (! $approved) {
            return $this->resource($user, $preparation);
        }

        return $this->resource($user, $preparation);
    }

    /** @return array<string, mixed> */
    public function resource(User $user, ApplicationPreparation $preparation): array
    {
        $this->assertOwner($user, $preparation);
        $items = ApplicationDraftItem::query()->where('owner_id', $user->id)->where('preparation_id', $preparation->id)->orderBy('created_at')->get();
        $revisions = ApplicationDraftRevision::query()->where('owner_id', $user->id)
            ->whereIn('draft_item_id', $items->pluck('id'))->orderBy('draft_item_id')->orderBy('revision_number')->get()
            ->groupBy('draft_item_id');

        return [
            'id' => (string) $preparation->id,
            'vacancy_id' => (string) $preparation->vacancy_id,
            'status' => $preparation->status,
            'stale' => $this->isStale($user, $preparation),
            'items' => $items->map(fn (ApplicationDraftItem $item): array => [
                'id' => (string) $item->id,
                'kind' => $item->kind,
                'variant' => $item->variant,
                'section' => $item->section,
                'before' => $item->before_text,
                'content' => $item->content,
                'reason' => $item->reason,
                'risk' => $item->risk,
                'status' => $item->status,
                'validation_result' => $item->validation_result,
                'revision_number' => (int) $item->revision_number,
                'claim_usages' => $this->presentUsages($user, $item),
                'revisions' => ($revisions->get($item->id) ?? collect())->map(fn (ApplicationDraftRevision $revision): array => [
                    'revision_number' => (int) $revision->revision_number,
                    'action' => $revision->action,
                    'content' => $revision->content,
                    'content_hash' => $revision->content_hash,
                    'validation_result' => $revision->validation_result,
                    'claim_usages' => $revision->claim_usages,
                    'actor_user_id' => (string) $revision->actor_user_id,
                    'created_at' => $revision->created_at,
                ])->all(),
                'approvals' => ApplicationApprovalEvent::query()->where('owner_id', $user->id)->where('draft_item_id', $item->id)
                    ->orderBy('created_at')->orderBy('id')->get(['action', 'revision_number', 'content_hash', 'validation_result', 'created_at']),
            ])->all(),
        ];
    }

    /** @param array<string, mixed> $output
     * @param  array{requirements: list<array{id: string, dimension: string, importance: string, label: string, source_excerpt: string}>, claims: list<array<string, mixed>>, career_signature: string}  $context
     * @param  array<string, Claim>  $allowedClaims
     * @return list<array{kind: string, variant: ?string, section: ?string, before_text: ?string, content: string, reason: ?string, risk: ?string, vacancy_requirement_id: ?string, claim_usages: list<array{assertion: string, claim_ids: list<string>}>}>
     */
    private function validateGeneration(array $output, array $context, array $allowedClaims): array
    {
        if (! $this->hasExactKeys($output, ['recommendations', 'short_cover', 'standard_cover'])
            || ! is_array($output['recommendations']) || ! array_is_list($output['recommendations'])
            || count($output['recommendations']) > 12
            || ! is_array($output['short_cover']) || ! is_array($output['standard_cover'])) {
            throw ValidationException::withMessages(['generation' => 'Generated content failed schema validation.']);
        }

        $requirementIds = array_fill_keys(array_column($context['requirements'], 'id'), true);
        $items = [];
        $seenRequirements = [];
        foreach ($output['recommendations'] as $recommendation) {
            if (! is_array($recommendation) || ! $this->hasExactKeys($recommendation, ['requirement_id', 'section', 'before', 'after', 'reason', 'risk', 'claim_usages'])
                || ! is_string($recommendation['requirement_id'] ?? null)
                || ! isset($requirementIds[$recommendation['requirement_id']])
                || isset($seenRequirements[$recommendation['requirement_id']])
                || ! is_string($recommendation['section']) || trim($recommendation['section']) === ''
                || mb_strlen(trim($recommendation['section'])) > 120
                || ! is_string($recommendation['before']) || mb_strlen(trim($recommendation['before'])) > 6000
                || ! is_string($recommendation['after']) || trim($recommendation['after']) === '' || mb_strlen(trim($recommendation['after'])) > 6000
                || ! is_string($recommendation['reason']) || trim($recommendation['reason']) === '' || mb_strlen(trim($recommendation['reason'])) > 1000
                || ! is_string($recommendation['risk']) || trim($recommendation['risk']) === '' || mb_strlen(trim($recommendation['risk'])) > 1000) {
                throw ValidationException::withMessages(['generation' => 'Generated recommendation failed semantic boundary validation.']);
            }
            $seenRequirements[$recommendation['requirement_id']] = true;
            $usages = $this->validateUsages($recommendation['claim_usages'], $allowedClaims, $recommendation['after']);
            if ($usages === []) {
                throw ValidationException::withMessages(['generation' => 'A resume recommendation must trace to confirmed Claims.']);
            }
            $beforeIsClaim = collect($usages)->contains(fn (array $usage): bool => collect($usage['claim_ids'])->contains(fn (string $id): bool => hash_equals((string) $allowedClaims[$id]->statement, trim($recommendation['before']))));
            if (! $beforeIsClaim) {
                throw ValidationException::withMessages(['generation' => 'The recommendation Before value must be supported by an existing confirmed Claim.']);
            }
            $requirement = collect($context['requirements'])->firstWhere('id', $recommendation['requirement_id']);
            $items[] = [
                'kind' => ApplicationDraftItem::KIND_RECOMMENDATION,
                'variant' => null,
                'section' => trim($recommendation['section']),
                'before_text' => trim($recommendation['before']),
                'content' => trim($recommendation['after']),
                // These visible explanations are guidance, not candidate facts; do not trust free-form model prose here.
                'reason' => 'Review the supported Claim against this vacancy requirement.',
                'risk' => 'Keep candidate wording within the confirmed Claim.',
                'vacancy_requirement_id' => $requirement['id'],
                'claim_usages' => $usages,
            ];
        }
        foreach (['SHORT' => 'short_cover', 'STANDARD' => 'standard_cover'] as $variant => $key) {
            $cover = $output[$key];
            if (! $this->hasExactKeys($cover, ['content', 'claim_usages'])
                || ! is_string($cover['content']) || trim($cover['content']) === '' || mb_strlen(trim($cover['content'])) > 6000) {
                throw ValidationException::withMessages(['generation' => 'Cover draft failed schema validation.']);
            }
            $usages = $this->validateUsages($cover['claim_usages'], $allowedClaims, $cover['content']);
            $items[] = [
                'kind' => ApplicationDraftItem::KIND_COVER,
                'variant' => $variant,
                'section' => null,
                'before_text' => null,
                'content' => trim($cover['content']),
                'reason' => null,
                'risk' => null,
                'vacancy_requirement_id' => null,
                'claim_usages' => $usages,
            ];
        }

        return $items;
    }

    /** @param array<string, Claim> $allowedClaims
     * @return list<array{assertion: string, claim_ids: list<string>}>
     */
    private function validateUsages(mixed $usages, array $allowedClaims, string $content): array
    {
        if (! is_array($usages) || ! array_is_list($usages) || count($usages) > 30) {
            throw ValidationException::withMessages(['generation' => 'Claim provenance failed schema validation.']);
        }
        $result = [];
        foreach ($usages as $usage) {
            if (! is_array($usage) || ! $this->hasExactKeys($usage, ['assertion', 'claim_ids'])
                || ! is_string($usage['assertion']) || trim($usage['assertion']) === ''
                || mb_strlen(trim($usage['assertion'])) > 6000
                || ! str_contains($content, $usage['assertion'])
                || ! is_array($usage['claim_ids']) || ! array_is_list($usage['claim_ids']) || $usage['claim_ids'] === [] || count($usage['claim_ids']) > 8) {
                throw ValidationException::withMessages(['generation' => 'Candidate assertion provenance is missing or invalid.']);
            }
            if (array_filter($usage['claim_ids'], fn (mixed $id): bool => ! is_string($id)) !== []) {
                throw ValidationException::withMessages(['generation' => 'Candidate assertion provenance is missing or invalid.']);
            }
            $ids = array_values(array_unique($usage['claim_ids']));
            foreach ($ids as $id) {
                if (! isset($allowedClaims[$id])) {
                    throw ValidationException::withMessages(['generation' => 'Cross-owner or untrusted Claim provenance was rejected.']);
                }
            }
            $result[] = ['assertion' => trim($usage['assertion']), 'claim_ids' => $ids];
        }

        return $result;
    }

    /** @param array<string, mixed> $value
     * @param  list<string>  $expected
     */
    private function hasExactKeys(array $value, array $expected): bool
    {
        return array_diff(array_keys($value), $expected) === []
            && array_diff($expected, array_keys($value)) === [];
    }

    /** @param list<array{assertion: string, claim_ids: list<string>}> $usages
     * @param  array<string, Claim>  $claims
     */
    private function replaceUsages(User $user, ApplicationDraftItem $item, array $usages, array $claims): void
    {
        ApplicationClaimUsage::query()->where('owner_id', $user->id)->where('draft_item_id', $item->id)->delete();
        foreach ($usages as $usage) {
            foreach ($usage['claim_ids'] as $claimId) {
                if (! isset($claims[$claimId]) || $this->claims->evaluate($claims[$claimId]) !== TruthGuard::PASS) {
                    throw ValidationException::withMessages(['claim_usages' => 'Only current same-owner PASS Claims may support candidate content.']);
                }
                $usageRow = new ApplicationClaimUsage;
                $usageRow->forceFill([
                    'owner_id' => $user->id,
                    'draft_item_id' => $item->id,
                    'claim_id' => $claimId,
                    'assertion_text' => $usage['assertion'],
                ])->save();
            }
        }
    }

    /** Must run inside the transaction that commits a draft or approval. */
    /** @param list<string> $claimIds
     * @return array<string, Claim>
     */
    private function lockClaims(User $user, array $claimIds): array
    {
        if ($claimIds === []) {
            return [];
        }
        sort($claimIds);
        $claims = Claim::query()->where('owner_id', $user->id)->whereIn('id', $claimIds)->orderBy('id')->lockForUpdate()->get()->keyBy(fn (Claim $claim): string => (string) $claim->id);
        $factIds = ClaimEvidence::query()->where('owner_id', $user->id)->whereIn('claim_id', $claimIds)->orderBy('career_fact_id')->pluck('career_fact_id')->map(fn ($id): string => (string) $id)->unique()->all();
        if ($factIds !== []) {
            CareerFact::query()->where('owner_id', $user->id)->whereIn('id', $factIds)->orderBy('id')->lockForUpdate()->get();
        }

        return $claims->all();
    }

    /** @return array<string, Claim> */
    private function linkedClaims(User $user, ApplicationDraftItem $item): array
    {
        $preparation = ApplicationPreparation::query()->where('owner_id', $user->id)->findOrFail($item->preparation_id);
        $context = $this->contextBuilder->build(
            $user,
            VacancySnapshot::query()->where('owner_id', $user->id)->findOrFail($preparation->vacancy_snapshot_id),
            VacancyAnalysis::query()->where('owner_id', $user->id)->findOrFail($preparation->vacancy_analysis_id),
        );

        return $this->claimMap($user, $context['claims']);
    }

    /** @param list<array<string, mixed>> $claims
     * @return array<string, Claim>
     */
    private function claimMap(User $user, array $claims): array
    {
        $map = [];
        foreach ($claims as $entry) {
            $claim = Claim::query()->where('owner_id', $user->id)->whereKey($entry['id'])->first();
            if ($claim !== null && $this->claims->evaluate($claim) === TruthGuard::PASS) {
                $map[(string) $claim->id] = $claim;
            }
        }

        return $map;
    }

    /** @return list<array{assertion: string, claim_ids: list<string>}> */
    private function storedUsages(User $user, ApplicationDraftItem $item): array
    {
        return ApplicationClaimUsage::query()->where('owner_id', $user->id)->where('draft_item_id', $item->id)
            ->orderBy('created_at')->get()->groupBy('assertion_text')
            ->map(fn ($rows, string $assertion): array => ['assertion' => $assertion, 'claim_ids' => $rows->pluck('claim_id')->map(fn ($id): string => (string) $id)->unique()->values()->all()])
            ->values()->all();
    }

    /** @return list<array{assertion: string, claim_ids: list<string>, supporting_claims: list<array<string, mixed>>}> */
    private function presentUsages(User $user, ApplicationDraftItem $item): array
    {
        return array_map(function (array $usage) use ($user): array {
            $claims = [];
            foreach ($usage['claim_ids'] as $claimId) {
                $claim = Claim::query()->where('owner_id', $user->id)->find($claimId);
                if ($claim === null) {
                    continue;
                }
                $facts = ClaimEvidence::query()->where('owner_id', $user->id)->where('claim_id', $claim->id)->get()
                    ->map(function (ClaimEvidence $evidence) use ($user): ?array {
                        $fact = CareerFact::query()->where('owner_id', $user->id)->find($evidence->career_fact_id);
                        if ($fact === null) {
                            return null;
                        }

                        return [
                            'id' => (string) $fact->id,
                            'statement' => $fact->approvedAssertion(),
                            'status' => $fact->status,
                            'provenance_type' => $fact->provenance_type,
                            'source_excerpt' => $fact->source_excerpt,
                        ];
                    })->filter()->values()->all();
                $claims[] = ['id' => (string) $claim->id, 'statement' => $claim->statement, 'truth_status' => $claim->truth_status, 'career_facts' => $facts];
            }

            return [...$usage, 'supporting_claims' => $claims];
        }, $this->storedUsages($user, $item));
    }

    /** @return list<ApplicationDraftItem> */
    private function items(User $user, ApplicationPreparation $preparation): array
    {
        return ApplicationDraftItem::query()->where('owner_id', $user->id)->where('preparation_id', $preparation->id)->get()->all();
    }

    private function newRun(User $user, ApplicationPreparation $preparation, string $workflow, RuntimeSkillDefinition $skill): ApplicationLlmRun
    {
        $run = new ApplicationLlmRun;
        $run->forceFill([
            'owner_id' => $user->id, 'preparation_id' => $preparation->id, 'workflow' => $workflow,
            'skill_id' => $skill->id, 'skill_version' => $skill->version, 'prompt_version' => $skill->promptVersion,
            'model_policy' => $skill->modelPolicy, 'status' => 'RUNNING',
            'retry_count' => ApplicationLlmRun::query()->where('owner_id', $user->id)->where('preparation_id', $preparation->id)->count(),
        ])->save();

        return $run;
    }

    private function recordRun(ApplicationLlmRun $run, LlmResponse $response, string $status): void
    {
        $run->forceFill([
            'provider' => $response->provider, 'model' => $response->model, 'provider_request_id' => $response->providerRequestId,
            'status' => $status === 'PASS' ? 'COMPLETED' : 'FAILED', 'input_tokens' => $response->inputTokens,
            'output_tokens' => $response->outputTokens, 'latency_ms' => $response->latencyMs,
            'estimated_cost_micros' => $response->estimatedCostMicros, 'validation_result' => $status,
            'error_category' => $status === 'PASS' ? null : $status,
        ])->save();
    }

    private function recordAction(User $user, ApplicationDraftItem $item, string $action, string $validationResult): void
    {
        $event = new ApplicationApprovalEvent;
        $event->forceFill([
            'owner_id' => $user->id,
            'draft_item_id' => $item->id,
            'actor_user_id' => $user->id,
            'action' => $action,
            'revision_number' => $item->revision_number,
            'content_hash' => hash('sha256', $item->content),
            'validation_result' => $validationResult,
            'created_at' => now(),
        ])->save();
    }

    /** @param list<array{assertion: string, claim_ids: list<string>}> $usages */
    private function recordRevision(User $user, ApplicationDraftItem $item, string $action, string $validationResult, array $usages): void
    {
        $revision = new ApplicationDraftRevision;
        $revision->forceFill([
            'owner_id' => $user->id,
            'draft_item_id' => $item->id,
            'actor_user_id' => $user->id,
            'revision_number' => $item->revision_number,
            'action' => $action,
            'content' => $item->content,
            'content_hash' => hash('sha256', $item->content),
            'validation_result' => $validationResult,
            'claim_usages' => $usages,
            'created_at' => now(),
        ])->save();
    }

    private function assertCurrent(User $user, ApplicationPreparation $preparation): void
    {
        $snapshot = VacancySnapshot::query()->where('owner_id', $user->id)->where('vacancy_id', $preparation->vacancy_id)
            ->orderByDesc('version')->orderByDesc('id')->firstOrFail();
        if (! hash_equals((string) $snapshot->id, (string) $preparation->vacancy_snapshot_id)
            || ! hash_equals($preparation->career_signature, $this->matching->careerSignature($user))) {
            throw ValidationException::withMessages(['preparation' => 'Vacancy or confirmed Career evidence changed. Reanalyze and open the current preparation.']);
        }
    }

    private function isStale(User $user, ApplicationPreparation $preparation): bool
    {
        try {
            $this->assertCurrent($user, $preparation);

            return false;
        } catch (ValidationException) {
            return true;
        }
    }

    private function ownerScopedVacancyTitle(User $user, ApplicationPreparation $preparation): ?string
    {
        return Vacancy::query()->where('owner_id', $user->id)->findOrFail($preparation->vacancy_id)->title;
    }

    private function ownerScopedVacancyCompany(User $user, ApplicationPreparation $preparation): ?string
    {
        return Vacancy::query()->where('owner_id', $user->id)->findOrFail($preparation->vacancy_id)->company;
    }

    private function assertOwner(User $user, object $resource): void
    {
        if (! hash_equals((string) $user->id, (string) $resource->owner_id)) {
            abort(404);
        }
    }
}
