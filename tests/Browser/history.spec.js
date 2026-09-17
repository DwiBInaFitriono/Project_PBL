import { test, expect } from './support/dashboard-fixture.js';

for (const width of [360, 1440]) {
    test(`riwayat lengkap ${width}px menampilkan filter dan tautan ekspor`, async ({ page, renderPage }) => {
        await page.setViewportSize({ width, height: 900 });
        await page.route('**/history*', (route) => {
            const url = new URL(route.request().url());
            return route.fulfill({ contentType: 'text/html', body: renderPage(url.pathname + url.search) });
        });
        await page.goto('/history');
        await expect(page.getByRole('heading', { name: 'Riwayat pembacaan', exact: true })).toBeVisible();
        const node = page.getByRole('combobox', { name: 'Node', exact: true });
        const sensor = page.getByRole('combobox', { name: 'Parameter', exact: true });
        await node.click();
        await page.getByRole('option', { name: 'Node 2', exact: true }).click();
        await sensor.click();
        await page.getByRole('option', { name: 'Kelembapan tanah', exact: true }).click();
        await page.locator('input[name="from"]').fill('2026-01-01');
        await page.locator('input[name="to"]').fill('2026-01-02');
        await page.locator('.history-filters button[type="submit"]').click();
        await expect(page).toHaveURL(/node=2/);
        await expect(page.locator('select[name="sensor"]')).toHaveValue('soil_moisture');
        const exportLink = page.locator('a[href*="/history/export"]');
        await expect(exportLink).toHaveAttribute('href', /node=2/);
        await expect(exportLink).toHaveAttribute('href', /from=2026-01-01/);
        await expect(exportLink).toHaveAttribute('href', /to=2026-01-02/);
        await expect(exportLink).toHaveAttribute('href', /sensor=soil_moisture/);
        expect(await page.evaluate(() => document.documentElement.scrollWidth)).toBeLessThanOrEqual(width);
    });
}
