<?php
/**
 * Tests for Reports API.
 *
 * @package    Bookit_Booking_System
 * @subpackage Tests
 */

use Yoast\PHPUnitPolyfills\TestCases\TestCase;

/**
 * Test Reports API endpoints.
 */
class Test_Reports_API extends TestCase {

	const NAMESPACE = 'bookit/v1';

	/**
	 * @var int
	 */
	private $admin_id;

	/**
	 * @var int
	 */
	private $staff_id;

	/**
	 * @var int
	 */
	private $service_one_id;

	/**
	 * @var int
	 */
	private $service_two_id;

	/**
	 * @var int
	 */
	private $customer_id;

	/**
	 * @var int[]
	 */
	private $booking_ids = array();

	/**
	 * @var int[]
	 */
	private $payment_ids = array();

	/**
	 * Set up each test.
	 */
	public function setUp(): void {
		parent::setUp();

		if ( ! class_exists( 'Bookit_Reports_API' ) ) {
			require_once dirname( __DIR__, 2 ) . '/includes/api/class-reports-api.php';
		}

		new Bookit_Reports_API();

		$this->admin_id      = $this->create_test_staff( array( 'role' => 'admin', 'first_name' => 'Admin' ) );
		$this->staff_id      = $this->create_test_staff( array( 'role' => 'staff', 'first_name' => 'Staff' ) );
		$this->service_one_id = $this->create_test_service( array( 'name' => 'Cut' ) );
		$this->service_two_id = $this->create_test_service( array( 'name' => 'Color' ) );
		$this->customer_id   = $this->create_test_customer();

		$_SESSION = array();
		do_action( 'rest_api_init' );
	}

	/**
	 * Tear down each test.
	 */
	public function tearDown(): void {
		global $wpdb;

		foreach ( array_unique( $this->payment_ids ) as $payment_id ) {
			$wpdb->delete(
				$wpdb->prefix . 'bookings_payments',
				array( 'id' => (int) $payment_id ),
				array( '%d' )
			);
		}

		foreach ( array_unique( $this->booking_ids ) as $booking_id ) {
			$wpdb->delete(
				$wpdb->prefix . 'bookings',
				array( 'id' => (int) $booking_id ),
				array( '%d' )
			);
		}

		$wpdb->delete( $wpdb->prefix . 'bookings_customers', array( 'id' => (int) $this->customer_id ), array( '%d' ) );
		$wpdb->delete( $wpdb->prefix . 'bookings_staff', array( 'id' => (int) $this->admin_id ), array( '%d' ) );
		$wpdb->delete( $wpdb->prefix . 'bookings_staff', array( 'id' => (int) $this->staff_id ), array( '%d' ) );
		$wpdb->delete( $wpdb->prefix . 'bookings_services', array( 'id' => (int) $this->service_one_id ), array( '%d' ) );
		$wpdb->delete( $wpdb->prefix . 'bookings_services', array( 'id' => (int) $this->service_two_id ), array( '%d' ) );

		$_SESSION = array();

		parent::tearDown();
	}

	/**
	 * @covers Bookit_Reports_API::register_routes
	 */
	public function test_overview_endpoint_registered() {
		$routes = rest_get_server()->get_routes();
		$this->assertArrayHasKey( '/' . self::NAMESPACE . '/dashboard/reports/overview', $routes );
	}

	/**
	 * @covers Bookit_Reports_API::check_admin_permission
	 */
	public function test_overview_requires_admin() {
		$this->login_as( $this->staff_id, 'staff' );
		$request  = new WP_REST_Request( 'GET', '/' . self::NAMESPACE . '/dashboard/reports/overview' );
		$response = rest_get_server()->dispatch( $request );
		$this->assertEquals( 403, $response->get_status() );
	}

	/**
	 * @covers Bookit_Reports_API::get_overview
	 */
	public function test_overview_returns_success_for_admin() {
		$this->login_as( $this->admin_id, 'admin' );
		$request  = new WP_REST_Request( 'GET', '/' . self::NAMESPACE . '/dashboard/reports/overview' );
		$response = rest_get_server()->dispatch( $request );
		$data     = $response->get_data();

		$this->assertEquals( 200, $response->get_status() );
		$this->assertTrue( $data['success'] );
		$this->assertArrayHasKey( 'this_week', $data['data'] );
		$this->assertArrayHasKey( 'this_month', $data['data'] );
		$this->assertArrayHasKey( 'all_time', $data['data'] );
		$this->assertArrayHasKey( 'revenue_trend', $data['data'] );
	}

	/**
	 * @covers Bookit_Reports_API::get_overview
	 */
	public function test_overview_this_week_counts_completed_bookings() {
		$booking_id           = $this->create_test_booking(
			array(
				'staff_id'     => $this->admin_id,
				'service_id'   => $this->service_one_id,
				'customer_id'  => $this->customer_id,
				'booking_date' => current_time( 'Y-m-d' ),
				'status'       => 'completed',
			)
		);
		$this->booking_ids[]  = $booking_id;

		$this->login_as( $this->admin_id, 'admin' );
		$request  = new WP_REST_Request( 'GET', '/' . self::NAMESPACE . '/dashboard/reports/overview' );
		$response = rest_get_server()->dispatch( $request );
		$data     = $response->get_data();

		$this->assertGreaterThanOrEqual( 1, (int) $data['data']['this_week']['total_bookings'] );
	}

	/**
	 * @covers Bookit_Reports_API::get_overview
	 */
	public function test_overview_excludes_cancelled_bookings_from_total() {
		// Create one cancelled and one completed booking for today.
		$cancelled_id        = $this->create_test_booking(
			array(
				'staff_id'     => $this->admin_id,
				'service_id'   => $this->service_one_id,
				'customer_id'  => $this->customer_id,
				'booking_date' => current_time( 'Y-m-d' ),
				'start_time'   => '10:00:00',
				'end_time'     => '11:00:00',
				'status'       => 'cancelled',
			)
		);
		$this->booking_ids[] = $cancelled_id;

		$completed_id        = $this->create_test_booking(
			array(
				'staff_id'     => $this->admin_id,
				'service_id'   => $this->service_one_id,
				'customer_id'  => $this->customer_id,
				'booking_date' => current_time( 'Y-m-d' ),
				'start_time'   => '11:00:00',
				'end_time'     => '12:00:00',
				'status'       => 'completed',
			)
		);
		$this->booking_ids[] = $completed_id;

		$this->login_as( $this->admin_id, 'admin' );
		$request  = new WP_REST_Request( 'GET', '/' . self::NAMESPACE . '/dashboard/reports/overview' );
		$response = rest_get_server()->dispatch( $request );
		$data     = $response->get_data();

		$this->assertEquals( 200, $response->get_status() );

		// Count directly from DB: non-cancelled bookings for today only.
		global $wpdb;
		$today    = current_time( 'Y-m-d' );
		$expected = (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$wpdb->prefix}bookings
				WHERE booking_date = %s
				AND status != 'cancelled'
				AND deleted_at IS NULL",
				$today
			)
		);

		// The API total for this_week must match the DB count exactly.
		$this->assertEquals( $expected, (int) $data['data']['this_week']['total_bookings'] );

		// Sanity check: expected must be at least 1 (our completed booking).
		$this->assertGreaterThanOrEqual( 1, $expected );
	}

	/**
	 * @covers Bookit_Reports_API::get_overview
	 */
	public function test_overview_no_show_rate_uses_no_show_status() {
		$booking_id           = $this->create_test_booking(
			array(
				'staff_id'     => $this->admin_id,
				'service_id'   => $this->service_one_id,
				'customer_id'  => $this->customer_id,
				'booking_date' => current_time( 'Y-m-d' ),
				'status'       => 'no_show',
			)
		);
		$this->booking_ids[]  = $booking_id;

		$this->login_as( $this->admin_id, 'admin' );
		$request  = new WP_REST_Request( 'GET', '/' . self::NAMESPACE . '/dashboard/reports/overview' );
		$response = rest_get_server()->dispatch( $request );
		$data     = $response->get_data();

		$this->assertGreaterThan( 0.0, (float) $data['data']['this_week']['no_show_rate'] );
	}

	/**
	 * @covers Bookit_Reports_API::register_routes
	 */
	public function test_revenue_endpoint_registered() {
		$routes = rest_get_server()->get_routes();
		$this->assertArrayHasKey( '/' . self::NAMESPACE . '/dashboard/reports/revenue', $routes );
	}

	/**
	 * @covers Bookit_Reports_API::check_admin_permission
	 */
	public function test_revenue_requires_admin() {
		$this->login_as( $this->staff_id, 'staff' );
		$request  = new WP_REST_Request( 'GET', '/' . self::NAMESPACE . '/dashboard/reports/revenue' );
		$response = rest_get_server()->dispatch( $request );
		$this->assertEquals( 403, $response->get_status() );
	}

	/**
	 * @covers Bookit_Reports_API::get_revenue_report
	 */
	public function test_revenue_returns_correct_structure() {
		$this->login_as( $this->admin_id, 'admin' );
		$request  = new WP_REST_Request( 'GET', '/' . self::NAMESPACE . '/dashboard/reports/revenue' );
		$response = rest_get_server()->dispatch( $request );
		$data     = $response->get_data();

		$this->assertEquals( 200, $response->get_status() );
		$this->assertTrue( $data['success'] );
		$this->assertArrayHasKey( 'total_revenue', $data['summary'] );
		$this->assertArrayHasKey( 'net_revenue', $data['summary'] );
		$this->assertArrayHasKey( 'by_service', $data );
		$this->assertArrayHasKey( 'by_staff', $data );
		$this->assertArrayHasKey( 'by_payment_method', $data );
		$this->assertArrayHasKey( 'revenue_trend', $data );
	}

	/**
	 * @covers Bookit_Reports_API::register_routes
	 */
	public function test_analytics_endpoint_registered() {
		$routes = rest_get_server()->get_routes();
		$this->assertArrayHasKey( '/' . self::NAMESPACE . '/dashboard/reports/analytics', $routes );
	}

	/**
	 * @covers Bookit_Reports_API::check_admin_permission
	 */
	public function test_analytics_requires_admin() {
		$this->login_as( $this->staff_id, 'staff' );
		$request  = new WP_REST_Request( 'GET', '/' . self::NAMESPACE . '/dashboard/reports/analytics' );
		$response = rest_get_server()->dispatch( $request );
		$this->assertEquals( 403, $response->get_status() );
	}

	/**
	 * @covers Bookit_Reports_API::get_booking_analytics
	 */
	public function test_analytics_returns_correct_structure() {
		$this->login_as( $this->admin_id, 'admin' );
		$request  = new WP_REST_Request( 'GET', '/' . self::NAMESPACE . '/dashboard/reports/analytics' );
		$response = rest_get_server()->dispatch( $request );
		$data     = $response->get_data();

		$this->assertEquals( 200, $response->get_status() );
		$this->assertTrue( $data['success'] );
		$this->assertArrayHasKey( 'summary', $data );
		$this->assertArrayHasKey( 'by_day_of_week', $data );
		$this->assertArrayHasKey( 'by_hour', $data );
		$this->assertArrayHasKey( 'heatmap', $data );
		$this->assertArrayHasKey( 'lead_time', $data );
		$this->assertArrayHasKey( 'daily_trend', $data );
		$this->assertArrayHasKey( 'total_bookings', $data['summary'] );
		$this->assertArrayHasKey( 'completion_rate', $data['summary'] );
		$this->assertArrayHasKey( 'no_show_rate', $data['summary'] );
	}

	/**
	 * @covers Bookit_Reports_API::register_routes
	 */
	public function test_staff_performance_endpoint_registered() {
		$routes = rest_get_server()->get_routes();
		$this->assertArrayHasKey( '/' . self::NAMESPACE . '/dashboard/reports/staff', $routes );
	}

	/**
	 * @covers Bookit_Reports_API::check_admin_permission
	 */
	public function test_staff_performance_requires_admin() {
		$this->login_as( $this->staff_id, 'staff' );
		$request  = new WP_REST_Request( 'GET', '/' . self::NAMESPACE . '/dashboard/reports/staff' );
		$response = rest_get_server()->dispatch( $request );
		$this->assertEquals( 403, $response->get_status() );
	}

	/**
	 * @covers Bookit_Reports_API::get_staff_performance
	 */
	public function test_staff_performance_returns_all_active_staff() {
		$this->login_as( $this->admin_id, 'admin' );
		$request  = new WP_REST_Request( 'GET', '/' . self::NAMESPACE . '/dashboard/reports/staff' );
		$response = rest_get_server()->dispatch( $request );
		$data     = $response->get_data();

		$this->assertEquals( 200, $response->get_status() );
		$this->assertTrue( $data['success'] );
		$this->assertIsArray( $data['staff'] );
		$this->assertGreaterThanOrEqual( 2, count( $data['staff'] ) );
	}

	/**
	 * @covers Bookit_Reports_API::check_admin_permission
	 */
	public function test_staff_detail_requires_admin() {
		$this->login_as( $this->staff_id, 'staff' );
		$request  = new WP_REST_Request( 'GET', '/' . self::NAMESPACE . '/dashboard/reports/staff/' . $this->admin_id );
		$response = rest_get_server()->dispatch( $request );
		$this->assertEquals( 403, $response->get_status() );
	}

	/**
	 * @covers Bookit_Reports_API::get_staff_detail
	 */
	public function test_staff_detail_returns_correct_structure() {
		$this->login_as( $this->admin_id, 'admin' );
		$request  = new WP_REST_Request( 'GET', '/' . self::NAMESPACE . '/dashboard/reports/staff/' . $this->admin_id );
		$response = rest_get_server()->dispatch( $request );
		$data     = $response->get_data();

		$this->assertEquals( 200, $response->get_status() );
		$this->assertTrue( $data['success'] );
		$this->assertArrayHasKey( 'name', $data['staff'] );
		$this->assertArrayHasKey( 'bookings', $data['staff'] );
		$this->assertArrayHasKey( 'revenue', $data['staff'] );
		$this->assertArrayHasKey( 'by_service', $data['staff'] );
		$this->assertArrayHasKey( 'weekly_trend', $data['staff'] );
		$this->assertArrayHasKey( 'time_off', $data['staff'] );
	}

	/**
	 * @covers Bookit_Reports_API::get_staff_detail
	 */
	public function test_staff_detail_returns_404_for_unknown_staff() {
		$this->login_as( $this->admin_id, 'admin' );
		$request  = new WP_REST_Request( 'GET', '/' . self::NAMESPACE . '/dashboard/reports/staff/99999' );
		$response = rest_get_server()->dispatch( $request );
		$this->assertEquals( 404, $response->get_status() );
	}

	/**
	 * Simulate Bookit dashboard login via session.
	 *
	 * Sets the $_SESSION values that Bookit_Auth::is_logged_in() and
	 * Bookit_Auth::get_current_staff() check.
	 *
	 * @param int    $staff_id Staff row ID.
	 * @param string $role     'admin' or 'staff'.
	 */
	private function login_as( $staff_id, $role = 'staff' ) {
		global $wpdb;

		$staff = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT id, email, first_name, last_name, role FROM {$wpdb->prefix}bookings_staff WHERE id = %d",
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
			'password_hash'      => wp_hash_password( 'password123' ),
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
	 * @param array $args Override defaults.
	 * @return int Service ID.
	 */
	private function create_test_service( $args = array() ) {
		global $wpdb;

		$defaults = array(
			'name'           => 'Test Service ' . wp_generate_password( 4, false ),
			'description'    => 'Test service description',
			'duration'       => 60,
			'price'          => 50.00,
			'deposit_amount' => 10.00,
			'deposit_type'   => 'fixed',
			'buffer_before'  => 0,
			'buffer_after'   => 0,
			'is_active'      => 1,
			'display_order'  => 0,
			'created_at'     => current_time( 'mysql' ),
			'updated_at'     => current_time( 'mysql' ),
			'deleted_at'     => null,
		);

		$data = wp_parse_args( $args, $defaults );

		$wpdb->insert(
			$wpdb->prefix . 'bookings_services',
			$data,
			array( '%s', '%s', '%d', '%f', '%f', '%s', '%d', '%d', '%d', '%d', '%s', '%s', '%s' )
		);
		return (int) $wpdb->insert_id;
	}

	/**
	 * Create test customer.
	 *
	 * @param array $args Override defaults.
	 * @return int Customer ID.
	 */
	private function create_test_customer( $args = array() ) {
		global $wpdb;

		$defaults = array(
			'email'      => 'customer-' . wp_generate_password( 6, false ) . '@test.com',
			'first_name' => 'Test',
			'last_name'  => 'Customer',
			'phone'      => '07700900000',
			'created_at' => current_time( 'mysql' ),
			'updated_at' => current_time( 'mysql' ),
		);

		$data = wp_parse_args( $args, $defaults );

		$wpdb->insert(
			$wpdb->prefix . 'bookings_customers',
			$data,
			array( '%s', '%s', '%s', '%s', '%s', '%s' )
		);
		return (int) $wpdb->insert_id;
	}

	/**
	 * Create test booking via direct DB insert.
	 *
	 * @param array $args Override defaults.
	 * @return int Booking ID.
	 */
	private function create_test_booking( $args = array() ) {
		global $wpdb;

		$defaults = array(
			'customer_id'     => 0,
			'service_id'      => 0,
			'staff_id'        => 0,
			'booking_date'    => '2026-06-15',
			'start_time'      => '10:00:00',
			'end_time'        => '11:00:00',
			'duration'        => 60,
			'status'          => 'confirmed',
			'total_price'     => 50.00,
			'deposit_paid'    => 0.00,
			'balance_due'     => 50.00,
			'full_amount_paid' => 0,
			'payment_method'  => 'cash',
			'created_at'      => current_time( 'mysql' ),
			'updated_at'      => current_time( 'mysql' ),
		);

		$data = wp_parse_args( $args, $defaults );

		$wpdb->insert( $wpdb->prefix . 'bookings', $data );
		return (int) $wpdb->insert_id;
	}
}
