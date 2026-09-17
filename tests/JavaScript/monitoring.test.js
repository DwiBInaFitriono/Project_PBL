import test from 'node:test';
import assert from 'node:assert/strict';
import { formatValue, normalizeReadings, parseMonitoringData, selectSeries, summarize } from '../../resources/js/monitoring/data.js';

const asOf = Date.parse('2026-01-01T10:00:00Z');

test('normalizes readings, excludes invalid values, and preserves zero', () => {
    const values = normalizeReadings([
        { recorded_at: '2026-01-01T10:00:00Z', value: 0 },
        { recorded_at: '2026-01-01T08:00:00Z', value: 8 },
        { recorded_at: '2026-01-01T08:00:00Z', value: 9 },
        { recorded_at: '2026-01-01T11:00:00Z', value: 7 },
        { recorded_at: 'invalid', value: 4 },
        { recorded_at: '2026-01-01T09:00:00Z', value: null },
        { recorded_at: '2026-01-01T09:00:00Z', value: '5' },
    ], asOf);
    assert.deepEqual(values.map(({ value }) => value), [9, 0]);
});

test('empty statistics and invalid values remain missing, not zero', () => {
    assert.equal(summarize([]), null);
    assert.equal(formatValue(null), '—');
    assert.equal(formatValue(NaN), '—');
    assert.equal(formatValue(0), '0,0');
    assert.equal(formatValue(1.234, 2), '1,23');
});

test('statistics are derived from the selected window', () => {
    const data = parseMonitoringData(JSON.stringify({
        generatedAt: '2026-01-01T10:00:00Z',
        sensors: [{ id: 'temperature', name: 'Suhu', unit: '°C', decimals: 1 }],
        nodes: [{ id: '1', name: 'Node 1', sensors: [{ id: 'temperature', readings: [
            { recorded_at: '2026-01-01T08:00:00Z', value: 8 },
            { recorded_at: '2026-01-01T09:00:00Z', value: 4 },
            { recorded_at: '2026-01-01T10:00:00Z', value: 0 },
        ] }] }],
    }));
    assert.equal(data.nodes[0].sensors[0].value, 0);
    assert.deepEqual(summarize(selectSeries(data, 'temperature', 1)[0].readings), { min: 0, avg: 2, max: 4 });
});

test('malformed dashboard payloads fail explicitly', () => {
    for (const value of ['invalid', '{}', 'null', '{"sensors":[]}']) {
        assert.throws(() => parseMonitoringData(value));
    }
});
