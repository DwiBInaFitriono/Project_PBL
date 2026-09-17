import { test, expect, openDashboard } from './support/dashboard-fixture.js';

test('judul dashboard lebih besar dan setiap menu node memiliki ikon', async ({ page, dashboardHtml }) => {
    await page.setViewportSize({ width: 1440, height: 900 });
    await openDashboard(page, dashboardHtml);
    await expect(page.locator('.workspace-label')).toHaveCSS('font-size', '22px');
    const sidebar = page.locator('[data-sidebar]');
    await expect(sidebar.getByRole('link', { name: 'Dashboard', exact: true })).toHaveCSS('font-size', '15px');
    for (const id of ['1', '2']) {
        const toggle = sidebar.getByRole('button', { name: `Menu Node ${id}`, exact: true });
        await expect(toggle.locator('[data-nav-icon="node"]')).toBeVisible();
        await expect(toggle).toHaveAttribute('title', `Node ${id}`);
        await toggle.click();
        const links = sidebar.locator(`#node-${id}-menu a`);
        await expect(links).toHaveCount(5);
        for (const link of await links.all()) {
            await expect(link.locator('[data-nav-icon]')).toBeVisible();
            await expect(link.locator('[data-nav-icon]')).toHaveAttribute('aria-hidden', 'true');
        }
    }
});

test('sidebar desktop menjadi ikon dan preferensinya bertahan saat reload', async ({ page, dashboardHtml }, testInfo) => {
    await page.setViewportSize({ width: 1440, height: 900 });
    await openDashboard(page, dashboardHtml);
    const sidebar = page.locator('[data-sidebar]');
    const control = page.locator('[data-sidebar-collapse]');
    await expect(control).toHaveAccessibleName('Ringkas sidebar');
    await control.click();
    await expect(control).toHaveAccessibleName('Perluas sidebar');
    await expect(control).toHaveAttribute('aria-expanded', 'false');
    await expect(sidebar).toHaveCSS('width', '80px');
    await expect(page.locator('[data-workspace]')).toHaveCSS('margin-left', '80px');
    await expect(sidebar.locator('.sidebar-menu-label').first()).toBeHidden();
    await expect(sidebar.getByRole('link', { name: 'Dashboard', exact: true }).locator('svg')).toBeVisible();
    await expect(sidebar.getByRole('button', { name: 'Menu Node 2', exact: true }).locator('[data-nav-icon="node"]')).toBeVisible();
    expect(await page.evaluate(() => document.documentElement.scrollWidth)).toBeLessThanOrEqual(1440);
    await page.screenshot({ path: testInfo.outputPath('sidebar-icons.png'), fullPage: true });
    await page.reload();
    await expect(sidebar).toHaveCSS('width', '80px');
    await sidebar.getByRole('button', { name: 'Menu Node 2', exact: true }).click();
    await expect(sidebar).toHaveCSS('width', '240px');
    await expect(sidebar.locator('#node-2-menu')).toBeVisible();
    await expect(control).toHaveAccessibleName('Ringkas sidebar');
});

test('mode ikon tidak merusak drawer saat beralih ke mobile', async ({ page, dashboardHtml }) => {
    await page.setViewportSize({ width: 1440, height: 900 });
    await openDashboard(page, dashboardHtml);
    await page.locator('[data-sidebar-collapse]').click();
    await page.setViewportSize({ width: 360, height: 800 });
    const sidebar = page.locator('[data-sidebar]');
    await expect(page.locator('[data-sidebar-collapse]')).toBeHidden();
    await page.locator('[data-sidebar-trigger]').click();
    await expect(sidebar).toHaveCSS('width', '280px');
    await expect(sidebar.locator('.sidebar-menu-label').first()).toBeVisible();
    await page.keyboard.press('Escape');
    await expect(sidebar).toBeHidden();
    await expect(page.locator('[data-sidebar-trigger]')).toBeFocused();
    await page.setViewportSize({ width: 1440, height: 900 });
    await expect(sidebar).toHaveCSS('width', '80px');
    await expect(sidebar).not.toHaveAttribute('inert');
    await expect(page.locator('[data-workspace]')).not.toHaveAttribute('inert');
});

test('preferensi ringkas tidak menyembunyikan submenu atau fokus saat diperluas', async ({ page, dashboardHtml }) => {
    await page.setViewportSize({ width: 1440, height: 900 });
    await openDashboard(page, dashboardHtml);
    const sidebar = page.locator('[data-sidebar]');
    const node = sidebar.getByRole('button', { name: 'Menu Node 1', exact: true });
    await node.click();
    await page.locator('[data-sidebar-collapse]').click();
    await expect(node).toHaveAttribute('aria-expanded', 'false');
    await expect(sidebar.locator('#node-1-menu')).toBeHidden();
    await page.locator('[data-sidebar-collapse]').focus();
    await page.keyboard.press('Enter');
    await expect(sidebar).toHaveCSS('width', '240px');
    await node.click();
    await expect(sidebar.locator('#node-1-menu')).toBeVisible();
    await expect(page.locator('[data-workspace]')).toHaveCSS('margin-left', '240px');
});

test('judul dan kontrol header tidak terpotong di layar kecil', async ({ page, dashboardHtml }) => {
    for (const width of [320, 360, 768, 1024, 1025]) {
        await page.setViewportSize({ width, height: 900 });
        await openDashboard(page, dashboardHtml);
        const overflow = await page.evaluate(() => [...document.querySelectorAll('main *')].map((el) => ({ tag: el.tagName, class: el.className, right: el.getBoundingClientRect().right })).filter((el) => el.right > innerWidth));
        expect(await page.evaluate(() => document.documentElement.scrollWidth), JSON.stringify(overflow)).toBeLessThanOrEqual(width);
        for (const selector of ['.workspace-label', '[data-theme-toggle]', '.dashboard-logout']) {
            const element = page.locator(selector);
            await expect(element).toBeVisible();
            const box = await element.boundingBox();
            expect(box.x).toBeGreaterThanOrEqual(0);
            expect(box.x + box.width).toBeLessThanOrEqual(width);
        }
        expect(await page.locator('.workspace-label').evaluate((el) => parseFloat(getComputedStyle(el).fontSize))).toBeGreaterThanOrEqual(18);
    }
});

test('animasi sidebar ringan dan menghormati reduced motion', async ({ page, dashboardHtml }) => {
    await page.setViewportSize({ width: 1440, height: 900 });
    await page.emulateMedia({ reducedMotion: 'no-preference' });
    await openDashboard(page, dashboardHtml);
    const sidebar = page.locator('[data-sidebar]');
    await expect(sidebar).toHaveCSS('transition-property', 'width');
    await expect(sidebar).toHaveCSS('transition-duration', '0.28s');
    await expect(sidebar).toHaveCSS('transition-timing-function', 'cubic-bezier(0.22, 1, 0.36, 1)');
    await expect(page.locator('[data-workspace]')).toHaveCSS('transition-property', 'margin-left');
    await page.locator('[data-sidebar-collapse]').click();
    await expect(sidebar).toHaveCSS('width', '80px');
    await page.emulateMedia({ reducedMotion: 'reduce' });
    await expect(sidebar).toHaveCSS('transition-duration', '0s');
    await page.locator('[data-sidebar-collapse]').click();
    await expect(sidebar).toHaveCSS('width', '240px');
});

test('sidebar dapat diringkas meskipun localStorage diblokir', async ({ page, dashboardHtml }) => {
    await page.addInitScript(() => {
        Storage.prototype.getItem = () => { throw new DOMException('Blocked', 'SecurityError'); };
        Storage.prototype.setItem = () => { throw new DOMException('Blocked', 'SecurityError'); };
    });
    await page.setViewportSize({ width: 1440, height: 900 });
    await openDashboard(page, dashboardHtml);
    await page.locator('[data-sidebar-collapse]').click();
    await expect(page.locator('[data-sidebar]')).toHaveCSS('width', '80px');
    await page.locator('[data-sidebar-collapse]').click();
    await expect(page.locator('[data-sidebar]')).toHaveCSS('width', '240px');
});
