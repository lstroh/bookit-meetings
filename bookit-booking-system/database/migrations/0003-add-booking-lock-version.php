<?php
/**
 * Migration: Add booking lock version column and backfill tokens.
 *
 * @package    Bookit_Booking_System
 * @subpackage Bookit_Booking_System/database/migrations
 */

if ( ! defined( 'WPINC' ) ) {
	die;
}

require_once BOOKIT_PLUGIN_DIR . 'database/migrations/class-bookit-migration-base.php';
require_once BOOKIT_PLUGIN_DIR . 'includes/utils/class-bookit-reference-generator.php';

/**
 * Adds optimistic locking token support for bookings.
 */
class Bookit_Migration_0003_Add_Booking_Lock_Version extends Bookit_Migration_Base {

	/**
	 * Return migration ID.
	 *
	 * @return string
	 */
	public function migration_id(): string {
		return '0003-add-booking-lock-version';
	}

	/**
	 * Run migration.
	 *
	 * @return void
	 */
	public function up(): void {
		global $wpdb;

		$bookings_table = $wpdb->prefix . 'bookings';

		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.DirectDatabaseQuery.DirectQuery
		$wpdb->query( "ALTER TABLE {$bookings_table} ADD COLUMN lock_version VARCHAR(32) NULL AFTER updated_at" );

		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.DirectDatabaseQuery.DirectQuery
		$rows = $wpdb->get_results( "SELECT id, updated_at FROM {$bookings_table} WHERE lock_version IS NULL" );

		if ( empty( $rows ) ) {
			return;
		}

		foreach ( $rows as $row ) {
			$lock_version = Bookit_Reference_Generator::generate_lock_version(
				(int) $row->id,
				(string) $row->updated_at
			);

			$wpdb->query(
				$wpdb->prepare(
					"UPDATE {$bookings_table} SET lock_version = %s WHERE id = %d",
					$lock_version,
					(int) $row->id
				)
			);
		}
	}

	/**
	 * Roll back migration.
	 *
	 * @return void
	 */
	public function down(): void {
		global $wpdb;

		$bookings_table = $wpdb->prefix . 'bookings';

		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.DirectDatabaseQuery.DirectQuery
		$wpdb->query( "ALTER TABLE {$bookings_table} DROP COLUMN lock_version" );
	}
}
