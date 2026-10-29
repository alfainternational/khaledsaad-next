// @ts-check
import { test, expect } from '@playwright/test';
import { ADMIN, expectNoHorizontalOverflow, login, memberFor, trackErrors } from './helpers.js';

/**
 * المرحلة 2 — المختبر (المخطط v1.3 §4.6–4.8، §5.3–5.4، §6.4): زائر يولّد سلسلة من أصلين مفحوصين بلا AI ولا تسجيل؛
 * عضو يحفظ ويُصدر ويشارك ويرى خطته تتجمّع؛ المدير يختبر الأداة من الاستوديو ويرى معدل الحفظ.
 * تعمل على الكمبيوتر والجوال مع فحص عدم التمرير الأفقي على 412px و390px لكل صفحة جديدة.
 */
async function expectNoOverflowNarrow(page, label) {
    const original = page.viewportSize();
    for (const width of [412, 390]) {
        await page.setViewportSize({ width, height: 844 });
        await page.waitForTimeout(80);
        await expectNoHorizontalOverflow(page, `${label} @${width}px`);
    }
    if (original) await page.setViewportSize(original);
}

/** Scrolls the control to the middle of the viewport before clicking so the sticky header never intercepts the tap on mobile. */
async function tap(locator) {
    await locator.evaluate((el) => el.scrollIntoView({ block: 'center' }));
    await locator.click();
}

test.describe('المختبر التسويقي', () => {
    test('زائر: /tools → شخصية العميل → عرض قيمة مغذّى منها → فحص ستّي → بوابة الحفظ', async ({ page }) => {
        test.setTimeout(150_000);
        const errors = trackErrors(page);

        await page.goto('/tools');
        await expect(page.locator('h1')).toContainText('من السؤال إلى أصل قابل للاستخدام');
        await expect(page.locator('.tool-card')).toHaveCount(15);
        await expect(page.locator('body')).not.toContainText('قريباً');
        // الفلاتر تعمل بلا تحميل.
        await page.locator('#tool-filters a[data-filter="الجمهور"]').click();
        await expect(page.locator('.tool-card:visible')).toHaveCount(1);
        await page.locator('#tool-filters a[data-filter=""]').click();
        await expect(page.locator('.tool-card:visible')).toHaveCount(15);
        await expectNoOverflowNarrow(page, '/tools');

        // شخصية العميل بمثال جاهز.
        await page.goto('/tools/customer-persona?example=1');
        await expect(page.locator('#chain')).toContainText('يُغذّى من');
        await expect(page.locator('#f-audience')).not.toHaveValue('');
        await tap(page.locator('#generate-btn'));
        await expect(page).toHaveURL(/\/tools\/customer-persona#asset$/);
        const asset = page.locator('#asset');
        await expect(asset).toBeVisible();
        await expect(asset.locator('.quality-list li')).toHaveCount(6);
        await expect(asset.locator('[data-action="copy"]')).toBeVisible();
        await expect(asset.locator('[data-action="save-gate"]')).toHaveAttribute('href', /\/register$/);
        await expect(page.locator('#plan-box [data-plan-item="customer_persona"]')).toHaveAttribute('data-state', 'generated');
        await expectNoOverflowNarrow(page, '/tools/customer-persona');

        // الخطوة التالية: عرض القيمة مُعبّأ من شخصية العميل.
        await tap(page.locator('[data-next-step]'));
        await expect(page).toHaveURL(/\/tools\/value-proposition/);
        const audienceField = page.locator('[data-field="audience"]');
        await expect(audienceField).toHaveClass(/is-prefilled/);
        await expect(audienceField.locator('[data-source]')).toContainText('من شخصية العميل');
        await expect(page.locator('#chain [data-feed="customer_persona"]')).toHaveAttribute('data-ok', '1');
        // الحقول غير المُعبّأة من السلسلة يكتبها المتعلم.
        await page.locator('#f-offer').fill('سيروم طبيعي بلا عطور');
        await page.locator('#f-differentiator').fill('تركيبة بلا عطور مختبَرة على بشرة حساسة');
        await page.locator('#f-proof').fill('شهادة اختبار جلدي و120 تقييماً بمتوسط 4.8');
        await tap(page.locator('#generate-btn'));
        await expect(page.locator('#asset')).toBeVisible();
        await expect(page.locator('#asset .quality-list li')).toHaveCount(6);
        await expect(page.locator('#asset')).toContainText('عرض القيمة');
        await expect(page.locator('[data-gate]')).toBeVisible();
        await expectNoOverflowNarrow(page, '/tools/value-proposition');
        expect(errors).toEqual([]);
    });

    test('عضو: يولّد ويحفظ v1 ثم v2 بفرق، يشارك برابط عام ويلغيه، حاسبة CAC بلا إرسال، وخطته تتجمّع', async ({ page, browser }, testInfo) => {
        test.setTimeout(180_000);
        const errors = trackErrors(page);
        await login(page, memberFor(testInfo));

        await page.goto('/tools/value-proposition?example=1');
        await expect(page.locator('#project_id option')).not.toHaveCount(0);
        await tap(page.locator('#generate-btn'));
        await expect(page.locator('#asset')).toBeVisible();
        await tap(page.locator('#asset [data-action="save"]'));
        const status1 = await page.locator('.alert-success').first().textContent();
        const v1 = Number(/v(\d+)/.exec(status1 || '')?.[1]);
        expect(v1).toBeGreaterThan(0);
        await expect(page.locator('#asset [data-saved]')).toBeVisible();

        // إصدار ثانٍ بدليل مختلف: لا يمحو الأول ويعرض ما تغيّر.
        await page.locator('#f-proof').fill('شهادة مختبر مستقل و300 تقييم بمتوسط 4.9');
        await tap(page.locator('#generate-btn'));
        await expect(page.locator('#asset')).toBeVisible();
        await expect(page.locator('#asset [data-diff]')).toBeVisible();
        await tap(page.locator('#asset [data-action="save"]'));
        const status2 = await page.locator('.alert-success').first().textContent();
        expect(Number(/v(\d+)/.exec(status2 || '')?.[1])).toBe(v1 + 1);
        await expect(page.locator('#asset [data-versions]')).toContainText(`v${v1 + 1}`);
        await expect(page.locator('#asset [data-versions]')).toContainText(`v${v1}`);
        await tap(page.locator('#asset [data-diff] summary'));
        await expect(page.locator('#asset .diff-add').first()).toContainText('300 تقييم');
        await expectNoOverflowNarrow(page, '/tools/value-proposition (member)');

        // المشاركة برابط للقراءة: تُفتح بلا تسجيل في سياق جديد، ثم تُلغى → 404.
        await tap(page.locator('#asset [data-action="share"]'));
        const shareUrl = await page.locator('#share-url').inputValue();
        expect(shareUrl).toMatch(/\/a\/[A-Za-z0-9]{32}$/);
        const anonymous = await browser.newContext();
        const reader = await anonymous.newPage();
        const open = await reader.goto(shareUrl);
        expect(open?.status()).toBe(200);
        await expect(reader.locator('#asset')).toContainText('عرض القيمة');
        await expect(reader.locator('body')).toContainText('أصل مشترك للقراءة');
        await expect(reader.locator('[data-action="save"]')).toHaveCount(0);
        await expectNoHorizontalOverflow(reader, shareUrl);
        await tap(page.locator('#asset [data-action="unshare"]'));
        await expect(page.locator('.alert-success').first()).toContainText('أُلغي رابط المشاركة');
        const revoked = await reader.goto(shareUrl);
        expect(revoked?.status()).toBe(404);
        await anonymous.close();

        // حاسبة CAC: النتيجة تتحدث وأنت تكتب بلا إرسال.
        await page.goto('/tools/calculators/cac');
        await page.locator('#f-spend').fill('3000');
        await page.locator('#f-customers').fill('60');
        await expect(page.locator('[data-headline]')).toContainText('50');
        await page.locator('#f-customers').fill('30');
        await expect(page.locator('[data-headline]')).toContainText('100');
        await expect(page).toHaveURL(/\/tools\/calculators\/cac$/);
        await expectNoOverflowNarrow(page, '/tools/calculators/cac');

        // المحاكي.
        await page.goto('/tools/simulator');
        await page.locator('#f-budget').fill('3000');
        await expect(page.locator('[data-sim-rows]')).toContainText('120,000');
        await expectNoOverflowNarrow(page, '/tools/simulator');

        // خطة مشروعك تتجمّع: في المشروع وفي أصولي.
        await page.goto('/dashboard');
        await page.locator('#lab-links a[href*="/app/projects/"]').click();
        await expect(page).toHaveURL(/\/app\/projects\/[a-z0-9-]+$/);
        await expect(page.locator('#plan-box')).toContainText('خطة مشروعك تتجمّع');
        await expect(page.locator('#plan-box [data-plan-item="value_proposition"]')).toHaveAttribute('data-state', 'saved');
        await expectNoOverflowNarrow(page, '/app/projects');
        await page.goto('/app/outputs');
        await expect(page.locator('table')).toContainText('عرض القيمة');
        await expectNoOverflowNarrow(page, '/app/outputs');
        expect(errors).toEqual([]);
    });

    test('المدير: استوديو الأداة يختبر بمدخلات نموذجية ويعرض معدل الحفظ', async ({ page }) => {
        test.setTimeout(120_000);
        const errors = trackErrors(page);
        await login(page, ADMIN);

        await page.goto('/admin/tools');
        await expect(page.locator('h1')).toContainText('استوديو الأدوات');
        await expect(page.locator('tr[data-tool]')).toHaveCount(15);
        await expectNoOverflowNarrow(page, '/admin/tools');
        await page.locator('tr[data-tool="campaign-brief-builder"] a').click();
        await expect(page).toHaveURL(/\/admin\/tools\/\d+$/);
        await expect(page.locator('#save-rate [data-save-rate]')).toBeVisible();
        await expect(page.locator('#versions tr[data-version="1"]')).toBeVisible();
        await tap(page.locator('#run-test'));
        await expect(page).toHaveURL(/#test$/);
        await expect(page.locator('[data-test-result]')).toBeVisible();
        await expect(page.locator('#test-asset .quality-list li')).toHaveCount(6);
        await expectNoOverflowNarrow(page, '/admin/tools/{id}');
        expect(errors).toEqual([]);
    });
});
