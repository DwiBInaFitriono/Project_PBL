<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class SnapshotBoundaryTest extends TestCase
{
    use RefreshDatabase;

    public function test_snapshot_routes_share_a_per_user_budget_even_for_invalid_requests(): void
    {
        $this->withoutVite();
        $this->freezeTime();
        $first = User::factory()->create();
        $second = User::factory()->create();
        $this->actingAs($first);
        $paths = ['/dashboard?sensor=invalid', '/nodes/1?hours=5', '/nodes/2?sensor[]=temperature', '/monitoring/data?node=invalid'];

        for ($attempt = 0; $attempt < 120; $attempt++) {
            $index = $attempt % count($paths);
            $this->getJson($paths[$index])->assertStatus($index === 3 ? 422 : 404);
        }

        $queries = [];
        DB::listen(function (QueryExecuted $query) use (&$queries): void {
            if (str_contains($query->sql, 'sensor_readings')) {
                $queries[] = $query->sql;
            }
        });
        foreach (['/dashboard', '/nodes/1', '/nodes/2', '/monitoring/data'] as $path) {
            $this->getJson($path)->assertStatus(429)->assertHeader('Retry-After');
        }
        $this->assertSame([], $queries);

        $this->actingAs($second)->getJson('/monitoring/data')->assertOk();
        $this->actingAs($first);
        $this->travel(61)->seconds();
        $this->getJson('/monitoring/data')->assertOk();
    }

    public function test_invalid_page_filters_are_rejected_before_sensor_queries(): void
    {
        $this->withoutVite();
        $this->actingAs(User::factory()->create());
        $queries = [];
        DB::listen(function (QueryExecuted $query) use (&$queries): void {
            if (str_contains($query->sql, 'sensor_readings')) {
                $queries[] = $query->sql;
            }
        });

        foreach (['/dashboard', '/nodes/1', '/nodes/2'] as $path) {
            foreach (['sensor=invalid', 'sensor[]=temperature', 'hours=5', 'hours[]=6'] as $query) {
                $this->getJson($path.'?'.$query)->assertNotFound();
            }
        }

        $this->assertSame([], $queries, 'Invalid filters must not query sensor readings.');
    }
}
