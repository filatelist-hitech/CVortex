<?php

namespace App\Http\Controllers;

use App\Diagnostics\IncidentRecorder;
use App\Models\User;
use App\Services\AuditLogger;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

final class DiagnosticsController extends Controller
{
    private const BROWSER_COMPONENTS = ['browser', 'app-root', 'global-root'];

    private const BROWSER_ROUTES = ['/', '/register', 'other'];

    public function index(Request $request): JsonResponse
    {
        $this->requireAdmin($request);
        $filters = $request->validate([
            'severity' => [Rule::in(['WARNING', 'ERROR', 'CRITICAL'])],
            'status' => [Rule::in(['OPEN', 'RESOLVED', 'IGNORED'])],
            'service' => ['string', 'max:32'], 'component' => ['string', 'max:96'],
            'environment' => ['string', 'max:32'], 'error_code' => ['string', 'max:96'],
            'from' => ['date'], 'to' => ['date'],
            'search' => ['string', 'max:128'],
            'request_id' => ['string', 'max:128'], 'job_id' => ['string', 'max:128'],
            'llm_run_id' => ['string', 'max:26'], 'application_id' => ['string', 'max:26'],
        ]);
        $query = DB::table('diagnostic_incidents');
        foreach (['severity', 'status', 'service', 'component', 'environment', 'error_code'] as $key) {
            if (isset($filters[$key])) {
                $query->where($key, $filters[$key]);
            }
        }
        if (isset($filters['from'])) {
            $query->where('last_seen_at', '>=', Carbon::parse($filters['from'])->startOfDay());
        }
        if (isset($filters['to'])) {
            $query->where('last_seen_at', '<=', Carbon::parse($filters['to'])->endOfDay());
        }
        foreach (['request_id', 'job_id', 'llm_run_id', 'application_id'] as $key) {
            if (isset($filters[$key])) {
                $query->whereIn('id', DB::table('diagnostic_occurrences')->select('incident_id')->where($key, $filters[$key]));
            }
        }
        if (isset($filters['search'])) {
            $search = $filters['search'];
            $query->where(function ($builder) use ($search): void {
                $builder->where('error_code', $search)->orWhereIn('id',
                    DB::table('diagnostic_occurrences')->select('incident_id')->where('request_id', $search)
                        ->orWhere('job_id', $search)->orWhere('llm_run_id', $search)->orWhere('application_id', $search));
            });
        }

        $incidents = $query->orderByDesc('last_seen_at')->paginate(25)->through(function (object $incident): object {
            $incident->retryable = (bool) $incident->retryable;

            return $incident;
        });

        return response()->json(['data' => $incidents]);
    }

    public function show(Request $request, string $id): JsonResponse
    {
        $this->requireAdmin($request);
        $incident = DB::table('diagnostic_incidents')->where('id', $id)->first();
        abort_if($incident === null, 404);
        $incident->retryable = (bool) $incident->retryable;
        $occurrences = DB::table('diagnostic_occurrences')->where('incident_id', $id)
            ->orderByDesc('created_at')->limit(20)->get();

        return response()->json(['data' => ['incident' => $incident, 'occurrences' => $occurrences]]);
    }

    public function update(Request $request, string $id, AuditLogger $audit): JsonResponse
    {
        $user = $this->requireAdmin($request);
        $data = $request->validate(['status' => ['required', Rule::in(['OPEN', 'RESOLVED', 'IGNORED'])]]);
        DB::transaction(function () use ($id, $data, $audit, $user): void {
            $incident = DB::table('diagnostic_incidents')->where('id', $id)->lockForUpdate()->first();
            abort_if($incident === null, 404);
            DB::table('diagnostic_incidents')->where('id', $id)->update(['status' => $data['status'], 'updated_at' => now()]);
            $audit->record('diagnostics.incident.status_changed', 'USER', $user, 'diagnostic_incident', $id, ['status' => $data['status']]);
        });

        return $this->show($request, $id);
    }

    public function report(Request $request, IncidentRecorder $recorder): JsonResponse
    {
        abort_if(strlen($request->getContent()) > 2048, 413);
        $data = $request->validate([
            'component' => ['required', Rule::in(self::BROWSER_COMPONENTS)],
            'kind' => ['required', Rule::in(['runtime', 'rejection', 'render'])],
            'error_ref' => ['sometimes', 'string', 'regex:/\\A[a-f0-9]{64}\\z/'],
            'route' => ['sometimes', 'string', Rule::in(self::BROWSER_ROUTES)],
        ]);
        $recorded = $recorder->record('FRONTEND_RUNTIME_ERROR', 'A browser operation failed.', $data['component'], 'ERROR', null, [
            'service' => 'frontend', 'request_id' => $request->attributes->get('request_id'),
            'user_id' => $request->user()?->id, 'operation' => $data['kind'],
            'route' => $data['route'] ?? null, 'error_ref' => $data['error_ref'] ?? null,
        ]);
        if (! $recorded) {
            return response()->json([
                'message' => 'The diagnostics report could not be stored.',
                'error' => [
                    'code' => 'DIAGNOSTICS_UNAVAILABLE',
                    'message' => 'The diagnostics report could not be stored.',
                    'request_id' => $request->attributes->get('request_id'),
                    'retryable' => true,
                ],
            ], 503);
        }

        return response()->json(['data' => ['request_id' => $request->attributes->get('request_id')]], 202);
    }

    private function requireAdmin(Request $request): User
    {
        /** @var User $user */
        $user = $request->user();
        abort_unless($user->role === User::ROLE_ADMIN, 403);

        return $user;
    }
}
