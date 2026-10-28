// Visual audit helper (not part of the test run): full-page screenshots + overflow/alt/tap-target report for key pages at desktop and mobile.
// Usage: npm run audit:visual  (requires a seeded database/browser.sqlite, e.g. after npm run test:browser)
import { chromium } from '@playwright/test';
import { spawn } from 'node:child_process';
import fs from 'node:fs';
import path from 'node:path';
import { fileURLToPath, pathToFileURL } from 'node:url';

const root = path.resolve(path.dirname(fileURLToPath(import.meta.url)), '..', '..');
const out = path.join(root, 'test-results', 'visual');
fs.mkdirSync(out, { recursive: true });

const { serverEnv, BASE_URL, PORT } = await import(pathToFileURL(path.join(root, 'playwright.config.js')).href);

const server = spawn('php', ['-S', `127.0.0.1:${PORT}`, path.join(root, 'vendor/laravel/framework/src/Illuminate/Foundation/resources/server.php')], {
    cwd: path.join(root, 'public'), env: serverEnv, stdio: 'ignore',
});
await new Promise((r) => setTimeout(r, 2500));

const browser = await chromium.launch();
const report = [];

async function audit(page, label) {
    const r = await page.evaluate(() => {
        const vw = document.documentElement.clientWidth;
        const wide = [];
        for (const el of document.querySelectorAll('body *')) {
            const b = el.getBoundingClientRect();
            if (b.width > 0 && (b.right > vw + 1 || b.left < -1)) {
                const cls = (el.className && typeof el.className === 'string') ? '.' + el.className.trim().split(/\s+/).slice(0, 2).join('.') : '';
                wide.push(`${el.tagName.toLowerCase()}${cls} [${Math.round(b.left)}..${Math.round(b.right)}]`);
                if (wide.length >= 6) break;
            }
        }
        const noAlt = [...document.querySelectorAll('img:not([alt])')].length;
        const tiny = [...document.querySelectorAll('a,button')].filter((e) => { const b = e.getBoundingClientRect(); return b.width > 0 && (b.height < 24 || b.width < 24); }).length;
        return { vw, sw: document.documentElement.scrollWidth, wide, noAlt, tiny, h1: document.querySelectorAll('h1').length, title: document.title };
    });
    report.push({ label, ...r });
}

async function run(name, viewport, pages, loginAs) {
    const ctx = await browser.newContext({ viewport, deviceScaleFactor: 1, locale: 'ar-SA' });
    const page = await ctx.newPage();
    if (loginAs) {
        await page.goto(`${BASE_URL}/login`);
        await page.fill('#email', loginAs.email); await page.fill('#password', loginAs.password);
        await page.click('button[type=submit]');
        await page.waitForURL(/dashboard/);
    }
    for (const p of pages) {
        const label = `${name} ${p}`;
        await page.goto(`${BASE_URL}${p}`, { waitUntil: 'networkidle' });
        await audit(page, label);
        const file = path.join(out, `${name}-${p.replace(/[^a-z0-9]+/gi, '_') || 'home'}.png`);
        await page.screenshot({ path: file, fullPage: true });
    }
    await ctx.close();
}

const member = { email: 'browser-member@example.invalid', password: 'BrowserPass!2026' };
const admin = { email: 'browser-admin@example.invalid', password: 'BrowserPass!2026' };
const publicPages = ['/', '/academy', '/academy/digital-marketing-foundations', '/articles', '/articles/digital-marketing-strategy-guide', '/tools', '/tools/campaign-brief-builder', '/resources', '/insights', '/search?q=تسويق', '/login', '/register'];
const appPages = ['/dashboard', '/app/billing', '/app/projects/browser-first-project', '/profile', '/tools/campaign-brief-builder'];
const adminPages = ['/admin', '/admin/content', '/admin/operations'];

await run('desktop', { width: 1366, height: 900 }, publicPages);
await run('mobile', { width: 390, height: 844 }, publicPages);
await run('desktop-app', { width: 1366, height: 900 }, appPages, member);
await run('mobile-app', { width: 390, height: 844 }, appPages, member);
await run('desktop-admin', { width: 1366, height: 900 }, adminPages, admin);
await run('mobile-admin', { width: 390, height: 844 }, adminPages, admin);

await browser.close();
server.kill();
fs.writeFileSync(path.join(out, 'report.json'), JSON.stringify(report, null, 2));
for (const r of report) {
    const flag = r.sw > r.vw + 1 ? 'OVERFLOW' : 'ok';
    console.log(`${flag.padEnd(8)} ${r.label.padEnd(60)} sw=${r.sw}/${r.vw} h1=${r.h1} noAlt=${r.noAlt} tiny=${r.tiny}${r.wide.length ? '\n         ' + r.wide.join(' | ') : ''}`);
}
