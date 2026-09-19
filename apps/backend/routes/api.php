<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\CareerController;
use App\Http\Controllers\CareerExtractionController;
use App\Http\Controllers\CurrentUserController;
use App\Http\Controllers\HealthController;
use App\Http\Controllers\VacancyController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function (): void {
    Route::get('/health/live', [HealthController::class, 'live']);
    Route::get('/health/ready', [HealthController::class, 'ready']);

    Route::middleware('throttle:login')->post('/auth/login', [AuthController::class, 'login']);
    Route::post('/auth/register', [AuthController::class, 'register']);
    Route::middleware(['auth:sanctum', 'active-user'])->group(function (): void {
        Route::get('/me', CurrentUserController::class);
        Route::post('/auth/logout', [AuthController::class, 'logout']);
        Route::group([], function (): void {
            Route::get('/career', [CareerController::class, 'index'])->name('career.index');
            Route::get('/career/trusted', [CareerController::class, 'trusted'])->name('career.trusted');
            Route::post('/career/extractions', CareerExtractionController::class)->name('career.extract');
            Route::get('/career/sources/{id}', [CareerController::class, 'source'])->name('career.source');
            Route::post('/career/facts/manual', [CareerController::class, 'manual'])->name('career.manual');
            Route::patch('/career/facts/{id}/review', [CareerController::class, 'review'])->name('career.review');
            Route::patch('/career/facts/{id}/deprecate', [CareerController::class, 'deprecate'])->name('career.deprecate');
            Route::post('/career/facts/{id}/supersede', [CareerController::class, 'supersede'])->name('career.supersede');
            Route::patch('/career/claims/{id}/resolve', [CareerController::class, 'resolveClaim'])->name('career.claim.resolve');
            Route::get('/vacancies', [VacancyController::class, 'index'])->name('vacancies.index');
            Route::post('/vacancies', [VacancyController::class, 'store'])->name('vacancies.store');
            Route::get('/vacancies/{id}', [VacancyController::class, 'show'])->name('vacancies.show');
            Route::post('/vacancies/{id}/reanalyze', [VacancyController::class, 'reanalyze'])->name('vacancies.reanalyze');
        });
    });
});
