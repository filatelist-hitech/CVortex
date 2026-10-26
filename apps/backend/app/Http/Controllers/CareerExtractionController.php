<?php

namespace App\Http\Controllers;

use App\Services\CareerExtractionService;
use App\Settings\SettingsResolver;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CareerExtractionController extends Controller
{
    public function __invoke(Request $request, CareerExtractionService $service): JsonResponse
    {
        $maxChars = app()->bound(SettingsResolver::class)
            ? (int) app(SettingsResolver::class)->get('ai.career_extraction.max_source_characters', config('ai.career_extraction.max_source_characters', 30000))
            : (int) config('ai.career_extraction.max_source_characters', 30000);

        $data = $request->validate([
            'source_text' => ['required', 'string', 'max:'.$maxChars],
        ]);

        $source = $service->queue($request->user(), $data['source_text']);

        return response()->json(['data' => [
            'id' => $source->id,
            'extraction_status' => $source->extraction_status,
            'error_code' => $source->error_code,
        ]], 202);
    }
}
