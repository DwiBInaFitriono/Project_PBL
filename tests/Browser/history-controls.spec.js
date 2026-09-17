import { test, expect } from './support/dashboard-fixture.js';

async function openHistory(page, renderPage, query = '') {
    await page.route('**/history*', (route) => {
        const url = new URL(route.request().url());
        return route.fulfill({ contentType: 'text/html', body: renderPage(url.pathname + url.search) });
    });
    await page.goto(`/history${query}`);
}

for (const theme of ['light', 'dark']) {
    test(`dropdown ${theme} memiliki panel opsi kustom dan mempertahankan nilai filter`, async ({ page, renderPage }) => {
        await page.emulateMedia({ colorScheme: theme });
        await openHistory(page, renderPage);
        const node = page.getByRole('combobox', { name: 'Node', exact: true });
        await expect(node).toHaveAttribute('aria-expanded', 'false');
        await node.click();
        const list = page.getByRole('listbox', { name: 'Node', exact: true });
        await expect(list).toBeVisible();
        await expect(list).toHaveCSS('border-radius', '10px');
        await expect(list).toHaveCSS('background-color', theme === 'dark' ? 'rgb(17, 31, 64)' : 'rgb(250, 249, 247)');
        await list.getByRole('option', { name: 'Node 2', exact: true }).click();
        await expect(node).toContainText('Node 2');
        await expect(list).toBeHidden();
        await expect(page.locator('select[name="node"]')).toHaveValue('2');
        await page.getByRole('combobox', { name: 'Parameter', exact: true }).click();
        await page.getByRole('option', { name: 'Kelembapan tanah', exact: true }).click();
        await page.getByRole('button', { name: 'Terapkan filter', exact: true }).click();
        await expect(page).toHaveURL(/node=2/);
        await expect(page).toHaveURL(/sensor=soil_moisture/);
        await expect(page.getByRole('combobox', { name: 'Parameter', exact: true })).toContainText('Kelembapan tanah');
        await page.reload();
        await expect(page.getByRole('combobox', { name: 'Node', exact: true })).toContainText('Node 2');
    });
}

test('dropdown mendukung keyboard Escape Tab dan klik di luar', async ({ page, renderPage }) => {
    await openHistory(page, renderPage);
    const node = page.getByRole('combobox', { name: 'Node', exact: true });
    await node.focus();
    await page.keyboard.press('ArrowDown');
    await page.keyboard.press('End');
    await page.keyboard.press('Enter');
    await expect(node).toContainText('Node 2');
    await expect(node).toBeFocused();
    await page.keyboard.press('Space');
    await page.keyboard.press('Home');
    await page.keyboard.press('Escape');
    await expect(node).toContainText('Node 2');
    await expect(node).toHaveAttribute('aria-expanded', 'false');
    await node.click();
    await page.keyboard.press('Tab');
    await expect(node).toHaveAttribute('aria-expanded', 'false');
    await expect(page.getByRole('combobox', { name: 'Parameter', exact: true })).toBeFocused();
    await node.click();
    await page.getByRole('heading', { name: 'Riwayat pembacaan', exact: true }).click();
    await expect(node).toHaveAttribute('aria-expanded', 'false');
});

for (const width of [320, 768, 1440]) {
    test(`filter ${width}px tombol sejajar simetris dan tidak melebar`, async ({ page, renderPage }) => {
        await page.setViewportSize({ width, height: 900 });
        await openHistory(page, renderPage);
        const apply = await page.getByRole('button', { name: 'Terapkan filter', exact: true }).boundingBox();
        const reset = await page.getByRole('link', { name: 'Reset filter', exact: true }).boundingBox();
        expect(apply.height).toBe(44);
        expect(reset.height).toBe(44);
        expect(Math.abs(apply.width - reset.width)).toBeLessThanOrEqual(1);
        expect(Math.abs(apply.y - reset.y)).toBeLessThanOrEqual(1);
        expect(reset.x - (apply.x + apply.width)).toBeGreaterThanOrEqual(16);
        if (width === 1440) {
            const date = await page.locator('input[name="to"]').boundingBox();
            expect(Math.abs(date.y - apply.y)).toBeLessThanOrEqual(1);
            expect(apply.width).toBeLessThanOrEqual(130);
        }
        await page.getByRole('combobox', { name: 'Parameter', exact: true }).click();
        const panel = await page.getByRole('listbox', { name: 'Parameter', exact: true }).boundingBox();
        expect(panel.x).toBeGreaterThanOrEqual(0);
        expect(panel.x + panel.width).toBeLessThanOrEqual(width);
        expect(await page.evaluate(() => document.documentElement.scrollWidth)).toBeLessThanOrEqual(width);
    });
}

test('dropdown tetap bekerja tanpa JavaScript utama', async ({ page, renderPage }) => {
    await page.route('**/build/assets/*.js', (route) => route.abort());
    await openHistory(page, renderPage);
    const native = page.locator('select[name="node"]');
    await expect(native).toBeVisible();
    await native.selectOption('2');
    await page.getByRole('button', { name: 'Terapkan filter', exact: true }).click();
    await expect(page).toHaveURL(/node=2/);
});

test('pencarian ketik dan reset mengembalikan pilihan awal', async ({ page, renderPage }) => {
    await openHistory(page, renderPage);
    const sensor = page.getByRole('combobox', { name: 'Parameter', exact: true });
    await sensor.focus();
    await page.keyboard.type('Suhu');
    await page.keyboard.press('Enter');
    await expect(sensor).toContainText('Suhu udara');
    await page.getByRole('button', { name: 'Terapkan filter', exact: true }).click();
    await page.getByRole('link', { name: 'Reset filter', exact: true }).click();
    await expect(page.getByRole('combobox', { name: 'Parameter', exact: true })).toContainText('Semua parameter');
    await expect(page.locator('select[name="sensor"]')).toHaveValue('');
});

test('muat ulang berupa ikon persegi dan masih mempertahankan filter', async ({ page, renderPage }) => {
    await page.route(/\/(dashboard|nodes\/[12])(\?.*)?$/, (route) => {
        const url = new URL(route.request().url());
        return route.fulfill({ contentType: 'text/html', body: renderPage(url.pathname + url.search) });
    });
    for (const path of ['/dashboard', '/nodes/2']) {
        await page.goto(`${path}?sensor=soil_moisture&hours=6`);
        const reload = page.getByRole('link', { name: 'Muat ulang', exact: true });
        expect((await reload.innerText()).trim()).toBe('');
        await expect(reload).toHaveAttribute('title', 'Muat ulang');
        await expect(reload.locator('svg')).toBeVisible();
        const box = await reload.boundingBox();
        expect(box.width).toBe(44);
        expect(box.height).toBe(44);
        await reload.click();
        await expect(page).toHaveURL(/sensor=soil_moisture&hours=6$/);
    }
});
