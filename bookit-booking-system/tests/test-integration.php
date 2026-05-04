<?php
/**
 * Integration tests - Test components working together.
 *
 * @package Bookit_Booking_System
 */

use Yoast\PHPUnitPolyfills\TestCases\TestCase;

/**
 * Integration test suite.
 */
class Test_Integration extends TestCase {

	/**
	 * Set up integration test.
	 */
	public function setUp(): void {
		parent::setUp();
		
		// Load all necessary classes
		require_once BOOKIT_PLUGIN_DIR . 'includes/class-bookit-logger.php';
		require_once BOOKIT_PLUGIN_DIR . 'includes/class-bookit-auth.php';
		require_once BOOKIT_PLUGIN_DIR . 'includes/class-bookit-session.php';
		require_once BOOKIT_PLUGIN_DIR . 'includes/class-bookit-database.php';
	}

	/**
	 * Test complete plugin activation flow.
	 *
	 * Verifies: Plugin activation → Database → Logging → Settings
	 */
	public function test_plugin_activation_flow() {
		// 1. Verify plugin constants
		$this->assertTrue( defined( 'BOOKIT_VERSION' ) );
		$this->assertTrue( defined( 'BOOKIT_PLUGIN_DIR' ) );
		
		// 2. Verify database tables created
		global $wpdb;
		$tables = array(
			'bookings_services',
			'bookings_categories',
			'bookings_service_categories',
			'bookings_staff',
			'bookings_staff_services',
			'bookings_customers',
			'bookings',
			'bookings_payments',
			'bookings_working_hours',
			'bookings_settings',
		);
		
		foreach ( $tables as $table ) {
			$table_name = $wpdb->prefix . $table;
			$exists     = $wpdb->get_var( "SHOW TABLES LIKE '$table_name'" );
			$this->assertEquals( $table_name, $exists, "Table $table should exist" );
		}
		
		// 3. Verify log directory created and secure
		$log_dir = Bookit_Logger::get_log_directory();
		$this->assertTrue( file_exists( $log_dir ) );
		$this->assertTrue( file_exists( $log_dir . '/.htaccess' ) );
		$this->assertTrue( file_exists( $log_dir . '/index.php' ) );
		
		// 4. Verify default settings created
		$settings = get_option( 'bookit_settings' );
		$this->assertIsArray( $settings );
		$this->assertEquals( 'Europe/London', $settings['timezone'] );
		$this->assertEquals( 'GBP', $settings['currency'] );
	}

	/**
	 * Test staff creation to authentication flow.
	 *
	 * Verifies: Staff creation → Password hashing → Authentication → Logging
	 */
	public function test_staff_creation_and_authentication_flow() {
		global $wpdb;
		$table_name = $wpdb->prefix . 'bookings_staff';
		
		// 1. Create staff member
		$password      = 'integration_test_' . time();
		$password_hash = Bookit_Auth::hash_password( $password );
		
		$result = $wpdb->insert(
			$table_name,
			array(
				'email'         => 'integration@test.com',
				'password_hash' => $password_hash,
				'first_name'    => 'Integration',
				'last_name'     => 'Test',
				'role'          => 'admin',
				'is_active'     => 1,
			),
			array( '%s', '%s', '%s', '%s', '%s', '%d' )
		);
		
		$this->assertNotFalse( $result, 'Staff member should be created' );
		$staff_id = $wpdb->insert_id;
		
		// 2. Verify password was hashed correctly
		$this->assertStringStartsWith( '$2y$', $password_hash );
		$this->assertEquals( 60, strlen( $password_hash ) );
		
		// 3. Test authentication succeeds
		$auth_result = Bookit_Auth::authenticate( 'integration@test.com', $password );
		$this->assertIsArray( $auth_result );
		$this->assertEquals( 'integration@test.com', $auth_result['email'] );
		$this->assertEquals( 'admin', $auth_result['role'] );
		
		// 4. Test authentication fails with wrong password
		$failed_auth = Bookit_Auth::authenticate( 'integration@test.com', 'wrongpassword' );
		$this->assertFalse( $failed_auth );
		
		// 5. Verify logging captured authentication events
		$log_file = Bookit_Logger::get_todays_log_file();
		$this->assertTrue( file_exists( $log_file ) );
		
		$log_contents = file_get_contents( $log_file );
		$this->assertStringContainsString( 'integration@test.com', $log_contents );
		
		// Cleanup
		$wpdb->delete( $table_name, array( 'id' => $staff_id ), array( '%d' ) );
	}

	/**
	 * Test booking creation with double-booking prevention.
	 *
	 * Verifies: Database constraints → Error handling → Logging
	 */
	public function test_booking_double_booking_prevention() {
		global $wpdb;
		
		// 1. Create test data
		$service_id  = $this->create_test_service();
		$staff_id    = $this->create_test_staff();
		$customer_id = $this->create_test_customer();
		
		$bookings_table = $wpdb->prefix . 'bookings';
		
		// 2. Create first booking
		$booking_data = array(
			'customer_id'  => $customer_id,
			'service_id'   => $service_id,
			'staff_id'     => $staff_id,
			'booking_date' => '2026-02-01',
			'start_time'   => '10:00:00',
			'end_time'     => '11:00:00',
			'duration'     => 60,
			'status'       => 'confirmed',
			'total_price'  => 50.00,
		);
		
		$result = $wpdb->insert( $bookings_table, $booking_data );
		$this->assertNotFalse( $result, 'First booking should succeed' );
		
		$booking_id = $wpdb->insert_id;
		
		// 3. Attempt duplicate booking (same staff, date, time)
		$duplicate_result = $wpdb->insert( $bookings_table, $booking_data );
		
		// Should fail due to UNIQUE constraint
		$this->assertFalse( $duplicate_result, 'Duplicate booking should be prevented' );
		// Check for duplicate error (MySQL error codes 1062 or message contains "Duplicate")
		$this->assertTrue( 
			! empty( $wpdb->last_error ) && (
				strpos( $wpdb->last_error, 'Duplicate' ) !== false || 
				strpos( $wpdb->last_error, '1062' ) !== false
			),
			'Should have duplicate entry error: ' . $wpdb->last_error
		);
		
		// 4. Verify different time slot succeeds
		$booking_data['start_time'] = '11:00:00';
		$booking_data['end_time']   = '12:00:00';
		$result2                    = $wpdb->insert( $bookings_table, $booking_data );
		$this->assertNotFalse( $result2, 'Booking at different time should succeed' );
		
		// Cleanup
		$wpdb->delete( $bookings_table, array( 'id' => $booking_id ), array( '%d' ) );
		$wpdb->delete( $bookings_table, array( 'id' => $wpdb->insert_id ), array( '%d' ) );
		$this->cleanup_test_data( $service_id, $staff_id, $customer_id );
	}

	/**
	 * Test logger security features.
	 *
	 * Verifies: Sensitive data redaction → File security → Location security
	 */
	public function test_logger_security_integration() {
		// 1. Test sensitive data is redacted
		$sensitive_data = array(
			'email'           => 'test@example.com',
			'password'        => 'supersecret123',
			'api_key'         => 'sk_live_abc123',
			'stripe_secret'   => 'sk_test_xyz789',
			'card_number'     => '4242424242424242',
			'normal_field'    => 'This should not be redacted',
		);
		
		Bookit_Logger::info( 'Security test log', $sensitive_data );
		
		$log_file     = Bookit_Logger::get_todays_log_file();
		$log_contents = file_get_contents( $log_file );
		
		// Verify redaction
		$this->assertStringContainsString( '[REDACTED]', $log_contents );
		$this->assertStringNotContainsString( 'supersecret123', $log_contents );
		$this->assertStringNotContainsString( 'sk_live_abc123', $log_contents );
		$this->assertStringNotContainsString( 'sk_test_xyz789', $log_contents );
		$this->assertStringNotContainsString( '4242424242424242', $log_contents );
		
		// Verify non-sensitive data appears
		$this->assertStringContainsString( 'This should not be redacted', $log_contents );
		$this->assertStringContainsString( 'test@example.com', $log_contents );
		
		// 2. Verify log directory is secure
		$log_dir   = Bookit_Logger::get_log_directory();
		$is_secure = Bookit_Logger::is_secure_location();
		
		// Should be outside web root OR protected
		if ( ! $is_secure ) {
			// If inside web root, must have protection
			$this->assertTrue( file_exists( $log_dir . '/.htaccess' ) );
			$this->assertTrue( file_exists( $log_dir . '/index.php' ) );
		}
	}

	/**
	 * Test admin menu integration.
	 *
	 * Verifies: Menu registration → Capability checks → Page loading
	 */
	public function test_admin_menu_integration() {
		global $menu, $submenu;
		
		// Load admin menu class
		require_once BOOKIT_PLUGIN_DIR . 'admin/class-bookit-admin-menu.php';
		
		// Simulate admin context
		set_current_screen( 'dashboard' );
		$user = $this->create_admin_user();
		wp_set_current_user( $user );
		
		// Trigger admin_menu action
		do_action( 'admin_menu' );
		
		// Verify main menu exists
		$found_main_menu = false;
		if ( is_array( $menu ) ) {
			foreach ( $menu as $menu_item ) {
				if ( isset( $menu_item[0] ) && strpos( $menu_item[0], 'Booking System' ) !== false ) {
					$found_main_menu = true;
					break;
				}
			}
		}
		
		$this->assertTrue( $found_main_menu, 'Main admin menu should be registered' );
		
		// Verify submenus exist
		if ( isset( $submenu['bookit-booking-system'] ) ) {
			$this->assertGreaterThan( 5, count( $submenu['bookit-booking-system'] ), 'Should have multiple submenu items' );
		}
		
		// Cleanup
		wp_delete_user( $user );
	}

	/**
	 * Test cross-component error handling.
	 *
	 * Verifies: Database error → Logger captures → No fatal errors
	 */
	public function test_error_handling_integration() {
		global $wpdb;
		
		// 1. Attempt invalid database operation
		$result = $wpdb->insert(
			$wpdb->prefix . 'bookings_staff',
			array(
				'email' => null, // NOT NULL constraint should fail
			)
		);
		
		$this->assertFalse( $result, 'Invalid insert should fail' );
		$this->assertNotEmpty( $wpdb->last_error, 'Should have error message' );
		
		// 2. Verify plugin continues functioning (no fatal error)
		$this->assertTrue( defined( 'BOOKIT_VERSION' ) );
		
		// 3. Test logger handles errors gracefully
		Bookit_Logger::error( 'Test error logging', array( 'error' => $wpdb->last_error ) );
		
		$log_file = Bookit_Logger::get_todays_log_file();
		$this->assertTrue( file_exists( $log_file ) );
	}

	/**
	 * Helper: Create test service.
	 *
	 * @return int Service ID.
	 */
	private function create_test_service() {
		global $wpdb;
		$table_name = $wpdb->prefix . 'bookings_services';
		
		$wpdb->insert(
			$table_name,
			array(
				'name'     => 'Test Service',
				'duration' => 60,
				'price'    => 50.00,
			)
		);
		
		return $wpdb->insert_id;
	}

	/**
	 * Helper: Create test staff.
	 *
	 * @param string|null $photo_url Optional photo URL.
	 * @param string|null $bio Optional bio.
	 * @param string|null $title Optional title.
	 * @return int Staff ID.
	 */
	private function create_test_staff( $photo_url = null, $bio = null, $title = null ) {
		global $wpdb;
		$table_name = $wpdb->prefix . 'bookings_staff';
		
		$data = array(
			'email'         => 'teststaff@example.com',
			'password_hash' => Bookit_Auth::hash_password( 'password' ),
			'first_name'    => 'Test',
			'last_name'     => 'Staff',
			'role'          => 'staff',
			'is_active'     => 1,
		);

		// Add optional fields if provided.
		if ( $photo_url !== null ) {
			$data['photo_url'] = $photo_url;
		}
		if ( $bio !== null ) {
			$data['bio'] = $bio;
		}
		if ( $title !== null ) {
			$data['title'] = $title;
		}
		
		$wpdb->insert( $table_name, $data );
		
		return $wpdb->insert_id;
	}

	/**
	 * Helper: Create test customer.
	 *
	 * @return int Customer ID.
	 */
	private function create_test_customer() {
		global $wpdb;
		$table_name = $wpdb->prefix . 'bookings_customers';
		
		$wpdb->insert(
			$table_name,
			array(
				'email'      => 'testcustomer@example.com',
				'first_name' => 'Test',
				'last_name'  => 'Customer',
				'phone'      => '01234567890',
			)
		);
		
		return $wpdb->insert_id;
	}

	/**
	 * Helper: Cleanup test data.
	 *
	 * @param int $service_id  Service ID.
	 * @param int $staff_id    Staff ID.
	 * @param int $customer_id Customer ID.
	 */
	private function cleanup_test_data( $service_id, $staff_id, $customer_id ) {
		global $wpdb;
		
		$wpdb->delete( $wpdb->prefix . 'bookings_services', array( 'id' => $service_id ), array( '%d' ) );
		$wpdb->delete( $wpdb->prefix . 'bookings_staff', array( 'id' => $staff_id ), array( '%d' ) );
		$wpdb->delete( $wpdb->prefix . 'bookings_customers', array( 'id' => $customer_id ), array( '%d' ) );
	}

	/**
	 * Helper: Create admin user for testing.
	 *
	 * @return int User ID.
	 */
	private function create_admin_user() {
		return wp_insert_user(
			array(
				'user_login' => 'testadmin_' . time(),
				'user_pass'  => 'password',
				'role'       => 'administrator',
			)
		);
	}
}
