<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Middleware\TrustProxies;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Exception\SuspiciousOperationException;
use Symfony\Component\HttpFoundation\Response;

class EnforceAllowedHosts extends TrustProxies
{
    public function handle(Request $request, Closure $next): Response
    {
        try {
            return parent::handle($request, function (Request $request) use ($next): Response {
                $allowed = ['localhost', '127.0.0.1', '[::1]'];
                $hosts = [...config('security.allowed_hosts', []), parse_url(config('app.url'), PHP_URL_HOST)];
                foreach ($hosts as $host) {
                    if (is_string($host) && (filter_var($host, FILTER_VALIDATE_DOMAIN, FILTER_FLAG_HOSTNAME)
                        || filter_var(trim($host, '[]'), FILTER_VALIDATE_IP))) {
                        $allowed[] = strtolower($host);
                    }
                }
                $direct = new Request(server: ['HTTP_HOST' => $request->headers->get('host', '')]);
                if (! in_array($direct->getHost(), $allowed, true) || ! in_array($request->getHost(), $allowed, true)) {
                    return response('Host tidak diizinkan.', 400);
                }

                return $next($request);
            });
        } catch (SuspiciousOperationException) {
            return response('Host tidak diizinkan.', 400);
        }
    }
}
