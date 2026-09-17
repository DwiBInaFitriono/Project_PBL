<?php

namespace Tests\Feature;

use App\Models\SensorReading;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class RestApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_password_change_revokes_all_tokens_and_api_never_redirects_validation(): void
    {
        $user = User::factory()->create();
        $user->createToken('test', ['mobile:read']);
        $user->password = Hash::make('new-test-password');
        $user->save();
        $this->assertDatabaseCount('personal_access_tokens', 0);
        $this->post('/api/v1/auth/login', [])->assertUnprocessable()->assertJsonValidationErrors(['email', 'password']);
    }

    public function test_cors_is_limited_to_configured_flutter_origin(): void
    {
        $this->withHeaders(['Origin' => 'http://127.0.0.1:8091', 'Access-Control-Request-Method' => 'POST', 'Access-Control-Request-Headers' => 'content-type,authorization'])
            ->options('/api/v1/auth/login')->assertNoContent()->assertHeader('Access-Control-Allow-Origin', 'http://127.0.0.1:8091');
        $this->withHeaders(['Origin' => 'https://untrusted.example', 'Access-Control-Request-Method' => 'POST'])
            ->options('/api/v1/auth/login')->assertHeaderMissing('Access-Control-Allow-Origin');
    }

    public function test_private_routes_reject_guests_expired_revoked_wrong_scope_and_web_sessions(): void
    {
        $this->getJson('/api/v1/me')->assertUnauthorized();
        $user = User::factory()->create();
        $this->actingAs($user, 'web')->getJson('/api/v1/me')->assertUnauthorized();
        Auth::forgetGuards();
        $expired = $user->createToken('expired', ['mobile:read'], now()->subMinute());
        $this->withToken($expired->plainTextToken)->getJson('/api/v1/me')->assertUnauthorized();
        Auth::forgetGuards();
        $wrong = $user->createToken('wrong', ['unrelated']);
        $this->withToken($wrong->plainTextToken)->getJson('/api/v1/me')->assertForbidden();
        Auth::forgetGuards();
        $token = $user->createToken('valid', ['mobile:read']);
        $this->withToken($token->plainTextToken)->postJson('/api/v1/auth/logout')->assertNoContent();
        Auth::forgetGuards();
        $this->withToken($token->plainTextToken)->getJson('/api/v1/me')->assertUnauthorized();
    }

    public function test_login_errors_are_generic_and_throttled_without_plaintext_secrets(): void
    {
        $user = User::factory()->create();
        $payload = ['email' => $user->email, 'password' => 'wrong', 'device_name' => 'test'];
        $this->postJson('/api/v1/auth/login', [...$payload, 'email' => 'missing@example.test'])->assertUnauthorized()->assertJsonPath('message', 'Email atau kata sandi salah.');
        for ($i = 0; $i < 5; $i++) {
            $this->postJson('/api/v1/auth/login', $payload)->assertUnauthorized()->assertJsonPath('message', 'Email atau kata sandi salah.');
        }
        $this->postJson('/api/v1/auth/login', $payload)->assertStatus(429)->assertHeader('Retry-After');
        $this->assertDatabaseCount('personal_access_tokens', 0);
        $this->postJson('/api/v1/auth/login', ['email' => [], 'password' => str_repeat('a', 73)])->assertUnprocessable()->assertJsonValidationErrors(['email', 'password', 'device_name']);
    }

    public function test_api_monitoring_and_history_share_real_readings_and_enforce_operator_gate(): void
    {
        $this->travelTo(now()->setDate(2026, 9, 16)->setTime(12, 0));
        $user = User::factory()->create();
        $token = $user->createToken('test', ['mobile:read'])->plainTextToken;
        $reading = SensorReading::create(['node_id' => '1', 'sensor_id' => 'temperature', 'value' => 0, 'recorded_at' => now()->subMinute()]);
        $this->withToken($token)->getJson('/api/v1/monitoring')->assertOk()->assertJsonPath('data.nodes.0.sensors.0.value', 0)->assertJsonPath('data.nodes.1.sensors.0.value', null);
        $this->withToken($token)->getJson('/api/v1/history?node=1&sensor=temperature&from=2026-09-16&to=2026-09-16')->assertOk()->assertJsonPath('data.0.id', $reading->id)->assertJsonPath('meta.total', 1)->assertJsonPath('filters.timezone', 'Asia/Jakarta');
        $this->withToken($token)->getJson('/api/v1/history?node=9')->assertUnprocessable();
        $this->withToken($token)->getJson('/api/v1/settings/esp')->assertForbidden();
        Auth::forgetGuards();
        $user->forceFill(['role' => 'operator'])->save();
        $this->withToken($token)->getJson('/api/v1/settings/esp')->assertOk()->assertJsonPath('data.integration.hardware_connected', false);
        $this->withToken($token)->postJson('/api/v1/telemetry', [])->assertNotFound();
    }

    public function test_operator_can_login_and_use_bearer_without_changing_account(): void
    {
        $user = User::factory()->operator()->create();
        $before = $user->fresh()->getAttributes();
        $response = $this->postJson('/api/v1/auth/login', [
            'email' => ' '.strtoupper($user->email).' ', 'password' => 'password', 'device_name' => 'Flutter test',
        ])->assertOk()->assertJsonPath('user.role', 'operator')->assertJsonPath('token_type', 'Bearer');
        $token = $response->json('token');
        $this->assertNotEmpty($token);
        $this->assertNotEmpty($response->json('expires_at'));
        $this->assertSame($before, $user->fresh()->getAttributes());
        $this->assertStringNotContainsString($token, (string) $user->tokens()->first()->token);
        Auth::forgetGuards();
        $this->withToken($token)->getJson('/api/v1/me')->assertOk()->assertJsonPath('data.id', $user->id)->assertJsonMissingPath('data.password');
    }
}
