<?php

namespace Tests\Feature;

use App\Http\Controllers\MonitoringDataController;
use App\Models\SensorReading;
use App\Models\User;
use App\Monitoring\MonitoringSnapshot;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class MonitoringDataTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        if (! Route::has('monitoring.data')) {
            Route::middleware(['web', 'auth'])->get('/monitoring/data', MonitoringDataController::class.'@__invoke')->name('monitoring.data');
        }
    }

    public function test_both_roles_can_read_monitoring(): void
    {
        foreach ([User::factory()->create(), User::factory()->operator()->create()] as $user) {
            $this->actingAs($user)->getJson('/monitoring/data')->assertOk()->assertJsonCount(2, 'nodes');
        }
    }

    public function test_monitoring_endpoint_is_private_authenticated_and_filters_nodes(): void
    {
        $this->getJson('/monitoring/data')->assertUnauthorized();
        config(['monitoring.stale_after_seconds' => 60, 'monitoring.poll_interval_seconds' => 20]);
        $this->freezeTime();
        SensorReading::factory()->create(['node_id' => '2', 'sensor_id' => 'temperature', 'value' => 0, 'recorded_at' => now()->subSeconds(60)]);
        $user = User::factory()->create();

        $response = $this->actingAs($user)->getJson('/monitoring/data?node=2');

        $response->assertOk()->assertJsonCount(1, 'nodes')
            ->assertJsonPath('nodes.0.id', '2')->assertJsonPath('nodes.0.freshness', 'fresh')
            ->assertJsonPath('staleAfterSeconds', 60)->assertJsonPath('pollIntervalSeconds', 20)
            ->assertJsonMissingPath('monitoring')->assertJsonMissingPath('user')
            ->assertDontSee($user->email);
        $this->assertStringContainsString('private', $response->headers->get('Cache-Control'));
        $this->assertStringContainsString('no-store', $response->headers->get('Cache-Control'));
        $this->travel(1)->seconds();
        $this->getJson('/monitoring/data?node=2')->assertJsonPath('nodes.0.freshness', 'stale');
        foreach (['node=3', 'node[]=1', 'node[foo]=2'] as $query) {
            $this->get('/monitoring/data?'.$query)->assertUnprocessable()->assertJsonValidationErrors('node');
        }
    }

    public function test_chart_is_bounded_to_latest_500_points_within_24_hours_per_sensor(): void
    {
        $this->travelTo(Carbon::parse('2026-09-16 12:00:00', 'UTC'));
        $rows = [];
        for ($index = 0; $index < 502; $index++) {
            $rows[] = ['node_id' => '2', 'sensor_id' => 'temperature', 'value' => $index, 'recorded_at' => now()->subSeconds(502 - $index)];
        }
        DB::table('sensor_readings')->insert($rows);
        SensorReading::factory()->create(['node_id' => '2', 'sensor_id' => 'air_humidity', 'recorded_at' => now()->subDay()]);
        SensorReading::factory()->create(['node_id' => '2', 'sensor_id' => 'air_humidity', 'recorded_at' => now()->subDay()->subSecond()]);

        $data = app(MonitoringSnapshot::class)->forNode('2');

        $this->assertCount(1, $data['nodes']);
        $this->assertSame(3, $data['sensorCount']);
        $this->assertSame('2', $data['nodes'][0]['id']);
        $points = $data['nodes'][0]['sensors'][0]['readings'];
        $this->assertCount(500, $points);
        $this->assertSame(2.0, $points[0]['value']);
        $this->assertSame(501.0, $points[499]['value']);
        $this->assertCount(1, $data['nodes'][0]['sensors'][1]['readings']);
    }

    public function test_snapshot_uses_latest_valid_readings_without_promoting_missing_sensors(): void
    {
        $this->travelTo(Carbon::parse('2026-09-16 12:00:00', 'UTC'));
        SensorReading::factory()->create(['node_id' => '1', 'sensor_id' => 'temperature', 'value' => 25, 'recorded_at' => now()->subMinutes(6)]);
        SensorReading::factory()->create(['node_id' => '1', 'sensor_id' => 'temperature', 'value' => 0, 'recorded_at' => now()->subSeconds(30)]);
        SensorReading::factory()->create(['node_id' => '1', 'sensor_id' => 'temperature', 'value' => 99, 'recorded_at' => now()->addSecond()]);
        SensorReading::factory()->create(['node_id' => '2', 'sensor_id' => 'air_humidity', 'value' => 61, 'recorded_at' => now()->subDays(2)]);
        foreach (['NaN', 'not-a-number', 'INF', '1e9999'] as $invalid) {
            DB::table('sensor_readings')->insert(['node_id' => '1', 'sensor_id' => 'temperature', 'value' => $invalid, 'recorded_at' => now()]);
        }
        SensorReading::factory()->create(['node_id' => '1', 'sensor_id' => 'unknown', 'value' => 3]);

        $data = app(MonitoringSnapshot::class)->forNode();

        $this->assertSame('Data terbaru', $data['nodes'][0]['status']);
        $this->assertSame('fresh', $data['nodes'][0]['freshness']);
        $this->assertSame(30, $data['nodes'][0]['age_seconds']);
        $sensor = $data['nodes'][0]['sensors'][0];
        $this->assertSame(0.0, $sensor['value']);
        $this->assertSame(now()->subSeconds(30)->toIso8601String(), $sensor['last_reading']);
        $this->assertSame([
            ['recorded_at' => now()->subMinutes(6)->toIso8601String(), 'value' => 25.0],
            ['recorded_at' => now()->subSeconds(30)->toIso8601String(), 'value' => 0.0],
        ], $sensor['readings']);
        $this->assertNull($data['nodes'][0]['sensors'][1]['last_reading']);
        $this->assertNull($data['nodes'][0]['sensors'][1]['value']);
        $this->assertSame('Data terlambat', $data['nodes'][1]['status']);
        $this->assertSame('stale', $data['nodes'][1]['freshness']);
        $this->assertSame(61.0, $data['nodes'][1]['sensors'][1]['value']);
        $this->assertSame([], $data['nodes'][1]['sensors'][1]['readings']);
        $this->assertSame(now()->subDays(2)->toIso8601String(), $data['nodes'][1]['sensors'][1]['last_reading']);
    }

    public function test_empty_snapshot_reports_unavailable_data_without_inventing_values(): void
    {
        $this->freezeTime();

        $data = app(MonitoringSnapshot::class)->forNode();

        $this->assertSame(6, $data['sensorCount']);
        $this->assertSame(now()->toIso8601String(), $data['monitoring']['generatedAt']);
        $this->assertSame(300, $data['monitoring']['staleAfterSeconds'] ?? null);
        $this->assertSame(15, $data['monitoring']['pollIntervalSeconds'] ?? null);
        foreach ($data['nodes'] as $node) {
            $this->assertSame('Menunggu integrasi', $node['status']);
            $this->assertSame('unavailable', $node['freshness']);
            $this->assertNull($node['last_reading']);
            $this->assertNull($node['age_seconds']);
            foreach ($node['sensors'] as $sensor) {
                $this->assertNull($sensor['value']);
                $this->assertNull($sensor['last_reading']);
                $this->assertSame([], $sensor['readings']);
            }
        }
    }
}
