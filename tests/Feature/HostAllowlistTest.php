<?php

namespace Tests\Feature;

use Illuminate\Contracts\Http\Kernel;
use Illuminate\Http\Middleware\TrustProxies;
use Illuminate\Http\Request;
use Tests\TestCase;

class HostAllowlistTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        config(['app.debug' => false]);
    }

    protected function tearDown(): void
    {
        Request::setTrustedHosts([]);
        TrustProxies::flushState();
        parent::tearDown();
    }

    public function test_exact_custom_hosts_and_app_url_work_without_trusting_subdomains_or_patterns(): void
    {
        config(['app.url' => 'https://monitor.example.test', 'security.allowed_hosts' => ['alias.example.test', '*.invalid', '^.*$', 'https://wrong.example.test']]);
        foreach (['localhost:8013', '127.0.0.1:8000', '[::1]:8000', 'MONITOR.example.test', 'alias.example.test'] as $host) {
            $this->get('http://'.$host.'/login')->assertOk();
        }
        foreach (['child.monitor.example.test', 'alias.example.test.invalid', 'other.invalid', 'wrong.example.test'] as $host) {
            $this->get('http://'.$host.'/login')->assertStatus(400);
        }
    }

    public function test_android_emulator_api_requires_an_explicit_host_allowlist_entry(): void
    {
        config(['app.url' => 'http://localhost:8000', 'security.allowed_hosts' => []]);
        foreach (['testing', 'local', 'production'] as $environment) {
            $this->app['env'] = $environment;
            $this->getJson('http://10.0.2.2:8000/api/v1/me')->assertStatus(400);
        }

        $this->app['env'] = 'local';
        config(['security.allowed_hosts' => ['10.0.2.2']]);
        $this->getJson('http://10.0.2.2:8000/api/v1/me')->assertUnauthorized();
        $this->getJson('http://audit.invalid:8000/api/v1/me')->assertStatus(400);
    }

    public function test_invalid_app_url_does_not_allow_an_empty_host(): void
    {
        config(['app.url' => 'not-a-url']);
        $request = Request::create('http://localhost/login');
        $request->headers->set('host', '');
        $request->server->set('SERVER_NAME', '');
        $response = $this->app->make(Kernel::class)->handle($request);
        $this->assertSame(400, $response->getStatusCode());
    }

    public function test_forwarded_hosts_cannot_bypass_the_allowlist(): void
    {
        config(['app.url' => 'http://localhost']);
        $this->withHeader('X-Forwarded-Host', 'audit.invalid')->get('/login')
            ->assertOk()->assertDontSee('audit.invalid');

        TrustProxies::at(['127.0.0.1']);
        TrustProxies::withHeaders(Request::HEADER_X_FORWARDED_HOST);
        $this->withHeader('X-Forwarded-Host', 'audit.invalid')->get('/login')->assertStatus(400);
        $this->withHeader('X-Forwarded-Host', 'localhost')->get('http://audit.invalid/login')->assertStatus(400);
    }

    public function test_unlisted_hosts_are_rejected_including_local_and_testing(): void
    {
        foreach (['testing', 'local', 'production'] as $environment) {
            $this->app['env'] = $environment;
            foreach (['/login', '/api/v1/me', '/not-found', '/up'] as $path) {
                $this->getJson('http://audit.invalid'.$path)->assertStatus(400)
                    ->assertDontSee('audit.invalid')->assertHeader('X-Content-Type-Options', 'nosniff');
            }
        }
    }
}
