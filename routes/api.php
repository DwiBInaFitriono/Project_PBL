<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\ReadController;
use App\Http\Middleware\ApiTokenOnly;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
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

    Route::get('/system/migrate', function (Request $request) {
        if ($request->query('key') !== config('app.key') && $request->query('key') !== 'rebung-pbl-2026') {
            abort(403, 'Akses tidak diizinkan.');
        }

        try {
            Artisan::call('migrate', ['--force' => true]);
            $output = Artisan::output();

            if ($request->has('seed')) {
                Artisan::call('db:seed', ['--force' => true]);
                $output .= "\n".Artisan::output();
            }

            return response()->json([
                'status' => 'success',
                'message' => 'Migrasi database berhasil dijalankan.',
                'output' => $output,
            ]);
        } catch (Throwable $e) {
            return response()->json([
                'status' => 'error',
                'message' => $e->getMessage(),
            ], 500);
        }
    });
});
