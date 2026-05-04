# Bookit Extension Plugin API Specification
**Version:** 1.5.1
**Applies to core version:** 1.0.0+
**Last updated:** May 2026

---

## 1. Overview

### What is a Bookit extension plugin?

A Bookit extension plugin is a standard WordPress plugin that integrates with
the Bookit Booking System core plugin to add optional features. Extensions use
a registry-based system to announce themselves to core, add sidebar navigation
to the business dashboard, hook into booking lifecycle events, add their own
REST endpoints, and manage their own database tables.

### What extensions can do

- Register themselves with core and appear in the Active Extensions list
- Add sidebar navigation items to the business dashboard
- Mount standalone Vue 3 pages at their own dashboard routes
- Hook into booking created, updated, cancelled, and payment events
- Modify available time slots and booking data via filters
- Add their own REST API endpoints under their own namespace
- Add and roll back their own database tables via the migration framework
- Log to the Bookit logger

### What extensions cannot do

- Modify core plugin files
- Access internal core PHP classes that are not documented as public API
  (see §9 for the safe list)
- Register routes under the `bookit/v1` REST namespace (use your own namespace)
- Override core authentication or permission logic
- Prevent core from functioning if the extension is deactivated

### Architecture: Option 3 (separate Vue pages)

Each extension mounts its own standalone Vue 3 application at a dedicated
dashboard route. The core sidebar links to extension pages when the extension
is active. Extension pages are completely independent — they do not share
Vue state or components with core, though they can call core REST endpoints
and must follow the same visual design conventions.

---

## 2. Plugin structure

Recommended directory layout for a Bookit extension plugin:
```
bookit-{slug}/
├── bookit-{slug}.php                  # Main plugin file
├── composer.json                      # PHP dependencies (if any)
├── includes/
│   └── class-bookit-{slug}-loader.php # Registers hooks on plugins_loaded
├── database/
│   └── migrations/                    # Numbered migration files
│       └── 0001-add-{slug}-tables.php
├── api/
│   └── class-{slug}-api.php           # REST endpoint registration
└── dashboard/                         # Vue 3 app (optional)
    ├── index.html
    ├── src/
    │   ├── main.js
    │   └── App.vue
    └── dist/                          # Built assets (gitignored in dev)
```

### Main plugin file header
```php
<?php
/**
 * Plugin Name: Bookit {Feature Name}
 * Plugin URI:  https://example.com
 * Description: {Description}
 * Version:     1.0.0
 * Requires at least: 6.0
 * Requires PHP:      8.0
 * Author:      {Author}
 * License:     GPL v2 or later
 * Text Domain: bookit-{slug}
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

define( 'BOOKIT_{SLUG}_VERSION',    '1.0.0' );
define( 'BOOKIT_{SLUG}_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'BOOKIT_{SLUG}_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'BOOKIT_{SLUG}_REQUIRES_CORE', '1.0.0' );
```

---

## 3. Registration

### Registering your extension

Call `bookit_register_extension()` on the `plugins_loaded` hook at priority 5
(before core runs at priority 10). Check the return value — if core is not
active or the version is incompatible, registration will fail and you should
bail out gracefully.
```php
add_action( 'plugins_loaded', function() {

    // Bail if Bookit core is not active.
    if ( ! function_exists( 'bookit_register_extension' ) ) {
        return;
    }

    $result = bookit_register_extension( [
        'name'          => 'Bookit Recurring',
        'slug'          => 'bookit-recurring',
        'version'       => BOOKIT_RECURRING_VERSION,
        'requires_core' => BOOKIT_RECURRING_REQUIRES_CORE,
        'description'   => 'Recurring appointment support for Bookit.',
        'author'        => 'Your Name',
    ] );

    if ( is_wp_error( $result ) ) {
        // Log the error and stop — do not initialise the extension.
        error_log( '[bookit-recurring] Registration failed: ' . $result->get_error_message() );
        return;
    }

    // Registration succeeded — initialise the rest of the extension.
    require_once BOOKIT_RECURRING_PLUGIN_DIR . 'includes/class-bookit-recurring-loader.php';
    ( new Bookit_Recurring_Loader() )->init();

}, 5 );
```

### Required arguments

| Argument | Type | Description |
|---|---|---|
| `name` | string | Display name shown in the Active Extensions list |
| `slug` | string | Unique slug matching your plugin folder name |
| `version` | string | Your extension's version (semver) |
| `requires_core` | string | Minimum Bookit core version required |

### Optional arguments

| Argument | Type | Description |
|---|---|---|
| `description` | string | Short description shown in Active Extensions list |
| `author` | string | Author name |

### Version compatibility

Core compares `requires_core` against `BOOKIT_VERSION` using `version_compare`.
If the installed core version is older than `requires_core`, registration returns
a `WP_Error` with code `bookit_version_incompatible`. Always test against the
minimum core version you actually need, not the latest.

### What happens if registration fails

`bookit_register_extension()` returns a `WP_Error`. Your extension should check
this and not initialise any hooks, endpoints, or migrations if registration
failed. A failed extension will not appear in the Active Extensions list and
its nav items will not be shown in the sidebar.

---

## 4. Action hooks

All action hooks are fired by Bookit core at specific points in the booking
lifecycle. Add listeners with `add_action()` inside your loader class.

---

### `bookit_before_booking_created`

Fires immediately before a new booking row is inserted into the database.

**Parameters:**
- `$booking_data` *(array)* — The data array about to be inserted. Contains:
  `customer_id`, `service_id`, `staff_id`, `booking_date`, `booking_time`,
  `end_time`, `status`, `payment_method`, `amount_paid`, `special_requests`

**When it fires:** Both manual bookings (dashboard) and public wizard bookings.

**Example:**
```php
add_action( 'bookit_before_booking_created', function( array $booking_data ) {
    // Validate or log before insert.
}, 10, 1 );
```

---

### `bookit_after_booking_created`

Fires after a new booking has been successfully inserted.

**Parameters:**
- `$booking_id` *(int)* — The new booking's ID.
- `$booking_data` *(array)* — The data that was inserted (same shape as above).

**When it fires:** Both manual bookings (dashboard) and public wizard bookings.

**Example:**
```php
add_action( 'bookit_after_booking_created', function( int $booking_id, array $booking_data ) {
    // Send a custom notification, create a linked record, etc.
}, 10, 2 );
```

---

### `bookit_before_booking_updated`

Fires before an existing booking is updated via the dashboard.

**Parameters:**
- `$booking_id` *(int)* — The booking being updated.
- `$old_data` *(array)* — Current booking data before the update.
- `$new_data` *(array)* — Data that will be written.

**Example:**
```php
add_action( 'bookit_before_booking_updated', function( int $booking_id, array $old_data, array $new_data ) {
    // Compare old and new to detect specific field changes.
}, 10, 3 );
```

---

### `bookit_after_booking_updated`

Fires after a booking has been successfully updated.

**Parameters:**
- `$booking_id` *(int)* — The updated booking's ID.
- `$booking_data` *(array)* — The data that was written.

**Example:**
```php
add_action( 'bookit_after_booking_updated', function( int $booking_id, array $booking_data ) {
    // Sync changes to an external calendar.
}, 10, 2 );
```

---

### `bookit_before_booking_cancelled`

Fires before a booking is cancelled.

**Parameters:**
- `$booking_id` *(int)* — The booking being cancelled.
- `$booking_data` *(array)* — Current booking data.

**Example:**
```php
add_action( 'bookit_before_booking_cancelled', function( int $booking_id, array $booking_data ) {
    // Free up a linked resource before the booking is cancelled.
}, 10, 2 );
```

---

### `bookit_after_booking_cancelled`

Fires after a booking has been successfully cancelled.

**Parameters:**
- `$booking_id` *(int)* — The cancelled booking's ID.
- `$booking_data` *(array)* — Booking data at time of cancellation.

**Example:**
```php
add_action( 'bookit_after_booking_cancelled', function( int $booking_id, array $booking_data ) {
    // Notify a waitlist, release a held resource, etc.
}, 10, 2 );
```

---

### `bookit_after_payment_completed`

Fires after a payment has been confirmed (Stripe webhook processed
successfully).

**Parameters:**
- `$booking_id` *(int)* — The booking the payment is for.
- `$payment_data` *(array)* — Payment details. Contains:
  `amount`, `currency`, `payment_intent_id`, `method`

**Example:**
```php
add_action( 'bookit_after_payment_completed', function( int $booking_id, array $payment_data ) {
    // Issue a custom receipt or loyalty points.
}, 10, 2 );
```

---

### `bookit_after_customer_created`

Fires after a new customer record is inserted into the database.

**Parameters:**
- `$customer_id` *(int)* — The new customer's ID.
- `$customer_data` *(array)* — The data that was inserted. Contains:
  `first_name`, `last_name`, `email`, `phone`

**Example:**
```php
add_action( 'bookit_after_customer_created', function( int $customer_id, array $customer_data ) {
    // Add customer to a mailing list.
}, 10, 2 );
```

---

### `bookit_dashboard_loaded`

Fires when the dashboard HTML page is served to the browser (before the Vue
app boots). Use this to enqueue extension assets.

**Parameters:**
- `$current_user` *(array)* — The authenticated dashboard user. Contains:
  `id`, `email`, `first_name`, `last_name`, `role`

**Example:**
```php
add_action( 'bookit_dashboard_loaded', function( array $current_user ) {
    wp_enqueue_script(
        'bookit-recurring-dashboard',
        BOOKIT_RECURRING_PLUGIN_URL . 'dashboard/dist/app.js',
        [],
        BOOKIT_RECURRING_VERSION,
        true
    );
} , 10, 1 );
```

---

### `bookit_dashboard_extension_content`

Fires inside the dashboard HTML template after `<div id="app"></div>` and
before `</body>`. Use this to inject a Vue mount point div **inside the
dashboard layout**. Extensions that use `wp_footer` instead will have their
mount div placed outside the layout container, making their UI invisible.

**Parameters:** None.

**Since:** 1.5.1

**Example:**
```php
add_action( 'bookit_dashboard_extension_content', function() {
    echo '<div id="bookit-recurring-app"></div>';
} );
```

---

## 5. Filter hooks

Filter hooks let extensions modify data that core is about to use or return.
Always return a value of the same type as the input.

---

### `bookit_available_slots`

Filters the array of available time slots before they are returned to the
booking wizard. Use this to remove slots that your extension has reserved
(e.g. Bookit Classes removes slots when a class is full).

**Value being filtered:** `$slots` *(array)* — Array of available slot strings
in `H:i` format (e.g. `['09:00', '09:30', '10:00']`).

**Additional parameters:**
- `$staff_id` *(int)* — Staff member whose slots are being calculated.
- `$date` *(string)* — Date in `Y-m-d` format.
- `$service_id` *(int)* — Service being booked.

**Expected return type:** `array` — Modified slots array. May be empty.

**Example:**
```php
add_filter( 'bookit_available_slots', function( array $slots, int $staff_id, string $date, int $service_id ): array {
    // Remove slots reserved by your extension.
    return array_filter( $slots, fn( $slot ) => ! my_slot_is_reserved( $slot, $staff_id, $date ) );
}, 10, 4 );
```

---

### `bookit_booking_data_before_insert`

Filters the booking data array immediately before it is inserted into the
database. Use this to add custom fields your extension needs stored on the
booking row (if you have added columns via a migration).

**Value being filtered:** `$booking_data` *(array)* — The data array about to
be inserted.

**Expected return type:** `array`

**Example:**
```php
add_filter( 'bookit_booking_data_before_insert', function( array $booking_data ): array {
    $booking_data['recurring_group_id'] = my_get_recurring_group_id();
    return $booking_data;
} );
```

---

### `bookit_booking_response`

Filters a booking's API response data before it is returned to the Vue
dashboard. Use this to append extension-specific data to booking responses
(e.g. recurring series information).

**Value being filtered:** `$response_data` *(array)* — The response array.

**Additional parameters:**
- `$booking_id` *(int)* — The booking ID.

**Expected return type:** `array`

**Example:**
```php
add_filter( 'bookit_booking_response', function( array $response_data, int $booking_id ): array {
    // Note: the second parameter is the booking ID integer, not the full booking array.
    // If you need the full booking record, re-fetch it from the database:
    // global $wpdb;
    // $booking = $wpdb->get_row(
    //     $wpdb->prepare(
    //         "SELECT * FROM {$wpdb->prefix}bookings WHERE id = %d",
    //         $booking_id
    //     ),
    //     ARRAY_A
    // );
    $response_data['recurring'] = my_get_recurring_data( $booking_id );
    return $response_data;
}, 10, 2 );
```

---

### `bookit_schedule_booking_response`

Filters a booking's API response data in the My Schedule endpoint before it
is returned to the Vue dashboard. Use this to append extension-specific fields
(e.g. `meeting_link`) to schedule booking responses.

**Value being filtered:** `$formatted` *(array)* — The formatted schedule booking array.

**Additional parameters:**
- `$booking_id` *(int)* — The booking ID.

**Expected return type:** `array`

**Since:** 1.5.1

**Example:**
```php
add_filter( 'bookit_schedule_booking_response', function( array $formatted, int $booking_id ): array {
    $formatted['meeting_link'] = my_get_meeting_link( $booking_id );
    return $formatted;
}, 10, 2 );
```

---

### `bookit_staff_email_meeting_section`

Filters the HTML injected into staff notification emails between the booking
detail rows and the action links. Use this to add a meeting link row to
staff emails, mirroring the `bookit_email_meeting_section` filter on customer
confirmation emails.

**Value being filtered:** `$html` *(string)* — Default empty string. Return
non-empty HTML to inject it into the email body via `wp_kses_post()`.

**Additional parameters:**
- `$booking` *(array)* — Full booking record.
- `$staff_id` *(int)* — The staff member receiving the notification.

**Expected return type:** `string`

**Since:** 1.5.1

**Example:**
```php
add_filter( 'bookit_staff_email_meeting_section', function( string $html, array $booking, int $staff_id ): string {
    $link = my_get_meeting_link( (int) $booking['id'] );
    if ( ! $link ) {
        return $html;
    }
    return '<p><strong>Meeting link:</strong> <a href="' . esc_url( $link ) . '">' . esc_html( $link ) . '</a></p>';
}, 10, 3 );
```

---

### `bookit_sidebar_nav_items`

Filters the full nav items array before it is sent to the Vue sidebar via the
extensions REST endpoint. This is an alternative to `bookit_register_nav_item()`
for dynamic nav item generation, or for modifying other extensions' nav items.

**Value being filtered:** `$nav_items` *(array)* — Array of nav item arrays.

**Expected return type:** `array`

**Example:**
```php
add_filter( 'bookit_sidebar_nav_items', function( array $nav_items ): array {
    // Conditionally add a nav item based on a setting.
    if ( my_feature_is_enabled() ) {
        $nav_items[] = [
            'label'      => 'My Feature',
            'route'      => '/bookit-dashboard/app/my-feature',
            'icon'       => 'star',
            'position'   => 60,
            'capability' => 'bookit_manage_all',
            'slug'       => 'my-extension',
        ];
    }
    return $nav_items;
} );
```

---

### `bookit_dashboard_js_data`

Filters the `window.bookitDashboard` JavaScript data object that is passed to
the Vue app on dashboard load. Use this to pass extension-specific config or
feature flags to your dashboard Vue app.

**Value being filtered:** `$js_data` *(array)* — The data object. Core includes:
`apiBase`, `nonce`, `currentUser`, `settings`, `branding`

**Expected return type:** `array`

**Example:**
```php
add_filter( 'bookit_dashboard_js_data', function( array $js_data ): array {
    $js_data['recurring'] = [
        'enabled'    => true,
        'maxOccurrences' => 52,
    ];
    return $js_data;
} );
```

---

## 6. Adding dashboard pages

### Registering a nav item

Call `bookit_register_nav_item()` after successfully registering your extension:
```php
bookit_register_nav_item( [
    'label'      => 'Recurring',
    'route'      => '/bookit-dashboard/app/recurring',
    'icon'       => 'calendar-repeat',
    'position'   => 50,
    'capability' => 'bookit_manage_all',
    'slug'       => 'bookit-recurring',
] );
```

| Argument | Type | Default | Description |
|---|---|---|---|
| `label` | string | — | Sidebar label text |
| `route` | string | — | Full dashboard route path |
| `icon` | string | — | Icon name (match existing sidebar icon names) |
| `position` | int | 100 | Sort order — lower appears higher |
| `capability` | string | `bookit_manage_all` | Required capability |
| `slug` | string | — | Your extension's slug |

### Enqueuing your Vue app assets

Use the `bookit_dashboard_loaded` action to enqueue your built Vue app JS and
CSS. Your Vue app will be in the page but not mounted until its route is active.
```php
add_action( 'bookit_dashboard_loaded', function( array $current_user ) {
    wp_enqueue_script(
        'bookit-recurring-app',
        BOOKIT_RECURRING_PLUGIN_URL . 'dashboard/dist/app.js',
        [],
        BOOKIT_RECURRING_VERSION,
        true
    );
    wp_enqueue_style(
        'bookit-recurring-app',
        BOOKIT_RECURRING_PLUGIN_URL . 'dashboard/dist/app.css',
        [],
        BOOKIT_RECURRING_VERSION
    );
} );
```

### Mounting contract

When your Vue app mounts, the following are available in the browser:

- `window.bookitDashboard` — core JS data object (filtered via
  `bookit_dashboard_js_data`). Contains `apiBase`, `nonce`, `currentUser`,
  `settings`, `branding`, plus any data your extension added via the filter.
- `window.bookitDashboard.apiBase` — base URL for REST API calls
  (e.g. `https://example.com/wp-json`)
- `window.bookitDashboard.nonce` — WordPress nonce for REST requests. Include
  as `X-WP-Nonce` header on all API calls.

Your Vue app is responsible for mounting itself to a DOM element. Add a mount
point `<div id="bookit-recurring-app"></div>` to your dashboard page via a
shortcode or WordPress template — or more simply, mount to a div your PHP
creates when it enqueues assets:
```php
add_action( 'bookit_dashboard_loaded', function() {
    // Output mount point after enqueuing scripts.
    add_action( 'wp_footer', function() {
        echo '<div id="bookit-recurring-app"></div>';
    } );
} );
```

Your `main.js`:
```js
import { createApp } from 'vue'
import App from './App.vue'

const app = createApp( App )
app.mount( '#bookit-recurring-app' )
```

### Dashboard routing

The core Vue Router handles `/bookit-dashboard/app/*` routes. Your extension
page is served at its registered route (e.g. `/bookit-dashboard/app/recurring`).
The core sidebar link navigates to this route, which loads your Vue app if it
is mounted. Your Vue app can have its own internal router for sub-pages.

---

## 7. Adding REST endpoints

### Namespace convention

All extension endpoints must use their own namespace, not `bookit/v1`:
```
bookit-{slug}/v1/
```

Example: `bookit-recurring/v1/schedules`

### Authentication

Extensions use the same session-based authentication as core. Copy the
`check_dashboard_permission()` pattern from a core API class. The session
check looks for `$_SESSION['bookit_dashboard_user_id']` and validates the
nonce from the `X-WP-Nonce` request header.
```php
private function check_dashboard_permission(): bool|WP_Error {
    // Require valid Bookit dashboard session.
    if ( ! Bookit_Auth::is_authenticated() ) {
        return new WP_Error( 'bookit_unauthorized', __( 'Authentication required.', 'bookit-recurring' ), [ 'status' => 401 ] );
    }
    return true;
}
```

### Accessing core data

Prefer calling core REST endpoints from your Vue frontend rather than
instantiating core PHP classes directly. For server-side access, see §9 for
the safe PHP class list.

---

## 8. Adding database migrations

### Registering your migrations path

Call `bookit_register_migration_path()` inside your loader, after registration
succeeds, on `plugins_loaded` priority 5:
```php
bookit_register_migration_path(
    'bookit-recurring',
    BOOKIT_RECURRING_PLUGIN_DIR . 'database/migrations/'
);
```

### Running migrations on activation

In your plugin's activation hook:
```php
register_activation_hook( __FILE__, function() {
    // Ensure core migration runner is available.
    if ( ! class_exists( 'Bookit_Migration_Runner' ) ) {
        return;
    }
    bookit_register_migration_path( 'bookit-recurring', BOOKIT_RECURRING_PLUGIN_DIR . 'database/migrations/' );
    Bookit_Migration_Runner::run_pending( 'bookit-recurring' );
} );
```

### Rolling back on deactivation
```php
register_deactivation_hook( __FILE__, function() {
    if ( ! class_exists( 'Bookit_Migration_Runner' ) ) {
        return;
    }
    Bookit_Migration_Runner::rollback_last( 'bookit-recurring' );
} );
```

### Migration file naming convention

Files must match: `NNNN-description.php` (e.g. `0001-add-recurring-tables.php`)

The migration runner identifies your class by its `migration_id()` and
`plugin_slug()` return values — not by the filename. Your class name can be
anything, as long as it extends `Bookit_Migration_Base` and returns the correct
identifiers. By convention the class name mirrors the filename (e.g.
`0001-add-recurring-tables.php` → `Bookit_Migration_0001_Add_Recurring_Tables`),
but this is not required and no `class_alias()` workaround is needed.

### Migration file contract
```php
<?php
if ( ! defined( 'WPINC' ) ) { die; }

class Bookit_Migration_0001_Add_Recurring_Tables extends Bookit_Migration_Base {

    public function migration_id(): string {
        return '0001-add-recurring-tables';
    }

    public function plugin_slug(): string {
        return 'bookit-recurring';
    }

    public function up(): void {
        global $wpdb;
        $charset = $wpdb->get_charset_collate();
        $wpdb->query(
            "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}bookit_recurring_schedules (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                booking_id BIGINT UNSIGNED NOT NULL,
                frequency VARCHAR(20) NOT NULL,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
            ) ENGINE=InnoDB {$charset};"
        );
    }

    public function down(): void {
        global $wpdb;
        // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
        $wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}bookit_recurring_schedules" );
    }
}
```

`down()` must be the exact inverse of `up()`. For table creation, `down()` drops
the table. For column additions, `down()` drops the column.

---

## 9. Accessing core data

### Safe PHP classes (public API)

These classes are safe to instantiate or call statically from extension code:

| Class | Safe usage |
|---|---|
| `Bookit_Logger` | `Bookit_Logger::info()`, `::error()`, `::warning()` |
| `Bookit_Auth` | `Bookit_Auth::is_authenticated()`, `::get_current_user()` |
| `Bookit_Migration_Runner` | `::run_pending()`, `::rollback_last()`, `::has_run()` |
| `Bookit_Extension_Registry` | `::is_registered()`, `::get_extensions()` |

### Everything else

Access all other core data — bookings, customers, staff, services, settings —
via the core REST API endpoints (`bookit/v1/*`) from your Vue frontend, or via
`$wpdb` direct queries from PHP if you need server-side access. Do not
instantiate core model or API classes directly as their internal interfaces
may change between core versions.

---

## 10. Worked example: Bookit Hello World

A complete minimal extension (PHP only, no Vue) that demonstrates all
integration points. Copy this as a starting point for new extensions.

**File:** `bookit-hello-world/bookit-hello-world.php`
```php
<?php
/**
 * Plugin Name: Bookit Hello World
 * Description: Minimal Bookit extension demonstrating all integration points.
 * Version:     1.0.0
 * Requires at least: 6.0
 * Requires PHP:      8.0
 * Text Domain: bookit-hello-world
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

define( 'BOOKIT_HELLO_VERSION',      '1.0.0' );
define( 'BOOKIT_HELLO_PLUGIN_DIR',   plugin_dir_path( __FILE__ ) );
define( 'BOOKIT_HELLO_REQUIRES_CORE', '1.0.0' );

// ── Activation: run migrations ───────────────────────────────────────────────

register_activation_hook( __FILE__, function() {
    if ( ! class_exists( 'Bookit_Migration_Runner' ) ) { return; }
    bookit_register_migration_path( 'bookit-hello-world', BOOKIT_HELLO_PLUGIN_DIR . 'database/migrations/' );
    Bookit_Migration_Runner::run_pending( 'bookit-hello-world' );
} );

// ── Deactivation: roll back migrations ───────────────────────────────────────

register_deactivation_hook( __FILE__, function() {
    if ( ! class_exists( 'Bookit_Migration_Runner' ) ) { return; }
    Bookit_Migration_Runner::rollback_last( 'bookit-hello-world' );
} );

// ── Bootstrap on plugins_loaded ──────────────────────────────────────────────

add_action( 'plugins_loaded', function() {

    // Bail if Bookit core is not active.
    if ( ! function_exists( 'bookit_register_extension' ) ) { return; }

    // Register this extension.
    $result = bookit_register_extension( [
        'name'          => 'Bookit Hello World',
        'slug'          => 'bookit-hello-world',
        'version'       => BOOKIT_HELLO_VERSION,
        'requires_core' => BOOKIT_HELLO_REQUIRES_CORE,
        'description'   => 'A minimal example extension.',
        'author'        => 'Your Name',
    ] );

    if ( is_wp_error( $result ) ) {
        error_log( '[bookit-hello-world] Registration failed: ' . $result->get_error_message() );
        return;
    }

    // Register migrations path.
    bookit_register_migration_path( 'bookit-hello-world', BOOKIT_HELLO_PLUGIN_DIR . 'database/migrations/' );

    // Add a sidebar nav item (points to a placeholder route).
    bookit_register_nav_item( [
        'label'      => 'Hello World',
        'route'      => '/bookit-dashboard/app/hello-world',
        'icon'       => 'star',
        'position'   => 200,
        'capability' => 'bookit_manage_all',
        'slug'       => 'bookit-hello-world',
    ] );

    // Listen for booking created events.
    add_action( 'bookit_after_booking_created', function( int $booking_id, array $booking_data ) {
        Bookit_Logger::info( 'Hello World: booking created', [
            'booking_id' => $booking_id,
            'service_id' => $booking_data['service_id'] ?? null,
        ] );
    }, 10, 2 );

}, 5 );
```

**File:** `bookit-hello-world/database/migrations/0001-add-hello-world-log.php`
```php
<?php
if ( ! defined( 'WPINC' ) ) { die; }

class Bookit_Migration_0001_Add_Hello_World_Log extends Bookit_Migration_Base {

    public function migration_id(): string {
        return '0001-add-hello-world-log';
    }

    public function plugin_slug(): string {
        return 'bookit-hello-world';
    }

    public function up(): void {
        global $wpdb;
        $charset = $wpdb->get_charset_collate();
        $wpdb->query(
            "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}bookit_hello_log (
                id         BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                booking_id BIGINT UNSIGNED NOT NULL,
                logged_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
            ) ENGINE=InnoDB {$charset};"
        );
    }

    public function down(): void {
        global $wpdb;
        // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
        $wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}bookit_hello_log" );
    }
}
```

---

## 11. Versioning and compatibility

### Core version increments

Bookit core follows semantic versioning (semver):

- **Patch** (1.0.x) — bug fixes only. No hook changes, no breaking changes.
- **Minor** (1.x.0) — new hooks and filters added. Backwards compatible.
  Existing extensions continue to work.
- **Major** (x.0.0) — may include breaking changes. Hook signatures or
  class interfaces may change. Extensions must declare compatibility.

### Declaring compatibility ranges

Set `requires_core` to the minimum version where the hooks your extension
depends on were introduced. If you use hooks added in core 1.1.0, set
`requires_core` to `1.1.0`.

There is no maximum version declaration — extensions are assumed compatible
with newer core versions until proven otherwise. When a major core version
is released, test your extension and update `requires_core` if needed.

### Checking compatibility at runtime
```php
if ( version_compare( BOOKIT_VERSION, '1.1.0', '>=' ) ) {
    // Use a hook only available in core 1.1.0+.
}
```