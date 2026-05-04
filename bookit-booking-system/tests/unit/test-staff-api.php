<?php
/**
 * Tests for Bookit_Staff_API (Staff Selection UI - Task 3)
 *
 * @package    Bookit_Booking_System
 * @subpackage Tests
 */

/**
 * Test Bookit_Staff_API REST endpoints.
 */
class Test_Staff_API extends WP_UnitTestCase {

	/**
	 * REST API namespace.
	 *
	 * @var string
	 */
	private $namespace = 'bookit/v1';

	/**
	 * Staff selection route.
	 *
	 * @var string
	 */
	private $route = '/staff/select';

	/**
	 * Set up each test.
	 */
	public function setUp(): void {
		parent::setUp();

		global $wpdb;

		$wpdb->query( "TRUNCATE TABLE {$wpdb->prefix}bookings_staff" );
		$wpdb->query( "TRUNCATE TABLE {$wpdb->prefix}bookings_staff_services" );
		$wpdb->query( "TRUNCATE TABLE {$wpdb->prefix}bookings_services" );
		$wpdb->query( "TRUNCATE TABLE {$wpdb->prefix}bookings_categories" );
		$wpdb->query( "TRUNCATE TABLE {$wpdb->prefix}bookings_service_categories" );

		Bookit_Session_Manager::clear();
		if ( session_status() === PHP_SESSION_ACTIVE ) {
			session_destroy();
		}
		if ( isset( $_SESSION ) ) {
			$_SESSION = array();
		}

		$column_exists = $wpdb->get_results(
			$wpdb->prepare(
				'SHOW COLUMNS FROM %1s LIKE %s',
				$wpdb->prefix . 'bookings_staff_services',
				'custom_price'
			)
		);
		if ( empty( $column_exists ) ) {
			$wpdb->query(
				"ALTER TABLE {$wpdb->prefix}bookings_staff_services 
				ADD COLUMN custom_price DECIMAL(10,2) NULL DEFAULT NULL 
				COMMENT 'Custom price for this staff member for this service'"
			);
		}

		do_action( 'rest_api_init' );
	}

	/**
	 * Tear down each test.
	 */
	public function tearDown(): void {
		global $wpdb;

		$wpdb->query( "TRUNCATE TABLE {$wpdb->prefix}bookings_staff" );
		$wpdb->query( "TRUNCATE TABLE {$wpdb->prefix}bookings_staff_services" );
		$wpdb->query( "TRUNCATE TABLE {$wpdb->prefix}bookings_services" );
		$wpdb->query( "TRUNCATE TABLE {$wpdb->prefix}bookings_categories" );
		$wpdb->query( "TRUNCATE TABLE {$wpdb->prefix}bookings_service_categories" );

		Bookit_Session_Manager::clear();
		if ( session_status() === PHP_SESSION_ACTIVE ) {
			session_destroy();
		}
		if ( isset( $_SESSION ) ) {
			$_SESSION = array();
		}

		parent::tearDown();
	}

	/**
	 * Test POST with valid staff_id saves to session.
	 *
	 * @covers Bookit_Staff_API::select_staff
	 */
	public function test_post_with_valid_staff_id_saves_to_session() {
		$service_id = $this->create_test_service( array( 'name' => 'Test Service', 'price' => 35.00 ) );
		$staff_id   = $this->create_test_staff( array( 'first_name' => 'Jane', 'last_name' => 'Doe' ) );
		$this->link_staff_to_service( $staff_id, $service_id, 40.00 );

		Bookit_Session_Manager::init();
		Bookit_Session_Manager::set_data( array(
			'service_id'   => $service_id,
			'current_step' => 2,
		) );

		$request = new WP_REST_Request( 'POST', '/' . $this->namespace . $this->route );
		$request->set_param( 'staff_id', $staff_id );
		$request->set_header( 'X-WP-Nonce', wp_create_nonce( 'wp_rest' ) );

		rest_get_server()->dispatch( $request );

		$wizard_data = Bookit_Session_Manager::get_data();
		$this->assertEquals( $staff_id, (int) $wizard_data['staff_id'] );
		$this->assertEquals( 'Jane Doe', $wizard_data['staff_name'] );
		$this->assertEquals( 40.00, (float) $wizard_data['staff_price'] );
	}

	/**
	 * Test POST with staff_id zero saves "No Preference".
	 *
	 * @covers Bookit_Staff_API::select_staff
	 */
	public function test_post_with_staff_id_zero_saves_no_preference() {
		$service_id = $this->create_test_service( array( 'price' => 50.00 ) );
		$staff_id   = $this->create_test_staff();
		$this->link_staff_to_service( $staff_id, $service_id, 45.00 );

		Bookit_Session_Manager::init();
		Bookit_Session_Manager::set_data( array(
			'service_id'   => $service_id,
			'current_step' => 2,
		) );

		$request = new WP_REST_Request( 'POST', '/' . $this->namespace . $this->route );
		$request->set_param( 'staff_id', 0 );
		$request->set_header( 'X-WP-Nonce', wp_create_nonce( 'wp_rest' ) );

		$response = rest_get_server()->dispatch( $request );

		$this->assertEquals( 200, $response->get_status() );
		$data = $response->get_data();
		$this->assertTrue( $data['success'] );
		$this->assertEquals( 0, $data['staff']['id'] );
		$this->assertEquals( 'No Preference', $data['staff']['name'] );
		$this->assertEquals( 45.00, (float) $data['staff']['price'] );

		$wizard_data = Bookit_Session_Manager::get_data();
		$this->assertEquals( 0, (int) $wizard_data['staff_id'] );
		$this->assertEquals( 'No Preference', $wizard_data['staff_name'] );
	}

	/**
	 * Test POST rejects staff not offering selected service.
	 *
	 * @covers Bookit_Staff_API::select_staff
	 */
	public function test_post_rejects_staff_not_offering_service() {
		$service_a = $this->create_test_service( array( 'name' => 'Service A' ) );
		$service_b = $this->create_test_service( array( 'name' => 'Service B' ) );
		$staff_id  = $this->create_test_staff();
		$this->link_staff_to_service( $staff_id, $service_a );
		// Staff does NOT offer service_b.

		Bookit_Session_Manager::init();
		Bookit_Session_Manager::set_data( array(
			'service_id'   => $service_b,
			'current_step' => 2,
		) );

		$request = new WP_REST_Request( 'POST', '/' . $this->namespace . $this->route );
		$request->set_param( 'staff_id', $staff_id );
		$request->set_header( 'X-WP-Nonce', wp_create_nonce( 'wp_rest' ) );

		$response = rest_get_server()->dispatch( $request );

		$this->assertTrue( $response->is_error() );
		$this->assertEquals( 400, $response->get_status() );
		$error = $response->as_error();
		$this->assertEquals( 'staff_not_available', $error->get_error_code() );
	}

	/**
	 * Test POST rejects inactive staff.
	 *
	 * @covers Bookit_Staff_API::select_staff
	 */
	public function test_post_rejects_inactive_staff() {
		$service_id = $this->create_test_service();
		$staff_id   = $this->create_test_staff( array( 'is_active' => 0 ) );
		$this->link_staff_to_service( $staff_id, $service_id );

		Bookit_Session_Manager::init();
		Bookit_Session_Manager::set_data( array(
			'service_id'   => $service_id,
			'current_step' => 2,
		) );

		$request = new WP_REST_Request( 'POST', '/' . $this->namespace . $this->route );
		$request->set_param( 'staff_id', $staff_id );
		$request->set_header( 'X-WP-Nonce', wp_create_nonce( 'wp_rest' ) );

		$response = rest_get_server()->dispatch( $request );

		$this->assertTrue( $response->is_error() );
		$this->assertEquals( 404, $response->get_status() );
		$error = $response->as_error();
		$this->assertEquals( 'invalid_staff', $error->get_error_code() );
	}

	/**
	 * Test POST rejects missing staff_id.
	 *
	 * @covers Bookit_Staff_API::select_staff
	 */
	public function test_post_rejects_missing_staff_id() {
		$service_id = $this->create_test_service();
		Bookit_Session_Manager::init();
		Bookit_Session_Manager::set_data( array(
			'service_id'   => $service_id,
			'current_step' => 2,
		) );

		$request = new WP_REST_Request( 'POST', '/' . $this->namespace . $this->route );
		// Do not set staff_id.
		$request->set_header( 'X-WP-Nonce', wp_create_nonce( 'wp_rest' ) );

		$response = rest_get_server()->dispatch( $request );

		$this->assertTrue( $response->is_error() || $response->get_status() === 400 );
		$this->assertEquals( 400, $response->get_status() );
	}

	/**
	 * Test POST rejects invalid (non-existent) staff_id.
	 *
	 * @covers Bookit_Staff_API::select_staff
	 */
	public function test_post_rejects_invalid_staff_id() {
		$service_id = $this->create_test_service();
		Bookit_Session_Manager::init();
		Bookit_Session_Manager::set_data( array(
			'service_id'   => $service_id,
			'current_step' => 2,
		) );

		$request = new WP_REST_Request( 'POST', '/' . $this->namespace . $this->route );
		$request->set_param( 'staff_id', 99999 );
		$request->set_header( 'X-WP-Nonce', wp_create_nonce( 'wp_rest' ) );

		$response = rest_get_server()->dispatch( $request );

		$this->assertTrue( $response->is_error() );
		$this->assertEquals( 404, $response->get_status() );
		$error = $response->as_error();
		$this->assertEquals( 'invalid_staff', $error->get_error_code() );
	}

	/**
	 * Test session advances to step 3 after successful selection.
	 *
	 * @covers Bookit_Staff_API::select_staff
	 */
	public function test_session_advances_to_step_three_after_selection() {
		$service_id = $this->create_test_service();
		$staff_id   = $this->create_test_staff();
		$this->link_staff_to_service( $staff_id, $service_id );

		Bookit_Session_Manager::init();
		Bookit_Session_Manager::set_data( array(
			'service_id'   => $service_id,
			'current_step' => 2,
		) );

		$request = new WP_REST_Request( 'POST', '/' . $this->namespace . $this->route );
		$request->set_param( 'staff_id', $staff_id );
		$request->set_header( 'X-WP-Nonce', wp_create_nonce( 'wp_rest' ) );

		rest_get_server()->dispatch( $request );

		$wizard_data = Bookit_Session_Manager::get_data();
		$this->assertEquals( 3, (int) $wizard_data['current_step'] );
	}

	/**
	 * Test error responses have correct HTTP status codes.
	 *
	 * @covers Bookit_Staff_API::select_staff
	 */
	public function test_error_responses_have_correct_status_codes() {
		// No service in session -> 400.
		Bookit_Session_Manager::init();
		Bookit_Session_Manager::clear();

		$request = new WP_REST_Request( 'POST', '/' . $this->namespace . $this->route );
		$request->set_param( 'staff_id', 1 );
		$request->set_header( 'X-WP-Nonce', wp_create_nonce( 'wp_rest' ) );

		$response = rest_get_server()->dispatch( $request );
		$this->assertTrue( $response->is_error() );
		$this->assertEquals( 400, $response->get_status() );

		// Invalid nonce -> 403.
		$service_id = $this->create_test_service();
		Bookit_Session_Manager::set_data( array( 'service_id' => $service_id, 'current_step' => 2 ) );
		$request2 = new WP_REST_Request( 'POST', '/' . $this->namespace . $this->route );
		$request2->set_param( 'staff_id', 0 );
		$request2->set_header( 'X-WP-Nonce', 'invalid-nonce' );

		$response2 = rest_get_server()->dispatch( $request2 );
		$this->assertTrue( $response2->is_error() );
		$this->assertEquals( 403, $response2->get_status() );
	}

	/**
	 * Test error responses have descriptive messages.
	 *
	 * @covers Bookit_Staff_API::select_staff
	 */
	public function test_error_responses_have_descriptive_messages() {
		Bookit_Session_Manager::init();
		Bookit_Session_Manager::clear();

		$request = new WP_REST_Request( 'POST', '/' . $this->namespace . $this->route );
		$request->set_param( 'staff_id', 1 );
		$request->set_header( 'X-WP-Nonce', wp_create_nonce( 'wp_rest' ) );

		$response = rest_get_server()->dispatch( $request );
		$error    = $response->as_error();
		$this->assertEquals( 'no_service', $error->get_error_code() );
		$this->assertNotEmpty( $error->get_error_message() );
		$this->assertStringContainsString( 'service', strtolower( $error->get_error_message() ) );

		$service_a  = $this->create_test_service();
		$service_b  = $this->create_test_service( array( 'name' => 'Other Service' ) );
		$staff_id   = $this->create_test_staff();
		$this->link_staff_to_service( $staff_id, $service_a );
		Bookit_Session_Manager::set_data( array( 'service_id' => $service_b, 'current_step' => 2 ) );

		$request2 = new WP_REST_Request( 'POST', '/' . $this->namespace . $this->route );
		$request2->set_param( 'staff_id', $staff_id );
		$request2->set_header( 'X-WP-Nonce', wp_create_nonce( 'wp_rest' ) );
		$response2 = rest_get_server()->dispatch( $request2 );
		$error2    = $response2->as_error();
		$this->assertEquals( 'staff_not_available', $error2->get_error_code() );
		$this->assertNotEmpty( $error2->get_error_message() );
	}

	/**
	 * Test staff select endpoint is registered.
	 *
	 * @covers Bookit_Staff_API::register_routes
	 */
	public function test_staff_select_endpoint_registered() {
		$routes = rest_get_server()->get_routes();
		$this->assertArrayHasKey( '/' . $this->namespace . $this->route, $routes );
	}

	// ========== HELPER METHODS ==========

	/**
	 * Create test staff member.
	 *
	 * @param array $args Override defaults.
	 * @return int Staff ID.
	 */
	private function create_test_staff( $args = array() ) {
		global $wpdb;

		$defaults = array(
			'email'             => 'test-' . wp_generate_password( 6, false ) . '@example.com',
			'password_hash'     => wp_hash_password( 'password123' ),
			'first_name'        => 'Test',
			'last_name'         => 'Staff',
			'phone'             => '07700900000',
			'photo_url'         => null,
			'bio'               => 'Test bio',
			'title'             => 'Senior Therapist',
			'role'              => 'staff',
			'google_calendar_id' => null,
			'is_active'         => 1,
			'display_order'     => 0,
			'created_at'        => current_time( 'mysql' ),
			'updated_at'        => current_time( 'mysql' ),
			'deleted_at'        => null,
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
			'buffer_after'    => 0,
			'is_active'      => 1,
			'display_order'  => 0,
			'created_at'      => current_time( 'mysql' ),
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
	 * Link staff to service with optional custom price.
	 *
	 * @param int        $staff_id     Staff ID.
	 * @param int        $service_id   Service ID.
	 * @param float|null $custom_price Custom price or null to use service.price.
	 */
	private function link_staff_to_service( $staff_id, $service_id, $custom_price = null ) {
		global $wpdb;

		$data   = array(
			'staff_id'     => $staff_id,
			'service_id'   => $service_id,
			'custom_price' => $custom_price,
			'created_at'   => current_time( 'mysql' ),
		);
		$format = array( '%d', '%d', $custom_price === null ? '%s' : '%f', '%s' );

		$wpdb->insert(
			$wpdb->prefix . 'bookings_staff_services',
			$data,
			$format
		);
	}
}
