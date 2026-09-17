import { formatTime, formatValue, summarize } from './data.js';
import { renderSparkline } from './chart.js';

export function renderSensorCards(root, nodes) {
    nodes.forEach((node) => {
        for (const sensor of node.sensors) {
            const card = [...root.querySelectorAll('[data-sensor-reading]')].find((item) => item.dataset.sensorNode === node.id && item.dataset.sensorId === sensor.id);
            if (!card) continue;
            card.querySelector('[data-current-value]').textContent = formatValue(sensor.value, sensor.decimals);
            card.querySelector('[data-sensor-source]').textContent = sensor.value === null ? 'Belum ada pembacaan' : 'Data perangkat';
            renderSparkline(card.querySelector('[data-sparkline]'), sensor.readings, node.id);
        }
    });
}

export function renderSummaries(root, series, sensor, hours) {
    for (const item of series) {
        const section = [...root.querySelectorAll('[data-node-summary]')].find((summary) => summary.dataset.nodeSummary === item.nodeId);
        if (!section) continue;
        const stats = summarize(item.readings);
        section.querySelector('[data-summary-source]').textContent = `Data perangkat · ${hours} jam`;
        for (const element of section.querySelectorAll('[data-summary-value]')) {
            element.textContent = stats ? `${formatValue(stats[element.dataset.summaryValue], sensor.decimals)} ${sensor.unit}` : '—';
        }
    }
}

export function renderHistory(body, series, sensor, emptyRow) {
    const rows = series.flatMap((item) => item.readings.map((reading) => ({ ...reading, nodeId: item.nodeId, name: item.name })))
        .sort((a, b) => b.timestamp - a.timestamp || a.nodeId.localeCompare(b.nodeId)).slice(0, 12);
    const fragment = document.createDocumentFragment();
    if (!rows.length) fragment.append(emptyRow.cloneNode(true));
    for (const row of rows) {
        const tr = document.createElement('tr');
        tr.dataset.readingRow = '';
        for (const value of [formatTime(row.timestamp), row.name, sensor.name, `${formatValue(row.value, sensor.decimals)} ${sensor.unit}`]) {
            const td = document.createElement('td');
            td.textContent = value;
            tr.append(td);
        }
        fragment.append(tr);
    }
    body.replaceChildren(fragment);
    return rows.length;
}
