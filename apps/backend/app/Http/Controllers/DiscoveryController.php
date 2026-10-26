<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\Discovery\JobDiscoveryService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DiscoveryController extends Controller
{
    public function __construct(
        private readonly JobDiscoveryService $discoveryService,
    ) {}

    public function index(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        $status = $request->query('status');
        $portal = $request->query('portal');
        $perPage = max(1, min(100, (int) $request->query('per_page', 20)));

        $paginator = $this->discoveryService->list(
            $user,
            is_string($status) ? $status : null,
            is_string($portal) ? $portal : null,
            $perPage
        );

        return response()->json([
            'items' => $paginator->items(),
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
            ],
        ]);
    }

    public function search(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'query' => ['required', 'string', 'min:2', 'max:255'],
            'portals' => ['nullable', 'array'],
            'portals.*' => ['string', 'in:freehire,headhunter,hh,habr,linkedin'],
            'location' => ['nullable', 'string', 'max:255'],
            'limit' => ['nullable', 'integer', 'min:1', 'max:50'],
            'remote' => ['nullable', 'boolean'],
        ]);

        /** @var User $user */
        $user = $request->user();

        $portals = $validated['portals'] ?? ['freehire', 'headhunter', 'habr', 'linkedin'];
        $location = $validated['location'] ?? null;
        $limit = isset($validated['limit']) ? (int) $validated['limit'] : 15;
        $remote = (bool) ($validated['remote'] ?? false);

        $results = $this->discoveryService->search(
            $user,
            $validated['query'],
            $portals,
            $location,
            $limit,
            $remote
        );

        return response()->json([
            'success' => true,
            'count' => $results->count(),
            'items' => $results->values(),
        ]);
    }

    public function accept(Request $request, string $id): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        $result = $this->discoveryService->accept($user, $id);

        return response()->json([
            'success' => true,
            'discovered' => $result['discovered'],
            'vacancy' => $result['ingestion']['vacancy'] ?? null,
            'duplicate' => $result['ingestion']['duplicate'] ?? false,
        ]);
    }

    public function dismiss(Request $request, string $id): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        $discovered = $this->discoveryService->dismiss($user, $id);

        return response()->json([
            'success' => true,
            'discovered' => $discovered,
        ]);
    }
}
