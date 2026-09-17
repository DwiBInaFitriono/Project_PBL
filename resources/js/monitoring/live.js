import { freshness, relativeAge } from './freshness.js';
import { formatTime } from './data.js';

export function renderFreshness(root, data, now) {
    for (const node of data.nodes) {
        const card = [...root.querySelectorAll('[data-node-id]')].find((el) => el.dataset.nodeId === node.id);
        if (!card) continue;
        const state = freshness(node.lastReading, now, data.staleAfterSeconds);
        const status = card.querySelector('[data-node-status]');
        status.textContent = state.state === 'unavailable' ? 'Menunggu integrasi' : state.label;
        status.dataset.state = state.state;
        const age = card.querySelector('[data-node-age]');
        age.textContent = relativeAge(state.ageSeconds);
        age.title = node.lastReading === null ? '' : formatTime(node.lastReading);
        for (const sensor of node.sensors) {
            const source = [...card.querySelectorAll('[data-sensor-reading]')].find((el) => el.dataset.sensorId === sensor.id)?.querySelector('[data-sensor-source]');
            if (!source) continue;
            const sensorState = freshness(sensor.lastReading, now, data.staleAfterSeconds);
            source.textContent = sensorState.state === 'unavailable' ? 'Belum ada pembacaan' : `${sensorState.label} · ${relativeAge(sensorState.ageSeconds)}`;
            source.dataset.state = sensorState.state;
        }
    }
    const timestamps = data.nodes.map((node) => node.lastReading).filter(Number.isFinite);
    const latest = timestamps.length ? Math.max(...timestamps) : null;
    const summary = root.querySelector('[data-latest-reading]');
    if (summary) summary.textContent = relativeAge(freshness(latest, now, data.staleAfterSeconds).ageSeconds);
    const notice = root.querySelector('[data-source-notice]');
    if (notice) notice.textContent = latest === null
        ? 'Menunggu integrasi perangkat. Nilai dan grafik belum berisi pembacaan perangkat.'
        : 'Menampilkan pembacaan tersimpan. Umur data tidak membuktikan perangkat sedang online.';
    const footer = root.querySelector('[data-source-footer]');
    if (footer) footer.textContent = latest === null ? 'Data perangkat belum tersedia' : 'Sumber: pembacaan tersimpan';
}

export function startPolling(root, initial, onData) {
    const endpoint = root.dataset.monitoringUrl;
    if (!endpoint) return;
    let data = initial;
    let receivedAt = Date.now();
    let timer;
    let redirectTimer;
    let controller;
    let disposed = false;
    let expired = false;
    const status = root.querySelector('[data-poll-status]');
    const retry = root.querySelector('[data-poll-refresh]');
    const recovery = root.querySelector('[data-access-recovery]');
    const now = () => data.asOf + Math.max(0, Date.now() - receivedAt);
    const ages = () => renderFreshness(root, data, now());
    const setStatus = (text) => { if (status) status.textContent = text; };

    function schedule() {
        clearTimeout(timer);
        if (!disposed && !expired && !document.hidden) timer = setTimeout(refresh, data.pollIntervalSeconds * 1000);
    }

    async function refresh() {
        if (disposed || expired || controller || document.hidden) return;
        clearTimeout(timer);
        const request = new AbortController();
        controller = request;
        const timeout = setTimeout(() => request.abort(), 10000);
        if (retry) retry.disabled = true;
        try {
            const response = await fetch(endpoint, { credentials: 'same-origin', cache: 'no-store', headers: { Accept: 'application/json' }, signal: request.signal });
            if ([401, 403, 419].includes(response.status) || response.redirected) {
                expired = true;
                const denied = response.status === 403;
                setStatus(denied
                    ? 'Akses ditolak. Anda akan diarahkan ke Dashboard.'
                    : 'Sesi berakhir. Anda akan diarahkan ke halaman masuk, lalu ke Dashboard setelah login.');
                if (recovery) {
                    recovery.hidden = false;
                    recovery.textContent = denied ? 'Kembali ke Dashboard' : 'Masuk kembali';
                    const target = denied ? recovery.dataset.dashboardUrl : recovery.href;
                    recovery.href = target;
                    redirectTimer = setTimeout(() => window.location.assign(target), 3000);
                }
                return;
            }
            if (!response.ok) throw new Error('Snapshot unavailable');
            const next = await response.json();
            if (disposed || request.signal.aborted) return;
            data = onData(next);
            receivedAt = Date.now();
            setStatus(`Terhubung ke server · pembaruan otomatis ${data.pollIntervalSeconds} detik`);
            ages();
        } catch {
            if (!disposed && !document.hidden) setStatus('Gagal memperbarui dari server. Data terakhir tetap ditampilkan; mencoba kembali otomatis.');
        } finally {
            clearTimeout(timeout);
            controller = null;
            if (retry) retry.disabled = expired;
            schedule();
        }
    }

    const onAgeTick = () => { if (!disposed && !document.hidden) ages(); };
    let ageTimer = setInterval(onAgeTick, 1000);
    retry?.addEventListener('click', refresh);
    document.addEventListener('visibilitychange', () => {
        if (document.hidden) { clearTimeout(timer); controller?.abort(); }
        else { ages(); refresh(); }
    });
    window.addEventListener('pagehide', () => {
        disposed = true;
        clearTimeout(timer);
        clearInterval(ageTimer);
        clearTimeout(redirectTimer);
        controller?.abort();
    });
    window.addEventListener('pageshow', (event) => {
        if (event.persisted) {
            disposed = false;
            ageTimer = setInterval(onAgeTick, 1000);
            ages();
            refresh();
        }
    });
    setStatus(`Pembaruan otomatis ${data.pollIntervalSeconds} detik · status koneksi perangkat belum tersedia`);
    ages();
    schedule();
}
