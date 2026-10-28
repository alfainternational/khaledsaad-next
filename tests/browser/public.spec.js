// @ts-check
import { test, expect } from '@playwright/test';
import { expectNoHorizontalOverflow, trackErrors } from './helpers.js';

const PUBLIC_PAGES = ['/', '/academy', '/academy/marketing-foundations', '/academy/marketing-foundations/what-marketing-really-is', '/assess', '/articles', '/insights', '/tools', '/resources', '/about', '/search?q=تسويق', '/login', '/register'];

test.describe('الصفحات العامة', () => {
    test('الرئيسية تعرض الهوية والتنقل الرئيسي بلا أخطاء', async ({ page }) => {
        const errors = trackErrors(page);
        await page.goto('/');

        await expect(page).toHaveTitle(/أثر/);
        await expect(page.locator('html')).toHaveAttribute('dir', 'rtl');
        await expect(page.locator('h1').first()).toBeVisible();
        await expect(page.locator('#main')).toBeVisible();
        await expect(page.locator('link[rel="canonical"]')).toHaveCount(1);
        await expect(page.locator('a.skip-link')).toHaveAttribute('href', '#main');

        expect(errors).toEqual([]);
    });

    test('قائمة التنقل تعمل على الجوال والمكتب', async ({ page, isMobile }) => {
        await page.goto('/');
        const toggle = page.locator('.nav-toggle');
        const nav = page.locator('#main-nav');

        if (isMobile) {
            await expect(toggle).toBeVisible();
            await expect(nav).toBeHidden();
            await toggle.click();
            await expect(toggle).toHaveAttribute('aria-expanded', 'true');
            await expect(nav).toBeVisible();
            await expect(nav.getByRole('link', { name: 'المختبر' })).toBeVisible();
        } else {
            await expect(toggle).toBeHidden();
            await expect(nav).toBeVisible();
        }
    });

    for (const path of PUBLIC_PAGES) {
        test(`لا تجاوز أفقي في ${path}`, async ({ page }) => {
            const errors = trackErrors(page);
            const response = await page.goto(path);
            expect(response?.status(), `${path} status`).toBe(200);
            await expectNoHorizontalOverflow(page, path);
            expect(errors).toEqual([]);
        });
    }

    test('الأكاديمية تفتح دورة وتعرض دروسها', async ({ page }) => {
        await page.goto('/academy');
        const firstCourse = page.locator('a[href*="/academy/"]').first();
        await expect(firstCourse).toBeVisible();
        await firstCourse.click();

        await expect(page).toHaveURL(/\/academy\/[a-z0-9-]+$/);
        await expect(page.locator('h1').first()).toBeVisible();
        await expect(page.locator('script[type="application/ld+json"]')).toHaveCount(2);
        await expectNoHorizontalOverflow(page);
    });

    test('المقالات تفتح مقالاً ببيانات منظمة', async ({ page }) => {
        await page.goto('/articles');
        const firstArticle = page.locator('a[href*="/articles/"]').first();
        await expect(firstArticle).toBeVisible();
        await firstArticle.click();

        await expect(page).toHaveURL(/\/articles\/[a-z0-9-]+$/);
        await expect(page.locator('h1').first()).toBeVisible();
        const jsonLd = await page.locator('script[type="application/ld+json"]').first().textContent();
        expect(JSON.parse(jsonLd ?? '{}')['@type']).toMatch(/Article/);
        await expectNoHorizontalOverflow(page);
    });

    test('الأداة تعرض بوابة التسجيل للزائر', async ({ page }) => {
        await page.goto('/tools');
        const firstTool = page.locator('a[href*="/tools/"]').first();
        await expect(firstTool).toBeVisible();
        await firstTool.click();

        await expect(page).toHaveURL(/\/tools\/[a-z0-9-]+$/);
        await expect(page.locator('.login-gate')).toBeVisible();
        await expect(page.locator('.login-gate a.button')).toHaveAttribute('href', /\/register$/);
        await expectNoHorizontalOverflow(page);
    });

    test('البحث يعيد نتائج لكلمة شائعة', async ({ page }) => {
        await page.goto('/search?q=تسويق');
        await expect(page.locator('#main')).toContainText(/تسويق/);
    });

    test('نقاط SEO تستجيب بالأنواع الصحيحة', async ({ request }) => {
        const sitemap = await request.get('/sitemap.xml');
        expect(sitemap.status()).toBe(200);
        expect(sitemap.headers()['content-type']).toContain('xml');
        expect(await sitemap.text()).toContain('<urlset');

        const feed = await request.get('/feed.xml');
        expect(feed.status()).toBe(200);
        expect(feed.headers()['content-type']).toContain('xml');
        expect(await feed.text()).toContain('<rss');

        const llms = await request.get('/llms.txt');
        expect(llms.status()).toBe(200);
        expect(llms.headers()['content-type']).toContain('text/plain');
        expect(await llms.text()).toContain('أثر');
    });

    test('ترويسات الأمان مفعّلة', async ({ request }) => {
        const response = await request.get('/');
        const headers = response.headers();
        expect(headers['content-security-policy']).toContain("default-src 'self'");
        expect(headers['x-content-type-options']).toBe('nosniff');
        expect(headers['x-frame-options']).toBe('SAMEORIGIN');
        expect(headers['referrer-policy']).toBe('strict-origin-when-cross-origin');
    });
});
