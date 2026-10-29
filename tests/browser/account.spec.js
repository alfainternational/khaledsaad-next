// @ts-check
import { test, expect } from '@playwright/test';
import { ADMIN, MEMBER, expectNoHorizontalOverflow, login, trackErrors } from './helpers.js';

test.describe('الحساب ومساحة العمل', () => {
    test('التسجيل ينشئ حساباً ويطلب تأكيد البريد', async ({ page }, testInfo) => {
        const errors = trackErrors(page);
        const email = `reg-${testInfo.project.name}-${Date.now()}@example.invalid`;

        await page.goto('/register');
        await page.locator('#name').fill('مستخدم جديد');
        await page.locator('#email').fill(email);
        await page.locator('#password').fill('BrowserPass!2026');
        await page.locator('#password_confirmation').fill('BrowserPass!2026');
        await page.getByRole('button', { name: /تسجيل|إنشاء|register/i }).click();

        await expect(page).toHaveURL(/\/verify-email$/);
        await expect(page.locator('body')).toContainText(/تأكيد|تحقق|verify/i);
        await expectNoHorizontalOverflow(page);
        expect(errors).toEqual([]);
    });

    test('الدخول ثم تشغيل أداة وحفظ المخرج في المشروع', async ({ page }, testInfo) => {
        const errors = trackErrors(page);
        await login(page, MEMBER);
        await expectNoHorizontalOverflow(page, '/dashboard');

        await page.goto('/tools/campaign-brief-builder?fresh=1');
        const form = page.locator('#tool-form');
        await expect(form).toBeVisible();
        await expect(form.locator('#project_id option')).not.toHaveCount(0);
        // A distinct goal per Playwright project: both projects share MEMBER, and an identical asset would already be saved.
        await form.locator('#f-goal').fill(`${testInfo.project.name === 'mobile' ? 25 : 20} استشارة مؤهلة خلال 90 يوماً`);
        await form.locator('#f-audience').fill('أصحاب المشاريع الصغيرة في السعودية');
        await form.locator('#f-offer').fill('جلسة تشخيص تسويقي مجانية');
        await form.locator('#f-channel').selectOption('google');
        await form.locator('#f-budget').fill('2000');
        await page.locator('#generate-btn').evaluate((el) => el.scrollIntoView({ block: 'center' }));
        await page.locator('#generate-btn').click();

        await expect(page.locator('#asset')).toBeVisible();
        await expect(page.locator('#asset .asset-body')).not.toBeEmpty();
        await page.locator('#asset [data-action="save"]').evaluate((el) => el.scrollIntoView({ block: 'center' }));
        await page.locator('#asset [data-action="save"]').click();
        await expect(page.locator('.alert-success').first()).toContainText('حُفظ الأصل');
        await expectNoHorizontalOverflow(page);

        await page.goto('/dashboard');
        await page.locator('#projects a[href*="/app/projects/"]').first().click();
        await expect(page).toHaveURL(/\/app\/projects\/[a-z0-9-]+$/);
        await expect(page.locator('#project-assets')).toContainText('موجز الحملة');
        await expectNoHorizontalOverflow(page);
        expect(errors).toEqual([]);
    });

    test('الفوترة والملف الشخصي تفتحان بلا تجاوز', async ({ page }) => {
        const errors = trackErrors(page);
        await login(page, MEMBER);

        for (const path of ['/app/billing', '/profile']) {
            const response = await page.goto(path);
            expect(response?.status(), path).toBe(200);
            await expectNoHorizontalOverflow(page, path);
        }
        expect(errors).toEqual([]);
    });

    test('العضو العادي يُمنع من لوحة الإدارة', async ({ page }) => {
        await login(page, MEMBER);
        const response = await page.goto('/admin');
        expect(response?.status()).toBe(403);
    });

    test('المدير يفتح لوحة الإدارة والعمليات', async ({ page }) => {
        const errors = trackErrors(page);
        await login(page, ADMIN);

        for (const path of ['/admin', '/admin/academy', '/admin/tools', '/admin/assessment', '/admin/content', '/admin/operations']) {
            const response = await page.goto(path);
            expect(response?.status(), path).toBe(200);
            await expectNoHorizontalOverflow(page, path);
        }
        expect(errors).toEqual([]);
    });

    test('تسجيل الخروج من قائمة الحساب يعيد إلى الصفحة العامة', async ({ page }) => {
        const errors = trackErrors(page);
        await login(page, MEMBER);

        // Account menu in the new header (a <details> element; present on desktop and mobile).
        await page.locator('details.account-menu > summary').click();
        const logout = page.locator('form[action$="/logout"] button:visible').first();
        await expect(logout).toBeVisible();
        await logout.click();

        await expect(page).toHaveURL(/\/$/);
        // The session is gone: protected pages bounce back to the login form.
        await page.goto('/dashboard');
        await expect(page).toHaveURL(/\/login$/);
        expect(errors).toEqual([]);
    });
});
