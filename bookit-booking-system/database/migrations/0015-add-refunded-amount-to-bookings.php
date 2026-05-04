<?php
/**
 * Migration: Add refunded_amount column to bookings table.
 *
 * @package    Bookit_Booking_System
 * @subpackage Bookit_Booking_System/database/migrations
 */

if ( ! defined( 'WPINC' ) ) {
	die;
}

require_once BOOKIT_PLUGIN_DIR . 'database/migrations/class-bookit-migration-base.php';

/**
 * Stores cumulative Stripe refund total (from charge.amount_refunded) on the booking row.
 */
class Bookit_Migration_0015_Add_Refunded_Amount_To_Bookings extends Bookit_Migration_Base {

	/**
	 * Return migration ID.
	 *
	 * @return string
	 */
	public function migration_id(): string {
		return '0015-add-refunded-amount-to-bookings';
	}

	/**
	 * Run migration.
	 *
	 * @return void
	 */
	public function up(): void {
		global $wpdb;

		$bookings_table = $wpdb->prefix . 'bookings';

		if ( $this->column_exists( $bookings_table, 'refunded_amount' ) ) {
			return;
		}

		$after_column = 'payment_intent_id';
		if ( ! $this->column_exists( $bookings_table, $after_column ) ) {
			$after_column = '';
		}

		$position_sql = '';
		if ( '' !== $after_column ) {
			$position_sql = ' AFTER ' . $after_column;
		}

		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.DirectDatabaseQuery.DirectQuery
		$wpdb->query(
			"ALTER TABLE {$bookings_table}
				ADD COLUMN refunded_amount DECIMAL(10,2) NULL DEFAULT NULL{$position_sql}"
		);
	}

	/**
	 * Roll back migration.
	 *
	 * @return void
	 */
	public function down(): void {
		global $wpdb;

		$bookings_table = $wpdb->prefix . 'bookings';

		if ( ! $this->column_exists( $bookings_table, 'refunded_amount' ) ) {
			return;
		}

		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.DirectDatabaseQuery.DirectQuery
		$wpdb->query( "ALTER TABLE {$bookings_table} DROP COLUMN refunded_amount" );
	}

	/**
	 * Check whether a column exists.
	 *
	 * @param string $table_name Full table name.
	 * @param string $column     Column name.
	 * @return bool
	 */
	private function column_exists( string $table_name, string $column ): bool {
		global $wpdb;

		$sql = $wpdb->prepare(
			"SHOW COLUMNS FROM {$table_name} LIKE %s",
			$column
		);

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.PreparedSQL.NotPrepared
		$result = $wpdb->get_var( $sql );
		return ! empty( $result );
	}
}
