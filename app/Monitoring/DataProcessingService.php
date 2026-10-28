<?php

namespace App\Monitoring;

class DataProcessingService
{
    /**
     * @return array{value: float|null, unit: string, plants: array<string, array{status: string, range: array{min: float, max: float}}>}
     */
    public function assessNutrientValue(string $metric, ?float $value): array
    {
        $unit = $metric === 'ph' ? 'pH' : 'ppm';
        $plants = [];

        foreach (config('monitoring.plants', []) as $plantId => $plant) {
            if (! isset($plant[$metric])) {
                continue;
            }
            [$minimum, $maximum] = $plant[$metric];
            $plants[$plantId] = [
                'status' => $this->rangeStatus($value, (float) $minimum, (float) $maximum),
                'range' => ['min' => (float) $minimum, 'max' => (float) $maximum],
            ];
        }

        return ['value' => $value, 'unit' => $unit, 'plants' => $plants];
    }

    /**
     * @param  array{ph?: float|null}  $measurements
     * @return array{ph: array{value: float|null, unit: string, plants: array<string, array{status: string, range: array{min: float, max: float}}>}}
     */
    public function assessNutrients(array $measurements): array
    {
        return [
            'ph' => $this->assessNutrientValue('ph', $measurements['ph'] ?? null),
        ];
    }

    public function rangeStatus(?float $value, float $minimum, float $maximum): string
    {
        if ($value === null) {
            return 'Belum tersedia';
        }

        if ($value < $minimum) {
            return 'Di bawah rentang';
        }

        if ($value > $maximum) {
            return 'Di atas rentang';
        }

        return 'Dalam rentang';
    }

    /**
     * @param  array<int, float>  $values
     * @return array<string, float|null>
     */
    public function summarize(array $values): array
    {
        $filtered = array_values(array_filter($values, fn ($value): bool => is_numeric($value)));
        $count = count($filtered);

        if ($count === 0) {
            return [
                'count' => 0.0,
                'average' => null,
                'min' => null,
                'max' => null,
            ];
        }

        $average = array_sum($filtered) / $count;

        return [
            'count' => (float) $count,
            'average' => round($average, 2),
            'min' => round(min($filtered), 2),
            'max' => round(max($filtered), 2),
        ];
    }
}
