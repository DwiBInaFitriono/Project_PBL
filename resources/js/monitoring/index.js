import { parseMonitoringData, selectSeries } from './data.js';
import { renderFreshness, startPolling } from './live.js';
import { renderComparisonChart } from './chart.js';
import { renderHistory, renderSensorCards, renderSummaries } from './view.js';

export function initMonitoring(root) {
    let data = parseMonitoringData(root.dataset.monitoring);
    const chart = root.querySelector('[data-chart]');
    const history = root.querySelector('[data-history-body]');
    const emptyRow = history.firstElementChild.cloneNode(true);
    const sensorButtons = [...root.querySelectorAll('[data-select-sensor]')];
    const rangeButtons = [...root.querySelectorAll('[data-select-range]')];
    const selectedId = root.querySelector('[data-select-sensor][aria-pressed="true"]')?.dataset.selectSensor;
    let activeSensor = data.sensors.find((sensor) => sensor.id === selectedId) || data.sensors[0];
    let hours = Number(root.querySelector('[data-select-range][aria-pressed="true"]')?.dataset.selectRange) || 24;
    let series = [];

    function syncNavigation(push = false) {
        const url = new URL(window.location.href);
        if (push) {
            url.searchParams.set('sensor', activeSensor.id);
            url.searchParams.set('hours', String(hours));
            if (url.href !== window.location.href) window.history.pushState({}, '', url);
        }
        const refresh = root.querySelector('.dashboard-heading .dashboard-refresh');
        if (refresh) refresh.href = url.href;
        const nodeId = root.dataset.node;
        const historyLink = root.querySelector('[data-history-link]');
        if (historyLink) {
            const historyUrl = new URL(historyLink.href);
            historyUrl.searchParams.set('sensor', activeSensor.id);
            if (nodeId) historyUrl.searchParams.set('node', nodeId);
            historyLink.href = historyUrl.href;
        }
        for (const link of document.querySelectorAll('[data-sidebar] .sidebar-submenu a')) {
            const target = new URL(link.href);
            if (target.searchParams.has('sensor')) {
                target.searchParams.set('hours', String(hours));
                link.href = target.href;
            }
            if (nodeId && target.pathname === `/nodes/${nodeId}`) {
                const selected = url.searchParams.has('sensor')
                    ? target.searchParams.get('sensor') === activeSensor.id
                    : !target.searchParams.has('sensor') && !target.hash;
                if (selected) link.setAttribute('aria-current', 'page');
                else link.removeAttribute('aria-current');
            }
        }
    }

    function update() {
        series = selectSeries(data, activeSensor.id, hours);
        const hasData = series.some((item) => item.readings.length > 0);
        root.querySelector('[data-active-sensor]').textContent = `${activeSensor.name} · ${activeSensor.unit}`;
        sensorButtons.forEach((button) => button.setAttribute('aria-pressed', String(button.dataset.selectSensor === activeSensor.id)));
        rangeButtons.forEach((button) => button.setAttribute('aria-pressed', String(Number(button.dataset.selectRange) === hours)));
        root.querySelector('[data-chart-empty]').hidden = hasData;
        root.querySelector('[data-chart-caption]').textContent = hasData
            ? `Pembacaan ${hours} jam terakhir (maksimal 500 titik terbaru per sensor). Statistik mengikuti titik yang dimuat; riwayat lengkap tersedia melalui ekspor. Waktu grafik ditampilkan dalam WIB.`
            : `Belum ada pembacaan perangkat dalam ${hours} jam terakhir. Data kosong tidak dianggap nol.`;
        renderComparisonChart(chart, series, activeSensor, hours, data.asOf);
        renderSummaries(root, series, activeSensor, hours);
        const count = renderHistory(history, series, activeSensor, emptyRow);
        root.querySelector('[data-history-source]').textContent = count ? `${count} pembacaan terbaru` : 'Belum ada data';
        syncNavigation();
    }

    sensorButtons.forEach((button) => button.addEventListener('click', () => {
        activeSensor = data.sensors.find((sensor) => sensor.id === button.dataset.selectSensor);
        syncNavigation(true);
        update();
    }));
    rangeButtons.forEach((button) => button.addEventListener('click', () => {
        hours = Number(button.dataset.selectRange);
        syncNavigation(true);
        update();
    }));
    window.addEventListener('popstate', () => {
        const params = new URL(window.location.href).searchParams;
        activeSensor = data.sensors.find((sensor) => sensor.id === params.get('sensor')) || data.sensors[0];
        hours = [1, 6, 24].includes(Number(params.get('hours'))) ? Number(params.get('hours')) : 24;
        update();
    });
    renderSensorCards(root, data.nodes);
    update();
    renderFreshness(root, data, data.asOf);
    startPolling(root, data, (payload) => {
        const next = parseMonitoringData(JSON.stringify(payload));
        if (next.nodes.map((node) => node.id).join(',') !== data.nodes.map((node) => node.id).join(',') || next.sensors.map((sensor) => sensor.id).join(',') !== data.sensors.map((sensor) => sensor.id).join(',')) {
            throw new TypeError('Monitoring configuration changed; reload required');
        }
        data = next;
        activeSensor = data.sensors.find((sensor) => sensor.id === activeSensor.id) || data.sensors[0];
        renderSensorCards(root, data.nodes);
        update();
        return data;
    });
    const observer = new ResizeObserver(() => renderComparisonChart(chart, series, activeSensor, hours, data.asOf));
    observer.observe(chart.parentElement);
    window.addEventListener('pagehide', () => observer.disconnect(), { once: true });
    window.addEventListener('pageshow', () => observer.observe(chart.parentElement));
}
