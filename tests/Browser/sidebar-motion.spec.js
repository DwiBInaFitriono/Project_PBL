import { test, expect, openDashboard } from './support/dashboard-fixture.js';

// Capture transitions in the same browser task as the click, before any can finish.
async function clickAndPause(page, selector) {
    return page.evaluate((selector) => {
        document.querySelector('[data-sidebar]').getBoundingClientRect();
        document.querySelector(selector).click();
        document.querySelector('[data-sidebar]').getBoundingClientRect();
        const animations = document.getAnimations();
        for (const animation of animations) {
            animation.pause();
            animation.currentTime = animation.effect.getComputedTiming().endTime / 2;
        }
        return animations.map((animation) => animation.transitionProperty);
    }, selector);
}

async function transitionState(locator) {
    return locator.evaluate((element) => {
        const style = getComputedStyle(element);
        return { visibility: style.visibility, opacity: Number(style.opacity), width: element.getBoundingClientRect().width, x: element.getBoundingClientRect().x };
    });
}

async function finishTransitions(page) {
    await page.evaluate(() => document.getAnimations().forEach((animation) => animation.finish()));
}

for (const width of [360, 1440]) {
    test(`sidebar pemantau ${width}px tidak menyisakan judul pengaturan kosong`, async ({ page, renderPage }) => {
        await page.setViewportSize({ width, height: 800 });
        await openDashboard(page, renderPage('/dashboard', 'viewer'));
        const sidebar = page.locator('[data-sidebar]');
        if (width <= 1024) await page.locator('[data-sidebar-trigger]').click();
        await expect(sidebar.getByText('PENGATURAN', { exact: true })).toHaveCount(0);
        await expect(sidebar.getByRole('link', { name: 'Setting ESP', exact: true })).toHaveCount(0);
        const account = sidebar.getByRole('link', { name: 'Setting Akun', exact: true });
        await expect(account).toBeInViewport();
        expect((await account.boundingBox()).y).toBeGreaterThan(700);
        if (width > 1024) {
            await page.locator('[data-sidebar-collapse]').click();
            await expect(account.locator('[data-nav-icon]')).toBeVisible();
            await expect(account).toHaveAccessibleName('Setting Akun');
        }
    });
}

test('desktop meredupkan label tanpa loncatan ikon saat menutup', async ({ page, dashboardHtml }) => {
    await page.setViewportSize({ width: 1440, height: 900 });
    await page.emulateMedia({ reducedMotion: 'no-preference' });
    await openDashboard(page, dashboardHtml);
    const sidebar = page.locator('[data-sidebar]');
    const icon = sidebar.locator('[data-nav-icon="dashboard"]');
    const before = await icon.boundingBox();
    const label = sidebar.locator('.sidebar-menu-label').first();
    await expect(label).toHaveCSS('transition-property', /opacity/);
    const properties = await clickAndPause(page, '[data-sidebar-collapse]');
    expect(properties).toContain('width');
    expect(properties).toContain('opacity');
    const midpoint = await transitionState(sidebar);
    expect(midpoint.width).toBeGreaterThan(80);
    expect(midpoint.width).toBeLessThan(240);
    const labelMidpoint = await transitionState(label);
    expect(labelMidpoint.visibility).toBe('visible');
    expect(labelMidpoint.opacity).toBeGreaterThan(0);
    expect(labelMidpoint.opacity).toBeLessThan(1);
    const during = await icon.boundingBox();
    expect(Math.abs(during.x - before.x)).toBeLessThanOrEqual(1);
    expect(Math.abs(during.y - before.y)).toBeLessThanOrEqual(1);
    await finishTransitions(page);
    await expect(sidebar).toHaveCSS('width', '80px');
    await expect(label).toBeHidden();
    const after = await icon.boundingBox();
    expect(Math.abs(after.y - before.y)).toBeLessThanOrEqual(1);
});

test('drawer mobile tetap terlihat selama transisi keluar dan overlay memudar', async ({ page, dashboardHtml }) => {
    await page.setViewportSize({ width: 390, height: 844 });
    await page.emulateMedia({ reducedMotion: 'no-preference' });
    await openDashboard(page, dashboardHtml);
    const sidebar = page.locator('[data-sidebar]');
    const overlay = page.locator('[data-sidebar-backdrop]');
    await page.locator('[data-sidebar-trigger]').click();
    await expect(sidebar).toHaveCSS('transform', 'matrix(1, 0, 0, 1, 0, 0)');
    const properties = await clickAndPause(page, '[data-sidebar-close]');
    expect(properties).toContain('transform');
    expect(properties).toContain('opacity');
    const midpoint = await transitionState(sidebar);
    expect(midpoint.visibility).toBe('visible');
    expect(midpoint.x).toBeLessThan(0);
    expect(midpoint.x).toBeGreaterThan(-280);
    await expect(sidebar).toHaveAttribute('inert', '');
    const overlayMidpoint = await transitionState(overlay);
    expect(overlayMidpoint.opacity).toBeGreaterThan(0);
    expect(overlayMidpoint.opacity).toBeLessThan(1);
    await finishTransitions(page);
    await expect(sidebar).toBeHidden();
    await expect(overlay).toBeHidden();
    await expect(page.locator('[data-sidebar-trigger]')).toBeFocused();
});

test('drawer reduced motion menutup langsung dan aman saat dibuka ulang cepat', async ({ page, dashboardHtml }) => {
    await page.setViewportSize({ width: 390, height: 844 });
    await page.emulateMedia({ reducedMotion: 'reduce' });
    await openDashboard(page, dashboardHtml);
    const sidebar = page.locator('[data-sidebar]');
    await page.locator('[data-sidebar-trigger]').click();
    await page.keyboard.press('Escape');
    await expect(sidebar).toBeHidden();
    await expect(sidebar).toHaveCSS('transition-duration', '0s');
    await page.emulateMedia({ reducedMotion: 'no-preference' });
    await page.locator('[data-sidebar-trigger]').click();
    await page.locator('[data-sidebar-close]').evaluate((button) => button.click());
    await page.locator('[data-sidebar-trigger]').evaluate((button) => button.click());
    await expect(sidebar).toBeVisible();
    await expect(sidebar).not.toHaveAttribute('inert');
    await expect(page.getByRole('button', { name: 'Tutup navigasi', exact: true })).toBeFocused();
});
