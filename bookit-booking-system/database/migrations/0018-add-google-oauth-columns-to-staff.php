<?php
/**
 * Migration: Add Google Calendar OAuth columns to staff table.
 *
 * @package    Bookit_Booking_System
 * @subpackage Bookit_Booking_System/database/migrations
 */

if ( ! defined( 'WPINC' ) ) {
	die;
}

require_once BOOKIT_PLUGIN_DIR . 'database/migrations/class-bookit-migration-base.php';

/**
 * Stores Google OAuth tokens and connection state per staff member.
 */
class Bookit_Migration_0018_Add_Google_Oauth_Columns_To_Staff extends Bookit_Migration_Base {

	/**
	 * Return migration ID.
	 *
	 * @return string
	 */
	public function migration_id(): string {
		return '0018-add-google-oauth-columns-to-staff';
	}

	/**
	 * Run migration.
	 *
	 * @return void
	 */
	public function up(): void {
		global $wpdb;

		$staff_table = $wpdb->prefix . 'bookings_staff';

		if ( ! $this->column_exists( $staff_table, 'google_oauth_access_token' ) ) {
			// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.DirectDatabaseQuery.DirectQuery
			$wpdb->query( "ALTER TABLE {$staff_table} ADD COLUMN google_oauth_access_token TEXT NULL" );
		}

		if ( ! $this->column_exists( $staff_table, 'google_oauth_refresh_token' ) ) {
			// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.DirectDatabaseQuery.DirectQuery
			$wpdb->query( "ALTER TABLE {$staff_table} ADD COLUMN google_oauth_refresh_token TEXT NULL" );
		}

		if ( ! $this->column_exists( $staff_table, 'google_oauth_token_expiry' ) ) {
			// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.DirectDatabaseQuery.DirectQuery
			$wpdb->query( "ALTER TABLE {$staff_table} ADD COLUMN google_oauth_token_expiry DATETIME NULL" );
		}

		if ( ! $this->column_exists( $staff_table, 'google_calendar_connected' ) ) {
			// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.DirectDatabaseQuery.DirectQuery
			$wpdb->query(
				"ALTER TABLE {$staff_table} ADD COLUMN google_calendar_connected TINYINT(1) NOT NULL DEFAULT 0"
			);
		}

		if ( ! $this->column_exists( $staff_table, 'google_calendar_email' ) ) {
			// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.DirectDatabaseQuery.DirectQuery
			$wpdb->query( "ALTER TABLE {$staff_table} ADD COLUMN google_calendar_email VARCHAR(255) NULL" );
		}
	}

	/**
	 * Roll back migration.
	 *
	 * @return void
	 */
	public function down(): void {
		global $wpdb;

		$staff_table = $wpdb->prefix . 'bookings_staff';

		if ( $this->column_exists( $staff_table, 'google_calendar_email' ) ) {
			// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.DirectDatabaseQuery.DirectQuery
			$wpdb->query( "ALTER TABLE {$staff_table} DROP COLUMN google_calendar_email" );
		}

		if ( $this->column_exists( $staff_table, 'google_calendar_connected' ) ) {
			// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.DirectDatabaseQuery.DirectQuery
			$wpdb->query( "ALTER TABLE {$staff_table} DROP COLUMN google_calendar_connected" );
		}

		if ( $this->column_exists( $staff_table, 'google_oauth_token_expiry' ) ) {
			// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.DirectDatabaseQuery.DirectQuery
			$wpdb->query( "ALTER TABLE {$staff_table} DROP COLUMN google_oauth_token_expiry" );
		}

		if ( $this->column_exists( $staff_table, 'google_oauth_refresh_token' ) ) {
			// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.DirectDatabaseQuery.DirectQuery
			$wpdb->query( "ALTER TABLE {$staff_table} DROP COLUMN google_oauth_refresh_token" );
		}

		if ( $this->column_exists( $staff_table, 'google_oauth_access_token' ) ) {
			// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.DirectDatabaseQuery.DirectQuery
			$wpdb->query( "ALTER TABLE {$staff_table} DROP COLUMN google_oauth_access_token" );
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
