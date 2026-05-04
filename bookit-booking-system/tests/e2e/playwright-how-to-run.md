# Playwright E2E Tests — How to Run

**Location:** `bookit-booking-system/tests/e2e/`
**Live site:** `https://test.wimbledonsmart.co.uk`
**Local site:** `http://plugin-test-1.local`

---

## Two modes

| Mode | Site | Email | Stripe | Duration |
|------|------|-------|--------|----------|
| `smoke` | Live | ❌ Not needed | ❌ Not needed | ~2 min |
| `full` | Local | ✅ Mailpit required | ✅ Stripe CLI required | ~15 min |

---

## Before running

### Smoke mode — nothing extra needed
Just make sure you have internet access to the live site.

### Full mode — start these first

**Terminal 1 — Local by Flywheel**
Make sure the local site is running in the Local app.

**Terminal 2 — Mailpit**
```powershell
mailpit.exe
```
Verify it's running: open `http://localhost:8025` in your browser.

**Terminal 3 — Stripe CLI**
```powershell
stripe listen --forward-to http://plugin-test-1.local/wp-json/bookit/v1/stripe/webhook
```

---

## Commands

Always run from the e2e folder:
```powershell
cd bookit-booking-system/tests/e2e
```

### Smoke tests (live site)
```powershell
# All smoke tests
npm run test:smoke

# One specific file
npx cross-env MODE=smoke npx playwright test tests/smoke/api.spec.ts
```

### Full E2E tests (local site)
```powershell
# All full tests
npm run test:full

# One specific file
npx cross-env MODE=full npx playwright test tests/full/booking-poa.spec.ts
```

### Useful options
```powershell
# Show the browser while tests run (good for debugging)
npx cross-env MODE=full npx playwright test --headed

# Interactive UI — step through tests one at a time (best for debugging)
npx cross-env MODE=full npx playwright test --ui

# View the HTML report from the last run
npx playwright show-report
```

---

## Test files

```
tests/
├── smoke/                        # Run against live site
│   ├── pages.spec.ts             # Page load checks (/book-v2/, /bookit-cancel/, etc.)
│   ├── api.spec.ts               # REST API health checks
│   ├── auth.spec.ts              # Dashboard login (valid / invalid credentials)
│   └── wizard-steps.spec.ts      # Wizard Step 1 and Step 2 render correctly
├── full/                         # Run against local site
│   ├── booking-poa.spec.ts       # Full wizard → Pay on Arrival → email in Mailpit
│   ├── booking-stripe.spec.ts    # Full wizard → Stripe payment → webhook → email
│   ├── magic-link.spec.ts        # Cancel and reschedule via email magic links
│   └── dashboard.spec.ts         # Admin and staff dashboard flows
└── email/                        # Email content verification via Mailpit
    ├── confirmation.spec.ts      # Subject, booking ref, Cancel/Reschedule links
    ├── cancellation.spec.ts      # Subject, service name
    └── reschedule.spec.ts        # Subject, updated date/time, action links
```

---

## Environment files

Fill in `FILL_IN` values before running full mode:

**`.env.test.local`** — used by full mode
```
BOOKIT_TEST_ADMIN_PASSWORD=...
BOOKIT_TEST_STAFF_PASSWORD=...
BOOKIT_TEST_SERVICE_NAME=...
BOOKIT_TEST_STAFF_NAME=...
```

**`.env.test.live`** — used by smoke mode
```
BOOKIT_TEST_ADMIN_PASSWORD=...
BOOKIT_TEST_STAFF_PASSWORD=...
```

Both files are gitignored — never commit them.

---

## GitHub Actions (automatic)

Smoke tests run automatically on every push to the `Phase1` branch.
Results are uploaded as an artifact in the Actions tab.

Required secrets (Settings → Secrets → Actions):
- `BOOKIT_TEST_ADMIN_EMAIL`
- `BOOKIT_TEST_ADMIN_PASSWORD`
- `BOOKIT_TEST_STAFF_EMAIL`
- `BOOKIT_TEST_STAFF_PASSWORD`

---

## Known caveats

- **Single-service sites:** If only one service exists, Step 1 auto-advances to Step 2 and the service card smoke test may not find `.bookit-v2-service-card`. This is expected behaviour, not a bug.
- **Stripe card radio missing:** If online payment is disabled for the configured service, `input[value="card"]` won't exist in Step 5. The Stripe spec assumes card payment is enabled.
- **Rate limiting:** Booking creation is limited to 10/hour/IP. If full mode tests fail with 429 errors, wait an hour or run from a different IP.
- **Workers = 1:** Tests run sequentially by design. The booking system has shared slot state that causes failures when tests run in parallel.
