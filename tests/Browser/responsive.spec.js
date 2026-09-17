import { devices } from '@playwright/test';
import { test, expect, withMonitoringData } from './support/dashboard-fixture.js';

const privatePaths = ['/dashboard', '/nodes/1', '/nodes/2', '/history', '/settings/account', '/settings/esp'];
const viewports = [
    [320, 568], [360, 640], [375, 667], [390, 844], [412, 915], [430, 932],
    [568, 320], [844, 390], [600, 960], [768, 1024], [820, 1180],
    [1024, 768], [1025, 768], [1280, 600], [1440, 900], [1920, 1080], [2560, 1440],
];

for (const [width, height] of viewports) {
    test(`semua halaman ${width}x${height} tidak meluber atau memotong teks`, async ({ page, renderPage }, testInfo) => {
        test.setTimeout(60000);
        const errors = [];
        page.on('pageerror', (error) => errors.push(error.message));
        await page.setViewportSize({ width, height });
        await page.emulateMedia({ reducedMotion: 'reduce' });
        await routeViews(page, renderPage);
        for (const path of ['/login', '/register', ...privatePaths]) {
            await page.goto(path);
            await expect(page.locator('h1')).toBeVisible();
            const issues = await page.evaluate(() => {
                const viewport = document.documentElement.clientWidth;
                const issues = [];
                if (document.documentElement.scrollWidth > viewport) issues.push('page overflow');
                for (const element of document.querySelectorAll('.workspace-label, .sensor-reading, .section-heading, .history-filter-actions, .settings-panel, .node-summary, .monitoring-overview > div')) {
                    const box = element.getBoundingClientRect();
                    if (box.width === 0) continue;
                    if (box.left < -1 || box.right > viewport + 1 || element.scrollWidth > element.clientWidth + 1) {
                        issues.push(`${element.className}: ${element.scrollWidth}/${element.clientWidth}, x=${box.left}, right=${box.right}`);
                    }
                }
                return issues;
            });
            expect.soft(issues, `${path} ${width}x${height}`).toEqual([]);
            if ([320, 390, 768, 1440].includes(width) && ['/login', '/dashboard', '/history', '/settings/account'].includes(path)) {
                await page.screenshot({ path: testInfo.outputPath(`${path.replaceAll('/', '-')}.png`), fullPage: true });
            }
        }
        expect(errors).toEqual([]);
    });
}


test('kartu sensor dan kontrol tetap terbaca di ponsel sempit', async ({ page, renderPage }) => {
    await page.setViewportSize({ width: 320, height: 568 });
    await routeViews(page, renderPage);
    for (const path of ['/dashboard', '/nodes/1', '/nodes/2']) {
        await page.goto(path);
        const readings = page.locator('.node-card').first().locator('.sensor-reading');
        const first = await readings.nth(0).boundingBox();
        const second = await readings.nth(1).boundingBox();
        expect.soft(second.y).toBeGreaterThanOrEqual(first.y + first.height);
        for (const selector of ['.sensor-name', '.sensor-tabs button', '.node-metadata']) {
            for (const item of await page.locator(selector).all()) {
                expect.soft(await item.evaluate((el) => parseFloat(getComputedStyle(el).fontSize)), selector).toBeGreaterThanOrEqual(12);
            }
        }
        const badge = await page.locator('.source-badge').boundingBox();
        const title = await page.locator('#trend-title').boundingBox();
        expect.soft(badge.y).toBeGreaterThanOrEqual(title.y + title.height);
    }
});

test('riwayat berisi data tetap terbaca dan dapat digeser tanpa menggeser halaman', async ({ page, renderPage }, testInfo) => {
    await page.setViewportSize({ width: 320, height: 568 });
    const sensor = { id: 'temperature', name: 'Suhu udara', unit: '°C', decimals: 1 };
    const data = {
        sensors: [sensor],
        nodes: ['1', '2'].map((id) => ({ id, name: `Node ${id}`, sensors: [{ ...sensor, value: 27, readings: [{ recorded_at: '2026-01-01T10:00:00Z', value: 27 }] }] })),
        generatedAt: '2026-01-01T10:00:00Z',
    };
    await page.route('**/dashboard', (route) => route.fulfill({ contentType: 'text/html', body: withMonitoringData(renderPage('/dashboard'), data) }));
    // Static populated fixture exercises the same history table markup without writing application data.
    const historyHtml = renderPage('/history').replace(/<tbody>[\s\S]*?<\/tbody>/, '<tbody><tr><td><time>2026-01-01 17:00:00</time></td><td>Node 1</td><td>Kelembapan tanah</td><td>72.5</td><td>%</td></tr></tbody>');
    await page.route('**/history', (route) => route.fulfill({ contentType: 'text/html', body: historyHtml }));
    for (const path of ['/dashboard', '/history']) {
        await page.goto(path);
        const wrap = page.locator('.history-table-wrap');
        await wrap.scrollIntoViewIfNeeded();
        await expect.soft(wrap).toHaveAttribute('tabindex', '0');
        await expect.soft(wrap).toHaveAttribute('role', 'region');
        for (const cell of await page.locator('.history-table td').all()) {
            expect.soft(await cell.evaluate((el) => parseFloat(getComputedStyle(el).fontSize))).toBeGreaterThanOrEqual(12);
        }
        const dimensions = await wrap.evaluate((el) => ({ width: el.clientWidth, scroll: el.scrollWidth }));
        expect.soft(dimensions.scroll).toBeGreaterThan(dimensions.width);
        await wrap.evaluate((el) => { el.scrollLeft = el.scrollWidth; });
        expect.soft(await wrap.evaluate((el) => el.scrollLeft)).toBeGreaterThan(0);
        expect.soft(await page.evaluate(() => document.documentElement.scrollWidth)).toBeLessThanOrEqual(320);
        await page.screenshot({ path: testInfo.outputPath(`${path.slice(1)}-populated.png`), fullPage: true });
    }
});

test('safe area melindungi konten dan drawer tanpa menonaktifkan zoom', async ({ page, renderPage }) => {
    await page.setViewportSize({ width: 844, height: 390 });
    await routeViews(page, renderPage);
    for (const path of ['/login', '/register', '/dashboard']) {
        await page.goto(path);
        const viewport = await page.locator('meta[name="viewport"]').getAttribute('content');
        expect.soft(viewport).toContain('viewport-fit=cover');
        expect.soft(viewport).not.toMatch(/user-scalable=no|maximum-scale=1/);
        // Inject non-zero insets: desktop engines have no physical notch/home indicator.
        await page.evaluate(() => {
            for (const [edge, size] of Object.entries({ top: 24, right: 44, bottom: 34, left: 44 })) {
                document.documentElement.style.setProperty(`--safe-area-${edge}`, `${size}px`);
            }
        });
        const selectors = path === '/dashboard' ? ['.dashboard-header-inner', '.dashboard-main'] : ['.form-panel'];
        for (const selector of selectors) {
            const padding = await page.locator(selector).evaluate((el) => {
                const css = getComputedStyle(el);
                return [parseFloat(css.paddingLeft), parseFloat(css.paddingRight)];
            });
            expect.soft(padding[0], `${path} left`).toBeGreaterThanOrEqual(44);
            expect.soft(padding[1], `${path} right`).toBeGreaterThanOrEqual(44);
        }
        if (path === '/dashboard') {
            await page.locator('[data-sidebar-trigger]').click();
            const account = await page.locator('.sidebar-account').boundingBox();
            expect.soft(account.y + account.height).toBeLessThanOrEqual(390 - 34);
        }
        expect.soft(await page.evaluate(() => document.documentElement.scrollWidth)).toBeLessThanOrEqual(844);
    }
});

for (const role of ['operator', 'viewer']) {
    test(`drawer ${role} tetap dapat digunakan saat rotasi dan layar pendek`, async ({ page, renderPage }, testInfo) => {
        await routeViews(page, renderPage, role);
        await page.setViewportSize({ width: 390, height: 844 });
        await page.goto('/dashboard');
        const opener = page.locator('[data-sidebar-trigger]');
        await opener.click();
        await page.setViewportSize({ width: 844, height: 320 });
        await expect(page.locator('[data-sidebar]')).toBeVisible();
        await page.getByRole('button', { name: 'Menu Node 2', exact: true }).click();
        const account = page.getByRole('link', { name: 'Setting Akun', exact: true });
        const box = await account.boundingBox();
        expect(box.y).toBeGreaterThanOrEqual(0);
        expect(box.y + box.height).toBeLessThanOrEqual(320);
        await page.screenshot({ path: testInfo.outputPath(`drawer-${role}-landscape.png`) });
        await page.keyboard.press('Escape');
        await expect(opener).toBeFocused();
        await opener.click();
        await page.setViewportSize({ width: 1280, height: 800 });
        await expect(page.locator('[data-workspace]')).not.toHaveAttribute('inert');
        await expect(page.locator('body')).toHaveAttribute('data-sidebar-open', 'false');
        await page.locator('[data-sidebar-collapse]').click();
        await expect(page.locator('body')).toHaveAttribute('data-sidebar-collapsed', 'true');
        await page.setViewportSize({ width: 375, height: 667 });
        await opener.click();
        await expect(account).toBeVisible();
        await expect(page.locator('[data-sidebar]')).toHaveAttribute('role', 'dialog');
        if (role === 'viewer') await expect(page.getByRole('link', { name: 'Setting ESP', exact: true })).toHaveCount(0);
        await page.keyboard.press('Escape');
    });
}

test('form tetap dapat digulir pada viewport keyboard dan pesan error panjang', async ({ page, renderPage }) => {
    await routeViews(page, renderPage);
    await page.setViewportSize({ width: 320, height: 280 });
    for (const path of ['/login', '/register', '/settings/account']) {
        await page.goto(path);
        await page.locator('input:not([type="hidden"])').first().evaluate((input) => {
            const error = document.createElement('p');
            error.className = 'field-error';
            error.textContent = 'Periksa kembali isian Anda. Alamat email atau kata sandi yang dimasukkan belum sesuai. '.repeat(3);
            input.closest('.field').append(error);
        });
        const button = page.locator('main button[type="submit"], .auth-form button[type="submit"]').last();
        await button.scrollIntoViewIfNeeded();
        await expect(button).toBeInViewport();
        expect(await page.evaluate(() => document.documentElement.scrollWidth)).toBeLessThanOrEqual(320);
        expect(await page.evaluate(() => getComputedStyle(document.body).overflowY)).not.toBe('hidden');
    }
});

for (const deviceName of ['iPhone SE', 'iPhone 13', 'Pixel 7', 'iPad Mini']) {
    test(`emulasi sentuh ${deviceName} mendukung form navigasi dan tema`, async ({ browser, browserName, renderPage }, testInfo) => {
        const { defaultBrowserType, ...device } = devices[deviceName];
        // Firefox does not expose isMobile emulation; viewport and touch remain tested.
        if (browserName === 'firefox') delete device.isMobile;
        const context = await browser.newContext({ ...device, baseURL: testInfo.project.use.baseURL });
        try {
            const page = await context.newPage();
            await routeViews(page, renderPage);
            await page.goto('/login');
            await page.locator('#email').tap();
            await page.locator('#email').fill('mobile-test@example.test');
            expect(await page.evaluate(() => window.visualViewport.scale)).toBeCloseTo(1, 1);
            await page.goto('/dashboard');
            await page.locator('[data-sidebar-trigger]').tap();
            await page.getByRole('button', { name: 'Menu Node 2', exact: true }).tap();
            await page.getByRole('link', { name: 'Ringkasan Node 2', exact: true }).tap();
            await expect(page).toHaveURL(/\/nodes\/2$/);
            await expect(page.locator('[data-sidebar]')).not.toBeVisible();
            await page.locator('[data-theme-toggle]').tap();
            const theme = await page.locator('html').getAttribute('data-theme');
            await page.reload();
            await expect(page.locator('html')).toHaveAttribute('data-theme', theme);
            await page.goto('/history');
            await page.getByRole('combobox', { name: 'Parameter', exact: true }).tap();
            await page.getByRole('option', { name: 'Kelembapan tanah', exact: true }).tap();
            await expect(page.locator('select[name="sensor"]')).toHaveValue('soil_moisture');
            expect(await page.evaluate(() => document.documentElement.scrollWidth)).toBeLessThanOrEqual(device.viewport.width);
            await page.screenshot({ path: testInfo.outputPath(`${deviceName.replaceAll(' ', '-')}.png`), fullPage: true });
        } finally {
            await context.close();
        }
    });
}

async function routeViews(page, renderPage, role = 'operator') {
    await page.route(/\/(dashboard|nodes\/[12]|history|settings\/(account|esp))(\?.*)?$/, (route) => {
        const url = new URL(route.request().url());
        return route.fulfill({ contentType: 'text/html', body: renderPage(url.pathname + url.search, role) });
    });
}

for (const path of ['/login', '/register', '/settings/account', '/history']) {
    test(`input mobile ${path} terbaca tanpa pemicu auto-zoom iOS`, async ({ page, renderPage }) => {
        await page.setViewportSize({ width: 375, height: 667 });
        await routeViews(page, renderPage);
        await page.goto(path);
        const controls = page.locator('input:not([type="hidden"]):not([type="checkbox"]), select:visible, [role="combobox"]');
        expect(await controls.count()).toBeGreaterThan(0);
        for (const control of await controls.all()) {
            const fontSize = await control.evaluate((element) => parseFloat(getComputedStyle(element).fontSize));
            expect.soft(fontSize, `${path}: ${await control.getAttribute('id')}`).toBeGreaterThanOrEqual(16);
            expect.soft((await control.boundingBox()).height).toBeGreaterThanOrEqual(44);
        }
    });
}
