import { test, expect } from './support/dashboard-fixture.js';

for (const width of [320, 1440]) {
    test(`riwayat ${width}px tanpa dropdown zona waktu`, async ({ page, renderPage }) => {
        await page.setViewportSize({ width, height: 900 });
        await page.route('**/history*', (route) => {
            const url = new URL(route.request().url());
            return route.fulfill({ contentType: 'text/html', body: renderPage(url.pathname + url.search) });
        });
        await page.goto('/history');
        await expect(page.getByRole('combobox', { name: 'Zona waktu', exact: true })).toHaveCount(0);
        await expect(page.locator('select[name="timezone"]')).toHaveCount(0);
        await expect(page.getByRole('columnheader', { name: 'Waktu (WIB)', exact: true })).toBeVisible();
        expect(await page.evaluate(() => document.documentElement.scrollWidth)).toBeLessThanOrEqual(width);
    });
}

for (const width of [320, 1440]) {
    test(`form ganti sandi ${width}px terpisah dan label input unik`, async ({ page, renderPage }) => {
        await page.setViewportSize({ width, height: 900 });
        await page.route('**/settings/account', route => route.fulfill({ contentType: 'text/html', body: renderPage('/settings/account') }));
        await page.goto('/settings/account');
        const form = page.locator('[data-password-form]');
        await expect(form).toBeVisible();
        await expect(form).toHaveAttribute('action', /\/settings\/password$/);
        await expect(form.locator('input[name="_method"]')).toHaveValue('PATCH');
        await expect(form.getByLabel('Kata sandi baru', { exact: true })).toHaveAttribute('type', 'password');
        await expect(form.getByLabel('Konfirmasi kata sandi baru', { exact: true })).toHaveAttribute('type', 'password');
        const ids = await page.locator('input[id]').evaluateAll(elements => elements.map(el => el.id));
        expect(new Set(ids).size).toBe(ids.length);
        await form.getByRole('button', { name: 'Tampilkan kata sandi baru', exact: true }).click();
        await expect(form.getByLabel('Kata sandi baru', { exact: true })).toHaveAttribute('type', 'text');
        expect(await page.evaluate(() => document.documentElement.scrollWidth)).toBeLessThanOrEqual(width);
    });
}
