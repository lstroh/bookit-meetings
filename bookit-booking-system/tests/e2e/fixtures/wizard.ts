import { Page } from '@playwright/test';
import { clearMailpit } from './mailpit';

const TEST_EMAIL = process.env.BOOKIT_TEST_CUSTOMER_EMAIL || 'testcustomer@bookit-e2e.local';

/**
 * Complete the booking wizard Steps 1–4.
 *
 * Selects service and staff by name from env vars:
 *   BOOKIT_TEST_SERVICE_NAME — exact name of service card to select
 *   BOOKIT_TEST_STAFF_NAME   — exact name of staff member to select
 *
 * KEY BEHAVIOURS:
 * - Step 2: clicking a staff row calls bookit/v1/staff/select which triggers
 *   window.location.reload() on success — no Continue click needed or wanted.
 * - Step 3: month nav arrows are <a href> links causing full page loads —
 *   must use waitForNavigation alongside the click.
 */
export async function completeWizardSteps1To4(page: Page): Promise<string> {
  await clearMailpit();
  await page.goto('/book-v2/');

  // -----------------------------------------------------------------------
  // Step 1: Select service by name
  // -----------------------------------------------------------------------
  const serviceName = process.env.BOOKIT_TEST_SERVICE_NAME;
  if (!serviceName) {
    throw new Error('BOOKIT_TEST_SERVICE_NAME is not set in .env.test.local');
  }

  await page.waitForSelector('.bookit-v2-service-card');

  const serviceCard = page.locator(
    `.bookit-v2-service-card[data-service-name="${serviceName}"]`
  );
  if ((await serviceCard.count()) === 0) {
    throw new Error(
      `Service card not found for BOOKIT_TEST_SERVICE_NAME="${serviceName}". ` +
      `Check the service exists and is active on the local site.`
    );
  }
  await serviceCard.first().click();

  // Wait for Continue to be enabled, then click it (Step 1 does need Continue)
  await page.waitForFunction(() => {
    const btn = document.querySelector<HTMLButtonElement>('#bookit-v2-continue');
    return btn !== null && !btn.disabled;
  });
  await page.locator('#bookit-v2-continue').click();

  // -----------------------------------------------------------------------
  // Step 2: Select staff by name — page reloads automatically on success
  // DO NOT click Continue after staff selection
  // -----------------------------------------------------------------------
  const staffName = process.env.BOOKIT_TEST_STAFF_NAME;
  if (!staffName) {
    throw new Error('BOOKIT_TEST_STAFF_NAME is not set in .env.test.local');
  }

  await page.waitForSelector('.bookit-v2-staff-row, .bookit-v2-staff-card');

  const staffRow = page
    .locator(
      '.bookit-v2-staff-row:not(.bookit-v2-staff-row--unavailable), ' +
        '.bookit-v2-staff-card:not(.bookit-v2-staff-card--unavailable)'
    )
    .filter({ hasText: staffName });

  if ((await staffRow.count()) === 0) {
    throw new Error(
      `Staff row not found for BOOKIT_TEST_STAFF_NAME="${staffName}". ` +
      `Check the staff member exists, is active, and is assigned to ` +
      `service "${serviceName}" on the local site.`
    );
  }

  // Click staff row and wait for the automatic page reload to Step 3.
  // booking-wizard-v2.js calls window.location.reload() after staff/select succeeds.
  await Promise.all([
    page.waitForNavigation({ waitUntil: 'load', timeout: 20_000 }),
    staffRow.first().click(),
  ]);
  // Page is now on Step 3. Do NOT click Continue here.

  // -----------------------------------------------------------------------
  // Step 3: Find an available day, pick a slot, confirm session write
  // Uses waitForResponse on the slot POST — avoids cookie rotation race
  // -----------------------------------------------------------------------
  await page.waitForSelector('.bookit-v2-calendar', { timeout: 15_000 });

  let slotPicked = false;

  for (let month = 0; month < 3; month++) {
    const availableDays = page.locator('.bookit-v2-day--available');
    const dayCount = await availableDays.count();

    for (let i = 0; i < Math.min(dayCount, 8); i++) {
      const dayBtn = availableDays.nth(i);

      // Skip today — same-day bookings may be past the cancellation window
      // and cause cancellation/reschedule tests to fail.
      // Always book at least one day ahead for reliable test behaviour.
      const dayDate = await dayBtn.getAttribute('data-date');
      const today = new Date().toISOString().split('T')[0];
      if (dayDate === today) {
        continue;
      }

      // Wait for the timeslots GET that fires automatically after the
      // session POST resolves client-side. This guarantees slots in the
      // DOM are for the currently selected day — not stale slots from
      // a previous day selection.
      //
      // IMPORTANT: set up waitForResponse BEFORE the session POST
      // resolves, so we don't miss the timeslots GET firing in the
      // JS .then() callback. Use a separate promise registered before
      // the day click resolves.
      const timeslotsPromise = page
        .waitForResponse(
          (r) =>
            r.url().includes('/wizard/timeslots') && r.request().method() === 'GET',
          { timeout: 10_000 }
        )
        .catch(() => null);

      // Click the day and wait for the day's session POST to complete.
      // The day click posts {current_step:3, date:X} to the session API.
      const [dayResponse] = await Promise.all([
        page.waitForResponse(
          (r) =>
            r.url().includes('/wp-json/bookit/v1/wizard/session') &&
            r.request().method() === 'POST',
          { timeout: 10_000 }
        ),
        dayBtn.click(),
      ]);

      // Check the day POST succeeded
      const dayJson = await dayResponse.json().catch(() => null);
      if (!dayJson?.success) {
        // Day POST failed — try the next day
        continue;
      }

      // The timeslots GET fires automatically after dayResponse — no
      // additional click needed. Just await the promise.
      const timeslotsResponse = await timeslotsPromise;
      if (!timeslotsResponse) {
        // Timeslots fetch timed out — try next day
        continue;
      }

      const timeslotsJson = await timeslotsResponse.json().catch(() => null);
      if (!timeslotsJson?.success || !timeslotsJson?.available) {
        // No slots available on this day
        continue;
      }

      // Slots are now guaranteed to be in the DOM for the selected day.
      // Click the first available slot and wait for its session POST.
      const [slotResponse] = await Promise.all([
        page.waitForResponse(
          (r) =>
            r.url().includes('/wp-json/bookit/v1/wizard/session') &&
            r.request().method() === 'POST',
          { timeout: 10_000 }
        ),
        page.locator('.bookit-v2-slot--available').first().click(),
      ]);

      const slotJson = await slotResponse.json().catch(() => null);
      if (!slotJson?.success) {
        // Slot POST failed — try next day
        continue;
      }

      slotPicked = true;
      break;
    }

    if (slotPicked) break;

    // No slots found this month — navigate to next month via <a href> link
    if (month < 2) {
      await Promise.all([
        page.waitForNavigation({ waitUntil: 'load', timeout: 15_000 }),
        page.locator('.bookit-v2-calendar-nav').last().click(),
      ]);
      await page.waitForSelector('.bookit-v2-calendar', { timeout: 10_000 });
    }
  }

  if (!slotPicked) {
    throw new Error(
      `No bookable time slots found in the next 3 months for ` +
      `staff "${staffName}" / service "${serviceName}". ` +
      `Go to Dashboard → Staff → ${staffName} → Working Hours and confirm ` +
      `availability is configured and the service is assigned to this staff member.`
    );
  }

  // Wait for Continue button to be enabled (JS enables it in the slot POST .then())
  await page.waitForFunction(
    () => {
      const btn = document.querySelector<HTMLButtonElement>('#bookit-v2-continue');
      return btn !== null && !btn.disabled;
    },
    { timeout: 10_000 }
  );

  await page.locator('#bookit-v2-continue').click();

  // -----------------------------------------------------------------------
  // Step 4: Fill contact form
  // -----------------------------------------------------------------------
  await page.waitForSelector('#bookit-contact-form', { timeout: 15_000 });
  await page.fill('#first-name', 'Test');
  await page.fill('#last-name', 'Bookit');
  await page.fill('#email', TEST_EMAIL);
  await page.fill('#phone', '07700900000');

  // Check cooling-off waiver if visible (only shown for near-term bookings)
  if (await page.locator('#cooling-off-waiver-group').isVisible()) {
    await page.check('#cooling-off-waiver');
  }

  // Submit Step 4 — triggers session save and advances to Step 5
  await page.locator(
    '#bookit-contact-form button[type="submit"].bookit-v2-cta-btn'
  ).click();

  // Wait for Step 5 CTA to confirm we've advanced
  await page.waitForSelector('#bookit-v2-cta-btn', { timeout: 15_000 });

  return TEST_EMAIL;
}
