<?php

namespace Tests\Feature;

use App\Models\SensorReading;
use App\Monitoring\MonitoringSnapshot;
use App\Mqtt\InvalidTelemetry;
use App\Mqtt\TelemetryIngestor;
use Carbon\CarbonImmutable;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class MqttIngestionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->assertSame(':memory:', config('database.connections.sqlite.database'));
        $this->travelTo(Carbon::parse('2026-09-16 00:00:00', 'UTC'));
    }

    public function test_valid_telemetry_is_persisted_and_visible_in_monitoring(): void
    {
        $result = app(TelemetryIngestor::class)->ingest($this->topic(), $this->payload());

        $this->assertSame('accepted', $result);
        $this->assertDatabaseCount('mqtt_messages', 1);
        $this->assertDatabaseCount('sensor_readings', 3);
        $this->assertSame(27.5, SensorReading::where('sensor_id', 'temperature')->firstOrFail()->value);
        $this->assertSame(27.5, app(MonitoringSnapshot::class)->forNode('1')['nodes'][0]['sensors'][0]['value']);
    }

    public function test_repeated_message_is_a_no_write_duplicate_scoped_to_node(): void
    {
        $ingestor = app(TelemetryIngestor::class);
        $ingestor->ingest($this->topic(), $this->payload());
        $before = SensorReading::all()->toArray();

        $this->assertSame('duplicate', $ingestor->ingest($this->topic(), $this->payload()));
        $this->assertSame($before, SensorReading::all()->toArray());
        $this->assertDatabaseCount('mqtt_messages', 1);
        $this->assertSame('accepted', $ingestor->ingest($this->topic('2'), $this->payload(['node_id' => '2'])));
        $this->assertDatabaseCount('mqtt_messages', 2);
        $this->assertDatabaseCount('sensor_readings', 6);
    }

    #[DataProvider('invalidEnvelopes')]
    public function test_invalid_envelope_is_rejected_without_partial_writes(string $topic, string $payload, bool $retained = false): void
    {
        try {
            app(TelemetryIngestor::class)->ingest($topic, $payload, $retained);
            $this->fail('Invalid telemetry was accepted.');
        } catch (InvalidTelemetry $exception) {
            $this->assertNotSame('', $exception->getMessage());
        }

        $this->assertDatabaseCount('mqtt_messages', 0);
        $this->assertDatabaseCount('sensor_readings', 0);
    }

    /** @return array<string, array{string, string, bool}> */
    public static function invalidEnvelopes(): array
    {
        $topic = 'rebung-pintar/v1/nodes/1/telemetry';
        $valid = [
            'schema_version' => 1,
            'message_id' => '5ef03860-30ae-44b2-a946-2ec66b397510',
            'node_id' => '1',
            'recorded_at' => '2026-09-16T00:00:00Z',
            'readings' => ['temperature' => 27.5, 'air_humidity' => 70],
        ];
        $json = json_encode($valid, JSON_THROW_ON_ERROR);
        $cases = [
            'empty' => [$topic, '', false],
            'broken json' => [$topic, '{', false],
            'array' => [$topic, '[]', false],
            'null' => [$topic, 'null', false],
            'oversized' => [$topic, $json.str_repeat(' ', 8192), false],
            'retained' => [$topic, $json, true],
            'topic mismatch' => ['rebung-pintar/v1/nodes/2/telemetry', $json, false],
            'topic suffix' => [$topic.'/extra', $json, false],
            'topic prefix' => ['other/'.$topic, $json, false],
            'nonfinite number' => [$topic, str_replace('27.5', '1e9999', $json), false],
            'NaN token' => [$topic, str_replace('27.5', 'NaN', $json), false],
        ];
        foreach ([
            'version' => ['schema_version' => 2],
            'string version' => ['schema_version' => '1'],
            'node' => ['node_id' => '3'],
            'integer node' => ['node_id' => 1],
            'uuid' => ['message_id' => 'invalid'],
            'blank uuid' => ['message_id' => ''],
            'unknown sensor' => ['readings' => ['temperature' => 27, 'unknown' => 3]],
            'empty readings' => ['readings' => (object) []],
            'list readings' => ['readings' => [1, 2]],
            'null reading' => ['readings' => ['temperature' => 27, 'air_humidity' => null]],
            'string reading' => ['readings' => ['temperature' => '27.5']],
            'bool reading' => ['readings' => ['temperature' => true]],
            'object reading' => ['readings' => ['temperature' => (object) ['value' => 27]]],
            'unknown field' => ['password' => 'payload-secret'],
        ] as $name => $overrides) {
            $cases[$name] = [$topic, json_encode(array_replace($valid, $overrides), JSON_THROW_ON_ERROR), false];
        }
        foreach (array_keys($valid) as $key) {
            $missing = $valid;
            unset($missing[$key]);
            $cases['missing '.$key] = [$topic, json_encode($missing, JSON_THROW_ON_ERROR), false];
        }

        return $cases;
    }

    #[DataProvider('invalidTimestamps')]
    public function test_invalid_time_is_rejected_before_database_writes(mixed $timestamp): void
    {
        try {
            app(TelemetryIngestor::class)->ingest($this->topic(), $this->payload(['recorded_at' => $timestamp]));
            $this->fail('Invalid timestamp was accepted.');
        } catch (InvalidTelemetry) {
            $this->assertDatabaseCount('mqtt_messages', 0);
            $this->assertDatabaseCount('sensor_readings', 0);
        }
    }

    /** @return array<string, array{mixed}> */
    public static function invalidTimestamps(): array
    {
        return [
            'no timezone' => ['2026-09-16T00:00:00'],
            'impossible day' => ['2026-02-30T00:00:00Z'],
            'impossible hour' => ['2026-09-15T25:00:00Z'],
            'future' => ['2026-09-16T00:01:01Z'],
            'fraction future' => ['2026-09-16T00:01:00.000001Z'],
            'unknown offset' => ['2026-09-16T00:00:00-00:00'],
            'invalid offset' => ['2026-09-16T00:00:00+25:00'],
            'no time' => ['2026-09-16'],
            'blank' => [''],
            'null' => [null],
            'number' => [123],
            'natural language' => ['yesterday'],
        ];
    }

    public function test_timezone_rollover_and_future_boundary_are_normalized_to_utc(): void
    {
        app(TelemetryIngestor::class)->ingest($this->topic(), $this->payload([
            'recorded_at' => '2026-09-16T00:30:00.123456+07:00',
            'readings' => ['temperature' => 0],
        ]));
        $reading = SensorReading::firstOrFail();
        $this->assertSame('2026-09-15T17:30:00+00:00', $reading->recorded_at->toIso8601String());
        $this->assertSame(0.0, $reading->value);
        $this->assertInstanceOf(CarbonImmutable::class, $reading->recorded_at);

        $this->assertSame('accepted', app(TelemetryIngestor::class)->ingest($this->topic('2'), $this->payload([
            'node_id' => '2', 'recorded_at' => '2026-09-16T00:01:00Z',
        ])));
    }

    public function test_storage_failure_rolls_back_message_and_all_sensor_rows(): void
    {
        DB::unprepared("CREATE TRIGGER mqtt_test_fail BEFORE INSERT ON sensor_readings WHEN NEW.sensor_id = 'air_humidity' BEGIN SELECT RAISE(ABORT, 'test storage failure'); END");
        try {
            app(TelemetryIngestor::class)->ingest($this->topic(), $this->payload());
            $this->fail('Expected a storage failure.');
        } catch (QueryException) {
            $this->assertDatabaseCount('mqtt_messages', 0);
            $this->assertDatabaseCount('sensor_readings', 0);
        }
    }

    public function test_uuid_case_cannot_bypass_deduplication(): void
    {
        $ingestor = app(TelemetryIngestor::class);
        $ingestor->ingest($this->topic(), $this->payload());
        $this->assertSame('duplicate', $ingestor->ingest($this->topic(), $this->payload([
            'message_id' => '5EF03860-30AE-44B2-A946-2EC66B397510',
        ])));
        $this->assertDatabaseCount('sensor_readings', 3);
    }

    public function test_validation_uses_configured_ids_and_accepts_exact_byte_limit(): void
    {
        $payload = $this->payload(['readings' => ['temperature' => -10]]);
        $this->assertSame('accepted', app(TelemetryIngestor::class)->ingest($this->topic(), str_pad($payload, 8192)));
        config(['monitoring.nodes' => [['id' => '2', 'name' => 'Node 2']]]);
        $this->expectException(InvalidTelemetry::class);
        app(TelemetryIngestor::class)->ingest($this->topic(), $this->payload());
    }

    private function topic(string $node = '1'): string
    {
        return 'rebung-pintar/v1/nodes/'.$node.'/telemetry';
    }

    /** @param array<string, mixed> $overrides */
    private function payload(array $overrides = []): string
    {
        return json_encode(array_replace([
            'schema_version' => 1,
            'message_id' => '5ef03860-30ae-44b2-a946-2ec66b397510',
            'node_id' => '1',
            'recorded_at' => '2026-09-16T00:00:00Z',
            'readings' => ['temperature' => 27.5, 'air_humidity' => 70, 'soil_moisture' => 55],
        ], $overrides), JSON_THROW_ON_ERROR);
    }
}
