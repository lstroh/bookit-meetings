<?php
/**
 * Migration: Add notification_preferences column to staff table.
 *
 * @package    Bookit_Booking_System
 * @subpackage Bookit_Booking_System/database/migrations
 */

if ( ! defined( 'WPINC' ) ) {
	die;
}

require_once BOOKIT_PLUGIN_DIR . 'database/migrations/class-bookit-migration-base.php';

/**
 * Stores per-staff notification channel preferences as JSON.
 */
class Bookit_Migration_0016_Add_Notification_Preferences_To_Staff extends Bookit_Migration_Base {

	/**
	 * Return migration ID.
	 *
	 * @return string
	 */
	public function migration_id(): string {
		return '0016-add-notification-preferences-to-staff';
	}

	/**
	 * Run migration.
	 *
	 * @return void
	 */
	public function up(): void {
		global $wpdb;

		$staff_table = $wpdb->prefix . 'bookings_staff';

		if ( $this->column_exists( $staff_table, 'notification_preferences' ) ) {
			return;
		}

		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.DirectDatabaseQuery.DirectQuery
		$wpdb->query(
			"ALTER TABLE {$staff_table}
				ADD COLUMN notification_preferences LONGTEXT NULL DEFAULT NULL
				COMMENT 'JSON: {\"new_booking\":\"immediate\",\"reschedule\":\"immediate\",\"cancellation\":\"immediate\",\"daily_schedule\":false}'"
		);
	}

	/**
	 * Roll back migration.
	 *
	 * @return void
	 */
	public function down(): void {
		global $wpdb;

		$staff_table = $wpdb->prefix . 'bookings_staff';

		if ( ! $this->column_exists( $staff_table, 'notification_preferences' ) ) {
			return;
		}

		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.DirectDatabaseQuery.DirectQuery
		$wpdb->query( "ALTER TABLE {$staff_table} DROP COLUMN notification_preferences" );
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
