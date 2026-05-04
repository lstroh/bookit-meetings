<?php
/**
 * Migration: Add wp_bookings_staff_working_hours table
 *
 * Run Date: 2026-01-29
 * Sprint: Sprint 1, Task 5
 * Reason: Time slot availability algorithm (working hours, exceptions, breaks)
 *
 * @package    Bookit_Booking_System
 * @subpackage Bookit_Booking_System/database/migrations
 */

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
	die;
}

/**
 * Migration class to add staff working hours table.
 */
class Bookit_Migration_Add_Staff_Working_Hours {

	/**
	 * WordPress database instance.
	 *
	 * @var wpdb
	 */
	private $wpdb;

	/**
	 * Constructor.
	 */
	public function __construct() {
		global $wpdb;
		$this->wpdb = $wpdb;
	}

	/**
	 * Run the migration.
	 *
	 * @return bool Success status
	 */
	public function up() {
		$table_name = $this->wpdb->prefix . 'bookings_staff_working_hours';

		// Skip if table already exists.
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		if ( $this->wpdb->get_var( $this->wpdb->prepare( 'SHOW TABLES LIKE %s', $table_name ) ) === $table_name ) {
			return true;
		}

		$charset_collate = $this->wpdb->get_charset_collate();

		$sql = "CREATE TABLE $table_name (
			id INT UNSIGNED NOT NULL AUTO_INCREMENT,
			staff_id INT UNSIGNED NOT NULL,
			day_of_week TINYINT(1) NULL COMMENT '1=Monday, 7=Sunday',
			specific_date DATE NULL COMMENT 'Exception date (vacation, etc.)',
			start_time TIME NOT NULL,
			end_time TIME NOT NULL,
			is_working TINYINT(1) DEFAULT 1 COMMENT '0=blocked/vacation',
			break_start TIME NULL,
			break_end TIME NULL,
			repeat_weekly TINYINT(1) DEFAULT 1,
			valid_from DATE NULL,
			valid_until DATE NULL,
			notes TEXT NULL,
			created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
			updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
			PRIMARY KEY (id),
			KEY idx_staff_day (staff_id, day_of_week),
			KEY idx_staff_date (staff_id, specific_date),
			KEY idx_specific_date (specific_date)
		) $charset_collate;";

		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		$result = $this->wpdb->query( $sql );

		return false !== $result;
	}

	/**
	 * Reverse the migration.
	 *
	 * @return bool Success status
	 */
	public function down() {
		$table_name = $this->wpdb->prefix . 'bookings_staff_working_hours';
		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.DirectDatabaseQuery.DirectQuery
		return false !== $this->wpdb->query( "DROP TABLE IF EXISTS $table_name" );
	}
}
