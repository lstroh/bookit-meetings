<?php
/**
 * Migration: Make booking start/end times nullable on cancel (free unique slot) and add audit columns.
 *
 * @package    Bookit_Booking_System
 * @subpackage Bookit_Booking_System/database/migrations
 */
 
if ( ! defined( 'WPINC' ) ) {
	die;
}
 
require_once BOOKIT_PLUGIN_DIR . 'database/migrations/class-bookit-migration-base.php';
 
/**
 * Frees unique booking slot on cancellation by NULLing start/end time and preserves originals.
 */
class Bookit_Migration_0020_Nullable_Booking_Times_Cancelled_Audit extends Bookit_Migration_Base {
 
	/**
	 * Return migration ID.
	 *
	 * @return string
	 */
	public function migration_id(): string {
		return '0020-nullable-booking-times-cancelled-audit';
	}
 
	/**
	 * Run migration.
	 *
	 * @return void
	 */
	public function up(): void {
		global $wpdb;
 
		// Step 1: start_time/end_time must allow NULL (safe to repeat).
		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.DirectDatabaseQuery.DirectQuery
		$wpdb->query( "ALTER TABLE {$wpdb->prefix}bookings MODIFY start_time TIME NULL DEFAULT NULL" );
		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.DirectDatabaseQuery.DirectQuery
		$wpdb->query( "ALTER TABLE {$wpdb->prefix}bookings MODIFY end_time TIME NULL DEFAULT NULL" );
 
		// Step 2: Add cancelled_start_time (guarded).
		if ( ! $this->column_exists( $wpdb->prefix . 'bookings', 'cancelled_start_time' ) ) {
			// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.DirectDatabaseQuery.DirectQuery
			$wpdb->query(
				"ALTER TABLE {$wpdb->prefix}bookings
				 ADD COLUMN cancelled_start_time TIME NULL DEFAULT NULL AFTER end_time"
			);
		}
 
		// Step 3: Add cancelled_end_time (guarded).
		if ( ! $this->column_exists( $wpdb->prefix . 'bookings', 'cancelled_end_time' ) ) {
			// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.DirectDatabaseQuery.DirectQuery
			$wpdb->query(
				"ALTER TABLE {$wpdb->prefix}bookings
				 ADD COLUMN cancelled_end_time TIME NULL DEFAULT NULL AFTER cancelled_start_time"
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
 
		if ( $this->column_exists( $wpdb->prefix . 'bookings', 'cancelled_end_time' ) ) {
			// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.DirectDatabaseQuery.DirectQuery
			$wpdb->query( "ALTER TABLE {$wpdb->prefix}bookings DROP COLUMN cancelled_end_time" );
		}
 
		if ( $this->column_exists( $wpdb->prefix . 'bookings', 'cancelled_start_time' ) ) {
			// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.DirectDatabaseQuery.DirectQuery
			$wpdb->query( "ALTER TABLE {$wpdb->prefix}bookings DROP COLUMN cancelled_start_time" );
		}
 
		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.DirectDatabaseQuery.DirectQuery
		$wpdb->query( "ALTER TABLE {$wpdb->prefix}bookings MODIFY start_time TIME NOT NULL" );
		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.DirectDatabaseQuery.DirectQuery
		$wpdb->query( "ALTER TABLE {$wpdb->prefix}bookings MODIFY end_time TIME NOT NULL" );
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

