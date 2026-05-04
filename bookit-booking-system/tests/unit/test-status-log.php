<?php
/**
 * Tests for booking status log writing.
 *
 * @package    Bookit_Booking_System
 * @subpackage Tests
 */

use Yoast\PHPUnitPolyfills\TestCases\TestCase;

/**
 * Test status log behavior.
 */
class Test_Status_Log extends TestCase {

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
	private $service_id;

	/**
	 * @var int
	 */
	private $customer_id;

	/**
	 * @var int[]
	 */
	private $booking_ids = array();

	/**
	 * Set up each test.
	 */
	public function setUp(): void {
		parent::setUp();

		if ( ! class_exists( 'Bookit_Dashboard_Bookings_API' ) ) {
			require_once dirname( __DIR__, 2 ) . '/includes/api/class-dashboard-bookings-api.php';
		}

		new Bookit_Dashboard_Bookings_API();

		$this->admin_id    = $this->create_test_staff( array( 'role' => 'admin', 'first_name' => 'Admin' ) );
		$this->staff_id    = $this->create_test_staff( array( 'role' => 'staff', 'first_name' => 'Staff' ) );
		$this->service_id  = $this->create_test_service();
		$this->customer_id = $this->create_test_customer();

		$_SESSION = array();
		do_action( 'rest_api_init' );
	}

	/**
	 * Tear down each test.
	 */
	public function tearDown(): void {
		global $wpdb;

		foreach ( array_unique( $this->booking_ids ) as $booking_id ) {
			$wpdb->delete( $wpdb->prefix . 'bookings_status_log', array( 'booking_id' => (int) $booking_id ), array( '%d' ) );
			$wpdb->delete( $wpdb->prefix . 'bookings', array( 'id' => (int) $booking_id ), array( '%d' ) );
		}

		$wpdb->delete( $wpdb->prefix . 'bookings_customers', array( 'id' => (int) $this->customer_id ), array( '%d' ) );
		$wpdb->delete( $wpdb->prefix . 'bookings_services', array( 'id' => (int) $this->service_id ), array( '%d' ) );
		$wpdb->delete( $wpdb->prefix . 'bookings_staff', array( 'id' => (int) $this->admin_id ), array( '%d' ) );
		$wpdb->delete( $wpdb->prefix . 'bookings_staff', array( 'id' => (int) $this->staff_id ), array( '%d' ) );

		$_SESSION = array();

		parent::tearDown();
	}

	/**
	 * @covers Bookit_Dashboard_Bookings_API::register_routes
	 */
	public function test_status_log_table_exists() {
		global $wpdb;

		$result = $wpdb->get_var( "SHOW TABLES LIKE '{$wpdb->prefix}bookings_status_log'" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
		$this->assertEquals( $wpdb->prefix . 'bookings_status_log', $result );
	}

	/**
	 * @covers Bookit_Dashboard_Bookings_API::mark_booking_complete
	 */
	public function test_mark_complete_writes_status_log() {
		global $wpdb;

		$booking_id          = $this->create_test_booking(
			array(
				'staff_id'    => $this->staff_id,
				'service_id'  => $this->service_id,
				'customer_id' => $this->customer_id,
				'status'      => 'confirmed',
			)
		);
		$this->booking_ids[] = $booking_id;

		$this->login_as( $this->admin_id, 'admin' );
		$request  = new WP_REST_Request( 'POST', '/' . self::NAMESPACE . '/dashboard/bookings/' . $booking_id . '/complete' );
		$response = rest_get_server()->dispatch( $request );

		$log = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$wpdb->prefix}bookings_status_log WHERE booking_id = %d",
				$booking_id
			)
		);

		$this->assertEquals( 200, $response->get_status() );
		$this->assertNotNull( $log );
		$this->assertEquals( 'confirmed', $log->old_status );
		$this->assertEquals( 'completed', $log->new_status );
		$this->assertEquals( $this->admin_id, (int) $log->changed_by_staff_id );
	}

	/**
	 * @covers Bookit_Dashboard_Bookings_API::mark_booking_no_show
	 */
	public function test_mark_no_show_writes_status_log_with_underscore_status() {
		global $wpdb;

		$booking_id          = $this->create_test_booking(
			array(
				'staff_id'    => $this->staff_id,
				'service_id'  => $this->service_id,
				'customer_id' => $this->customer_id,
				'status'      => 'confirmed',
			)
		);
		$this->booking_ids[] = $booking_id;

		$this->login_as( $this->admin_id, 'admin' );
		$request  = new WP_REST_Request( 'POST', '/' . self::NAMESPACE . '/dashboard/bookings/' . $booking_id . '/no-show' );
		$response = rest_get_server()->dispatch( $request );

		$log = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$wpdb->prefix}bookings_status_log WHERE booking_id = %d",
				$booking_id
			)
		);

		$this->assertEquals( 200, $response->get_status() );
		$this->assertNotNull( $log );
		$this->assertEquals( 'no_show', $log->new_status );
	}

	/**
	 * @covers Bookit_Dashboard_Bookings_API::update_booking
	 */
	public function test_update_booking_status_writes_status_log() {
		global $wpdb;

		$booking_id          = $this->create_test_booking(
			array(
				'staff_id'    => $this->staff_id,
				'service_id'  => $this->service_id,
				'customer_id' => $this->customer_id,
				'status'      => 'confirmed',
			)
		);
		$this->booking_ids[] = $booking_id;

		$this->login_as( $this->admin_id, 'admin' );
		$request = new WP_REST_Request( 'PUT', '/' . self::NAMESPACE . '/dashboard/bookings/' . $booking_id );
		$request->set_body_params(
			array(
				'service_id'        => $this->service_id,
				'staff_id'          => $this->staff_id,
				'booking_date'      => '2026-06-15',
				'booking_time'      => '10:00:00',
				'status'            => 'completed',
				'payment_method'    => 'cash',
				'amount_paid'       => 0,
				'send_notification' => false,
			)
		);
		rest_get_server()->dispatch( $request );

		$log = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$wpdb->prefix}bookings_status_log WHERE booking_id = %d",
				$booking_id
			)
		);

		$this->assertNotNull( $log );
		$this->assertEquals( 'completed', $log->new_status );
	}

	/**
	 * @covers Bookit_Dashboard_Bookings_API::update_booking
	 */
	public function test_update_booking_without_status_change_does_not_write_log() {
		global $wpdb;

		$booking_id          = $this->create_test_booking(
			array(
				'staff_id'    => $this->staff_id,
				'service_id'  => $this->service_id,
				'customer_id' => $this->customer_id,
				'status'      => 'confirmed',
			)
		);
		$this->booking_ids[] = $booking_id;

		$this->login_as( $this->admin_id, 'admin' );
		$request = new WP_REST_Request( 'PUT', '/' . self::NAMESPACE . '/dashboard/bookings/' . $booking_id );
		$request->set_body_params(
			array(
				'service_id'        => $this->service_id,
				'staff_id'          => $this->staff_id,
				'booking_date'      => '2026-06-15',
				'booking_time'      => '10:00:00',
				'status'            => 'confirmed',
				'payment_method'    => 'cash',
				'amount_paid'       => 0,
				'staff_notes'       => 'test note',
				'send_notification' => false,
			)
		);
		$response = rest_get_server()->dispatch( $request );

		$count = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$wpdb->prefix}bookings_status_log WHERE booking_id = %d",
				$booking_id
			)
		);

		$this->assertEquals( 200, $response->get_status() );
		$this->assertEquals( 0, (int) $count );
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
