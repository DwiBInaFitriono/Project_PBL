<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Vite;
use Symfony\Component\HttpFoundation\Response;

class SecurityHeaders
{
    public function handle(Request $request, Closure $next): Response
    {
        $nonce = base64_encode(random_bytes(32));
        Vite::useCspNonce($nonce);
        $origin = $this->viteOrigin();
        $assets = $origin === null ? '' : ' '.$origin;
        $connections = $origin === null ? '' : ' '.$origin.' '.preg_replace('/^http/', 'ws', $origin);
        $response = $next($request);
        $response->headers->set('X-Content-Type-Options', 'nosniff');
        $response->headers->set('X-Frame-Options', 'DENY');
        $response->headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');
        $response->headers->set('Permissions-Policy', 'camera=(), microphone=(), geolocation=()');
        $response->headers->set('Content-Security-Policy', "default-src 'self'; script-src 'self' 'nonce-{$nonce}'{$assets}; script-src-attr 'none'; style-src 'self' 'nonce-{$nonce}'{$assets}; style-src-attr 'none'; img-src 'self' data:; font-src 'self'; connect-src 'self'{$connections}; base-uri 'self'; object-src 'none'; frame-ancestors 'none'; form-action 'self'");
        if ($request->secure() && app()->environment('production')) {
            $response->headers->set('Strict-Transport-Security', 'max-age=31536000');
        }
        $response->headers->set('Cache-Control', 'private, no-store');

        return $response;
    }

    private function viteOrigin(): ?string
    {
        $origin = config('security.vite_origin');
        if (! app()->environment('local') || ! is_string($origin)
            || ! preg_match('~^https?://(?:[a-zA-Z0-9.-]+|\[[a-fA-F0-9:]+\])(?::[0-9]{1,5})?$~D', $origin)) {
            return null;
        }

        $host = parse_url($origin, PHP_URL_HOST);
        if (! is_string($host) || (! filter_var($host, FILTER_VALIDATE_DOMAIN, FILTER_FLAG_HOSTNAME)
            && ! filter_var(trim($host, '[]'), FILTER_VALIDATE_IP))) {
            return null;
        }

        return $origin;
    }
}
