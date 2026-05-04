<?php
/**
 * Tests for rate limiter.
 *
 * @package    Bookit_Booking_System
 * @subpackage Tests
 */

/**
 * Test Bookit_Rate_Limiter.
 */
class Test_Rate_Limiter extends WP_UnitTestCase {

	/**
	 * Transient keys created during a test.
	 *
	 * @var string[]
	 */
	private $transient_keys = array();

	/**
	 * Set up each test.
	 */
	public function setUp(): void {
		parent::setUp();

		global $wpdb;
		$wpdb->query( "TRUNCATE TABLE {$wpdb->prefix}bookings_audit_log" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery

		$this->transient_keys = array();
	}

	/**
	 * Tear down each test.
	 */
	public function tearDown(): void {
		global $wpdb;

		foreach ( array_unique( $this->transient_keys ) as $key ) {
			delete_transient( $key );
		}

		$wpdb->query( "TRUNCATE TABLE {$wpdb->prefix}bookings_audit_log" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery

		parent::tearDown();
	}

	/**
	 * @covers Bookit_Rate_Limiter::check
	 */
	public function test_check_allows_requests_under_limit() {
		$action = 'check_under_limit';
		$ip     = '1.2.3.4';
		$this->remember_key( $action, $ip );

		for ( $i = 0; $i < 9; $i++ ) {
			$this->assertTrue( Bookit_Rate_Limiter::check( $action, $ip, 10, HOUR_IN_SECONDS ) );
		}
	}

	/**
	 * @covers Bookit_Rate_Limiter::check
	 */
	public function test_check_blocks_at_limit() {
		$action = 'check_blocks_limit';
		$ip     = '1.2.3.4';
		$this->remember_key( $action, $ip );

		for ( $i = 0; $i < 10; $i++ ) {
			$this->assertTrue( Bookit_Rate_Limiter::check( $action, $ip, 10, HOUR_IN_SECONDS ) );
		}

		$this->assertFalse( Bookit_Rate_Limiter::check( $action, $ip, 10, HOUR_IN_SECONDS ) );
	}

	/**
	 * @covers Bookit_Rate_Limiter::check
	 */
	public function test_check_first_request_sets_transient() {
		$action = 'first_request_set';
		$ip     = '1.2.3.4';
		$key    = $this->remember_key( $action, $ip );

		$this->assertTrue( Bookit_Rate_Limiter::check( $action, $ip, 10, HOUR_IN_SECONDS ) );
		$this->assertSame( 1, (int) get_transient( $key ) );
	}

	/**
	 * @covers Bookit_Rate_Limiter::check
	 */
	public function test_check_increments_count() {
		$action = 'increments_count';
		$ip     = '1.2.3.4';
		$key    = $this->remember_key( $action, $ip );

		Bookit_Rate_Limiter::check( $action, $ip, 10, HOUR_IN_SECONDS );
		Bookit_Rate_Limiter::check( $action, $ip, 10, HOUR_IN_SECONDS );
		Bookit_Rate_Limiter::check( $action, $ip, 10, HOUR_IN_SECONDS );

		$this->assertSame( 3, (int) get_transient( $key ) );
	}

	/**
	 * @covers Bookit_Rate_Limiter::check
	 */
	public function test_check_different_actions_are_independent() {
		$ip = '1.2.3.4';
		$this->remember_key( 'action_a', $ip );
		$this->remember_key( 'action_b', $ip );

		for ( $i = 0; $i < 10; $i++ ) {
			Bookit_Rate_Limiter::check( 'action_a', $ip, 10, HOUR_IN_SECONDS );
		}

		$this->assertTrue( Bookit_Rate_Limiter::check( 'action_b', $ip, 10, HOUR_IN_SECONDS ) );
	}

	/**
	 * @covers Bookit_Rate_Limiter::check
	 */
	public function test_check_different_ips_are_independent() {
		$action = 'independent_ips';
		$this->remember_key( $action, '1.2.3.4' );
		$this->remember_key( $action, '5.6.7.8' );

		for ( $i = 0; $i < 10; $i++ ) {
			Bookit_Rate_Limiter::check( $action, '1.2.3.4', 10, HOUR_IN_SECONDS );
		}

		$this->assertTrue( Bookit_Rate_Limiter::check( $action, '5.6.7.8', 10, HOUR_IN_SECONDS ) );
	}

	/**
	 * @covers Bookit_Rate_Limiter::handle_exceeded
	 */
	public function test_handle_exceeded_returns_429() {
		$response = Bookit_Rate_Limiter::handle_exceeded( 'wizard_book', '1.2.3.4' );

		$this->assertSame( 429, $response->get_status() );
	}

	/**
	 * @covers Bookit_Rate_Limiter::handle_exceeded
	 */
	public function test_handle_exceeded_logs_audit_entry() {
		global $wpdb;

		Bookit_Rate_Limiter::handle_exceeded( 'wizard_book', '1.2.3.4' );

		$row = $wpdb->get_row(
			"SELECT action, actor_type FROM {$wpdb->prefix}bookings_audit_log ORDER BY id DESC LIMIT 1",
			ARRAY_A
		); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared

		$this->assertNotEmpty( $row );
		$this->assertSame( 'rate_limit_exceeded', $row['action'] );
		$this->assertSame( 'system', $row['actor_type'] );
	}

	/**
	 * @covers Bookit_Error_Registry::to_wp_error
	 */
	public function test_e6001_registered_in_error_registry() {
		$error = Bookit_Error_Registry::to_wp_error( 'E6001' );
		$this->assertInstanceOf( WP_Error::class, $error );
	}

	/**
	 * Build and track a transient key for cleanup.
	 *
	 * @param string $action Action key.
	 * @param string $ip     IP address.
	 * @return string
	 */
	private function remember_key( $action, $ip ) {
		$key                    = Bookit_Rate_Limiter::KEY_PREFIX . $action . '_' . md5( $ip );
		$this->transient_keys[] = $key;
		return $key;
	}
}
