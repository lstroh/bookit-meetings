<?php
/**
 * Migration: Add magic_link_token column and index to bookings.
 *
 * @package    Bookit_Booking_System
 * @subpackage Bookit_Booking_System/database/migrations
 */

if ( ! defined( 'WPINC' ) ) {
	die;
}

/**
 * Adds customer self-service magic link token support for bookings.
 */
class Bookit_Migration_0012_Add_Magic_Link_Token extends Bookit_Migration_Base {

	/**
	 * Return migration ID.
	 *
	 * @return string
	 */
	public function migration_id(): string {
		return '0012-add-magic-link-token';
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

		$bookings_table = $wpdb->prefix . 'bookings';

		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.DirectDatabaseQuery.DirectQuery
		$column_exists = $wpdb->get_results( "SHOW COLUMNS FROM {$bookings_table} LIKE 'magic_link_token'" );
		if ( empty( $column_exists ) ) {
			// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.DirectDatabaseQuery.DirectQuery
			$wpdb->query( "ALTER TABLE {$bookings_table} ADD COLUMN magic_link_token VARCHAR(64) NULL DEFAULT NULL AFTER lock_version" );
		}

		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.DirectDatabaseQuery.DirectQuery
		$index_rows = $wpdb->get_results( "SHOW INDEX FROM {$bookings_table}" );
		$has_magic_idx = false;
		foreach ( (array) $index_rows as $idx_row ) {
			if ( isset( $idx_row->Key_name ) && 'idx_magic_link_token' === $idx_row->Key_name ) {
				$has_magic_idx = true;
				break;
			}
		}
		if ( ! $has_magic_idx ) {
			// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.DirectDatabaseQuery.DirectQuery
			$wpdb->query( "ALTER TABLE {$bookings_table} ADD KEY idx_magic_link_token (magic_link_token)" );
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
		$index_rows = $wpdb->get_results( "SHOW INDEX FROM {$bookings_table}" );
		$has_magic_idx = false;
		foreach ( (array) $index_rows as $idx_row ) {
			if ( isset( $idx_row->Key_name ) && 'idx_magic_link_token' === $idx_row->Key_name ) {
				$has_magic_idx = true;
				break;
			}
		}
		if ( $has_magic_idx ) {
			// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.DirectDatabaseQuery.DirectQuery
			$wpdb->query( "ALTER TABLE {$bookings_table} DROP INDEX idx_magic_link_token" );
		}

		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.DirectDatabaseQuery.DirectQuery
		$column_exists = $wpdb->get_results( "SHOW COLUMNS FROM {$bookings_table} LIKE 'magic_link_token'" );
		if ( ! empty( $column_exists ) ) {
			// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.DirectDatabaseQuery.DirectQuery
			$wpdb->query( "ALTER TABLE {$bookings_table} DROP COLUMN magic_link_token" );
		}
	}
}
