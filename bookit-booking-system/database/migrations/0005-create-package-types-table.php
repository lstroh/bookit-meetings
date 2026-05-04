<?php
/**
 * Migration: Create package types table.
 *
 * @package    Bookit_Booking_System
 * @subpackage Bookit_Booking_System/database/migrations
 */

if ( ! defined( 'WPINC' ) ) {
	die;
}

require_once BOOKIT_PLUGIN_DIR . 'database/migrations/class-bookit-migration-base.php';

/**
 * Creates package type definitions.
 */
class Bookit_Migration_0005_Create_Package_Types_Table extends Bookit_Migration_Base {

	/**
	 * Return migration ID.
	 *
	 * @return string
	 */
	public function migration_id(): string {
		return '0005-create-package-types-table';
	}

	/**
	 * Run migration.
	 *
	 * @return void
	 */
	public function up(): void {
		global $wpdb;

		$table_name       = $wpdb->prefix . 'bookings_package_types';
		$charset_collate = $wpdb->get_charset_collate();

		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.DirectDatabaseQuery.DirectQuery
		$wpdb->query(
			"CREATE TABLE IF NOT EXISTS {$table_name} (
				id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
				name VARCHAR(255) NOT NULL,
				description TEXT NULL,
				sessions_count INT UNSIGNED NOT NULL,
				price_mode ENUM('fixed', 'discount') NOT NULL,
				fixed_price DECIMAL(10,2) NULL,
				discount_percentage DECIMAL(5,2) NULL,
				expiry_enabled TINYINT(1) NOT NULL DEFAULT 0,
				expiry_days INT UNSIGNED NULL,
				applicable_service_ids LONGTEXT NULL COMMENT 'JSON array of service IDs; NULL = applies to all services',
				is_active TINYINT(1) NOT NULL DEFAULT 1,
				created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
				updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
				PRIMARY KEY (id),
				KEY idx_is_active (is_active)
			) ENGINE=InnoDB {$charset_collate};"
		);
	}

	/**
	 * Roll back migration.
	 *
	 * @return void
	 */
	public function down(): void {
		global $wpdb;

		$table_name = $wpdb->prefix . 'bookings_package_types';

		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.DirectDatabaseQuery.DirectQuery
		$wpdb->query( "DROP TABLE IF EXISTS {$table_name}" );
	}
}
