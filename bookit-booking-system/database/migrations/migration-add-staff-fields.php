<?php
/**
 * Migration: Add Staff Photo, Bio, Title, and Custom Pricing
 * 
 * Run Date: 2026-01-28
 * Sprint: Sprint 1, Task 3
 * Reason: Support staff selection UI with photos, bios, and variable pricing
 * 
 * @package    Bookit_Booking_System
 * @subpackage Bookit_Booking_System/database/migrations
 */

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
	die;
}

/**
 * Migration class to add missing staff fields.
 */
class Bookit_Migration_Add_Staff_Fields {

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
		$errors = array();

		// Add columns to wp_bookings_staff.
		if ( ! $this->column_exists( 'bookings_staff', 'photo_url' ) ) {
			// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
			$result = $this->wpdb->query(
				"ALTER TABLE {$this->wpdb->prefix}bookings_staff 
				 ADD COLUMN photo_url VARCHAR(500) NULL AFTER phone"
			);
			if ( false === $result ) {
				$errors[] = 'Failed to add photo_url: ' . $this->wpdb->last_error;
			} else {
				// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
				error_log( '✅ Added photo_url to bookings_staff' );
			}
		} else {
			// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
			error_log( '⏭️  photo_url already exists in bookings_staff' );
		}

		if ( ! $this->column_exists( 'bookings_staff', 'bio' ) ) {
			// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
			$result = $this->wpdb->query(
				"ALTER TABLE {$this->wpdb->prefix}bookings_staff 
				 ADD COLUMN bio TEXT NULL AFTER photo_url"
			);
			if ( false === $result ) {
				$errors[] = 'Failed to add bio: ' . $this->wpdb->last_error;
			} else {
				// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
				error_log( '✅ Added bio to bookings_staff' );
			}
		} else {
			// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
			error_log( '⏭️  bio already exists in bookings_staff' );
		}

		if ( ! $this->column_exists( 'bookings_staff', 'title' ) ) {
			// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
			$result = $this->wpdb->query(
				"ALTER TABLE {$this->wpdb->prefix}bookings_staff 
				 ADD COLUMN title VARCHAR(100) NULL AFTER bio"
			);
			if ( false === $result ) {
				$errors[] = 'Failed to add title: ' . $this->wpdb->last_error;
			} else {
				// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
				error_log( '✅ Added title to bookings_staff' );
			}
		} else {
			// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
			error_log( '⏭️  title already exists in bookings_staff' );
		}

		// Add custom_price to wp_bookings_staff_services.
		if ( ! $this->column_exists( 'bookings_staff_services', 'custom_price' ) ) {
			// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
			$result = $this->wpdb->query(
				"ALTER TABLE {$this->wpdb->prefix}bookings_staff_services 
				 ADD COLUMN custom_price DECIMAL(10,2) NULL COMMENT 'Staff-specific price override (NULL = use service base price)' AFTER service_id"
			);
			if ( false === $result ) {
				$errors[] = 'Failed to add custom_price: ' . $this->wpdb->last_error;
			} else {
				// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
				error_log( '✅ Added custom_price to bookings_staff_services' );
			}
		} else {
			// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
			error_log( '⏭️  custom_price already exists in bookings_staff_services' );
		}

		if ( empty( $errors ) ) {
			// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
			error_log( '🎉 Migration completed successfully!' );
			return true;
		} else {
			// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
			error_log( '❌ Migration completed with errors:' );
			foreach ( $errors as $error ) {
				// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
				error_log( '  - ' . $error );
			}
			return false;
		}
	}

	/**
	 * Rollback the migration (optional, for safety).
	 *
	 * @return void
	 */
	public function down() {
		// Only drop columns if they exist and have no data.
		// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
		error_log( '⚠️  Rollback not implemented. Manually drop columns if needed.' );
	}

	/**
	 * Check if column exists in table.
	 *
	 * @param string $table  Table name (without prefix).
	 * @param string $column Column name.
	 * @return bool True if column exists, false otherwise.
	 */
	private function column_exists( $table, $column ) {
		$full_table = $this->wpdb->prefix . $table;
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
		$results = $this->wpdb->get_results(
			$this->wpdb->prepare(
				"SHOW COLUMNS FROM `{$full_table}` LIKE %s",
				$column
			)
		);
		return ! empty( $results );
	}
}
