<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class RequireSessionFingerprint
{
    public function handle(Request $request, Closure $next): Response
    {
        $guard = Auth::guard('web');
        if ($request->hasSession() && $request->session()->has($guard->getName())
            && $guard->check() && ! $guard->viaRemember()
            && ! $request->session()->has('password_hash_web')) {
            $guard->logoutCurrentDevice();
            $request->session()->invalidate();
            $request->session()->regenerateToken();
            throw new AuthenticationException;
        }

        return $next($request);
    }
}
