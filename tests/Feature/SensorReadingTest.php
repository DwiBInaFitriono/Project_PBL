<?php

namespace Tests\Feature;

use App\Models\SensorReading;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class SensorReadingTest extends TestCase
{
    use RefreshDatabase;

    public function test_model_rejects_non_finite_or_non_numeric_values_before_persistence(): void
    {
        foreach ([INF, -INF, NAN, 'NaN', 'not-a-number', '1e9999', null, true, []] as $invalid) {
            try {
                SensorReading::factory()->create(['value' => $invalid]);
                $this->fail('Invalid sensor value was accepted.');
            } catch (\InvalidArgumentException $exception) {
                $this->assertSame('Nilai pembacaan harus berupa angka berhingga.', $exception->getMessage());
            }
        }
        $this->assertDatabaseCount('sensor_readings', 0);
    }

    public function test_missing_recorded_time_cannot_be_fabricated_as_now(): void
    {
        foreach ([null, '', '  '] as $invalid) {
            try {
                SensorReading::factory()->create(['recorded_at' => $invalid]);
                $this->fail('A missing recorded_at was fabricated.');
            } catch (\InvalidArgumentException $exception) {
                $this->assertSame('Waktu pembacaan wajib diisi.', $exception->getMessage());
            }
        }
        $this->assertDatabaseCount('sensor_readings', 0);
    }

    public function test_recorded_at_is_normalized_to_utc_before_storage(): void
    {
        $reading = SensorReading::factory()->create(['recorded_at' => '2026-09-16T12:00:00+07:00'])->fresh();

        $this->assertSame('2026-09-16T05:00:00+00:00', $reading->recorded_at->toIso8601String());
        $this->assertDatabaseHas('sensor_readings', ['id' => $reading->id, 'recorded_at' => '2026-09-16 05:00:00']);
    }

    public function test_readings_are_persisted_with_typed_values_and_query_indexes(): void
    {
        $this->assertTrue(Schema::hasTable('sensor_readings'));

        $reading = SensorReading::factory()->create([
            'node_id' => '2',
            'sensor_id' => 'temperature',
            'value' => 0,
            'recorded_at' => '2026-09-16 05:00:00',
        ])->fresh();

        $this->assertSame('2', $reading->node_id);
        $this->assertSame('temperature', $reading->sensor_id);
        $this->assertSame(0.0, $reading->value);
        $this->assertSame('2026-09-16T05:00:00+00:00', $reading->recorded_at->toIso8601String());
        $this->assertNotNull($reading->created_at);
        $this->assertTrue(Schema::hasIndex('sensor_readings', ['node_id', 'sensor_id', 'recorded_at']));
        $this->assertTrue(Schema::hasIndex('sensor_readings', ['recorded_at']));
    }
}
