import { test, expect } from '@playwright/test';
import { completeWizardSteps1To4 } from '../../fixtures/wizard';
import { fillStripeCheckout } from '../../fixtures/stripe';
import { getLatestEmail } from '../../fixtures/mailpit';

// PREREQUISITE: Run in a separate terminal before this test:
//   stripe listen --forward-to http://plugin-test-1.local/wp-json/bookit/v1/stripe/webhook
//
// Step 5 selectors from booking-wizard-v2-step-5.php:
//   Card radio: input[name="bookit_v2_payment_choice"][value="card"]
//   CTA:        #bookit-v2-cta-btn  (label becomes "Pay £X.XX now" when card selected)

test.describe('Full booking — Stripe card payment', { tag: '@full' }, () => {
  test('completes wizard with Stripe, webhook fires, confirmation email delivered', async ({ page }) => {
    test.skip(true, 'Stripe test — run manually with Stripe CLI. Requires: stripe listen, test keys configured in plugin settings, idempotency table cleared.');
    const testEmail = await completeWizardSteps1To4(page);

    // Step 5: select card payment
    await page.locator('input[name="bookit_v2_payment_choice"][value="card"]').check();
    // CTA label should update to "Pay £X.XX now"
    // CTA triggers: POST /wizard/session then POST /wizard/complete
    // Intercept wizard/complete response to confirm it succeeded
    const [completeResponse] = await Promise.all([
      page.waitForResponse(
        r => r.url().includes('/wizard/complete') && r.request().method() === 'POST',
        { timeout: 15_000 }
      ),
      page.locator('#bookit-v2-cta-btn').click(),
    ]);

    let completeJson: any = null;
    let completeBodyText = '';
    try {
      // Use Playwright's buffered response body (safe even if the page navigates immediately).
      const body = await completeResponse.body();
      completeBodyText = body.toString();
      completeJson = JSON.parse(completeBodyText);
    } catch {
      completeJson = null;
    }

    if (!completeJson?.success) {
      throw new Error(`wizard/complete failed with: ${completeBodyText || JSON.stringify(completeJson)}`);
    }

    // Fill Stripe hosted checkout (headed mode — set in playwright.config.ts for full mode)
    await fillStripeCheckout(page);

    // Confirmation page
    await expect(page.locator('body')).toContainText(/BK[\d-]/);

    // Wait for Stripe CLI webhook to fire and email to send (3s buffer)
    await page.waitForTimeout(3_000);

    const email = await getLatestEmail(testEmail, page);
    expect(email.Subject.toLowerCase()).toContain('confirmed');
    expect(email.HTML).toMatch(/BK[\d-]/);
  });
});
