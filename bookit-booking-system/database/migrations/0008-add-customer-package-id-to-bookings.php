<?php
/**
 * Migration: Add customer_package_id column to bookings table.
 *
 * @package    Bookit_Booking_System
 * @subpackage Bookit_Booking_System/database/migrations
 */

if ( ! defined( 'WPINC' ) ) {
	die;
}

require_once BOOKIT_PLUGIN_DIR . 'database/migrations/class-bookit-migration-base.php';

/**
 * Adds package link column to bookings records.
 */
class Bookit_Migration_0008_Add_Customer_Package_Id_To_Bookings extends Bookit_Migration_Base {

	/**
	 * Return migration ID.
	 *
	 * @return string
	 */
	public function migration_id(): string {
		return '0008-add-customer-package-id-to-bookings';
	}

	/**
	 * Run migration.
	 *
	 * @return void
	 */
	public function up(): void {
		global $wpdb;

		$bookings_table = $wpdb->prefix . 'bookings';

		if ( $this->column_exists( $bookings_table, 'customer_package_id' ) ) {
			return;
		}

		$after_column = 'payment_reference';
		if ( ! $this->column_exists( $bookings_table, $after_column ) ) {
			$after_column = $this->column_exists( $bookings_table, 'payment_intent_id' ) ? 'payment_intent_id' : '';
		}

		$position_sql = '';
		if ( '' !== $after_column ) {
			$position_sql = ' AFTER ' . $after_column;
		}

		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.DirectDatabaseQuery.DirectQuery
		$wpdb->query(
			"ALTER TABLE {$bookings_table}
				ADD COLUMN customer_package_id BIGINT UNSIGNED NULL{$position_sql},
				ADD KEY idx_customer_package_id (customer_package_id)"
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

		if ( ! $this->column_exists( $bookings_table, 'customer_package_id' ) ) {
			return;
		}

		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.DirectDatabaseQuery.DirectQuery
		$wpdb->query( "ALTER TABLE {$bookings_table} DROP COLUMN customer_package_id" );
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
