<?php
/**
 * Migration: Create package redemptions table.
 *
 * @package    Bookit_Booking_System
 * @subpackage Bookit_Booking_System/database/migrations
 */

if ( ! defined( 'WPINC' ) ) {
	die;
}

require_once BOOKIT_PLUGIN_DIR . 'database/migrations/class-bookit-migration-base.php';

/**
 * Tracks package usage against bookings.
 */
class Bookit_Migration_0007_Create_Package_Redemptions_Table extends Bookit_Migration_Base {

	/**
	 * Return migration ID.
	 *
	 * @return string
	 */
	public function migration_id(): string {
		return '0007-create-package-redemptions-table';
	}

	/**
	 * Run migration.
	 *
	 * @return void
	 */
	public function up(): void {
		global $wpdb;

		$table_name        = $wpdb->prefix . 'bookings_package_redemptions';
		$packages_table    = $wpdb->prefix . 'bookings_customer_packages';
		$bookings_table    = $wpdb->prefix . 'bookings';
		$charset_collate   = $wpdb->get_charset_collate();

		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.DirectDatabaseQuery.DirectQuery
		$wpdb->query(
			"CREATE TABLE IF NOT EXISTS {$table_name} (
				id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
				customer_package_id BIGINT UNSIGNED NOT NULL,
				booking_id BIGINT UNSIGNED NOT NULL,
				redeemed_at DATETIME NOT NULL,
				redeemed_by BIGINT UNSIGNED NOT NULL COMMENT 'WP user ID of staff/admin who redeemed',
				notes TEXT NULL,
				created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
				PRIMARY KEY (id),
				KEY idx_customer_package_id (customer_package_id),
				KEY idx_booking_id (booking_id),
				CONSTRAINT fk_pr_customer_package
					FOREIGN KEY (customer_package_id) REFERENCES {$packages_table}(id),
				CONSTRAINT fk_pr_booking
					FOREIGN KEY (booking_id) REFERENCES {$bookings_table}(id)
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

		$table_name = $wpdb->prefix . 'bookings_package_redemptions';

		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.DirectDatabaseQuery.DirectQuery
		$wpdb->query( "DROP TABLE IF EXISTS {$table_name}" );
	}
}
