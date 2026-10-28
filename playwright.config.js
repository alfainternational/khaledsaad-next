// @ts-check
import { defineConfig, devices } from '@playwright/test';
import path from 'node:path';
import { fileURLToPath } from 'node:url';

const root = path.dirname(fileURLToPath(import.meta.url));

export const PORT = 8321;
export const BASE_URL = `http://127.0.0.1:${PORT}`;

/**
 * Environment handed to artisan (global setup) and to the PHP built-in server.
 * A dedicated SQLite file keeps the browser suite away from the developer database.
 */
export const serverEnv = {
    ...process.env,
    APP_ENV: 'local',
    APP_DEBUG: 'true',
    APP_URL: BASE_URL,
    DB_CONNECTION: 'sqlite',
    DB_DATABASE: path.join(root, 'database', 'browser.sqlite'),
    SESSION_DRIVER: 'database',
    CACHE_STORE: 'database',
    QUEUE_CONNECTION: 'sync',
    MAIL_MAILER: 'log',
    BROADCAST_CONNECTION: 'log',
    // One learner is enough for a behaviour pattern to become a quality signal in the browser suite.
    PLATFORM_QUALITY_MIN_SAMPLE: '1',
};

export default defineConfig({
    testDir: './tests/browser',
    globalSetup: './tests/browser/global-setup.js',
    fullyParallel: false,
    workers: 1,
    retries: 0,
    timeout: 90_000,
    expect: { timeout: 10_000 },
    reporter: [['list'], ['html', { open: 'never', outputFolder: 'playwright-report' }]],
    outputDir: 'test-results',
    use: {
        baseURL: BASE_URL,
        locale: 'ar-SA',
        trace: 'retain-on-failure',
        screenshot: 'only-on-failure',
    },
    projects: [
        {
            name: 'desktop',
            use: { ...devices['Desktop Chrome'], viewport: { width: 1366, height: 900 } },
        },
        {
            name: 'mobile',
            use: { ...devices['Pixel 7'] },
        },
    ],
    webServer: {
        // Same invocation as `artisan serve`: the router resolves the document root from cwd.
        command: `php -S 127.0.0.1:${PORT} "${path.join(root, 'vendor/laravel/framework/src/Illuminate/Foundation/resources/server.php')}"`,
        cwd: path.join(root, 'public'),
        url: `${BASE_URL}/up`,
        reuseExistingServer: false,
        timeout: 60_000,
        env: serverEnv,
    },
});
