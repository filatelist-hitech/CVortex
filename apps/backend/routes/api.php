<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\CareerController;
use App\Http\Controllers\CareerExtractionController;
use App\Http\Controllers\CurrentUserController;
use App\Http\Controllers\HealthController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function (): void {
    Route::get('/health/live', [HealthController::class, 'live']);
    Route::get('/health/ready', [HealthController::class, 'ready']);

    Route::middleware('throttle:login')->post('/auth/login', [AuthController::class, 'login']);
    Route::post('/auth/register', [AuthController::class, 'register']);
    Route::middleware(['auth:sanctum', 'active-user'])->group(function (): void {
        Route::get('/me', CurrentUserController::class);
        Route::post('/auth/logout', [AuthController::class, 'logout']);
        Route::get('/career', [CareerController::class, 'index']);
        Route::post('/career/extractions', CareerExtractionController::class);
        Route::get('/career/sources/{id}', [CareerController::class, 'source']);
        Route::post('/career/facts/manual', [CareerController::class, 'manual']);
        Route::patch('/career/facts/{id}/review', [CareerController::class, 'review']);
        Route::patch('/career/facts/{id}/deprecate', [CareerController::class, 'deprecate']);
    });
});
