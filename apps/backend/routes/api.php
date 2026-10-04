<?php

use App\Http\Controllers\ApplicationPreparationController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\CareerController;
use App\Http\Controllers\CareerExtractionController;
use App\Http\Controllers\ChatGptConnectionController;
use App\Http\Controllers\CurrentUserController;
use App\Http\Controllers\DiagnosticsController;
use App\Http\Controllers\HealthController;
use App\Http\Controllers\VacancyChatController;
use App\Http\Controllers\VacancyController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function (): void {
    Route::get('/health/live', [HealthController::class, 'live']);
    Route::get('/health/ready', [HealthController::class, 'ready']);
    Route::middleware('throttle:30,1')->get('/chatgpt/callback', [ChatGptConnectionController::class, 'callback']);

    Route::middleware('throttle:login')->post('/auth/login', [AuthController::class, 'login']);
    Route::post('/auth/register', [AuthController::class, 'register']);
    Route::middleware(['auth:sanctum', 'active-user'])->group(function (): void {
        Route::get('/me', CurrentUserController::class);
        Route::middleware(['db-owner-context', 'throttle:chatgpt'])->prefix('chatgpt')->group(function (): void {
            $controller = ChatGptConnectionController::class;
            Route::get('/connections', [$controller, 'index']);
            Route::post('/connections', [$controller, 'start']);
            Route::get('/connections/{id}/models', [$controller, 'models']);
            Route::patch('/connections/{id}/welcome', [$controller, 'acknowledgeWelcome']);
            Route::delete('/connections/{id}', [$controller, 'disconnect']);
            Route::post('/connections/{id}/proof', [$controller, 'proof']);
        });
        Route::post('/auth/logout', [AuthController::class, 'logout']);
        Route::middleware('throttle:diagnostics-report')->post('/diagnostics/report', [DiagnosticsController::class, 'report']);
        Route::get('/diagnostics/incidents', [DiagnosticsController::class, 'index']);
        Route::get('/diagnostics/incidents/{id}', [DiagnosticsController::class, 'show']);
        Route::patch('/diagnostics/incidents/{id}', [DiagnosticsController::class, 'update']);
        if (app()->environment('testing')) {
            Route::get('/_diagnostics-test/internal', fn () => throw new RuntimeException('Synthetic internal failure.'));
        }
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
            Route::middleware('db-owner-context')->group(function (): void {
                Route::get('/vacancies', [VacancyController::class, 'index'])->name('vacancies.index');
                Route::post('/vacancies', [VacancyController::class, 'store'])->name('vacancies.store');
                Route::middleware('throttle:chatgpt')->group(function (): void {
                    $chat = VacancyChatController::class;
                    Route::get('/vacancies/{id}/chat', [$chat, 'show']);
                    Route::post('/vacancies/{id}/chat/context-preview', [$chat, 'preview']);
                    Route::post('/vacancies/{id}/chat/messages', [$chat, 'send']);
                    Route::post('/vacancies/{id}/chat/cancel', [$chat, 'cancel']);
                    Route::get('/vacancies/{id}/analysis-drafts', [$chat, 'drafts']);
                    Route::post('/vacancies/{id}/analysis-drafts', [$chat, 'save']);
                    Route::post('/vacancy-analysis-drafts/{id}/approve', [$chat, 'approve']);
                });
                Route::get('/vacancies/{id}', [VacancyController::class, 'show'])->name('vacancies.show');
                Route::post('/vacancies/{id}/reanalyze', [VacancyController::class, 'reanalyze'])->name('vacancies.reanalyze');
                Route::post('/vacancies/{vacancyId}/preparation', [ApplicationPreparationController::class, 'open'])->name('applications.preparation.open');
                Route::get('/applications/preparations/{id}', [ApplicationPreparationController::class, 'show'])->name('applications.preparation.show');
                Route::post('/applications/preparations/{id}/generate', [ApplicationPreparationController::class, 'generate'])->name('applications.preparation.generate');
                Route::patch('/applications/draft-items/{id}', [ApplicationPreparationController::class, 'updateItem'])->name('applications.draft-items.update');
                Route::post('/applications/draft-items/{id}/approve', [ApplicationPreparationController::class, 'approve'])->name('applications.draft-items.approve');
            });
        });
    });
});
