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

    test('الدخول ثم تشغيل أداة وحفظ المخرج في المشروع', async ({ page }) => {
        const errors = trackErrors(page);
        await login(page, MEMBER);
        await expectNoHorizontalOverflow(page, '/dashboard');

        await page.goto('/tools');
        await page.locator('a[href*="/tools/"]').first().click();
        await expect(page).toHaveURL(/\/tools\/[a-z0-9-]+$/);

        const form = page.locator('form[action*="/app/tools/"]');
        await expect(form).toBeVisible();
        await expect(form.locator('#project_id option')).not.toHaveCount(0);
        await form.locator('#goal').fill('زيادة طلبات الاستشارة المؤهلة خلال 90 يوماً');
        await form.locator('#audience').fill('أصحاب المشاريع الصغيرة في السعودية');
        await form.locator('#offer').fill('جلسة تشخيص تسويقي مجانية');
        await form.getByRole('button', { name: /أنشئ/ }).click();

        await expect(page.locator('.tool-output')).toBeVisible();
        await expect(page.locator('.tool-output')).not.toBeEmpty();
        await expectNoHorizontalOverflow(page);

        await page.goto('/dashboard');
        await page.locator('a[href*="/app/projects/"]').first().click();
        await expect(page).toHaveURL(/\/app\/projects\/[a-z0-9-]+$/);
        await expect(page.locator('body')).toContainText(/المسودة|المخرج|مخرجات/);
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

        for (const path of ['/admin', '/admin/academy', '/admin/assessment', '/admin/content', '/admin/operations']) {
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
