<?php

namespace App\Http\Controllers;

use App\AI\Exceptions\LlmProviderException;
use App\Models\ApplicationDraftItem;
use App\Models\ApplicationPreparation;
use App\Services\ApplicationPreparationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ApplicationPreparationController extends Controller
{
    public function open(Request $request, string $vacancyId, ApplicationPreparationService $service): JsonResponse
    {
        $preparation = $service->open($request->user(), $vacancyId);

        return response()->json(['data' => $service->resource($request->user(), $preparation)]);
    }

    public function show(Request $request, string $id, ApplicationPreparationService $service): JsonResponse
    {
        $preparation = ApplicationPreparation::query()->where('owner_id', $request->user()->id)->findOrFail($id);

        return response()->json(['data' => $service->resource($request->user(), $preparation)]);
    }

    public function generate(Request $request, string $id, ApplicationPreparationService $service): JsonResponse
    {
        $preparation = ApplicationPreparation::query()->where('owner_id', $request->user()->id)->findOrFail($id);
        try {
            return response()->json(['data' => $service->generate($request->user(), $preparation)]);
        } catch (LlmProviderException) {
            return response()->json(['message' => 'Draft generation is temporarily unavailable.', 'error' => ['code' => 'GENERATION_UNAVAILABLE']], 503);
        }
    }

    public function updateItem(Request $request, string $id, ApplicationPreparationService $service): JsonResponse
    {
        $item = ApplicationDraftItem::query()->where('owner_id', $request->user()->id)->findOrFail($id);
        $data = $request->validate([
            'action' => ['required', 'in:accept,edit,reject'],
            'content' => ['required_if:action,edit', 'nullable', 'string', 'max:6000'],
        ]);

        try {
            $resource = $data['action'] === 'edit'
                ? $service->edit($request->user(), $item, trim((string) $data['content']))
                : $service->decide($request->user(), $item, $data['action']);
        } catch (LlmProviderException) {
            return response()->json(['message' => 'Draft truth validation is temporarily unavailable.', 'error' => ['code' => 'VALIDATION_UNAVAILABLE']], 503);
        }

        return response()->json(['data' => $resource]);
    }

    public function approve(Request $request, string $id, ApplicationPreparationService $service): JsonResponse
    {
        $item = ApplicationDraftItem::query()->where('owner_id', $request->user()->id)->findOrFail($id);

        try {
            return response()->json(['data' => $service->approve($request->user(), $item)]);
        } catch (LlmProviderException) {
            return response()->json(['message' => 'Draft truth validation is temporarily unavailable.', 'error' => ['code' => 'VALIDATION_UNAVAILABLE']], 503);
        }
    }
}
