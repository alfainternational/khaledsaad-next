// @ts-check
import { test, expect } from '@playwright/test';
import { ADMIN, expectNoHorizontalOverflow, login, trackErrors } from './helpers.js';

/**
 * المرحلة 3 — المجلة ومحرك المحتوى (المخطط v1.3 §4.10–4.12، §6.3، §6.5):
 * زائر يتصفح الأقسام الخمسة ويقرأ مادة بالشكل الكامل (فهرس، «في دقيقة»، مصادر، طبّق ما قرأت) ويفتح مصطلحاً وموضوعاً
 * ويبحث فيجد نتائج مجمّعة واقتراحات ويشترك في النشرة؛ والمدير يدير جداول المحتوى ويعدّل مادة ويمرّر رابطاً يدوياً عبر البوابات الخمس حتى النشر.
 * بلا تمرير أفقي على 412px و390px وبلا أخطاء كونسول.
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

test.describe('المجلة ومحرك المحتوى', () => {
    test('زائر: الأقسام الخمسة → مادة كاملة → مصطلح → موضوع → بحث → النشرة', async ({ page }) => {
        test.setTimeout(180_000);
        const errors = trackErrors(page);

        // الأقسام الخمسة بالقالب نفسه، كل قسم 10 مواد على الأقل.
        for (const [path, heading] of [['/articles', 'تسويق يمكن تطبيقه'], ['/news', 'أخبار'], ['/insights', 'رؤى عالمية'], ['/case-studies', 'دراسات حالة'], ['/research', 'أبحاث']]) {
            await page.goto(path);
            await expect(page.locator('h1')).toContainText(heading);
            const count = await page.locator('article.content-card').count();
            expect(count, `${path} shows at least 10 items`).toBeGreaterThanOrEqual(path === '/articles' ? 12 : 10);
            await expectNoOverflowNarrow(page, path);
        }
        // فلتر المؤلف يعمل بلا جافاسكربت.
        await page.goto('/articles');
        await page.locator('#f-author').selectOption('abdullah-alshehri');
        await tap(page.locator('#magazine-filters button[type="submit"]'));
        await expect(page).toHaveURL(/author=abdullah-alshehri/);
        await expect(page.locator('article.content-card').first()).toContainText('عبدالله الشهري');

        // مادة من الرؤى بالشكل الكامل.
        await page.goto('/insights');
        // A stage-3 item (the legacy stage-1 insights carry no tldr or apply box and are newer, so «first» is not enough).
        await tap(page.locator('article.content-card h2 a[href$="/articles/nngroup-f-pattern-how-arabic-readers-scan-your-page"]'));
        await expect(page).toHaveURL(/\/articles\/nngroup-f-pattern-how-arabic-readers-scan-your-page$/);
        await expect(page.locator('#tldr')).toContainText('في دقيقة');
        await expect(page.locator('.byline')).toContainText('بقلم');
        await expect(page.locator('.byline')).toContainText('دقائق قراءة');
        await expect(page.locator('.article-body h2[id]').first()).toBeVisible();
        await expect(page.locator('.apply-now .btn')).toBeVisible();
        await expect(page.locator('#sources .sources-list li').first()).toBeVisible();
        await expect(page.locator('#apply')).toContainText('طبّق ما قرأت');
        await expect(page.locator('#apply')).toContainText('الأداة المرتبطة');
        await expect(page.locator('.prev-next')).toBeVisible();
        const jsonLd = await page.locator('script[type="application/ld+json"]').first().textContent();
        expect(JSON.parse(jsonLd ?? '{}')['@type']).toMatch(/Article/);
        // الفهرس اللاصق على سطح المكتب يعلّم العنوان الظاهر؛ وعلى الجوال يظهر صندوق قابل للطي.
        const desktop = (page.viewportSize()?.width ?? 0) >= 980;
        if (desktop) {
            await expect(page.locator('.article-side .toc [data-toc]').first()).toBeVisible();
            const secondHeading = page.locator('.article-body h2[id]').nth(1);
            const secondId = await secondHeading.getAttribute('id');
            await tap(page.locator(`.toc a[data-toc="${secondId}"]`));
            await expect(secondHeading).toBeInViewport();
        } else {
            await expect(page.locator('.toc-mobile-box summary')).toBeVisible();
        }
        await expect(page.locator('body')).not.toContainText('تنبيه للمحرر فقط');
        await expectNoOverflowNarrow(page, '/articles/{slug}');

        // بلاغ من داخل المادة.
        await tap(page.locator('#report summary'));
        await page.locator('#report-reason').selectOption('unclear');
        await tap(page.locator('#report button[type="submit"]'));
        await expect(page.locator('.alert-success').first()).toContainText('وصل بلاغك');

        // القاموس: تعريف مباشر في أول سطر ثم مثال ثم تطبيق ومواد مرتبطة.
        await page.goto('/glossary');
        await expect(page.locator('h1')).toContainText('40 مصطلحاً');
        await expect(page.locator('.term-card')).toHaveCount(40);
        await expectNoOverflowNarrow(page, '/glossary');
        await tap(page.locator('a.term-card[href$="/glossary/cac"]'));
        await expect(page).toHaveURL(/\/glossary\/cac$/);
        await expect(page.locator('#definition')).toBeVisible();
        await expect(page.locator('.example')).toContainText('مثال');
        await expect(page.locator('#apply')).toContainText('طبّق المصطلح');
        await expect(page.locator('#apply a[href*="/tools/calculators/cac"]')).toBeVisible();
        await expectNoOverflowNarrow(page, '/glossary/cac');

        // موضوع = مسار: تعريف + مواد + المسار + أداة.
        await page.goto('/topics/measurement-analytics-cro');
        await expect(page.locator('h1')).toContainText('القياس');
        expect(await page.locator('.topic-layout .card.is-link').count()).toBeGreaterThan(0);
        await expect(page.locator('aside .side-box').first()).toContainText('المسار');
        await expectNoOverflowNarrow(page, '/topics/{topic}');

        // البحث من طبقة الرأس: نتائج مجمّعة.
        await page.goto('/articles');
        await tap(page.locator('.search-open'));
        await page.locator('#layer-q').fill('تكلفة اكتساب');
        await page.locator('#search-layer form button[type="submit"]').click();
        await expect(page).toHaveURL(/\/search\?q=/);
        await expect(page.locator('[data-search-total]')).toBeVisible();
        await expect(page.locator('section[data-group="terms"]')).toContainText('تكلفة اكتساب العميل');
        await expect(page.locator('section[data-group="items"] li').first()).toBeVisible();
        await expectNoOverflowNarrow(page, '/search?q=');
        // بلا نتائج فارغة.
        await page.goto('/search?q=zzzzqqqq');
        await expect(page.locator('[data-suggestions]')).toContainText('أحدث المواد');
        await expect(page.locator('[data-suggestions]')).toContainText('أشهر المصطلحات');
        await expect(page.locator('[data-suggestions] a[href*="/glossary/"]').first()).toBeVisible();

        // النشرة من التذييل: رسالة نجاح بلا إعادة تحميل كاملة.
        const beforeUrl = page.url();
        await page.locator('#footer-email').fill(`reader-${Date.now()}@example.invalid`);
        await tap(page.locator('[data-newsletter] button[type="submit"]'));
        await expect(page.locator('[data-newsletter-status]')).toContainText('رسالة تأكيد');
        expect(page.url()).toBe(beforeUrl);
        await expect(page.locator('#footer-email')).toHaveValue('');

        expect(errors).toEqual([]);
    });

    test('المدير: جداول المحتوى → تعديل مادة → لوح المصادر بالبوابات الخمس حتى النشر', async ({ page }) => {
        test.setTimeout(180_000);
        const errors = trackErrors(page);
        await login(page, ADMIN);

        // جداول قياسية: بحث وفرز وتصفية.
        await page.goto('/admin/content');
        await expect(page.locator('h1')).toContainText('المحتوى');
        await page.locator('#f-q').fill('cac');
        await page.locator('#f-section').selectOption('case-studies');
        await tap(page.locator('#content-filters button[type="submit"]'));
        await expect(page).toHaveURL(/section=case-studies/);
        await expect(page.locator('tr[data-item]').first()).toBeVisible();
        await tap(page.locator('th a[aria-sort]').first());
        await expect(page).toHaveURL(/sort=title/);
        await expectNoOverflowNarrow(page, '/admin/content');
        for (const kind of ['categories', 'tags', 'authors']) {
            await page.goto(`/admin/content/${kind}`);
            await expect(page.locator('tr[data-row]').first()).toBeVisible();
            await expectNoOverflowNarrow(page, `/admin/content/${kind}`);
        }
        // وسم جديد ثم حذفه بتأكيد.
        await page.goto('/admin/content/tags');
        await page.locator('#form #name').fill('وسم تجريبي');
        await tap(page.locator('#form button[type="submit"]'));
        await expect(page.locator('.alert-success').first()).toContainText('وسم تجريبي');
        const row = page.locator('tr[data-row]', { hasText: 'وسم تجريبي' });
        await row.locator('input[name="confirm"]').check();
        await tap(row.locator('button:has-text("حذف")'));
        await expect(page.locator('.alert-success').first()).toContainText('حُذف');

        // تعديل مادة: tldr جديد يظهر على الموقع.
        await page.goto('/admin/content?q=natural-skincare');
        await tap(page.locator('tr[data-item="natural-skincare-store-true-cac"] a').first());
        await expect(page).toHaveURL(/\/admin\/content\/\d+\/edit$/);
        await page.locator('#tldr').fill('احسب التكلفة الحقيقية قبل أن تحكم على القناة؛ المتجر لم يخسر الحملة بل أخطأ القسمة.');
        await tap(page.locator('#content-editor button[type="submit"]'));
        await expect(page.locator('.alert-success').first()).toContainText('تم حفظ');
        await expectNoOverflowNarrow(page, '/admin/content/{id}/edit');
        await page.goto('/articles/natural-skincare-store-true-cac');
        await expect(page.locator('#tldr')).toContainText('أخطأ القسمة');

        // لوح المصادر: رابط يدوي عبر البوابات الخمس.
        await page.goto('/admin/sources');
        await expect(page.locator('h1')).toContainText('من المصادر إلى الرؤى');
        await expect(page.locator('.gate')).toHaveCount(5);
        await expect(page.locator('tr[data-feed]')).toHaveCount(14);
        await expectNoOverflowNarrow(page, '/admin/sources');
        const stamp = Date.now();
        await page.locator('#m-url').fill(`https://www.thinkwithgoogle.com/intl/en-145/consumer-insights/${stamp}/`);
        await page.locator('#m-title').fill('Consumer insights: how shoppers research before buying');
        await page.locator('#m-publisher').fill('Think with Google');
        await page.locator('#m-date').fill('2026-09-10');
        await page.locator('#m-excerpt').fill('Google data on search and conversion for small business marketing in Saudi Arabia.');
        await tap(page.locator('#manual-link button[type="submit"]'));
        await expect(page).toHaveURL(/\/admin\/sources\/items\/\d+$/);
        await expect(page.locator('.alert-success').first()).toContainText('درجة صلة');
        await expectNoOverflowNarrow(page, '/admin/sources/items/{id}');

        // 2 تحقق: رقم بلا مصدر أولي لا يمر.
        await page.locator('#v-date').fill('2026-09-10');
        await page.locator('#v-market').fill('ينطبق على المتاجر السعودية التي تعتمد البحث المدفوع قبل الشراء.');
        await page.locator('#gate-verified input[name="has_numbers"]').check();
        await tap(page.locator('#gate-verified button[type="submit"]'));
        await expect(page.locator('.alert-error').first()).toContainText('المصدر الأولي');
        await page.locator('#v-primary').fill('Google/Ipsos 2026 — https://www.thinkwithgoogle.com/data/');
        await tap(page.locator('#gate-verified button[type="submit"]'));
        await expect(page.locator('.alert-success').first()).toContainText('بوابة التحقق');

        // 3 ملخص بالقواعد (مادة خام).
        await tap(page.locator('#summarize-rules'));
        await expect(page.locator('[data-summary]')).toContainText('لا تُنشر كما هي');

        // 4 سياق عربي: عدّاد الكلمات ثم إنشاء مسودة «رؤية عالمية».
        await page.locator('#c-title').fill('كيف يبحث المتسوق السعودي قبل الشراء وماذا يعني ذلك لمتجرك؟');
        await page.locator('#c-tldr').fill('ابدأ بقياس الطلب على بحث العلامة قبل الإنفاق على الإعلان.');
        await page.locator('#c-context').fill('هذا سياق عربي يشرح أثر النتيجة على قرار صاحب المشروع السعودي ويقارنها بسلوك الشراء المحلي. '.repeat(14));
        await expect(page.locator('#c-count')).toHaveClass(/ok/);
        await page.locator('#c-example').fill('متجر عطور في جدة قاس بحث العلامة لأسبوعين قبل الإطلاق.');
        await page.locator('#c-lesson').selectOption({ index: 1 });
        await page.locator('#c-tool').selectOption({ index: 1 });
        await tap(page.locator('#gate-contextualized button[type="submit"]'));
        await expect(page.locator('.alert-success').first()).toContainText('رؤية عالمية');

        // 5 مراجعة ونشر: قائمة الفحص الخمسية واسم المراجع.
        for (const key of ['primary', 'context', 'links', 'voice', 'freshness']) {
            await page.locator(`#chk-${key}`).check();
        }
        await page.locator('#r-name').fill('خالد سعد');
        await tap(page.locator('#publish-from-sources'));
        await expect(page).toHaveURL(/\/admin\/sources$/);
        await expect(page.locator('.alert-success').first()).toContainText('نُشرت المادة');
        await page.goto('/insights');
        await expect(page.locator('article.content-card').first()).toContainText('كيف يبحث المتسوق السعودي');
        await tap(page.locator('article.content-card h2 a').first());
        await expect(page.locator('#sources')).toContainText('Think with Google');
        await expect(page.locator('#apply')).toContainText('الدرس المرتبط');
        expect(errors).toEqual([]);
    });
});
