<?php

namespace App\Http\Controllers;

use App\AI\Exceptions\LlmProviderException;
use App\Diagnostics\ErrorCatalog;
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
        } catch (LlmProviderException $exception) {
            return $this->providerFailure($exception, $request);
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
        } catch (LlmProviderException $exception) {
            return $this->providerFailure($exception, $request);
        }

        return response()->json(['data' => $resource]);
    }

    public function approve(Request $request, string $id, ApplicationPreparationService $service): JsonResponse
    {
        $item = ApplicationDraftItem::query()->where('owner_id', $request->user()->id)->findOrFail($id);

        try {
            return response()->json(['data' => $service->approve($request->user(), $item)]);
        } catch (LlmProviderException $exception) {
            return $this->providerFailure($exception, $request);
        }
    }

    private function providerFailure(LlmProviderException $exception, Request $request): JsonResponse
    {
        $entry = ErrorCatalog::classify($exception);
        $requestId = $request->attributes->get('request_id');

        return response()->json([
            'message' => $entry['message'],
            'error' => [
                'code' => $entry['code'],
                'message' => $entry['message'],
                'request_id' => $requestId,
                'retryable' => $entry['retryable'],
            ],
        ], 503, ErrorCatalog::responseHeaders($exception));
    }
}
