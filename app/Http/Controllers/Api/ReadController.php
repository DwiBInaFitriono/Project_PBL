<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\HistoryFilterRequest;
use App\Http\Resources\SensorReadingResource;
use App\Monitoring\DataProcessingService;
use App\Monitoring\MonitoringSnapshot;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class ReadController extends Controller
{
    public function monitoring(Request $request, MonitoringSnapshot $snapshot): JsonResponse
    {
        $validated = $request->validate(['node' => ['nullable', 'string', Rule::in(['1', '2'])]]);

        return response()->json(['data' => $snapshot->forNode($validated['node'] ?? null)['monitoring']]);
    }

    public function history(HistoryFilterRequest $request): JsonResponse
    {
        $filters = $request->filters();
        $page = $filters->query()->paginate(25);

        return response()->json([
            'data' => SensorReadingResource::collection($page->getCollection())->resolve($request),
            'meta' => ['current_page' => $page->currentPage(), 'last_page' => $page->lastPage(), 'per_page' => $page->perPage(), 'total' => $page->total()],
            'filters' => $filters->toArray(),
        ]);
    }

    public function esp(): JsonResponse
    {
        Gate::authorize('manage-esp');

        return response()->json(['data' => [
            'nodes' => config('monitoring.nodes'), 'sensors' => config('monitoring.sensors'),
            'integration' => ['mqtt_enabled' => (bool) config('mqtt.enabled', false), 'hardware_connected' => false],
        ]]);
    }

    public function analytics(Request $request, MonitoringSnapshot $snapshot, DataProcessingService $analytics): JsonResponse
    {
        $nodeData = $snapshot->forNode($request->query('node'))['nodes'];
        $processed = [];

        foreach ($nodeData as $node) {
            $temp = null;
            $hum = null;
            $soil = null;

            foreach ($node['sensors'] as $s) {
                if ($s['id'] === 'temperature') {
                    $temp = $s['value'] !== null ? (float) $s['value'] : null;
                }
                if ($s['id'] === 'air_humidity') {
                    $hum = $s['value'] !== null ? (float) $s['value'] : null;
                }
                if ($s['id'] === 'soil_moisture') {
                    $soil = $s['value'] !== null ? (float) $s['value'] : null;
                }
            }

            $vpd = ($temp !== null && $hum !== null) ? $analytics->calculateVpd($temp, $hum) : null;
            $vpdInfo = $vpd !== null ? $analytics->classifyVpd($vpd) : null;
            $soilInfo = $soil !== null ? $analytics->evaluateSoilMoisture($soil) : null;

            $processed[] = [
                'node_id' => $node['id'],
                'node_name' => $node['name'],
                'status' => $node['status'],
                'last_reading' => $node['last_reading'],
                'vpd' => [
                    'value' => $vpd,
                    'unit' => 'kPa',
                    'assessment' => $vpdInfo,
                ],
                'soil_assessment' => $soilInfo,
            ];
        }

        return response()->json([
            'data' => [
                'nodes' => $processed,
                'generated_at' => now()->toIso8601String(),
            ],
        ]);
    }
}
