<?php
/**
 * Tests for Team Calendar API.
 *
 * @package    Bookit_Booking_System
 * @subpackage Tests
 */

/**
 * Test Team Calendar API endpoint behavior.
 */
class Test_Team_Calendar_API extends WP_UnitTestCase {

	/**
	 * REST API namespace.
	 *
	 * @var string
	 */
	private $namespace = 'bookit/v1';

	/**
	 * Test service ID.
	 *
	 * @var int
	 */
	private $service_id = 0;

	/**
	 * Test customer ID.
	 *
	 * @var int
	 */
	private $customer_id = 0;

	/**
	 * Set up each test.
	 */
	public function setUp(): void {
		parent::setUp();

		bookit_test_truncate_tables(
			array(
				'bookings_package_redemptions',
				'bookings_customer_packages',
				'bookings_package_types',
				'bookings_payments',
				'bookings',
				'bookings_customers',
				'bookings_staff_working_hours',
				'bookings_staff',
				'bookings_services',
			)
		);

		$_SESSION = array();

		do_action( 'rest_api_init' );

		$this->service_id  = $this->create_test_service();
		$this->customer_id = $this->create_test_customer();
	}

	/**
	 * Tear down each test.
	 */
	public function tearDown(): void {
		bookit_test_truncate_tables(
			array(
				'bookings_package_redemptions',
				'bookings_customer_packages',
				'bookings_package_types',
				'bookings_payments',
				'bookings',
				'bookings_customers',
				'bookings_staff_working_hours',
				'bookings_staff',
				'bookings_services',
			)
		);

		$_SESSION = array();

		parent::tearDown();
	}

	/**
	 * @covers Bookit_Team_Calendar_API::get_team_calendar
	 */
	public function test_day_view_returns_correct_date_range() {
		$admin = $this->create_test_staff( array( 'role' => 'admin' ) );
		$this->login_as( $admin, 'admin' );

		$request = new WP_REST_Request( 'GET', '/' . $this->namespace . '/team-calendar' );
		$request->set_param( 'view_type', 'day' );
		$request->set_param( 'date', '2026-03-15' );

		$response = rest_get_server()->dispatch( $request );
		$data     = $response->get_data();

		$this->assertEquals( 200, $response->get_status() );
		$this->assertEquals( '2026-03-15', $data['date_start'] );
		$this->assertEquals( '2026-03-15', $data['date_end'] );
		$this->assertCount( 1, $data['days'] );
		$this->assertArrayHasKey( 'bookings', $data['days'][0] );
		$this->assertIsArray( $data['days'][0]['bookings'] );
	}

	/**
	 * @covers Bookit_Team_Calendar_API::get_team_calendar
	 */
	public function test_week_view_returns_monday_to_sunday() {
		$admin = $this->create_test_staff( array( 'role' => 'admin' ) );
		$this->login_as( $admin, 'admin' );

		$request = new WP_REST_Request( 'GET', '/' . $this->namespace . '/team-calendar' );
		$request->set_param( 'view_type', 'week' );
		$request->set_param( 'date', '2026-03-18' );

		$response = rest_get_server()->dispatch( $request );
		$data     = $response->get_data();

		$this->assertEquals( 200, $response->get_status() );
		$this->assertEquals( '2026-03-16', $data['date_start'] );
		$this->assertEquals( '2026-03-22', $data['date_end'] );
		$this->assertCount( 7, $data['days'] );
	}

	/**
	 * @covers Bookit_Team_Calendar_API::get_team_calendar
	 */
	public function test_month_view_returns_full_month() {
		$admin = $this->create_test_staff( array( 'role' => 'admin' ) );
		$this->login_as( $admin, 'admin' );

		$request = new WP_REST_Request( 'GET', '/' . $this->namespace . '/team-calendar' );
		$request->set_param( 'view_type', 'month' );
		$request->set_param( 'date', '2026-03-15' );

		$response = rest_get_server()->dispatch( $request );
		$data     = $response->get_data();

		$this->assertEquals( 200, $response->get_status() );
		$this->assertEquals( '2026-03-01', $data['date_start'] );
		$this->assertEquals( '2026-03-31', $data['date_end'] );
		$this->assertCount( 31, $data['days'] );
	}

	/**
	 * @covers Bookit_Team_Calendar_API::get_team_calendar
	 */
	public function test_invalid_view_type_returns_400() {
		$admin = $this->create_test_staff( array( 'role' => 'admin' ) );
		$this->login_as( $admin, 'admin' );

		$request = new WP_REST_Request( 'GET', '/' . $this->namespace . '/team-calendar' );
		$request->set_param( 'view_type', 'invalid' );
		$request->set_param( 'date', '2026-03-15' );

		$response = rest_get_server()->dispatch( $request );

		$this->assertEquals( 400, $response->get_status() );
	}

	/**
	 * @covers Bookit_Team_Calendar_API::get_team_calendar
	 */
	public function test_staff_filter_works() {
		$admin        = $this->create_test_staff( array( 'role' => 'admin' ) );
		$booking_staff = $this->create_test_staff(
			array(
				'role'  => 'staff',
				'email' => 'calendar.staff@example.com',
			)
		);
		$this->create_test_booking( $booking_staff, '2026-03-15', '10:00:00', '10:45:00' );

		$this->login_as( $admin, 'admin' );

		$request = new WP_REST_Request( 'GET', '/' . $this->namespace . '/team-calendar' );
		$request->set_param( 'view_type', 'day' );
		$request->set_param( 'date', '2026-03-15' );
		$request->set_param( 'staff_id', 999 );

		$response = rest_get_server()->dispatch( $request );
		$data     = $response->get_data();

		$this->assertEquals( 200, $response->get_status() );
		$this->assertCount( 1, $data['days'] );
		$this->assertIsArray( $data['days'][0]['bookings'] );
		$this->assertSame( array(), $data['days'][0]['bookings'] );
	}

	/**
	 * @covers Bookit_Team_Calendar_API::check_admin_permission
	 */
	public function test_requires_admin_permission() {
		$staff = $this->create_test_staff( array( 'role' => 'staff' ) );
		$this->login_as( $staff, 'staff' );

		$request = new WP_REST_Request( 'GET', '/' . $this->namespace . '/team-calendar' );
		$request->set_param( 'view_type', 'day' );
		$request->set_param( 'date', '2026-03-15' );

		$response = rest_get_server()->dispatch( $request );

		$this->assertTrue( $response->is_error() );
		$this->assertEquals( 403, $response->get_status() );
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

	/**
	 * Create test service.
	 *
	 * @return int
	 */
	private function create_test_service() {
		global $wpdb;

		$wpdb->insert(
			$wpdb->prefix . 'bookings_services',
			array(
				'name'       => 'Calendar Service',
				'duration'   => 45,
				'price'      => 35.00,
				'is_active'  => 1,
				'created_at' => current_time( 'mysql' ),
				'updated_at' => current_time( 'mysql' ),
			),
			array( '%s', '%d', '%f', '%d', '%s', '%s' )
		);

		return (int) $wpdb->insert_id;
	}

	/**
	 * Create test customer.
	 *
	 * @return int
	 */
	private function create_test_customer() {
		global $wpdb;

		$wpdb->insert(
			$wpdb->prefix . 'bookings_customers',
			array(
				'email'      => 'calendar.customer@example.com',
				'first_name' => 'Calendar',
				'last_name'  => 'Customer',
				'phone'      => '07700900999',
				'created_at' => current_time( 'mysql' ),
				'updated_at' => current_time( 'mysql' ),
			),
			array( '%s', '%s', '%s', '%s', '%s', '%s' )
		);

		return (int) $wpdb->insert_id;
	}

	/**
	 * Create test booking.
	 *
	 * @param int    $staff_id     Staff ID.
	 * @param string $booking_date Booking date.
	 * @param string $start_time   Start time.
	 * @param string $end_time     End time.
	 * @return int
	 */
	private function create_test_booking( $staff_id, $booking_date, $start_time, $end_time ) {
		global $wpdb;

		$wpdb->insert(
			$wpdb->prefix . 'bookings',
			array(
				'booking_reference' => 'BKTEST-0001',
				'customer_id'       => $this->customer_id,
				'service_id'        => $this->service_id,
				'staff_id'          => $staff_id,
				'booking_date'      => $booking_date,
				'start_time'        => $start_time,
				'end_time'          => $end_time,
				'duration'          => 45,
				'status'            => 'confirmed',
				'total_price'       => 35.00,
				'deposit_paid'      => 0.00,
				'balance_due'       => 35.00,
				'full_amount_paid'  => 0,
				'payment_method'    => 'pay_on_arrival',
				'created_at'        => current_time( 'mysql' ),
				'updated_at'        => current_time( 'mysql' ),
			),
			array( '%s', '%d', '%d', '%d', '%s', '%s', '%s', '%d', '%s', '%f', '%f', '%f', '%d', '%s', '%s', '%s' )
		);

		return (int) $wpdb->insert_id;
	}
}
