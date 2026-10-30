// @ts-check
import { test, expect } from '@playwright/test';
import { ADMIN, certificateMemberFor, expectNoHorizontalOverflow, login, trackErrors } from './helpers.js';

/**
 * المرحلة 4 — الاكتمال (المخطط v1.3 §8): زائر يرى 12 مساراً ويقرأ درساً كاملاً من مسار جديد، يبدّل الوضع الداكن،
 * يحمّل قالباً، يرسل نموذج الاتصال، يزور عن/التسعير، ويفتح 404 وشهادة غير صالحة؛ عضو يكمل مساراً ويحصل على شهادة
 * يتحقق منها زائر علناً؛ مدير يفتح الشاشات القياسية الجديدة. تعمل على المكتب والجوال.
 */
const NEW_LESSON = '/academy/seo-content-marketing/seo-query-intent-map';

test.describe('المرحلة الرابعة: الاكتمال', () => {
    test('زائر: 12 مساراً، درس كامل من مسار جديد، الوضع الداكن، قالب، اتصال، عن/التسعير، 404 وشهادة غير صالحة', async ({ page }) => {
        test.setTimeout(180_000);
        const errors = trackErrors(page);

        // الأكاديمية: البرنامجان و12 مساراً كلها تفتح.
        await page.goto('/academy');
        await expect(page.locator('#courses-grid .card')).toHaveCount(12);
        await expect(page.locator('#courses-grid .is-soon')).toHaveCount(0);
        await expect(page.locator('#program-owner, #program-marketer')).toHaveCount(2);
        await expectNoHorizontalOverflow(page, '/academy');

        // درس من مسار جديد بالشكل الكامل: القرار الافتتاحي + الأجزاء الثمانية + القالب + الأداة.
        const response = await page.goto(NEW_LESSON);
        expect(response?.status()).toBe(200);
        await expect(page.locator('.part')).toHaveCount(9);
        for (const part of ['opening', 'goal', 'concept', 'situation', 'guided', 'lab', 'questions', 'template', 'apply']) {
            await expect(page.locator(`#part-${part}`), part).toBeAttached();
        }
        await expect(page.locator('#part-opening .dq-option')).toHaveCount(3);
        await expect(page.locator('#part-questions .dq')).toHaveCount(3);
        await expect(page.locator('#part-apply')).toContainText('الأداة');
        await expect(page.locator('#part-apply a[href*="/tools/"]').first()).toBeVisible();
        await expect(page.locator('#part-template a[href*="/resources/"]').first()).toBeAttached();
        await expectNoHorizontalOverflow(page, NEW_LESSON);

        // الوضع الداكن من زر الرأس، محفوظ عبر الصفحات.
        await page.emulateMedia({ colorScheme: 'light' });
        await expect(page.locator('html')).toHaveAttribute('data-theme', 'light');
        await page.locator('.theme-toggle').click();
        await expect(page.locator('html')).toHaveAttribute('data-theme', 'dark');
        await expect(page.locator('.theme-toggle')).toHaveAttribute('aria-pressed', 'true');
        const bodyBg = await page.evaluate(() => getComputedStyle(document.body).backgroundColor);
        expect(bodyBg).not.toBe('rgb(255, 253, 248)');
        await page.goto('/resources');
        await expect(page.locator('html')).toHaveAttribute('data-theme', 'dark');
        await expectNoHorizontalOverflow(page, '/resources (dark)');

        // تنزيل قالب من المكتبة (التنزيل للزائر).
        await expect(page.locator('.content-card').first()).toBeVisible();
        const downloadEvent = page.waitForEvent('download');
        await page.locator('.content-card a', { hasText: 'تنزيل' }).first().click();
        expect((await downloadEvent).suggestedFilename()).toMatch(/\.md$/);

        // عن أثر والتسعير.
        await page.goto('/about');
        await expect(page.locator('h1')).toBeVisible();
        await expect(page.locator('#main')).toContainText('الذكاء الاصطناعي');
        await expectNoHorizontalOverflow(page, '/about');
        await page.goto('/pricing');
        await expect(page.locator('h1')).toContainText('ابدأ مجانًا');
        await expect(page.locator('#main')).toContainText('شهادة إكمال المسار');
        await expect(page.locator('a[href$="/app/billing"]').first()).toBeAttached();
        await expectNoHorizontalOverflow(page, '/pricing');

        // نموذج الاتصال.
        await page.goto('/contact');
        await page.locator('#contact-name').fill('زائرة المرحلة الرابعة');
        await page.locator('#contact-email').fill('stage4-visitor@example.invalid');
        await page.locator('#contact-message').fill('رسالة اختبار محلية من مواصفة المرحلة الرابعة للتحقق من التسجيل.');
        await page.getByRole('button', { name: 'إرسال الرسالة' }).click();
        await expect(page.locator('.alert-success')).toContainText('استلمنا رسالتك');

        // 404 بالبحث وثلاثة روابط، وشهادة غير صالحة.
        const missing = await page.goto('/this-page-does-not-exist');
        expect(missing?.status()).toBe(404);
        await expect(page.locator('h1')).toContainText('الرابط لا يقابل صفحة');
        await expect(page.locator('form[action="/search"] input[name=q]')).toBeVisible();
        await expect(page.locator('nav a[href="/academy"], nav a[href="/tools"], nav a[href="/articles"]')).toHaveCount(3);
        await expect(page.locator('html')).toHaveAttribute('data-theme', 'dark');
        await expectNoHorizontalOverflow(page, '404');
        const invalid = await page.goto('/certificates/not-a-real-code');
        expect(invalid?.status()).toBe(404);
        await expect(page.locator('#main')).toContainText('لم نعثر على شهادة');
        // The two deliberate 404 navigations are logged by the browser as resource errors; nothing else may be.
        expect(errors.filter(e => !e.includes('status of 404'))).toEqual([]);
    });

    test('عضو احترافي يكمل المسار بنتيجة واقعية فتصدر شهادة يتحقق منها زائر علناً ويطبعها', async ({ page, browser }, testInfo) => {
        test.setTimeout(240_000);
        const errors = trackErrors(page);
        await login(page, certificateMemberFor(testInfo));

        // الدرس الأول من «الجمهور والشخصيات»: الدروس الخمسة الأخرى مكتملة مسبقاً في بذر المتصفح.
        await page.goto('/academy/audience-and-personas/audience-research-question');
        const guided = page.locator('#part-guided input:not([type=hidden]), #part-guided textarea');
        for (let i = 0; i < await guided.count(); i++) {
            await guided.nth(i).fill((await guided.nth(i).getAttribute('placeholder')) || 'خطة بحث محددة لهذا المشروع');
        }
        await page.locator('#lab-form button[type=submit]').first().click();
        await expect(page.locator('#asset')).toContainText('موجز بحث الجمهور');
        await page.locator('#asset [data-action="save"]').click();
        const questions = page.locator('#part-questions .dq');
        await expect(questions).toHaveCount(3);
        for (let i = 0; i < 3; i++) {
            const event = page.waitForResponse(r => r.url().endsWith('/events') && r.request().method() === 'POST');
            await questions.nth(i).locator('[data-best="1"]').click();
            await event;
        }
        await page.reload();
        await expect(page.locator('#lesson')).toContainText('مكتمل ✓');

        // الالتزام الواقعي ثم تسجيل النتيجة (الصفر مقبول، الفراغ مرفوض).
        await page.locator('#commit-form button[type=submit]').click();
        await expect(page.locator('#result-form')).toBeVisible();
        await page.locator('#result-form button[type=submit]').click();
        await expect(page.locator('.alert-error')).toContainText('سجّل قيمة فعلية واحدة على الأقل');
        await page.locator('#result-form input[type=number]').first().fill('7');
        const textFields = page.locator('#result-form input[type=text]:not(#r-note)');
        for (let i = 0; i < await textFields.count(); i++) {
            await textFields.nth(i).fill('مقابلات أسبوع 1 — 2026/10/30');
        }
        await page.locator('#result-form button[type=submit]').click();
        await expect(page.locator('.alert-success')).toContainText('سُجّلت نتيجتك الواقعية');

        // الشهادة في «تقدمي» مع رابط التحقق العام.
        await page.goto('/app/progress');
        await expect(page.locator('#certificates')).toContainText('الجمهور والشخصيات');
        await expect(page.locator('#certificates')).not.toContainText('شهادة مستحقة');
        const verifyLink = page.locator('#certificates a', { hasText: 'عرض الشهادة والتحقق' }).first();
        const href = await verifyLink.getAttribute('href');
        expect(href).toMatch(/\/certificates\/[0-9a-f-]{36}$/);
        await verifyLink.click();
        await expect(page.locator('#main')).toContainText('شهادة صالحة');
        await expect(page.locator('#main')).toContainText('متعلمة الشهادة');
        await expect(page.locator('#main')).toContainText('الجمهور والشخصيات');
        await expect(page.getByRole('button', { name: /طباعة/ })).toBeVisible();
        // Script elements have no rendered text, so read the JSON-LD source directly.
        const schemas = await page.$$eval('script[type="application/ld+json"]', nodes => nodes.map(n => n.textContent || ''));
        expect(schemas.some(s => s.includes('EducationalOccupationalCredential'))).toBe(true);
        await expectNoHorizontalOverflow(page, 'certificate');

        // زائر (سياق جديد بلا جلسة) يتحقق من الرابط نفسه.
        const visitor = await browser.newContext();
        const visitorPage = await visitor.newPage();
        const verification = await visitorPage.goto(String(href));
        expect(verification?.status()).toBe(200);
        await expect(visitorPage.locator('#main')).toContainText('شهادة صالحة');
        await expect(visitorPage.locator('#main')).not.toContainText('@example.invalid');
        await visitor.close();
        expect(errors).toEqual([]);
    });

    test('مدير يفتح الشاشات القياسية الجديدة بالوضع الداكن', async ({ page }) => {
        test.setTimeout(120_000);
        const errors = trackErrors(page);
        await page.emulateMedia({ colorScheme: 'dark' });
        await login(page, ADMIN);
        for (const path of ['/admin/users', '/admin/plans', '/admin/redirects', '/admin/audit', '/admin/contact-messages', '/admin/operations']) {
            const response = await page.goto(path);
            expect(response?.status(), path).toBe(200);
            await expect(page.locator('h1')).toBeVisible();
            await expect(page.locator('.admin-nav a[aria-current="page"]')).toHaveCount(1);
            await expect(page.locator('html')).toHaveAttribute('data-theme', 'dark');
            await expectNoHorizontalOverflow(page, path);
        }
        await expect(page.locator('#main')).toContainText('التشغيل');
        await page.goto('/admin/contact-messages');
        await expect(page.locator('#main')).toContainText('زائرة المرحلة الرابعة');
        expect(errors).toEqual([]);
    });
});
