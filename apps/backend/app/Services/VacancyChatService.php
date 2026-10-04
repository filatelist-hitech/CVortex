<?php

namespace App\Services;

use App\AI\ChatGpt\ConnectionService;
use App\AI\ChatGpt\PlanException;
use App\AI\Contracts\StreamingProvider;
use App\AI\Data\ModelPolicy;
use App\AI\Exceptions\ModelSelectionException;
use App\AI\ModelPolicyResolver;
use App\AI\RuntimeSkillRegistry;
use App\Models\User;
use App\Models\Vacancy;
use App\Models\VacancyChatMessage;
use App\Models\VacancyChatThread;
use App\Models\VacancyLlmRun;
use Generator;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Throwable;

class VacancyChatService
{
    public function __construct(private readonly DatabaseOwnerContext $owners, private readonly ConnectionService $connections,
        private readonly StreamingProvider $provider, private readonly RuntimeSkillRegistry $skills,
        private readonly VacancyChatContextBuilder $context, private readonly ModelPolicyResolver $policies) {}

    public function open(User $user, string $vacancyId): VacancyChatThread
    {
        return $this->owners->run((string) $user->id, fn () => DB::transaction(function () use ($user, $vacancyId): VacancyChatThread {
            Vacancy::query()->where('owner_id', $user->id)->lockForUpdate()->findOrFail($vacancyId);
            $thread = VacancyChatThread::query()->firstOrCreate(['owner_id' => $user->id, 'vacancy_id' => $vacancyId]);
            if ($thread->status === 'STREAMING' && $thread->updated_at->isBefore(now()->subSeconds(150))) {
                $runs = VacancyChatMessage::query()->where('owner_id', $user->id)->where('thread_id', $thread->id)->where('status', 'STREAMING')->pluck('run_id');
                VacancyChatMessage::query()->where('owner_id', $user->id)->where('thread_id', $thread->id)->where('status', 'STREAMING')->update(['status' => 'INTERRUPTED', 'error_code' => 'STREAM_INTERRUPTED']);
                VacancyLlmRun::query()->where('owner_id', $user->id)->whereIn('id', $runs)->update(['status' => 'INTERRUPTED', 'error_category' => 'STREAM_INTERRUPTED']);
                $thread->update(['status' => 'INTERRUPTED']);
            }

            return $thread;
        }));
    }

    /** @return array<string, mixed> */
    public function show(User $user, string $vacancyId): array
    {
        return $this->owners->run((string) $user->id, function () use ($user, $vacancyId): array {
            $thread = $this->open($user, $vacancyId);

            return [...$thread->toArray(), 'messages' => VacancyChatMessage::query()->where('owner_id', $user->id)->where('thread_id', $thread->id)->orderByDesc('id')->limit(100)->get()->reverse()->values()->toArray()];
        });
    }

    /** @return array{thread: VacancyChatThread, assistant: VacancyChatMessage, context: array<string, mixed>, instructions: string} */
    public function begin(User $user, string $vacancyId, string $connectionId, string $model, string $requestId, ?string $content, bool $analyze): array
    {
        return $this->owners->run((string) $user->id, function () use ($user, $vacancyId, $connectionId, $model, $requestId, $content, $analyze): array {
            $this->connections->accessToken($user, $connectionId);
            $skill = $this->skills->vacancyPlanChat();
            $resolved = $this->policies->resolveSelected(new ModelPolicy($skill->modelPolicy, false), $model);
            $this->policies->assertAvailable($resolved, $this->provider->models($user, $connectionId));
            $thread = $this->open($user, $vacancyId);

            return DB::transaction(function () use ($user, $thread, $connectionId, $model, $requestId, $content, $analyze, $skill, $resolved): array {
                $thread = VacancyChatThread::query()->where('owner_id', $user->id)->lockForUpdate()->findOrFail($thread->id);
                if ($thread->status === 'STREAMING') {
                    throw ValidationException::withMessages(['thread' => 'A turn is already streaming. Refresh after completion.']);
                }
                if (VacancyChatMessage::query()->where('owner_id', $user->id)->where('thread_id', $thread->id)->where('client_request_id', $requestId)->exists()) {
                    throw ValidationException::withMessages(['client_request_id' => 'This turn was already submitted. Reload its saved result.']);
                }
                $turn = $analyze ? file_get_contents(rtrim((string) config('ai.asset_root'), '/').'/skills/vacancy-plan-chat/v1/analyze.prompt.md') : $content;
                if (! is_string($turn) || trim($turn) === '') {
                    throw ValidationException::withMessages(['content' => 'Enter a message.']);
                }
                $context = $this->context->build($user, $thread, $turn);
                $run = VacancyLlmRun::query()->create(['owner_id' => $user->id, 'vacancy_snapshot_id' => $context['snapshot']->id,
                    'workflow' => 'vacancy_plan_chat', 'skill_id' => $skill->id, 'skill_version' => $skill->version,
                    'prompt_version' => $skill->promptVersion, 'model_policy' => $skill->modelPolicy, 'provider' => $resolved->provider,
                    'model' => $resolved->model, 'status' => 'RUNNING', 'validation_result' => 'NOT_VALIDATED']);
                $base = ['owner_id' => $user->id, 'thread_id' => $thread->id, 'vacancy_snapshot_id' => $context['snapshot']->id,
                    'career_signature' => $context['career_signature'], 'run_id' => $run->id];
                VacancyChatMessage::query()->create([...$base, 'role' => 'user', 'content' => $turn, 'client_request_id' => $requestId, 'status' => 'COMPLETED']);
                $assistant = VacancyChatMessage::query()->create([...$base, 'role' => 'assistant', 'content' => '', 'status' => 'STREAMING']);
                $thread->update(['status' => 'STREAMING', 'connection_id' => $connectionId, 'model' => $model]);

                return ['thread' => $thread, 'assistant' => $assistant, 'context' => $context,
                    'instructions' => $skill->trustedInstructions."\nOutput schema:\n".json_encode($skill->outputSchema, JSON_THROW_ON_ERROR)];
            });
        });
    }

    public function cancel(User $user, string $vacancyId, string $requestId): bool
    {
        return $this->owners->run((string) $user->id, fn (): bool => DB::transaction(function () use ($user, $vacancyId, $requestId): bool {
            $vacancy = Vacancy::query()->where('owner_id', $user->id)->findOrFail($vacancyId);
            $thread = VacancyChatThread::query()->where('owner_id', $user->id)->where('vacancy_id', $vacancy->id)->lockForUpdate()->firstOrFail();
            $userMessage = VacancyChatMessage::query()->where('owner_id', $user->id)->where('thread_id', $thread->id)
                ->where('role', 'user')->where('client_request_id', $requestId)->firstOrFail();
            $assistant = VacancyChatMessage::query()->where('owner_id', $user->id)->where('thread_id', $thread->id)
                ->where('run_id', $userMessage->run_id)->where('role', 'assistant')->lockForUpdate()->firstOrFail();
            if ($assistant->status !== 'STREAMING') {
                return false;
            }

            $assistant->forceFill(['status' => 'INTERRUPTED', 'error_code' => 'USER_CANCELLED'])->save();
            $thread->forceFill(['status' => 'INTERRUPTED'])->save();
            VacancyLlmRun::query()->where('owner_id', $user->id)->whereKey($assistant->run_id)->where('status', 'RUNNING')
                ->update(['status' => 'INTERRUPTED', 'error_category' => 'USER_CANCELLED']);

            return true;
        }));
    }

    /** @param array{thread: VacancyChatThread, assistant: VacancyChatMessage, context: array<string, mixed>, instructions: string} $turn
     * @return Generator<int, array<string, mixed>>
     */
    public function stream(User $user, array $turn): Generator
    {
        $text = '';
        $started = hrtime(true);
        $success = false;
        $error = 'STREAM_INTERRUPTED';
        $requestId = null;
        try {
            yield ['type' => 'started', 'message_id' => $turn['assistant']->id];
            $skill = $this->skills->vacancyPlanChat();
            $this->policies->resolveSelected(new ModelPolicy($skill->modelPolicy, false), $turn['thread']->model);
            $state = $this->assistantState($user, $turn['assistant']);
            if ($state['status'] !== 'STREAMING') {
                $error = $state['error_code'] ?? 'STREAM_INTERRUPTED';
            } else {
                foreach ($this->provider->stream($user, $turn['thread']->connection_id, $turn['thread']->model, $turn['instructions'], $turn['context']['input']) as $event) {
                    $state = $this->assistantState($user, $turn['assistant']);
                    if ($state['status'] !== 'STREAMING') {
                        $error = $state['error_code'] ?? 'STREAM_INTERRUPTED';
                        break;
                    }
                    if ($event['type'] === 'delta') {
                        $text .= $event['text'];
                        // Persist partial output so reload/disconnect never claims a completed result.
                        $changed = $this->owners->run((string) $user->id, fn () => VacancyChatMessage::query()->where('owner_id', $user->id)->whereKey($turn['assistant']->id)->where('status', 'STREAMING')->update(['content' => $text]));
                        if ($changed === 0) {
                            $error = 'USER_CANCELLED';
                            break;
                        }
                        yield $event;
                    } elseif ($event['type'] === 'completed') {
                        $requestId = $event['request_id'] ?? null;
                        $success = true;
                    }
                }
            }
        } catch (PlanException $exception) {
            $error = $exception->errorCode;
            $requestId = $exception->requestId;
        } catch (ModelSelectionException $exception) {
            $error = $exception->errorCode;
        } catch (Throwable) {
            $error = 'CHAT_OPERATION_FAILED';
        } finally {
            $status = $success ? 'COMPLETED' : 'INTERRUPTED';
            $this->owners->run((string) $user->id, fn () => DB::transaction(function () use ($user, $turn, $text, $status, $error, $requestId, $started): void {
                $changed = VacancyChatMessage::query()->where('owner_id', $user->id)->whereKey($turn['assistant']->id)->where('status', 'STREAMING')
                    ->update(['content' => $text, 'status' => $status, 'error_code' => $status === 'COMPLETED' ? null : $error]);
                if ($changed !== 0) {
                    VacancyChatThread::query()->where('owner_id', $user->id)->whereKey($turn['thread']->id)->update(['status' => $status]);
                    VacancyLlmRun::query()->where('owner_id', $user->id)->whereKey($turn['assistant']->run_id)->update(['status' => $status,
                        'provider_request_id' => $requestId, 'error_category' => $status === 'COMPLETED' ? null : mb_substr($error, 0, 64),
                        'latency_ms' => (int) ((hrtime(true) - $started) / 1e6), 'estimated_cost_micros' => null]);
                }
            }));
        }
        yield $success ? ['type' => 'completed', 'message_id' => $turn['assistant']->id] : ['type' => 'error', 'code' => $error];
    }

    /** @return array{status: string|null, error_code: string|null} */
    private function assistantState(User $user, VacancyChatMessage $assistant): array
    {
        return $this->owners->run((string) $user->id, fn (): array => VacancyChatMessage::query()
            ->where('owner_id', $user->id)->whereKey($assistant->id)->first(['status', 'error_code'])?->only(['status', 'error_code'])
            ?? ['status' => null, 'error_code' => null]);
    }
}
