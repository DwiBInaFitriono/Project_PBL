<?php

namespace App\Monitoring;

use Illuminate\Http\Request;

class MonitoringFilters
{
    /** @return array{activeSensor: string, activeHours: int} */
    public function fromRequest(Request $request): array
    {
        $sensors = array_column(config('monitoring.sensors'), 'id');
        $sensor = $request->query('sensor', $sensors[0]);
        $hours = $request->query('hours', '24');
        abort_unless(is_string($sensor) && in_array($sensor, $sensors, true), 404);
        abort_unless(is_string($hours) && in_array($hours, ['1', '6', '24'], true), 404);

        return ['activeSensor' => $sensor, 'activeHours' => (int) $hours];
    }
}
