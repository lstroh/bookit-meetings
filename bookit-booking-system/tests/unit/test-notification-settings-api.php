<?php
/**
 * Tests for notification provider settings API behavior.
 *
 * @package    Bookit_Booking_System
 * @subpackage Tests
 */

/**
 * Test notification settings and test-email API endpoints.
 */
class Test_Notification_Settings_API extends WP_UnitTestCase {

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
		$wpdb->query( "TRUNCATE TABLE {$wpdb->prefix}bookings_settings" );

		$_SESSION = array();
		do_action( 'rest_api_init' );
		add_filter( 'pre_wp_mail', array( $this, 'mock_pre_wp_mail' ), 10, 2 );
	}

	/**
	 * Tear down each test.
	 */
	public function tearDown(): void {
		global $wpdb;
		$wpdb->query( "TRUNCATE TABLE {$wpdb->prefix}bookings_staff" );
		$wpdb->query( "TRUNCATE TABLE {$wpdb->prefix}bookings_settings" );

		delete_option( 'bookit_confirmed_v2_url' );

		$_SESSION = array();
		remove_filter( 'pre_wp_mail', array( $this, 'mock_pre_wp_mail' ), 10 );
		parent::tearDown();
	}

	/**
	 * @covers Bookit_Dashboard_Bookings_API::update_settings
	 * @covers Bookit_Dashboard_Bookings_API::get_settings
	 */
	public function test_template_id_settings_are_saved_and_retrieved() {
		$admin = $this->create_test_staff( array( 'role' => 'admin' ) );
		$this->login_as( $admin, 'admin' );

		$update_request = new WP_REST_Request( 'POST', '/' . $this->namespace . '/dashboard/settings' );
		$update_request->set_body_params(
			array(
				'settings' => array(
					'brevo_template_booking_confirmed' => '42',
				),
			)
		);
		$update_response = rest_get_server()->dispatch( $update_request );
		$this->assertEquals( 200, $update_response->get_status() );

		$get_request = new WP_REST_Request( 'GET', '/' . $this->namespace . '/dashboard/settings' );
		$get_request->set_param( 'keys', 'brevo_template_booking_confirmed' );
		$get_response = rest_get_server()->dispatch( $get_request );
		$get_data     = $get_response->get_data();

		$this->assertEquals( 200, $get_response->get_status() );
		$this->assertSame( '42', $get_data['settings']['brevo_template_booking_confirmed'] );
	}

	/**
	 * @covers Bookit_Dashboard_Bookings_API::update_settings
	 * @covers Bookit_Dashboard_Bookings_API::get_settings
	 */
	public function test_email_provider_setting_is_saved_and_retrieved() {
		$admin = $this->create_test_staff( array( 'role' => 'admin' ) );
		$this->login_as( $admin, 'admin' );

		$update_request = new WP_REST_Request( 'POST', '/' . $this->namespace . '/dashboard/settings' );
		$update_request->set_body_params(
			array(
				'settings' => array(
					'email_provider' => 'brevo',
				),
			)
		);
		$update_response = rest_get_server()->dispatch( $update_request );
		$this->assertEquals( 200, $update_response->get_status() );

		$get_request = new WP_REST_Request( 'GET', '/' . $this->namespace . '/dashboard/settings' );
		$get_request->set_param( 'keys', 'email_provider' );
		$get_response = rest_get_server()->dispatch( $get_request );
		$get_data     = $get_response->get_data();

		$this->assertEquals( 200, $get_response->get_status() );
		$this->assertEquals( 'brevo', $get_data['settings']['email_provider'] );
	}

	/**
	 * @covers Bookit_Dashboard_Bookings_API::get_settings
	 */
	public function test_brevo_api_key_is_masked_in_get_response() {
		global $wpdb;

		$wpdb->insert(
			$wpdb->prefix . 'bookings_settings',
			array(
				'setting_key'   => 'brevo_api_key',
				'setting_value' => 'xkeysib-live-test',
				'setting_type'  => 'string',
			),
			array( '%s', '%s', '%s' )
		);

		$admin = $this->create_test_staff( array( 'role' => 'admin' ) );
		$this->login_as( $admin, 'admin' );

		$request = new WP_REST_Request( 'GET', '/' . $this->namespace . '/dashboard/settings' );
		$request->set_param( 'keys', 'brevo_api_key' );
		$response = rest_get_server()->dispatch( $request );
		$data     = $response->get_data();

		$this->assertEquals( 200, $response->get_status() );
		$this->assertEquals( 'SAVED', $data['settings']['brevo_api_key'] );
	}

	/**
	 * @covers Bookit_Dashboard_Bookings_API::update_settings
	 * @covers Bookit_Dashboard_Bookings_API::get_settings
	 */
	public function test_brevo_api_key_is_not_overwritten_by_empty_string() {
		global $wpdb;

		$wpdb->insert(
			$wpdb->prefix . 'bookings_settings',
			array(
				'setting_key'   => 'brevo_api_key',
				'setting_value' => 'xkeysib-existing-real-key',
				'setting_type'  => 'string',
			),
			array( '%s', '%s', '%s' )
		);

		$admin = $this->create_test_staff( array( 'role' => 'admin' ) );
		$this->login_as( $admin, 'admin' );

		$update_request = new WP_REST_Request( 'POST', '/' . $this->namespace . '/dashboard/settings' );
		$update_request->set_body_params(
			array(
				'settings' => array(
					'brevo_api_key' => '',
				),
			)
		);
		$update_response = rest_get_server()->dispatch( $update_request );
		$this->assertEquals( 200, $update_response->get_status() );

		$get_request = new WP_REST_Request( 'GET', '/' . $this->namespace . '/dashboard/settings' );
		$get_request->set_param( 'keys', 'brevo_api_key' );
		$get_response = rest_get_server()->dispatch( $get_request );
		$get_data     = $get_response->get_data();

		$this->assertEquals( 200, $get_response->get_status() );
		$this->assertEquals( 'SAVED', $get_data['settings']['brevo_api_key'] );
	}

	/**
	 * @covers Bookit_Dashboard_Bookings_API::update_settings
	 * @covers Bookit_Dashboard_Bookings_API::get_settings
	 */
	public function test_sms_provider_setting_is_saved_and_retrieved() {
		$admin = $this->create_test_staff( array( 'role' => 'admin' ) );
		$this->login_as( $admin, 'admin' );

		$update_request = new WP_REST_Request( 'POST', '/' . $this->namespace . '/dashboard/settings' );
		$update_request->set_body_params(
			array(
				'settings' => array(
					'sms_provider' => 'brevo',
				),
			)
		);
		$update_response = rest_get_server()->dispatch( $update_request );
		$this->assertEquals( 200, $update_response->get_status() );

		$get_request = new WP_REST_Request( 'GET', '/' . $this->namespace . '/dashboard/settings' );
		$get_request->set_param( 'keys', 'sms_provider' );
		$get_response = rest_get_server()->dispatch( $get_request );
		$get_data     = $get_response->get_data();

		$this->assertEquals( 200, $get_response->get_status() );
		$this->assertEquals( 'brevo', $get_data['settings']['sms_provider'] );
	}

	/**
	 * @covers Bookit_Dashboard_Bookings_API::update_settings
	 * @covers Bookit_Dashboard_Bookings_API::get_settings
	 */
	public function test_confirmed_v2_url_setting_is_saved_and_retrieved() {
		$admin = $this->create_test_staff( array( 'role' => 'admin' ) );
		$this->login_as( $admin, 'admin' );

		$custom_url = 'https://example.com/custom-booking-confirmed/';

		$update_request = new WP_REST_Request( 'POST', '/' . $this->namespace . '/dashboard/settings' );
		$update_request->set_body_params(
			array(
				'settings' => array(
					'bookit_confirmed_v2_url' => $custom_url,
				),
			)
		);
		$update_response = rest_get_server()->dispatch( $update_request );
		$this->assertEquals( 200, $update_response->get_status() );

		$this->assertSame( $custom_url, get_option( 'bookit_confirmed_v2_url' ) );

		$get_request = new WP_REST_Request( 'GET', '/' . $this->namespace . '/dashboard/settings' );
		$get_request->set_param( 'keys', 'bookit_confirmed_v2_url' );
		$get_response = rest_get_server()->dispatch( $get_request );
		$get_data     = $get_response->get_data();

		$this->assertEquals( 200, $get_response->get_status() );
		$this->assertSame( $custom_url, $get_data['settings']['bookit_confirmed_v2_url'] );
	}

	/**
	 * @covers Bookit_Dashboard_Bookings_API::update_settings
	 */
	public function test_confirmed_v2_url_setting_accepts_valid_url() {
		$admin = $this->create_test_staff( array( 'role' => 'admin' ) );
		$this->login_as( $admin, 'admin' );

		$update_request = new WP_REST_Request( 'POST', '/' . $this->namespace . '/dashboard/settings' );
		$update_request->set_body_params(
			array(
				'settings' => array(
					'bookit_confirmed_v2_url' => 'https://example.org/thank-you/',
				),
			)
		);
		$update_response = rest_get_server()->dispatch( $update_request );

		$this->assertEquals( 200, $update_response->get_status() );
		$data = $update_response->get_data();
		$this->assertArrayHasKey( 'success', $data );
		$this->assertTrue( $data['success'] );
	}

	/**
	 * @covers Bookit_Dashboard_Bookings_API::update_settings
	 * @covers Bookit_Dashboard_Bookings_API::get_settings
	 */
	public function test_digest_send_time_setting_saved_and_retrieved() {
		$admin = $this->create_test_staff( array( 'role' => 'admin' ) );
		$this->login_as( $admin, 'admin' );

		$update_request = new WP_REST_Request( 'POST', '/' . $this->namespace . '/dashboard/settings' );
		$update_request->set_body_params(
			array(
				'settings' => array(
					'staff_digest_send_time' => '17:30',
				),
			)
		);
		$update_response = rest_get_server()->dispatch( $update_request );
		$this->assertEquals( 200, $update_response->get_status() );

		$get_request = new WP_REST_Request( 'GET', '/' . $this->namespace . '/dashboard/settings' );
		$get_request->set_param( 'keys', 'staff_digest_send_time' );
		$get_response = rest_get_server()->dispatch( $get_request );
		$get_data     = $get_response->get_data();

		$this->assertEquals( 200, $get_response->get_status() );
		$this->assertSame( '17:30', $get_data['settings']['staff_digest_send_time'] );
	}

	/**
	 * @covers Bookit_Dashboard_Bookings_API::update_settings
	 * @covers Bookit_Dashboard_Bookings_API::get_settings
	 */
	public function test_schedule_send_time_setting_saved_and_retrieved() {
		$admin = $this->create_test_staff( array( 'role' => 'admin' ) );
		$this->login_as( $admin, 'admin' );

		$update_request = new WP_REST_Request( 'POST', '/' . $this->namespace . '/dashboard/settings' );
		$update_request->set_body_params(
			array(
				'settings' => array(
					'staff_schedule_send_time' => '07:00',
				),
			)
		);
		$update_response = rest_get_server()->dispatch( $update_request );
		$this->assertEquals( 200, $update_response->get_status() );

		$get_request = new WP_REST_Request( 'GET', '/' . $this->namespace . '/dashboard/settings' );
		$get_request->set_param( 'keys', 'staff_schedule_send_time' );
		$get_response = rest_get_server()->dispatch( $get_request );
		$get_data     = $get_response->get_data();

		$this->assertEquals( 200, $get_response->get_status() );
		$this->assertSame( '07:00', $get_data['settings']['staff_schedule_send_time'] );
	}

	/**
	 * @covers Bookit_Dashboard_Bookings_API::update_settings
	 * @covers Bookit_Dashboard_Bookings_API::get_settings
	 */
	public function test_weekly_day_setting_saved_and_retrieved() {
		$admin = $this->create_test_staff( array( 'role' => 'admin' ) );
		$this->login_as( $admin, 'admin' );

		$update_request = new WP_REST_Request( 'POST', '/' . $this->namespace . '/dashboard/settings' );
		$update_request->set_body_params(
			array(
				'settings' => array(
					'staff_digest_weekly_day' => 5,
				),
			)
		);
		$update_response = rest_get_server()->dispatch( $update_request );
		$this->assertEquals( 200, $update_response->get_status() );

		$get_request = new WP_REST_Request( 'GET', '/' . $this->namespace . '/dashboard/settings' );
		$get_request->set_param( 'keys', 'staff_digest_weekly_day' );
		$get_response = rest_get_server()->dispatch( $get_request );
		$get_data     = $get_response->get_data();

		$this->assertEquals( 200, $get_response->get_status() );
		$this->assertSame( 5, (int) $get_data['settings']['staff_digest_weekly_day'] );
	}

	/**
	 * @covers Bookit_Dashboard_Bookings_API::update_settings
	 */
	public function test_unknown_setting_key_is_rejected() {
		global $wpdb;

		$admin = $this->create_test_staff( array( 'role' => 'admin' ) );
		$this->login_as( $admin, 'admin' );

		$request = new WP_REST_Request( 'POST', '/' . $this->namespace . '/dashboard/settings' );
		$request->set_body_params(
			array(
				'settings' => array(
					'malicious_key' => 'x',
				),
			)
		);
		$response = rest_get_server()->dispatch( $request );

		$this->assertEquals( 200, $response->get_status() );

		$count = (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$wpdb->prefix}bookings_settings WHERE setting_key = %s",
				'malicious_key'
			)
		);
		$this->assertSame( 0, $count );
	}

	/**
	 * @covers Bookit_Dashboard_Bookings_API::send_test_email
	 */
	public function test_test_email_endpoint_returns_success_with_wp_mail_provider() {
		$admin = $this->create_test_staff( array( 'role' => 'admin' ) );
		$this->login_as( $admin, 'admin' );

		$request = new WP_REST_Request( 'POST', '/' . $this->namespace . '/dashboard/settings/test-email' );
		$request->set_body_params(
			array(
				'to_email' => 'test@example.com',
			)
		);
		$response = rest_get_server()->dispatch( $request );
		$data     = $response->get_data();

		$this->assertEquals( 200, $response->get_status() );
		$this->assertTrue( $data['success'] );
		$this->assertArrayHasKey( 'provider', $data );
		$this->assertNotEmpty( $data['provider'] );
	}

	/**
	 * Simulate Bookit dashboard login via session.
	 *
	 * @param int    $staff_id Staff row ID.
	 * @param string $role     Staff role.
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
	 * @return int
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
			'created_at'         => current_time( 'mysql' ),
			'updated_at'         => current_time( 'mysql' ),
			'deleted_at'         => null,
		);

		$data = wp_parse_args( $args, $defaults );
		$wpdb->insert(
			$wpdb->prefix . 'bookings_staff',
			$data,
			array( '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%d', '%d', '%s', '%s', '%s' )
		);

		return (int) $wpdb->insert_id;
	}

	/**
	 * Force wp_mail success in test environment.
	 *
	 * @param null|bool $return Short-circuit value.
	 * @param array     $atts wp_mail attributes.
	 * @return bool
	 */
	public function mock_pre_wp_mail( $return, $atts ) {
		return true;
	}
}
