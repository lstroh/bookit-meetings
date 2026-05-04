<?php
/**
 * Tests for Google Calendar dashboard settings keys and masking.
 *
 * @package    Bookit_Booking_System
 * @subpackage Tests
 */

/**
 * Test Google Calendar settings allowlist and sensitive masking.
 */
class Test_Google_Calendar_Settings extends WP_UnitTestCase {

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
		$wpdb->query( "TRUNCATE TABLE {$wpdb->prefix}bookings_audit_log" );

		$_SESSION = array();

		do_action( 'rest_api_init' );
	}

	/**
	 * Tear down each test.
	 */
	public function tearDown(): void {
		global $wpdb;

		$wpdb->query( "TRUNCATE TABLE {$wpdb->prefix}bookings_staff" );
		$wpdb->query( "TRUNCATE TABLE {$wpdb->prefix}bookings_settings" );
		$wpdb->query( "TRUNCATE TABLE {$wpdb->prefix}bookings_audit_log" );

		$_SESSION = array();

		parent::tearDown();
	}

	/**
	 * @covers Bookit_Dashboard_Bookings_API::get_allowed_settings_keys
	 */
	public function test_google_client_id_is_in_allowed_settings_keys() {
		$keys = $this->get_allowed_settings_keys_via_reflection();
		$this->assertContains( 'google_client_id', $keys );
	}

	/**
	 * @covers Bookit_Dashboard_Bookings_API::get_allowed_settings_keys
	 */
	public function test_google_client_secret_is_in_allowed_settings_keys() {
		$keys = $this->get_allowed_settings_keys_via_reflection();
		$this->assertContains( 'google_client_secret', $keys );
	}

	/**
	 * @covers Bookit_Dashboard_Bookings_API::get_allowed_settings_keys
	 */
	public function test_google_calendar_fallback_enabled_is_in_allowed_settings_keys() {
		$keys = $this->get_allowed_settings_keys_via_reflection();
		$this->assertContains( 'google_calendar_fallback_enabled', $keys );
	}

	/**
	 * @covers Bookit_Dashboard_Bookings_API::get_settings
	 */
	public function test_google_client_secret_is_masked_in_get_settings_response() {
		global $wpdb;

		$wpdb->insert(
			$wpdb->prefix . 'bookings_settings',
			array(
				'setting_key'   => 'google_client_secret',
				'setting_value' => 'gcal-secret-value-not-for-json',
				'setting_type'  => 'string',
			),
			array( '%s', '%s', '%s' )
		);

		$admin = $this->create_test_staff( array( 'role' => 'admin' ) );
		$this->login_as( $admin, 'admin' );

		$settings = $this->fetch_settings( 'google_client_secret' );

		$this->assertEquals( 'SAVED', $settings['google_client_secret'] );
		$this->assertNotEquals( 'gcal-secret-value-not-for-json', $settings['google_client_secret'] );
	}

	/**
	 * @return array
	 */
	private function get_allowed_settings_keys_via_reflection() {
		$api    = new Bookit_Dashboard_Bookings_API();
		$ref    = new ReflectionClass( $api );
		$method = $ref->getMethod( 'get_allowed_settings_keys' );
		$method->setAccessible( true );

		return $method->invoke( $api );
	}

	/**
	 * Fetch settings helper.
	 *
	 * @param string $keys Comma-separated key list.
	 * @return array
	 */
	private function fetch_settings( $keys ) {
		$request = new WP_REST_Request( 'GET', '/' . $this->namespace . '/dashboard/settings' );
		$request->set_param( 'keys', $keys );

		$response = rest_get_server()->dispatch( $request );
		$this->assertEquals( 200, $response->get_status() );

		$data = $response->get_data();
		$this->assertTrue( $data['success'] );

		return $data['settings'];
	}

	/**
	 * Simulate dashboard login via session.
	 *
	 * @param int    $staff_id Staff row ID.
	 * @param string $role     Dashboard role.
	 * @return void
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
	 * Create a test staff member.
	 *
	 * @param array $args Optional overrides.
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
}
