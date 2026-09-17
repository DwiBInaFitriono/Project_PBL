import { test, expect } from '@playwright/test';

test('halaman masuk menampilkan identitas dan formulir yang berlabel', async ({ page }) => {
    await page.goto('/');
    await expect(page).toHaveTitle('Masuk — Rebung Pintar');
    await expect(page.getByRole('heading', { name: 'Selamat datang kembali.' })).toBeVisible();
    await expect(page.getByLabel('Alamat email')).toBeVisible();
    await expect(page.getByLabel('Kata sandi', { exact: true })).toHaveAttribute('type', 'password');
    await expect(page.getByRole('link', { name: 'Buat akun', exact: true })).toBeVisible();
});

test('formulir autentikasi satu kolom di tengah tanpa panel samping', async ({ page }) => {
    await page.setViewportSize({ width: 1440, height: 900 });
    for (const route of ['/login', '/register']) {
        await page.goto(route);
        await expect(page.locator('aside')).toHaveCount(0);
        const form = await page.locator('.form-content').boundingBox();
        expect(Math.abs(form.x + form.width / 2 - 720)).toBeLessThanOrEqual(1);
        expect(form.width).toBeLessThanOrEqual(440);
        await expect(page.getByLabel('Alamat email')).toBeVisible();
    }
});

test('identitas aplikasi adalah monitoring rebung bambu melalui dua node', async ({ page }) => {
    for (const route of ['/login', '/register']) {
        await page.goto(route);
        await expect(page.locator('.form-content')).toContainText('rebung bambu');
        await expect(page.locator('.form-content')).toContainText('node 1 dan node 2');
        await expect(page.locator('body')).not.toContainText(/belajar|pembelajaran|rasa ingin tahu/i);
    }
});

test('login muat satu layar tanpa scroll dan tanpa memotong kontrol', async ({ page }) => {
    for (const viewport of [
        { width: 1366, height: 650 },
        { width: 1280, height: 600 },
        { width: 390, height: 844 },
        { width: 360, height: 640 },
        { width: 320, height: 568 },
    ]) {
        await page.setViewportSize(viewport);
        for (const theme of ['light', 'dark']) {
            await page.emulateMedia({ colorScheme: theme });
            await page.goto('/login');
            const dimensions = await page.evaluate(() => ({
                height: document.documentElement.scrollHeight,
                width: document.documentElement.scrollWidth,
                overflow: getComputedStyle(document.body).overflowY,
            }));
            expect(dimensions.height, `${viewport.width}x${viewport.height} ${theme}`).toBeLessThanOrEqual(viewport.height);
            expect(dimensions.width).toBeLessThanOrEqual(viewport.width);
            expect(dimensions.overflow).not.toBe('hidden');
            for (const selector of ['.page-header', 'h1', '#email', '#password', '.checkbox-label', '.primary-button', '.page-footer']) {
                const control = page.locator(selector);
                await expect(control).toBeVisible();
                const box = await control.boundingBox();
                expect(box.y).toBeGreaterThanOrEqual(0);
                expect(box.y + box.height).toBeLessThanOrEqual(viewport.height);
            }
            await page.mouse.wheel(0, 600);
            expect(await page.evaluate(() => window.scrollY)).toBe(0);
        }
    }
});

test('tombol tema hanya ikon bulan dan matahari tanpa teks', async ({ page }) => {
    await page.emulateMedia({ colorScheme: 'light' });
    for (const route of ['/login', '/register']) {
        await page.goto(route);
        const button = page.locator('[data-theme-toggle]');
        expect((await button.innerText()).trim()).toBe('');
        await expect(button).toHaveAccessibleName('Aktifkan mode gelap');
        await expect(button).toHaveAttribute('title', 'Aktifkan mode gelap');
        await expect(button.locator('[data-theme-icon="moon"]')).toBeVisible();
        await expect(button.locator('[data-theme-icon="sun"]')).toBeHidden();
        const box = await button.boundingBox();
        expect(box.width).toBeCloseTo(44, 4);
        expect(box.height).toBeCloseTo(44, 4);

        await button.focus();
        await page.keyboard.press('Space');
        await expect(page.locator('html')).toHaveAttribute('data-theme', 'dark');
        await expect(button).toHaveAccessibleName('Aktifkan mode terang');
        await expect(button).toHaveAttribute('title', 'Aktifkan mode terang');
        await expect(button.locator('[data-theme-icon="sun"]')).toBeVisible();
        await expect(button.locator('[data-theme-icon="moon"]')).toBeHidden();
        expect((await button.innerText()).trim()).toBe('');
        await button.click();
        await expect(page.locator('html')).toHaveAttribute('data-theme', 'light');
    }
});

test('tema gelap tersimpan saat pindah halaman dan reload', async ({ page }) => {
    await page.emulateMedia({ colorScheme: 'light' });
    await page.goto('/login');
    await expect(page.locator('html')).toHaveAttribute('data-theme', 'light');
    await page.getByRole('button', { name: 'Aktifkan mode gelap' }).click();
    await expect(page.locator('html')).toHaveAttribute('data-theme', 'dark');
    await expect(page.locator('body')).toHaveCSS('background-color', 'rgb(10, 21, 48)');
    await expect(page.getByRole('button', { name: 'Aktifkan mode terang' })).toHaveAttribute('aria-pressed', 'true');
    await page.getByRole('link', { name: 'Buat akun', exact: true }).click();
    await expect(page).toHaveTitle('Daftar — Rebung Pintar');
    await expect(page.locator('html')).toHaveAttribute('data-theme', 'dark');
    await page.reload();
    await expect(page.locator('html')).toHaveAttribute('data-theme', 'dark');
    await page.getByRole('button', { name: 'Aktifkan mode terang' }).click();
    await expect(page.locator('html')).toHaveAttribute('data-theme', 'light');
    expect(await page.evaluate(() => localStorage.getItem('rebung-pintar-theme'))).toBe('light');
});

test('kata sandi dapat ditampilkan dan disembunyikan tanpa mengirim form', async ({ page }) => {
    await page.goto('/login');
    const password = page.getByLabel('Kata sandi', { exact: true });
    await password.fill('contoh-password-uji');
    await page.getByRole('button', { name: 'Tampilkan kata sandi', exact: true }).click();
    await expect(password).toHaveAttribute('type', 'text');
    await expect(page.getByRole('button', { name: 'Sembunyikan kata sandi', exact: true })).toHaveAttribute('aria-pressed', 'true');
    await page.getByRole('button', { name: 'Sembunyikan kata sandi', exact: true }).click();
    await expect(password).toHaveAttribute('type', 'password');
    await expect(page).toHaveURL(/\/login$/);
});

test('preferensi gelap diterapkan sebelum JavaScript utama dimuat', async ({ page }) => {
    await page.addInitScript(() => localStorage.setItem('rebung-pintar-theme', 'dark'));
    await page.route('**/build/assets/*.js', (route) => route.abort());
    await page.goto('/login');
    await expect(page.locator('html')).toHaveAttribute('data-theme', 'dark');
});

test('tema mengikuti sistem sampai pengguna memilih sendiri', async ({ page }) => {
    await page.emulateMedia({ colorScheme: 'dark' });
    await page.goto('/login');
    await expect(page.locator('html')).toHaveAttribute('data-theme', 'dark');
    await page.emulateMedia({ colorScheme: 'light' });
    await expect(page.locator('html')).toHaveAttribute('data-theme', 'light');
    await page.getByRole('button', { name: 'Aktifkan mode gelap' }).click();
    await page.emulateMedia({ colorScheme: 'dark' });
    await page.emulateMedia({ colorScheme: 'light' });
    await expect(page.locator('html')).toHaveAttribute('data-theme', 'dark');
});

test('tema tetap berfungsi ketika penyimpanan browser diblokir', async ({ page }) => {
    await page.addInitScript(() => {
        Storage.prototype.getItem = () => { throw new DOMException('Blocked', 'SecurityError'); };
        Storage.prototype.setItem = () => { throw new DOMException('Blocked', 'SecurityError'); };
    });
    await page.emulateMedia({ colorScheme: 'light' });
    await page.goto('/login');
    await page.getByRole('button', { name: 'Aktifkan mode gelap' }).click();
    await expect(page.locator('html')).toHaveAttribute('data-theme', 'dark');
});

test('register menyediakan field lengkap dan toggle konfirmasi independen', async ({ page }) => {
    await page.goto('/register');
    await expect(page.getByLabel('Nama lengkap')).toBeVisible();
    await expect(page.getByLabel('Alamat email')).toBeVisible();
    const password = page.getByLabel('Kata sandi', { exact: true });
    const confirmation = page.getByLabel('Konfirmasi kata sandi', { exact: true });
    await expect(password).toHaveAttribute('minlength', '8');
    await expect(confirmation).toBeVisible();
    await page.getByRole('button', { name: 'Tampilkan konfirmasi kata sandi' }).click();
    await expect(confirmation).toHaveAttribute('type', 'text');
    await expect(password).toHaveAttribute('type', 'password');
    await page.getByRole('button', { name: 'Buat akun', exact: true }).click();
    await expect(page).toHaveURL(/\/register$/);
    expect(await page.locator('form').evaluate((form) => form.checkValidity())).toBe(false);
});

for (const width of [360, 768, 1440]) {
    for (const theme of ['light', 'dark']) {
        test(`layout ${width}px ${theme} tidak terpotong dan tanpa error browser`, async ({ page }) => {
            const errors = [];
            page.on('pageerror', (error) => errors.push(error.message));
            page.on('console', (message) => { if (message.type() === 'error') errors.push(message.text()); });
            await page.setViewportSize({ width, height: 900 });
            await page.emulateMedia({ colorScheme: theme, reducedMotion: 'reduce' });
            for (const route of ['/login', '/register']) {
                const response = await page.goto(route);
                expect(response.status()).toBe(200);
                await expect(page.locator('h1')).toBeVisible();
                await expect(page.locator('.primary-button')).toBeVisible();
                expect(await page.evaluate(() => document.documentElement.scrollWidth > window.innerWidth)).toBe(false);
                for (const selector of ['.form-content', '.primary-button', '.page-header', '.page-footer']) {
                    const box = await page.locator(selector).boundingBox();
                    expect(box.x).toBeGreaterThanOrEqual(0);
                    expect(box.x + box.width).toBeLessThanOrEqual(width + 1);
                }
            }
            expect(errors).toEqual([]);
        });
    }
}


