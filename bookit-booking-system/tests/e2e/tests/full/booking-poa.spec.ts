import { test, expect } from '@playwright/test';
import { completeWizardSteps1To4 } from '../../fixtures/wizard';
import { getLatestEmail } from '../../fixtures/mailpit';

declare const process: { env: Record<string, string | undefined> };

test.describe('Full booking — Pay on Arrival', { tag: '@full' }, () => {
  test('completes wizard Steps 1–5 POA, shows confirmation, delivers email', async ({ page }) => {
    const testEmail = await completeWizardSteps1To4(page);

    // Step 5: select Pay in Person (UI only — no network request on row click)
    await page.locator('#bookit-v2-pay-person').click();

    // --- DIAGNOSTIC: verify session contains correct date/time ---
    const baseUrl = process.env.BASE_URL || 'http://plugin-test-1.local';
    const nonce = await page.evaluate(() => (window as any).bookitWizardV2?.nonce || '');
    const sessionCheck = await page.request.get(
      `${baseUrl}/wp-json/bookit/v1/wizard/session`,
      { headers: { 'X-WP-Nonce': nonce } }
    ).catch(() => null);
    if (sessionCheck) {
      const sessionData = await sessionCheck.json().catch(() => null);
      console.log('[SESSION CHECK before complete]', JSON.stringify({
        date: sessionData?.data?.date,
        time: sessionData?.data?.time,
        payment_method: sessionData?.data?.payment_method,
        customer_email: sessionData?.data?.customer_email,
        current_step: sessionData?.data?.current_step,
      }));
    } else {
      console.log('[SESSION CHECK] failed to fetch session');
    }
    // --- END DIAGNOSTIC ---

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

    // Confirmation page
    await page.waitForURL('**/booking-confirmed-v2/**', { timeout: 20_000 });
    await expect(page.locator('body')).toContainText(/BK[\d-]/);

    // Visiting the dashboard triggers Action Scheduler to process the email queue.
    // On local sites this is more reliable than /?doing_wp_cron.
    await page.goto(`${baseUrl}/wp-admin/`, { waitUntil: 'commit', timeout: 10_000 })
      .catch(() => {/* best effort */});

    // Email
    const email = await getLatestEmail(testEmail, page);
    expect(email.Subject.toLowerCase()).toContain('confirmed');
    expect(email.HTML).toMatch(/BK[\d-]/);
    expect(email.HTML.toLowerCase()).toContain('cancel');
    expect(email.HTML.toLowerCase()).toContain('reschedule');
    expect(email.HTML.toLowerCase()).toContain('calendar');
  });
});