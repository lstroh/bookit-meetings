<?php
/**
 * Tests for Reorder API (Sprint 3, Drag & Drop)
 *
 * @package    Bookit_Booking_System
 * @subpackage Tests
 */

/**
 * Test drag & drop reordering endpoints.
 */
class Test_Reorder_API extends WP_UnitTestCase {

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
		$wpdb->query( "TRUNCATE TABLE {$wpdb->prefix}bookings_services" );
		$wpdb->query( "TRUNCATE TABLE {$wpdb->prefix}bookings_categories" );

		$_SESSION = array();

		do_action( 'rest_api_init' );
	}

	/**
	 * Tear down each test.
	 */
	public function tearDown(): void {
		global $wpdb;

		$wpdb->query( "TRUNCATE TABLE {$wpdb->prefix}bookings_staff" );
		$wpdb->query( "TRUNCATE TABLE {$wpdb->prefix}bookings_services" );
		$wpdb->query( "TRUNCATE TABLE {$wpdb->prefix}bookings_categories" );

		$_SESSION = array();

		parent::tearDown();
	}

	// ========== TESTS FOR: POST /dashboard/staff/reorder ==========

	/**
	 * Test reorder staff updates display_order.
	 *
	 * @covers Bookit_Dashboard_Bookings_API::reorder_staff
	 */
	public function test_reorder_staff_updates_display_order() {
		$staff_a = $this->create_test_staff( array( 'first_name' => 'Alice', 'display_order' => 0 ) );
		$staff_b = $this->create_test_staff( array( 'first_name' => 'Bob', 'display_order' => 1 ) );
		$staff_c = $this->create_test_staff( array( 'first_name' => 'Charlie', 'display_order' => 2 ) );

		$admin = $this->create_test_staff( array( 'role' => 'admin' ) );
		$this->login_as( $admin, 'admin' );

		$request = new WP_REST_Request( 'POST', '/' . $this->namespace . '/dashboard/staff/reorder' );
		$request->set_body_params( array(
			'staff' => array(
				array( 'id' => $staff_c, 'display_order' => 0 ),
				array( 'id' => $staff_a, 'display_order' => 1 ),
				array( 'id' => $staff_b, 'display_order' => 2 ),
			),
		) );

		$response = rest_get_server()->dispatch( $request );

		$this->assertEquals( 200, $response->get_status() );
		$this->assertTrue( $response->get_data()['success'] );

		global $wpdb;
		$get_order = function ( $id ) use ( $wpdb ) {
			return (int) $wpdb->get_var( $wpdb->prepare(
				"SELECT display_order FROM {$wpdb->prefix}bookings_staff WHERE id = %d",
				$id
			) );
		};

		$this->assertEquals( 0, $get_order( $staff_c ) );
		$this->assertEquals( 1, $get_order( $staff_a ) );
		$this->assertEquals( 2, $get_order( $staff_b ) );
	}

	/**
	 * Test reorder staff requires admin permission.
	 *
	 * @covers Bookit_Dashboard_Bookings_API::reorder_staff
	 */
	public function test_reorder_staff_requires_admin() {
		$staff = $this->create_test_staff( array( 'role' => 'staff' ) );
		$this->login_as( $staff, 'staff' );

		$request = new WP_REST_Request( 'POST', '/' . $this->namespace . '/dashboard/staff/reorder' );
		$request->set_body_params( array(
			'staff' => array(
				array( 'id' => $staff, 'display_order' => 0 ),
			),
		) );

		$response = rest_get_server()->dispatch( $request );

		$this->assertTrue( $response->is_error() );
		$this->assertEquals( 403, $response->get_status() );
	}

	/**
	 * Test reorder staff skips entries missing required fields.
	 *
	 * @covers Bookit_Dashboard_Bookings_API::reorder_staff
	 */
	public function test_reorder_staff_skips_incomplete_entries() {
		$staff_a = $this->create_test_staff( array( 'display_order' => 0 ) );
		$staff_b = $this->create_test_staff( array( 'display_order' => 1 ) );

		$admin = $this->create_test_staff( array( 'role' => 'admin' ) );
		$this->login_as( $admin, 'admin' );

		$request = new WP_REST_Request( 'POST', '/' . $this->namespace . '/dashboard/staff/reorder' );
		$request->set_body_params( array(
			'staff' => array(
				array( 'id' => $staff_a, 'display_order' => 5 ),
				array( 'id' => $staff_b ),
			),
		) );

		$response = rest_get_server()->dispatch( $request );

		$this->assertEquals( 200, $response->get_status() );

		global $wpdb;
		$order_a = (int) $wpdb->get_var( $wpdb->prepare(
			"SELECT display_order FROM {$wpdb->prefix}bookings_staff WHERE id = %d", $staff_a
		) );
		$order_b = (int) $wpdb->get_var( $wpdb->prepare(
			"SELECT display_order FROM {$wpdb->prefix}bookings_staff WHERE id = %d", $staff_b
		) );

		$this->assertEquals( 5, $order_a, 'Staff A should be updated' );
		$this->assertEquals( 1, $order_b, 'Staff B should remain unchanged (missing display_order)' );
	}

	// ========== TESTS FOR: POST /dashboard/services/reorder ==========

	/**
	 * Test reorder services updates display_order.
	 *
	 * @covers Bookit_Dashboard_Bookings_API::reorder_services
	 */
	public function test_reorder_services_updates_display_order() {
		$service_a = $this->create_test_service( array( 'name' => 'Service A', 'display_order' => 0 ) );
		$service_b = $this->create_test_service( array( 'name' => 'Service B', 'display_order' => 1 ) );
		$service_c = $this->create_test_service( array( 'name' => 'Service C', 'display_order' => 2 ) );

		$admin = $this->create_test_staff( array( 'role' => 'admin' ) );
		$this->login_as( $admin, 'admin' );

		$request = new WP_REST_Request( 'POST', '/' . $this->namespace . '/dashboard/services/reorder' );
		$request->set_body_params( array(
			'services' => array(
				array( 'id' => $service_b, 'display_order' => 0 ),
				array( 'id' => $service_c, 'display_order' => 1 ),
				array( 'id' => $service_a, 'display_order' => 2 ),
			),
		) );

		$response = rest_get_server()->dispatch( $request );

		$this->assertEquals( 200, $response->get_status() );
		$this->assertTrue( $response->get_data()['success'] );

		global $wpdb;
		$first = $wpdb->get_var(
			"SELECT id FROM {$wpdb->prefix}bookings_services ORDER BY display_order ASC LIMIT 1"
		);
		$this->assertEquals( $service_b, (int) $first );
	}

	/**
	 * Test reorder services rejects empty array.
	 *
	 * @covers Bookit_Dashboard_Bookings_API::reorder_services
	 */
	public function test_reorder_services_rejects_empty_array() {
		$admin = $this->create_test_staff( array( 'role' => 'admin' ) );
		$this->login_as( $admin, 'admin' );

		$request = new WP_REST_Request( 'POST', '/' . $this->namespace . '/dashboard/services/reorder' );
		$request->set_body_params( array(
			'services' => array(),
		) );

		$response = rest_get_server()->dispatch( $request );

		$this->assertTrue( $response->is_error() );
		$this->assertEquals( 400, $response->get_status() );
	}

	// ========== TESTS FOR: POST /dashboard/categories/reorder ==========

	/**
	 * Test reorder categories updates display_order.
	 *
	 * @covers Bookit_Dashboard_Bookings_API::reorder_categories
	 */
	public function test_reorder_categories_updates_display_order() {
		$cat_a = $this->create_test_category( array( 'name' => 'Category A', 'display_order' => 0 ) );
		$cat_b = $this->create_test_category( array( 'name' => 'Category B', 'display_order' => 1 ) );

		$admin = $this->create_test_staff( array( 'role' => 'admin' ) );
		$this->login_as( $admin, 'admin' );

		$request = new WP_REST_Request( 'POST', '/' . $this->namespace . '/dashboard/categories/reorder' );
		$request->set_body_params( array(
			'categories' => array(
				array( 'id' => $cat_b, 'display_order' => 0 ),
				array( 'id' => $cat_a, 'display_order' => 1 ),
			),
		) );

		$response = rest_get_server()->dispatch( $request );

		$this->assertEquals( 200, $response->get_status() );
		$this->assertTrue( $response->get_data()['success'] );

		global $wpdb;
		$orders = $wpdb->get_results(
			"SELECT id FROM {$wpdb->prefix}bookings_categories ORDER BY display_order ASC",
			ARRAY_A
		);
		$this->assertEquals( $cat_b, (int) $orders[0]['id'] );
		$this->assertEquals( $cat_a, (int) $orders[1]['id'] );
	}

	// ========== TESTS FOR: Route registration ==========

	/**
	 * Test reorder endpoints are registered.
	 *
	 * @covers Bookit_Dashboard_Bookings_API::register_routes
	 */
	public function test_reorder_endpoints_registered() {
		$routes = rest_get_server()->get_routes();

		$this->assertArrayHasKey( '/' . $this->namespace . '/dashboard/staff/reorder', $routes );
		$this->assertArrayHasKey( '/' . $this->namespace . '/dashboard/services/reorder', $routes );
		$this->assertArrayHasKey( '/' . $this->namespace . '/dashboard/categories/reorder', $routes );
	}

	// ========== HELPER METHODS ==========

	/**
	 * Simulate Bookit dashboard login via session.
	 *
	 * @param int    $staff_id Staff row ID.
	 * @param string $role     'admin' or 'staff'.
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
	 * @return int Staff ID.
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
	 * Create test category.
	 *
	 * @param array $args Override defaults.
	 * @return int Category ID.
	 */
	private function create_test_category( $args = array() ) {
		global $wpdb;

		$defaults = array(
			'name'          => 'Test Category ' . wp_generate_password( 4, false ),
			'description'   => null,
			'display_order' => 0,
			'is_active'     => 1,
			'created_at'    => current_time( 'mysql' ),
			'updated_at'    => current_time( 'mysql' ),
			'deleted_at'    => null,
		);

		$data = wp_parse_args( $args, $defaults );

		$wpdb->insert(
			$wpdb->prefix . 'bookings_categories',
			$data,
			array( '%s', '%s', '%d', '%d', '%s', '%s', '%s' )
		);
		return (int) $wpdb->insert_id;
	}
}
