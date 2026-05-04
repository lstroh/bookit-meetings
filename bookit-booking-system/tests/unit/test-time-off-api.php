<?php
/**
 * Tests for staff time-off self-service API.
 *
 * @package    Bookit_Booking_System
 * @subpackage Tests
 */

use Yoast\PHPUnitPolyfills\TestCases\TestCase;

/**
 * Test time-off API endpoints.
 */
class Test_Time_Off_API extends TestCase {

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
	 * @var int[]
	 */
	private $booking_ids = array();

	/**
	 * @var int[]
	 */
	private $block_ids = array();

	/**
	 * @var int[]
	 */
	private $service_ids = array();

	/**
	 * @var int[]
	 */
	private $customer_ids = array();

	/**
	 * Set up each test.
	 */
	public function setUp(): void {
		parent::setUp();

		if ( ! class_exists( 'Bookit_Dashboard_Bookings_API' ) ) {
			require_once dirname( __DIR__, 2 ) . '/includes/api/class-dashboard-bookings-api.php';
		}

		new Bookit_Dashboard_Bookings_API();

		$this->admin_id = $this->create_test_staff( array( 'role' => 'admin', 'first_name' => 'Admin' ) );
		$this->staff_id = $this->create_test_staff( array( 'role' => 'staff', 'first_name' => 'Staff' ) );

		$_SESSION = array();
		do_action( 'rest_api_init' );
	}

	/**
	 * Tear down each test.
	 */
	public function tearDown(): void {
		global $wpdb;

		foreach ( array_unique( $this->booking_ids ) as $booking_id ) {
			$wpdb->delete( $wpdb->prefix . 'bookings', array( 'id' => (int) $booking_id ), array( '%d' ) );
		}

		foreach ( array_unique( $this->block_ids ) as $block_id ) {
			$wpdb->delete( $wpdb->prefix . 'bookings_staff_working_hours', array( 'id' => (int) $block_id ), array( '%d' ) );
		}

		foreach ( array_unique( $this->customer_ids ) as $customer_id ) {
			$wpdb->delete( $wpdb->prefix . 'bookings_customers', array( 'id' => (int) $customer_id ), array( '%d' ) );
		}

		foreach ( array_unique( $this->service_ids ) as $service_id ) {
			$wpdb->delete( $wpdb->prefix . 'bookings_services', array( 'id' => (int) $service_id ), array( '%d' ) );
		}

		$wpdb->delete( $wpdb->prefix . 'bookings_staff_working_hours', array( 'staff_id' => (int) $this->admin_id ), array( '%d' ) );
		$wpdb->delete( $wpdb->prefix . 'bookings_staff_working_hours', array( 'staff_id' => (int) $this->staff_id ), array( '%d' ) );
		$wpdb->delete( $wpdb->prefix . 'bookings_staff', array( 'id' => (int) $this->admin_id ), array( '%d' ) );
		$wpdb->delete( $wpdb->prefix . 'bookings_staff', array( 'id' => (int) $this->staff_id ), array( '%d' ) );

		$_SESSION = array();

		parent::tearDown();
	}

	/**
	 * @covers Bookit_Dashboard_Bookings_API::register_routes
	 */
	public function test_my_availability_endpoint_registered() {
		$routes = rest_get_server()->get_routes();
		$this->assertArrayHasKey( '/' . self::NAMESPACE . '/dashboard/my-availability', $routes );
	}

	/**
	 * @covers Bookit_Dashboard_Bookings_API::create_my_availability_block
	 */
	public function test_create_time_off_block_succeeds() {
		global $wpdb;

		$tomorrow = gmdate( 'Y-m-d', strtotime( current_time( 'Y-m-d' ) . ' +1 day' ) );

		$this->login_as( $this->staff_id, 'staff' );
		$request = new WP_REST_Request( 'POST', '/' . self::NAMESPACE . '/dashboard/my-availability' );
		$request->set_body_params(
			array(
				'date_from' => $tomorrow,
				'date_to'   => $tomorrow,
				'all_day'   => true,
				'reason'    => 'vacation',
				'repeat'    => 'none',
			)
		);
		$response = rest_get_server()->dispatch( $request );

		$block = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT id, staff_id, is_working
				FROM {$wpdb->prefix}bookings_staff_working_hours
				WHERE staff_id = %d AND specific_date = %s AND is_working = 0",
				$this->staff_id,
				$tomorrow
			),
			ARRAY_A
		);

		if ( ! empty( $block['id'] ) ) {
			$this->block_ids[] = (int) $block['id'];
		}

		$this->assertEquals( 200, $response->get_status() );
		$this->assertTrue( $response->get_data()['success'] );
		$this->assertNotNull( $block );
		$this->assertEquals( $this->staff_id, (int) $block['staff_id'] );
		$this->assertEquals( 0, (int) $block['is_working'] );
	}

	/**
	 * @covers Bookit_Dashboard_Bookings_API::create_my_availability_block
	 */
	public function test_create_time_off_rejects_past_dates() {
		$this->login_as( $this->staff_id, 'staff' );
		$request = new WP_REST_Request( 'POST', '/' . self::NAMESPACE . '/dashboard/my-availability' );
		$request->set_body_params(
			array(
				'date_from' => '2020-01-01',
				'date_to'   => '2020-01-01',
				'all_day'   => true,
				'reason'    => 'vacation',
				'repeat'    => 'none',
			)
		);
		$response = rest_get_server()->dispatch( $request );

		$this->assertEquals( 400, $response->get_status() );
	}

	/**
	 * @covers Bookit_Dashboard_Bookings_API::delete_my_availability_block
	 */
	public function test_staff_can_only_delete_own_blocks() {
		global $wpdb;

		$staff_two = $this->create_test_staff( array( 'role' => 'staff', 'first_name' => 'Second' ) );
		$tomorrow  = gmdate( 'Y-m-d', strtotime( current_time( 'Y-m-d' ) . ' +1 day' ) );

		$this->login_as( $this->staff_id, 'staff' );
		$create_request = new WP_REST_Request( 'POST', '/' . self::NAMESPACE . '/dashboard/my-availability' );
		$create_request->set_body_params(
			array(
				'date_from' => $tomorrow,
				'date_to'   => $tomorrow,
				'all_day'   => true,
				'reason'    => 'vacation',
				'repeat'    => 'none',
			)
		);
		rest_get_server()->dispatch( $create_request );

		$block_id = (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT id
				FROM {$wpdb->prefix}bookings_staff_working_hours
				WHERE staff_id = %d AND specific_date = %s
				ORDER BY id DESC LIMIT 1",
				$this->staff_id,
				$tomorrow
			)
		);
		$this->block_ids[] = $block_id;

		$this->login_as( $staff_two, 'staff' );
		$delete_request = new WP_REST_Request( 'DELETE', '/' . self::NAMESPACE . '/dashboard/my-availability/' . $block_id );
		$response       = rest_get_server()->dispatch( $delete_request );

		$still_exists = (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$wpdb->prefix}bookings_staff_working_hours WHERE id = %d",
				$block_id
			)
		);

		$wpdb->delete( $wpdb->prefix . 'bookings_staff', array( 'id' => (int) $staff_two ), array( '%d' ) );

		$this->assertEquals( 403, $response->get_status() );
		$this->assertEquals( 1, $still_exists );
	}

	/**
	 * @covers Bookit_Dashboard_Bookings_API::delete_my_availability_block
	 */
	public function test_delete_own_block_succeeds() {
		global $wpdb;

		$tomorrow = gmdate( 'Y-m-d', strtotime( current_time( 'Y-m-d' ) . ' +1 day' ) );

		$this->login_as( $this->staff_id, 'staff' );
		$create_request = new WP_REST_Request( 'POST', '/' . self::NAMESPACE . '/dashboard/my-availability' );
		$create_request->set_body_params(
			array(
				'date_from' => $tomorrow,
				'date_to'   => $tomorrow,
				'all_day'   => true,
				'reason'    => 'vacation',
				'repeat'    => 'none',
			)
		);
		rest_get_server()->dispatch( $create_request );

		$block_id = (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT id
				FROM {$wpdb->prefix}bookings_staff_working_hours
				WHERE staff_id = %d AND specific_date = %s
				ORDER BY id DESC LIMIT 1",
				$this->staff_id,
				$tomorrow
			)
		);

		$delete_request = new WP_REST_Request( 'DELETE', '/' . self::NAMESPACE . '/dashboard/my-availability/' . $block_id );
		$response       = rest_get_server()->dispatch( $delete_request );

		$exists = (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$wpdb->prefix}bookings_staff_working_hours WHERE id = %d",
				$block_id
			)
		);

		$this->assertEquals( 200, $response->get_status() );
		$this->assertEquals( 0, $exists );
	}

	/**
	 * @covers Bookit_Dashboard_Bookings_API::get_my_availability
	 */
	public function test_get_availability_returns_only_own_blocks() {
		global $wpdb;

		$tomorrow = gmdate( 'Y-m-d', strtotime( current_time( 'Y-m-d' ) . ' +1 day' ) );

		$this->login_as( $this->admin_id, 'admin' );
		$admin_create = new WP_REST_Request( 'POST', '/' . self::NAMESPACE . '/dashboard/my-availability' );
		$admin_create->set_body_params(
			array(
				'date_from' => $tomorrow,
				'date_to'   => $tomorrow,
				'all_day'   => true,
				'reason'    => 'vacation',
				'repeat'    => 'none',
			)
		);
		rest_get_server()->dispatch( $admin_create );

		$this->login_as( $this->staff_id, 'staff' );
		$staff_create = new WP_REST_Request( 'POST', '/' . self::NAMESPACE . '/dashboard/my-availability' );
		$staff_create->set_body_params(
			array(
				'date_from' => gmdate( 'Y-m-d', strtotime( current_time( 'Y-m-d' ) . ' +2 days' ) ),
				'date_to'   => gmdate( 'Y-m-d', strtotime( current_time( 'Y-m-d' ) . ' +2 days' ) ),
				'all_day'   => true,
				'reason'    => 'vacation',
				'repeat'    => 'none',
			)
		);
		rest_get_server()->dispatch( $staff_create );

		$request  = new WP_REST_Request( 'GET', '/' . self::NAMESPACE . '/dashboard/my-availability' );
		$response = rest_get_server()->dispatch( $request );
		$data     = $response->get_data();

		foreach ( $data['blocks'] as $block ) {
			$this->block_ids[] = (int) $block['id'];
			$owner_id          = (int) $wpdb->get_var(
				$wpdb->prepare(
					"SELECT staff_id FROM {$wpdb->prefix}bookings_staff_working_hours WHERE id = %d",
					$block['id']
				)
			);
			$this->assertEquals( $this->staff_id, $owner_id );
		}
	}

	/**
	 * @covers Bookit_Dashboard_Bookings_API::create_my_availability_block
	 */
	public function test_conflict_detection_returns_409() {
		$service_id          = $this->create_test_service();
		$this->service_ids[] = $service_id;
		$customer_id         = $this->create_test_customer();
		$this->customer_ids[] = $customer_id;
		$future_date         = gmdate( 'Y-m-d', strtotime( current_time( 'Y-m-d' ) . ' +3 days' ) );
		$booking_id          = $this->create_test_booking(
			array(
				'staff_id'     => $this->staff_id,
				'service_id'   => $service_id,
				'customer_id'  => $customer_id,
				'booking_date' => $future_date,
				'status'       => 'confirmed',
			)
		);
		$this->booking_ids[] = $booking_id;

		$this->login_as( $this->staff_id, 'staff' );
		$request = new WP_REST_Request( 'POST', '/' . self::NAMESPACE . '/dashboard/my-availability' );
		$request->set_body_params(
			array(
				'date_from' => $future_date,
				'date_to'   => $future_date,
				'all_day'   => true,
				'reason'    => 'vacation',
				'repeat'    => 'none',
			)
		);
		$response = rest_get_server()->dispatch( $request );
		$error    = $response->as_error();

		$this->assertEquals( 409, $response->get_status() );
		$this->assertEquals( 'booking_conflict', $error->get_error_code() );
		$this->assertStringContainsString( 'confirmed booking', $error->get_error_message() );
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
