<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class AccessWarningTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
    }

    public function test_all_protected_pages_warn_guests_without_exposing_dashboard(): void
    {
        foreach (['/dashboard', '/nodes/1', '/nodes/2', '/history', '/history/export', '/settings/account', '/settings/esp'] as $path) {
            $this->get($path)->assertRedirect('/login')
                ->assertSessionHas('access_warning', 'Silakan masuk terlebih dahulu untuk mengakses halaman ini. Setelah masuk, Anda diarahkan ke Dashboard.');
        }
        $this->withCookie(session()->getName(), session()->getId())->get('/login')
            ->assertOk()->assertSeeText('Silakan masuk terlebih dahulu')
            ->assertDontSee('data-monitoring-root', false);
        $this->assertGuest();
    }

    public function test_forbidden_html_warns_and_redirects_to_dashboard_without_loop(): void
    {
        $this->actingAs(User::factory()->create());
        $this->get('/settings/esp')->assertRedirect('/dashboard')
            ->assertSessionHas('access_warning', 'Anda tidak memiliki izin untuk mengakses halaman atau tindakan ini. Anda telah diarahkan ke Dashboard.');
        $this->withCookie(session()->getName(), session()->getId())->get('/dashboard')
            ->assertOk()->assertSeeText('Anda tidak memiliki izin');
        $this->get('/settings/account')->assertOk();
    }

    public function test_global_handler_covers_new_forbidden_routes_and_unsafe_post_without_replaying_input(): void
    {
        Route::middleware('web')->post('/_test/forbidden', fn () => abort(403, 'secret policy detail'));
        $this->actingAs(User::factory()->create());
        $this->post('/_test/forbidden', ['password' => 'must-not-flash'])->assertRedirect('/dashboard')
            ->assertSessionHas('access_warning')->assertSessionMissing('_old_input.password');
    }

    public function test_json_access_errors_keep_status_and_provide_safe_redirect_metadata(): void
    {
        $this->getJson('/monitoring/data')->assertUnauthorized()
            ->assertJsonPath('redirect', '/login')->assertJsonPath('code', 'authentication_required');
        $this->actingAs(User::factory()->create());
        $this->getJson('/settings/esp')->assertForbidden()
            ->assertJsonPath('redirect', '/dashboard')->assertJsonPath('code', 'access_denied')
            ->assertHeader('Cache-Control', 'no-store, private');
    }

    public function test_expired_csrf_uses_warning_and_safe_get_not_reposting_credentials(): void
    {
        Route::middleware('web')->post('/_test/expired', fn () => abort(419));
        $this->post('/_test/expired', ['password' => 'must-not-flash'])->assertRedirect('/login')
            ->assertSessionHas('access_warning')->assertSessionMissing('_old_input.password');
        $this->actingAs(User::factory()->create());
        $this->postJson('/_test/expired')->assertStatus(419)->assertJsonPath('redirect', '/dashboard');
    }

    public function test_password_changed_elsewhere_invalidates_stale_session(): void
    {
        $user = User::factory()->create();
        $oldHash = $user->password;
        $user->password = 'changed-in-other-session';
        $user->save();
        $this->actingAs($user)->withSession(['password_hash_web' => $oldHash])
            ->get('/dashboard')->assertRedirect('/login')->assertSessionHas('access_warning');
        $this->assertGuest();
    }
}
