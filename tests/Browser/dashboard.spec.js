import { test, expect, openDashboard, withMonitoringData } from './support/dashboard-fixture.js';

for (const theme of ['light', 'dark']) {
    test(`tombol dashboard ${theme} memakai aksen biru tanpa mengubah login`, async ({ page, dashboardHtml }, testInfo) => {
        await page.setViewportSize({ width: 1440, height: 900 });
        await page.emulateMedia({ colorScheme: theme });
        await openDashboard(page, dashboardHtml);
        const sidebar = page.locator('[data-sidebar]');
        const activeMenu = sidebar.getByRole('link', { name: 'Dashboard', exact: true });
        await expect(activeMenu).toHaveCSS('background-color', 'rgb(161, 178, 234)');
        await expect(activeMenu).toHaveCSS('color', 'rgb(1, 30, 96)');
        await sidebar.getByRole('button', { name: 'Menu Node 1', exact: true }).click();
        await expect(sidebar.getByRole('button', { name: 'Menu Node 1', exact: true })).toHaveCSS('color', 'rgb(161, 178, 234)');
        const activeSensor = page.locator('[data-select-sensor][aria-pressed="true"]');
        await expect(activeSensor).toHaveCSS('background-color', theme === 'dark' ? 'rgb(161, 178, 234)' : 'rgb(1, 30, 96)');
        await expect(activeSensor).toHaveCSS('color', theme === 'dark' ? 'rgb(1, 30, 96)' : 'rgb(242, 241, 239)');
        await page.getByRole('button', { name: 'Kelembapan tanah', exact: true }).click();
        await expect(activeSensor).toHaveText('Kelembapan tanah');
        await page.screenshot({ path: testInfo.outputPath('dashboard-blue.png'), fullPage: true });
        await page.goto('/login');
        await expect(page.locator('.primary-button')).toHaveCSS('background-color', theme === 'dark' ? 'rgb(247, 183, 0)' : 'rgb(1, 30, 96)');
        await expect(page.locator('[data-sidebar]')).toHaveCount(0);
    });
}

test('kesalahan data dashboard tidak mematikan tombol tema', async ({ page, dashboardHtml }) => {
    const brokenData = dashboardHtml.replace(/data-monitoring="[^"]*"/, 'data-monitoring="invalid-json"');
    await page.emulateMedia({ colorScheme: 'light' });
    await openDashboard(page, brokenData);
    await page.getByRole('button', { name: 'Aktifkan mode gelap' }).click();
    await expect(page.locator('html')).toHaveAttribute('data-theme', 'dark');
    await expect(page.locator('[data-monitoring-error]')).toBeVisible();
    await expect(page.getByRole('button', { name: '1 jam', exact: true })).toBeDisabled();
});

test('dashboard memakai pembacaan yang diberikan tanpa mengubah nol menjadi kosong', async ({ page, dashboardHtml }) => {
    const sensors = [
        { id: 'temperature', name: 'Suhu udara', unit: '°C', decimals: 1 },
        { id: 'air_humidity', name: 'Kelembapan udara', unit: '% RH', decimals: 1 },
        { id: 'soil_moisture', name: 'Kelembapan tanah', unit: '%', decimals: 1 },
    ];
    const readings = [
        { recorded_at: '2026-01-01T09:00:00Z', value: 4 },
        { recorded_at: '2026-01-01T08:00:00Z', value: 8 },
        { recorded_at: '2026-01-01T10:00:00Z', value: 0 },
        { recorded_at: 'invalid', value: 20 },
        { recorded_at: '2026-01-01T09:30:00Z', value: null },
    ];
    const data = {
        sensors,
        nodes: ['1', '2'].map((id) => ({
            id,
            name: `Node ${id}`,
            sensors: sensors.map((sensor) => ({ ...sensor, value: null, readings: sensor.id === 'temperature' ? readings : [] })),
        })),
        generatedAt: '2026-01-01T10:00:00Z',
    };
    await openDashboard(page, withMonitoringData(dashboardHtml, data));
    await expect(page.locator('[data-sensor-node="1"][data-sensor-id="temperature"] [data-current-value]')).toHaveText('0,0');
    await expect(page.locator('[data-chart-series]')).toHaveCount(2);
    await expect(page.locator('[data-node-summary="1"] [data-summary-value="avg"]')).toHaveText('4,0 °C');
    await page.getByRole('button', { name: '1 jam', exact: true }).click();
    await expect(page.locator('[data-node-summary="1"] [data-summary-value="avg"]')).toHaveText('2,0 °C');
    await expect(page.locator('[data-history-body] tr')).toHaveCount(4);
    await page.getByRole('button', { name: 'Kelembapan tanah', exact: true }).click();
    await expect(page.locator('[data-chart-empty]')).toBeVisible();
    await expect(page.locator('[data-chart-series]')).toHaveCount(0);
});

test('dashboard memiliki enam kanal sensor tanpa kode simulasi', async ({ page, dashboardHtml }) => {
    await openDashboard(page, dashboardHtml);
    await expect(page.locator('[data-demo-toggle]')).toHaveCount(0);
    await expect(page.locator('[data-sensor-reading]')).toHaveCount(6);
    for (const node of await page.locator('[data-node-id]').all()) {
        await expect(node.locator('[data-sensor-reading]')).toHaveCount(3);
        await expect(node).toContainText('Suhu udara');
        await expect(node).toContainText('Kelembapan udara');
        await expect(node).toContainText('Kelembapan tanah');
        await expect(node).toContainText('Menunggu integrasi');
    }
    for (const value of await page.locator('[data-current-value]').all()) {
        await expect(value).toHaveText('—');
    }
    await expect(page.locator('[data-chart-empty]')).toBeVisible();
    await expect(page.locator('[data-chart-series]')).toHaveCount(0);
    await expect(page.locator('[data-history-body]')).toContainText('Belum ada riwayat pembacaan');
});

test('filter sensor dan waktu tetap dapat digunakan tanpa pembacaan', async ({ page, dashboardHtml }) => {
    await openDashboard(page, dashboardHtml);
    await page.getByRole('button', { name: 'Kelembapan tanah', exact: true }).click();
    await page.getByRole('button', { name: '6 jam', exact: true }).click();
    await expect(page.locator('[data-active-sensor]')).toHaveText('Kelembapan tanah · %');
    await expect(page.getByRole('button', { name: '6 jam', exact: true })).toHaveAttribute('aria-pressed', 'true');
    await expect(page.locator('[data-chart]')).toHaveAttribute('aria-label', /Kelembapan tanah.*6 jam/);
    for (const value of await page.locator('[data-summary-value]').all()) await expect(value).toHaveText('—');
    for (const button of await page.locator('[data-select-sensor], [data-select-range]').all()) {
        expect((await button.boundingBox()).height).toBeGreaterThanOrEqual(44);
    }
});

for (const width of [360, 768, 1440]) {
    for (const theme of ['light', 'dark']) {
        test(`dashboard ${width}px ${theme} responsif dan tema tersimpan`, async ({ page, dashboardHtml }) => {
            const errors = [];
            page.on('pageerror', (error) => errors.push(error.message));
            page.on('console', (message) => {
                if (message.type() === 'error') errors.push(message.text());
            });
            await page.setViewportSize({ width, height: 900 });
            await page.emulateMedia({ colorScheme: theme });
            await openDashboard(page, dashboardHtml);
            await expect(page.locator('html')).toHaveAttribute('data-theme', theme);
            expect(await page.evaluate(() => document.documentElement.scrollWidth)).toBeLessThanOrEqual(width);
            for (const item of await page.locator('.dashboard-header, .node-card, .history-table, .trend-card').all()) {
                const box = await item.boundingBox();
                expect(box.x).toBeGreaterThanOrEqual(0);
                expect(box.x + box.width).toBeLessThanOrEqual(width);
            }
            const first = await page.locator('[data-node-id="1"]').boundingBox();
            const second = await page.locator('[data-node-id="2"]').boundingBox();
            if (width <= 760) {
                expect(second.y).toBeGreaterThan(first.y + first.height);
            } else {
                expect(Math.abs(first.y - second.y)).toBeLessThanOrEqual(1);
            }
            const opposite = theme === 'dark' ? 'light' : 'dark';
            await page.locator('[data-theme-toggle]').click();
            await page.reload();
            await expect(page.locator('html')).toHaveAttribute('data-theme', opposite);
            await page.getByRole('link', { name: 'Muat ulang', exact: true }).click();
            await expect(page.getByRole('heading', { name: 'Ringkasan monitoring', exact: true })).toBeVisible();
            await expect(page.locator('html')).toHaveAttribute('data-theme', opposite);
            await expect(page.getByRole('button', { name: 'Keluar', exact: true })).toBeVisible();
            expect(errors).toEqual([]);
        });
    }
}
