import { test, expect, withMonitoringData } from './support/dashboard-fixture.js';

async function routePages(page, renderPage) {
    await page.route(/\/(dashboard|nodes\/[12])(\?.*)?$/, (route) => {
        const url = new URL(route.request().url());
        return route.fulfill({ contentType: 'text/html', body: renderPage(url.pathname + url.search) });
    });
}

test('sesi habis memberi peringatan dan mengarahkan ke login', async ({ page, renderPage }) => {
    await page.clock.install();
    await routePages(page, renderPage);
    let calls = 0;
    await page.route('**/monitoring/data*', (route) => {
        calls++;
        return route.fulfill({ status: 401, json: { message: 'Unauthenticated' } });
    });
    await page.goto('/dashboard');
    await page.clock.runFor(16000);
    await expect(page.locator('[data-poll-status]')).toContainText('Sesi berakhir');
    await expect(page.locator('[data-poll-refresh]')).toBeDisabled();
    await expect(page.getByRole('link', { name: 'Masuk kembali', exact: true })).toBeVisible();
    await page.clock.runFor(4000);
    await expect(page).toHaveURL(/\/login\?access=login$/);
    await expect(page.locator('[data-access-warning]')).toContainText('Silakan masuk');
    expect(calls).toBe(1);
});

test('umur data tampil dan polling memperbarui sensor tanpa reload', async ({ page, renderPage }) => {
    const start = new Date('2026-01-01T10:00:00Z');
    await page.clock.install({ time: start });
    const sensor = { id: 'temperature', name: 'Suhu udara', unit: '°C', decimals: 1 };
    const payload = (value, generatedAt, recorded_at) => ({
        generatedAt, staleAfterSeconds: 300, pollIntervalSeconds: 15,
        sensors: [sensor],
        nodes: [{ id: '2', name: 'Node 2', last_reading: recorded_at,
            sensors: [{ ...sensor, value, last_reading: recorded_at, readings: [{ value, recorded_at }] }] }],
    });
    const initial = payload(0, start.toISOString(), '2026-01-01T09:50:00Z');
    const html = withMonitoringData(renderPage('/nodes/2'), initial);
    await page.route('**/nodes/2', (route) => route.fulfill({ contentType: 'text/html', body: html }));
    let polls = 0;
    await page.route('**/monitoring/data*', (route) => {
        polls++;
        return route.fulfill({ json: payload(27, '2026-01-01T10:00:15Z', '2026-01-01T10:00:10Z') });
    });
    await page.goto('/nodes/2');
    await expect(page.locator('[data-node-status]')).toHaveText('Data terlambat');
    await expect(page.locator('[data-node-age]')).toContainText('10 menit lalu');
    await expect(page.locator('[data-current-value]').first()).toHaveText('0,0');
    await page.evaluate(() => { window.__navigationMarker = 'unchanged'; });
    await page.clock.runFor(16000);
    await expect.poll(() => polls).toBe(1);
    await expect(page.locator('[data-current-value]').first()).toHaveText('27,0');
    await expect(page.locator('[data-node-status]')).toHaveText('Data terbaru');
    expect(await page.evaluate(() => window.__navigationMarker)).toBe('unchanged');
    await page.route('**/monitoring/data*', (route) => route.abort());
    await page.clock.runFor(16000);
    await expect(page.locator('[data-poll-status]')).toContainText('Gagal memperbarui');
    await expect(page.locator('[data-current-value]').first()).toHaveText('27,0');
    await page.clock.runFor(301000);
    await expect(page.locator('[data-node-status]')).toHaveText('Data terlambat');
});

test('sensor dan waktu sinkron dengan URL sidebar reload dan tombol kembali', async ({ page, renderPage }) => {
    await page.setViewportSize({ width: 1440, height: 900 });
    await routePages(page, renderPage);
    await page.goto('/nodes/2?sensor=temperature&hours=6#tren');
    await page.getByRole('button', { name: 'Kelembapan tanah', exact: true }).click();
    await expect(page).toHaveURL(/sensor=soil_moisture&hours=6#tren$/);
    const active = page.locator('#node-2-menu a[aria-current="page"]');
    await expect(active).toHaveText('Kelembapan tanah');
    await page.getByRole('button', { name: '1 jam', exact: true }).click();
    await expect(page).toHaveURL(/sensor=soil_moisture&hours=1#tren$/);
    await page.goBack();
    await expect(page.getByRole('button', { name: '6 jam', exact: true })).toHaveAttribute('aria-pressed', 'true');
    await page.reload();
    await expect(page.locator('[data-active-sensor]')).toHaveText('Kelembapan tanah · %');
    await expect(active).toHaveText('Kelembapan tanah');
    await page.getByRole('link', { name: 'Muat ulang', exact: true }).click();
    await expect(page).toHaveURL(/sensor=soil_moisture&hours=6#tren$/);
});
