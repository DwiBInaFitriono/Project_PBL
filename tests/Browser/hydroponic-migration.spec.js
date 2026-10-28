import { test, expect } from './support/dashboard-fixture.js';

const forbiddenDomainTerms = /rebung|bambu|bamboo/i;

async function routeApplicationViews(page, renderPage) {
    await page.route(/\/(dashboard|nodes\/[12]|history|settings\/(account|esp))(\?.*)?$/, (route) => {
        const url = new URL(route.request().url());
        return route.fulfill({ contentType: 'text/html', body: renderPage(url.pathname + url.search) });
    });
}

test('seluruh antarmuka Blade memakai identitas Hidroponik DFT tanpa istilah lama', async ({ page, renderPage }) => {
    await routeApplicationViews(page, renderPage);

    for (const path of ['/login', '/register', '/dashboard', '/nodes/1', '/nodes/2', '/history', '/settings/esp']) {
        await page.goto(path);
        await expect(page.locator('body')).not.toContainText(forbiddenDomainTerms);
        await expect(page.locator('body')).toContainText(/hidroponik\s*DFT/i);
    }
});

test('node nutrisi dan iklim hanya menampilkan empat parameter DFT masing-masing', async ({ page, dashboardHtml }) => {
    await page.route(/\/dashboard(?:\?.*)?$/, (route) => route.fulfill({ contentType: 'text/html', body: dashboardHtml }));
    await page.goto('/dashboard');

    const nutrient = page.locator('[data-node-id="1"]');
    await expect(nutrient.getByRole('heading', { name: 'Node Nutrisi', exact: true })).toBeVisible();
    await expect(nutrient.locator('[data-sensor-reading]')).toHaveCount(3);
    await expect(nutrient).toContainText('pH nutrisi');
    await expect(nutrient).not.toContainText('TDS nutrisi');
    await expect(nutrient).toContainText('Suhu air');
    await expect(nutrient).toContainText('Level tandon');

    const climate = page.locator('[data-node-id="2"]');
    await expect(climate.getByRole('heading', { name: 'Node Iklim', exact: true })).toBeVisible();
    await expect(climate.locator('[data-sensor-reading]')).toHaveCount(4);
    await expect(climate).toContainText('Suhu udara');
    await expect(climate).toContainText('Kelembapan udara');
    await expect(climate).toContainText('Intensitas cahaya');
    await expect(climate).toContainText('Debit air');

    await expect(page.locator('[data-sensor-reading]')).toHaveCount(8);
    const sensorText = await page.locator('[data-sensor-reading]').allTextContents();
    expect(sensorText.join(' ')).not.toMatch(/kelembapan tanah/i);
});

test('navigasi dan kontrol parameter menggunakan nama node dan parameter DFT', async ({ page, dashboardHtml }) => {
    await page.route(/\/dashboard(?:\?.*)?$/, (route) => route.fulfill({ contentType: 'text/html', body: dashboardHtml }));
    await page.goto('/dashboard');

    const sidebar = page.locator('[data-sidebar]');
    await expect(sidebar.getByRole('button', { name: 'Menu Node Nutrisi', exact: true })).toBeVisible();
    await expect(sidebar.getByRole('button', { name: 'Menu Node Iklim', exact: true })).toBeVisible();
    await sidebar.getByRole('button', { name: 'Menu Node Nutrisi', exact: true }).click();
    await expect(sidebar.getByRole('link', { name: 'pH nutrisi', exact: true })).toBeVisible();
    await expect(sidebar.getByRole('link', { name: 'Level tandon', exact: true })).toBeVisible();
    await page.getByRole('button', { name: 'Debit air', exact: true }).click();
    await expect(page.locator('[data-active-sensor]')).toHaveText('Debit air · L/menit');
});
