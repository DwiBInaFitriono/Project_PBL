<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\ReadController;
use App\Http\Middleware\ApiTokenOnly;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function (): void {
    Route::post('/auth/login', [AuthController::class, 'login'])->middleware('throttle:api-login');
    Route::middleware(['auth:sanctum', ApiTokenOnly::class, 'throttle:mobile-api'])->group(function (): void {
        Route::get('/me', [AuthController::class, 'me']);
        Route::post('/auth/logout', [AuthController::class, 'logout']);
        Route::get('/monitoring', [ReadController::class, 'monitoring']);
        Route::get('/history', [ReadController::class, 'history']);
        Route::get('/settings/esp', [ReadController::class, 'esp']);
        Route::get('/analytics', [ReadController::class, 'analytics']);
    });
});
