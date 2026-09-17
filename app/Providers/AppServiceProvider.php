<?php

namespace App\Providers;

use App\Models\User;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        RateLimiter::for('api-login', fn (Request $request) => Limit::perMinute(20)->by($request->ip()));
        RateLimiter::for('mobile-api', fn (Request $request) => Limit::perMinute(120)->by((string) $request->user()?->getAuthIdentifier()));
        Gate::define('manage-esp', fn (User $user): bool => $user->isOperator());
        RateLimiter::for('web-monitoring', fn (Request $request) => Limit::perMinute(120)->by((string) $request->user()?->getAuthIdentifier()));
        RateLimiter::for('registration', fn (Request $request) => Limit::perMinute(10)->by($request->ip()));
        RateLimiter::for('history-export', fn (Request $request) => Limit::perMinute(5)->by((string) $request->user()?->getAuthIdentifier()));
    }
}
