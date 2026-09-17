import { test, expect } from './support/dashboard-fixture.js';

for (const width of [320, 1440]) {
    test(`akses tamu ${width}px memberi peringatan login tanpa membocorkan data`, async ({ page }) => {
        await page.setViewportSize({ width, height: 900 });
        for (const path of ['/dashboard', '/nodes/1', '/history', '/history/export', '/settings/esp', '/settings/account']) {
            await page.goto(path);
            await expect(page).toHaveURL(/\/login$/);
            await expect(page.locator('[data-access-warning]')).toContainText('Silakan masuk');
            await expect(page.locator('[data-monitoring-root]')).toHaveCount(0);
            await expect(page.getByLabel('Alamat email')).toBeVisible();
            expect(await page.evaluate(() => document.documentElement.scrollWidth)).toBeLessThanOrEqual(width);
        }
    });
}

test('peringatan akses ditolak tampil di dashboard tanpa mengubah hak pemantau', async ({ page, renderPage }) => {
    await page.route('**/dashboard*', (route) => {
        const url = new URL(route.request().url());
        return route.fulfill({ contentType: 'text/html', body: renderPage(url.pathname + url.search, 'viewer') });
    });
    await page.goto('/dashboard?access=denied');
    await expect(page.locator('[data-access-warning]')).toContainText('tidak memiliki izin');
    await expect(page.getByRole('heading', { name: 'Ringkasan monitoring', exact: true })).toBeVisible();
    await expect(page.getByRole('link', { name: 'Setting ESP', exact: true })).toHaveCount(0);
});
