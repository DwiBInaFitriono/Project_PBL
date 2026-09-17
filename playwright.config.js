import { defineConfig } from '@playwright/test';
import { existsSync } from 'node:fs';

const host = '127.0.0.1';
const port = 8013;
const baseURL = `http://${host}:${port}`;
const chrome = 'C:/Program Files/Google/Chrome/Application/chrome.exe';
const browserName = process.env.BROWSER_ENGINE || 'chromium';
const executablePath = browserName === 'chromium'
    ? process.env.BROWSER_EXECUTABLE || (existsSync(chrome) ? chrome : undefined)
    : undefined;

export default defineConfig({
    testDir: './tests/Browser',
    use: {
        baseURL,
        browserName,
        launchOptions: { executablePath },
        screenshot: 'only-on-failure',
    },
    webServer: {
        command: `php -S ${host}:${port} -t public tests/Browser/support/server.php`,
        url: `${baseURL}/login`,
        reuseExistingServer: false,
        env: { APP_ENV: 'testing' },
    },
});
