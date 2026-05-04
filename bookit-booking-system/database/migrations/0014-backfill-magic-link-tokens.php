<?php
/**
 * Migration: Backfill magic_link_token for existing bookings.
 *
 * @package    Bookit_Booking_System
 * @subpackage Bookit_Booking_System/database/migrations
 */

if ( ! defined( 'WPINC' ) ) {
	die;
}

/**
 * Generates magic_link_token per row for legacy bookings.
 */
class Bookit_Migration_0014_Backfill_Magic_Link_Tokens extends Bookit_Migration_Base {

	/**
	 * Return migration ID.
	 *
	 * @return string
	 */
	public function migration_id(): string {
		return '0014-backfill-magic-link-tokens';
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
		if ( ! function_exists( 'wp_generate_password' ) ) {
			require_once ABSPATH . 'wp-includes/pluggable.php';
		}

		global $wpdb;

		$bookings_table = $wpdb->prefix . 'bookings';

		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.DirectDatabaseQuery.DirectQuery
		$rows = $wpdb->get_results( "SELECT id FROM {$bookings_table} WHERE magic_link_token IS NULL" );

		if ( empty( $rows ) ) {
			return;
		}

		foreach ( $rows as $row ) {
			$token = wp_generate_password( 32, false, false );
			$wpdb->update(
				$bookings_table,
				array( 'magic_link_token' => $token ),
				array( 'id' => (int) $row->id ),
				array( '%s' ),
				array( '%d' )
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
		$wpdb->query( "UPDATE {$bookings_table} SET magic_link_token = NULL" );
	}
}
