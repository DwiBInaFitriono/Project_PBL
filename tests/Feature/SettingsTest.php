<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class SettingsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
    }

    public function test_guests_cannot_view_esp_settings(): void
    {
        $this->get('/settings/esp')->assertRedirect('/login');
    }

    public function test_viewers_cannot_view_esp_settings(): void
    {
        $this->actingAs(User::factory()->make())
            ->getJson('/settings/esp')->assertForbidden();
    }

    public function test_esp_settings_show_configured_nodes_without_claiming_a_device_connection(): void
    {
        $user = User::factory()->operator()->make();

        $response = $this->actingAs($user)->get('/settings/esp');

        $response->assertOk()->assertViewIs('settings.esp')
            ->assertSeeText('Setting ESP')
            ->assertSeeText('Node 1')->assertSeeText('Node 2')
            ->assertSeeText('Menunggu integrasi')
            ->assertSeeText('Parameter usulan')
            ->assertSeeText('Protokol komunikasi belum ditentukan')
            ->assertSeeText('belum dapat dikonfigurasi melalui aplikasi')
            ->assertDontSeeText('Hubungkan perangkat')
            ->assertDontSeeText('Simpan konfigurasi')
            ->assertDontSee($user->password, false)
            ->assertDontSee($user->remember_token, false);

        foreach (config('monitoring.sensors') as $sensor) {
            $response->assertSeeText($sensor['name'])->assertSeeText($sensor['unit']);
        }

        $this->assertSame('/settings/esp', route('settings.esp', absolute: false));
        $this->assertDatabaseCount('users', 0);
    }

    public function test_guests_cannot_update_account_settings(): void
    {
        $this->patch('/settings/account', [
            'name' => 'Intruder',
            'email' => 'intruder@example.test',
            'current_password' => 'password',
        ])->assertRedirect('/login');

        $this->assertDatabaseCount('users', 0);
    }

    public function test_account_update_changes_only_the_authenticated_users_name_and_normalized_email(): void
    {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();
        $passwordHash = $user->password;
        $rememberToken = $user->remember_token;
        $otherAttributes = $otherUser->fresh()->getAttributes();

        $this->actingAs($user)->patch('/settings/account', [
            'name' => '  Operator Baru  ',
            'email' => '  OPERATOR.BARU@EXAMPLE.TEST  ',
            'current_password' => 'password',
            'id' => $otherUser->id,
            'user_id' => $otherUser->id,
            'password' => 'a-new-password',
            'password_confirmation' => 'a-new-password',
            'remember_token' => 'replacement-token',
            'email_verified_at' => null,
            'is_admin' => true,
        ])->assertRedirect('/settings/account')->assertSessionHasNoErrors()
            ->assertSessionHas('status', 'Informasi akun berhasil diperbarui.')
            ->assertSessionMissing('_old_input.current_password');

        $user->refresh();
        $this->assertSame('Operator Baru', $user->name);
        $this->assertSame('operator.baru@example.test', $user->email);
        $this->assertSame($passwordHash, $user->password);
        $this->assertSame($rememberToken, $user->remember_token);
        $this->assertSame($otherAttributes, $otherUser->fresh()->getAttributes());
        $this->assertArrayNotHasKey('is_admin', $user->getAttributes());
        $this->assertAuthenticatedAs($user);
        $this->assertDatabaseCount('users', 2);
        $this->assertSame('/settings/account', route('settings.account.update', absolute: false));

        $this->get('/settings/account')->assertOk()
            ->assertSeeText('Informasi akun berhasil diperbarui.')
            ->assertSee('value="Operator Baru"', false)
            ->assertSee('value="operator.baru@example.test"', false)
            ->assertDontSee($passwordHash, false);
    }

    #[DataProvider('invalidCurrentPasswords')]
    public function test_account_update_requires_the_correct_current_password(mixed $password, string $message): void
    {
        $user = User::factory()->create();
        $original = $user->fresh()->getAttributes();

        $this->actingAs($user)->from('/settings/account')->patch('/settings/account', [
            'name' => 'Nama Percobaan',
            'email' => 'attempt@example.test',
            'current_password' => $password,
        ])->assertRedirect('/settings/account')
            ->assertSessionHasErrors(['current_password' => $message])
            ->assertSessionHasInput('name', 'Nama Percobaan')
            ->assertSessionHasInput('email', 'attempt@example.test')
            ->assertSessionMissing('_old_input.current_password');

        $this->assertSame($original, $user->fresh()->getAttributes());
        $this->withCookie(session()->getName(), session()->getId())
            ->get('/settings/account')->assertOk()->assertSeeText($message)
            ->assertSee('value="Nama Percobaan"', false)
            ->assertDontSee('value="incorrect-password"', false);
    }

    /** @return array<string, array{mixed, string}> */
    public static function invalidCurrentPasswords(): array
    {
        return [
            'missing' => [null, 'Kata sandi saat ini wajib diisi.'],
            'incorrect' => ['incorrect-password', 'Kata sandi saat ini tidak sesuai.'],
            'array' => [['password'], 'Kata sandi saat ini harus berupa teks.'],
        ];
    }

    public function test_account_update_rejects_another_accounts_normalized_email(): void
    {
        $user = User::factory()->create();
        $otherUser = User::factory()->create(['email' => 'taken@example.test']);
        $original = $user->fresh()->getAttributes();

        $this->actingAs($user)->from('/settings/account')->patch('/settings/account', [
            'name' => 'Nama Baru',
            'email' => '  TAKEN@EXAMPLE.TEST  ',
            'current_password' => 'password',
            'id' => $otherUser->id,
        ])->assertRedirect('/settings/account')
            ->assertSessionHasErrors(['email' => 'Alamat email sudah digunakan oleh akun lain.'])
            ->assertSessionHasInput('email', 'TAKEN@EXAMPLE.TEST')
            ->assertSessionMissing('_old_input.current_password');

        $this->assertSame($original, $user->fresh()->getAttributes());
    }

    public function test_account_update_allows_keeping_own_normalized_email(): void
    {
        $user = User::factory()->create(['email' => 'own@example.test']);

        $this->actingAs($user)->patch('/settings/account', [
            'name' => 'Nama Baru',
            'email' => '  OWN@EXAMPLE.TEST  ',
            'current_password' => 'password',
        ])->assertRedirect('/settings/account')->assertSessionHasNoErrors();

        $this->assertSame('own@example.test', $user->fresh()->email);
    }

    #[DataProvider('invalidAccountData')]
    public function test_account_validation_is_indonesian_and_never_writes_invalid_input(array $input, array $errors): void
    {
        $user = User::factory()->create();
        $original = $user->fresh()->getAttributes();

        $this->actingAs($user)->patchJson('/settings/account', array_replace([
            'name' => 'Nama Baru',
            'email' => 'valid@example.test',
            'current_password' => 'password',
        ], $input))->assertUnprocessable()->assertJsonValidationErrors($errors);

        $this->assertSame($original, $user->fresh()->getAttributes());
    }

    /** @return array<string, array{array<string, mixed>, array<string, string>}> */
    public static function invalidAccountData(): array
    {
        return [
            'blank name' => [['name' => '   '], ['name' => 'Nama lengkap wajib diisi.']],
            'array name' => [['name' => ['unexpected']], ['name' => 'Nama lengkap harus berupa teks.']],
            'long name' => [['name' => str_repeat('a', 256)], ['name' => 'Nama lengkap maksimal 255 karakter.']],
            'blank email' => [['email' => '   '], ['email' => 'Alamat email wajib diisi.']],
            'malformed email' => [['email' => 'invalid'], ['email' => 'Masukkan alamat email yang valid.']],
            'array email' => [['email' => ['unexpected']], ['email' => 'Masukkan alamat email yang valid.']],
            'long email' => [['email' => str_repeat('a', 250).'@example.test'], ['email' => 'Alamat email maksimal 254 karakter.']],
        ];
    }

    public function test_invalid_array_input_and_password_secrets_are_safe_after_redirect(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->from('/settings/account')->patch('/settings/account', [
            'name' => ['unexpected'],
            'email' => ['unexpected'],
            'current_password' => 'password',
            'password' => 'must-not-be-flashed',
            'password_confirmation' => 'must-not-be-flashed',
        ])->assertRedirect('/settings/account')->assertSessionHasErrors(['name', 'email'])
            ->assertSessionMissing('_old_input.current_password')
            ->assertSessionMissing('_old_input.password')
            ->assertSessionMissing('_old_input.password_confirmation');

        $this->withCookie(session()->getName(), session()->getId())->get('/settings/account')
            ->assertOk()->assertSeeText('Nama lengkap harus berupa teks.')
            ->assertDontSee('must-not-be-flashed')->assertDontSee($user->password, false);
    }

    public function test_account_updates_are_throttled_per_authenticated_user_for_sixty_seconds(): void
    {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();
        $this->freezeTime();
        $this->actingAs($user);

        for ($attempt = 0; $attempt < 5; $attempt++) {
            $this->patchJson('/settings/account', [
                'name' => 'Nama Baru',
                'email' => 'attempt'.$attempt.'@example.test',
                'current_password' => 'incorrect-password',
            ])->assertUnprocessable()->assertJsonValidationErrors([
                'current_password' => 'Kata sandi saat ini tidak sesuai.',
            ]);
        }

        $valid = ['name' => 'Nama Baru', 'email' => 'new@example.test', 'current_password' => 'password'];
        $this->from('/settings/account')->patch('/settings/account', $valid)
            ->assertRedirect('/settings/account')->assertSessionHasErrors([
                'current_password' => 'Terlalu banyak percobaan menyimpan. Coba lagi dalam 60 detik.',
            ])->assertSessionMissing('_old_input.current_password');
        $this->assertNotSame('new@example.test', $user->fresh()->email);

        $this->actingAs($otherUser)->patch('/settings/account', array_replace($valid, ['email' => $otherUser->email]))
            ->assertRedirect('/settings/account')->assertSessionHasNoErrors();

        $this->actingAs($user);
        $this->travel(59)->seconds();
        $this->patchJson('/settings/account', $valid)->assertUnprocessable()->assertJsonValidationErrors([
            'current_password' => 'Terlalu banyak percobaan menyimpan. Coba lagi dalam 1 detik.',
        ]);

        $this->travel(1)->seconds();
        $this->patch('/settings/account', $valid)->assertRedirect('/settings/account')->assertSessionHasNoErrors();
        $this->assertSame('new@example.test', $user->fresh()->email);
    }

    public function test_current_password_cannot_match_only_the_bcrypt_prefix(): void
    {
        $password = str_repeat('a', 72);
        $user = User::factory()->create(['password' => $password]);

        $this->actingAs($user)->patchJson('/settings/account', [
            'name' => 'Nama Baru',
            'email' => 'prefix@example.test',
            'current_password' => $password.'different',
        ])->assertUnprocessable()->assertJsonValidationErrors([
            'current_password' => 'Kata sandi maksimal 72 byte. Karakter khusus dapat memakai lebih dari satu byte.',
        ]);

        $this->assertNotSame('prefix@example.test', $user->fresh()->email);
    }

    public function test_guests_cannot_view_account_settings(): void
    {
        $this->get('/settings/account')->assertRedirect('/login');
    }

    public function test_account_settings_show_the_authenticated_profile_without_secrets(): void
    {
        $user = User::factory()->make(['name' => 'Operator Rebung', 'email' => 'operator@example.test']);

        $this->actingAs($user)->get('/settings/account')
            ->assertOk()->assertViewIs('settings.account')
            ->assertSeeText('Setting Akun')
            ->assertSee('value="Operator Rebung"', false)
            ->assertSee('value="operator@example.test"', false)
            ->assertSee('name="_token"', false)
            ->assertSee('name="_method" value="PATCH"', false)
            ->assertSee('name="current_password"', false)
            ->assertSee('autocomplete="current-password"', false)
            ->assertSeeText('Simpan perubahan')
            ->assertDontSee($user->password, false)
            ->assertDontSee($user->remember_token, false)
            ->assertSee('data-password-form', false)
            ->assertDontSeeText('Hapus akun');

        $this->assertSame('/settings/account', route('settings.account', absolute: false));
        $this->assertDatabaseCount('users', 0);
    }
}
