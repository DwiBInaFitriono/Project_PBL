<?php

namespace App\Support;

use Illuminate\Auth\AuthenticationException;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

class AccessResponse
{
    public static function handle(Response $response, Throwable $exception, Request $request): Response
    {
        $status = $exception instanceof AuthenticationException ? 401 : $response->getStatusCode();
        if ($status >= 500) {
            error_log('[SERVER ERROR 500] '.$exception->getMessage().' in '.$exception->getFile().':'.$exception->getLine());
        }

        if ($status >= 500 && ! config('app.debug')) {
            if ($request->has('debug')) {
                return response('<pre style="white-space: pre-wrap; font-family: monospace; padding: 20px; background: #111; color: #ff6b6b;">'.htmlspecialchars($exception->getMessage()."\n\nFile: ".$exception->getFile().':'.$exception->getLine()."\n\nTrace:\n".$exception->getTraceAsString()).'</pre>', 500);
            }

            $message = 'Terjadi gangguan server. Silakan coba lagi nanti.';
            $response->setContent($request->expectsJson() || $request->is('api/*')
                ? json_encode(['message' => $message], JSON_THROW_ON_ERROR)
                : '<!doctype html><html lang="id"><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>Gangguan server</title><body><h1>Terjadi gangguan server</h1><p>Silakan coba lagi nanti.</p></body></html>');
            $response->headers->set('Content-Type', $request->expectsJson() || $request->is('api/*')
                ? 'application/json'
                : 'text/html; charset=utf-8');
            $response->headers->remove('Content-Length');
            $response->headers->set('Cache-Control', 'private, no-store');

            return $response;
        }
        if (! in_array($status, [401, 403, 419], true)) {
            return $response;
        }

        $guest = $request->user() === null;
        $target = $guest ? '/login' : '/dashboard';
        $code = match ($status) {
            401 => 'authentication_required',
            419 => 'session_expired',
            default => 'access_denied',
        };
        $message = match (true) {
            $status === 419 => 'Sesi formulir telah berakhir. Silakan buka kembali halaman dan coba lagi.',
            $guest => 'Silakan masuk terlebih dahulu untuk mengakses halaman ini. Setelah masuk, Anda diarahkan ke Dashboard.',
            default => 'Anda tidak memiliki izin untuk mengakses halaman atau tindakan ini. Anda telah diarahkan ke Dashboard.',
        };

        if ($request->expectsJson() || $request->is('api/*', 'monitoring/data')) {
            return response()->json(['message' => $message, 'code' => $code, 'redirect' => $target], $status, [
                'Cache-Control' => 'private, no-store',
            ]);
        }

        if ($request->hasSession()) {
            $request->session()->flash('access_warning', $message);
            if ($guest) {
                $request->session()->put('url.intended', route('dashboard'));
            }
        }

        // Do not replay denied form submissions or redirect a denied destination to itself.
        if ($request->getPathInfo() === $target) {
            return response()->view('errors.access', ['message' => $message, 'destination' => $target], $status);
        }

        return redirect()->to($target)->withHeaders(['Cache-Control' => 'private, no-store']);
    }
}
