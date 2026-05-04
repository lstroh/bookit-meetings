<?php
/**
 * Migration: Add performance indexes for bookings and customer packages.
 *
 * @package    Bookit_Booking_System
 * @subpackage Bookit_Booking_System/database/migrations
 */

if ( ! defined( 'WPINC' ) ) {
	die;
}

require_once BOOKIT_PLUGIN_DIR . 'database/migrations/class-bookit-migration-base.php';

/**
 * Adds composite indexes used by dashboard/report filtering and package expiry cleanup.
 */
class Bookit_Migration_0009_Add_Performance_Indexes extends Bookit_Migration_Base {

	/**
	 * Return migration ID.
	 *
	 * @return string
	 */
	public function migration_id(): string {
		return '0009-add-performance-indexes';
	}

	/**
	 * Run migration.
	 *
	 * @return void
	 */
	public function up(): void {
		global $wpdb;

		$bookings_table          = $wpdb->prefix . 'bookings';
		$customer_packages_table = $wpdb->prefix . 'bookings_customer_packages';

		if ( ! $this->index_exists( $bookings_table, 'idx_status_date' ) ) {
			// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.DirectDatabaseQuery.DirectQuery
			$wpdb->query( "ALTER TABLE {$bookings_table} ADD INDEX idx_status_date (status, booking_date)" );
		}

		if ( ! $this->index_exists( $bookings_table, 'idx_staff_date_status' ) ) {
			// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.DirectDatabaseQuery.DirectQuery
			$wpdb->query( "ALTER TABLE {$bookings_table} ADD INDEX idx_staff_date_status (staff_id, booking_date, status)" );
		}

		if ( ! $this->index_exists( $customer_packages_table, 'idx_status_expires' ) ) {
			// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.DirectDatabaseQuery.DirectQuery
			$wpdb->query( "ALTER TABLE {$customer_packages_table} ADD INDEX idx_status_expires (status, expires_at)" );
		}
	}

	/**
	 * Roll back migration.
	 *
	 * @return void
	 */
	public function down(): void {
		global $wpdb;

		$bookings_table          = $wpdb->prefix . 'bookings';
		$customer_packages_table = $wpdb->prefix . 'bookings_customer_packages';

		if ( $this->index_exists( $bookings_table, 'idx_status_date' ) ) {
			// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.DirectDatabaseQuery.DirectQuery
			$wpdb->query( "ALTER TABLE {$bookings_table} DROP INDEX idx_status_date" );
		}

		if ( $this->index_exists( $bookings_table, 'idx_staff_date_status' ) ) {
			// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.DirectDatabaseQuery.DirectQuery
			$wpdb->query( "ALTER TABLE {$bookings_table} DROP INDEX idx_staff_date_status" );
		}

		if ( $this->index_exists( $customer_packages_table, 'idx_status_expires' ) ) {
			// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.DirectDatabaseQuery.DirectQuery
			$wpdb->query( "ALTER TABLE {$customer_packages_table} DROP INDEX idx_status_expires" );
		}
	}

	/**
	 * Check whether an index exists on a table.
	 *
	 * @param string $table_name Full table name.
	 * @param string $index_name Index name.
	 * @return bool
	 */
	private function index_exists( string $table_name, string $index_name ): bool {
		global $wpdb;

		$sql = $wpdb->prepare(
			"SELECT COUNT(*)
			FROM information_schema.statistics
			WHERE table_schema = DATABASE()
				AND table_name = %s
				AND index_name = %s",
			$table_name,
			$index_name
		);

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.PreparedSQL.NotPrepared
		$count = (int) $wpdb->get_var( $sql );
		return $count > 0;
	}
}
