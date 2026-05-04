<?php
/**
 * Migration: Add wp_bookings_status_log table
 *
 * Run Date: 2026-02-23
 * Sprint: Sprint 4A, Task 1
 * Reason: Track booking status changes for audit and staff accountability
 *
 * @package    Bookit_Booking_System
 * @subpackage Bookit_Booking_System/database/migrations
 */

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
	die;
}

/**
 * Migration class to add booking status log table.
 */
class Bookit_Migration_Add_Status_Log {

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
		$table_name = $this->wpdb->prefix . 'bookings_status_log';

		// Skip if table already exists.
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		if ( $this->wpdb->get_var( $this->wpdb->prepare( 'SHOW TABLES LIKE %s', $table_name ) ) === $table_name ) {
			return true;
		}

		$charset_collate = $this->wpdb->get_charset_collate();

		$sql = "CREATE TABLE $table_name (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			booking_id BIGINT UNSIGNED NOT NULL,
			old_status VARCHAR(50) NOT NULL,
			new_status VARCHAR(50) NOT NULL,
			changed_by_staff_id BIGINT UNSIGNED NOT NULL,
			changed_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
			notes TEXT NULL,
			PRIMARY KEY (id),
			KEY idx_booking_id (booking_id),
			KEY idx_changed_by (changed_by_staff_id),
			KEY idx_changed_at (changed_at)
		) ENGINE=InnoDB $charset_collate;";

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		dbDelta( $sql );

		// Verify table was created.
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		return $this->wpdb->get_var( $this->wpdb->prepare( 'SHOW TABLES LIKE %s', $table_name ) ) === $table_name;
	}

	/**
	 * Reverse the migration.
	 *
	 * @return bool Success status
	 */
	public function down() {
		$table_name = $this->wpdb->prefix . 'bookings_status_log';
		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.DirectDatabaseQuery.DirectQuery
		return false !== $this->wpdb->query( "DROP TABLE IF EXISTS $table_name" );
	}
}
