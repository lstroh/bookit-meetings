<?php
/**
 * Migration: Add email change workflow columns to customers table.
 *
 * @package    Bookit_Booking_System
 * @subpackage Bookit_Booking_System/database/migrations
 */

if ( ! defined( 'WPINC' ) ) {
	die;
}

require_once BOOKIT_PLUGIN_DIR . 'database/migrations/class-bookit-migration-base.php';

/**
 * Stores pending customer email change requests (token + expiry).
 */
class Bookit_Migration_0019_Add_Email_Change_Columns_To_Customers extends Bookit_Migration_Base {

	/**
	 * Return migration ID.
	 *
	 * @return string
	 */
	public function migration_id(): string {
		return '0019-add-email-change-columns-to-customers';
	}

	/**
	 * Run migration.
	 *
	 * @return void
	 */
	public function up(): void {
		global $wpdb;

		$customers_table = $wpdb->prefix . 'bookings_customers';

		if ( ! $this->column_exists( $customers_table, 'pending_email_change' ) ) {
			// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.DirectDatabaseQuery.DirectQuery
			$wpdb->query( "ALTER TABLE {$customers_table} ADD COLUMN pending_email_change VARCHAR(255) NULL DEFAULT NULL" );
		}

		if ( ! $this->column_exists( $customers_table, 'email_change_token' ) ) {
			// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.DirectDatabaseQuery.DirectQuery
			$wpdb->query( "ALTER TABLE {$customers_table} ADD COLUMN email_change_token VARCHAR(64) NULL DEFAULT NULL" );
		}

		if ( ! $this->column_exists( $customers_table, 'email_change_expires' ) ) {
			// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.DirectDatabaseQuery.DirectQuery
			$wpdb->query( "ALTER TABLE {$customers_table} ADD COLUMN email_change_expires DATETIME NULL DEFAULT NULL" );
		}
	}

	/**
	 * Roll back migration.
	 *
	 * @return void
	 */
	public function down(): void {
		global $wpdb;

		$customers_table = $wpdb->prefix . 'bookings_customers';

		if ( $this->column_exists( $customers_table, 'email_change_expires' ) ) {
			// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.DirectDatabaseQuery.DirectQuery
			$wpdb->query( "ALTER TABLE {$customers_table} DROP COLUMN email_change_expires" );
		}

		if ( $this->column_exists( $customers_table, 'email_change_token' ) ) {
			// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.DirectDatabaseQuery.DirectQuery
			$wpdb->query( "ALTER TABLE {$customers_table} DROP COLUMN email_change_token" );
		}

		if ( $this->column_exists( $customers_table, 'pending_email_change' ) ) {
			// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.DirectDatabaseQuery.DirectQuery
			$wpdb->query( "ALTER TABLE {$customers_table} DROP COLUMN pending_email_change" );
		}
	}

	/**
	 * Check whether a column exists (information_schema, current database).
	 *
	 * @param string $table_name Full table name.
	 * @param string $column     Column name.
	 * @return bool
	 */
	private function column_exists( string $table_name, string $column ): bool {
		global $wpdb;

		$sql = $wpdb->prepare(
			'SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = %s AND COLUMN_NAME = %s',
			$table_name,
			$column
		);

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.PreparedSQL.NotPrepared
		$count = (int) $wpdb->get_var( $sql );
		return $count > 0;
	}
}

