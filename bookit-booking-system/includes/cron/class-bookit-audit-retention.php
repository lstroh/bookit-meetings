<?php
/**
 * Audit log retention cron.
 *
 * @package    Bookit_Booking_System
 * @subpackage Bookit_Booking_System/includes/cron
 */

if ( ! defined( 'WPINC' ) ) {
	die;
}

/**
 * Handles automated audit retention cleanup.
 */
class Bookit_Audit_Retention {

	/**
	 * Register daily cron event.
	 *
	 * @return void
	 */
	public static function register_cron(): void {
		if ( ! wp_next_scheduled( 'bookit_audit_retention' ) ) {
			wp_schedule_event( strtotime( 'tomorrow 04:00:00' ), 'daily', 'bookit_audit_retention' );
		}
	}

	/**
	 * Execute retention cleanup.
	 *
	 * @return void
	 */
	public static function run(): void {
		global $wpdb;

		$table = $wpdb->prefix . 'bookings_audit_log';

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.PreparedSQL.NotPrepared
		$wpdb->query(
			$wpdb->prepare(
				"DELETE FROM {$table}
				WHERE action LIKE %s
				AND created_at < %s",
				'payment.%',
				gmdate( 'Y-m-d H:i:s', strtotime( '-7 years' ) )
			)
		);

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.PreparedSQL.NotPrepared
		$wpdb->query(
			$wpdb->prepare(
				"DELETE FROM {$table}
				WHERE action NOT LIKE %s
				AND created_at < %s",
				'payment.%',
				gmdate( 'Y-m-d H:i:s', strtotime( '-2 years' ) )
			)
		);
	}
}
