<?php
/**
 * Tests for Wizard REST API
 *
 * @package    Bookit_Booking_System
 * @subpackage Tests
 */

/**
 * Test Bookit_Wizard_API REST endpoints.
 */
class Test_Wizard_API extends WP_UnitTestCase {

	/**
	 * REST API namespace.
	 *
	 * @var string
	 */
	private $namespace = 'bookit/v1';

	/**
	 * Session route.
	 *
	 * @var string
	 */
	private $route = '/wizard/session';

	/**
	 * Set up each test.
	 */
	public function setUp(): void {
		parent::setUp();
		Bookit_Session_Manager::clear();
		$ip = Bookit_Rate_Limiter::get_client_ip();
		delete_transient( Bookit_Rate_Limiter::KEY_PREFIX . 'wizard_book_' . md5( $ip ) );
		do_action( 'rest_api_init' );
	}

	/**
	 * Tear down each test.
	 */
	public function tearDown(): void {
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
	 * Test that session endpoint is registered.
	 *
	 * @covers Bookit_Wizard_API::register_routes
	 */
	public function test_session_endpoint_registered() {
		$routes = rest_get_server()->get_routes();
		$key    = '/' . $this->namespace . $this->route;
		$this->assertArrayHasKey( $key, $routes );
	}

	/**
	 * Test that GET session returns 200.
	 *
	 * @covers Bookit_Wizard_API::get_session
	 */
	public function test_get_session_returns_200() {
		$request  = new WP_REST_Request( 'GET', '/' . $this->namespace . $this->route );
		$response = rest_get_server()->dispatch( $request );
		$this->assertEquals( 200, $response->get_status() );
	}

	/**
	 * Test that GET session returns valid JSON structure.
	 *
	 * @covers Bookit_Wizard_API::get_session
	 */
	public function test_get_session_returns_json() {
		$request  = new WP_REST_Request( 'GET', '/' . $this->namespace . $this->route );
		$response = rest_get_server()->dispatch( $request );
		$data     = $response->get_data();
		$this->assertIsArray( $data );
		$this->assertArrayHasKey( 'success', $data );
		$this->assertArrayHasKey( 'data', $data );
		$this->assertTrue( $data['success'] );
	}

	/**
	 * Test that GET session response has current_step in data.
	 *
	 * @covers Bookit_Wizard_API::get_session
	 */
	public function test_get_session_has_current_step() {
		$request  = new WP_REST_Request( 'GET', '/' . $this->namespace . $this->route );
		$response = rest_get_server()->dispatch( $request );
		$data     = $response->get_data();
		$this->assertArrayHasKey( 'data', $data );
		$this->assertArrayHasKey( 'current_step', $data['data'] );
		$this->assertArrayHasKey( 'time_remaining', $data['data'] );
	}

	/**
	 * Test that POST session updates data with valid nonce.
	 *
	 * @covers Bookit_Wizard_API::update_session
	 * @covers Bookit_Wizard_API::check_permission
	 */
	public function test_post_session_updates_data() {
		$request = new WP_REST_Request( 'POST', '/' . $this->namespace . $this->route );
		$request->set_header( 'X-WP-Nonce', wp_create_nonce( 'wp_rest' ) );
		$request->set_body_params( array( 'current_step' => 2 ) );

		$response = rest_get_server()->dispatch( $request );
		$this->assertEquals( 200, $response->get_status() );

		$data = $response->get_data();
		$this->assertTrue( $data['success'] );
		$this->assertEquals( 2, (int) $data['data']['current_step'] );

		$this->assertEquals( 2, (int) Bookit_Session_Manager::get( 'current_step' ) );
	}

	/**
	 * Test that POST session fails without valid nonce.
	 *
	 * @covers Bookit_Wizard_API::check_permission
	 */
	public function test_post_session_requires_nonce() {
		$request = new WP_REST_Request( 'POST', '/' . $this->namespace . $this->route );
		$request->set_body_params( array( 'current_step' => 2 ) );
		// No X-WP-Nonce header.

		$response = rest_get_server()->dispatch( $request );
		$this->assertEquals( 403, $response->get_status() );
	}

	/**
	 * Test that POST session rejects invalid nonce.
	 *
	 * @covers Bookit_Wizard_API::check_permission
	 */
	public function test_post_session_rejects_invalid_nonce() {
		$request = new WP_REST_Request( 'POST', '/' . $this->namespace . $this->route );
		$request->set_header( 'X-WP-Nonce', 'invalid-nonce' );
		$request->set_body_params( array( 'current_step' => 2 ) );

		$response = rest_get_server()->dispatch( $request );
		$this->assertEquals( 403, $response->get_status() );
	}

	/**
	 * Test that POST validates step number (1-5).
	 *
	 * @covers Bookit_Wizard_API::validate_step
	 */
	public function test_post_session_validates_step_number() {
		$request = new WP_REST_Request( 'POST', '/' . $this->namespace . $this->route );
		$request->set_header( 'X-WP-Nonce', wp_create_nonce( 'wp_rest' ) );
		$request->set_body_params( array( 'current_step' => 0 ) );

		$response = rest_get_server()->dispatch( $request );
		$this->assertEquals( 400, $response->get_status() );

		$request->set_body_params( array( 'current_step' => 6 ) );
		$response = rest_get_server()->dispatch( $request );
		$this->assertEquals( 400, $response->get_status() );
	}

	/**
	 * Test that POST sanitizes input (XSS).
	 *
	 * @covers Bookit_Wizard_API::update_session
	 */
	public function test_post_session_sanitizes_input() {
		$request = new WP_REST_Request( 'POST', '/' . $this->namespace . $this->route );
		$request->set_header( 'X-WP-Nonce', wp_create_nonce( 'wp_rest' ) );
		$request->set_body_params(
			array(
				'date' => '<script>alert("xss")</script>2026-01-15',
				'time' => '10:00',
			)
		);

		$response = rest_get_server()->dispatch( $request );
		$this->assertEquals( 200, $response->get_status() );

		$stored = Bookit_Session_Manager::get( 'date' );
		$this->assertStringNotContainsString( '<script>', $stored );
	}

	/**
	 * Test that GET endpoint is public (no auth required).
	 *
	 * @covers Bookit_Wizard_API::register_routes
	 * @covers Bookit_Wizard_API::get_session
	 */
	public function test_endpoint_is_public() {
		$request  = new WP_REST_Request( 'GET', '/' . $this->namespace . $this->route );
		$response = rest_get_server()->dispatch( $request );
		$this->assertEquals( 200, $response->get_status() );
	}

	/**
	 * Test that OPTIONS request succeeds (CORS preflight).
	 *
	 * @covers Bookit_Wizard_API::register_routes
	 */
	public function test_endpoint_allows_cors() {
		$request  = new WP_REST_Request( 'OPTIONS', '/' . $this->namespace . $this->route );
		$response = rest_get_server()->dispatch( $request );
		$this->assertContains( $response->get_status(), array( 200, 204 ), 'OPTIONS should return 200 or 204 for CORS' );
	}

	/**
	 * Test that POST with valid step 1-5 succeeds.
	 *
	 * @covers Bookit_Wizard_API::validate_step
	 * @covers Bookit_Wizard_API::update_session
	 */
	public function test_post_session_accepts_valid_steps() {
		foreach ( array( 1, 2, 3, 4, 5 ) as $step ) {
			$request = new WP_REST_Request( 'POST', '/' . $this->namespace . $this->route );
			$request->set_header( 'X-WP-Nonce', wp_create_nonce( 'wp_rest' ) );
			$request->set_body_params( array( 'current_step' => $step ) );

			$response = rest_get_server()->dispatch( $request );
			$this->assertEquals( 200, $response->get_status(), "Step $step should be accepted" );
			$data = $response->get_data();
			$this->assertEquals( $step, (int) $data['data']['current_step'] );
		}
	}

	/**
	 * Test that POST updates service_id, staff_id, date, time.
	 *
	 * @covers Bookit_Wizard_API::update_session
	 */
	public function test_post_session_updates_booking_fields() {
		$request = new WP_REST_Request( 'POST', '/' . $this->namespace . $this->route );
		$request->set_header( 'X-WP-Nonce', wp_create_nonce( 'wp_rest' ) );
		$request->set_body_params(
			array(
				'current_step' => 2,
				'service_id'   => 7,
				'staff_id'     => 3,
				'date'         => '2026-02-01',
				'time'         => '14:30',
			)
		);

		$response = rest_get_server()->dispatch( $request );
		$this->assertEquals( 200, $response->get_status() );

		$this->assertEquals( 7, (int) Bookit_Session_Manager::get( 'service_id' ) );
		$this->assertEquals( 3, (int) Bookit_Session_Manager::get( 'staff_id' ) );
		$this->assertEquals( '2026-02-01', Bookit_Session_Manager::get( 'date' ) );
		$this->assertEquals( '14:30', Bookit_Session_Manager::get( 'time' ) );
	}

	/**
	 * Test that POST merges customer data and sanitizes.
	 *
	 * @covers Bookit_Wizard_API::update_session
	 * @covers Bookit_Wizard_API::sanitize_customer
	 */
	public function test_post_session_sanitizes_customer() {
		$request = new WP_REST_Request( 'POST', '/' . $this->namespace . $this->route );
		$request->set_header( 'X-WP-Nonce', wp_create_nonce( 'wp_rest' ) );
		$request->set_body_params(
			array(
				'customer' => array(
					'name'  => 'Test User',
					'email' => 'test@example.com',
					'phone' => '01234567890',
					'notes' => 'Some notes',
				),
			)
		);

		$response = rest_get_server()->dispatch( $request );
		$this->assertEquals( 200, $response->get_status() );

		$customer = Bookit_Session_Manager::get( 'customer', array() );
		$this->assertIsArray( $customer );
		$this->assertEquals( 'Test User', $customer['name'] );
		$this->assertEquals( 'test@example.com', $customer['email'] );
		$this->assertEquals( '01234567890', $customer['phone'] );
		$this->assertEquals( 'Some notes', $customer['notes'] );
	}

	/**
	 * @covers Bookit_Wizard_API::register_routes
	 */
	public function test_complete_booking_endpoint_registered() {
		$routes = rest_get_server()->get_routes();
		$key    = '/' . $this->namespace . '/wizard/complete';
		$this->assertArrayHasKey( $key, $routes );
	}

	/**
	 * @covers Bookit_Wizard_API::complete_booking
	 */
	public function test_complete_booking_returns_400_on_empty_session() {
		require_once BOOKIT_PLUGIN_DIR . 'includes/core/class-session-manager.php';
		Bookit_Session_Manager::init();
		$_SESSION['bookit_wizard'] = array();

		$request = new WP_REST_Request( 'POST', '/' . $this->namespace . '/wizard/complete' );
		$request->set_header( 'X-WP-Nonce', wp_create_nonce( 'wp_rest' ) );

		$response = rest_get_server()->dispatch( $request );
		$this->assertEquals( 400, $response->get_status() );
		$data = $response->get_data();
		$this->assertArrayHasKey( 'code', $data );
		$this->assertSame( 'invalid_session', $data['code'] );
	}

	/**
	 * @covers Bookit_Wizard_API::complete_booking
	 */
	public function test_complete_booking_pay_on_arrival_returns_redirect_url() {
		global $wpdb;

		add_filter( 'bookit_send_email', '__return_false' );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
		$wpdb->insert(
			$wpdb->prefix . 'bookings_services',
			array(
				'name'           => 'Wizard API POA Test',
				'duration'       => 60,
				'price'          => 50.00,
				'deposit_type'   => 'fixed',
				'deposit_amount' => 10.00,
				'is_active'      => 1,
				'created_at'     => current_time( 'mysql' ),
				'updated_at'     => current_time( 'mysql' ),
			),
			array( '%s', '%d', '%f', '%s', '%f', '%d', '%s', '%s' )
		);
		$service_id = (int) $wpdb->insert_id;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
		$wpdb->insert(
			$wpdb->prefix . 'bookings_staff',
			array(
				'first_name'    => 'Wizard',
				'last_name'     => 'Tester',
				'email'         => 'wizard-api-poa@example.com',
				'password_hash' => wp_hash_password( 'x' ),
				'is_active'     => 1,
				'created_at'    => current_time( 'mysql' ),
				'updated_at'    => current_time( 'mysql' ),
			),
			array( '%s', '%s', '%s', '%s', '%d', '%s', '%s' )
		);
		$staff_id = (int) $wpdb->insert_id;

		$booking_date = wp_date( 'Y-m-d', strtotime( '+30 days' ), wp_timezone() );

		require_once BOOKIT_PLUGIN_DIR . 'includes/core/class-session-manager.php';
		Bookit_Session_Manager::clear();
		Bookit_Session_Manager::set_data(
			array(
				'current_step'              => 5,
				'service_id'                => $service_id,
				'staff_id'                  => $staff_id,
				'date'                      => $booking_date,
				'time'                      => '10:00',
				'customer_first_name'       => 'Jane',
				'customer_last_name'        => 'Doe',
				'customer_email'            => 'jane-wizard-complete@example.com',
				'customer_phone'            => '07700900456',
				'customer_special_requests' => '',
				'cooling_off_waiver'        => 1,
				'payment_method'            => 'pay_on_arrival',
				'wizard_version'            => 'v2',
			)
		);

		$request = new WP_REST_Request( 'POST', '/' . $this->namespace . '/wizard/complete' );
		$request->set_header( 'X-WP-Nonce', wp_create_nonce( 'wp_rest' ) );

		$response = rest_get_server()->dispatch( $request );
		remove_filter( 'bookit_send_email', '__return_false' );

		$this->assertEquals( 200, $response->get_status() );
		$data = $response->get_data();
		$this->assertTrue( $data['success'] );
		$this->assertArrayHasKey( 'redirect_url', $data );
		$this->assertStringContainsString( 'booking_id=', $data['redirect_url'] );
		$this->assertNotEmpty( $data['booking_id'] );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
		$wpdb->delete( $wpdb->prefix . 'bookings', array( 'service_id' => $service_id ), array( '%d' ) );
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
		$wpdb->delete( $wpdb->prefix . 'bookings_customers', array( 'email' => 'jane-wizard-complete@example.com' ), array( '%s' ) );
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
		$wpdb->delete( $wpdb->prefix . 'bookings_services', array( 'id' => $service_id ), array( '%d' ) );
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
		$wpdb->delete( $wpdb->prefix . 'bookings_staff', array( 'id' => $staff_id ), array( '%d' ) );
	}

	/**
	 * @covers Bookit_Wizard_API::complete_booking
	 */
	public function test_complete_booking_card_returns_400() {
		require_once BOOKIT_PLUGIN_DIR . 'includes/core/class-session-manager.php';
		Bookit_Session_Manager::clear();
		Bookit_Session_Manager::set_data(
			array(
				'current_step'   => 5,
				'payment_method' => 'card',
				'service_id'     => 1,
			)
		);

		$request = new WP_REST_Request( 'POST', '/' . $this->namespace . '/wizard/complete' );
		$request->set_header( 'X-WP-Nonce', wp_create_nonce( 'wp_rest' ) );

		$response = rest_get_server()->dispatch( $request );
		$this->assertEquals( 400, $response->get_status() );
		$data = $response->get_data();
		$this->assertSame( 'payment_method_not_available', $data['code'] );
	}

	/**
	 * @covers Bookit_Wizard_API::complete_booking
	 */
	public function test_complete_booking_invalid_method_returns_400() {
		require_once BOOKIT_PLUGIN_DIR . 'includes/core/class-session-manager.php';
		Bookit_Session_Manager::clear();
		Bookit_Session_Manager::set_data(
			array(
				'current_step'   => 5,
				'payment_method' => 'unknown',
				'service_id'     => 1,
			)
		);

		$request = new WP_REST_Request( 'POST', '/' . $this->namespace . '/wizard/complete' );
		$request->set_header( 'X-WP-Nonce', wp_create_nonce( 'wp_rest' ) );

		$response = rest_get_server()->dispatch( $request );
		$this->assertEquals( 400, $response->get_status() );
		$data = $response->get_data();
		$this->assertSame( 'invalid_payment_method', $data['code'] );
	}
}
