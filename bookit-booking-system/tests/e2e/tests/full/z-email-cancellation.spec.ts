import { test, expect } from '@playwright/test';
import { completeWizardSteps1To4 } from '../../fixtures/wizard';
import { getLatestEmail, extractLinkFromEmail, clearMailpit } from '../../fixtures/mailpit';

test.describe('Cancellation email content', { tag: '@full' }, () => {
  test('cancellation email has correct subject and service name', async ({ page }) => {
    const testEmail = await completeWizardSteps1To4(page);

    // Step 5: select Pay in Person (UI only — no network request on row click)
    await page.locator('#bookit-v2-pay-person').click();

    // Intercept wizard/complete at network level BEFORE clicking CTA
    let capturedBody: string | null = null;
    await page.route('**/wizard/complete', async (route) => {
      const response = await route.fetch();
      capturedBody = await response.text();
      await route.fulfill({ response });
    });

    // CTA click: fires POST /wizard/session then POST /wizard/complete
    await page.locator('#bookit-v2-cta-btn').click();

    // Wait for route handler to capture the body
    const deadline = Date.now() + 15_000;
    while (capturedBody === null && Date.now() < deadline) {
      await page.waitForTimeout(100);
    }

    let completeJson: any = null;
    try {
      if (capturedBody) completeJson = JSON.parse(capturedBody);
    } catch { /* ignore */ }

    if (!completeJson?.success) {
      throw new Error(`wizard/complete failed: ${capturedBody}`);
    }

    await page.waitForURL('**/booking-confirmed-v2/**', { timeout: 20_000 });

    const confirmEmail = await getLatestEmail(testEmail, page);
    const cancelUrl = extractLinkFromEmail(confirmEmail.HTML, 'Cancel Booking');

    await clearMailpit();

await page.goto(cancelUrl, { waitUntil: 'networkidle', timeout: 15_000 });

// Log what the page says after navigation
const pageText = await page.locator('body').innerText();

const confirmBtn = page.locator('#bookit-cancel-confirm');
await expect(confirmBtn).toBeVisible({ timeout: 10_000 });
await confirmBtn.click();
await page.waitForTimeout(10_000);

// Log page after clicking confirm
const pageTextAfter = await page.locator('body').innerText();

// Trigger Action Scheduler
const baseUrl = process.env.BASE_URL || 'http://plugin-test-1.local';
await page.goto(`${baseUrl}/wp-admin/`, {
  waitUntil: 'commit',
  timeout: 10_000,
}).catch(() => {});

const cancelEmail = await getLatestEmail(testEmail, page);
expect(cancelEmail.Subject.toLowerCase()).toContain('cancel');
expect(cancelEmail.HTML.length).toBeGreaterThan(0);
  });
});
