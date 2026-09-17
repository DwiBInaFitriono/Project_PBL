<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class ResponseSecurityTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        config(['app.debug' => false]);
    }

    #[DataProvider('unprotectedResponses')]
    public function test_security_headers_cover_responses_outside_the_web_route_pipeline(string $method, string $path, int $status): void
    {
        Route::get('/_security/early-error', fn () => abort(500));

        $response = $this->json($method, $path)->assertStatus($status)
            ->assertHeader('X-Content-Type-Options', 'nosniff')
            ->assertHeader('X-Frame-Options', 'DENY')
            ->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin')
            ->assertHeader('Permissions-Policy', 'camera=(), microphone=(), geolocation=()')
            ->assertHeader('Content-Security-Policy');

        $policy = $response->headers->get('Content-Security-Policy');
        foreach (["default-src 'self'", "base-uri 'self'", "object-src 'none'", "frame-ancestors 'none'", "form-action 'self'", "script-src-attr 'none'"] as $directive) {
            $this->assertStringContainsString($directive, $policy);
        }
    }

    #[DataProvider('nonCacheableResponses')]
    public function test_dynamic_responses_are_not_stored_even_without_an_authenticated_user(string $method, string $path, int $status): void
    {
        $this->json($method, $path)->assertStatus($status)->assertHeader('Cache-Control', 'no-store, private');
    }

    public static function nonCacheableResponses(): array
    {
        return [
            'login form with csrf token' => ['GET', '/login', 200],
            'registration form with csrf token' => ['GET', '/register', 200],
            'login validation error' => ['POST', '/login', 422],
            'api validation error' => ['POST', '/api/v1/auth/login', 422],
            'missing route' => ['GET', '/api/v1/not-found', 404],
            'method rejection' => ['GET', '/api/v1/auth/login', 405],
        ];
    }

    public function test_production_config_disables_debug_even_when_the_environment_flag_is_enabled(): void
    {
        $originalEnv = $_ENV;
        $originalServer = $_SERVER;

        try {
            $_ENV['APP_ENV'] = $_SERVER['APP_ENV'] = 'production';
            $_ENV['APP_DEBUG'] = $_SERVER['APP_DEBUG'] = 'true';
            $production = require config_path('app.php');
            $this->assertFalse($production['debug']);

            $_ENV['APP_ENV'] = $_SERVER['APP_ENV'] = 'local';
            $local = require config_path('app.php');
            $this->assertTrue($local['debug']);
        } finally {
            $_ENV = $originalEnv;
            $_SERVER = $originalServer;
        }
    }

    public function test_non_debug_server_errors_do_not_disclose_custom_exception_messages(): void
    {
        Route::get('/_security/custom-error', fn () => abort(500, 'INTERNAL_DATABASE_DETAIL'));

        $this->getJson('/_security/custom-error')->assertStatus(500)
            ->assertExactJson(['message' => 'Terjadi gangguan server. Silakan coba lagi nanti.'])
            ->assertDontSee('INTERNAL_DATABASE_DETAIL');
        $this->get('/_security/custom-error')->assertStatus(500)
            ->assertSee('Terjadi gangguan server')
            ->assertDontSee('INTERNAL_DATABASE_DETAIL')
            ->assertDontSee('Laravel')
            ->assertHeader('X-Content-Type-Options', 'nosniff');
    }

    public function test_sanitized_unavailable_response_preserves_retry_after(): void
    {
        Route::get('/_security/unavailable', fn () => abort(503, 'INTERNAL_SERVICE_DETAIL', ['Retry-After' => '60']));

        $this->getJson('/_security/unavailable')->assertStatus(503)
            ->assertHeader('Retry-After', '60')
            ->assertDontSee('INTERNAL_SERVICE_DETAIL');
    }

    public static function unprotectedResponses(): array
    {
        return [
            'web missing route' => ['GET', '/_security/not-found', 404],
            'api missing route' => ['GET', '/api/v1/not-found', 404],
            'web method rejection' => ['DELETE', '/login', 405],
            'api method rejection' => ['GET', '/api/v1/auth/login', 405],
            'web authentication rejection' => ['GET', '/dashboard', 401],
            'api authentication rejection' => ['GET', '/api/v1/me', 401],
            'server error' => ['GET', '/_security/early-error', 500],
            'health check' => ['GET', '/up', 200],
        ];
    }
}
