<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Vite;
use Tests\TestCase;

class ContentSecurityPolicyTest extends TestCase
{
    use RefreshDatabase;

    public function test_vite_development_origins_require_explicit_local_configuration(): void
    {
        $this->withoutVite();
        $this->app['env'] = 'local';
        $default = $this->get('/login')->headers->get('Content-Security-Policy');
        $this->assertStringNotContainsString('5173', $default);

        config(['security.vite_origin' => 'http://localhost:5173']);
        $response = $this->get('/login')->assertOk();
        $policy = $response->headers->get('Content-Security-Policy');
        $this->assertStringContainsString("connect-src 'self' http://localhost:5173 ws://localhost:5173", $policy);
        $this->assertStringContainsString('property="csp-nonce" nonce="', $response->getContent());
        $this->assertStringNotContainsString("'unsafe-inline'", $policy);
        $this->assertStringNotContainsString("'unsafe-eval'", $policy);

        $this->app['env'] = 'production';
        $this->assertStringNotContainsString('5173', $this->get('/login')->headers->get('Content-Security-Policy'));
        $this->app['env'] = 'local';
        foreach (['http://localhost:5173; script-src *', 'http://*.example.test', 'http://localhost:5173/path', 'https://user:pass@localhost:5173'] as $origin) {
            config(['security.vite_origin' => $origin]);
            $this->assertStringNotContainsString('5173', $this->get('/login')->headers->get('Content-Security-Policy'));
        }
    }

    public function test_html_uses_fresh_nonces_for_trusted_scripts_and_print_styles(): void
    {
        Vite::useHotFile(base_path('tests/Support/no-vite-hot-file'));
        $nonces = [];
        foreach (['/login', '/register', '/dashboard', '/nodes/1', '/history/export'] as $path) {
            if ($path === '/dashboard') {
                $this->actingAs(User::factory()->create());
            }
            $response = $this->get($path)->assertOk();
            $policy = $response->headers->get('Content-Security-Policy');
            $this->assertStringContainsString("default-src 'self'", $policy);
            $this->assertStringContainsString("script-src-attr 'none'", $policy);
            $this->assertStringNotContainsString("'unsafe-inline'", $policy);
            $this->assertStringNotContainsString("'unsafe-eval'", $policy);
            $this->assertSame(1, preg_match("/script-src 'self' 'nonce-([^']+)'/", $policy, $match));
            $nonce = $match[1];
            $this->assertNotContains($nonce, $nonces);
            $nonces[] = $nonce;
            preg_match_all('/<(script|style)\b([^>]*)>/i', $response->getContent(), $tags, PREG_SET_ORDER);
            $this->assertNotEmpty($tags);
            foreach ($tags as $tag) {
                $this->assertStringContainsString('nonce="'.$nonce.'"', $tag[2]);
            }
        }
    }
}
