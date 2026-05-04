<?php
/**
 * Migration: Drop legacy working hours table.
 *
 * @package    Bookit_Booking_System
 * @subpackage Bookit_Booking_System/database/migrations
 */

if ( ! defined( 'WPINC' ) ) {
	die;
}

require_once BOOKIT_PLUGIN_DIR . 'database/migrations/class-bookit-migration-base.php';

/**
 * Drops wp_bookings_working_hours (superseded by wp_bookings_staff_working_hours).
 */
class Bookit_Migration_0011_Drop_Working_Hours_Table extends Bookit_Migration_Base {

	/**
	 * Return migration ID.
	 *
	 * @return string
	 */
	public function migration_id(): string {
		return '0011-drop-working-hours-table';
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

		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.DirectDatabaseQuery.DirectQuery
		$wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}bookings_working_hours" );
	}

	/**
	 * Roll back migration.
	 *
	 * @return void
	 */
	public function down(): void {
		global $wpdb;

		$table = $wpdb->prefix . 'bookings_working_hours';

		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.DirectDatabaseQuery.DirectQuery
		$wpdb->query(
			"CREATE TABLE IF NOT EXISTS {$table} (
				id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
				staff_id BIGINT UNSIGNED NOT NULL,
				day_of_week TINYINT UNSIGNED NOT NULL COMMENT '0=Sunday, 6=Saturday',
				start_time TIME NOT NULL,
				end_time TIME NOT NULL,
				is_active TINYINT(1) DEFAULT 1,
				created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
				updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
					ON UPDATE CURRENT_TIMESTAMP,
				PRIMARY KEY (id),
				KEY idx_staff_id (staff_id),
				KEY idx_day_of_week (day_of_week),
				KEY idx_is_active (is_active)
			) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
		);
	}
}
