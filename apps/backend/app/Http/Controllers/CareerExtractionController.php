<?php

namespace App\Http\Controllers;

use App\AI\Exceptions\LlmProviderException;
use App\Services\CareerExtractionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CareerExtractionController extends Controller
{
    public function __invoke(Request $request, CareerExtractionService $service): JsonResponse
    {
        $data = $request->validate([
            'source_text' => ['required', 'string', 'max:'.config('ai.career_extraction.max_source_characters')],
        ]);

        try {
            $source = $service->extract($request->user(), $data['source_text']);
        } catch (LlmProviderException) {
            return response()->json([
                'message' => 'Career extraction is temporarily unavailable. Manual fact entry is still available.',
                'error' => ['code' => 'PROVIDER_ERROR'],
            ], 503);
        }

        return response()->json(['data' => [
            'id' => $source->id,
            'extraction_status' => $source->extraction_status,
            'error_code' => $source->error_code,
        ]], 202);
    }
}
