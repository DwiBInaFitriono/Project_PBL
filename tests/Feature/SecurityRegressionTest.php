<?php

namespace Tests\Feature;

use App\Models\SensorReading;
use App\Models\User;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class EnforcedCsrfForSecurityTest extends ValidateCsrfToken
{
    protected function runningUnitTests(): bool
    {
        return false;
    }
}

class SecurityRegressionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
    }

    public function test_sql_payloads_do_not_bypass_login_or_history_filters(): void
    {
        User::factory()->create(['email' => 'owner@example.test']);
        foreach (["' OR 1=1 --", "owner@example.test'--", 'owner@example.test'] as $email) {
            $this->postJson('/login', ['email' => $email, 'password' => "' OR 1=1 --"])->assertUnprocessable();
            $this->assertGuest();
        }
        $this->actingAs(User::first());
        SensorReading::factory()->create(['node_id' => '1', 'sensor_id' => 'temperature']);
        foreach (['node' => "1' OR 1=1--", 'sensor' => "temperature'; DROP TABLE users;--", 'from' => "2026-01-01' OR '1'='1", 'page' => '1 UNION SELECT 1'] as $key => $value) {
            $this->getJson('/history?'.http_build_query([$key => $value]))->assertUnprocessable();
            $this->getJson('/history/export?'.http_build_query([$key => $value]))->assertUnprocessable();
        }
        $this->assertDatabaseCount('users', 1);
        $this->assertDatabaseCount('sensor_readings', 1);
    }

    public function test_profile_and_warning_markup_escape_untrusted_strings(): void
    {
        $payload = '<img src=x onerror=alert(1)>';
        $this->actingAs(User::factory()->create(['name' => $payload]));
        foreach (['/dashboard', '/settings/account'] as $path) {
            $this->get($path)->assertOk()->assertDontSee($payload, false)->assertSee(e($payload), false);
        }
        $this->withSession(['access_warning' => '<script>alert(1)</script>'])->get('/dashboard')
            ->assertDontSee('<script>alert(1)</script>', false)->assertSee('&lt;script&gt;alert(1)&lt;/script&gt;', false);
    }

    public function test_real_csrf_validation_rejects_missing_token_before_side_effect(): void
    {
        Route::middleware(['web', EnforcedCsrfForSecurityTest::class])->post('/_security/csrf', function () {
            User::factory()->create();

            return response()->noContent();
        });
        $this->withSession(['_token' => 'known-test-csrf']);
        $this->postJson('/_security/csrf')->assertStatus(419);
        $this->assertDatabaseCount('users', 0);
        $this->postJson('/_security/csrf', ['_token' => 'wrong'])->assertStatus(419);
        $this->assertDatabaseCount('users', 0);
        $this->postJson('/_security/csrf', ['_token' => 'known-test-csrf'])->assertNoContent();
        $this->assertDatabaseCount('users', 1);
    }

    public function test_security_headers_restrict_framing_and_content_sniffing(): void
    {
        foreach (['/login', '/register'] as $path) {
            $this->get($path)->assertOk()->assertHeader('X-Content-Type-Options', 'nosniff')
                ->assertHeader('X-Frame-Options', 'DENY')
                ->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin');
        }
    }

    public function test_login_fingerprint_revokes_session_even_before_first_dashboard_visit(): void
    {
        $user = User::factory()->create();
        $this->post('/login', ['email' => $user->email, 'password' => 'password'])
            ->assertRedirect('/dashboard')->assertSessionHas('password_hash_web');
        $sessionId = session()->getId();
        $sessionName = session()->getName();
        User::whereKey($user->id)->update(['password' => Hash::make('another-device-password')]);
        Auth::forgetGuards();
        $this->withCookie($sessionName, $sessionId)->get('/dashboard')->assertRedirect('/login');
        $this->assertGuest();
    }

    public function test_legacy_session_without_fingerprint_must_reauthenticate(): void
    {
        $user = User::factory()->create();
        $this->withSession([auth()->guard()->getName() => $user->id]);
        $this->get('/dashboard')->assertRedirect('/login');
        $this->assertGuest();
    }

    public function test_registration_attempts_are_throttled_by_ip(): void
    {
        for ($i = 0; $i < 10; $i++) {
            $this->postJson('/register', [])->assertUnprocessable();
        }
        $this->postJson('/register', [])->assertStatus(429);
        $this->assertDatabaseCount('users', 0);
    }

    public function test_exports_are_throttled_per_user(): void
    {
        $this->actingAs(User::factory()->create());
        for ($i = 0; $i < 5; $i++) {
            $this->getJson('/history/export?node=invalid')->assertUnprocessable();
        }
        $this->getJson('/history/export?node=invalid')->assertStatus(429);
    }

    public function test_rotating_email_addresses_cannot_bypass_login_ip_limit(): void
    {
        for ($attempt = 0; $attempt < 20; $attempt++) {
            $this->postJson('/login', ['email' => 'unknown'.$attempt.'@example.test', 'password' => 'invalid-password'])
                ->assertUnprocessable();
        }

        $this->postJson('/login', ['email' => 'another@example.test', 'password' => 'invalid-password'])
            ->assertStatus(429)->assertHeader('Retry-After');
        $this->assertDatabaseCount('users', 0);
    }

    public function test_repeated_web_monitoring_reads_are_limited_without_blocking_normal_polling(): void
    {
        $this->actingAs(User::factory()->create());
        for ($attempt = 0; $attempt < 120; $attempt++) {
            $this->getJson('/monitoring/data')->assertOk();
        }

        $this->getJson('/monitoring/data')->assertStatus(429)->assertHeader('Retry-After');
    }

    public function test_redirects_ignore_external_destinations_and_post_input(): void
    {
        $this->get('/settings/account?redirect=https://evil.example')->assertRedirect('/login');
        $this->actingAs(User::factory()->create());
        $this->get('/settings/esp?next=//evil.example')->assertRedirect('/dashboard');
    }
}
