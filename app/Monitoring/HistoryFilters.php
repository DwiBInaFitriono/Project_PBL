<?php

namespace App\Monitoring;

use App\Models\SensorReading;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;

class HistoryFilters
{
    public const int MAX_DAYS = 366;

    public readonly CarbonImmutable $from;

    public readonly CarbonImmutable $to;

    public readonly ?string $node;

    public readonly ?string $sensor;

    public readonly string $timezone;

    /** @param array{node?: ?string, sensor?: ?string, from?: ?string, to?: ?string, timezone?: string, page?: mixed} $validated */
    public function __construct(array $validated = [])
    {
        $this->timezone = $validated['timezone'] ?? 'Asia/Jakarta';
        $today = CarbonImmutable::today($this->timezone);
        $this->from = isset($validated['from']) ? CarbonImmutable::createFromFormat('!Y-m-d', $validated['from'], $this->timezone) : $today->subDays(6);
        $this->to = isset($validated['to']) ? CarbonImmutable::createFromFormat('!Y-m-d', $validated['to'], $this->timezone) : $today;
        $this->node = $validated['node'] ?? null;
        $this->sensor = $validated['sensor'] ?? null;
    }

    /** @return array{node: ?string, sensor: ?string, from: string, to: string, timezone: string} */
    public function toArray(): array
    {
        return ['node' => $this->node, 'sensor' => $this->sensor, 'from' => $this->from->toDateString(), 'to' => $this->to->toDateString(), 'timezone' => $this->timezone];
    }

    /** @return Builder<SensorReading> */
    public function query(): Builder
    {
        return SensorReading::query()->valid()
            ->when($this->node !== null, fn (Builder $query): Builder => $query->where('node_id', $this->node))
            ->when($this->sensor !== null, fn (Builder $query): Builder => $query->where('sensor_id', $this->sensor))
            ->where('recorded_at', '>=', $this->from->utc())
            ->where('recorded_at', '<', $this->to->addDay()->utc())
            ->where('recorded_at', '<=', CarbonImmutable::now('UTC'))
            ->orderByDesc('recorded_at')->orderByDesc('id');
    }
}
