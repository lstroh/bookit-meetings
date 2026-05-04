import { Page } from '@playwright/test';

// Before running Stripe tests, start webhook listener in a separate terminal:
//   stripe listen --forward-to http://plugin-test-1.local/wp-json/bookit/v1/stripe/webhook

export const STRIPE_TEST_CARD = process.env.STRIPE_TEST_CARD || '4242424242424242';
export const STRIPE_TEST_EXPIRY = process.env.STRIPE_TEST_EXPIRY || '12/30';
export const STRIPE_TEST_CVC = process.env.STRIPE_TEST_CVC || '123';

export async function fillStripeCheckout(page: Page): Promise<void> {
  await page.waitForURL('**/checkout.stripe.com/**', { timeout: 30_000 });
  await page.fill('[placeholder*="Card number"]', STRIPE_TEST_CARD);
  await page.fill('[placeholder*="MM / YY"]', STRIPE_TEST_EXPIRY);
  await page.fill('[placeholder*="CVC"]', STRIPE_TEST_CVC);
  await page.click('button[type="submit"]');
  await page.waitForURL('**/booking-confirmed-v2/**', { timeout: 30_000 });
}
