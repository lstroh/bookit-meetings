<?php
/**
 * Migration: Add cooling-off waiver fields to bookings table.
 *
 * @package    Bookit_Booking_System
 * @subpackage Bookit_Booking_System/database/migrations
 */

if ( ! defined( 'WPINC' ) ) {
	die;
}

require_once BOOKIT_PLUGIN_DIR . 'database/migrations/class-bookit-migration-base.php';

/**
 * Adds legal cooling-off waiver tracking columns for bookings.
 */
class Bookit_Migration_0004_Add_Cooling_Off_Waiver extends Bookit_Migration_Base {

	/**
	 * Return migration ID.
	 *
	 * @return string
	 */
	public function migration_id(): string {
		return '0004-add-cooling-off-waiver';
	}

	/**
	 * Run migration.
	 *
	 * @return void
	 */
	public function up(): void {
		global $wpdb;

		$bookings_table = $wpdb->prefix . 'bookings';

		if ( ! $this->column_exists( $bookings_table, 'cooling_off_waiver_given' ) ) {
			// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.DirectDatabaseQuery.DirectQuery
			$wpdb->query(
				"ALTER TABLE {$bookings_table}
				ADD COLUMN cooling_off_waiver_given TINYINT(1) DEFAULT 0
				AFTER special_requests"
			);
		}

		if ( ! $this->column_exists( $bookings_table, 'cooling_off_waiver_at' ) ) {
			// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.DirectDatabaseQuery.DirectQuery
			$wpdb->query(
				"ALTER TABLE {$bookings_table}
				ADD COLUMN cooling_off_waiver_at DATETIME DEFAULT NULL
				AFTER cooling_off_waiver_given"
			);
		}
	}

	/**
	 * Roll back migration.
	 *
	 * @return void
	 */
	public function down(): void {
		global $wpdb;

		$bookings_table = $wpdb->prefix . 'bookings';

		if ( $this->column_exists( $bookings_table, 'cooling_off_waiver_at' ) ) {
			// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.DirectDatabaseQuery.DirectQuery
			$wpdb->query( "ALTER TABLE {$bookings_table} DROP COLUMN cooling_off_waiver_at" );
		}

		if ( $this->column_exists( $bookings_table, 'cooling_off_waiver_given' ) ) {
			// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.DirectDatabaseQuery.DirectQuery
			$wpdb->query( "ALTER TABLE {$bookings_table} DROP COLUMN cooling_off_waiver_given" );
		}
	}

	/**
	 * Check whether a column exists.
	 *
	 * @param string $table_name Full table name.
	 * @param string $column     Column name.
	 * @return bool
	 */
	private function column_exists( $table_name, $column ): bool {
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
