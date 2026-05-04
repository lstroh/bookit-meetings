<?php
/**
 * Google Calendar — booking lifecycle hooks → sync queue.
 *
 * @package    Bookit_Booking_System
 * @subpackage Bookit_Booking_System/includes/integrations
 */

if ( ! defined( 'WPINC' ) ) {
	die;
}

/**
 * Listens for booking hooks and enqueues deferred Google Calendar sync jobs.
 */
class Bookit_Google_Calendar_Sync {

	/**
	 * Register hook listeners.
	 *
	 * @return void
	 */
	public static function init(): void {
		add_action( 'bookit_after_booking_created', array( __CLASS__, 'on_booking_created' ), 10, 2 );
		add_action( 'bookit_booking_rescheduled', array( __CLASS__, 'on_booking_rescheduled' ), 10, 2 );
		add_action( 'bookit_after_booking_cancelled', array( __CLASS__, 'on_booking_cancelled' ), 10, 2 );
	}

	/**
	 * After create: enqueue create sync for confirmed / pending_payment only.
	 *
	 * @param int   $booking_id   Booking ID.
	 * @param array $booking_data Payload from the firing code (may omit fields).
	 * @return void
	 */
	public static function on_booking_created( int $booking_id, array $booking_data ): void {
		$status = self::get_booking_status( $booking_id );
		if ( null === $status ) {
			return;
		}

		if ( ! in_array( $status, array( 'confirmed', 'pending_payment' ), true ) ) {
			return;
		}

		$booking_staff_id = self::resolve_booking_staff_id( $booking_id, $booking_data );
		if ( $booking_staff_id < 1 ) {
			return;
		}

		$calendar_staff_id = self::resolve_staff_id( $booking_staff_id );
		if ( null === $calendar_staff_id ) {
			Bookit_Audit_Logger::log(
				'google_calendar.sync_skipped',
				'booking',
				$booking_id,
				array( 'notes' => 'no_connected_staff' )
			);
			return;
		}

		bookit_enqueue_calendar_sync( 'create', $booking_id, $calendar_staff_id );
	}

	/**
	 * After reschedule: enqueue update sync.
	 *
	 * @param int   $booking_id   Booking ID.
	 * @param array $booking_data Payload from the firing code.
	 * @return void
	 */
	public static function on_booking_rescheduled( int $booking_id, array $booking_data ): void {
		$booking_staff_id = self::resolve_booking_staff_id( $booking_id, $booking_data );
		if ( $booking_staff_id < 1 ) {
			return;
		}

		$calendar_staff_id = self::resolve_staff_id( $booking_staff_id );
		if ( null === $calendar_staff_id ) {
			Bookit_Audit_Logger::log(
				'google_calendar.sync_skipped',
				'booking',
				$booking_id,
				array( 'notes' => 'no_connected_staff' )
			);
			return;
		}

		bookit_enqueue_calendar_sync( 'update', $booking_id, $calendar_staff_id );
	}

	/**
	 * After cancel: enqueue delete sync (processor uses booking row + fallback for OAuth).
	 *
	 * @param int   $booking_id   Booking ID.
	 * @param array $booking_data Payload from the firing code.
	 * @return void
	 */
	public static function on_booking_cancelled( int $booking_id, array $booking_data ): void {
		// booking_data unused; signature matches other lifecycle hooks.
		unset( $booking_data );
		bookit_enqueue_calendar_sync( 'delete', $booking_id );
	}

	/**
	 * Which staff member’s Google OAuth to use for sync (assigned staff or fallback admin).
	 *
	 * @param int $booking_staff_id Assigned staff ID from the booking.
	 * @return int|null Staff ID with a usable calendar connection, or null.
	 */
	private static function resolve_staff_id( int $booking_staff_id ): ?int {
		global $wpdb;

		$table = $wpdb->prefix . 'bookings_staff';

		$connected = (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT google_calendar_connected FROM {$table} WHERE id = %d AND deleted_at IS NULL",
				$booking_staff_id
			)
		);

		if ( 1 === $connected ) {
			return $booking_staff_id;
		}

		$fallback_raw = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT setting_value FROM {$wpdb->prefix}bookings_settings WHERE setting_key = %s",
				'google_calendar_fallback_enabled'
			)
		);

		$fallback_on = self::is_truthy_setting( $fallback_raw );
		if ( ! $fallback_on ) {
			return null;
		}

		$admin_id = $wpdb->get_var(
			"SELECT id FROM {$table}
			WHERE role = 'admin'
			AND google_calendar_connected = 1
			AND deleted_at IS NULL
			AND is_active = 1
			ORDER BY id ASC
			LIMIT 1"
		);

		if ( null === $admin_id ) {
			return null;
		}

		return (int) $admin_id;
	}

	/**
	 * @param mixed $value Raw setting value.
	 * @return bool
	 */
	private static function is_truthy_setting( $value ): bool {
		if ( is_bool( $value ) ) {
			return $value;
		}
		$s = is_string( $value ) ? strtolower( trim( $value ) ) : (string) (int) $value;
		return in_array( $s, array( '1', 'true', 'yes', 'on' ), true );
	}

	/**
	 * @param int   $booking_id   Booking ID.
	 * @param array $booking_data Hook payload.
	 * @return int Assigned staff ID or 0.
	 */
	private static function resolve_booking_staff_id( int $booking_id, array $booking_data ): int {
		if ( isset( $booking_data['staff_id'] ) ) {
			$id = (int) $booking_data['staff_id'];
			if ( $id > 0 ) {
				return $id;
			}
		}

		global $wpdb;
		$row = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT staff_id FROM {$wpdb->prefix}bookings WHERE id = %d AND deleted_at IS NULL",
				$booking_id
			)
		);

		return null !== $row ? (int) $row : 0;
	}

	/**
	 * @param int $booking_id Booking ID.
	 * @return string|null Status slug or null if not found.
	 */
	private static function get_booking_status( int $booking_id ): ?string {
		global $wpdb;

		$status = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT status FROM {$wpdb->prefix}bookings WHERE id = %d AND deleted_at IS NULL",
				$booking_id
			)
		);

		return is_string( $status ) && '' !== $status ? $status : null;
	}
}
