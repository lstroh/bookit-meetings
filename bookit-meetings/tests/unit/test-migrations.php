<?php

class Test_Bookit_Meetings_Migrations extends WP_UnitTestCase {
	private Bookit_Migration_Meetings_0001_Add_Meetings_Schema $migration;

	public function setUp(): void {
		parent::setUp();

		require_once BOOKIT_MEETINGS_PLUGIN_DIR . 'database/migrations/0001-add-meetings-schema.php';

		$this->migration = new Bookit_Migration_Meetings_0001_Add_Meetings_Schema();
		$this->migration->down();
	}

	public function tearDown(): void {
		$this->migration->down();
		parent::tearDown();
	}

	private function meeting_link_column_exists(): bool {
		global $wpdb;

		$table_name = $wpdb->prefix . 'bookings';
		$sql        = $wpdb->prepare(
			"SELECT COUNT(*)
			FROM information_schema.COLUMNS
			WHERE TABLE_SCHEMA = %s
				AND TABLE_NAME = %s
				AND COLUMN_NAME = %s",
			DB_NAME,
			$table_name,
			'meeting_link'
		);

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.PreparedSQL.NotPrepared
		return (int) $wpdb->get_var( $sql ) > 0;
	}

	private function credentials_table_exists(): bool {
		global $wpdb;

		$table_name = $wpdb->prefix . 'bookit_meetings_credentials';
		$sql        = $wpdb->prepare(
			"SELECT COUNT(*)
			FROM information_schema.TABLES
			WHERE TABLE_SCHEMA = %s
				AND TABLE_NAME = %s",
			DB_NAME,
			$table_name
		);

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.PreparedSQL.NotPrepared
		return (int) $wpdb->get_var( $sql ) > 0;
	}

	private function get_setting_value( string $setting_key ): ?string {
		global $wpdb;

		$table_name = $wpdb->prefix . 'bookings_settings';
		$sql        = $wpdb->prepare(
			"SELECT COALESCE(setting_value, '') FROM {$table_name} WHERE setting_key = %s LIMIT 1",
			$setting_key
		);

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.PreparedSQL.NotPrepared
		$value = $wpdb->get_var( $sql );

		return null === $value ? null : (string) $value;
	}

	private function count_setting_key( string $setting_key ): int {
		global $wpdb;

		$table_name = $wpdb->prefix . 'bookings_settings';
		$sql        = $wpdb->prepare(
			"SELECT COUNT(*) FROM {$table_name} WHERE setting_key = %s",
			$setting_key
		);

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.PreparedSQL.NotPrepared
		return (int) $wpdb->get_var( $sql );
	}

	public function test_meeting_link_column_exists_after_up(): void {
		$this->migration->up();
		$this->assertTrue( $this->meeting_link_column_exists() );
	}

	public function test_credentials_table_exists_after_up(): void {
		$this->migration->up();
		$this->assertTrue( $this->credentials_table_exists() );
	}

	public function test_settings_rows_inserted_after_up(): void {
		$this->migration->up();

		$this->assertSame( '0', $this->get_setting_value( 'meetings_enabled' ) );
		$this->assertSame( '', $this->get_setting_value( 'meetings_platform' ) );
		$this->assertSame( '', $this->get_setting_value( 'meetings_manual_url' ) );
	}

	public function test_up_is_idempotent(): void {
		global $wpdb;

		$this->migration->up();
		$this->migration->up();

		$this->assertSame( '', (string) $wpdb->last_error );
		$this->assertTrue( $this->meeting_link_column_exists() );
		$this->assertTrue( $this->credentials_table_exists() );

		$this->assertSame( 1, $this->count_setting_key( 'meetings_enabled' ) );
		$this->assertSame( 1, $this->count_setting_key( 'meetings_platform' ) );
		$this->assertSame( 1, $this->count_setting_key( 'meetings_manual_url' ) );

		$this->assertSame( '0', $this->get_setting_value( 'meetings_enabled' ) );
		$this->assertSame( '', $this->get_setting_value( 'meetings_platform' ) );
		$this->assertSame( '', $this->get_setting_value( 'meetings_manual_url' ) );
	}

	public function test_meeting_link_column_removed_after_down(): void {
		$this->migration->up();
		$this->migration->down();

		$this->assertFalse( $this->meeting_link_column_exists() );
	}

	public function test_credentials_table_removed_after_down(): void {
		$this->migration->up();
		$this->migration->down();

		$this->assertFalse( $this->credentials_table_exists() );
	}

	public function test_settings_rows_removed_after_down(): void {
		$this->migration->up();
		$this->migration->down();

		$this->assertSame( 0, $this->count_setting_key( 'meetings_enabled' ) );
		$this->assertSame( 0, $this->count_setting_key( 'meetings_platform' ) );
		$this->assertSame( 0, $this->count_setting_key( 'meetings_manual_url' ) );
	}
}

