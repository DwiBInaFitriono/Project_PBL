<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Timebox;
use Tests\TestCase;

class ApiSecurityTest extends TestCase
{
    use RefreshDatabase;

    public function test_missing_account_still_performs_password_hash_verification(): void
    {
        $hasher = Hash::getFacadeRoot();
        $checked = [];
        Hash::shouldReceive('make')->once()->andReturnUsing(fn (string $value): string => $hasher->make($value));
        Hash::shouldReceive('check')->once()->andReturnUsing(function (string $plain, string $hash) use ($hasher, &$checked): bool {
            $checked[] = [$plain, $hash];

            return $hasher->check($plain, $hash);
        });
        $this->postJson('/api/v1/auth/login', ['email' => 'absent@example.test', 'password' => 'invalid', 'device_name' => 'test'])->assertUnauthorized();
        $this->assertCount(1, $checked);
    }

    public function test_password_and_token_revocation_roll_back_together_when_delete_fails(): void
    {
        $user = User::factory()->create();
        $before = $user->fresh()->getAttributes();
        $user->createToken('test', ['mobile:read']);
        DB::unprepared("CREATE TRIGGER reject_token_delete BEFORE DELETE ON personal_access_tokens BEGIN SELECT RAISE(ABORT, 'test revocation failure'); END");
        try {
            $user->password = Hash::make('new-test-secret');
            $user->save();
            $this->fail('Expected injected deletion failure.');
        } catch (QueryException) {
            $this->assertSame($before, $user->fresh()->getAttributes());
            $this->assertDatabaseCount('personal_access_tokens', 1);
        }
    }

    public function test_unknown_and_known_login_failures_use_the_same_timebox(): void
    {
        $user = User::factory()->create();
        $box = new class extends Timebox
        {
            public array $durations = [];

            public function call(callable $callback, int $microseconds): mixed
            {
                $this->durations[] = $microseconds;

                return $callback($this);
            }
        };
        $this->app->instance(Timebox::class, $box);
        foreach ([$user->email, 'absent@example.test'] as $email) {
            $this->postJson('/api/v1/auth/login', ['email' => $email, 'password' => 'invalid', 'device_name' => 'test'])->assertUnauthorized();
        }
        $this->assertCount(2, $box->durations);
        $this->assertGreaterThanOrEqual(200000, $box->durations[0]);
        $this->assertSame($box->durations[0], $box->durations[1]);
    }

    public function test_sqlite_login_acquires_write_lock_before_loading_credentials(): void
    {
        $user = User::factory()->create();
        $queries = [];
        DB::listen(function ($query) use (&$queries): void {
            $queries[] = strtolower($query->sql);
        });
        $this->postJson('/api/v1/auth/login', ['email' => $user->email, 'password' => 'password', 'device_name' => 'test'])->assertOk();
        $lock = null;
        $read = null;
        foreach ($queries as $index => $sql) {
            if ($lock === null && str_starts_with($sql, 'update "users"') && str_contains($sql, '"id" = "id"')) {
                $lock = $index;
            }
            if ($read === null && str_starts_with($sql, 'select * from "users"')) {
                $read = $index;
            }
        }
        $this->assertNotNull($lock, 'SQLite must serialize issuance before reading password.');
        $this->assertNotNull($read);
        $this->assertLessThan($read, $lock);
    }
}
