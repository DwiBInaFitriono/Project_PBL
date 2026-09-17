import { test, expect, openDashboard, withMonitoringData } from './support/dashboard-fixture.js';

test('drawer mobile menutup dengan Escape dan overlay serta mengembalikan fokus', async ({ page, dashboardHtml }) => {
    await page.setViewportSize({ width: 390, height: 844 });
    await openDashboard(page, dashboardHtml);
    const sidebar = page.locator('[data-sidebar]');
    const opener = page.locator('[data-sidebar-trigger]');
    await expect(sidebar).not.toBeVisible();
    await opener.click();
    await expect(sidebar).toBeVisible();
    await expect(opener).toHaveAttribute('aria-expanded', 'true');
    await expect(page.locator('[data-workspace]')).toHaveAttribute('inert', '');
    await expect(page.getByRole('button', { name: 'Tutup navigasi', exact: true })).toBeFocused();
    await sidebar.getByRole('button', { name: 'Menu Node 2', exact: true }).click();
    await expect(sidebar.locator('#node-2-menu')).toBeVisible();
    const firstLink = sidebar.getByRole('link', { name: 'Rebung Pintar — beranda', exact: true });
    const lastLink = sidebar.getByRole('link', { name: 'Setting Akun', exact: true });
    await firstLink.focus();
    await page.keyboard.press('Shift+Tab');
    await expect(lastLink).toBeFocused();
    await page.keyboard.press('Tab');
    await expect(firstLink).toBeFocused();
    await page.keyboard.press('Escape');
    await expect(sidebar).not.toBeVisible();
    await expect(opener).toBeFocused();
    await expect(page.locator('[data-workspace]')).not.toHaveAttribute('inert');
    await opener.click();
    await page.locator('[data-sidebar-backdrop]').click({ position: { x: 370, y: 400 } });
    await expect(sidebar).not.toBeVisible();
    await expect(opener).toBeFocused();
    expect(await page.evaluate(() => document.documentElement.scrollWidth)).toBeLessThanOrEqual(390);
});

test('tautan sidebar membuka halaman node dan parameter yang sesuai', async ({ page, renderPage }) => {
    await page.setViewportSize({ width: 1440, height: 900 });
    await page.route(/\/(dashboard|nodes\/[^/?]+)(\?.*)?$/, (route) => {
        const url = new URL(route.request().url());
        return route.fulfill({ contentType: 'text/html', body: renderPage(url.pathname + url.search) });
    });
    await page.goto('/dashboard');
    const sidebar = page.locator('[data-sidebar]');
    await sidebar.getByRole('button', { name: 'Menu Node 1', exact: true }).click();
    await sidebar.getByRole('link', { name: 'Ringkasan Node 1', exact: true }).click();
    await expect(page).toHaveURL(/\/nodes\/1$/);
    await expect(page.getByRole('heading', { name: 'Monitoring Node 1', exact: true })).toBeVisible();
    await expect(page.locator('[data-node-id]')).toHaveCount(1);
    await expect(page.locator('[data-node-id="1"] [data-sensor-reading]')).toHaveCount(3);
    await expect(sidebar.getByRole('link', { name: 'Ringkasan Node 1', exact: true })).toHaveAttribute('aria-current', 'page');
    await expect(sidebar.getByRole('button', { name: 'Menu Node 1', exact: true })).toHaveAttribute('aria-expanded', 'true');

    await sidebar.getByRole('button', { name: 'Menu Node 2', exact: true }).click();
    await sidebar.locator('#node-2-menu').getByRole('link', { name: 'Kelembapan tanah', exact: true }).click();
    await expect(page).toHaveURL(/\/nodes\/2\?sensor=soil_moisture&hours=24#tren$/);
    await expect(page.getByRole('heading', { name: 'Monitoring Node 2', exact: true })).toBeVisible();
    await expect(page.locator('[data-node-id]')).toHaveCount(1);
    await expect(page.locator('[data-node-id="2"] [data-sensor-reading]')).toHaveCount(3);
    await expect(page.locator('[data-active-sensor]')).toHaveText('Kelembapan tanah · %');
    await expect(sidebar.locator('#node-2-menu').getByRole('link', { name: 'Kelembapan tanah', exact: true })).toHaveAttribute('aria-current', 'page');
    await expect(sidebar.getByRole('button', { name: 'Menu Node 2', exact: true })).toHaveAttribute('aria-expanded', 'true');
    await expect(page.locator('[data-chart]')).toHaveAttribute('aria-label', /Node 2/);
    await expect(page.locator('[data-chart]')).not.toHaveAttribute('aria-label', /Node 1/);
});

test('warna Node 2 konsisten pada grafik detail', async ({ page, renderPage }) => {
    const html = renderPage('/nodes/2');
    const sensor = { id: 'temperature', name: 'Suhu udara', unit: '°C', decimals: 1 };
    const data = {
        sensors: [sensor],
        nodes: [{ id: '2', name: 'Node 2', sensors: [{ ...sensor, readings: [{ recorded_at: '2026-01-01T10:00:00Z', value: 27 }] }] }],
        generatedAt: '2026-01-01T10:00:00Z',
    };
    await page.route('**/nodes/2', (route) => route.fulfill({ contentType: 'text/html', body: withMonitoringData(html, data) }));
    await page.goto('/nodes/2');
    await expect(page.locator('[data-chart-series="2"]')).toHaveClass(/series-node-2/);
    await expect(page.locator('[data-sensor-id="temperature"] [data-sparkline] polyline')).toHaveClass(/series-node-2/);
});

for (const width of [360, 1440]) {
    for (const theme of ['light', 'dark']) {
        test(`halaman node ${width}px ${theme} responsif tanpa error`, async ({ page, renderPage }, testInfo) => {
            const errors = [];
            page.on('pageerror', (error) => errors.push(error.message));
            page.on('console', (message) => {
                if (message.type() === 'error') errors.push(message.text());
            });
            await page.setViewportSize({ width, height: 900 });
            await page.emulateMedia({ colorScheme: theme, reducedMotion: 'reduce' });
            await page.route(/\/nodes\/[12](\?.*)?$/, (route) => {
                const url = new URL(route.request().url());
                return route.fulfill({ contentType: 'text/html', body: renderPage(url.pathname + url.search) });
            });
            for (const id of ['1', '2']) {
                await page.goto(`/nodes/${id}?sensor=soil_moisture`);
                await expect(page.getByRole('heading', { name: `Monitoring Node ${id}`, exact: true })).toBeVisible();
                await expect(page.locator('html')).toHaveAttribute('data-theme', theme);
                await expect(page.locator('[data-active-sensor]')).toHaveText('Kelembapan tanah · %');
                expect(await page.evaluate(() => document.documentElement.scrollWidth)).toBeLessThanOrEqual(width);
                for (const item of await page.locator('.dashboard-header, .node-card, .history-table, .trend-card').all()) {
                    const box = await item.boundingBox();
                    expect(box.x).toBeGreaterThanOrEqual(0);
                    expect(box.x + box.width).toBeLessThanOrEqual(width);
                }
            }
            await page.screenshot({ path: testInfo.outputPath('node-2.png'), fullPage: true });
            if (width <= 1024) {
                await page.locator('[data-sidebar-trigger]').click();
                const sidebar = page.locator('[data-sidebar]');
                await expect(sidebar).toBeVisible();
                await expect(sidebar.getByRole('button', { name: 'Menu Node 2', exact: true })).toHaveAttribute('aria-expanded', 'true');
                await page.screenshot({ path: testInfo.outputPath('drawer.png') });
                await sidebar.locator('#node-2-menu').getByRole('link', { name: 'Suhu udara', exact: true }).click();
                await expect(page).toHaveURL(/\/nodes\/2\?sensor=temperature&hours=24#tren$/);
                await expect(sidebar).not.toBeVisible();
                await expect(page.locator('[data-workspace]')).not.toHaveAttribute('inert');
                await expect(page.locator('[data-active-sensor]')).toHaveText('Suhu udara · °C');
            }
            expect(errors).toEqual([]);
        });
    }
}

test('sidebar memiliki dropdown node yang dapat dibuka dengan keyboard', async ({ page, dashboardHtml }) => {
    await page.setViewportSize({ width: 1440, height: 900 });
    await openDashboard(page, dashboardHtml);
    const sidebar = page.locator('[data-sidebar]');
    await expect(sidebar).toBeVisible();
    const nodeOne = sidebar.getByRole('button', { name: 'Menu Node 1', exact: true });
    const nodeTwo = sidebar.getByRole('button', { name: 'Menu Node 2', exact: true });
    await expect(nodeOne).toHaveAttribute('aria-expanded', 'false');
    await nodeOne.focus();
    await page.keyboard.press('Enter');
    await expect(nodeOne).toHaveAttribute('aria-expanded', 'true');
    await expect(sidebar.locator('#node-1-menu')).toBeVisible();
    await expect(sidebar.locator('#node-1-menu').getByRole('link', { name: 'Ringkasan Node 1' })).toHaveAttribute('href', /\/nodes\/1$/);
    await expect(sidebar.locator('#node-1-menu').getByRole('link', { name: 'Suhu udara', exact: true })).toHaveAttribute('href', /\/nodes\/1\?sensor=temperature&hours=24#tren$/);
    await nodeTwo.click();
    await expect(nodeTwo).toHaveAttribute('aria-expanded', 'true');
    await expect(nodeOne).toHaveAttribute('aria-expanded', 'false');
    await expect(sidebar.locator('#node-1-menu')).toBeHidden();
    await nodeTwo.click();
    await expect(sidebar.locator('#node-2-menu')).toBeHidden();
});
