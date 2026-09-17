import test from 'node:test';
import assert from 'node:assert/strict';
import { formatTime, summarize } from '../../resources/js/monitoring/data.js';
import { renderComparisonChart } from '../../resources/js/monitoring/chart.js';

function svgFixture() {
    return {
        attributes: {}, children: [], textContent: '',
        setAttribute(name, value) { this.attributes[name] = value; },
        append(...children) { this.children.push(...children); },
        replaceChildren(...children) { this.children = children; },
        getBoundingClientRect() { return { width: 600 }; },
    };
}

test('chart axes and point titles use WIB without changing timestamps or statistics', () => {
    const originalTimezone = process.env.TZ;
    const originalDocument = globalThis.document;
    try {
        process.env.TZ = 'UTC';
        globalThis.document = { createElementNS: () => svgFixture() };
        const svg = svgFixture();
        const readings = [
            { timestamp: Date.parse('2026-09-16T16:00:00Z'), value: 0 },
            { timestamp: Date.parse('2026-09-16T17:00:00Z'), value: 10 },
        ];
        const before = structuredClone(readings);
        renderComparisonChart(svg, [{ nodeId: '1', name: 'Node 1', readings }],
            { name: 'Suhu', unit: '°C', decimals: 1 }, 1, readings[1].timestamp);
        const elements = svg.children[0].children;
        const axisLabels = elements.filter((element) => element.attributes.y === '250').map((element) => element.textContent);
        assert.deepEqual(axisLabels, ['23.00', '23.15', '23.30', '23.45', '00.00']);
        const titles = elements.flatMap((element) => element.children).map((element) => element.textContent);
        assert.deepEqual(titles, ['Node 1 · 16 Sep, 23.00 WIB · 0,0 °C', 'Node 1 · 17 Sep, 00.00 WIB · 10,0 °C']);
        assert.deepEqual(readings, before);
        assert.deepEqual(summarize(readings), { min: 0, avg: 5, max: 10 });
    } finally {
        if (originalTimezone === undefined) delete process.env.TZ;
        else process.env.TZ = originalTimezone;
        if (originalDocument === undefined) delete globalThis.document;
        else globalThis.document = originalDocument;
    }
});

test('monitoring time is explicitly WIB across midnight regardless of host timezone', () => {
    const originalTimezone = process.env.TZ;
    try {
        for (const timezone of ['UTC', 'America/New_York', 'Asia/Tokyo']) {
            process.env.TZ = timezone;
            assert.equal(formatTime(Date.parse('2026-09-16T16:59:59Z')), '16 Sep, 23.59 WIB');
            assert.equal(formatTime(Date.parse('2026-09-16T17:00:00Z')), '17 Sep, 00.00 WIB');
        }
    } finally {
        if (originalTimezone === undefined) delete process.env.TZ;
        else process.env.TZ = originalTimezone;
    }
});
