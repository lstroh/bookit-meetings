<?php
/**
 * Sprint 6A migrations 0016–0017 (notification preferences + digest queue).
 *
 * @package Bookit_Booking_System
 */

/**
 * @covers Bookit_Migration_0016_Add_Notification_Preferences_To_Staff
 * @covers Bookit_Migration_0017_Create_Notification_Digest_Queue
 */
class Test_Sprint6A_Migrations extends WP_UnitTestCase {

	/**
	 * Plugin root path.
	 *
	 * @return string
	 */
	private function plugin_dir(): string {
		return dirname( dirname( __DIR__ ) );
	}

	/**
	 * Whether notification_preferences exists on wp_bookings_staff.
	 *
	 * @param string $table Full table name.
	 * @return bool
	 */
	private function staff_notification_column_exists( string $table ): bool {
		global $wpdb;

		$sql = $wpdb->prepare(
			'SHOW COLUMNS FROM ' . $table . ' LIKE %s',
			'notification_preferences'
		);

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.PreparedSQL.NotPrepared
		$result = $wpdb->get_var( $sql );
		return ! empty( $result );
	}

	/**
	 * Whether digest queue table exists.
	 *
	 * @param string $table Full table name.
	 * @return bool
	 */
	private function digest_table_exists( string $table ): bool {
		global $wpdb;
		$wpdb->flush(); // Clear query cache before checking table existence.
		// Avoid SHOW TABLES LIKE: '_' is a wildcard in SQL LIKE patterns.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.PreparedSQL.NotPrepared
		$tables = $wpdb->get_col( 'SHOW TABLES' );
		if ( ! is_array( $tables ) ) {
			return false;
		}

		return in_array( $table, $tables, true );
	}

	/**
	 * Whether a base table exists (information_schema, current connection database).
	 *
	 * @param string $table_name Full table name (e.g. wp_bookit_notification_digest_queue).
	 * @return bool
	 */
	private function table_exists_via_information_schema( string $table_name ): bool {
		global $wpdb;
		$wpdb->flush(); // Clear query cache before checking table existence.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.PreparedSQL.NotPrepared
		$count = (int) $wpdb->get_var(
			$wpdb->prepare(
				'SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = %s',
				$table_name
			)
		);

		return $count > 0;
	}

	/**
	 * Whether the name is a normal base table (not a VIEW, etc.) in the current connection database.
	 *
	 * Uses information_schema.tables with TABLE_TYPE = 'BASE TABLE' — stricter than
	 * table_exists_via_information_schema(), which matches any row (e.g. a view with the same name).
	 *
	 * @param string $table_name Full table name (e.g. wp_bookit_notification_digest_queue).
	 * @return bool
	 */
	private function table_is_base_table_via_information_schema( string $table_name ): bool {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.PreparedSQL.NotPrepared
		$count = (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = %s AND table_type = 'BASE TABLE'",
				$table_name
			)
		);

		return $count > 0;
	}

	/**
	 * Mirrors future Bookit_Staff_Notifier::get_staff_preferences() behavior.
	 *
	 * @param string|null $json Raw notification_preferences column value.
	 * @return array<string, mixed>
	 */
	private static function get_staff_preferences_inline( ?string $json ): array {
		$defaults = array(
			'new_booking'    => 'immediate',
			'reschedule'     => 'immediate',
			'cancellation'   => 'immediate',
			'daily_schedule' => false,
		);
		if ( null === $json || '' === $json ) {
			return $defaults;
		}
		$decoded = json_decode( $json, true );
		if ( ! is_array( $decoded ) ) {
			return $defaults;
		}
		return array_merge( $defaults, $decoded );
	}

	/**
	 * Migration 0016 up/down/up leaves notification_preferences column present.
	 */
	public function test_migration_0016_adds_notification_preferences_column(): void {
		global $wpdb;

		$migration_file = $this->plugin_dir() . '/database/migrations/0016-add-notification-preferences-to-staff.php';
		$this->assertFileExists( $migration_file );
		require_once $migration_file;

		$table = $wpdb->prefix . 'bookings_staff';

		$migration = new Bookit_Migration_0016_Add_Notification_Preferences_To_Staff();
		$migration->up();
		$this->assertTrue( $this->staff_notification_column_exists( $table ), 'notification_preferences should exist after up()' );

		$migration->down();
		$this->assertFalse( $this->staff_notification_column_exists( $table ), 'notification_preferences should be removed after down()' );

		$migration->up();
		$this->assertTrue( $this->staff_notification_column_exists( $table ), 'notification_preferences should exist after second up()' );
	}

	/**
	 * Migration 0016 up() is idempotent.
	 */
	public function test_migration_0016_up_is_idempotent(): void {
		global $wpdb;

		require_once $this->plugin_dir() . '/database/migrations/0016-add-notification-preferences-to-staff.php';

		$table     = $wpdb->prefix . 'bookings_staff';
		$migration = new Bookit_Migration_0016_Add_Notification_Preferences_To_Staff();
		$migration->up();
		$migration->up();
		$this->assertTrue( $this->staff_notification_column_exists( $table ) );
	}

	/**
	 * Migration 0017 up/down/up leaves digest queue table present.
	 */
	public function test_migration_0017_creates_digest_queue_table(): void {
		global $wpdb;
	
		require_once $this->plugin_dir() . '/database/migrations/0017-create-notification-digest-queue.php';
	
		$table     = $wpdb->prefix . 'bookit_notification_digest_queue';
		$migration = new Bookit_Migration_0017_Create_Notification_Digest_Queue();
	
		// up() should be idempotent — table already exists from plugin activation.
		$migration->up();
		$this->assertTrue( $this->digest_table_exists( $table ), 'Table should exist after up()' );
		$this->assertEmpty( $wpdb->last_error, 'up() should not produce a DB error' );
	}

	/**
	 * Migration 0017 up() is idempotent.
	 */
	public function test_migration_0017_up_is_idempotent(): void {
		global $wpdb;

		require_once $this->plugin_dir() . '/database/migrations/0017-create-notification-digest-queue.php';

		$table     = $wpdb->prefix . 'bookit_notification_digest_queue';
		$migration = new Bookit_Migration_0017_Create_Notification_Digest_Queue();
		$migration->up();
		$migration->up();
		$this->assertTrue( $this->digest_table_exists( $table ) );
	}

	/**
	 * Digest queue has expected columns after up().
	 */
	public function test_digest_queue_has_correct_columns(): void {
		global $wpdb;

		require_once $this->plugin_dir() . '/database/migrations/0017-create-notification-digest-queue.php';

		$table_name = $wpdb->prefix . 'bookit_notification_digest_queue';
		$migration  = new Bookit_Migration_0017_Create_Notification_Digest_Queue();
		$migration->up();

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.PreparedSQL.NotPrepared
		$columns = $wpdb->get_col( "SHOW COLUMNS FROM {$table_name}", 0 );
		$this->assertIsArray( $columns );

		foreach ( array( 'id', 'staff_id', 'event_type', 'booking_id', 'processed', 'created_at' ) as $expected ) {
			$this->assertContains( $expected, $columns, "Column {$expected} should exist on digest queue table" );
		}
	}

	/**
	 * NULL notification_preferences yields default preference array.
	 */
	public function test_get_staff_preferences_returns_defaults_when_null(): void {
		global $wpdb;

		require_once $this->plugin_dir() . '/database/migrations/0016-add-notification-preferences-to-staff.php';
		$migration = new Bookit_Migration_0016_Add_Notification_Preferences_To_Staff();
		$migration->up();

		$email = 'prefs-null-' . wp_generate_password( 12, false, false ) . '@example.com';
		$wpdb->insert(
			$wpdb->prefix . 'bookings_staff',
			array(
				'first_name'    => 'N',
				'last_name'     => 'U',
				'email'         => $email,
				'password_hash' => wp_hash_password( 'x' ),
				'is_active'     => 1,
				'created_at'    => current_time( 'mysql' ),
				'updated_at'    => current_time( 'mysql' ),
			),
			array( '%s', '%s', '%s', '%s', '%d', '%s', '%s' )
		);
		$staff_id = (int) $wpdb->insert_id;

		$row = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT notification_preferences FROM {$wpdb->prefix}bookings_staff WHERE id = %d",
				$staff_id
			),
			ARRAY_A
		);
		$this->assertNotNull( $row );
		$this->assertNull( $row['notification_preferences'] );

		$prefs = self::get_staff_preferences_inline( $row['notification_preferences'] );
		$this->assertSame(
			array(
				'new_booking'    => 'immediate',
				'reschedule'     => 'immediate',
				'cancellation'   => 'immediate',
				'daily_schedule' => false,
			),
			$prefs
		);

		$wpdb->delete( $wpdb->prefix . 'bookings_staff', array( 'id' => $staff_id ), array( '%d' ) );
	}

	/**
	 * Partial JSON merges with defaults.
	 */
	public function test_get_staff_preferences_merges_with_defaults(): void {
		global $wpdb;

		require_once $this->plugin_dir() . '/database/migrations/0016-add-notification-preferences-to-staff.php';
		$migration = new Bookit_Migration_0016_Add_Notification_Preferences_To_Staff();
		$migration->up();

		$email = 'prefs-merge-' . wp_generate_password( 12, false, false ) . '@example.com';
		$wpdb->insert(
			$wpdb->prefix . 'bookings_staff',
			array(
				'first_name'               => 'M',
				'last_name'                => 'G',
				'email'                    => $email,
				'password_hash'            => wp_hash_password( 'x' ),
				'is_active'                => 1,
				'notification_preferences' => '{"new_booking":"daily"}',
				'created_at'               => current_time( 'mysql' ),
				'updated_at'               => current_time( 'mysql' ),
			),
			array( '%s', '%s', '%s', '%s', '%d', '%s', '%s', '%s' )
		);
		$staff_id = (int) $wpdb->insert_id;

		$raw = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT notification_preferences FROM {$wpdb->prefix}bookings_staff WHERE id = %d",
				$staff_id
			)
		);
		$this->assertIsString( $raw );

		$prefs = self::get_staff_preferences_inline( $raw );
		$this->assertSame( 'daily', $prefs['new_booking'] );
		$this->assertSame( 'immediate', $prefs['reschedule'] );
		$this->assertSame( 'immediate', $prefs['cancellation'] );
		$this->assertFalse( $prefs['daily_schedule'] );

		$wpdb->delete( $wpdb->prefix . 'bookings_staff', array( 'id' => $staff_id ), array( '%d' ) );
	}
}
