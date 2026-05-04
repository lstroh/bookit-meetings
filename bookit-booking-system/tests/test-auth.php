<?php
/**
 * Authentication tests.
 *
 * @package Bookit_Booking_System
 */

use Yoast\PHPUnitPolyfills\TestCases\TestCase;

/**
 * Test authentication functionality.
 */
class Test_Auth extends TestCase {

	/**
	 * Test staff member for authentication tests.
	 *
	 * @var array
	 */
	private $test_staff;

	/**
	 * Set up test.
	 */
	public function setUp(): void {
		parent::setUp();
		
		require_once BOOKIT_PLUGIN_DIR . 'includes/class-bookit-auth.php';
		require_once BOOKIT_PLUGIN_DIR . 'includes/class-bookit-session.php';
		
		// Create test staff member
		global $wpdb;
		$table_name = $wpdb->prefix . 'bookings_staff';
		
		$wpdb->insert(
			$table_name,
			array(
				'email'         => 'test@example.com',
				'password_hash' => Bookit_Auth::hash_password( 'testpassword123' ),
				'first_name'    => 'Test',
				'last_name'     => 'User',
				'role'          => 'staff',
				'is_active'     => 1,
			),
			array( '%s', '%s', '%s', '%s', '%s', '%d' )
		);
		
		$this->test_staff = array(
			'id'    => $wpdb->insert_id,
			'email' => 'test@example.com',
		);
	}

	/**
	 * Tear down test.
	 */
	public function tearDown(): void {
		// Clean up test staff
		global $wpdb;
		$table_name = $wpdb->prefix . 'bookings_staff';
		
		$wpdb->delete(
			$table_name,
			array( 'id' => $this->test_staff['id'] ),
			array( '%d' )
		);
		
		parent::tearDown();
	}

	/**
	 * Test password hashing.
	 */
	public function test_password_hashing() {
		$password = 'testpassword123';
		$hash     = Bookit_Auth::hash_password( $password );
		
		// Hash should start with $2y$ (bcrypt)
		$this->assertStringStartsWith( '$2y$', $hash );
		
		// Hash should be 60 characters
		$this->assertEquals( 60, strlen( $hash ) );
		
		// Should verify correctly
		$this->assertTrue( password_verify( $password, $hash ) );
	}

	/**
	 * Test successful authentication.
	 */
	public function test_successful_authentication() {
		$result = Bookit_Auth::authenticate( 'test@example.com', 'testpassword123' );
		
		$this->assertIsArray( $result );
		$this->assertArrayHasKey( 'id', $result );
		$this->assertArrayHasKey( 'email', $result );
		$this->assertEquals( 'test@example.com', $result['email'] );
	}

	/**
	 * Test failed authentication with wrong password.
	 */
	public function test_failed_authentication_wrong_password() {
		$result = Bookit_Auth::authenticate( 'test@example.com', 'wrongpassword' );
		
		$this->assertFalse( $result );
	}

	/**
	 * Test failed authentication with non-existent email.
	 */
	public function test_failed_authentication_invalid_email() {
		$result = Bookit_Auth::authenticate( 'nonexistent@example.com', 'testpassword123' );
		
		$this->assertFalse( $result );
	}

	/**
	 * Test authentication with inactive staff.
	 */
	public function test_authentication_inactive_staff() {
		global $wpdb;
		$table_name = $wpdb->prefix . 'bookings_staff';
		
		// Set staff to inactive
		$wpdb->update(
			$table_name,
			array( 'is_active' => 0 ),
			array( 'id' => $this->test_staff['id'] ),
			array( '%d' ),
			array( '%d' )
		);
		
		// Authentication should fail
		$result = Bookit_Auth::authenticate( 'test@example.com', 'testpassword123' );
		$this->assertFalse( $result );
		
		// Reset to active
		$wpdb->update(
			$table_name,
			array( 'is_active' => 1 ),
			array( 'id' => $this->test_staff['id'] ),
			array( '%d' ),
			array( '%d' )
		);
	}

	/**
	 * Test get current staff when not logged in.
	 */
	public function test_get_current_staff_not_logged_in() {
		$staff = Bookit_Auth::get_current_staff();
		$this->assertNull( $staff );
	}

	/**
	 * Test is_admin method.
	 */
	public function test_is_admin_method() {
		// Not logged in - should be false
		$this->assertFalse( Bookit_Auth::is_admin() );
	}
}
