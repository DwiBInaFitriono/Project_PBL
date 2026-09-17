<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class ChangePasswordTest extends TestCase
{
    use RefreshDatabase;

    public function test_password_change_updates_only_current_user_and_rotates_session(): void
    {
        $this->withoutVite();
        $user = User::factory()->create();
        $other = User::factory()->create();
        $otherHash = $other->password;
        $oldToken = $user->remember_token;
        $this->actingAs($user)->withSession(['password_hash_web' => $user->password, '_token' => 'before']);
        $sessionId = session()->getId();
        $this->patch('/settings/password', [
            'current_password' => 'password', 'password' => 'New-test-password-42',
            'password_confirmation' => 'New-test-password-42', 'id' => $other->id, 'role' => 'operator',
        ])->assertRedirect('/settings/account')->assertSessionHasNoErrors()->assertSessionHas('password_status');
        $user->refresh();
        $this->assertTrue(Hash::check('New-test-password-42', $user->password));
        $this->assertFalse(Hash::check('password', $user->password));
        $this->assertSame($otherHash, $other->fresh()->password);
        $this->assertSame('viewer', $user->role);
        $this->assertNotSame($oldToken, $user->remember_token);
        $this->assertNotSame($sessionId, session()->getId());
        $this->assertNotSame('before', session()->token());
        $this->get('/settings/account')->assertOk();
        $this->assertAuthenticatedAs($user);
    }

    public function test_invalid_password_inputs_never_change_or_flash_passwords(): void
    {
        foreach ([
            ['wrong', 'long-enough-new', 'long-enough-new', 'current_password'],
            ['password', 'short', 'short', 'password'],
            ['password', 'long-enough-new', 'not-matching', 'password'],
            ['password', 'password', 'password', 'password'],
            ['password', str_repeat('a', 73), str_repeat('a', 73), 'password'],
            ['password', "long-password\0null", "long-password\0null", 'password'],
        ] as [$current, $new, $confirmation, $field]) {
            $user = User::factory()->create();
            $hash = $user->password;
            $this->actingAs($user)->withSession(['password_hash_web' => $hash]);
            $this->from('/settings/account')->patch('/settings/password', [
                'current_password' => $current, 'password' => $new, 'password_confirmation' => $confirmation,
            ])->assertRedirect('/settings/account')->assertSessionHasErrorsIn('changePassword', [$field])
                ->assertSessionMissing('_old_input.current_password')->assertSessionMissing('_old_input.password')
                ->assertSessionMissing('_old_input.password_confirmation');
            $this->assertSame($hash, $user->fresh()->password);
        }
    }

    public function test_hash_shaped_new_password_is_hashed_as_literal_and_old_login_no_longer_works(): void
    {
        $user = User::factory()->create();
        $literal = Hash::make('not-the-password');
        $this->actingAs($user)->patch('/settings/password', [
            'current_password' => 'password', 'password' => $literal, 'password_confirmation' => $literal,
        ])->assertRedirect('/settings/account');
        $this->assertTrue(Hash::check($literal, $user->fresh()->password));
        $this->assertFalse(Hash::check('not-the-password', $user->fresh()->password));
        $this->post('/logout');
        $this->postJson('/login', ['email' => $user->email, 'password' => 'password'])->assertUnprocessable();
        $this->post('/login', ['email' => $user->email, 'password' => $literal])->assertRedirect('/dashboard');
    }

    public function test_guests_cannot_change_passwords(): void
    {
        $this->patchJson('/settings/password', [])->assertUnauthorized();
        $this->patch('/settings/password', [])->assertRedirect('/login');
    }

    public function test_password_error_bag_is_separate_from_profile_and_fields_stay_blank(): void
    {
        $this->withoutVite();
        $this->actingAs(User::factory()->create());
        $this->from('/settings/account')->patch('/settings/password', [
            'current_password' => 'incorrect-secret', 'password' => 'new-secret-value', 'password_confirmation' => 'new-secret-value',
        ])->assertSessionHasErrorsIn('changePassword', ['current_password']);
        $response = $this->withCookie(session()->getName(), session()->getId())->get('/settings/account')->assertOk();
        $response->assertSee('change-current-password-error', false)
            ->assertDontSee('id="current_password-error"', false)
            ->assertDontSee('incorrect-secret', false)->assertDontSee('new-secret-value', false);
    }

    public function test_password_change_throttles_attempts_per_account(): void
    {
        $this->freezeTime();
        $user = User::factory()->create();
        $this->actingAs($user);
        $input = ['current_password' => 'wrong', 'password' => 'new-long-password', 'password_confirmation' => 'new-long-password'];
        for ($i = 0; $i < 5; $i++) {
            $this->patchJson('/settings/password', $input)->assertUnprocessable();
        }
        $this->patchJson('/settings/password', $input)->assertUnprocessable()
            ->assertJsonPath('errors.current_password.0', 'Terlalu banyak percobaan mengganti kata sandi. Coba lagi dalam 60 detik.');
    }
}
