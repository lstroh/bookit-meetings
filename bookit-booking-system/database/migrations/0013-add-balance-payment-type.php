<?php
/**
 * Migration: Add balance_payment to bookings_payments.payment_type ENUM.
 *
 * @package    Bookit_Booking_System
 * @subpackage Bookit_Booking_System/database/migrations
 */

if ( ! defined( 'WPINC' ) ) {
	die;
}

/**
 * Extends payment_type ENUM with balance_payment.
 */
class Bookit_Migration_0013_Add_Balance_Payment_Type extends Bookit_Migration_Base {

	/**
	 * Return migration ID.
	 *
	 * @return string
	 */
	public function migration_id(): string {
		return '0013-add-balance-payment-type';
	}

	/**
	 * Return plugin slug.
	 *
	 * @return string
	 */
	public function plugin_slug(): string {
		return 'bookit-booking-system';
	}

	/**
	 * Run migration.
	 *
	 * @return void
	 */
	public function up(): void {
		global $wpdb;

		$table = $wpdb->prefix . 'bookings_payments';

		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.DirectDatabaseQuery.DirectQuery
		$wpdb->query(
			"ALTER TABLE {$table} MODIFY COLUMN payment_type ENUM('deposit','full_payment','balance_payment','refund') DEFAULT 'full_payment'"
		);
	}

	/**
	 * Roll back migration.
	 *
	 * @return void
	 */
	public function down(): void {
		global $wpdb;

		$table = $wpdb->prefix . 'bookings_payments';

		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.DirectDatabaseQuery.DirectQuery
		$wpdb->query(
			"ALTER TABLE {$table} MODIFY COLUMN payment_type ENUM('deposit','full_payment','refund') DEFAULT 'full_payment'"
		);
	}
}
