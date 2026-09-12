<?php

namespace App\Http\Controllers;

use App\Services\DependencyProbe;
use Illuminate\Http\JsonResponse;

final class HealthController extends Controller
{
    public function live(): JsonResponse
    {
        return response()->json(['status' => 'live']);
    }

    public function ready(DependencyProbe $probe): JsonResponse
    {
        $ready = $probe->ready();

        return response()->json(
            ['status' => $ready ? 'ready' : 'not_ready'],
            $ready ? 200 : 503,
        );
    }
}
