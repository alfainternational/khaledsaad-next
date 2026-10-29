// @ts-check
import { expect } from '@playwright/test';

export const MEMBER = { email: 'browser-member@example.invalid', password: 'BrowserPass!2026' };
export const ADMIN = { email: 'browser-admin@example.invalid', password: 'BrowserPass!2026' };
const MOBILE_MEMBER = { email: 'browser-member-mobile@example.invalid', password: 'BrowserPass!2026' };

/** The member account owned by this Playwright project, so desktop and mobile runs never share learner state. */
export function memberFor(testInfo) {
    return testInfo.project.name === 'mobile' ? MOBILE_MEMBER : MEMBER;
}

/** Fails the test if the page is wider than its viewport (horizontal overflow). */
export async function expectNoHorizontalOverflow(page, label = page.url()) {
    // Measure only once stylesheets and fonts are applied: right after a redirect the unstyled DOM is wider.
    await page.waitForLoadState('load');
    await page.evaluate(() => document.fonts?.ready);
    const { scrollWidth, clientWidth } = await page.evaluate(() => ({
        scrollWidth: document.documentElement.scrollWidth,
        clientWidth: document.documentElement.clientWidth,
    }));
    expect(scrollWidth, `${label} overflows horizontally (${scrollWidth}px > ${clientWidth}px)`).toBeLessThanOrEqual(clientWidth + 1);
}

/** Collects uncaught page errors and console errors so a test can assert none happened. */
export function trackErrors(page) {
    const errors = [];
    page.on('pageerror', (error) => errors.push(`pageerror: ${error.message}`));
    page.on('console', (message) => {
        if (message.type() === 'error') {
            errors.push(`console: ${message.text()}`);
        }
    });
    return errors;
}

export async function login(page, { email, password }) {
    await page.goto('/login');
    await page.locator('#email').fill(email);
    await page.locator('#password').fill(password);
    await page.getByRole('button', { name: /دخول|تسجيل الدخول|log in/i }).click();
    await expect(page).toHaveURL(/\/dashboard$/);
}
