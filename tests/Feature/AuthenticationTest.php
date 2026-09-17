<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class AuthenticationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
    }

    public function test_registration_creates_an_authenticated_user_with_a_fresh_session(): void
    {
        $this->withSession(['_token' => 'old-token']);
        $sessionId = session()->getId();

        $response = $this->post('/register', [
            'name' => 'Dwi Bina',
            'email' => '  DWI@example.com  ',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ]);

        $response->assertRedirect('/dashboard')->assertSessionHasNoErrors();
        $this->assertDatabaseCount('users', 1);
        $user = User::sole();
        $this->assertSame('Dwi Bina', $user->name);
        $this->assertSame('dwi@example.com', $user->email);
        $this->assertTrue(Hash::check('password123', $user->password));
        $this->assertAuthenticatedAs($user);
        $this->assertNotSame($sessionId, session()->getId());
        $this->assertNotSame('old-token', session()->token());
    }

    public function test_login_normalizes_email_remembers_the_user_and_regenerates_the_session(): void
    {
        $user = User::factory()->create(['email' => 'dwi@example.com']);
        $this->withSession(['_token' => 'old-token']);
        $sessionId = session()->getId();

        $response = $this->post('/login', [
            'email' => '  DWI@example.com  ',
            'password' => 'password',
            'remember' => '1',
        ]);

        $response->assertRedirect('/dashboard')->assertSessionHasNoErrors();
        $response->assertCookie(auth()->guard()->getRecallerName());
        $this->assertAuthenticatedAs($user);
        $this->assertNotSame($sessionId, session()->getId());
        $this->assertNotSame('old-token', session()->token());
    }

    public function test_invalid_credentials_return_a_generic_indonesian_error_without_authentication(): void
    {
        User::factory()->create(['email' => 'dwi@example.com']);

        foreach (['dwi@example.com', 'missing@example.com'] as $email) {
            $this->from('/login')->post('/login', [
                'email' => $email,
                'password' => 'incorrect-password',
            ])->assertRedirect('/login')
                ->assertSessionHasErrors(['email' => 'Email atau kata sandi salah.'])
                ->assertSessionMissing('_old_input.password');

            $this->assertGuest();
        }
    }

    #[DataProvider('invalidLoginData')]
    public function test_login_rejects_invalid_input(array $input, array $errors): void
    {
        $response = $this->postJson('/login', $input)->assertUnprocessable()->assertJsonValidationErrors($errors);
        $this->assertNotSame('Email atau kata sandi salah.', $response->json('errors.email.0'));
        $this->assertGuest();
    }

    public static function invalidLoginData(): array
    {
        return [
            'required credentials' => [[], ['email', 'password']],
            'malformed email' => [['email' => 'invalid', 'password' => 'password'], ['email']],
            'array email' => [['email' => ['dwi@example.com'], 'password' => 'password'], ['email']],
            'array password' => [['email' => 'dwi@example.com', 'password' => ['password']], ['password']],
            'invalid remember' => [['email' => 'dwi@example.com', 'password' => 'password', 'remember' => 'not-a-boolean'], ['remember']],
        ];
    }

    public function test_registration_rejects_duplicate_normalized_email(): void
    {
        User::factory()->create(['email' => 'dwi@example.com']);

        $this->from('/register')->post('/register', [
            'name' => 'Another User',
            'email' => '  DWI@example.com  ',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ])->assertRedirect('/register')->assertSessionHasErrors('email')
            ->assertSessionMissing('_old_input.password')
            ->assertSessionMissing('_old_input.password_confirmation');

        $this->assertDatabaseCount('users', 1);
        $this->assertGuest();
    }

    #[DataProvider('invalidRegistrationData')]
    public function test_registration_rejects_invalid_input(array $input, array $errors): void
    {
        $this->postJson('/register', $input)->assertUnprocessable()->assertJsonValidationErrors($errors);
        $this->assertDatabaseCount('users', 0);
        $this->assertGuest();
    }

    public static function invalidRegistrationData(): array
    {
        $valid = [
            'name' => 'Dwi Bina',
            'email' => 'dwi@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ];

        return [
            'required fields' => [[], ['name', 'email', 'password']],
            'blank name' => [array_replace($valid, ['name' => '   ']), ['name']],
            'long name' => [array_replace($valid, ['name' => str_repeat('a', 256)]), ['name']],
            'malformed email' => [array_replace($valid, ['email' => 'invalid']), ['email']],
            'long email' => [array_replace($valid, ['email' => str_repeat('a', 250).'@example.com']), ['email']],
            'short password' => [array_replace($valid, ['password' => 'short', 'password_confirmation' => 'short']), ['password']],
            'mismatched confirmation' => [array_replace($valid, ['password_confirmation' => 'different']), ['password']],
            'missing confirmation' => [array_diff_key($valid, ['password_confirmation' => true]), ['password']],
        ];
    }

    #[DataProvider('guestPageData')]
    public function test_guests_can_view_authentication_pages(string $path, string $view): void
    {
        $this->get($path)->assertOk()->assertViewIs($view);
    }

    public static function guestPageData(): array
    {
        return [
            'root login' => ['/', 'auth.login'],
            'login' => ['/login', 'auth.login'],
            'register' => ['/register', 'auth.register'],
        ];
    }

    public function test_guests_are_redirected_from_dashboard_to_login(): void
    {
        $this->get('/dashboard')->assertRedirect('/login');
        $this->assertGuest();
    }

    public function test_logout_invalidates_the_session_and_regenerates_csrf_token(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user)->withSession(['_token' => 'old-token', 'private_data' => 'secret']);
        $sessionId = session()->getId();

        $this->post('/logout')->assertRedirect('/login')->assertSessionMissing('private_data');

        $this->assertGuest();
        $this->assertNotSame($sessionId, session()->getId());
        $this->assertNotSame('old-token', session()->token());
        $this->assertNotEmpty(session()->token());
    }

    public function test_guests_cannot_post_logout(): void
    {
        $this->withSession(['private_data' => 'retained']);
        $this->post('/logout')->assertRedirect('/login')->assertSessionHas('private_data', 'retained');
    }

    #[DataProvider('guestOnlyRouteData')]
    public function test_authenticated_users_are_redirected_from_guest_routes(string $method, string $path): void
    {
        $user = User::factory()->create();
        $this->actingAs($user)->call($method, $path)->assertRedirect('/dashboard');
        $this->assertAuthenticatedAs($user);
        $this->assertDatabaseCount('users', 1);
    }

    public static function guestOnlyRouteData(): array
    {
        return [
            ['GET', '/'],
            ['GET', '/login'],
            ['GET', '/register'],
            ['POST', '/login'],
            ['POST', '/register'],
        ];
    }

    public function test_login_is_throttled_after_five_failed_attempts_for_sixty_seconds(): void
    {
        $user = User::factory()->create(['email' => 'dwi@example.com']);
        $this->freezeTime();

        for ($attempt = 0; $attempt < 5; $attempt++) {
            $this->postJson('/login', [
                'email' => 'dwi@example.com',
                'password' => 'incorrect-password',
            ])->assertUnprocessable()->assertJsonValidationErrors([
                'email' => 'Email atau kata sandi salah.',
            ]);
        }

        $credentials = ['email' => '  DWI@example.com  ', 'password' => 'password'];
        $this->postJson('/login', $credentials)->assertUnprocessable()->assertJsonValidationErrors([
            'email' => 'Terlalu banyak percobaan masuk. Coba lagi dalam 60 detik.',
        ]);
        $this->assertGuest();

        $this->travel(59)->seconds();
        $this->postJson('/login', $credentials)->assertUnprocessable()->assertJsonValidationErrors([
            'email' => 'Terlalu banyak percobaan masuk. Coba lagi dalam 1 detik.',
        ]);
        $this->assertGuest();

        $this->travel(1)->seconds();
        $this->post('/login', $credentials)->assertRedirect('/dashboard');
        $this->assertAuthenticatedAs($user);
    }

    public function test_login_throttle_is_scoped_to_both_email_and_ip(): void
    {
        $user = User::factory()->create(['email' => 'dwi@example.com']);
        $otherUser = User::factory()->create(['email' => 'other@example.com']);

        for ($attempt = 0; $attempt < 5; $attempt++) {
            $this->withServerVariables(['REMOTE_ADDR' => '192.0.2.1'])->postJson('/login', [
                'email' => 'dwi@example.com',
                'password' => 'incorrect-password',
            ])->assertUnprocessable();
        }

        $this->post('/login', [
            'email' => 'other@example.com',
            'password' => 'password',
        ])->assertRedirect('/dashboard');
        $this->assertAuthenticatedAs($otherUser);
        $this->post('/logout')->assertRedirect('/login');

        $this->withServerVariables(['REMOTE_ADDR' => '192.0.2.2'])->post('/login', [
            'email' => 'dwi@example.com',
            'password' => 'password',
        ])->assertRedirect('/dashboard')->assertSessionHasNoErrors();
        $this->assertAuthenticatedAs($user);
    }

    public function test_successful_login_clears_previous_failed_attempts(): void
    {
        $user = User::factory()->create(['email' => 'dwi@example.com']);
        $this->freezeTime();

        for ($attempt = 0; $attempt < 4; $attempt++) {
            $this->postJson('/login', [
                'email' => $user->email,
                'password' => 'incorrect-password',
            ])->assertUnprocessable();
        }

        $this->post('/login', ['email' => $user->email, 'password' => 'password'])->assertRedirect('/dashboard');
        $this->post('/logout')->assertRedirect('/login');

        $this->postJson('/login', [
            'email' => $user->email,
            'password' => 'incorrect-password',
        ])->assertUnprocessable();

        $this->post('/login', ['email' => $user->email, 'password' => 'password'])
            ->assertRedirect('/dashboard')->assertSessionHasNoErrors();
        $this->assertAuthenticatedAs($user);
    }

    public function test_authenticated_users_can_view_the_dashboard(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->get('/dashboard')->assertOk()->assertViewIs('dashboard')
            ->assertSeeText($user->name)->assertSeeText($user->email)
            ->assertSeeText('monitoring rebung bambu melalui node 1 dan node 2')
            ->assertDontSeeText('pembelajaran');
    }

    public function test_logout_only_accepts_post_requests(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->get('/logout')->assertMethodNotAllowed();
        $this->assertAuthenticatedAs($user);
    }

    public function test_login_does_not_remember_the_user_when_remember_is_false(): void
    {
        $user = User::factory()->create();

        $this->post('/login', [
            'email' => $user->email,
            'password' => 'password',
            'remember' => '0',
        ])->assertRedirect('/dashboard')->assertCookieMissing(auth()->guard()->getRecallerName());

        $this->assertAuthenticatedAs($user);
    }

    public function test_validation_messages_are_in_indonesian(): void
    {
        $this->postJson('/login', [])->assertUnprocessable()->assertJsonValidationErrors([
            'email' => 'Alamat email wajib diisi.',
            'password' => 'Kata sandi wajib diisi.',
        ]);

        $this->postJson('/register', [
            'name' => 'Pengguna',
            'email' => 'invalid',
            'password' => 'short',
            'password_confirmation' => 'different',
        ])->assertUnprocessable()->assertJsonValidationErrors([
            'email' => 'Masukkan alamat email yang valid.',
            'password' => 'Kata sandi minimal 8 karakter.',
        ]);
    }

    #[DataProvider('unsafePasswords')]
    public function test_registration_rejects_passwords_that_bcrypt_cannot_safely_hash(string $password): void
    {
        $this->postJson('/register', [
            'name' => 'Pengguna',
            'email' => 'safe@example.test',
            'password' => $password,
            'password_confirmation' => $password,
        ])->assertUnprocessable()->assertJsonValidationErrors('password');

        $this->assertDatabaseCount('users', 0);
    }

    public static function unsafePasswords(): array
    {
        return [
            'long ascii' => [str_repeat('a', 73)],
            'long multibyte' => [str_repeat('é', 40)],
            'null byte' => ["abcdefgh\0suffix"],
        ];
    }

    public function test_login_rejects_a_different_password_with_the_same_bcrypt_prefix(): void
    {
        $password = str_repeat('a', 72);
        $user = User::factory()->create(['password' => $password]);

        $this->postJson('/login', [
            'email' => $user->email,
            'password' => $password.'different',
        ])->assertUnprocessable()->assertJsonValidationErrors('password');

        $this->assertGuest();
    }

    public function test_invalid_array_input_can_be_redisplayed_without_a_server_error(): void
    {
        $this->from('/register')->post('/register', [
            'name' => ['unexpected'],
            'email' => ['unexpected'],
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ])->assertRedirect('/register')->assertSessionHasErrors(['name', 'email']);

        $this->withCookie(session()->getName(), session()->getId())
            ->get('/register')
            ->assertOk()
            ->assertSeeText('Nama lengkap harus berupa teks.');
    }

    public function test_registration_hashes_hash_shaped_passwords_as_literal_plaintext(): void
    {
        $literalPassword = Hash::make('x');

        $this->post('/register', [
            'name' => 'Pengguna',
            'email' => 'literal@example.test',
            'password' => $literalPassword,
            'password_confirmation' => $literalPassword,
        ])->assertRedirect('/dashboard');

        $stored = User::sole()->password;
        $this->assertTrue(Hash::check($literalPassword, $stored));
        $this->assertFalse(Hash::check('x', $stored));
    }

    public function test_named_routes_resolve_to_the_expected_paths(): void
    {
        foreach ([
            'login' => '/login',
            'register' => '/register',
            'login.store' => '/login',
            'register.store' => '/register',
            'logout' => '/logout',
            'dashboard' => '/dashboard',
        ] as $name => $path) {
            $this->assertSame($path, route($name, absolute: false));
        }
    }
}
