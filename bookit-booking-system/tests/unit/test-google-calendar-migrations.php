<?php
/**
 * Google Calendar OAuth migration 0018 and bookings column presence.
 *
 * @package Bookit_Booking_System
 */

/**
 * @covers Bookit_Migration_0018_Add_Google_Oauth_Columns_To_Staff
 */
class Test_Google_Calendar_Migrations extends WP_UnitTestCase {

	/**
	 * Plugin root path.
	 *
	 * @return string
	 */
	private function plugin_dir(): string {
		return dirname( dirname( __DIR__ ) );
	}

	/**
	 * Whether a column exists on a table (information_schema, current database).
	 *
	 * @param string $table Full table name.
	 * @param string $column Column name.
	 * @return bool
	 */
	private function column_exists_via_information_schema( string $table, string $column ): bool {
		global $wpdb;

		$sql = $wpdb->prepare(
			'SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = %s AND COLUMN_NAME = %s',
			$table,
			$column
		);

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.PreparedSQL.NotPrepared
		return (int) $wpdb->get_var( $sql ) > 0;
	}

	/**
	 * Migration 0018 adds all OAuth-related columns to wp_bookings_staff.
	 *
	 * @covers Bookit_Migration_0018_Add_Google_Oauth_Columns_To_Staff::up
	 */
	public function test_migration_0018_adds_oauth_columns_to_staff_table(): void {
		global $wpdb;

		$migration_file = $this->plugin_dir() . '/database/migrations/0018-add-google-oauth-columns-to-staff.php';
		$this->assertFileExists( $migration_file );
		require_once $migration_file;

		$table     = $wpdb->prefix . 'bookings_staff';
		$migration = new Bookit_Migration_0018_Add_Google_Oauth_Columns_To_Staff();
		$migration->up();

		$expected = array(
			'google_oauth_access_token',
			'google_oauth_refresh_token',
			'google_oauth_token_expiry',
			'google_calendar_connected',
			'google_calendar_email',
		);

		foreach ( $expected as $column ) {
			$this->assertTrue(
				$this->column_exists_via_information_schema( $table, $column ),
				"Column {$column} should exist on {$table}"
			);
		}
	}

	/**
	 * Migration 0018 up() is idempotent (no DB error on second run).
	 *
	 * @covers Bookit_Migration_0018_Add_Google_Oauth_Columns_To_Staff::up
	 */
	public function test_migration_0018_is_idempotent(): void {
		global $wpdb;

		require_once $this->plugin_dir() . '/database/migrations/0018-add-google-oauth-columns-to-staff.php';

		$migration = new Bookit_Migration_0018_Add_Google_Oauth_Columns_To_Staff();
		$migration->up();
		$migration->up();

		$this->assertEmpty( $wpdb->last_error, 'Second up() should not set wpdb last_error' );
	}

	/**
	 * wp_bookings includes google_calendar_event_id (core schema; no migration 0019 in this sprint).
	 *
	 * @coversNothing
	 */
	public function test_google_calendar_event_id_exists_in_bookings_table(): void {
		global $wpdb;

		$table = $wpdb->prefix . 'bookings';
		$this->assertTrue(
			$this->column_exists_via_information_schema( $table, 'google_calendar_event_id' ),
			'google_calendar_event_id should exist on wp_bookings'
		);
	}
}
