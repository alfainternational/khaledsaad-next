// @ts-check
import { test, expect } from '@playwright/test';
import { expectNoHorizontalOverflow, trackErrors } from './helpers.js';

test.describe('القراءة والمحتوى الفعلي', () => {
    for (const theme of ['light', 'dark']) {
        test(`مادة قصيرة ومطولة ومراجع بالمظهر ${theme}`, async ({ page }, testInfo) => {
            test.setTimeout(180_000);
            const errors = trackErrors(page);
            await page.emulateMedia({ colorScheme: theme });
            for (const [slug, kind] of [['browser-short-reading', 'focused'], ['customer-journey-map', 'indexed'], ['ai-writing-without-sounding-like-everyone-else', 'indexed'], ['whatsapp-business-per-message-pricing', 'indexed']]) {
                await page.goto(`/articles/${slug}`);
                await expect(page.locator('.reading-page')).toHaveClass(new RegExp(`reading-page--${kind}`));
                await expect(page.locator('.reading-trust')).toBeVisible();
                await expect(page.locator('.reading-action-primary')).toHaveCount(1);
                if (slug === 'customer-journey-map') {
                    await page.screenshot({ path: `test-results/reading-preview-${testInfo.project.name}-${theme}.png` });
                }
                if (kind === 'focused') await expect(page.locator('.article-side')).toHaveCount(0);
                else {
                    const mobile = testInfo.project.name === 'mobile';
                    await expect(page.locator(mobile ? '.toc-mobile-box summary' : '.article-side .toc')).toBeVisible();
                    if (mobile) await page.locator('.toc-mobile-box summary').click();
                    await page.locator(mobile ? '.toc-mobile-box a' : '.article-side [data-toc]').first().click();
                    await expect(page).toHaveURL(/#.+/);
                }
                await expectNoHorizontalOverflow(page);
                await page.screenshot({ path: `test-results/reading-${testInfo.project.name}-${theme}-${slug}.png`, fullPage: true });
            }
            for (const path of ['/glossary/cac', '/about']) {
                await page.goto(path);
                await expect(page.locator('.reading-page')).toBeVisible();
                await expectNoHorizontalOverflow(page);
                await page.screenshot({ path: `test-results/reading-${testInfo.project.name}-${theme}-${path.split('/').pop()}.png`, fullPage: true });
            }
            expect(errors).toEqual([]);
        });
    }

    test('عرض القراءة يبقى مضبوطاً عند اختفاء الفهرس في الجهاز اللوحي', async ({ page }) => {
        await page.setViewportSize({ width: 980, height: 900 });
        await page.goto('/articles/customer-journey-map');
        const width = await page.locator('.article-main').evaluate(e => e.getBoundingClientRect().width);
        expect(width).toBeLessThanOrEqual(741);
        await expectNoHorizontalOverflow(page);
    });
});
