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
        $validated = $request->validate(['node' => ['nullable', 'string', Rule::in(['1', '2'])]]);
        $nodeData = $snapshot->forNode($validated['node'] ?? null)['nodes'];
        $processed = [];

        foreach ($nodeData as $node) {
            $measurements = ['ph' => null];

            foreach ($node['sensors'] as $sensor) {
                if (array_key_exists($sensor['id'], $measurements) && $sensor['value'] !== null) {
                    $measurements[$sensor['id']] = (float) $sensor['value'];
                }
            }

            $processed[] = [
                'node_id' => $node['id'],
                'node_name' => $node['name'],
                'status' => $node['status'],
                'last_reading' => $node['last_reading'],
                'nutrient_assessment' => $analytics->assessNutrients($measurements),
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
