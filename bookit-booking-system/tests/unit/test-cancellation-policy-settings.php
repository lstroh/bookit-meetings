<?php
/**
 * Tests for cancellation policy settings via dashboard settings API.
 *
 * @package    Bookit_Booking_System
 * @subpackage Tests
 */

/**
 * Test cancellation policy settings persistence and defaults.
 */
class Test_Cancellation_Policy_Settings extends WP_UnitTestCase {

	/**
	 * REST API namespace.
	 *
	 * @var string
	 */
	private $namespace = 'bookit/v1';

	/**
	 * Cancellation setting keys.
	 *
	 * @var string
	 */
	private $cancellation_keys = 'cancellation_window_hours,within_window_refund_type,within_window_refund_percent,late_cancel_refund_type,late_cancel_refund_percent,noshow_refund_type,noshow_refund_percent,reschedule_policy,reschedule_fee_amount,cancellation_policy_text,auto_refund_enabled';

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
	 * Test cancellation defaults are returned for fresh installs.
	 *
	 * @covers Bookit_Dashboard_Bookings_API::get_settings
	 */
	public function test_get_cancellation_settings_returns_defaults() {
		$admin = $this->create_test_staff( array( 'role' => 'admin' ) );
		$this->login_as( $admin, 'admin' );

		$request = new WP_REST_Request( 'GET', '/' . $this->namespace . '/dashboard/settings' );
		$request->set_param( 'keys', $this->cancellation_keys );

		$response = rest_get_server()->dispatch( $request );
		$data     = $response->get_data();

		$this->assertEquals( 200, $response->get_status() );
		$this->assertTrue( $data['success'] );
		$this->assertEquals( 24, $data['settings']['cancellation_window_hours'] );
		$this->assertEquals( 'full', $data['settings']['within_window_refund_type'] );
		$this->assertEquals( 100, $data['settings']['within_window_refund_percent'] );
		$this->assertEquals( 'none', $data['settings']['late_cancel_refund_type'] );
		$this->assertEquals( 0, $data['settings']['late_cancel_refund_percent'] );
		$this->assertEquals( 'none', $data['settings']['noshow_refund_type'] );
		$this->assertEquals( 0, $data['settings']['noshow_refund_percent'] );
		$this->assertEquals( 'free', $data['settings']['reschedule_policy'] );
		$this->assertEquals( '0.00', $data['settings']['reschedule_fee_amount'] );
		$this->assertEquals(
			'Free cancellation up to 24 hours before your appointment. Late cancellations and no-shows may forfeit their deposit.',
			$data['settings']['cancellation_policy_text']
		);
		$this->assertFalse( $data['settings']['auto_refund_enabled'] );
	}

	/**
	 * Test saving cancellation window hours.
	 *
	 * @covers Bookit_Dashboard_Bookings_API::update_settings
	 */
	public function test_save_cancellation_window_hours() {
		$admin = $this->create_test_staff( array( 'role' => 'admin' ) );
		$this->login_as( $admin, 'admin' );

		$this->save_settings( array( 'cancellation_window_hours' => 48 ) );
		$settings = $this->fetch_settings( 'cancellation_window_hours' );

		$this->assertEquals( 48, $settings['cancellation_window_hours'] );
	}

	/**
	 * Test saving within-window partial refund settings.
	 *
	 * @covers Bookit_Dashboard_Bookings_API::update_settings
	 */
	public function test_save_within_window_refund_type_partial() {
		$admin = $this->create_test_staff( array( 'role' => 'admin' ) );
		$this->login_as( $admin, 'admin' );

		$this->save_settings(
			array(
				'within_window_refund_type'    => 'partial',
				'within_window_refund_percent' => 75,
			)
		);

		$settings = $this->fetch_settings( 'within_window_refund_type,within_window_refund_percent' );

		$this->assertEquals( 'partial', $settings['within_window_refund_type'] );
		$this->assertEquals( 75, $settings['within_window_refund_percent'] );
	}

	/**
	 * Test saving late cancellation refund type.
	 *
	 * @covers Bookit_Dashboard_Bookings_API::update_settings
	 */
	public function test_save_late_cancel_refund_type_none() {
		$admin = $this->create_test_staff( array( 'role' => 'admin' ) );
		$this->login_as( $admin, 'admin' );

		$response = $this->save_settings( array( 'late_cancel_refund_type' => 'none' ) );

		$this->assertEquals( 200, $response->get_status() );
	}

	/**
	 * Test saving no-show policy.
	 *
	 * @covers Bookit_Dashboard_Bookings_API::update_settings
	 */
	public function test_save_noshow_refund_type() {
		$admin = $this->create_test_staff( array( 'role' => 'admin' ) );
		$this->login_as( $admin, 'admin' );

		$this->save_settings(
			array(
				'noshow_refund_type'    => 'partial',
				'noshow_refund_percent' => 25,
			)
		);

		$settings = $this->fetch_settings( 'noshow_refund_type,noshow_refund_percent' );

		$this->assertEquals( 'partial', $settings['noshow_refund_type'] );
		$this->assertEquals( 25, $settings['noshow_refund_percent'] );
	}

	/**
	 * Test saving reschedule policy and fee.
	 *
	 * @covers Bookit_Dashboard_Bookings_API::update_settings
	 */
	public function test_save_reschedule_policy_fee() {
		$admin = $this->create_test_staff( array( 'role' => 'admin' ) );
		$this->login_as( $admin, 'admin' );

		$this->save_settings(
			array(
				'reschedule_policy'     => 'fee',
				'reschedule_fee_amount' => '10.00',
			)
		);

		$settings = $this->fetch_settings( 'reschedule_policy,reschedule_fee_amount' );

		$this->assertEquals( 'fee', $settings['reschedule_policy'] );
		$this->assertEquals( '10.00', $settings['reschedule_fee_amount'] );
	}

	/**
	 * Test fee policy saves without explicit fee amount.
	 *
	 * @covers Bookit_Dashboard_Bookings_API::update_settings
	 */
	public function test_save_reschedule_fee_requires_fee_amount() {
		$admin = $this->create_test_staff( array( 'role' => 'admin' ) );
		$this->login_as( $admin, 'admin' );

		$this->save_settings( array( 'reschedule_policy' => 'fee' ) );
		$settings = $this->fetch_settings( 'reschedule_policy,reschedule_fee_amount' );

		$this->assertEquals( 'fee', $settings['reschedule_policy'] );
		$this->assertEquals( '0.00', $settings['reschedule_fee_amount'] );
	}

	/**
	 * Test auto refund enabled setting.
	 *
	 * @covers Bookit_Dashboard_Bookings_API::update_settings
	 */
	public function test_save_auto_refund_enabled() {
		$admin = $this->create_test_staff( array( 'role' => 'admin' ) );
		$this->login_as( $admin, 'admin' );

		$this->save_settings( array( 'auto_refund_enabled' => true ) );
		$settings = $this->fetch_settings( 'auto_refund_enabled' );

		$this->assertTrue( $settings['auto_refund_enabled'] );
	}

	/**
	 * Test cancellation policy text persistence.
	 *
	 * @covers Bookit_Dashboard_Bookings_API::update_settings
	 */
	public function test_save_cancellation_policy_text() {
		$admin = $this->create_test_staff( array( 'role' => 'admin' ) );
		$this->login_as( $admin, 'admin' );

		$text = 'Custom policy text for customers.';
		$this->save_settings( array( 'cancellation_policy_text' => $text ) );
		$settings = $this->fetch_settings( 'cancellation_policy_text' );

		$this->assertEquals( $text, $settings['cancellation_policy_text'] );
	}

	/**
	 * Test settings save requires admin role.
	 *
	 * @covers Bookit_Dashboard_Bookings_API::check_admin_permission
	 */
	public function test_save_requires_admin() {
		$staff = $this->create_test_staff( array( 'role' => 'staff' ) );
		$this->login_as( $staff, 'staff' );

		$request = new WP_REST_Request( 'POST', '/' . $this->namespace . '/dashboard/settings' );
		$request->set_body_params(
			array(
				'settings' => array( 'cancellation_window_hours' => 48 ),
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
		$request->set_param( 'keys', $this->cancellation_keys );

		$response = rest_get_server()->dispatch( $request );

		$this->assertTrue( $response->is_error() );
		$this->assertEquals( 401, $response->get_status() );
	}

	/**
	 * Test cancellation policy save writes an audit log entry.
	 *
	 * @covers Bookit_Dashboard_Bookings_API::update_settings
	 */
	public function test_save_logs_audit_entry() {
		global $wpdb;

		$admin = $this->create_test_staff( array( 'role' => 'admin' ) );
		$this->login_as( $admin, 'admin' );

		$this->save_settings(
			array(
				'cancellation_window_hours' => 72,
				'late_cancel_refund_type'   => 'none',
			)
		);

		$count = (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$wpdb->prefix}bookings_audit_log WHERE action = %s",
				'cancellation_policy_updated'
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
	 * Simulate Bookit dashboard login via session.
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
