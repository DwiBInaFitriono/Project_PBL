// Explicit, local-only smoke test of the real Flutter web UI.
// Credentials must be supplied by the caller through environment variables.
import { chromium } from '@playwright/test';
import assert from 'node:assert/strict';
import path from 'node:path';
const email = process.env.REBUNG_SMOKE_EMAIL;
const password = process.env.REBUNG_SMOKE_PASSWORD;
if (!email || !password) throw new Error('Supply smoke-test credentials via environment.');
const browser = await chromium.launch({executablePath: 'C:/Program Files/Google/Chrome/Application/chrome.exe'});
let authenticated = false;
let logoutConfirmed = false;
const page = await browser.newPage({viewport: {width: 390, height: 844}});
const observed = [];
const errors = [];
page.on('pageerror', e => errors.push(e.name));
page.on('response', r => {
  const url = new URL(r.url());
  if (url.origin === 'http://127.0.0.1:8000' && url.pathname.startsWith('/api/v1/')) {
    observed.push({method:r.request().method(),path:url.pathname,status:r.status()});
  }
});
try {
  await page.goto('http://127.0.0.1:8091/');
  await page.locator('flt-semantics-placeholder').evaluate(e => e.click());
  await page.getByText('REST API v1', {exact:true}).waitFor();
  await page.locator('input[type=text]').fill(email);
  await page.locator('input[type=password]').fill(password);
  const login = page.waitForResponse(r => r.url().endsWith('/api/v1/auth/login') && r.request().method() === 'POST');
  await page.getByRole('button', {name:'Masuk',exact:true}).click();
  assert.equal((await login).status(), 200);
  authenticated = true;
  await page.getByRole('button', {name:'Keluar',exact:true}).waitFor();
  await page.waitForFunction(() => document.body.innerText.includes('REST ·') && !document.body.innerText.includes('Memuat data'));
  await page.screenshot({path:path.resolve('android-app/build/live-dashboard-390.png')});
  await page.getByRole('button', {name:'Buka navigasi',exact:true}).click();
  await page.getByRole('button', {name:'Setting Akun',exact:true}).click();
  await page.waitForFunction(() => document.body.innerText.includes('Profil hanya baca'));
  await page.screenshot({path:path.resolve('android-app/build/live-account-inspect.png')});
  await page.waitForFunction(expected => document.body.innerText.includes(expected) || [...document.querySelectorAll('[aria-label]')].some(e => e.getAttribute('aria-label')?.includes(expected)) || [...document.querySelectorAll('input,textarea')].some(e => e.value === expected), email);
  console.log('Account identity rendered from /api/v1/me.');
  assert((await page.locator('body').innerText()).includes('Operator'));
  await page.screenshot({path:path.resolve('android-app/build/live-account-390.png')});
  await page.getByRole('button', {name:'Buka navigasi',exact:true}).click();
  await page.getByRole('button', {name:'Riwayat data',exact:true}).click();
  await page.getByText('Terapkan filter',{exact:true}).waitFor();
  const history = page.waitForResponse(r => r.url().includes('/api/v1/history') && r.request().method() === 'GET');
  await page.getByRole('button',{name:'Terapkan filter',exact:true}).click();
  assert.equal((await history).status(),200);
  await page.screenshot({path:path.resolve('android-app/build/live-history-390.png')});
  await page.getByRole('button', {name:'Buka navigasi',exact:true}).click();
  await page.getByRole('button', {name:'Setting ESP',exact:true}).click();
  await page.getByText('Konfigurasi perangkat',{exact:true}).waitFor();
  await page.setViewportSize({width:1440,height:900});
  await page.getByRole('button',{name:'Dashboard',exact:true}).click();
  await page.screenshot({path:path.resolve('android-app/build/live-dashboard-1440.png')});
  for (const endpoint of ['/auth/login','/monitoring','/me','/history','/settings/esp']) {
    assert(observed.some(r => r.path === '/api/v1'+endpoint && r.status === 200), `Missing success for ${endpoint}`);
  }
  assert.deepEqual(errors, []);
} finally {
  if (authenticated) {
    try {
      const logout = page.waitForResponse(r => r.url().endsWith('/api/v1/auth/logout') && r.request().method() === 'POST', {timeout:15000});
      await page.getByRole('button',{name:'Keluar',exact:true}).click();
      assert.equal((await logout).status(),204);
      await page.getByRole('button',{name:'Masuk',exact:true}).waitFor();
      logoutConfirmed = true;
      console.log('Flutter UI logout verified.');
    } catch { console.log('UI logout not confirmed; inspect only smoke-test token for cleanup.'); }
  }
  console.log(JSON.stringify({responses:observed,pageErrors:errors}));
  await browser.close();
  if (authenticated && !logoutConfirmed) throw new Error('Logout cleanup was not confirmed.');
}
