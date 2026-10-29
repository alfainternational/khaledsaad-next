// @ts-check
import { test, expect } from '@playwright/test';
import { ADMIN, expectNoHorizontalOverflow, login, memberFor, trackErrors } from './helpers.js';

/**
 * المرحلة 1 — الرحلة الكاملة (المخطط v1.3 §8): الرئيسية (النموذج) → التشخيص بثلاث مراحل → التسجيل →
 * درس كامل (قرار، مختبر، ماذا لو، أسئلة، قالب، التزام واقعي) → لوحتي، ثم المدير يفتح غرفة التحكم واستوديو الدرس.
 * تعمل على الكمبيوتر والجوال (مشروعا Playwright)، مع فحص عدم التمرير الأفقي على 390px لكل صفحة جديدة.
 */
const LESSON = '/academy/marketing-foundations/first-channel-and-budget';

async function expectNoOverflowAt390(page, label) {
    const original = page.viewportSize();
    await page.setViewportSize({ width: 390, height: 844 });
    await page.waitForTimeout(100);
    await expectNoHorizontalOverflow(page, `${label} @390px`);
    if (original) await page.setViewportSize(original);
}

test.describe('الرحلة الكاملة للشريحة العمودية', () => {
    test('زائر: الرئيسية → أول خطوة → تشخيص بثلاث مراحل → خريطة أولويات → تسجيل', async ({ page }, testInfo) => {
        test.setTimeout(180_000);
        const errors = trackErrors(page);

        // 1. الرئيسية: نموذج «جرّب على مشروعك الآن» يعطي أول خطوة بلا طلب شبكة.
        await page.goto('/');
        await expect(page.locator('#try-form')).toBeVisible();
        await page.locator('#try-offer').fill('منتجات عناية طبيعية للنساء في الرياض');
        await page.locator('#try-problem').selectOption('no_sales');
        await page.locator('#try-budget').selectOption('2000');
        await page.getByRole('button', { name: 'أعطني أول خطوة' }).click();
        const firstStep = page.locator('#first-step');
        await expect(firstStep).toBeVisible();
        await expect(firstStep.locator('[data-step]')).toContainText('منتجات عناية طبيعية');
        await expect(firstStep.locator('[data-lesson-link]')).toHaveAttribute('href', /\/academy\/marketing-foundations\/core-message$/);
        await expectNoOverflowAt390(page, '/');

        // 2. التشخيص — المرحلة 1 مُعبّأة من الرئيسية.
        await firstStep.locator('[data-assess-link]').click();
        await expect(page).toHaveURL(/\/assess\?/);
        await expect(page.locator('#offer')).toHaveValue('منتجات عناية طبيعية للنساء في الرياض');
        await expect(page.locator('#problem')).toHaveValue('no_sales');
        await page.locator('#sector').selectOption('beauty');
        await page.locator('#city').fill('الرياض');
        await page.locator('#budget').fill('3000');
        await page.locator('#channel').selectOption('instagram');
        await page.locator('#goal').selectOption('leads');
        await page.locator('#goal_number').fill('60');
        await expectNoOverflowAt390(page, '/assess');
        await page.getByRole('button', { name: /ماذا تفعل الآن/ }).click();

        // المرحلة 2: 8 أسئلة مواقف مصوغة من بيانات المشروع، خيار واحد ينقل تلقائياً.
        await expect(page).toHaveURL(/\/assess\/questions$/);
        await expect(page.locator('.aq')).toHaveCount(8);
        await expect(page.locator('#assess-questions')).toContainText('3,000 ريال');
        await expectNoOverflowAt390(page, '/assess/questions');
        for (let i = 0; i < 8; i++) {
            const panel = page.locator(`.aq[data-index="${i}"]`);
            await expect(panel).toBeVisible();
            await panel.locator('input[type=radio]').nth(i < 2 ? 0 : 2).check();
            if (i < 7) await expect(page.locator(`.aq[data-index="${i + 1}"]`)).toBeVisible();
        }
        await page.locator('#aq-submit').click();

        // المرحلة 3: خريطة 3 أولويات برابط درس حقيقي، نسخ وتنزيل بلا تسجيل.
        await expect(page).toHaveURL(/\/assess\/result\?t=/);
        await expect(page.locator('#priorities .priority')).toHaveCount(3);
        await expect(page.locator('#priorities a[href*="/academy/marketing-foundations/"]').first()).toBeVisible();
        await expect(page.locator('#priorities [data-action="copy"]')).toBeVisible();
        await expect(page.locator('#priorities [data-action="download-pdf"]')).toBeVisible();
        await expect(page.locator('.dim-bar')).toHaveCount(5);
        await expect(page.locator('#impact')).toContainText('أثرك يُقاس هنا');
        const stored = await page.evaluate(() => JSON.parse(localStorage.getItem('athar-assessment') || 'null'));
        expect(stored?.token).toBeTruthy();
        await expectNoOverflowAt390(page, '/assess/result');

        // 3. التسجيل من البوابة: يحمل رمز الخريطة.
        await page.locator('[data-gate] a').click();
        await expect(page).toHaveURL(/\/register\?assessment=/);
        await expect(page.locator('#assessment_token')).toHaveValue(stored.token);
        await page.locator('#name').fill('زائرة التشخيص');
        await page.locator('#email').fill(`journey-${testInfo.project.name}-${Date.now()}@example.invalid`);
        await page.locator('#password').fill('BrowserPass!2026');
        await page.locator('#password_confirmation').fill('BrowserPass!2026');
        await page.getByRole('button', { name: /إنشاء الحساب/ }).click();
        await expect(page).toHaveURL(/\/verify-email$/);
        expect(errors).toEqual([]);
    });

    test('عضو: تشخيص → درس كامل عن مشروعه → لوحتي وتقدمي', async ({ page }, testInfo) => {
        test.setTimeout(240_000);
        const errors = trackErrors(page);
        await login(page, memberFor(testInfo));

        // التشخيص كعضو يبني ملف المشروع.
        await page.goto('/assess');
        await page.locator('#offer').fill('قهوة مختصة بالاشتراك في جدة');
        await page.locator('#sector').selectOption('food');
        await page.locator('#city').fill('جدة');
        await page.locator('#budget').fill('3000');
        await page.locator('#channel').selectOption('instagram');
        await page.locator('#goal').selectOption('leads');
        await page.locator('#goal_number').fill('60');
        await page.getByRole('button', { name: /ماذا تفعل الآن/ }).click();
        for (let i = 0; i < 8; i++) {
            await page.locator(`.aq[data-index="${i}"] input[type=radio]`).nth(1).check();
        }
        await page.locator('#aq-submit').click();
        await expect(page).toHaveURL(/\/assess\/result/);
        await expect(page.locator('body')).toContainText('محفوظة في حسابك');

        // الدرس: موقفه من مشروعه، القرار الافتتاحي، المختبر، ماذا لو، الأسئلة، القالب، الالتزام.
        await page.goto(LESSON);
        await expect(page.locator('#part-situation')).toContainText('3,000 ريال');
        await expect(page.locator('#part-situation')).toContainText('إنستغرام');
        await expect(page.locator('#part-goal')).toContainText('ما يدخل من الدروس السابقة');
        await expect(page.locator('.field.is-prefilled').first()).toBeVisible();
        await expectNoOverflowAt390(page, LESSON);

        await page.locator('#part-opening .dq-option').nth(1).click();
        await expect(page.locator('#part-opening .dq-option').nth(1)).toHaveAttribute('data-state', 'chosen');
        await expect(page.locator('[data-first-choice]')).not.toHaveText('—');

        await page.locator('[data-depth="fast"]').click();
        await expect(page.locator('html')).toHaveClass(/fast/);
        await page.locator('[data-depth="deep"]').click();

        await page.locator('#f-why_channel').fill('شريحتي تسأل في إنستغرام وتتابع حسابات قهوة سعودية');
        await page.locator('#f-margin').fill('120');
        await page.locator('#part-lab').scrollIntoViewIfNeeded();
        await page.locator('#lab-form button[type=submit]').first().click();
        await expect(page.locator('#asset')).toBeVisible();
        await expect(page.locator('#asset')).toContainText('خطة القناة الأولى');
        await expect(page.locator('#asset [data-verdict]')).toContainText('ضمن الهدف');

        // ماذا لو: تخفيض الميزانية يغيّر الحكم إلى «خارج الهدف».
        const range = page.locator('#whatif-range');
        await range.fill('900');
        await expect(page.locator('[data-wi-verdict]')).toContainText('خارج الهدف');
        await expect(page.locator('[data-wi-out="expected_leads"]')).toContainText('23');

        await page.locator('#asset [data-action="copy"]').click();
        await expect(page.locator('#asset [data-action="copy"]')).toContainText('نُسخ');
        await page.locator('#asset [data-action="save"]').click();
        await expect(page.locator('.alert-success')).toContainText('حُفظ الأصل');
        await expect(page.locator('#asset')).toContainText('محفوظ في مشروعي');

        // أسئلة القرار بتغذية راجعة فورية (الأول خطأ عمداً ليغذّي إشارات الجودة).
        const questions = page.locator('#part-questions .dq');
        await expect(questions).toHaveCount(3);
        const wrong = questions.nth(0).locator('.dq-option[data-best="0"]').first();
        await wrong.click();
        await expect(questions.nth(0).locator('[data-feedback-box]')).toBeVisible();
        await expect(wrong).toHaveAttribute('data-state', 'wrong');
        for (const i of [1, 2]) {
            await questions.nth(i).locator('.dq-option[data-best="1"]').click();
            await expect(questions.nth(i).locator('[data-feedback-box]')).toHaveClass(/is-best/);
        }
        await page.locator('#changed-mind input[name=choice]').nth(1).check();
        await page.locator('#note').fill('فهمت قاعدة القرار');
        await page.locator('#changed-mind button[type=submit]').click();
        await expect(page.locator('#changed-mind')).toContainText('مسجّل ✓');

        // القالب والالتزام الواقعي.
        await expect(page.locator('[data-template-download]')).toHaveAttribute('href', /\/download$/);
        await page.locator('#commit-form textarea').fill('أشغّل إنستغرام 7 أيام بـ100 ريال يومياً وأسجّل الطلبات');
        await page.locator('#commit-form button[type=submit]').click();
        await expect(page.locator('.alert-success')).toContainText('التزمت بفعل واقعي');
        await expect(page.locator('#result-form')).toBeVisible();
        await expect(page.locator('#lesson')).toContainText('مكتمل ✓');
        await page.locator('#r-spent').fill('700');
        await page.locator('#r-leads').fill('16');
        await page.locator('#r-actual_cpl').fill('44');
        await page.locator('#result-form button[type=submit]').click();
        await expect(page.locator('.alert-success')).toContainText('سُجّلت نتيجتك الواقعية');

        // لوحتي وتقدمي.
        await page.goto('/dashboard');
        await expect(page.locator('#weekly-plan')).toBeVisible();
        await expect(page.locator('#assets')).toContainText('خطة القناة الأولى');
        await expect(page.locator('.stat').nth(2)).toContainText('1');
        await expect(page.locator('#maturity')).toContainText('/100');
        await expectNoOverflowAt390(page, '/dashboard');
        await page.goto('/app/progress');
        await expect(page.locator('body')).toContainText('الدروس المكتملة');
        await expectNoOverflowAt390(page, '/app/progress');
        expect(errors).toEqual([]);
    });

    test('المدير: غرفة التحكم تعرض إشارة جودة حقيقية ويفتح الدرس في الاستوديو', async ({ page }) => {
        test.setTimeout(120_000);
        const errors = trackErrors(page);
        await login(page, ADMIN);

        await page.goto('/admin');
        await expect(page.locator('#journey .col')).toHaveCount(5);
        await page.locator('#recompute-signals').click();
        await expect(page.locator('.alert-success')).toContainText('إشارات الجودة');
        const signal = page.locator('#signals .signal').first();
        await expect(signal).toBeVisible();
        await expect(page.locator('#pipeline .heat-cell[data-state]')).not.toHaveCount(0);
        await expectNoOverflowAt390(page, '/admin');

        await page.locator('#signals .signal a[href*="/admin/academy/lessons/"]').first().click();
        await expect(page).toHaveURL(/\/admin\/academy\/lessons\/\d+/);
        await expect(page.locator('#studio-parts a')).toHaveCount(9);
        await expect(page.locator('#studio-editor')).toBeVisible();
        await expect(page.locator('#studio-assistant .suggestion').first()).toBeVisible();
        await expectNoOverflowAt390(page, 'studio');

        await page.locator('#reason').fill('تجربة المتصفح');
        await page.locator('#studio-editor button[type=submit]').click();
        await expect(page.locator('.alert-success')).toContainText('إصدار جديد');

        for (const path of ['/admin/academy', '/admin/assessment']) {
            await page.goto(path);
            await expectNoOverflowAt390(page, path);
        }
        expect(errors).toEqual([]);
    });
});
