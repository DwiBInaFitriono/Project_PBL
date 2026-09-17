import { formatTime, formatValue, summarize } from './data.js';

function svgElement(name, attributes = {}, text) {
    const element = document.createElementNS('http://www.w3.org/2000/svg', name);
    for (const [key, value] of Object.entries(attributes)) element.setAttribute(key, String(value));
    if (text !== undefined) element.textContent = text;
    return element;
}

function seriesClass(nodeId) {
    return nodeId === '2' ? 'series-node-2' : 'series-node-1';
}

export function renderSparkline(container, readings, nodeId) {
    container.replaceChildren();
    const stats = summarize(readings);
    if (!stats) return;
    const span = stats.max - stats.min || 1;
    const start = readings[0].timestamp;
    const duration = readings.at(-1).timestamp - start || 1;
    const points = readings.map(({ timestamp, value }) => `${4 + (timestamp - start) / duration * 132},${30 - (value - stats.min) / span * 24}`).join(' ');
    const svg = svgElement('svg', { viewBox: '0 0 140 36', 'aria-hidden': 'true' });
    svg.append(svgElement('polyline', { points, class: seriesClass(nodeId), fill: 'none', 'stroke-width': 2, 'vector-effect': 'non-scaling-stroke' }));
    if (readings.length === 1) svg.append(svgElement('circle', { cx: 4, cy: 30, r: 3, class: seriesClass(nodeId) }));
    container.append(svg);
}

export function renderComparisonChart(svg, series, sensor, hours, asOf) {
    const width = Math.max(220, Math.round(svg.getBoundingClientRect().width));
    const height = 260;
    const bounds = { left: 54, right: width - 18, top: 22, bottom: height - 38 };
    const content = svgElement('g');
    const readings = series.flatMap((item) => item.readings);
    const stats = summarize(readings);
    const nodeNames = series.map((item) => item.name).join(' dan ');
    svg.setAttribute('viewBox', `0 0 ${width} ${height}`);
    for (let tick = 0; tick < 5; tick++) {
        const y = bounds.top + (bounds.bottom - bounds.top) * tick / 4;
        content.append(svgElement('line', { x1: bounds.left, x2: bounds.right, y1: y, y2: y, class: 'chart-grid-line' }));
    }
    if (!stats) {
        svg.setAttribute('aria-label', `Grafik ${sensor.name} · ${nodeNames}; belum ada pembacaan perangkat dalam ${hours} jam`);
        svg.replaceChildren(content);
        return;
    }
    const pad = (stats.max - stats.min) * 0.14 || 1;
    const lower = stats.min - pad;
    const upper = stats.max + pad;
    const duration = hours * 60 * 60 * 1000;
    const start = asOf - duration;
    const x = (timestamp) => bounds.left + (timestamp - start) / duration * (bounds.right - bounds.left);
    const y = (value) => bounds.bottom - (value - lower) / (upper - lower) * (bounds.bottom - bounds.top);
    for (let tick = 0; tick < 5; tick++) {
        const value = upper - (upper - lower) * tick / 4;
        content.append(svgElement('text', { x: bounds.left - 9, y: y(value) + 4, 'text-anchor': 'end', class: 'chart-axis-label' }, formatValue(value, sensor.decimals)));
    }
    const ticks = width < 500 ? 2 : 4;
    for (let tick = 0; tick <= ticks; tick++) {
        const timestamp = start + duration * tick / ticks;
        const anchor = tick === 0 ? 'start' : tick === ticks ? 'end' : 'middle';
        content.append(svgElement('text', { x: x(timestamp), y: height - 10, 'text-anchor': anchor, class: 'chart-axis-label' }, new Date(timestamp).toLocaleTimeString('id-ID', { hour: '2-digit', minute: '2-digit', timeZone: 'Asia/Jakarta' })));
    }
    series.forEach((item) => {
        if (!item.readings.length) return;
        const points = item.readings.map((reading) => `${x(reading.timestamp).toFixed(2)},${y(reading.value).toFixed(2)}`).join(' ');
        content.append(svgElement('polyline', { points, fill: 'none', 'stroke-width': 2.5, class: seriesClass(item.nodeId), 'data-chart-series': item.nodeId }));
        for (const reading of item.readings) {
            const point = svgElement('circle', { cx: x(reading.timestamp), cy: y(reading.value), r: item.readings.length === 1 ? 3 : 5, fill: item.readings.length === 1 ? 'currentColor' : 'transparent', class: 'chart-point' });
            point.append(svgElement('title', {}, `${item.name} · ${formatTime(reading.timestamp)} · ${formatValue(reading.value, sensor.decimals)} ${sensor.unit}`));
            content.append(point);
        }
    });
    svg.setAttribute('aria-label', `Grafik ${sensor.name} (${sensor.unit}) ${nodeNames} selama ${hours} jam.`);
    svg.replaceChildren(content);
}
