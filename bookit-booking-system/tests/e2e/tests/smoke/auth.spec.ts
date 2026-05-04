import { test, expect } from '@playwright/test';

// Selectors from dashboard/index.php:
//   input[name="email"], input[name="password"], button.booking-login-button
//   Error: .booking-login-error
//   Success redirect: /bookit-dashboard/app/

test.describe('Dashboard auth', { tag: '@smoke' }, () => {
  test('dashboard redirects to login when not authenticated', async ({ page }) => {
    await page.goto('/bookit-dashboard/');
    // Should show login form, not the Vue app
    await expect(page.locator('input[name="email"]')).toBeVisible();
    await expect(page.locator('input[name="password"]')).toBeVisible();
  });

  test('valid credentials reach the dashboard app', async ({ page }) => {
    await page.goto('/bookit-dashboard/');
    await page.fill('input[name="email"]', process.env.BOOKIT_TEST_ADMIN_EMAIL!);
    await page.fill('input[name="password"]', process.env.BOOKIT_TEST_ADMIN_PASSWORD!);
    await page.click('button.booking-login-button');
    await page.waitForURL('**/bookit-dashboard/app/**');
    // Confirm Vue app loaded (not login page)
    await expect(page.locator('input[name="email"]')).not.toBeVisible();
  });

  test('invalid credentials show error and stay on login', async ({ page }) => {
    await page.goto('/bookit-dashboard/');
    await page.fill('input[name="email"]', 'wrong@example.com');
    await page.fill('input[name="password"]', 'wrongpassword');
    await page.click('button.booking-login-button');
    // Must not redirect to app
    await expect(page).not.toHaveURL(/bookit-dashboard\/app/);
    // Error message shown (.booking-login-error from dashboard/css/dashboard-auth.css)
    await expect(page.locator('.booking-login-error')).toBeVisible();
  });
});
