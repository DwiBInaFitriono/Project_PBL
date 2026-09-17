<?php

namespace Tests\Feature;

use App\Models\User;
use App\Mqtt\TelemetryIngestor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Tests\TestCase;

class MqttRestFlowTest extends TestCase
{
    use RefreshDatabase;

    public function test_mqtt_ingestion_and_authenticated_rest_share_same_data_without_public_ingest(): void
    {
        $this->travelTo(now('UTC')->setDate(2026, 9, 16)->setTime(0, 30));
        $operator = User::factory()->operator()->create();
        $before = $operator->fresh()->getAttributes();
        $token = $this->postJson('/api/v1/auth/login', [
            'email' => $operator->email, 'password' => 'password', 'device_name' => 'Isolated flow test',
        ])->assertOk()->json('token');
        $payload = json_encode([
            'schema_version' => 1, 'message_id' => '2dcd02f8-3366-4814-b631-2a48d05d64a3', 'node_id' => '2',
            'recorded_at' => '2026-09-16T07:29:00+07:00',
            'readings' => ['temperature' => 0, 'air_humidity' => 73.5],
        ], JSON_THROW_ON_ERROR);
        $ingestor = app(TelemetryIngestor::class);
        $this->assertSame('accepted', $ingestor->ingest('rebung-pintar/v1/nodes/2/telemetry', $payload));
        $this->assertSame('duplicate', $ingestor->ingest('rebung-pintar/v1/nodes/2/telemetry', $payload));
        $this->assertDatabaseCount('sensor_readings', 2);
        $this->withToken($token)->getJson('/api/v1/monitoring?node=2')->assertOk()
            ->assertJsonCount(1, 'data.nodes')
            ->assertJsonPath('data.nodes.0.sensors.0.value', 0)
            ->assertJsonPath('data.nodes.0.sensors.1.value', 73.5)
            ->assertJsonPath('data.nodes.0.sensors.2.value', null)
            ->assertJsonPath('data.nodes.0.freshness', 'fresh');
        $this->withToken($token)->getJson('/api/v1/history?node=2&from=2026-09-16&to=2026-09-16')
            ->assertOk()->assertJsonPath('meta.total', 2)->assertJsonPath('data.0.recorded_at', '2026-09-16T00:29:00+00:00');
        $this->assertSame($before, $operator->fresh()->getAttributes());
        $this->withToken($token)->postJson('/api/v1/auth/logout')->assertNoContent();
        Auth::forgetGuards();
        $this->withToken($token)->getJson('/api/v1/monitoring')->assertUnauthorized();
        $this->assertDatabaseCount('personal_access_tokens', 0);
    }
}
