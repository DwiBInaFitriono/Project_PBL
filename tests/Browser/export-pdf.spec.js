import { test, expect } from './support/dashboard-fixture.js';
import { readFileSync } from 'node:fs';

test('ekspor menampilkan laporan siap PDF bukan CSV', async ({ page, renderResponse }, testInfo) => {
    await page.route('**/history/export*', (route) => {
        const url = new URL(route.request().url());
        return route.fulfill(renderResponse(url.pathname + url.search));
    });
    await page.goto('/history/export?node=2&sensor=temperature&from=2026-01-01&to=2026-01-02');
    await expect(page.locator('body')).toContainText('Rebung Pintar');
    await expect(page.locator('body')).toContainText('2026-01-01');
    await expect(page.locator('#print-report')).toBeVisible();
    await page.evaluate(() => { window.__printCalled = false; window.print = () => { window.__printCalled = true; }; });
    await page.locator('#print-report').click();
    expect(await page.evaluate(() => window.__printCalled)).toBe(true);
    await page.emulateMedia({ media: 'print' });
    await expect(page.locator('#print-report')).toBeHidden();
    const file = testInfo.outputPath('riwayat.pdf');
    await page.pdf({ path: file, format: 'A4', landscape: true, printBackground: true, preferCSSPageSize: true });
    expect(readFileSync(file).subarray(0, 5).toString()).toBe('%PDF-');
});
