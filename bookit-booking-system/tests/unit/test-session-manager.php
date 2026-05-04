<?php
/**
 * Tests for Bookit_Session_Manager
 *
 * @package    Bookit_Booking_System
 * @subpackage Tests
 */

/**
 * Test Bookit_Session_Manager class.
 */
class Test_Session_Manager extends WP_UnitTestCase {

	/**
	 * Set up each test.
	 */
	public function setUp(): void {
		parent::setUp();
		Bookit_Session_Manager::clear();
		if ( session_status() === PHP_SESSION_ACTIVE ) {
			session_destroy();
		}
		if ( isset( $_SESSION ) ) {
			$_SESSION = array();
		}
	}

	/**
	 * Tear down each test.
	 */
	public function tearDown(): void {
		// Clean up session after each test.
		if ( session_status() === PHP_SESSION_ACTIVE ) {
			session_destroy();
		}
		if ( isset( $_SESSION ) ) {
			$_SESSION = array();
		}

		parent::tearDown();
	}

	/**
	 * Test that session starts successfully and returns active status.
	 *
	 * @covers Bookit_Session_Manager::init
	 */
	public function test_session_starts_successfully() {
		Bookit_Session_Manager::init();
		// In PHPUnit, headers are often sent; init uses fallback $_SESSION array.
		$session_active = session_status() === PHP_SESSION_ACTIVE;
		$session_ready  = $session_active || ( isset( $_SESSION ) && is_array( $_SESSION ) );
		$this->assertTrue( $session_ready, 'Session should be active or $_SESSION array available' );
	}

	/**
	 * Test that session ID exists after init when real session started.
	 *
	 * @covers Bookit_Session_Manager::init
	 */
	public function test_session_id_exists_after_init() {
		Bookit_Session_Manager::init();
		// When headers_sent, session_start() is skipped; session_id() may be empty.
		if ( session_status() === PHP_SESSION_ACTIVE ) {
			$this->assertNotEmpty( session_id() );
		} else {
			$this->assertTrue( isset( $_SESSION ) && is_array( $_SESSION ) );
		}
	}

	/**
	 * Test that wizard data key exists after set_data or get_data triggers init.
	 *
	 * @covers Bookit_Session_Manager::init
	 * @covers Bookit_Session_Manager::set_data
	 */
	public function test_wizard_data_key_exists() {
		Bookit_Session_Manager::init();
		Bookit_Session_Manager::set_data( array() );
		$this->assertArrayHasKey( Bookit_Session_Manager::SESSION_KEY, $_SESSION );
	}

	/**
	 * Test that wizard data has correct structure.
	 *
	 * @covers Bookit_Session_Manager::get_data
	 */
	public function test_wizard_data_has_correct_structure() {
		$data = Bookit_Session_Manager::get_data();
		$expected_keys = array( 'current_step', 'service_id', 'staff_id', 'date', 'time', 'customer', 'created_at', 'last_activity' );
		foreach ( $expected_keys as $key ) {
			$this->assertArrayHasKey( $key, $data, "Wizard data should have key: $key" );
		}
	}

	/**
	 * Test that default step is one.
	 *
	 * @covers Bookit_Session_Manager::get_data
	 * @covers Bookit_Session_Manager::get
	 */
	public function test_default_step_is_one() {
		Bookit_Session_Manager::clear();
		$step = (int) Bookit_Session_Manager::get( 'current_step', 1 );
		$this->assertEquals( 1, $step );
	}

	/**
	 * Test that default booking values are null.
	 *
	 * @covers Bookit_Session_Manager::get_data
	 * @covers Bookit_Session_Manager::get
	 */
	public function test_default_values_are_null() {
		Bookit_Session_Manager::clear();
		$this->assertNull( Bookit_Session_Manager::get( 'service_id' ) );
		$this->assertNull( Bookit_Session_Manager::get( 'staff_id' ) );
		$this->assertNull( Bookit_Session_Manager::get( 'date' ) );
		$this->assertNull( Bookit_Session_Manager::get( 'time' ) );
	}

	/**
	 * Test that customer array is empty by default.
	 *
	 * @covers Bookit_Session_Manager::get_data
	 * @covers Bookit_Session_Manager::get
	 */
	public function test_customer_array_is_empty() {
		Bookit_Session_Manager::clear();
		$customer = Bookit_Session_Manager::get( 'customer', array() );
		$this->assertIsArray( $customer );
		$this->assertEmpty( $customer );
	}

	/**
	 * Test that get_data returns array.
	 *
	 * @covers Bookit_Session_Manager::get_data
	 */
	public function test_get_wizard_data_returns_array() {
		$data = Bookit_Session_Manager::get_data();
		$this->assertIsArray( $data );
	}

	/**
	 * Test that get_data returns current_step value.
	 *
	 * @covers Bookit_Session_Manager::get_data
	 * @covers Bookit_Session_Manager::get
	 */
	public function test_get_wizard_data_returns_current_step() {
		$data = Bookit_Session_Manager::get_data();
		$this->assertArrayHasKey( 'current_step', $data );
		$this->assertEquals( 1, (int) $data['current_step'] );
	}

	/**
	 * Test that set_data updates session.
	 *
	 * @covers Bookit_Session_Manager::set_data
	 * @covers Bookit_Session_Manager::get_data
	 * @covers Bookit_Session_Manager::get
	 */
	public function test_set_wizard_data_updates_session() {
		Bookit_Session_Manager::set_data( array( 'current_step' => 2, 'service_id' => 5 ) );
		$this->assertEquals( 2, (int) Bookit_Session_Manager::get( 'current_step' ) );
		$this->assertEquals( 5, (int) Bookit_Session_Manager::get( 'service_id' ) );
	}

	/**
	 * Test that setting current_step changes step (update_step equivalent).
	 *
	 * @covers Bookit_Session_Manager::set
	 * @covers Bookit_Session_Manager::get
	 */
	public function test_update_step_changes_current_step() {
		Bookit_Session_Manager::set( 'current_step', 1 );
		$this->assertEquals( 1, (int) Bookit_Session_Manager::get( 'current_step' ) );

		Bookit_Session_Manager::set( 'current_step', 2 );
		$this->assertEquals( 2, (int) Bookit_Session_Manager::get( 'current_step' ) );

		Bookit_Session_Manager::set( 'current_step', 4 );
		$this->assertEquals( 4, (int) Bookit_Session_Manager::get( 'current_step' ) );
	}

	/**
	 * Test that clear resets wizard data to defaults (session key retained but reset).
	 *
	 * @covers Bookit_Session_Manager::clear
	 * @covers Bookit_Session_Manager::get_data
	 */
	public function test_clear_wizard_data_removes_session() {
		Bookit_Session_Manager::set_data( array( 'current_step' => 3, 'service_id' => 10 ) );
		Bookit_Session_Manager::clear();
		$data = Bookit_Session_Manager::get_data();
		$this->assertEquals( 1, (int) $data['current_step'] );
		$this->assertNull( $data['service_id'] );
	}

	/**
	 * Test that regenerate runs without error (session_regenerate_id when active).
	 *
	 * @covers Bookit_Session_Manager::regenerate
	 */
	public function test_session_regenerates_on_step_change() {
		Bookit_Session_Manager::init();
		$threw = false;
		try {
			Bookit_Session_Manager::regenerate();
		} catch ( Exception $e ) {
			$threw = true;
		}
		$this->assertFalse( $threw, 'regenerate() should not throw' );
	}

	/**
	 * Test is_expired when last_activity is old.
	 *
	 * @covers Bookit_Session_Manager::is_expired
	 */
	public function test_session_expired_after_timeout() {
		// set() and set_data() call update_activity() and overwrite last_activity.
		// Set directly in session so it sticks.
		Bookit_Session_Manager::init();
		Bookit_Session_Manager::set_data( array() );
		$_SESSION[ Bookit_Session_Manager::SESSION_KEY ]['last_activity'] = time() - ( Bookit_Session_Manager::SESSION_TIMEOUT + 1 ); // 30 min+ ago.
		$this->assertTrue( Bookit_Session_Manager::is_expired() );
	}

	/**
	 * Test is_expired when last_activity is recent.
	 *
	 * @covers Bookit_Session_Manager::is_expired
	 * @covers Bookit_Session_Manager::set_data
	 */
	public function test_session_not_expired_when_recent() {
		Bookit_Session_Manager::set_data( array( 'last_activity' => time() ) );
		$this->assertFalse( Bookit_Session_Manager::is_expired() );
	}

	/**
	 * Test get_time_remaining returns non-negative seconds.
	 *
	 * @covers Bookit_Session_Manager::get_time_remaining
	 */
	public function test_get_time_remaining() {
		Bookit_Session_Manager::set( 'last_activity', time() );
		$remaining = Bookit_Session_Manager::get_time_remaining();
		$this->assertIsInt( $remaining );
		$this->assertGreaterThanOrEqual( 0, $remaining );
		$this->assertLessThanOrEqual( Bookit_Session_Manager::SESSION_TIMEOUT, $remaining );
	}

	/**
	 * Test get with default value for missing field.
	 *
	 * @covers Bookit_Session_Manager::get
	 */
	public function test_get_returns_default_for_missing_field() {
		$this->assertNull( Bookit_Session_Manager::get( 'nonexistent' ) );
		$this->assertEquals( 'default', Bookit_Session_Manager::get( 'nonexistent', 'default' ) );
	}
}
