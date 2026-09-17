import { test, expect } from './support/dashboard-fixture.js';

test('CSP blocks injected inline scripts and handlers while trusted theme still runs', async ({ page }) => {
    await page.addInitScript(() => localStorage.setItem('rebung-pintar-theme', 'dark'));
    await page.route('**/login', async (route) => {
        const response = await route.fetch();
        const body = (await response.text()).replace('</body>', '<script>window.untrustedInline = true</script><button id="untrusted-handler" onclick="window.untrustedHandler=true">Test</button></body>');
        await route.fulfill({ response, body });
    });
    await page.goto('/login');
    await expect(page.locator('html')).toHaveAttribute('data-theme', 'dark');
    await page.locator('#untrusted-handler').click();
    expect(await page.evaluate(() => window.untrustedInline)).toBeUndefined();
    expect(await page.evaluate(() => window.untrustedHandler)).toBeUndefined();
    await page.locator('[data-theme-toggle]').click();
    await expect(page.locator('html')).toHaveAttribute('data-theme', 'light');
});

for (const width of [390, 1440]) {
    test(`trusted monitoring and history controls work under response CSP at ${width}px`, async ({ page, renderResponse }) => {
        const violations = [];
        await page.exposeFunction('recordViolation', (directive) => violations.push(directive));
        await page.addInitScript(() => document.addEventListener('securitypolicyviolation', (event) => window.recordViolation(event.violatedDirective)));
        await page.setViewportSize({ width, height: 900 });
        await page.route(/\/(dashboard|nodes\/1|history)(?:\?.*)?$/, (route) => {
            const url = new URL(route.request().url());
            return route.fulfill(renderResponse(url.pathname + url.search));
        });
        for (const path of ['/dashboard', '/nodes/1']) {
            await page.goto(path);
            await page.getByRole('button', { name: '6 jam', exact: true }).click();
            await expect(page.locator('[data-select-range="6"]')).toHaveAttribute('aria-pressed', 'true');
            await page.getByRole('button', { name: 'Kelembapan tanah', exact: true }).click();
            await expect(page.locator('[data-active-sensor]')).toContainText('Kelembapan tanah');
            await page.locator('[data-theme-toggle]').click();
        }
        await page.goto('/history');
        await page.getByRole('combobox', { name: 'Node', exact: true }).click();
        await page.getByRole('option', { name: 'Node 2', exact: true }).click();
        await expect(page.locator('select[name="node"]')).toHaveValue('2');
        await page.getByRole('button', { name: 'Terapkan filter', exact: true }).click();
        await expect(page).toHaveURL(/node=2/);
        expect(violations).toEqual([]);
    });
}
