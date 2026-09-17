<?php

namespace Database\Factories;

use App\Models\SensorReading;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<SensorReading> */
class SensorReadingFactory extends Factory
{
    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'node_id' => fake()->randomElement(array_column(config('monitoring.nodes'), 'id')),
            'sensor_id' => fake()->randomElement(array_column(config('monitoring.sensors'), 'id')),
            'value' => fake()->randomFloat(2, 0, 100),
            'recorded_at' => now('UTC'),
        ];
    }
}
