<?php
/**
 * Tests for Profile API (Sprint 3, Task 11)
 *
 * @package    Bookit_Booking_System
 * @subpackage Tests
 */

/**
 * Test Profile API endpoints.
 */
class Test_Profile_API extends WP_UnitTestCase {

	/**
	 * REST API namespace.
	 *
	 * @var string
	 */
	private $namespace = 'bookit/v1';

	/**
	 * Set up each test.
	 */
	public function setUp(): void {
		parent::setUp();

		global $wpdb;

		$wpdb->query( "TRUNCATE TABLE {$wpdb->prefix}bookings_staff" );

		$_SESSION = array();

		do_action( 'rest_api_init' );
	}

	/**
	 * Tear down each test.
	 */
	public function tearDown(): void {
		global $wpdb;

		$wpdb->query( "TRUNCATE TABLE {$wpdb->prefix}bookings_staff" );

		$_SESSION = array();

		parent::tearDown();
	}

	// ========== TESTS FOR: GET /dashboard/profile ==========

	/**
	 * Test get profile returns correct data.
	 *
	 * @covers Bookit_Dashboard_Bookings_API::get_my_profile
	 */
	public function test_get_profile_returns_staff_data() {
		$staff_id = $this->create_test_staff( array(
			'first_name' => 'John',
			'last_name'  => 'Doe',
			'email'      => 'john@test.com',
			'phone'      => '07700900000',
			'title'      => 'Senior Therapist',
			'role'       => 'staff',
		) );

		$this->login_as( $staff_id, 'staff' );

		$request  = new WP_REST_Request( 'GET', '/' . $this->namespace . '/dashboard/profile' );
		$response = rest_get_server()->dispatch( $request );

		$this->assertEquals( 200, $response->get_status() );

		$data    = $response->get_data();
		$profile = $data['profile'];

		$this->assertTrue( $data['success'] );
		$this->assertEquals( 'John', $profile['first_name'] );
		$this->assertEquals( 'Doe', $profile['last_name'] );
		$this->assertEquals( 'john@test.com', $profile['email'] );
		$this->assertEquals( '07700900000', $profile['phone'] );
		$this->assertEquals( 'Senior Therapist', $profile['title'] );
		$this->assertEquals( 'John Doe', $profile['full_name'] );
	}

	/**
	 * Test get profile does not expose password hash.
	 *
	 * @covers Bookit_Dashboard_Bookings_API::get_my_profile
	 */
	public function test_get_profile_does_not_expose_password() {
		$staff_id = $this->create_test_staff();
		$this->login_as( $staff_id, 'staff' );

		$request  = new WP_REST_Request( 'GET', '/' . $this->namespace . '/dashboard/profile' );
		$response = rest_get_server()->dispatch( $request );

		$profile = $response->get_data()['profile'];
		$this->assertArrayNotHasKey( 'password_hash', $profile );
		$this->assertArrayNotHasKey( 'password', $profile );
	}

	/**
	 * Test unauthenticated user cannot get profile.
	 *
	 * @covers Bookit_Dashboard_Bookings_API::check_dashboard_permission
	 */
	public function test_unauthenticated_cannot_get_profile() {
		$_SESSION = array();

		$request  = new WP_REST_Request( 'GET', '/' . $this->namespace . '/dashboard/profile' );
		$response = rest_get_server()->dispatch( $request );

		$this->assertTrue( $response->is_error() );
		$this->assertEquals( 401, $response->get_status() );
	}

	// ========== TESTS FOR: PUT /dashboard/profile ==========

	/**
	 * Test update profile changes data.
	 *
	 * @covers Bookit_Dashboard_Bookings_API::update_my_profile
	 */
	public function test_update_profile_changes_data() {
		$staff_id = $this->create_test_staff( array(
			'first_name' => 'John',
			'phone'      => '01234567890',
		) );

		$this->login_as( $staff_id, 'staff' );

		$request = new WP_REST_Request( 'PUT', '/' . $this->namespace . '/dashboard/profile' );
		$request->set_body_params( array(
			'first_name' => 'Jane',
			'phone'      => '07700900000',
			'title'      => 'Lead Therapist',
		) );

		$response = rest_get_server()->dispatch( $request );

		$this->assertEquals( 200, $response->get_status() );

		$data = $response->get_data();
		$this->assertTrue( $data['success'] );
		$this->assertEquals( 'Jane', $data['profile']['first_name'] );

		global $wpdb;
		$staff = $wpdb->get_row( $wpdb->prepare(
			"SELECT first_name, phone, title FROM {$wpdb->prefix}bookings_staff WHERE id = %d",
			$staff_id
		) );
		$this->assertEquals( 'Jane', $staff->first_name );
		$this->assertEquals( '07700900000', $staff->phone );
		$this->assertEquals( 'Lead Therapist', $staff->title );
	}

	/**
	 * Test update profile rejects duplicate email.
	 *
	 * @covers Bookit_Dashboard_Bookings_API::update_my_profile
	 */
	public function test_update_profile_rejects_duplicate_email() {
		$this->create_test_staff( array( 'email' => 'existing@test.com' ) );

		$staff_id = $this->create_test_staff( array( 'email' => 'me@test.com' ) );
		$this->login_as( $staff_id, 'staff' );

		$request = new WP_REST_Request( 'PUT', '/' . $this->namespace . '/dashboard/profile' );
		$request->set_body_params( array(
			'email' => 'existing@test.com',
		) );

		$response = rest_get_server()->dispatch( $request );

		$this->assertTrue( $response->is_error() );
		$this->assertEquals( 409, $response->get_status() );
	}

	/**
	 * Test update profile rejects empty body.
	 *
	 * @covers Bookit_Dashboard_Bookings_API::update_my_profile
	 */
	public function test_update_profile_rejects_no_fields() {
		$staff_id = $this->create_test_staff();
		$this->login_as( $staff_id, 'staff' );

		$request  = new WP_REST_Request( 'PUT', '/' . $this->namespace . '/dashboard/profile' );
		$response = rest_get_server()->dispatch( $request );

		$this->assertTrue( $response->is_error() );
		$this->assertEquals( 400, $response->get_status() );
	}

	// ========== TESTS FOR: POST /dashboard/profile/change-password ==========

	/**
	 * Test change password with correct current password.
	 *
	 * @covers Bookit_Dashboard_Bookings_API::change_password
	 */
	public function test_change_password_with_correct_current_password() {
		$old_password = 'oldpassword123';
		$staff_id     = $this->create_test_staff( array(
			'password_hash' => password_hash( $old_password, PASSWORD_BCRYPT ),
		) );

		$this->login_as( $staff_id, 'staff' );

		$request = new WP_REST_Request( 'POST', '/' . $this->namespace . '/dashboard/profile/change-password' );
		$request->set_body_params( array(
			'current_password' => $old_password,
			'new_password'     => 'newpassword123',
		) );

		$response = rest_get_server()->dispatch( $request );

		$this->assertEquals( 200, $response->get_status() );
		$this->assertTrue( $response->get_data()['success'] );

		global $wpdb;
		$hash = $wpdb->get_var( $wpdb->prepare(
			"SELECT password_hash FROM {$wpdb->prefix}bookings_staff WHERE id = %d",
			$staff_id
		) );
		$this->assertTrue( password_verify( 'newpassword123', $hash ) );
	}

	/**
	 * Test change password rejects incorrect current password.
	 *
	 * @covers Bookit_Dashboard_Bookings_API::change_password
	 */
	public function test_change_password_rejects_incorrect_current_password() {
		$staff_id = $this->create_test_staff( array(
			'password_hash' => password_hash( 'correctpassword', PASSWORD_BCRYPT ),
		) );

		$this->login_as( $staff_id, 'staff' );

		$request = new WP_REST_Request( 'POST', '/' . $this->namespace . '/dashboard/profile/change-password' );
		$request->set_body_params( array(
			'current_password' => 'wrongpassword',
			'new_password'     => 'newpassword123',
		) );

		$response = rest_get_server()->dispatch( $request );

		$this->assertTrue( $response->is_error() );
		$this->assertEquals( 401, $response->get_status() );
		$error = $response->as_error();
		$this->assertEquals( 'invalid_password', $error->get_error_code() );
	}

	/**
	 * Test change password validates minimum length.
	 *
	 * @covers Bookit_Dashboard_Bookings_API::change_password
	 */
	public function test_change_password_validates_minimum_length() {
		$staff_id = $this->create_test_staff( array(
			'password_hash' => password_hash( 'oldpassword123', PASSWORD_BCRYPT ),
		) );

		$this->login_as( $staff_id, 'staff' );

		$request = new WP_REST_Request( 'POST', '/' . $this->namespace . '/dashboard/profile/change-password' );
		$request->set_body_params( array(
			'current_password' => 'oldpassword123',
			'new_password'     => 'short',
		) );

		$response = rest_get_server()->dispatch( $request );

		$this->assertTrue( $response->is_error() );
		$this->assertEquals( 400, $response->get_status() );
	}

	// ========== TESTS FOR: POST /dashboard/profile/verify-password ==========

	/**
	 * Test verify password succeeds with correct password.
	 *
	 * @covers Bookit_Dashboard_Bookings_API::verify_password
	 */
	public function test_verify_password_succeeds_with_correct_password() {
		$password = 'testpassword123';
		$staff_id = $this->create_test_staff( array(
			'password_hash' => password_hash( $password, PASSWORD_BCRYPT ),
		) );

		$this->login_as( $staff_id, 'staff' );

		$request = new WP_REST_Request( 'POST', '/' . $this->namespace . '/dashboard/profile/verify-password' );
		$request->set_body_params( array(
			'password' => $password,
		) );

		$response = rest_get_server()->dispatch( $request );

		$this->assertEquals( 200, $response->get_status() );
		$this->assertTrue( $response->get_data()['success'] );
	}

	/**
	 * Test verify password returns error for incorrect password.
	 *
	 * @covers Bookit_Dashboard_Bookings_API::verify_password
	 */
	public function test_verify_password_fails_with_incorrect_password() {
		$staff_id = $this->create_test_staff( array(
			'password_hash' => password_hash( 'correctpassword', PASSWORD_BCRYPT ),
		) );

		$this->login_as( $staff_id, 'staff' );

		$request = new WP_REST_Request( 'POST', '/' . $this->namespace . '/dashboard/profile/verify-password' );
		$request->set_body_params( array(
			'password' => 'wrongpassword',
		) );

		$response = rest_get_server()->dispatch( $request );

		$this->assertTrue( $response->is_error() );
		$this->assertEquals( 403, $response->get_status() );
		$error = $response->as_error();
		$this->assertEquals( 'invalid_password', $error->get_error_code() );
	}

	// ========== TESTS FOR: Route registration ==========

	/**
	 * Test profile endpoints are registered.
	 *
	 * @covers Bookit_Dashboard_Bookings_API::register_routes
	 */
	public function test_profile_endpoints_registered() {
		$routes = rest_get_server()->get_routes();

		$this->assertArrayHasKey( '/' . $this->namespace . '/dashboard/profile', $routes );
		$this->assertArrayHasKey( '/' . $this->namespace . '/dashboard/profile/notification-preferences', $routes );
		$this->assertArrayHasKey( '/' . $this->namespace . '/dashboard/profile/change-password', $routes );
		$this->assertArrayHasKey( '/' . $this->namespace . '/dashboard/profile/verify-password', $routes );
	}

	// ========== TESTS FOR: PUT /dashboard/profile/notification-preferences ==========

	/**
	 * Test notification preferences can be saved and retrieved via profile.
	 *
	 * @covers Bookit_Dashboard_Bookings_API::update_notification_preferences
	 * @covers Bookit_Dashboard_Bookings_API::get_my_profile
	 */
	public function test_preferences_endpoint_saves_and_retrieves_preferences() {
		$staff_id = $this->create_test_staff();
		$this->login_as( $staff_id, 'staff' );

		$request = new WP_REST_Request( 'PUT', '/' . $this->namespace . '/dashboard/profile/notification-preferences' );
		$request->set_body_params( array(
			'new_booking'    => 'daily',
			'reschedule'     => 'immediate',
			'cancellation'   => 'weekly',
			'daily_schedule' => true,
		) );

		$response = rest_get_server()->dispatch( $request );
		$this->assertEquals( 200, $response->get_status() );

		$data = $response->get_data();
		$this->assertTrue( $data['success'] );
		$this->assertEquals( 'daily', $data['preferences']['new_booking'] );
		$this->assertEquals( 'immediate', $data['preferences']['reschedule'] );
		$this->assertEquals( 'weekly', $data['preferences']['cancellation'] );
		$this->assertTrue( (bool) $data['preferences']['daily_schedule'] );

		$get_request  = new WP_REST_Request( 'GET', '/' . $this->namespace . '/dashboard/profile' );
		$get_response = rest_get_server()->dispatch( $get_request );
		$this->assertEquals( 200, $get_response->get_status() );

		$profile = $get_response->get_data()['profile'];
		$this->assertEquals( $data['preferences'], $profile['notification_preferences'] );
	}

	/**
	 * Test preferences endpoint validates frequency values or falls back to defaults.
	 *
	 * @covers Bookit_Dashboard_Bookings_API::update_notification_preferences
	 */
	public function test_preferences_endpoint_validates_frequency_values() {
		$staff_id = $this->create_test_staff();
		$this->login_as( $staff_id, 'staff' );

		$request = new WP_REST_Request( 'PUT', '/' . $this->namespace . '/dashboard/profile/notification-preferences' );
		$request->set_body_params( array(
			'new_booking' => 'never',
		) );

		$response = rest_get_server()->dispatch( $request );
		$status   = $response->get_status();

		if ( 400 === $status ) {
			$this->assertTrue( $response->is_error() );
			return;
		}

		$this->assertEquals( 200, $status );

		$data = $response->get_data();
		$this->assertTrue( $data['success'] );
		$this->assertEquals( 'immediate', $data['preferences']['new_booking'] );

		$get_request  = new WP_REST_Request( 'GET', '/' . $this->namespace . '/dashboard/profile' );
		$get_response = rest_get_server()->dispatch( $get_request );
		$profile      = $get_response->get_data()['profile'];
		$this->assertNotEquals( 'never', $profile['notification_preferences']['new_booking'] );
	}

	/**
	 * Test preferences endpoint requires authentication.
	 *
	 * @covers Bookit_Dashboard_Bookings_API::check_dashboard_permission
	 */
	public function test_preferences_endpoint_requires_authentication() {
		$_SESSION = array();

		$request  = new WP_REST_Request( 'PUT', '/' . $this->namespace . '/dashboard/profile/notification-preferences' );
		$response = rest_get_server()->dispatch( $request );

		$this->assertTrue( $response->is_error() );
		$this->assertEquals( 401, $response->get_status() );
	}

	/**
	 * Test get profile includes notification preferences keys.
	 *
	 * @covers Bookit_Dashboard_Bookings_API::get_my_profile
	 */
	public function test_get_profile_includes_notification_preferences() {
		$staff_id = $this->create_test_staff();
		$this->login_as( $staff_id, 'staff' );

		$request  = new WP_REST_Request( 'GET', '/' . $this->namespace . '/dashboard/profile' );
		$response = rest_get_server()->dispatch( $request );
		$this->assertEquals( 200, $response->get_status() );

		$profile = $response->get_data()['profile'];
		$this->assertArrayHasKey( 'notification_preferences', $profile );
		$this->assertArrayHasKey( 'new_booking', $profile['notification_preferences'] );
		$this->assertArrayHasKey( 'reschedule', $profile['notification_preferences'] );
		$this->assertArrayHasKey( 'cancellation', $profile['notification_preferences'] );
		$this->assertArrayHasKey( 'daily_schedule', $profile['notification_preferences'] );
	}

	/**
	 * Test preferences default when not set.
	 *
	 * @covers Bookit_Dashboard_Bookings_API::get_my_profile
	 */
	public function test_preferences_default_to_immediate_when_not_set() {
		$staff_id = $this->create_test_staff( array(
			'notification_preferences' => null,
		) );
		$this->login_as( $staff_id, 'staff' );

		$request  = new WP_REST_Request( 'GET', '/' . $this->namespace . '/dashboard/profile' );
		$response = rest_get_server()->dispatch( $request );
		$this->assertEquals( 200, $response->get_status() );

		$prefs = $response->get_data()['profile']['notification_preferences'];
		$this->assertEquals( 'immediate', $prefs['new_booking'] );
		$this->assertEquals( 'immediate', $prefs['reschedule'] );
		$this->assertEquals( 'immediate', $prefs['cancellation'] );
		$this->assertFalse( (bool) $prefs['daily_schedule'] );
	}

	// ========== HELPER METHODS ==========

	/**
	 * Simulate Bookit dashboard login via session.
	 *
	 * @param int    $staff_id Staff row ID.
	 * @param string $role     'admin' or 'staff'.
	 */
	private function login_as( $staff_id, $role = 'staff' ) {
		global $wpdb;

		$staff = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT id, email, first_name, last_name FROM {$wpdb->prefix}bookings_staff WHERE id = %d",
				$staff_id
			),
			ARRAY_A
		);

		$_SESSION['staff_id']      = (int) $staff['id'];
		$_SESSION['staff_email']   = $staff['email'];
		$_SESSION['staff_role']    = $role;
		$_SESSION['staff_name']    = trim( $staff['first_name'] . ' ' . $staff['last_name'] );
		$_SESSION['is_logged_in']  = true;
		$_SESSION['last_activity'] = time();
	}

	/**
	 * Create test staff member.
	 *
	 * @param array $args Override defaults.
	 * @return int Staff ID.
	 */
	private function create_test_staff( $args = array() ) {
		global $wpdb;

		$defaults = array(
			'email'              => 'staff-' . wp_generate_password( 6, false ) . '@test.com',
			'password_hash'      => password_hash( 'password123', PASSWORD_BCRYPT ),
			'first_name'         => 'Test',
			'last_name'          => 'Staff',
			'phone'              => '07700900000',
			'photo_url'          => null,
			'bio'                => 'Test bio',
			'title'              => 'Therapist',
			'role'               => 'staff',
			'google_calendar_id' => null,
			'is_active'          => 1,
			'display_order'      => 0,
			'notification_preferences' => null,
			'created_at'         => current_time( 'mysql' ),
			'updated_at'         => current_time( 'mysql' ),
			'deleted_at'         => null,
		);

		$data = wp_parse_args( $args, $defaults );

		$wpdb->insert(
			$wpdb->prefix . 'bookings_staff',
			$data,
			array( '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%d', '%d', '%s', '%s', '%s', '%s' )
		);
		return (int) $wpdb->insert_id;
	}
}
