# Bookit — Setup Guide

This guide covers installing, configuring, and deploying Bookit on a client WordPress site. It assumes Hostinger shared hosting with LiteSpeed, and documents every non-obvious decision and gotcha encountered during development.

---

## 1. Prerequisites

**Server:**
- WordPress 6.0+
- PHP 8.0+ (8.2 recommended)
- MySQL 5.7+ or MariaDB 10.3+
- LiteSpeed web server (Hostinger shared hosting)
- HTTPS enabled (required for Stripe, Google Calendar OAuth)

**Accounts to have ready before starting:**
- Hostinger account with the client site active
- Brevo account (free plan: 300 emails/day — sufficient for most small clients)
- Stripe account (you'll start in test mode; switch to live at go-live)
- Google Cloud Console project (one per client — see §8)

**Local build tools:**
- Node.js 18+
- Composer 2.0+

---

## 2. Building Locally Before Deployment

The plugin ships without `vendor/` or `dist/` — both are gitignored and must be built locally before every deployment.

### PHP dependencies

From inside `bookit-booking-system/`:

```bash
composer install --no-dev --optimize-autoloader
```

### Vue dashboard

From inside `bookit-booking-system/dashboard/`:

```bash
npm run build
```

This generates `dist/` including `dist/.vite/manifest.json`. The manifest is critical — PHP reads it at runtime to resolve the current hashed JS and CSS filenames. **Never add `?v=` query strings to the entry JS file.** Because Vite uses `base: './'` for relative chunk imports, adding a query string to the entry causes a double Vue mount crash (the browser treats it as a different module and loads it twice). Cache-busting is handled entirely by the content-hashed filename in the manifest.

### Creating the zip

Zip the entire `bookit-booking-system/` folder including the freshly built `vendor/` and `dist/`:

```bash
cd ..
zip -r bookit-booking-system.zip bookit-booking-system/
```

---

## 3. Initial Deployment

In the WordPress admin: **Plugins → Add New → Upload Plugin → choose the zip → Install Now → Activate**.

On activation, the plugin automatically:
- Runs all pending DB migrations
- Seeds default settings
- Creates the following pages if they do not already exist:

| Page slug | Shortcode |
|---|---|
| `/book-v2/` | `[bookit_wizard_v2]` |
| `/booking-confirmed-v2/` | `[bookit_booking_confirmed_v2]` |
| `/bookit-cancel/` | `[bookit_cancel_booking]` |
| `/bookit-reschedule/` | `[bookit_reschedule_booking]` |
| `/my-packages/` | `[bookit_my_packages]` |
| `/bookit-email-changed/` | `[bookit_email_changed]` |

**Post-activation checklist:**
- Confirm all six pages exist in WordPress → Pages
- Confirm no PHP errors in Hostinger hPanel → File Manager → `error_log`
- Confirm the dashboard is accessible at `/bookit-dashboard/`

---

## 4. LiteSpeed Cache Configuration

**Do this before anything else.** Without these exclusions, the dashboard login loops endlessly, booking wizard sessions fail, and REST API responses are served stale from cache.

In the WordPress admin, go to **LiteSpeed Cache → Cache → Excludes**.

Under **Do Not Cache URIs**, add each of the following on its own line:

```
/bookit-dashboard/
/bookit-dashboard/app/
/bookit-dashboard/setup/
/bookit-dashboard/logout/
/book-v2/
/booking-confirmed-v2/
/my-packages/
/bookit-cancel/
/bookit-reschedule/
/bookit-email-changed/
/wp-json/bookit/
```

Also go to **LiteSpeed Cache → Page Optimization → JS Settings** and disable **JS Minify** and **JS Combine**. These break the Vite-built dashboard JS.

### Cache purge after deployments

After uploading a new version of the plugin, purge all three cache layers in this order:

1. **LiteSpeed Cache plugin** — WordPress admin → LiteSpeed Cache → Manage → Purge All
2. **Hostinger server cache** — hPanel → Hosting → Manage → Cache Manager → Purge All
3. **Hostinger CDN cache** — hPanel → CDN → Purge Cache

The CDN is the most persistent layer and is the most common culprit when you see stale JS after a deployment. Always purge all three. Verify in an incognito window with DevTools open and the Network tab's "Disable cache" checkbox ticked.

> **Note on the Vite manifest:** As of Sprint 6D, frontend JS and CSS filenames include a content hash (e.g. `index.DuvrpLnL.js`). The browser and CDN will automatically fetch fresh assets when the hash changes — so the three-layer purge is mainly needed for PHP template or plugin file changes, not for every Vue rebuild.

---

## 5. First-Run Setup Wizard

Navigate to `/bookit-dashboard/setup/`. This creates the first admin account and sets the business name and contact details. After completion it redirects to `/bookit-dashboard/app/`.

The setup wizard runs once and will not reappear after completion.

---

## 6. Email Configuration (Brevo)

### Brevo account setup

1. Create a Brevo account at brevo.com.
2. Add and verify the client's sender domain — Brevo will give you SPF and DKIM DNS records to add. Both must be in place before transactional email will deliver reliably.
3. Generate an API key: **Brevo dashboard → Settings → API Keys → Create API Key**.

### Plugin configuration

In Bookit Dashboard → Settings → Email:

- **Provider:** Brevo
- **API Key:** paste the key (stored encrypted; displayed as `SAVED` after saving)
- **From Name:** client business name (what recipients see in their inbox)
- **From Email:** the verified sender address

Send a test email from the settings page and confirm delivery before proceeding.

### Brevo SDK note

Bookit uses `getbrevo/brevo-php` v4. The v4 SDK is a full rewrite — the old `Brevo\Client\*` namespace no longer exists. The entry point is `\Brevo\Brevo`. If you ever need to debug or extend the email provider class, read `vendor/composer/autoload_classmap.php` directly — online docs including Context7 are stale for v4.

### Template IDs (optional)

Brevo template IDs can be configured in Settings → Email → Brevo Email Templates. If left blank, Bookit sends pre-rendered HTML emails (fully functional for launch). Template IDs only need to be set if you want to use Brevo-designed templates with dynamic variable substitution.

The available template variable params passed to Brevo for staff notification emails are: `service_name`, `booking_date`, `start_time`, `customer_first`, `customer_last`, `customer_phone`, `booking_reference`, `dashboard_url`, `preferences_url`.

---

## 7. Stripe Configuration

In Bookit Dashboard → Settings → Payments:

1. Enter the test **Publishable Key** and **Secret Key**.
2. Register a webhook endpoint in the Stripe dashboard:
   - **Endpoint URL:** `https://clientdomain.com/wp-json/bookit/v1/stripe/webhook`
   - **Events to listen for:** `checkout.session.completed`, `charge.refunded`
3. Copy the **Webhook Signing Secret** and paste it into the plugin settings.

Test the full flow using Stripe's test card:
```
Card number: 4242 4242 4242 4242
Expiry: any future date
CVC: any 3 digits
```

**Important:** Stripe config is read directly from the `wp_bookings_settings` database table via `$wpdb->get_var()` — not via `get_option()`. This is intentional. If Stripe appears unconfigured after saving keys, check that the keys are present in `wp_bookings_settings`, not `wp_options`.

### Switching to live at go-live

Replace the test publishable and secret keys with live keys, register a new live webhook endpoint in the Stripe dashboard (same URL, live mode), paste the new webhook signing secret, and flip the test/live mode toggle. This takes about five minutes.

---

## 8. Google Calendar OAuth Setup (per client)

Each client needs their own Google Cloud project because OAuth redirect URIs are domain-specific.

### Step-by-step

1. Go to [console.cloud.google.com](https://console.cloud.google.com) → **New Project** → name it (e.g. "ClientName Bookit").
2. **Enable the Google Calendar API** for the project.
3. Go to **APIs & Services → OAuth consent screen**:
   - User type: **External**
   - App name: client business name
   - Authorised domain: client domain (e.g. `clientdomain.co.uk`)
   - Scopes: add `https://www.googleapis.com/auth/calendar.events`, `openid`, and `email`
4. Under **Test Users**, add every staff member's Gmail address before they try to connect.

   > **This step is critical.** If a staff member's Gmail is not in the Test Users list, they will see "Access blocked: this app has not completed Google's verification process" when they try to connect. The fix is to add their Gmail here and ask them to try again. The app stays in Testing mode permanently for small client installs — this is fine, up to 100 test users are supported.

5. Go to **APIs & Services → Credentials → Create Credentials → OAuth 2.0 Client ID**:
   - Application type: **Web application**
   - Authorised redirect URI: `https://clientdomain.com/wp-json/bookit/v1/google-calendar/callback`

   The redirect URI must match exactly — correct subdomain, `https`, no trailing slash. A mismatch causes a `redirect_uri_mismatch` error.

6. Copy the **Client ID** and **Client Secret**.

### Plugin configuration

In Bookit Dashboard → Settings → Integrations:

- **Google Client ID:** paste (displayed in plain text)
- **Google Client Secret:** paste (stored encrypted; displayed as `SAVED` after saving)
- **Fallback calendar:** when enabled, bookings where the assigned staff member has no Google connection will sync to the first connected admin calendar instead.

Store the Client Secret in a password manager. Once it is saved in the plugin settings it is masked and cannot be retrieved from the UI.

### Staff connecting their calendars

Each staff member connects their own Google account individually via **My Profile → Google Calendar → Connect**. The connection is per-staff — each staff member's OAuth tokens are stored separately and encrypted at rest. Token compromise affects only one staff member.

### Admin disconnecting a staff member

An admin can disconnect any staff member's Google Calendar from the staff edit modal in the dashboard.

---

## 9. Configuring Services and Staff

**Services first, then staff.**

- Create service categories (e.g. "Hair", "Nails") — services are grouped into them on the booking page.
- Create services: name, duration (minutes), price, buffer time, deposit (none / fixed / percentage).
- Create staff members: name, email (used for dashboard login and notifications), phone, role (Admin or Staff).
- Assign each staff member to the services they offer. Staff only appear in the booking wizard for their assigned services.
- Set working hours per staff member. Split shifts are supported — add multiple time blocks per day.

---

## 10. Deploying Updates

1. Build locally (both `composer install --no-dev --optimize-autoloader` and `npm run build`).
2. Create a fresh zip of the plugin folder.
3. In WordPress admin: **Plugins → Deactivate Bookit → Delete → Upload Plugin → Install → Activate**.
4. Purge all three cache layers (LiteSpeed → Hostinger server → Hostinger CDN).

Migrations run automatically on activation. The migration runner checks `wp_bookit_migrations` to determine which migrations have already run and skips them — re-activating is safe.

> **Caution on `dist/`:** WordPress plugin reinstall preserves existing files on disk when uploaded chunk filenames are unchanged. Thanks to the Vite manifest hash, JS/CSS filenames change whenever their content changes, so this is generally not an issue. However, if you ever suspect stale assets are being served despite a purge, verify via Hostinger File Manager that `dist/.vite/manifest.json` contains the hash you expect from your local build.

---

## 11. Known Technical Gotchas

These are all non-obvious and each has cost real debugging time. Read before raising a Cursor prompt for any related task.

**Stripe config reads from `wp_bookings_settings`, not `get_option()`.**
The Stripe config class queries the custom settings table directly via `$wpdb->get_var()`. If Stripe appears unconfigured despite keys being saved, look here first.

**`applicable_service_ids` filtering must use PHP, not SQL `JSON_CONTAINS()`.**
Always decode with `json_decode()` and filter with `in_array()`. `JSON_CONTAINS()` is incompatible with MariaDB 11.4 and will silently misbehave.

**`get_full_booking()` in lifecycle hook callbacks must NOT filter `deleted_at IS NULL`.**
The cancellation hook fires after the booking has been soft-deleted. If the query excludes soft-deleted rows, the notifier receives `null` and silently does nothing. This was the root cause of staff/admin not receiving cancellation notifications.

**Shortcode handlers must not return `<script>` blocks.**
Output scripts via a `wp_footer` action instead. WordPress's `the_content` pipeline encodes `&&` as `&#038;` in the shortcode return value regardless of the `no_texturize_shortcodes` filter. This broke the reschedule page month navigation and was the hardest bug to diagnose in Phase 1.

**`wp_bookings_staff.id` is the primary key — there is no `staff_id` column.**
Always use `WHERE id = %d` when querying the staff table.

**Column existence checks in migrations must use `information_schema.COLUMNS`.**
Do not use `SHOW COLUMNS LIKE 'column_name'`. MariaDB treats the underscore in `LIKE` as a single-character wildcard, causing false positives. Use an exact match against `information_schema.COLUMNS` instead.

**`wp_enqueue_media()` must not be called at dashboard app page boot.**
It should only be loaded lazily when staff photo upload is actually needed. Calling it at boot time causes conflicts with the Vue SPA.

**Do not apply `sanitize_text_field()` to base64 OAuth state params.**
This function silently strips `+`, `/`, and `=` characters, which corrupts the base64 string and breaks the OAuth callback. Use `sanitize_key()` or no sanitisation for state params.

**Action Scheduler callbacks with multiple args must use positional arrays.**
When calling `as_schedule_single_action()` with 3+ arguments, pass a positional array (not associative). AS and WP-Cron handle arg passing differently; positional is the safe choice.

**Vite `base: './'` + query strings = double Vue mount.**
Never add `?v=` or any version query string to `dist/index.[hash].js`. The hash in the filename is the cache-busting mechanism. Adding a query string causes the browser to treat it as a different module entry point and mount Vue twice.

---

## 12. Monitoring and Maintenance

**Email queue log:**
Dashboard → Reports → Email Queue. Shows every email that has been sent, failed, or is pending. Check this after go-live and after any deployment that touches email code. Columns include status, attempts, scheduled time, sent time, and the last error message (truncated; full text in the title attribute on hover).

**Database migrations:**
Tracked in `wp_bookit_migrations`. Each migration records its filename and the timestamp it ran. Re-activating the plugin will not re-run completed migrations.

**Action Scheduler jobs:**
Email queue processing and Google Calendar sync both run as Action Scheduler background jobs. Inspect them at WordPress admin → Tools → Scheduled Actions.

**PHP error log:**
Hostinger hPanel → File Manager → look for `error_log` in the site root or `public_html/`. Check this whenever something behaves unexpectedly — the plugin logs extensively.

**Backups:**
Hostinger daily backup is enabled by default. Confirm the retention period with the client and recommend at least 30 days.

---

## 13. Pre-Launch Checklist

- [ ] Plugin activated, no PHP errors in `error_log`
- [ ] All six pages auto-created and verified in WordPress → Pages
- [ ] LiteSpeed cache exclusions added (all URIs in §4)
- [ ] LiteSpeed JS Minify and Combine disabled
- [ ] Brevo configured — sender domain SPF/DKIM verified, test email received in inbox (not spam)
- [ ] Stripe in test mode — full end-to-end booking tested with card `4242 4242 4242 4242`
- [ ] At least one service and one staff member created with working hours set
- [ ] Google Calendar configured (if client wants it) — staff test connection confirmed
- [ ] All staff Gmail addresses added to Google Cloud Console Test Users list
- [ ] Stripe switched to live mode — live keys entered, live webhook registered and tested
- [ ] Full end-to-end live booking tested (real card, create a £0 or low-value test service)
- [ ] Privacy Policy published on client site and linked in footer
- [ ] Terms & Conditions published on client site and linked in footer
- [ ] ICO registration confirmed for client (data controller registration at ico.org.uk)
- [ ] Three-layer cache purge done after final deployment
- [ ] Accessibility Statement scheduled (required within 30 days of go-live for public sector clients; good practice for all)
