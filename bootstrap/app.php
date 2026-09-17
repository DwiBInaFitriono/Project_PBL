<?php

use App\Http\Middleware\EnforceAllowedHosts;
use App\Http\Middleware\RequireSessionFingerprint;
use App\Http\Middleware\SecurityHeaders;
use App\Support\AccessResponse;
use Illuminate\Contracts\Session\Middleware\AuthenticatesSessions;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Middleware\TrustProxies;
use Illuminate\Http\Request;
use Illuminate\Session\Middleware\AuthenticateSession;
use Symfony\Component\HttpFoundation\Response;

$app = Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->prepend(SecurityHeaders::class);
        $middleware->replace(TrustProxies::class, EnforceAllowedHosts::class);
        $middleware->prependToPriorityList(AuthenticatesSessions::class, RequireSessionFingerprint::class);
        $middleware->web(append: [RequireSessionFingerprint::class, AuthenticateSession::class]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->respond(fn (Response $response, Throwable $exception, Request $request): Response => AccessResponse::handle($response, $exception, $request));
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );
    })->create();

if ($storage = env('APP_STORAGE')) {
    $app->useStoragePath($storage);
}

return $app;
