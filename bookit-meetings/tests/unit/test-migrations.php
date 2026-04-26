<?php

if ( ! function_exists( 'bookit_test_table_exists' ) ) {
	/**
	 * Check whether a test database table exists.
	 *
	 * @param string $full_table_name Full table name with prefix.
	 * @return bool
	 */
	function bookit_test_table_exists( string $full_table_name ): bool {
		global $wpdb;

		$table = $wpdb->get_var(
			$wpdb->prepare(
				'SHOW TABLES LIKE %s',
				$full_table_name
			)
		);

		return $table === $full_table_name;
	}
}

if ( ! function_exists( 'bookit_test_truncate_tables' ) ) {
	/**
	 * Truncate tables in a FK-safe block for tests.
	 *
	 * @param array<int, string> $table_suffixes Table suffixes without prefix.
	 * @return void
	 */
	function bookit_test_truncate_tables( array $table_suffixes ): void {
		global $wpdb;

		$unique_suffixes = array_values( array_unique( $table_suffixes ) );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
		$wpdb->query( 'SET FOREIGN_KEY_CHECKS = 0' );
		try {
			foreach ( $unique_suffixes as $table_suffix ) {
				$full_table = $wpdb->prefix . $table_suffix;
				if ( ! bookit_test_table_exists( $full_table ) ) {
					continue;
				}

				// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.DirectDatabaseQuery.DirectQuery
				$wpdb->query( "TRUNCATE TABLE {$full_table}" );
			}
		} finally {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
			$wpdb->query( 'SET FOREIGN_KEY_CHECKS = 1' );
		}
	}
}

class Test_Bookit_Meetings_Migrations extends WP_UnitTestCase {
	private Bookit_Migration_Meetings_0001_Add_Meetings_Schema $migration;

	public function setUp(): void {
		parent::setUp();

		require_once BOOKIT_MEETINGS_PLUGIN_DIR . 'database/migrations/0001-add-meetings-schema.php';

		$this->migration = new Bookit_Migration_Meetings_0001_Add_Meetings_Schema();

		$this->ensure_meetings_schema_exists();
		bookit_test_truncate_tables( array( 'bookings_settings' ) );
	}

	public function tearDown(): void {
		bookit_test_truncate_tables( array( 'bookings_settings' ) );
		parent::tearDown();
	}

	private function ensure_meetings_schema_exists(): void {
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
		$column_exists = (int) $wpdb->get_var( $sql );

		if ( $column_exists > 0 ) {
			return;
		}

		$this->migration->up();
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
}

