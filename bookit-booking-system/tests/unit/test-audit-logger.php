<?php
/**
 * Tests for audit logger.
 *
 * @package    Bookit_Booking_System
 * @subpackage Tests
 */

/**
 * Test Bookit_Audit_Logger.
 */
class Test_Bookit_Audit_Logger extends WP_UnitTestCase {

	/**
	 * Set up each test.
	 */
	public function setUp(): void {
		parent::setUp();

		global $wpdb;

		$wpdb->query( "TRUNCATE TABLE {$wpdb->prefix}bookings_audit_log" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
		$_SESSION = array();
	}

	/**
	 * Tear down each test.
	 */
	public function tearDown(): void {
		global $wpdb;

		$wpdb->query( "TRUNCATE TABLE {$wpdb->prefix}bookings_audit_log" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
		$_SESSION = array();

		parent::tearDown();
	}

	/**
	 * @covers Bookit_Audit_Logger::log
	 */
	public function test_log_inserts_row_into_audit_table() {
		global $wpdb;

		$action = 'test.action.' . wp_generate_password( 6, false, false );

		Bookit_Audit_Logger::log(
			$action,
			'test',
			42,
			array( 'notes' => 'PHPUnit test entry' )
		);

		$row = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$wpdb->prefix}bookings_audit_log WHERE action = %s ORDER BY id DESC LIMIT 1",
				$action
			),
			ARRAY_A
		);

		$this->assertNotEmpty( $row );
		$this->assertSame( $action, $row['action'] );
		$this->assertSame( 'test', $row['object_type'] );
		$this->assertSame( 42, (int) $row['object_id'] );
		$this->assertSame( 'PHPUnit test entry', $row['notes'] );
	}

	/**
	 * @covers Bookit_Audit_Logger::log
	 */
	public function test_log_with_old_and_new_value() {
		global $wpdb;

		$action = 'test.values.' . wp_generate_password( 6, false, false );

		Bookit_Audit_Logger::log(
			$action,
			'test',
			7,
			array(
				'old_value' => array(
					'status' => 'pending',
					'amount' => 50,
				),
				'new_value' => array(
					'status' => 'confirmed',
					'amount' => 100,
				),
			)
		);

		$row = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT old_value, new_value FROM {$wpdb->prefix}bookings_audit_log WHERE action = %s ORDER BY id DESC LIMIT 1",
				$action
			),
			ARRAY_A
		);

		$this->assertNotEmpty( $row );
		$this->assertIsString( $row['old_value'] );
		$this->assertIsString( $row['new_value'] );
		$this->assertSame( 'pending', json_decode( $row['old_value'], true )['status'] );
		$this->assertSame( 'confirmed', json_decode( $row['new_value'], true )['status'] );
	}

	/**
	 * @covers Bookit_Audit_Logger::log
	 */
	public function test_log_redacts_sensitive_fields() {
		global $wpdb;

		$action = 'test.redact.' . wp_generate_password( 6, false, false );

		Bookit_Audit_Logger::log(
			$action,
			'test',
			9,
			array(
				'new_value' => array(
					'stripe_secret' => 'sk_live_abc',
					'amount'        => 100,
				),
			)
		);

		$stored = (string) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT new_value FROM {$wpdb->prefix}bookings_audit_log WHERE action = %s ORDER BY id DESC LIMIT 1",
				$action
			)
		);

		$this->assertStringNotContainsString( 'stripe_secret', $stored );
		$this->assertStringContainsString( 'amount', $stored );
	}

	/**
	 * @covers Bookit_Audit_Logger::log
	 */
	public function test_log_does_not_throw_on_db_failure() {
		global $wpdb;

		$original_prefix = $wpdb->prefix;
		$wpdb->prefix    = 'nonexistent_prefix_';

		try {
			Bookit_Audit_Logger::log( 'test.action.db.failure', 'test', 0 );
			$this->assertTrue( true );
		} catch ( Throwable $exception ) {
			$this->fail( 'Bookit_Audit_Logger::log should not throw on DB failure.' );
		} finally {
			$wpdb->prefix = $original_prefix;
		}
	}

	/**
	 * @covers Bookit_Audit_Logger::log
	 */
	public function test_log_detects_system_actor_when_no_session() {
		global $wpdb;

		$_SESSION = array();
		$action   = 'test.system.actor.' . wp_generate_password( 6, false, false );

		Bookit_Audit_Logger::log( $action, 'test', 0 );

		$row = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT actor_type, actor_id FROM {$wpdb->prefix}bookings_audit_log WHERE action = %s ORDER BY id DESC LIMIT 1",
				$action
			),
			ARRAY_A
		);

		$this->assertNotEmpty( $row );
		$this->assertSame( 'system', $row['actor_type'] );
		$this->assertSame( 0, (int) $row['actor_id'] );
	}

	/**
	 * @covers Bookit_Audit_Logger::log
	 */
	public function test_log_stores_null_object_id_when_zero_passed() {
		global $wpdb;

		$action = 'test.null.object.' . wp_generate_password( 6, false, false );

		Bookit_Audit_Logger::log( $action, 'system', 0 );

		$row = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT object_id FROM {$wpdb->prefix}bookings_audit_log WHERE action = %s ORDER BY id DESC LIMIT 1",
				$action
			),
			ARRAY_A
		);

		$this->assertNotEmpty( $row );
		$this->assertNull( $row['object_id'] );
	}
}
