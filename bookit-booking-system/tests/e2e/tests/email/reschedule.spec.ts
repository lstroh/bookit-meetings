import { test, expect, Page } from '@playwright/test';
import { completeWizardSteps1To4 } from '../../fixtures/wizard';
import { getLatestEmail, extractLinkFromEmail, clearMailpit } from '../../fixtures/mailpit';

/**
 * On the reschedule page, find a date that actually has slots available
 * and click the first slot. Retries across up to 3 months.
 *
 * The reschedule calendar marks all non-past days as .bookit-v2-day--available
 * regardless of staff working hours — so we must check the timeslots API
 * response, not just the DOM class, to find a bookable day.
 *
 * Unlike the wizard fixture (which waits for a session POST after each day
 * click), the reschedule page fires only a timeslots GET on day click.
 * We intercept that GET to check availability before touching the DOM.
 */
async function pickRescheduleSlot(page: Page): Promise<void> {
  let slotPicked = false;

  for (let month = 0; month < 3; month++) {
    await page.waitForSelector('.bookit-v2-day--available', { timeout: 15_000 });

    const availableDays = page.locator('.bookit-v2-day--available');
    const dayCount = await availableDays.count();

    for (let i = 0; i < dayCount; i++) {
      const dayBtn = availableDays.nth(i);

      // Register the timeslots response promise BEFORE clicking,
      // so we don't miss the GET that fires immediately on click.
      const timeslotsPromise = page
        .waitForResponse(
          (r) =>
            r.url().includes('/wizard/timeslots') && r.request().method() === 'GET',
          { timeout: 10_000 }
        )
        .catch(() => null);

      await dayBtn.click();

      const timeslotsResponse = await timeslotsPromise;
      if (!timeslotsResponse) continue; // GET timed out — try next day

      const timeslotsJson: any = await timeslotsResponse.json().catch(() => null);
      if (!timeslotsJson?.success || !timeslotsJson?.available) continue; // no slots

      // Slots are now in the DOM — click the first one.
      await page.waitForSelector('.bookit-v2-slot--available', { timeout: 5_000 });
      await page.locator('.bookit-v2-slot--available').first().click();
      slotPicked = true;
      break;
    }

    if (slotPicked) break;

    // No slots found this month — click the next-month button and rebuild.
    // The reschedule calendar uses JS to rebuild the grid on next/prev click
    // (class-shortcodes.php buildGrid() function), so wait for the grid to
    // update rather than a navigation event.
    const nextBtn = page.locator('#bookit-reschedule-next-month');
    if (await nextBtn.isVisible()) {
      await nextBtn.click();
      await page.waitForTimeout(500); // allow JS grid rebuild
    } else {
      break; // no next-month button — can't advance
    }
  }

  if (!slotPicked) {
    throw new Error(
      'pickRescheduleSlot: no bookable slot found in 3 months. ' +
        'Ensure the test staff member has working hours configured.'
    );
  }
}

test.describe('Reschedule email content', { tag: '@full' }, () => {
  test('reschedule email has correct subject and action links', async ({ page }) => {
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
    const rescheduleUrl = extractLinkFromEmail(confirmEmail.HTML, 'Reschedule');

    await clearMailpit();
    await page.goto(rescheduleUrl);
    await page.waitForLoadState('networkidle', { timeout: 15_000 }).catch(() => {});

    await pickRescheduleSlot(page);
    const confirmBtn = page.locator('#bookit-reschedule-confirm');
    if (await confirmBtn.isVisible()) {
      await expect(confirmBtn).toBeEnabled({ timeout: 10_000 });
      await confirmBtn.click();
    }

    const rescheduleEmail = await getLatestEmail(testEmail, page);
    expect(rescheduleEmail.Subject.toLowerCase()).toContain('reschedul');
    expect(rescheduleEmail.HTML.toLowerCase()).toContain('cancel');
    expect(rescheduleEmail.HTML.toLowerCase()).toContain('reschedule');
  });
});
