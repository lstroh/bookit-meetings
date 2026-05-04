import { test, expect } from '@playwright/test';

test.describe('Page load checks', { tag: '@smoke' }, () => {
  test('booking wizard loads at /book-v2/', async ({ page }) => {
    await page.goto('/book-v2/');
    // Shell renders .bookit-v2-wizard-container[data-step] — confirms no PHP fatal
    await expect(page.locator('.bookit-v2-wizard-container[data-step]')).toBeVisible();
  });

  test('/booking-confirmed-v2/ loads without params (no 500)', async ({ page }) => {
    await page.goto('/booking-confirmed-v2/');
    // Should render some content, not a PHP error page
    await expect(page).not.toHaveTitle(/error/i);
    await expect(page.locator('body')).not.toContainText('Fatal error');
  });

  test('/bookit-cancel/ shows invalid link message without token', async ({ page }) => {
    await page.goto('/bookit-cancel/');
    await expect(page.locator('body')).not.toContainText('Fatal error');
    // Should show an error/invalid state, not crash
    await expect(page.locator('body')).toBeVisible();
  });

  test('/bookit-reschedule/ shows invalid link message without token', async ({ page }) => {
    await page.goto('/bookit-reschedule/');
    await expect(page.locator('body')).not.toContainText('Fatal error');
    await expect(page.locator('body')).toBeVisible();
  });

  test('/my-packages/ loads', async ({ page }) => {
    await page.goto('/my-packages/');
    await expect(page).not.toHaveTitle(/error/i);
    await expect(page.locator('body')).not.toContainText('Fatal error');
  });
});
