// @ts-check
import { test, expect } from '@playwright/test';
import { login, memberFor, expectNoHorizontalOverflow, trackErrors } from './helpers.js';

test.describe('المرحلة الرابعة: الاكتمال', () => {
    test('المظهر يتبع النظام ويحفظ اختيار المتعلم عبر الصفحات', async ({ page }) => {
        const errors = trackErrors(page);
        await page.emulateMedia({ colorScheme: 'dark' });
        await page.goto('/pricing');
        await expect(page.locator('html')).toHaveAttribute('data-theme', 'dark');
        await expect(page.locator('.theme-toggle')).toHaveAttribute('aria-pressed', 'true');
        await page.emulateMedia({ colorScheme: 'light' });
        await expect(page.locator('html')).toHaveAttribute('data-theme', 'light');
        await page.locator('.theme-toggle').click();
        await expect(page.locator('html')).toHaveAttribute('data-theme', 'dark');
        await page.goto('/login');
        await expect(page.locator('html')).toHaveAttribute('data-theme', 'dark');
        await expect(page.locator('h1')).toContainText('دخول');
        await page.reload();
        await expect(page.locator('.theme-toggle')).toHaveAttribute('aria-pressed', 'true');
        expect(errors).toEqual([]);
    });

    for (const theme of ['light', 'dark']) {
        test(`الصفحات الجديدة والهوية في الوضع ${theme}`, async ({ page }, testInfo) => {
            test.setTimeout(120_000);
            const errors = trackErrors(page);
            await page.emulateMedia({ colorScheme: theme });
            for (const path of ['/resources', '/resources/value-card-template', '/about', '/pricing', '/contact', '/privacy', '/terms', '/login', '/register', '/forgot-password']) {
                const response = await page.goto(path);
                expect(response?.status(), path).toBe(200);
                await expect(page.locator('h1')).toBeVisible();
                await expect(page.locator('html')).toHaveAttribute('data-theme', theme);
                await expectNoHorizontalOverflow(page, path);
                if (['/pricing', '/login', '/resources/value-card-template'].includes(path)) {
                    await page.screenshot({ path: `test-results/stage4-${testInfo.project.name}-${theme}-${path.split('/').pop()}.png`, fullPage: true });
                }
            }
            expect(errors).toEqual([]);
        });
    }

    test('مكتبة القوالب: تصفية ومعاينة وتنزيل وحفظ', async ({ page }, testInfo) => {
        const errors = trackErrors(page);
        await page.goto('/resources');
        await page.locator('#resource-topic').selectOption('audience-and-personas');
        await page.locator('#resource-format').selectOption('csv');
        await page.getByRole('button', { name: 'عرض القوالب' }).click();
        await expect(page.locator('.content-card')).toHaveCount(6);
        await page.locator('.content-card h2 a').first().click();
        await expect(page.getByRole('heading', { name: 'معاينة القالب' })).toBeVisible();
        const downloadEvent = page.waitForEvent('download');
        await page.getByRole('link', { name: 'تنزيل جدول CSV' }).click();
        expect((await downloadEvent).suggestedFilename()).toMatch(/\.csv$/);
        await login(page, memberFor(testInfo));
        await page.goto('/resources/value-card-template');
        await page.getByRole('button', { name: 'احفظ في مشروعي', exact: true }).click();
        await expect(page).toHaveURL(/\/app\/outputs\//);
        await expect(page.locator('h1')).toContainText('القيمة');
        await expectNoHorizontalOverflow(page);
        expect(errors).toEqual([]);
    });

    test('نموذج التواصل يتحقق ويرسل ويعرض التأكيد', async ({ page }) => {
        const errors = trackErrors(page);
        await page.goto('/contact');
        await page.locator('#contact-name').fill('اختبار المتصفح');
        await page.locator('#contact-email').fill('browser-contact@example.invalid');
        await page.locator('#contact-message').fill('هذه رسالة اختبار محلية للتحقق من نموذج التواصل.');
        await page.getByRole('button', { name: 'إرسال الرسالة' }).click();
        await expect(page.locator('.alert-success')).toContainText('استلمنا رسالتك');
        expect(errors).toEqual([]);
    });

    test('درس جديد كامل يولد أصلاً ويكمل بأسئلة القرار', async ({ page }, testInfo) => {
        const errors = trackErrors(page);
        await login(page, memberFor(testInfo));
        await page.goto('/academy/audience-and-personas/audience-research-question');
        const guided = page.locator('#part-guided input:not([type=hidden]), #part-guided textarea');
        for (let i = 0; i < await guided.count(); i++) {
            await guided.nth(i).fill((await guided.nth(i).getAttribute('placeholder')) || 'خطة بحث محددة لهذا المشروع');
        }
        await page.locator('#lab-form button[type=submit]').first().click();
        await expect(page.locator('#asset')).toContainText('موجز بحث الجمهور');
        await expect(page.locator('#asset')).not.toContainText('{{');
        await page.locator('#asset [data-action="save"]').click();
        const questions = page.locator('#part-questions .dq');
        await expect(questions).toHaveCount(3);
        for (let i = 0; i < 3; i++) {
            await expect(questions.nth(i).locator('.dq-option')).toHaveCount(3);
            const event = page.waitForResponse(r => r.url().endsWith('/events') && r.request().method() === 'POST');
            await questions.nth(i).locator('[data-best="1"]').click();
            await event;
        }
        await page.reload();
        await expect(page.locator('#lesson')).toContainText('مكتمل ✓');
        await expectNoHorizontalOverflow(page);
        expect(errors).toEqual([]);
    });

    test('التحقق من الشهادة صالح وملغى ومفقود', async ({ page }) => {
        await page.goto('/certificates/11111111-1111-4111-8111-111111111111');
        await expect(page.locator('#main')).toContainText('شهادة صالحة');
        await expectNoHorizontalOverflow(page);
        await page.goto('/certificates/22222222-2222-4222-8222-222222222222');
        await expect(page.locator('#main')).toContainText('شهادة ملغاة');
        const missing = await page.goto('/certificates/33333333-3333-4333-8333-333333333333');
        expect(missing?.status()).toBe(404);
        await expect(page.locator('#main')).toContainText('لم نعثر على شهادة');
    });

    test('الحساب والفوترة يستعملان المظهر الداكن نفسه', async ({ page }, testInfo) => {
        const errors = trackErrors(page);
        await page.emulateMedia({ colorScheme: 'dark' });
        await login(page, memberFor(testInfo));
        for (const path of ['/profile', '/app/billing', '/app/progress']) {
            await page.goto(path);
            await expect(page.locator('html')).toHaveAttribute('data-theme', 'dark');
            await expectNoHorizontalOverflow(page, path);
        }
        expect(errors).toEqual([]);
    });
});
