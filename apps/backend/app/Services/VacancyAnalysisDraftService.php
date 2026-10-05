<?php

namespace App\Services;

use App\AI\Exceptions\VacancyOutputException;
use App\Models\User;
use App\Models\Vacancy;
use App\Models\VacancyAnalysisDraft;
use App\Models\VacancyAnalysisDraftRequirement;
use App\Models\VacancyChatMessage;
use App\Models\VacancyLlmRun;
use App\Models\VacancyRequirement;
use App\Models\VacancySnapshot;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class VacancyAnalysisDraftService
{
    public function __construct(private readonly DatabaseOwnerContext $owners, private readonly VacancyRequirementValidator $validator,
        private readonly TrustedCareerQuery $career, private readonly VacancyMatchingService $matching) {}

    /** @param array<string, mixed> $analysis
     * @return array<string, mixed>
     */
    public function save(User $user, string $vacancyId, string $snapshotId, string $requestId, array $analysis, ?string $messageId = null,
        ?string $careerSignature = null): array
    {
        return $this->owners->run((string) $user->id, fn (): array => DB::transaction(function () use ($user, $vacancyId, $snapshotId, $requestId, $analysis, $messageId, $careerSignature): array {
            $vacancy = Vacancy::query()->where('owner_id', $user->id)->lockForUpdate()->findOrFail($vacancyId);
            Validator::make(['client_request_id' => $requestId], ['client_request_id' => ['required', 'string', 'max:128', 'regex:/\A[A-Za-z0-9_-]+\z/D']])->validate();
            $snapshot = VacancySnapshot::query()->where('owner_id', $user->id)->where('vacancy_id', $vacancyId)->latest('version')->firstOrFail();
            if ((string) $snapshot->id !== $snapshotId) {
                throw ValidationException::withMessages(['snapshot_id' => 'The vacancy source changed. Analyze the current snapshot.']);
            }
            $signature = $this->matching->careerSignature($user);
            if ($careerSignature !== null && ! hash_equals($careerSignature, $signature)) {
                throw ValidationException::withMessages(['career_signature' => 'Career evidence changed. Read the current context before saving.']);
            }
            $hash = hash('sha256', json_encode([$snapshotId, $signature, $analysis, $messageId], JSON_THROW_ON_ERROR));
            $existing = VacancyAnalysisDraft::query()->where('owner_id', $user->id)->where('vacancy_id', $vacancy->id)->where('client_request_id', $requestId)->first();
            if ($existing !== null) {
                if (! hash_equals($existing->payload_hash, $hash)) {
                    throw ValidationException::withMessages(['client_request_id' => 'This request ID already saved a different result.']);
                }

                return $this->resource($user, $existing);
            }
            $requirements = $this->validate($user, $analysis, $snapshot);
            $message = null;
            $run = null;
            if ($messageId !== null) {
                $message = VacancyChatMessage::query()->where('owner_id', $user->id)->where('role', 'assistant')->where('status', 'COMPLETED')->findOrFail($messageId);
                $run = VacancyLlmRun::query()->where('owner_id', $user->id)->findOrFail($message->run_id);
                if ($message->vacancy_snapshot_id !== $snapshotId || ! hash_equals($message->career_signature, $signature)
                    || $run->status !== 'COMPLETED' || json_decode($message->content, true) !== $analysis) {
                    throw ValidationException::withMessages(['message_id' => 'The completed result is stale or does not match this message.']);
                }
            }
            $draft = VacancyAnalysisDraft::query()->create([
                'owner_id' => $user->id, 'vacancy_id' => $vacancyId, 'vacancy_snapshot_id' => $snapshotId,
                'career_signature' => $signature, 'client_request_id' => $requestId, 'payload_hash' => $hash,
                'status' => 'DRAFT', 'origin' => 'AI_GENERATED', 'source_channel' => $message === null ? 'MCP' : 'EMBEDDED_CHAT',
                'message_id' => $messageId, 'run_id' => $run?->id, 'provider' => $run === null ? 'external_mcp' : $run->provider, 'model' => $run?->model,
                'proposed_matches' => $analysis['matches'], 'gaps' => $analysis['gaps'], 'risks' => $analysis['risks'],
                'questions' => $analysis['questions'], 'recommendations' => $analysis['recommendations'],
            ]);
            foreach ($requirements as $index => $requirement) {
                VacancyAnalysisDraftRequirement::query()->create(['owner_id' => $user->id, 'draft_id' => $draft->id, 'position' => $index, ...$requirement]);
            }

            return $this->resource($user, $draft);
        }));
    }

    /** @param array<string, mixed> $analysis
     * @return list<array<string, mixed>>
     */
    private function validate(User $user, array $analysis, VacancySnapshot $snapshot): array
    {
        if (strlen(json_encode($analysis, JSON_THROW_ON_ERROR)) > 65536) {
            throw ValidationException::withMessages(['analysis' => 'Analysis exceeds the 64 KiB limit.']);
        }
        Validator::make(['analysis' => $analysis], [
            'analysis' => ['required', 'array:requirements,matches,gaps,risks,questions,recommendations'],
            'analysis.requirements' => ['present', 'array', 'list', 'max:50'],
            'analysis.matches' => ['present', 'array', 'list', 'max:50'],
            'analysis.matches.*' => ['array:requirement_index,career_fact_ids'],
            'analysis.matches.*.requirement_index' => ['required', 'integer', 'min:0', 'max:49'],
            'analysis.matches.*.career_fact_ids' => ['present', 'array', 'list', 'max:20'],
            'analysis.matches.*.career_fact_ids.*' => ['required', 'ulid'],
            'analysis.gaps' => ['present', 'array', 'list', 'max:30'], 'analysis.gaps.*' => ['string', 'max:1000'],
            'analysis.risks' => ['present', 'array', 'list', 'max:30'], 'analysis.risks.*' => ['string', 'max:1000'],
            'analysis.questions' => ['present', 'array', 'list', 'max:30'], 'analysis.questions.*' => ['string', 'max:1000'],
            'analysis.recommendations' => ['present', 'array', 'list', 'max:30'], 'analysis.recommendations.*' => ['string', 'max:1000'],
        ])->validate();
        try {
            $requirements = $this->validator->validate(['requirements' => $analysis['requirements']], $snapshot->raw_text);
        } catch (VacancyOutputException) {
            throw ValidationException::withMessages(['analysis.requirements' => 'Requirements must use valid domain fields and exact supported source excerpts.']);
        }
        if (count($requirements) !== count($analysis['requirements'])) {
            throw ValidationException::withMessages(['analysis.requirements' => 'Some requirements are not supported by the source.']);
        }
        foreach ($requirements as &$requirement) {
            if ($requirement['importance'] === 'UNCERTAIN') {
                $position = strpos($snapshot->raw_text, $requirement['source_excerpt']);
                $nextPosition = $position === false ? false : strpos($snapshot->raw_text, $requirement['source_excerpt'], $position + 1);
                if ($nextPosition !== false) {
                    continue;
                }
                $prefix = substr($snapshot->raw_text, 0, $position === false ? 0 : $position);
                preg_match_all('/(?:^|\n)\s*(Наши пожелания к кандидату|Требования|Requirements|Must-have|Будет плюсом|Будет плюс|Nice-to-have|Твои будущие задачи|Обязанности|Responsibilities|Почему[^\n]*|Benefits)\s*:?\s*$/miu', $prefix, $headings);
                $heading = mb_strtolower((string) end($headings[1]));
                $requirement['importance'] = match ($heading) {
                    'наши пожелания к кандидату', 'требования', 'requirements', 'must-have' => 'MANDATORY',
                    'будет плюсом', 'будет плюс', 'nice-to-have' => 'PREFERRED',
                    default => 'UNCERTAIN',
                };
            }
        }
        unset($requirement);
        $keys = array_map(fn (array $item): string => json_encode([$item['dimension'], $item['importance'], $item['label'], $item['normalized_value'], $item['source_excerpt']], JSON_THROW_ON_ERROR), $requirements);
        if (count(array_unique($keys)) !== count($keys)) {
            throw ValidationException::withMessages(['analysis.requirements' => 'Duplicate requirements are not allowed.']);
        }
        $ids = array_map(fn ($fact): string => (string) $fact->id, $this->career->forMatching($user)['facts']);
        foreach ($analysis['matches'] as $match) {
            if (count(array_unique($match['career_fact_ids'])) !== count($match['career_fact_ids'])
                || ! isset($requirements[$match['requirement_index']]) || array_diff($match['career_fact_ids'], $ids) !== []) {
                throw ValidationException::withMessages(['analysis.matches' => 'Matches must reference current owned CONFIRMED facts and a valid requirement.']);
            }
        }

        return $requirements;
    }

    /** @return array<string, mixed> */
    public function resource(User $user, VacancyAnalysisDraft $draft): array
    {
        abort_unless((string) $draft->owner_id === (string) $user->id, 404);

        return [...$draft->toArray(), 'requirements' => VacancyAnalysisDraftRequirement::query()->where('owner_id', $user->id)->where('draft_id', $draft->id)
            ->orderBy('position')->get(['dimension', 'importance', 'label', 'normalized_value', 'source_excerpt', 'confidence'])->toArray()];
    }

    /** @return array<string, mixed> */
    public function approve(User $user, string $id): array
    {
        return $this->owners->run((string) $user->id, fn (): array => DB::transaction(function () use ($user, $id): array {
            // Career writers take the same owner-row lock before changing confirmed facts or claims.
            User::query()->whereKey($user->id)->lockForUpdate()->firstOrFail();
            $draft = VacancyAnalysisDraft::query()->where('owner_id', $user->id)->findOrFail($id);
            $vacancy = Vacancy::query()->where('owner_id', $user->id)->lockForUpdate()->findOrFail($draft->vacancy_id);
            $draft = VacancyAnalysisDraft::query()->where('owner_id', $user->id)->lockForUpdate()->findOrFail($id);
            if ($draft->status === 'APPROVED') {
                return $this->resource($user, $draft);
            }
            $snapshot = VacancySnapshot::query()->where('owner_id', $user->id)->where('vacancy_id', $vacancy->id)->latest('version')->firstOrFail();
            if ($snapshot->id !== $draft->vacancy_snapshot_id || ! hash_equals($draft->career_signature, $this->matching->careerSignature($user))
                || in_array($vacancy->analysis_status, ['RUNNING', 'PENDING'], true)) {
                throw ValidationException::withMessages(['draft' => 'Source/evidence changed or API analysis is active. Refresh and analyze again.']);
            }
            if (mb_strlen($snapshot->raw_text) > VacancyChatContextBuilder::MAX_SOURCE_CHARACTERS) {
                throw ValidationException::withMessages(['draft' => 'This source exceeds the chat context limit. Shorten the vacancy before approving the draft.']);
            }
            $resource = $this->resource($user, $draft);
            $validated = $this->validate($user, ['requirements' => $resource['requirements'], 'matches' => $draft->proposed_matches,
                'gaps' => $draft->gaps, 'risks' => $draft->risks, 'questions' => $draft->questions, 'recommendations' => $draft->recommendations], $snapshot);
            $existing = VacancyRequirement::query()->where('owner_id', $user->id)->where('vacancy_snapshot_id', $snapshot->id)->get();
            $fingerprint = fn (array $item): string => hash('sha256', json_encode([$item['dimension'], $item['importance'], $item['label'], $item['normalized_value'], $item['source_excerpt']], JSON_THROW_ON_ERROR));
            $existingHashes = $existing->map(fn ($item): string => $fingerprint($item->toArray()))->sort()->values()->all();
            $newHashes = collect($validated)->map($fingerprint)->sort()->values()->all();
            if ($existingHashes !== [] && $existingHashes !== $newHashes) {
                throw ValidationException::withMessages(['draft' => 'Canonical requirements already exist. This draft cannot replace them.']);
            }
            if ($existingHashes === []) {
                foreach ($validated as $requirement) {
                    VacancyRequirement::query()->create(['owner_id' => $user->id, 'vacancy_snapshot_id' => $snapshot->id,
                        ...$requirement, 'extracted_by' => 'approved_analysis_draft', 'candidate_hash' => $fingerprint($requirement)]);
                }
            }
            $analysis = $this->matching->analyze($user, $vacancy, $snapshot);
            if (! hash_equals($draft->career_signature, $analysis->career_signature)
                || ! hash_equals($draft->career_signature, $this->matching->careerSignature($user))) {
                throw ValidationException::withMessages(['draft' => 'Career evidence changed during approval. Refresh and review the draft again.']);
            }
            $draft->forceFill(['status' => 'APPROVED', 'approved_at' => now(), 'approved_analysis_id' => $analysis->id])->save();
            $vacancy->forceFill(['analysis_status' => 'COMPLETED', 'error_code' => null, 'active_run_token' => null, 'next_attempt_at' => null, 'dispatch_recovery_at' => null])->save();

            return $this->resource($user, $draft);
        }));
    }
}
