<?php
/**
 * Tests for Service Selection REST API
 *
 * @package    Bookit_Booking_System
 * @subpackage Tests
 */

/**
 * Test Bookit_Service_API REST endpoints.
 */
class Test_Service_API extends WP_UnitTestCase {

	/**
	 * REST API namespace.
	 *
	 * @var string
	 */
	private $namespace = 'bookit/v1';

	/**
	 * Service selection route.
	 *
	 * @var string
	 */
	private $route = '/service/select';

	/**
	 * Set up each test.
	 */
	public function setUp(): void {
		parent::setUp();

		global $wpdb;

		// Clear tables
		$wpdb->query( "TRUNCATE TABLE {$wpdb->prefix}bookings_services" );
		$wpdb->query( "TRUNCATE TABLE {$wpdb->prefix}bookings_categories" );
		$wpdb->query( "TRUNCATE TABLE {$wpdb->prefix}bookings_service_categories" );
		$wpdb->query( "TRUNCATE TABLE {$wpdb->prefix}bookings_staff" );
		$wpdb->query( "TRUNCATE TABLE {$wpdb->prefix}bookings_staff_services" );

		// Clear session
		Bookit_Session_Manager::clear();
		if ( session_status() === PHP_SESSION_ACTIVE ) {
			session_destroy();
		}
		if ( isset( $_SESSION ) ) {
			$_SESSION = array();
		}

		// Register REST routes
		do_action( 'rest_api_init' );
	}

	/**
	 * Tear down each test.
	 */
	public function tearDown(): void {
		global $wpdb;

		// Clean up
		$wpdb->query( "TRUNCATE TABLE {$wpdb->prefix}bookings_services" );
		$wpdb->query( "TRUNCATE TABLE {$wpdb->prefix}bookings_categories" );
		$wpdb->query( "TRUNCATE TABLE {$wpdb->prefix}bookings_service_categories" );
		$wpdb->query( "TRUNCATE TABLE {$wpdb->prefix}bookings_staff" );
		$wpdb->query( "TRUNCATE TABLE {$wpdb->prefix}bookings_staff_services" );

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
	 * Test service selection endpoint is registered
	 *
	 * @covers Bookit_Service_API::register_routes
	 */
	public function test_service_select_endpoint_registered() {
		$routes = rest_get_server()->get_routes();
		$this->assertArrayHasKey( '/' . $this->namespace . $this->route, $routes );
	}

	/**
	 * Test endpoint requires service_id parameter
	 *
	 * @covers Bookit_Service_API::select_service
	 */
	public function test_service_select_requires_service_id() {
		$request = new WP_REST_Request( 'POST', '/' . $this->namespace . $this->route );
		// Don't set service_id

		// Set valid nonce for this test
		$nonce = wp_create_nonce( 'wp_rest' );
		$request->set_header( 'X-WP-Nonce', $nonce );

		$response = rest_get_server()->dispatch( $request );

		$this->assertEquals( 400, $response->get_status() );
	}

	/**
	 * Test endpoint validates service_id is numeric
	 *
	 * @covers Bookit_Service_API::select_service
	 */
	public function test_service_select_validates_numeric_id() {
		$request = new WP_REST_Request( 'POST', '/' . $this->namespace . $this->route );
		$request->set_param( 'service_id', 'not-a-number' );

		// Set valid nonce
		$nonce = wp_create_nonce( 'wp_rest' );
		$request->set_header( 'X-WP-Nonce', $nonce );

		$response = rest_get_server()->dispatch( $request );

		// REST API validation should catch this before our callback
		$this->assertTrue( $response->is_error() || $response->get_status() === 400 );
	}

	/**
	 * Test endpoint returns 404 for invalid service
	 *
	 * @covers Bookit_Service_API::select_service
	 */
	public function test_service_select_returns_404_for_invalid_service() {
		$request = new WP_REST_Request( 'POST', '/' . $this->namespace . $this->route );
		$request->set_param( 'service_id', 99999 );

		// Set valid nonce
		$nonce = wp_create_nonce( 'wp_rest' );
		$request->set_header( 'X-WP-Nonce', $nonce );

		$response = rest_get_server()->dispatch( $request );

		$this->assertEquals( 404, $response->get_status() );
		$this->assertTrue( $response->is_error() );
	}

	/**
	 * Test endpoint returns 404 for inactive service
	 *
	 * @covers Bookit_Service_API::select_service
	 */
	public function test_service_select_returns_404_for_inactive_service() {
		// Create inactive service
		$service_id = $this->create_service( 'Inactive Service', 30.00, false );

		$request = new WP_REST_Request( 'POST', '/' . $this->namespace . $this->route );
		$request->set_param( 'service_id', $service_id );

		// Set valid nonce
		$nonce = wp_create_nonce( 'wp_rest' );
		$request->set_header( 'X-WP-Nonce', $nonce );

		$response = rest_get_server()->dispatch( $request );

		$this->assertEquals( 404, $response->get_status() );
		$this->assertTrue( $response->is_error() );
	}

	/**
	 * Test successful service selection saves to session
	 *
	 * @covers Bookit_Service_API::select_service
	 */
	public function test_service_select_saves_to_session() {
		// Create active service
		$service_id = $this->create_service( 'Test Service', 35.00 );

		// Initialize session
		Bookit_Session_Manager::init();

		$request = new WP_REST_Request( 'POST', '/' . $this->namespace . $this->route );
		$request->set_param( 'service_id', $service_id );

		// Set valid nonce
		$nonce = wp_create_nonce( 'wp_rest' );
		$request->set_header( 'X-WP-Nonce', $nonce );

		$response = rest_get_server()->dispatch( $request );

		// Check session
		$wizard_data = Bookit_Session_Manager::get_data();
		$this->assertEquals( $service_id, $wizard_data['service_id'] );
		$this->assertEquals( 'Test Service', $wizard_data['service_name'] );
		$this->assertEquals( 35.00, $wizard_data['service_price'] );
	}

	/**
	 * Test service selection advances to step 2
	 *
	 * @covers Bookit_Service_API::select_service
	 */
	public function test_service_select_advances_to_step_2() {
		$service_id = $this->create_service( 'Test Service', 35.00 );

		Bookit_Session_Manager::init();

		$request = new WP_REST_Request( 'POST', '/' . $this->namespace . $this->route );
		$request->set_param( 'service_id', $service_id );

		// Set valid nonce
		$nonce = wp_create_nonce( 'wp_rest' );
		$request->set_header( 'X-WP-Nonce', $nonce );

		rest_get_server()->dispatch( $request );

		$wizard_data = Bookit_Session_Manager::get_data();
		$this->assertEquals( 2, $wizard_data['current_step'] );
	}

	/**
	 * Test successful response structure
	 *
	 * @covers Bookit_Service_API::select_service
	 */
	public function test_service_select_returns_success_response() {
		$service_id = $this->create_service( 'Test Service', 35.00 );

		$request = new WP_REST_Request( 'POST', '/' . $this->namespace . $this->route );
		$request->set_param( 'service_id', $service_id );

		// Set valid nonce
		$nonce = wp_create_nonce( 'wp_rest' );
		$request->set_header( 'X-WP-Nonce', $nonce );

		$response = rest_get_server()->dispatch( $request );
		$data     = $response->get_data();

		$this->assertEquals( 200, $response->get_status() );
		$this->assertTrue( $data['success'] );
		$this->assertArrayHasKey( 'service', $data );
		$this->assertEquals( 2, $data['next_step'] );
		$this->assertEquals( $service_id, $data['service']['id'] );
		$this->assertEquals( 'Test Service', $data['service']['name'] );
	}

	/**
	 * Test service selection requires valid nonce
	 *
	 * @covers Bookit_Service_API::select_service
	 */
	public function test_service_select_requires_valid_nonce() {
		$service_id = $this->create_service( 'Test Service', 35.00 );

		$request = new WP_REST_Request( 'POST', '/' . $this->namespace . $this->route );
		$request->set_param( 'service_id', $service_id );

		// Set invalid nonce
		$request->set_header( 'X-WP-Nonce', 'invalid-nonce' );

		$response = rest_get_server()->dispatch( $request );

		$this->assertTrue( $response->is_error() );
		$this->assertEquals( 403, $response->get_status() );
	}

	// ========== HELPER METHODS ==========

	/**
	 * Create a test service
	 *
	 * @param string $name Service name.
	 * @param float  $price Base price.
	 * @param bool   $is_active Whether service is active.
	 * @return int Service ID.
	 */
	private function create_service( $name, $price, $is_active = true ) {
		global $wpdb;

		$wpdb->insert(
			$wpdb->prefix . 'bookings_services',
			array(
				'name'         => $name,
				'description' => "Test service",
				'duration'     => 45,
				'price'        => $price,
				'buffer_before' => 0,
				'buffer_after'  => 0,
				'is_active'    => $is_active ? 1 : 0,
				'deleted_at'   => null,
			),
			array( '%s', '%s', '%d', '%f', '%d', '%d', '%d', '%s' )
		);

		return $wpdb->insert_id;
	}
}
