const formatters = new Map();

export function formatValue(value, decimals = 1) {
    if (!Number.isFinite(value)) return '—';
    if (!formatters.has(decimals)) {
        formatters.set(decimals, new Intl.NumberFormat('id-ID', {
            minimumFractionDigits: decimals,
            maximumFractionDigits: decimals,
        }));
    }
    return formatters.get(decimals).format(value);
}

export function formatTime(timestamp) {
    return `${new Intl.DateTimeFormat('id-ID', {
        day: '2-digit', month: 'short', hour: '2-digit', minute: '2-digit', timeZone: 'Asia/Jakarta',
    }).format(timestamp)} WIB`;
}

export function normalizeReadings(readings, asOf) {
    const byTime = new Map();
    for (const reading of Array.isArray(readings) ? readings : []) {
        const timestamp = Date.parse(reading?.recorded_at);
        if (Number.isFinite(timestamp) && timestamp <= asOf && Number.isFinite(reading?.value)) {
            byTime.set(timestamp, { timestamp, value: reading.value });
        }
    }
    return [...byTime.values()].sort((a, b) => a.timestamp - b.timestamp);
}

function validTimestamp(value, asOf) {
    const timestamp = Date.parse(value);
    return Number.isFinite(timestamp) && timestamp <= asOf ? timestamp : null;
}

export function parseMonitoringData(raw) {
    const data = JSON.parse(raw);
    const asOf = Date.parse(data.generatedAt);
    if (!Array.isArray(data.sensors) || !data.sensors.length || !Array.isArray(data.nodes) || !Number.isFinite(asOf)) {
        throw new TypeError('Invalid monitoring payload');
    }
    const sensors = data.sensors.map((sensor) => {
        if (typeof sensor.id !== 'string' || typeof sensor.name !== 'string' || typeof sensor.unit !== 'string') {
            throw new TypeError('Invalid sensor definition');
        }
        return { ...sensor, decimals: Number.isInteger(sensor.decimals) && sensor.decimals >= 0 && sensor.decimals <= 6 ? sensor.decimals : 1 };
    });
    const nodes = data.nodes.map((node) => ({
        id: String(node.id),
        name: String(node.name),
        lastReading: validTimestamp(node.last_reading, asOf),
        sensors: sensors.map((definition) => {
            const source = node.sensors?.find((sensor) => sensor.id === definition.id);
            const readings = normalizeReadings(source?.readings, asOf);
            return {
                ...definition,
                value: readings.at(-1)?.value ?? (Number.isFinite(source?.value) ? source.value : null),
                readings,
                lastReading: validTimestamp(source?.last_reading, asOf) ?? readings.at(-1)?.timestamp ?? null,
            };
        }),
    }));
    for (const node of nodes) {
        const timestamps = node.sensors.map((sensor) => sensor.lastReading).filter(Number.isFinite);
        node.lastReading ??= timestamps.length ? Math.max(...timestamps) : null;
    }
    const staleAfterSeconds = Number.isFinite(data.staleAfterSeconds) && data.staleAfterSeconds >= 1 ? data.staleAfterSeconds : 300;
    const pollIntervalSeconds = Number.isFinite(data.pollIntervalSeconds) ? Math.max(5, Math.min(300, data.pollIntervalSeconds)) : 15;
    return { sensors, nodes, asOf, staleAfterSeconds, pollIntervalSeconds };
}

export function selectSeries(data, sensorId, hours) {
    const start = data.asOf - hours * 60 * 60 * 1000;
    return data.nodes.map((node) => ({
        nodeId: node.id,
        name: node.name,
        readings: node.sensors.find((sensor) => sensor.id === sensorId).readings.filter((reading) => reading.timestamp >= start),
    }));
}

export function summarize(readings) {
    if (!readings.length) return null;
    const aggregate = readings.reduce((result, { value }) => ({
        min: Math.min(result.min, value),
        max: Math.max(result.max, value),
        sum: result.sum + value,
    }), { min: Infinity, max: -Infinity, sum: 0 });
    return { min: aggregate.min, avg: aggregate.sum / readings.length, max: aggregate.max };
}
