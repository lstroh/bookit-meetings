# Extension plugin API — action hooks

Documentation for WordPress `do_action` hooks intended for extension plugins.

---

**Hook name:** `bookit_after_booking_updated`

**Fires:** After a booking is updated via the dashboard `update_booking()` endpoint, after the DB update and lock version update succeed.

**Parameters:**

- `$booking_id` *(int)* — The booking's ID.
- `$booking_data` *(array)* — The booking field array passed to the update (aligned with the row written to the database).

**Example use:**

```php
add_action(
    'bookit_after_booking_updated',
    function( int $booking_id, array $booking_data ) {
        // Extension logic after any dashboard booking edit.
    },
    10,
    2
);
```

---

**Hook name:** `bookit_booking_reassigned`

**Fires:** After a booking's assigned staff member is changed via the dashboard `update_booking()` endpoint, after the DB update succeeds.

**Parameters:**

- `$booking_id` *(int)* — The booking's ID.
- `$old_staff_id` *(int)* — The staff member previously assigned.
- `$new_staff_id` *(int)* — The staff member now assigned.
- `$booking_data` *(array)* — The full updated booking data array written to the DB.

**Example use:**

```php
add_action(
    'bookit_booking_reassigned',
    function( int $booking_id, int $old_staff_id, int $new_staff_id, array $booking_data ) {
        // Notify old and new assignees, sync external calendar, etc.
    },
    10,
    4
);
```

**Note:** This hook does NOT fire for magic link reschedules (which cannot change staff) or for admin bulk actions.
