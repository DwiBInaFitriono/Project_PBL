<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SensorReadingResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $sensor = collect(config('monitoring.sensors'))->firstWhere('id', $this->sensor_id);
        $node = collect(config('monitoring.nodes'))->firstWhere('id', $this->node_id);

        return [
            'id' => $this->id, 'node_id' => $this->node_id, 'sensor_id' => $this->sensor_id,
            'value' => $this->value, 'recorded_at' => $this->recorded_at->toIso8601String(),
            'node_name' => $node['name'], 'sensor_name' => $sensor['name'], 'unit' => $sensor['unit'],
        ];
    }
}
