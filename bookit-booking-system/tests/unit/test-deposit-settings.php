<?php
/**
 * Tests for deposit settings via dashboard settings API.
 *
 * @package    Bookit_Booking_System
 * @subpackage Tests
 */

/**
 * Test deposit settings persistence, defaults, and audit logging.
 */
class Test_Deposit_Settings extends WP_UnitTestCase {

	/**
	 * REST API namespace.
	 *
	 * @var string
	 */
	private $namespace = 'bookit/v1';

	/**
	 * Deposit keys list used for defaults fetch.
	 *
	 * @var string
	 */
	private $deposit_keys = 'deposit_required_default,deposit_type_default,deposit_amount_default,deposit_minimum_percent,deposit_maximum_percent,deposit_applies_to,deposit_required_for_pay_on_arrival,deposit_refundable_within_window,deposit_refundable_outside_window';

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
	 * Test deposit defaults are returned.
	 *
	 * @covers Bookit_Dashboard_Bookings_API::get_settings
	 */
	public function test_get_deposit_settings_returns_defaults() {
		$admin = $this->create_test_staff( array( 'role' => 'admin' ) );
		$this->login_as( $admin, 'admin' );

		$settings = $this->fetch_settings( $this->deposit_keys );

		$this->assertFalse( $settings['deposit_required_default'] );
		$this->assertEquals( 'percentage', $settings['deposit_type_default'] );
		$this->assertEquals( 50, $settings['deposit_amount_default'] );
		$this->assertEquals( 10, $settings['deposit_minimum_percent'] );
		$this->assertEquals( 100, $settings['deposit_maximum_percent'] );
		$this->assertEquals( 'all', $settings['deposit_applies_to'] );
		$this->assertFalse( $settings['deposit_required_for_pay_on_arrival'] );
		$this->assertTrue( $settings['deposit_refundable_within_window'] );
		$this->assertFalse( $settings['deposit_refundable_outside_window'] );
	}

	/**
	 * Test saving default required toggle.
	 *
	 * @covers Bookit_Dashboard_Bookings_API::update_settings
	 */
	public function test_save_deposit_required_default() {
		$admin = $this->create_test_staff( array( 'role' => 'admin' ) );
		$this->login_as( $admin, 'admin' );

		$this->save_settings( array( 'deposit_required_default' => true ) );
		$settings = $this->fetch_settings( 'deposit_required_default' );

		$this->assertTrue( $settings['deposit_required_default'] );
	}

	/**
	 * Test saving default deposit type.
	 *
	 * @covers Bookit_Dashboard_Bookings_API::update_settings
	 */
	public function test_save_deposit_type_default() {
		$admin = $this->create_test_staff( array( 'role' => 'admin' ) );
		$this->login_as( $admin, 'admin' );

		$this->save_settings( array( 'deposit_type_default' => 'fixed' ) );
		$settings = $this->fetch_settings( 'deposit_type_default' );

		$this->assertEquals( 'fixed', $settings['deposit_type_default'] );
	}

	/**
	 * Test saving default deposit amount.
	 *
	 * @covers Bookit_Dashboard_Bookings_API::update_settings
	 */
	public function test_save_deposit_amount_default() {
		$admin = $this->create_test_staff( array( 'role' => 'admin' ) );
		$this->login_as( $admin, 'admin' );

		$this->save_settings( array( 'deposit_amount_default' => 25 ) );
		$settings = $this->fetch_settings( 'deposit_amount_default' );

		$this->assertEquals( 25, $settings['deposit_amount_default'] );
	}

	/**
	 * Test saving minimum percentage.
	 *
	 * @covers Bookit_Dashboard_Bookings_API::update_settings
	 */
	public function test_save_deposit_minimum_percent() {
		$admin = $this->create_test_staff( array( 'role' => 'admin' ) );
		$this->login_as( $admin, 'admin' );

		$this->save_settings( array( 'deposit_minimum_percent' => 20 ) );
		$settings = $this->fetch_settings( 'deposit_minimum_percent' );

		$this->assertEquals( 20, $settings['deposit_minimum_percent'] );
	}

	/**
	 * Test saving maximum percentage.
	 *
	 * @covers Bookit_Dashboard_Bookings_API::update_settings
	 */
	public function test_save_deposit_maximum_percent() {
		$admin = $this->create_test_staff( array( 'role' => 'admin' ) );
		$this->login_as( $admin, 'admin' );

		$this->save_settings( array( 'deposit_maximum_percent' => 75 ) );
		$settings = $this->fetch_settings( 'deposit_maximum_percent' );

		$this->assertEquals( 75, $settings['deposit_maximum_percent'] );
	}

	/**
	 * Test saving applies-to mode.
	 *
	 * @covers Bookit_Dashboard_Bookings_API::update_settings
	 */
	public function test_save_deposit_applies_to() {
		$admin = $this->create_test_staff( array( 'role' => 'admin' ) );
		$this->login_as( $admin, 'admin' );

		$this->save_settings( array( 'deposit_applies_to' => 'online_only' ) );
		$settings = $this->fetch_settings( 'deposit_applies_to' );

		$this->assertEquals( 'online_only', $settings['deposit_applies_to'] );
	}

	/**
	 * Test saving refund rule toggles.
	 *
	 * @covers Bookit_Dashboard_Bookings_API::update_settings
	 */
	public function test_save_deposit_refund_rules() {
		$admin = $this->create_test_staff( array( 'role' => 'admin' ) );
		$this->login_as( $admin, 'admin' );

		$this->save_settings(
			array(
				'deposit_refundable_within_window'  => false,
				'deposit_refundable_outside_window' => true,
			)
		);
		$settings = $this->fetch_settings( 'deposit_refundable_within_window,deposit_refundable_outside_window' );

		$this->assertFalse( $settings['deposit_refundable_within_window'] );
		$this->assertTrue( $settings['deposit_refundable_outside_window'] );
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
				'settings' => array( 'deposit_required_default' => true ),
			)
		);

		$response = rest_get_server()->dispatch( $request );

		$this->assertTrue( $response->is_error() );
		$this->assertEquals( 403, $response->get_status() );
	}

	/**
	 * Test get settings requires authentication.
	 *
	 * @covers Bookit_Dashboard_Bookings_API::check_admin_permission
	 */
	public function test_get_requires_authentication() {
		$_SESSION = array();

		$request = new WP_REST_Request( 'GET', '/' . $this->namespace . '/dashboard/settings' );
		$request->set_param( 'keys', $this->deposit_keys );

		$response = rest_get_server()->dispatch( $request );

		$this->assertTrue( $response->is_error() );
		$this->assertEquals( 401, $response->get_status() );
	}

	/**
	 * Test save writes deposit settings audit entry.
	 *
	 * @covers Bookit_Dashboard_Bookings_API::update_settings
	 */
	public function test_save_logs_audit_entry() {
		global $wpdb;

		$admin = $this->create_test_staff( array( 'role' => 'admin' ) );
		$this->login_as( $admin, 'admin' );

		$this->save_settings(
			array(
				'deposit_required_default' => true,
				'deposit_type_default'     => 'fixed',
			)
		);

		$count = (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$wpdb->prefix}bookings_audit_log WHERE action = %s",
				'deposit_settings_updated'
			)
		);

		$this->assertGreaterThan( 0, $count );
	}

	/**
	 * Test percentage deposit calculation at booking step.
	 *
	 * @covers Booking_System_Stripe_Checkout::calculate_deposit
	 */
	public function test_percentage_deposit_calculates_correctly() {
		require_once BOOKIT_PLUGIN_DIR . 'includes/payment/class-stripe-checkout.php';

		$stripe_checkout = new Booking_System_Stripe_Checkout();
		$deposit_amount  = $stripe_checkout->calculate_deposit(
			array(
				'price'          => 80.00,
				'deposit_type'   => 'percentage',
				'deposit_amount' => 25,
			)
		);

		$this->assertEquals( 20.0, $deposit_amount );
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
