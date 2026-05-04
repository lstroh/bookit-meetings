import { Page } from '@playwright/test';

// Selectors from dashboard/index.php:
//   input[name="email"], input[name="password"], button.booking-login-button
// Error message selector: .booking-login-error
// Success: redirects to /bookit-dashboard/app/

export async function loginAsAdmin(page: Page): Promise<void> {
  await page.goto('/bookit-dashboard/');
  await page.fill('input[name="email"]', process.env.BOOKIT_TEST_ADMIN_EMAIL!);
  await page.fill('input[name="password"]', process.env.BOOKIT_TEST_ADMIN_PASSWORD!);
  await page.click('button.booking-login-button');
  await page.waitForURL('**/bookit-dashboard/app/**');
}

export async function loginAsStaff(page: Page): Promise<void> {
  await page.goto('/bookit-dashboard/');
  await page.fill('input[name="email"]', process.env.BOOKIT_TEST_STAFF_EMAIL!);
  await page.fill('input[name="password"]', process.env.BOOKIT_TEST_STAFF_PASSWORD!);
  await page.click('button.booking-login-button');
  await page.waitForURL('**/bookit-dashboard/app/**');
}
