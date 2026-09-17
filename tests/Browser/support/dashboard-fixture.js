import { execFileSync } from 'node:child_process';
import { mkdtempSync, rmSync } from 'node:fs';
import { tmpdir } from 'node:os';
import { join } from 'node:path';
import { test as base, expect } from '@playwright/test';

export const test = base.extend({
    renderResponse: [async ({}, use, workerInfo) => {
        const directory = mkdtempSync(join(tmpdir(), 'rebung-dashboard-'));
        const pages = new Map();
        try {
            await use((path = '/dashboard', role = 'operator') => {
                const key = `${role}:${path}`;
                if (!pages.has(key)) {
                    pages.set(key, JSON.parse(execFileSync('php', ['tests/Browser/support/render-dashboard.php', workerInfo.project.use.baseURL, path, role], {
                        cwd: process.cwd(),
                        encoding: 'utf8',
                        env: { ...process.env, APP_ENV: 'testing', VIEW_COMPILED_PATH: directory },
                    })));
                }
                return pages.get(key);
            });
        } finally {
            rmSync(directory, { recursive: true, force: true });
        }
    }, { scope: 'worker' }],
    renderPage: [async ({ renderResponse }, use) => {
        await use((path = '/dashboard', role = 'operator') => renderResponse(path, role).body);
    }, { scope: 'worker' }],
    dashboardHtml: [async ({ renderPage }, use) => {
        await use(renderPage('/dashboard'));
    }, { scope: 'worker' }],
});

export { expect };

export async function openDashboard(page, html) {
    await page.route(/\/dashboard(?:\?.*)?$/, (route) => route.fulfill({ contentType: 'text/html', body: html }));
    await page.goto('/dashboard');
}

export function withMonitoringData(html, data) {
    const attribute = JSON.stringify(data).replaceAll('&', '&amp;').replaceAll('"', '&quot;');
    return html.replace(/data-monitoring="[^"]*"/, `data-monitoring="${attribute}"`);
}
