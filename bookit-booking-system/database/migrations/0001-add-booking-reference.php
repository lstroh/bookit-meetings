<?php
/**
 * Migration: Add booking reference column and backfill data.
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
 * Adds BK[YYMM]-[XXXX] booking references.
 */
class Bookit_Migration_0001_Add_Booking_Reference extends Bookit_Migration_Base {

	/**
	 * Return migration ID.
	 *
	 * @return string
	 */
	public function migration_id(): string {
		return '0001-add-booking-reference';
	}

	/**
	 * Run migration.
	 *
	 * @return void
	 * @throws Throwable When migration fails.
	 */
	public function up(): void {
		global $wpdb;

		try {
			$bookings_table = $wpdb->prefix . 'bookings';

			// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.DirectDatabaseQuery.DirectQuery
			$wpdb->query( "ALTER TABLE {$bookings_table} ADD COLUMN booking_reference VARCHAR(12) NULL AFTER id" );

			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
			$rows = $wpdb->get_results(
				"SELECT id, created_at FROM {$bookings_table} WHERE booking_reference IS NULL"
			);

			if ( ! empty( $rows ) ) {
				$processed = 0;
				foreach ( $rows as $row ) {
					$reference = Bookit_Reference_Generator::generate_unique( (int) $row->id, (string) $row->created_at );

					$wpdb->query(
						$wpdb->prepare(
							"UPDATE {$bookings_table} SET booking_reference = %s WHERE id = %d",
							$reference,
							(int) $row->id
						)
					);

					++$processed;
					if ( 0 === ( $processed % 100 ) ) {
						Bookit_Logger::info(
							'Booking reference migration progress',
							array(
								'processed' => $processed,
								'total'     => count( $rows ),
							)
						);
					}
				}
			}

			// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.DirectDatabaseQuery.DirectQuery
			$wpdb->query( "ALTER TABLE {$bookings_table} ADD UNIQUE KEY uq_booking_reference (booking_reference)" );
		} catch ( Throwable $exception ) {
			Bookit_Logger::error(
				'Failed running booking reference migration',
				array(
					'error' => $exception->getMessage(),
				)
			);

			throw $exception;
		}
	}

	/**
	 * Rollback migration.
	 *
	 * @return void
	 */
	public function down(): void {
		global $wpdb;

		$bookings_table = $wpdb->prefix . 'bookings';

		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.DirectDatabaseQuery.DirectQuery
		$wpdb->query( "ALTER TABLE {$bookings_table} DROP KEY uq_booking_reference" );

		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.DirectDatabaseQuery.DirectQuery
		$wpdb->query( "ALTER TABLE {$bookings_table} DROP COLUMN booking_reference" );
	}
}
