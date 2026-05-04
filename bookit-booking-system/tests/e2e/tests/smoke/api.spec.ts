import { test, expect } from '@playwright/test';

// NOTE: /wizard/services and /wizard/staff are documented in
// Bookit_REST_API_Reference_Phase1.md but are not registered as
// REST routes in Phase 1. They are served via PHP template rendering
// (class-shortcodes.php), not the REST API. Smoke tests use the
// actual registered routes instead.
//
// Verified: `rg "wizard/services|wizard/staff" bookit-booking-system/includes --glob "*.php"`
// returns no matches (no register_rest_route entries). Registered wizard routes
// live in includes/api/class-wizard-api.php and includes/api/class-datetime-api.php
// (e.g. /wizard/session, /wizard/timeslots, /wizard/cancel). POST
// /bookit/v1/dashboard/login is not registered; dashboard login is the rewrite
// target dashboard/index.php, not REST.

test.describe('REST API health', { tag: '@smoke' }, () => {
  test('GET wizard/session returns 200 with session payload', async ({ request }) => {
    const res = await request.get('/wp-json/bookit/v1/wizard/session');
    expect(res.status()).toBe(200);
    const body = await res.json();
    expect(body.success).toBe(true);
    expect(body.data).toBeDefined();
  });

  test('GET wizard/timeslots without service returns 400 no_service', async ({ request }) => {
    const d = new Date();
    d.setDate(d.getDate() + 21);
    const date = d.toISOString().slice(0, 10);
    const res = await request.get(`/wp-json/bookit/v1/wizard/timeslots?date=${date}`);
    expect(res.status()).toBe(400);
    const body = await res.json();
    expect(body.code).toBe('no_service');
  });

  test('POST wizard/cancel with empty body returns 4xx not 500', async ({ request }) => {
    const res = await request.post('/wp-json/bookit/v1/wizard/cancel', {
      headers: { 'Content-Type': 'application/json' },
      data: {},
    });
    expect(res.status()).not.toBe(500);
    expect(res.status()).toBeGreaterThanOrEqual(400);
    expect(res.status()).toBeLessThan(500);
  });
});
