<?php
/**
 * Migration: Add audit log table.
 *
 * @package    Bookit_Booking_System
 * @subpackage Bookit_Booking_System/database/migrations
 */

if ( ! defined( 'WPINC' ) ) {
	die;
}

require_once BOOKIT_PLUGIN_DIR . 'database/migrations/class-bookit-migration-base.php';

/**
 * Creates the audit log table.
 */
class Bookit_Migration_0002_Add_Audit_Log extends Bookit_Migration_Base {

	/**
	 * Return migration ID.
	 *
	 * @return string
	 */
	public function migration_id(): string {
		return '0002-add-audit-log';
	}

	/**
	 * Run migration.
	 *
	 * @return void
	 */
	public function up(): void {
		global $wpdb;

		$table_name = $wpdb->prefix . 'bookings_audit_log';

		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.DirectDatabaseQuery.DirectQuery
		$wpdb->query(
			"CREATE TABLE IF NOT EXISTS {$table_name} (
				id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
				actor_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
				actor_type ENUM('admin','staff','customer','system') NOT NULL,
				actor_ip VARCHAR(45) NULL,
				action VARCHAR(100) NOT NULL,
				object_type VARCHAR(50) NOT NULL,
				object_id BIGINT UNSIGNED NULL,
				old_value LONGTEXT NULL,
				new_value LONGTEXT NULL,
				notes TEXT NULL,
				created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
				INDEX idx_actor_id (actor_id),
				INDEX idx_action (action),
				INDEX idx_object (object_type, object_id),
				INDEX idx_created_at (created_at)
			) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;"
		);
	}

	/**
	 * Roll back migration.
	 *
	 * @return void
	 */
	public function down(): void {
		global $wpdb;

		$table_name = $wpdb->prefix . 'bookings_audit_log';

		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.DirectDatabaseQuery.DirectQuery
		$wpdb->query( "DROP TABLE IF EXISTS {$table_name}" );
	}
}
