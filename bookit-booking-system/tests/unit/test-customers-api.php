<?php
/**
 * Tests for Customers API.
 *
 * @package    Bookit_Booking_System
 * @subpackage Tests
 */

use Yoast\PHPUnitPolyfills\TestCases\TestCase;

/**
 * Test Customers API endpoints.
 */
class Test_Customers_API extends TestCase {

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
	private $customer_one_id;

	/**
	 * @var int
	 */
	private $customer_two_id;

	/**
	 * @var int
	 */
	private $customer_three_id;

	/**
	 * @var string
	 */
	private $customer_one_email;

	/**
	 * @var int[]
	 */
	private $booking_ids = array();

	/**
	 * @var int[]
	 */
	private $service_ids = array();

	/**
	 * Set up each test.
	 */
	public function setUp(): void {
		parent::setUp();

		if ( ! class_exists( 'Bookit_Customers_API' ) ) {
			require_once dirname( __DIR__, 2 ) . '/includes/api/class-customers-api.php';
		}

		new Bookit_Customers_API();

		$this->admin_id = $this->create_test_staff( array( 'role' => 'admin', 'first_name' => 'Admin' ) );
		$this->staff_id = $this->create_test_staff( array( 'role' => 'staff', 'first_name' => 'Staff' ) );

		$this->customer_one_email  = 'customer-one-' . wp_generate_password( 6, false ) . '@test.com';
		$this->customer_one_id     = $this->create_test_customer( array( 'email' => $this->customer_one_email, 'first_name' => 'Alice' ) );
		$this->customer_two_id     = $this->create_test_customer( array( 'email' => 'customer-two-' . wp_generate_password( 6, false ) . '@test.com', 'first_name' => 'Bob' ) );
		$this->customer_three_id   = $this->create_test_customer( array( 'email' => 'customer-three-' . wp_generate_password( 6, false ) . '@test.com', 'first_name' => 'Cara' ) );

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

		foreach ( array_unique( $this->service_ids ) as $service_id ) {
			$wpdb->delete( $wpdb->prefix . 'bookings_services', array( 'id' => (int) $service_id ), array( '%d' ) );
		}

		$customer_ids = $wpdb->get_col(
			"SELECT id
			FROM {$wpdb->prefix}bookings_customers
			WHERE deleted_at IS NOT NULL
			OR id IN (" . implode( ',', array_map( 'intval', array( $this->customer_one_id, $this->customer_two_id, $this->customer_three_id ) ) ) . ')'
		);

		foreach ( $customer_ids as $customer_id ) {
			$wpdb->delete(
				$wpdb->prefix . 'bookings_customers',
				array( 'id' => (int) $customer_id ),
				array( '%d' )
			);
		}

		$wpdb->delete( $wpdb->prefix . 'bookings_staff', array( 'id' => (int) $this->admin_id ), array( '%d' ) );
		$wpdb->delete( $wpdb->prefix . 'bookings_staff', array( 'id' => (int) $this->staff_id ), array( '%d' ) );

		$_SESSION = array();

		parent::tearDown();
	}

	/**
	 * @covers Bookit_Customers_API::register_routes
	 */
	public function test_customers_endpoint_registered() {
		$routes = rest_get_server()->get_routes();
		$this->assertArrayHasKey( '/' . self::NAMESPACE . '/dashboard/customers', $routes );
	}

	/**
	 * @covers Bookit_Customers_API::check_admin_permission
	 */
	public function test_customers_requires_admin() {
		$this->login_as( $this->staff_id, 'staff' );
		$request  = new WP_REST_Request( 'GET', '/' . self::NAMESPACE . '/dashboard/customers' );
		$response = rest_get_server()->dispatch( $request );
		$this->assertEquals( 403, $response->get_status() );
	}

	/**
	 * @covers Bookit_Customers_API::get_customers
	 */
	public function test_customers_returns_list() {
		$this->login_as( $this->admin_id, 'admin' );
		$request  = new WP_REST_Request( 'GET', '/' . self::NAMESPACE . '/dashboard/customers' );
		$response = rest_get_server()->dispatch( $request );
		$data     = $response->get_data();

		$this->assertEquals( 200, $response->get_status() );
		$this->assertTrue( $data['success'] );
		$this->assertIsArray( $data['customers'] );
		$this->assertGreaterThanOrEqual( 3, count( $data['customers'] ) );
		$this->assertArrayHasKey( 'id', $data['customers'][0] );
		$this->assertArrayHasKey( 'full_name', $data['customers'][0] );
		$this->assertArrayHasKey( 'email', $data['customers'][0] );
		$this->assertArrayHasKey( 'total_bookings', $data['customers'][0] );
		$this->assertArrayHasKey( 'total_spent', $data['customers'][0] );
		$this->assertArrayHasKey( 'status', $data['customers'][0] );
	}

	/**
	 * @covers Bookit_Customers_API::get_customers
	 */
	public function test_customers_search_by_email() {
		$this->login_as( $this->admin_id, 'admin' );
		$request = new WP_REST_Request( 'GET', '/' . self::NAMESPACE . '/dashboard/customers' );
		$request->set_param( 'search', $this->customer_one_email );
		$response = rest_get_server()->dispatch( $request );
		$data     = $response->get_data();

		$this->assertCount( 1, $data['customers'] );
		$this->assertEquals( $this->customer_one_email, $data['customers'][0]['email'] );
	}

	/**
	 * @covers Bookit_Customers_API::get_customers
	 */
	public function test_customers_search_returns_empty_for_no_match() {
		$this->login_as( $this->admin_id, 'admin' );
		$request = new WP_REST_Request( 'GET', '/' . self::NAMESPACE . '/dashboard/customers' );
		$request->set_param( 'search', 'zzznomatch@example.com' );
		$response = rest_get_server()->dispatch( $request );
		$data     = $response->get_data();

		$this->assertEquals( 200, $response->get_status() );
		$this->assertEmpty( $data['customers'] );
	}

	/**
	 * @covers Bookit_Customers_API::get_customers
	 */
	public function test_customers_pagination_works() {
		$this->login_as( $this->admin_id, 'admin' );
		$request = new WP_REST_Request( 'GET', '/' . self::NAMESPACE . '/dashboard/customers' );
		$request->set_param( 'per_page', 2 );
		$request->set_param( 'page', 1 );
		$response = rest_get_server()->dispatch( $request );
		$data     = $response->get_data();

		$this->assertEquals( 200, $response->get_status() );
		$this->assertLessThanOrEqual( 2, count( $data['customers'] ) );
		$this->assertEquals( 2, (int) $data['pagination']['per_page'] );
	}

	/**
	 * @covers Bookit_Customers_API::get_customer
	 */
	public function test_get_single_customer_returns_detail() {
		$this->login_as( $this->admin_id, 'admin' );
		$request  = new WP_REST_Request( 'GET', '/' . self::NAMESPACE . '/dashboard/customers/' . $this->customer_one_id );
		$response = rest_get_server()->dispatch( $request );
		$data     = $response->get_data();

		$this->assertEquals( 200, $response->get_status() );
		$this->assertTrue( $data['success'] );
		$this->assertArrayHasKey( 'id', $data['customer'] );
		$this->assertArrayHasKey( 'full_name', $data['customer'] );
		$this->assertArrayHasKey( 'email', $data['customer'] );
		$this->assertArrayHasKey( 'total_bookings', $data['customer'] );
		$this->assertArrayHasKey( 'bookings', $data['customer'] );
		$this->assertArrayHasKey( 'payments', $data['customer'] );
		$this->assertIsArray( $data['customer']['bookings'] );
		$this->assertIsArray( $data['customer']['payments'] );
	}

	/**
	 * @covers Bookit_Customers_API::get_customer
	 */
	public function test_get_single_customer_returns_404_for_unknown() {
		$this->login_as( $this->admin_id, 'admin' );
		$request  = new WP_REST_Request( 'GET', '/' . self::NAMESPACE . '/dashboard/customers/99999' );
		$response = rest_get_server()->dispatch( $request );
		$this->assertEquals( 404, $response->get_status() );
	}

	/**
	 * @covers Bookit_Customers_API::update_customer
	 */
	public function test_update_customer_changes_data() {
		global $wpdb;

		$this->login_as( $this->admin_id, 'admin' );
		$request = new WP_REST_Request( 'PUT', '/' . self::NAMESPACE . '/dashboard/customers/' . $this->customer_one_id );
		$request->set_body_params( array( 'first_name' => 'UpdatedName' ) );
		$response = rest_get_server()->dispatch( $request );

		$first_name = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT first_name FROM {$wpdb->prefix}bookings_customers WHERE id = %d",
				$this->customer_one_id
			)
		);

		$this->assertEquals( 200, $response->get_status() );
		$this->assertTrue( $response->get_data()['success'] );
		$this->assertEquals( 'UpdatedName', $first_name );
	}

	/**
	 * @covers Bookit_Customers_API::update_customer
	 */
	public function test_update_customer_sets_marketing_consent_date() {
		global $wpdb;

		$this->login_as( $this->admin_id, 'admin' );
		$request = new WP_REST_Request( 'PUT', '/' . self::NAMESPACE . '/dashboard/customers/' . $this->customer_one_id );
		$request->set_body_params( array( 'marketing_consent' => true ) );
		$response = rest_get_server()->dispatch( $request );

		$row = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT marketing_consent, marketing_consent_date
				FROM {$wpdb->prefix}bookings_customers
				WHERE id = %d",
				$this->customer_one_id
			),
			ARRAY_A
		);

		$this->assertEquals( 200, $response->get_status() );
		$this->assertEquals( 1, (int) $row['marketing_consent'] );
		$this->assertNotEmpty( $row['marketing_consent_date'] );
	}

	/**
	 * @covers Bookit_Customers_API::update_customer
	 */
	public function test_update_customer_clears_marketing_consent_date() {
		global $wpdb;

		$this->login_as( $this->admin_id, 'admin' );

		$request_set = new WP_REST_Request( 'PUT', '/' . self::NAMESPACE . '/dashboard/customers/' . $this->customer_one_id );
		$request_set->set_body_params( array( 'marketing_consent' => true ) );
		rest_get_server()->dispatch( $request_set );

		$request_clear = new WP_REST_Request( 'PUT', '/' . self::NAMESPACE . '/dashboard/customers/' . $this->customer_one_id );
		$request_clear->set_body_params( array( 'marketing_consent' => false ) );
		rest_get_server()->dispatch( $request_clear );

		$row = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT marketing_consent, marketing_consent_date
				FROM {$wpdb->prefix}bookings_customers
				WHERE id = %d",
				$this->customer_one_id
			),
			ARRAY_A
		);

		$this->assertEquals( 0, (int) $row['marketing_consent'] );
		$this->assertNull( $row['marketing_consent_date'] );
	}

	/**
	 * @covers Bookit_Customers_API::delete_customer
	 */
	public function test_delete_customer_anonymises_data() {
		global $wpdb;

		$this->login_as( $this->admin_id, 'admin' );
		$request  = new WP_REST_Request( 'DELETE', '/' . self::NAMESPACE . '/dashboard/customers/' . $this->customer_two_id );
		$response = rest_get_server()->dispatch( $request );

		$row = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT first_name, last_name, email, deleted_at
				FROM {$wpdb->prefix}bookings_customers
				WHERE id = %d",
				$this->customer_two_id
			),
			ARRAY_A
		);

		$this->assertEquals( 200, $response->get_status() );
		$this->assertTrue( $response->get_data()['success'] );
		$this->assertNotEmpty( $row['deleted_at'] );
		$this->assertEquals( 'Deleted', $row['first_name'] );
		$this->assertEquals( 'Customer', $row['last_name'] );
		$this->assertStringContainsString( 'deleted_', $row['email'] );
		$this->assertStringContainsString( '@deleted.invalid', $row['email'] );
	}

	/**
	 * @covers Bookit_Customers_API::delete_customer
	 */
	public function test_delete_customer_preserves_booking_records() {
		global $wpdb;

		$service_id           = $this->create_test_service();
		$this->service_ids[]  = $service_id;
		$booking_id           = $this->create_test_booking(
			array(
				'customer_id'  => $this->customer_three_id,
				'staff_id'     => $this->admin_id,
				'service_id'   => $service_id,
				'status'       => 'completed',
				'booking_date' => current_time( 'Y-m-d' ),
			)
		);
		$this->booking_ids[]  = $booking_id;

		$this->login_as( $this->admin_id, 'admin' );
		$request  = new WP_REST_Request( 'DELETE', '/' . self::NAMESPACE . '/dashboard/customers/' . $this->customer_three_id );
		$response = rest_get_server()->dispatch( $request );

		$deleted_at = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT deleted_at FROM {$wpdb->prefix}bookings WHERE id = %d",
				$booking_id
			)
		);

		$this->assertEquals( 200, $response->get_status() );
		$this->assertNull( $deleted_at );
	}

	/**
	 * @covers Bookit_Customers_API::delete_customer
	 */
	public function test_delete_customer_blocks_if_upcoming_booking_exists() {
		global $wpdb;

		$future_date          = gmdate( 'Y-m-d', strtotime( current_time( 'Y-m-d' ) . ' +7 days' ) );
		$service_id           = $this->create_test_service();
		$this->service_ids[]  = $service_id;
		$booking_id           = $this->create_test_booking(
			array(
				'customer_id'  => $this->customer_one_id,
				'staff_id'     => $this->admin_id,
				'service_id'   => $service_id,
				'status'       => 'confirmed',
				'booking_date' => $future_date,
			)
		);
		$this->booking_ids[]  = $booking_id;

		$this->login_as( $this->admin_id, 'admin' );
		$request  = new WP_REST_Request( 'DELETE', '/' . self::NAMESPACE . '/dashboard/customers/' . $this->customer_one_id );
		$response = rest_get_server()->dispatch( $request );
		$error    = $response->as_error();

		$deleted_at = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT deleted_at FROM {$wpdb->prefix}bookings_customers WHERE id = %d",
				$this->customer_one_id
			)
		);

		$this->assertEquals( 409, $response->get_status() );
		$this->assertEquals( 'has_upcoming_bookings', $error->get_error_code() );
		$this->assertNull( $deleted_at );
	}

	/**
	 * @covers Bookit_Customers_API::check_admin_permission
	 */
	public function test_customers_endpoint_requires_admin_for_delete() {
		$this->login_as( $this->staff_id, 'staff' );
		$request  = new WP_REST_Request( 'DELETE', '/' . self::NAMESPACE . '/dashboard/customers/' . $this->customer_one_id );
		$response = rest_get_server()->dispatch( $request );
		$this->assertEquals( 403, $response->get_status() );
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
