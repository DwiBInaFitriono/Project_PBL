<?php

namespace App\Monitoring;

use App\Models\SensorReading;
use Carbon\CarbonImmutable;

class MonitoringSnapshot
{
    /** @return array<string, mixed> */
    public function forNode(?string $nodeId = null): array
    {
        $now = CarbonImmutable::now('UTC');
        $sensors = config('monitoring.sensors');
        $staleAfterSeconds = (int) config('monitoring.stale_after_seconds', 300);
        $selected = array_filter(config('monitoring.nodes'), fn (array $node): bool => $nodeId === null || $node['id'] === $nodeId);
        $nodes = [];

        foreach ($selected as $definition) {
            $latestAt = null;
            $nodeSensors = [];

            foreach ($sensors as $sensor) {
                $query = SensorReading::query()->valid()
                    ->where('node_id', $definition['id'])
                    ->where('sensor_id', $sensor['id'])
                    ->where('recorded_at', '<=', $now)
                    ->orderByDesc('recorded_at')->orderByDesc('id');
                $latest = (clone $query)->first();
                $readings = (clone $query)->where('recorded_at', '>=', $now->subDay())
                    ->limit(500)->get()->reverse()->values()
                    ->map(fn (SensorReading $reading): array => [
                        'recorded_at' => $reading->recorded_at->toIso8601String(),
                        'value' => $reading->value,
                    ])->all();

                if ($latest !== null && ($latestAt === null || $latest->recorded_at->greaterThan($latestAt))) {
                    $latestAt = $latest->recorded_at;
                }

                $nodeSensors[] = [
                    ...$sensor,
                    'value' => $latest?->value,
                    'last_reading' => $latest?->recorded_at->toIso8601String(),
                    'readings' => $readings,
                ];
            }

            $age = $latestAt === null ? null : (int) $latestAt->diffInSeconds($now);
            $freshness = $age === null ? 'unavailable' : ($age <= $staleAfterSeconds ? 'fresh' : 'stale');
            $nodes[] = [
                ...$definition,
                'status' => match ($freshness) {
                    'fresh' => 'Data terbaru',
                    'stale' => 'Data terlambat',
                    default => 'Menunggu integrasi',
                },
                'freshness' => $freshness,
                'age_seconds' => $age,
                'last_reading' => $latestAt?->toIso8601String(),
                'sensors' => $nodeSensors,
            ];
        }

        return [
            'nodes' => $nodes,
            'sensors' => $sensors,
            'monitoring' => [
                'nodes' => $nodes,
                'sensors' => $sensors,
                'generatedAt' => $now->toIso8601String(),
                'staleAfterSeconds' => $staleAfterSeconds,
                'pollIntervalSeconds' => (int) config('monitoring.poll_interval_seconds', 15),
            ],
            'sensorCount' => array_sum(array_map(fn (array $node): int => count($node['sensors']), $nodes)),
        ];
    }
}
