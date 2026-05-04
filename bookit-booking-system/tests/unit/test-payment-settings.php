<?php
/**
 * Tests for payment gateway settings via dashboard settings API.
 *
 * @package    Bookit_Booking_System
 * @subpackage Tests
 */

/**
 * Test payment settings persistence, masking, defaults, and audit logging.
 */
class Test_Payment_Settings extends WP_UnitTestCase {

	/**
	 * REST API namespace.
	 *
	 * @var string
	 */
	private $namespace = 'bookit/v1';

	/**
	 * Payment keys list used for defaults fetch.
	 *
	 * @var string
	 */
	private $payment_defaults_keys = 'stripe_test_mode,paypal_sandbox_mode,pay_on_arrival_enabled';

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
	 * Test payment defaults are returned.
	 *
	 * @covers Bookit_Dashboard_Bookings_API::get_settings
	 */
	public function test_get_payment_settings_returns_defaults() {
		$admin = $this->create_test_staff( array( 'role' => 'admin' ) );
		$this->login_as( $admin, 'admin' );

		$settings = $this->fetch_settings( $this->payment_defaults_keys );

		$this->assertTrue( $settings['stripe_test_mode'] );
		$this->assertTrue( $settings['paypal_sandbox_mode'] );
		$this->assertTrue( $settings['pay_on_arrival_enabled'] );
	}

	/**
	 * Test sensitive keys are masked on GET responses.
	 *
	 * @covers Bookit_Dashboard_Bookings_API::get_settings
	 */
	public function test_sensitive_keys_are_masked_on_get() {
		global $wpdb;

		$wpdb->insert(
			$wpdb->prefix . 'bookings_settings',
			array(
				'setting_key'   => 'stripe_secret_key',
				'setting_value' => 'sk_test_real_secret',
				'setting_type'  => 'string',
			),
			array( '%s', '%s', '%s' )
		);

		$admin = $this->create_test_staff( array( 'role' => 'admin' ) );
		$this->login_as( $admin, 'admin' );

		$settings = $this->fetch_settings( 'stripe_secret_key' );

		$this->assertEquals( 'SAVED', $settings['stripe_secret_key'] );
		$this->assertNotEquals( 'sk_test_real_secret', $settings['stripe_secret_key'] );
	}

	/**
	 * Test saving Stripe publishable key.
	 *
	 * @covers Bookit_Dashboard_Bookings_API::update_settings
	 */
	public function test_save_stripe_publishable_key() {
		$admin = $this->create_test_staff( array( 'role' => 'admin' ) );
		$this->login_as( $admin, 'admin' );

		$this->save_settings( array( 'stripe_publishable_key' => 'pk_test_abc123' ) );
		$settings = $this->fetch_settings( 'stripe_publishable_key' );

		$this->assertEquals( 'pk_test_abc123', $settings['stripe_publishable_key'] );
	}

	/**
	 * Test saving Stripe secret key stores masked response.
	 *
	 * @covers Bookit_Dashboard_Bookings_API::update_settings
	 */
	public function test_save_stripe_secret_key() {
		$admin = $this->create_test_staff( array( 'role' => 'admin' ) );
		$this->login_as( $admin, 'admin' );

		$this->save_settings( array( 'stripe_secret_key' => 'sk_test_xyz' ) );
		$settings = $this->fetch_settings( 'stripe_secret_key' );

		$this->assertEquals( 'SAVED', $settings['stripe_secret_key'] );
	}

	/**
	 * Test empty sensitive value does not overwrite existing key.
	 *
	 * @covers Bookit_Dashboard_Bookings_API::update_settings
	 */
	public function test_save_does_not_overwrite_key_when_empty_sent() {
		$admin = $this->create_test_staff( array( 'role' => 'admin' ) );
		$this->login_as( $admin, 'admin' );

		$this->save_settings( array( 'stripe_secret_key' => 'sk_test_original' ) );
		$this->save_settings( array( 'stripe_secret_key' => '' ) );

		$settings = $this->fetch_settings( 'stripe_secret_key' );
		$this->assertEquals( 'SAVED', $settings['stripe_secret_key'] );
	}

	/**
	 * Test sensitive key can be overwritten with new value.
	 *
	 * @covers Bookit_Dashboard_Bookings_API::update_settings
	 */
	public function test_save_overwrites_key_when_new_value_sent() {
		$admin = $this->create_test_staff( array( 'role' => 'admin' ) );
		$this->login_as( $admin, 'admin' );

		$this->save_settings( array( 'stripe_secret_key' => 'sk_test_original' ) );
		$this->save_settings( array( 'stripe_secret_key' => 'sk_test_new' ) );

		$settings = $this->fetch_settings( 'stripe_secret_key' );
		$this->assertEquals( 'SAVED', $settings['stripe_secret_key'] );
	}

	/**
	 * Test pay on arrival toggle save.
	 *
	 * @covers Bookit_Dashboard_Bookings_API::update_settings
	 */
	public function test_save_pay_on_arrival_toggle() {
		$admin = $this->create_test_staff( array( 'role' => 'admin' ) );
		$this->login_as( $admin, 'admin' );

		$this->save_settings( array( 'pay_on_arrival_enabled' => false ) );
		$settings = $this->fetch_settings( 'pay_on_arrival_enabled' );

		$this->assertFalse( $settings['pay_on_arrival_enabled'] );
	}

	/**
	 * Test stripe test mode toggle save.
	 *
	 * @covers Bookit_Dashboard_Bookings_API::update_settings
	 */
	public function test_save_stripe_test_mode() {
		$admin = $this->create_test_staff( array( 'role' => 'admin' ) );
		$this->login_as( $admin, 'admin' );

		$this->save_settings( array( 'stripe_test_mode' => false ) );
		$settings = $this->fetch_settings( 'stripe_test_mode' );

		$this->assertFalse( $settings['stripe_test_mode'] );
	}

	/**
	 * Test save endpoint requires admin.
	 *
	 * @covers Bookit_Dashboard_Bookings_API::check_admin_permission
	 */
	public function test_save_requires_admin() {
		$staff = $this->create_test_staff( array( 'role' => 'staff' ) );
		$this->login_as( $staff, 'staff' );

		$request = new WP_REST_Request( 'POST', '/' . $this->namespace . '/dashboard/settings' );
		$request->set_body_params(
			array(
				'settings' => array( 'stripe_publishable_key' => 'pk_test_123' ),
			)
		);

		$response = rest_get_server()->dispatch( $request );

		$this->assertTrue( $response->is_error() );
		$this->assertEquals( 403, $response->get_status() );
	}

	/**
	 * Test save writes payment settings audit entry.
	 *
	 * @covers Bookit_Dashboard_Bookings_API::update_settings
	 */
	public function test_save_logs_audit_entry() {
		global $wpdb;

		$admin = $this->create_test_staff( array( 'role' => 'admin' ) );
		$this->login_as( $admin, 'admin' );

		$this->save_settings(
			array(
				'stripe_publishable_key' => 'pk_test_log',
				'stripe_test_mode'       => false,
			)
		);

		$count = (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$wpdb->prefix}bookings_audit_log WHERE action = %s",
				'payment_settings_updated'
			)
		);

		$this->assertGreaterThan( 0, $count );
	}

	/**
	 * Save settings helper.
	 *
	 * @param array $settings Settings payload.
	 * @return WP_REST_Response
	 */
	private function save_settings( $settings ) {
		$request = new WP_REST_Request( 'POST', '/' . $this->namespace . '/dashboard/settings' );
		$request->set_body_params(
			array(
				'settings' => $settings,
			)
		);

		$response = rest_get_server()->dispatch( $request );
		$this->assertEquals( 200, $response->get_status() );

		return $response;
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
