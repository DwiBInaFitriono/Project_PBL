import { test, expect, openDashboard } from './support/dashboard-fixture.js';

test('menu pengaturan punya ikon dan dapat digunakan ketika sidebar ringkas', async ({ page, dashboardHtml, renderPage }) => {
    await page.setViewportSize({ width: 1440, height: 900 });
    await openDashboard(page, dashboardHtml);
    const sidebar = page.locator('[data-sidebar]');
    await expect(sidebar.getByRole('link', { name: 'Setting ESP', exact: true })).toBeVisible();
    await expect(sidebar.getByRole('link', { name: 'Setting Akun', exact: true })).toBeVisible();
    await page.route('**/settings/*', (route) => {
        const url = new URL(route.request().url());
        return route.fulfill({ contentType: 'text/html', body: renderPage(url.pathname) });
    });
    await page.locator('[data-sidebar-collapse]').click();
    for (const [label, path, icon] of [['Setting ESP', 'esp', 'esp'], ['Setting Akun', 'account', 'account']]) {
        const link = sidebar.getByRole('link', { name: label, exact: true });
        await expect(link).toHaveAttribute('title', label);
        await expect(link.locator(`[data-nav-icon="${icon}"]`)).toBeVisible();
        await link.click();
        await expect(page).toHaveURL(new RegExp(`/settings/${path}$`));
        await expect(page.getByRole('heading', { name: label, exact: true })).toBeVisible();
        await expect(link).toHaveAttribute('aria-current', 'page');
        await expect(sidebar.getByRole('link', { name: 'Dashboard', exact: true })).not.toHaveAttribute('aria-current');
        await expect(sidebar).toHaveCSS('width', '80px');
    }
});

for (const width of [320, 768, 1440]) {
    for (const theme of ['light', 'dark']) {
        test(`pengaturan ${width}px ${theme} responsif dan jujur tentang ESP`, async ({ page, renderPage }, testInfo) => {
            const errors = [];
            page.on('pageerror', (error) => errors.push(error.message));
            await page.setViewportSize({ width, height: 900 });
            await page.emulateMedia({ colorScheme: theme, reducedMotion: 'reduce' });
            await page.route('**/settings/*', (route) => {
                const url = new URL(route.request().url());
                return route.fulfill({ contentType: 'text/html', body: renderPage(url.pathname) });
            });
            for (const [path, label] of [['esp', 'Setting ESP'], ['account', 'Setting Akun']]) {
                await page.goto(`/settings/${path}`);
                await expect(page.getByRole('heading', { name: label, exact: true })).toBeVisible();
                await expect(page.locator('html')).toHaveAttribute('data-theme', theme);
                expect(await page.evaluate(() => document.documentElement.scrollWidth)).toBeLessThanOrEqual(width);
                for (const control of await page.locator('.settings-panel, .settings-form input, .settings-save').all()) {
                    const box = await control.boundingBox();
                    if (!box) continue;
                    expect(box.x).toBeGreaterThanOrEqual(0);
                    expect(box.x + box.width).toBeLessThanOrEqual(width + 1);
                }
                if (path === 'esp') {
                    await expect(page.locator('main')).toContainText('Menunggu integrasi');
                    await expect(page.locator('main')).toContainText('Node 1');
                    await expect(page.locator('main')).toContainText('Node 2');
                    await expect(page.locator('main form')).toHaveCount(0);
                } else {
                    await expect(page.getByLabel('Nama lengkap', { exact: true })).toHaveValue('Operator Uji');
                    await expect(page.getByLabel('Alamat email', { exact: true })).toHaveValue('ui-test@example.test');
                    for (const password of await page.locator('input[name="current_password"]').all()) {
                        await expect(password).toHaveAttribute('type', 'password');
                    }
                    for (const form of await page.locator('.settings-form').all()) {
                        await expect(form.locator('input[name="_token"]')).toHaveCount(1);
                    }
                }
                await page.screenshot({ path: testInfo.outputPath(`${path}.png`), fullPage: true });
            }
            expect(errors).toEqual([]);
        });
    }
}
