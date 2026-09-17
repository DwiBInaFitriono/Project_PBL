import { test, expect, openDashboard } from './support/dashboard-fixture.js';

for (const viewport of [{ width: 1440, height: 600 }, { width: 360, height: 568 }]) {
    test(`Setting Akun tetap di bawah saat menu digulir ${viewport.width}px`, async ({ page, dashboardHtml }, testInfo) => {
        await page.setViewportSize(viewport);
        await openDashboard(page, dashboardHtml);
        const sidebar = page.locator('[data-sidebar]');
        if (viewport.width < 1025) await page.locator('[data-sidebar-trigger]').click();
        await sidebar.getByRole('button', { name: 'Menu Node 2', exact: true }).click();
        const account = sidebar.getByRole('link', { name: 'Setting Akun', exact: true });
        const before = await account.boundingBox();
        expect(before.y + before.height).toBeGreaterThan(viewport.height - 50);
        expect(before.y + before.height).toBeLessThanOrEqual(viewport.height - 10);
        const scrollArea = page.locator('[data-sidebar-scroll]');
        await expect(scrollArea).toHaveCSS('overflow-y', 'auto');
        expect(await scrollArea.evaluate((el) => el.scrollHeight > el.clientHeight)).toBe(true);
        await scrollArea.evaluate((el) => { el.scrollTop = el.scrollHeight; });
        expect(await scrollArea.evaluate((el) => el.scrollTop)).toBeGreaterThan(0);
        const after = await account.boundingBox();
        expect(Math.abs(after.y - before.y)).toBeLessThanOrEqual(1);
        await expect(account).toBeInViewport();
        await expect(sidebar.getByRole('link', { name: 'Setting ESP', exact: true })).toBeInViewport();
        await page.screenshot({ path: testInfo.outputPath('pinned-account.png') });
        if (viewport.width > 1024) {
            await page.locator('[data-sidebar-collapse]').click();
            await expect(account.locator('[data-nav-icon="account"]')).toBeVisible();
            const compact = await account.boundingBox();
            expect(compact.y + compact.height).toBeGreaterThan(viewport.height - 50);
        }
    });
}

for (const theme of ['light', 'dark']) {
    test(`scrollbar halaman dan sidebar ${theme} memakai warna khusus`, async ({ page, dashboardHtml }) => {
        await page.setViewportSize({ width: 1440, height: 600 });
        await page.emulateMedia({ colorScheme: theme });
        await openDashboard(page, dashboardHtml);
        const rootStyle = await page.locator('html').evaluate((el) => ({
            thumb: getComputedStyle(el, '::-webkit-scrollbar-thumb').backgroundColor,
            radius: getComputedStyle(el, '::-webkit-scrollbar-thumb').borderRadius,
            width: getComputedStyle(el, '::-webkit-scrollbar').width,
        }));
        expect(rootStyle.thumb).toBe(theme === 'dark' ? 'rgb(97, 118, 170)' : 'rgb(113, 131, 181)');
        expect(rootStyle.radius).toBe('999px');
        expect(rootStyle.width).toBe('10px');
        const menuStyle = await page.locator('[data-sidebar-scroll]').evaluate((el) => ({
            thumb: getComputedStyle(el, '::-webkit-scrollbar-thumb').backgroundColor,
            width: getComputedStyle(el, '::-webkit-scrollbar').width,
        }));
        expect(menuStyle.thumb).toBe('rgb(106, 127, 192)');
        expect(menuStyle.width).toBe('6px');
        await page.mouse.move(1100, 400);
        await page.mouse.wheel(0, 700);
        await expect.poll(() => page.evaluate(() => window.scrollY)).toBeGreaterThan(0);
        expect(await page.evaluate(() => document.documentElement.scrollWidth)).toBeLessThanOrEqual(1440);
    });
}
