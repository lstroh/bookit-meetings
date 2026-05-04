import { test, expect } from '@playwright/test';

// Selectors from booking-wizard-v2-step-1.php and booking-wizard-v2-step-2.php

test.describe('Wizard step rendering', { tag: '@smoke' }, () => {
  test('Step 1 renders at least one service card', async ({ page }) => {
    await page.goto('/book-v2/');
    await expect(page.locator('.bookit-v2-service-card').first()).toBeVisible();
  });

  test('Step 2 renders staff after service selected and Continue clicked', async ({ page }) => {
    await page.goto('/book-v2/');
    await page.waitForSelector('.bookit-v2-service-card');
    await page.locator('.bookit-v2-service-card').first().click();
    await page.locator('#bookit-v2-continue').click();
    // Step 2: staff row or card visible
    await expect(page.locator('.bookit-v2-staff-row, .bookit-v2-staff-card').first()).toBeVisible({
      timeout: 10_000,
    });
  });
});
