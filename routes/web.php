<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\HistoryController;
use App\Http\Controllers\MonitoringDataController;
use App\Http\Controllers\NodeController;
use App\Http\Controllers\PasswordController;
use App\Http\Controllers\SettingsController;
use Illuminate\Support\Facades\Route;

Route::middleware('guest')->group(function (): void {
    Route::view('/', 'auth.login');
    Route::view('/login', 'auth.login')->name('login');
    Route::view('/register', 'auth.register')->name('register');

    Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:api-login')->name('login.store');
    Route::post('/register', [AuthController::class, 'register'])->middleware('throttle:registration')->name('register.store');
});

Route::get('/dashboard', DashboardController::class)->middleware(['auth', 'throttle:web-monitoring'])->name('dashboard');
Route::get('/nodes/{node}', NodeController::class)->whereIn('node', ['1', '2'])->middleware(['auth', 'throttle:web-monitoring'])->name('nodes.show');
Route::get('/monitoring/data', MonitoringDataController::class)->middleware(['auth', 'throttle:web-monitoring'])->name('monitoring.data');
Route::get('/history', [HistoryController::class, 'index'])->middleware('auth')->name('history.index');
Route::get('/history/export', [HistoryController::class, 'export'])->middleware(['auth', 'throttle:history-export'])->name('history.export');
Route::get('/settings/esp', [SettingsController::class, 'esp'])->middleware('auth')->name('settings.esp');
Route::get('/settings/account', [SettingsController::class, 'account'])->middleware('auth')->name('settings.account');
Route::patch('/settings/account', [SettingsController::class, 'updateAccount'])->middleware('auth')->name('settings.account.update');
Route::patch('/settings/password', [PasswordController::class, '__invoke'])->middleware('auth')->name('settings.password.update');
Route::post('/logout', [AuthController::class, 'logout'])->middleware('auth')->name('logout');
