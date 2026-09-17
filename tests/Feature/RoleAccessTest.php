<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Schema;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class RoleAccessTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
    }

    public function test_additive_role_migration_preserves_existing_accounts_as_viewers(): void
    {
        $migration = require database_path('migrations/2026_09_15_192453_add_role_to_users_table.php');
        $migration->down();
        $this->assertFalse(Schema::hasColumn('users', 'role'));

        $attributes = User::factory()->make(['name' => 'Operator'])->getAttributes();
        unset($attributes['role']);
        $id = DB::table('users')->insertGetId($attributes);
        $original = (array) DB::table('users')->find($id);

        $migration->up();

        $user = User::findOrFail($id);
        $this->assertSame('viewer', $user->role);
        $this->assertFalse($user->isOperator());
        $updated = $user->getAttributes();
        unset($updated['role']);
        $this->assertSame($original, $updated);
        $this->assertDatabaseCount('users', 1);
    }

    public function test_registration_ignores_a_submitted_operator_role(): void
    {
        $this->post('/register', [
            'name' => 'Operator',
            'email' => 'new-viewer@example.test',
            'password' => 'test-only-password',
            'password_confirmation' => 'test-only-password',
            'role' => 'operator',
        ])->assertRedirect('/dashboard')->assertSessionHasNoErrors();

        $user = User::sole();
        $this->assertSame('viewer', $user->role);
        $this->assertAuthenticatedAs($user);
        $this->getJson('/settings/esp')->assertForbidden();
    }

    public function test_role_is_not_mass_assignable(): void
    {
        $user = new User(['name' => 'Operator', 'role' => 'operator']);

        $this->assertSame(['name', 'email', 'password'], $user->getFillable());
        $this->assertSame('viewer', $user->role);
        $this->assertFalse($user->isOperator());
    }

    public function test_viewer_can_update_only_own_profile_without_promoting_any_account(): void
    {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();
        $otherOriginal = $otherUser->fresh()->getAttributes();

        $this->actingAs($user)->patch('/settings/account', [
            'name' => 'Nama Baru',
            'email' => 'updated-viewer@example.test',
            'current_password' => 'password',
            'role' => 'operator',
            'id' => $otherUser->id,
            'user_id' => $otherUser->id,
        ])->assertRedirect('/settings/account')->assertSessionHasNoErrors();

        $this->assertSame('viewer', $user->fresh()->role);
        $this->assertSame('Nama Baru', $user->fresh()->name);
        $this->assertSame('updated-viewer@example.test', $user->fresh()->email);
        $this->assertSame($otherOriginal, $otherUser->fresh()->getAttributes());
        $this->getJson('/settings/esp')->assertForbidden();
    }

    public function test_viewer_can_access_monitoring_and_history(): void
    {
        $this->actingAs(User::factory()->create());

        foreach (['/dashboard', '/nodes/1', '/nodes/2', '/monitoring/data', '/history', '/history/export', '/settings/account'] as $path) {
            $this->get($path)->assertOk();
        }
    }

    public function test_missing_role_denies_esp_access(): void
    {
        $user = User::factory()->make();
        $attributes = $user->getAttributes();
        unset($attributes['role']);
        $user->setRawAttributes($attributes);

        $this->assertFalse($user->isOperator());
        $this->assertFalse(Gate::forUser($user)->allows('manage-esp'));
        $this->actingAs($user)->getJson('/settings/esp')->assertForbidden();
    }

    #[DataProvider('roleLabels')]
    public function test_account_displays_read_only_role(string $role, string $label): void
    {
        $user = User::factory()->create(['role' => $role]);

        $this->actingAs($user)->get('/settings/account')->assertOk()
            ->assertSeeText('Peran akun')->assertSeeText($label)
            ->assertDontSee('name="role"', false);
    }

    /** @return array<string, array{string, string}> */
    public static function roleLabels(): array
    {
        return ['operator' => ['operator', 'Operator'], 'viewer' => ['viewer', 'Pemantau']];
    }

    public function test_cli_can_demote_operator_to_viewer(): void
    {
        $user = User::factory()->operator()->create();

        $this->artisan('users:set-role', [
            'email' => $user->email,
            'role' => 'viewer',
            '--no-interaction' => true,
        ])->expectsOutputToContain('Peran akun berhasil diubah menjadi Pemantau.')
            ->assertSuccessful();

        $this->assertSame('viewer', $user->fresh()->role);
        $this->assertFalse(Gate::forUser($user->fresh())->allows('manage-esp'));
    }

    public function test_cli_rejects_unknown_email_without_creating_an_account(): void
    {
        $user = User::factory()->create();
        $original = $user->fresh()->getAttributes();

        $this->artisan('users:set-role', [
            'email' => 'missing@example.test',
            'role' => 'operator',
            '--no-interaction' => true,
        ])->expectsOutputToContain('Akun tidak ditemukan. Tidak ada akun yang dibuat.')
            ->assertFailed();

        $this->assertSame($original, $user->fresh()->getAttributes());
        $this->assertDatabaseCount('users', 1);
    }

    #[DataProvider('invalidRoles')]
    public function test_cli_rejects_any_role_except_exact_operator_or_viewer(string $role): void
    {
        $user = User::factory()->create();
        $original = $user->fresh()->getAttributes();

        $this->artisan('users:set-role', [
            'email' => $user->email,
            'role' => $role,
            '--no-interaction' => true,
        ])->expectsOutputToContain('Peran harus operator atau viewer.')->assertFailed();

        $this->assertSame($original, $user->fresh()->getAttributes());
        $this->assertDatabaseCount('users', 1);
    }

    /** @return array<string, array{string}> */
    public static function invalidRoles(): array
    {
        return [
            'admin' => ['admin'],
            'uppercase operator' => ['Operator'],
            'uppercase viewer' => ['Viewer'],
            'leading whitespace' => [' operator'],
            'trailing whitespace' => ['viewer '],
            'empty' => [''],
            'numeric' => ['1'],
        ];
    }

    public function test_cli_can_explicitly_assign_operator_without_changing_credentials_or_other_users(): void
    {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();
        $original = $user->fresh()->getAttributes();
        $otherOriginal = $otherUser->fresh()->getAttributes();

        $this->artisan('users:set-role', [
            'email' => $user->email,
            'role' => 'operator',
            '--no-interaction' => true,
        ])->expectsOutputToContain('Peran akun berhasil diubah menjadi Operator.')
            ->doesntExpectOutputToContain($user->password)
            ->doesntExpectOutputToContain($user->remember_token)
            ->doesntExpectOutputToContain('password')
            ->assertSuccessful();

        $updated = $user->fresh()->getAttributes();
        $this->assertSame('operator', $updated['role']);
        unset($updated['role'], $updated['updated_at'], $original['role'], $original['updated_at']);
        $this->assertSame($original, $updated);
        $this->assertSame($otherOriginal, $otherUser->fresh()->getAttributes());
        $this->assertDatabaseCount('users', 2);
    }

    public function test_operator_factory_requires_an_explicit_state(): void
    {
        $this->assertFalse(User::factory()->make()->isOperator());
        $this->assertTrue(User::factory()->operator()->make()->isOperator());
        $this->assertTrue(User::factory()->operator()->create()->fresh()->isOperator());
    }

    public function test_only_exact_operator_role_can_manage_esp(): void
    {
        $operator = User::factory()->make(['role' => 'operator']);

        $this->assertTrue(Gate::forUser($operator)->allows('manage-esp'));
        $this->assertTrue($operator->isOperator());

        foreach (['viewer', null, '', 'Operator', 'admin', 'operator ', 1] as $role) {
            $user = User::factory()->make(['role' => $role]);
            $this->assertFalse($user->isOperator());
            $this->assertFalse(Gate::forUser($user)->allows('manage-esp'));
        }

        $this->assertFalse(Gate::allows('manage-esp'));
    }

    public function test_new_database_users_default_to_viewer(): void
    {
        $this->assertTrue(Schema::hasColumn('users', 'role'));

        $attributes = User::factory()->make()->getAttributes();
        unset($attributes['role']);
        DB::table('users')->insert($attributes);

        $this->assertSame('viewer', User::firstOrFail()->role);
        $this->assertSame('viewer', User::factory()->create()->fresh()->role);
    }

    public function test_unsaved_users_default_to_viewer(): void
    {
        $this->assertSame('viewer', (new User)->role);
        $this->assertSame('viewer', User::factory()->make()->role);
    }
}
