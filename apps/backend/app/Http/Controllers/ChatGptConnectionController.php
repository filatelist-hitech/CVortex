<?php

namespace App\Http\Controllers;

use App\AI\ChatGpt\ConnectionService;
use App\AI\ChatGpt\PlanException;
use App\AI\Contracts\StreamingProvider;
use App\AI\Data\ModelPolicy;
use App\AI\Exceptions\ModelSelectionException;
use App\AI\ModelPolicyResolver;
use App\AI\RuntimeSkillRegistry;
use App\Models\ChatGptConnection;
use App\Models\User;
use App\Services\DatabaseOwnerContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ChatGptConnectionController extends Controller
{
    public function __construct(
        private readonly ConnectionService $connections,
        private readonly StreamingProvider $provider,
        private readonly DatabaseOwnerContext $owners,
        private readonly ModelPolicyResolver $policies,
        private readonly RuntimeSkillRegistry $skills,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $user = $this->user($request);

        return $this->owners->run((string) $user->id, fn (): JsonResponse => response()->json([
            'data' => ChatGptConnection::query()->where('owner_id', $user->id)->latest()->get(['id', 'client_id', 'status', 'updated_at', 'welcome_acknowledged_at']),
            'enabled' => (bool) config('chatgpt.enabled'),
        ])->header('Cache-Control', 'no-store'));
    }

    public function start(Request $request): JsonResponse
    {
        $args = $request->validate(['connection_id' => ['nullable', 'ulid'], 'consent' => ['sometimes', 'boolean']]);
        try {
            return $this->owners->run((string) $this->user($request)->id, fn (): JsonResponse => response()->json([
                'authorization_url' => $this->connections->start($this->user($request), $args['connection_id'] ?? null, $args['consent'] ?? false),
            ])->header('Cache-Control', 'no-store'));
        } catch (PlanException $exception) {
            return $this->failure($exception);
        }
    }

    public function callback(Request $request): Response
    {
        // Browser callback may originate from a session opened at localhost. State identifies the authorized CVortex owner.
        // The app is local-only; accept this callback only at the configured loopback origin.
        if ($request->getSchemeAndHttpHost().$request->getPathInfo() !== config('chatgpt.callback_uri')) {
            abort(400);
        }
        try {
            $args = $request->validate([
                'state' => ['required', 'string', 'max:100'], 'code' => ['nullable', 'string', 'max:4096'],
                'client_id' => ['nullable', 'string', 'max:255'], 'error' => ['nullable', 'string', 'max:100'],
            ]);
            $connection = $this->connections->complete($args);
            $message = $connection->status === 'CONNECTED'
                ? 'Connected. Using ChatGPT plan. Return to CVortex and refresh the connection panel.'
                : 'Plan permission missing. Return to CVortex and enable ChatGPT plan usage.';
        } catch (PlanException $exception) {
            $message = 'Connection failed: '.$exception->errorCode.'. Return to CVortex and start a new sign-in.';
        }

        return response($message, 200)->header('Content-Type', 'text/plain; charset=utf-8')
            ->header('Cache-Control', 'no-store')->header('Referrer-Policy', 'no-referrer');
    }

    public function models(Request $request, string $id): JsonResponse
    {
        try {
            return response()->json(['data' => $this->provider->models($this->user($request), $id)])
                ->header('Cache-Control', 'no-store');
        } catch (PlanException $exception) {
            return $this->failure($exception);
        }
    }

    public function acknowledgeWelcome(Request $request, string $id): JsonResponse
    {
        $connection = $this->connections->owned($this->user($request), $id);
        $connection->forceFill(['welcome_acknowledged_at' => now()])->save();

        return response()->json(['acknowledged' => true]);
    }

    public function disconnect(Request $request, string $id): JsonResponse
    {
        return response()->json(['remote_revocation_confirmed' => $this->connections->disconnect($this->user($request), $id)]);
    }

    public function proof(Request $request, string $id): StreamedResponse|JsonResponse
    {
        $args = $request->validate(['model' => ['required', 'string', 'max:128', 'regex:/\A[A-Za-z0-9._:-]+\z/D']]);
        $user = $this->user($request);
        try {
            $this->connections->accessToken($user, $id);
            $resolved = $this->policies->resolveSelected(new ModelPolicy($this->skills->vacancyPlanChat()->modelPolicy, false), $args['model']);
            $this->policies->assertAvailable($resolved, $this->provider->models($user, $id));
        } catch (PlanException $exception) {
            return $this->failure($exception);
        } catch (ModelSelectionException $exception) {
            return response()->json(['error' => ['code' => $exception->errorCode, 'retryable' => false]], 422);
        }

        return response()->stream(function () use ($user, $id, $args, $request): void {
            $status = 'INTERRUPTED';
            $started = hrtime(true);
            $errorCode = null;
            try {
                foreach ($this->provider->stream($user, $id, $args['model'], 'This is a connection capability check. Reply exactly: Hello, CVortex.', [
                    ['role' => 'user', 'content' => 'Verify this explicitly requested ChatGPT plan connection.'],
                ]) as $event) {
                    echo 'data: '.json_encode($event, JSON_THROW_ON_ERROR)."\n\n";
                    if (ob_get_level() > 0) {
                        ob_flush();
                    }
                    flush();
                    if ($event['type'] === 'completed') {
                        $status = 'COMPLETED';
                    }
                }
            } catch (PlanException $exception) {
                $errorCode = $exception->errorCode;
                echo 'data: '.json_encode(['type' => 'error', 'code' => $errorCode], JSON_THROW_ON_ERROR)."\n\n";
            } catch (ModelSelectionException $exception) {
                $errorCode = $exception->errorCode;
                echo 'data: '.json_encode(['type' => 'error', 'code' => $errorCode], JSON_THROW_ON_ERROR)."\n\n";
            } finally {
                Log::info('chatgpt.connection_proof', [
                    'user_id' => (string) $user->id, 'request_id' => $request->attributes->get('request_id'),
                    'provider' => 'openai_chatgpt_plan', 'model' => $args['model'], 'status' => $status,
                    'error_code' => $errorCode, 'latency_ms' => (int) ((hrtime(true) - $started) / 1e6),
                ]);
            }
        }, 200, ['Content-Type' => 'text/event-stream', 'Cache-Control' => 'no-store', 'X-Accel-Buffering' => 'no']);
    }

    private function user(Request $request): User
    {
        $user = $request->user();
        abort_unless($user instanceof User, 401);

        return $user;
    }

    private function failure(PlanException $exception): JsonResponse
    {
        return response()->json(['error' => ['code' => $exception->errorCode, 'retryable' => false]], $exception->httpStatus);
    }
}
