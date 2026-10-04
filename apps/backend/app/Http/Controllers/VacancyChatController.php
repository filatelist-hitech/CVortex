<?php

namespace App\Http\Controllers;

use App\AI\ChatGpt\PlanException;
use App\AI\Exceptions\ModelSelectionException;
use App\Models\User;
use App\Models\Vacancy;
use App\Models\VacancyAnalysisDraft;
use App\Models\VacancyChatMessage;
use App\Services\VacancyAnalysisDraftService;
use App\Services\VacancyChatService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\StreamedResponse;

class VacancyChatController extends Controller
{
    public function __construct(private readonly VacancyChatService $chat, private readonly VacancyAnalysisDraftService $drafts) {}

    public function show(Request $request, string $id): JsonResponse
    {
        return response()->json(['data' => $this->chat->show($this->user($request), $id)])->header('Cache-Control', 'no-store');
    }

    public function send(Request $request, string $id): StreamedResponse|JsonResponse
    {
        $args = $request->validate([
            'connection_id' => ['required', 'ulid'], 'model' => ['required', 'string', 'max:128', 'regex:/\A[A-Za-z0-9._:-]+\z/D'],
            'client_request_id' => ['required', 'string', 'max:128', 'regex:/\A[A-Za-z0-9_-]+\z/D'],
            'content' => ['nullable', 'string', 'max:4000'], 'analyze' => ['sometimes', 'boolean'],
        ]);
        $user = $this->user($request);
        try {
            $turn = $this->chat->begin($user, $id, $args['connection_id'], $args['model'], $args['client_request_id'], $args['content'] ?? null, $args['analyze'] ?? false);
        } catch (PlanException $exception) {
            return response()->json(['error' => ['code' => $exception->errorCode, 'retryable' => false]], $exception->httpStatus);
        } catch (ModelSelectionException $exception) {
            return response()->json(['error' => ['code' => $exception->errorCode, 'retryable' => false]], 422);
        }

        return response()->stream(function () use ($user, $turn): void {
            foreach ($this->chat->stream($user, $turn) as $event) {
                echo 'data: '.json_encode($event, JSON_THROW_ON_ERROR)."\n\n";
                if (ob_get_level() > 0) {
                    ob_flush();
                }
                flush();
                if (connection_aborted()) {
                    break;
                }
            }
        }, 200, ['Content-Type' => 'text/event-stream', 'Cache-Control' => 'no-store', 'X-Accel-Buffering' => 'no']);
    }

    public function cancel(Request $request, string $id): JsonResponse
    {
        $args = $request->validate(['client_request_id' => ['required', 'string', 'max:128', 'regex:/\A[A-Za-z0-9_-]+\z/D']]);

        return response()->json(['cancelled' => $this->chat->cancel($this->user($request), $id, $args['client_request_id'])]);
    }

    public function drafts(Request $request, string $id): JsonResponse
    {
        $user = $this->user($request);
        Vacancy::query()->where('owner_id', $user->id)->findOrFail($id);

        return response()->json(['data' => VacancyAnalysisDraft::query()->where('owner_id', $user->id)->where('vacancy_id', $id)
            ->latest()->limit(20)->get()->map(fn ($draft): array => $this->drafts->resource($user, $draft))->all()])->header('Cache-Control', 'no-store');
    }

    public function save(Request $request, string $id): JsonResponse
    {
        $args = $request->validate(['message_id' => ['required', 'ulid'], 'client_request_id' => ['required', 'string', 'max:128']]);
        $user = $this->user($request);
        $message = VacancyChatMessage::query()->where('owner_id', $user->id)->where('role', 'assistant')->where('status', 'COMPLETED')->findOrFail($args['message_id']);
        $analysis = json_decode($message->content, true);
        if (! is_array($analysis)) {
            throw ValidationException::withMessages(['message_id' => 'This message has no structured analysis. Use Analyze vacancy.']);
        }

        return response()->json(['data' => $this->drafts->save($user, $id, $message->vacancy_snapshot_id, $args['client_request_id'], $analysis, $message->id)]);
    }

    public function approve(Request $request, string $id): JsonResponse
    {
        return response()->json(['data' => $this->drafts->approve($this->user($request), $id)]);
    }

    private function user(Request $request): User
    {
        $user = $request->user();
        abort_unless($user instanceof User, 401);

        return $user;
    }
}
