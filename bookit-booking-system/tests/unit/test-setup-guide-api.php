<?php
/**
 * Tests for Setup Guide API.
 *
 * @package    Bookit_Booking_System
 * @subpackage Tests
 */

/**
 * Test Setup Guide API endpoints.
 */
class Test_Setup_Guide_API extends WP_UnitTestCase {

	/**
	 * REST API namespace.
	 *
	 * @var string
	 */
	private $namespace = 'bookit/v1';

	/**
	 * Setup Guide user meta key.
	 *
	 * @var string
	 */
	private $meta_key = 'bookit_setup_guide_status';

	/**
	 * Created staff IDs for cleanup.
	 *
	 * @var int[]
	 */
	private $created_staff_ids = array();

	/**
	 * Set up each test.
	 */
	public function setUp(): void {
		parent::setUp();

		global $wpdb;

		$wpdb->query( "TRUNCATE TABLE {$wpdb->prefix}bookings_staff" );
		$wpdb->query( "TRUNCATE TABLE {$wpdb->prefix}bookings_audit_log" );

		$_SESSION = array();

		do_action( 'rest_api_init' );
	}

	/**
	 * Tear down each test.
	 */
	public function tearDown(): void {
		global $wpdb;

		foreach ( $this->created_staff_ids as $staff_id ) {
			delete_user_meta( $staff_id, $this->meta_key );
		}
		$this->created_staff_ids = array();

		$wpdb->query( "TRUNCATE TABLE {$wpdb->prefix}bookings_staff" );
		$wpdb->query( "TRUNCATE TABLE {$wpdb->prefix}bookings_audit_log" );

		$_SESSION = array();

		parent::tearDown();
	}

	// ========== TESTS FOR: GET /setup-guide/status ==========

	/**
	 * @covers Bookit_Setup_Guide_API::register_routes
	 */
	public function test_get_status_endpoint_is_registered() {
		$routes = rest_get_server()->get_routes();

		$this->assertArrayHasKey( '/' . $this->namespace . '/setup-guide/status', $routes );
	}

	/**
	 * @covers Bookit_Setup_Guide_API::check_admin_permission
	 */
	public function test_get_status_requires_admin() {
		$staff = $this->create_test_staff( array( 'role' => 'staff' ) );
		$this->login_as( $staff, 'bookit_staff' );

		$request  = new WP_REST_Request( 'GET', '/' . $this->namespace . '/setup-guide/status' );
		$response = rest_get_server()->dispatch( $request );

		$this->assertTrue( $response->is_error() );
		$this->assertEquals( 403, $response->get_status() );
	}

	/**
	 * @covers Bookit_Setup_Guide_API::check_admin_permission
	 */
	public function test_get_status_unauthenticated_returns_401() {
		$_SESSION = array();

		$request  = new WP_REST_Request( 'GET', '/' . $this->namespace . '/setup-guide/status' );
		$response = rest_get_server()->dispatch( $request );

		$this->assertTrue( $response->is_error() );
		$this->assertEquals( 401, $response->get_status() );
	}

	/**
	 * @covers Bookit_Setup_Guide_API::get_status
	 */
	public function test_get_status_returns_default_when_no_meta() {
		$admin = $this->create_test_staff( array( 'role' => 'admin' ) );
		$this->login_as( $admin, 'bookit_admin' );
		delete_user_meta( $admin, $this->meta_key );

		$request  = new WP_REST_Request( 'GET', '/' . $this->namespace . '/setup-guide/status' );
		$response = rest_get_server()->dispatch( $request );
		$data     = $response->get_data();

		$this->assertEquals( 200, $response->get_status() );
		$this->assertTrue( $data['success'] );
		$this->assertEquals( 'pending', $data['status'] );
		$this->assertEquals( 1, $data['current_step'] );
		$this->assertNull( $data['completed_at'] );
		$this->assertNull( $data['dismissed_at'] );
		$this->assertSame( array(), $data['steps_completed'] );
	}

	/**
	 * @covers Bookit_Setup_Guide_API::get_status
	 */
	public function test_get_status_returns_stored_meta() {
		$admin = $this->create_test_staff( array( 'role' => 'admin' ) );
		$this->login_as( $admin, 'bookit_admin' );

		update_user_meta(
			$admin,
			$this->meta_key,
			wp_json_encode(
				array(
					'status'          => 'dismissed',
					'current_step'    => 2,
					'completed_at'    => null,
					'dismissed_at'    => gmdate( 'c' ),
					'steps_completed' => array( 1 ),
				)
			)
		);

		$request  = new WP_REST_Request( 'GET', '/' . $this->namespace . '/setup-guide/status' );
		$response = rest_get_server()->dispatch( $request );
		$data     = $response->get_data();

		$this->assertEquals( 200, $response->get_status() );
		$this->assertEquals( 'dismissed', $data['status'] );
	}

	// ========== TESTS FOR: POST /setup-guide/status (action: complete) ==========

	/**
	 * @covers Bookit_Setup_Guide_API::update_status
	 */
	public function test_post_complete_sets_status_completed() {
		$admin = $this->create_test_staff( array( 'role' => 'admin' ) );
		$this->login_as( $admin, 'bookit_admin' );

		$request = new WP_REST_Request( 'POST', '/' . $this->namespace . '/setup-guide/status' );
		$request->set_body_params( array( 'action' => 'complete' ) );

		$response = rest_get_server()->dispatch( $request );
		$data     = $response->get_data();

		$this->assertEquals( 200, $response->get_status() );
		$this->assertEquals( 'completed', $data['status'] );
		$this->assertNotNull( $data['completed_at'] );
		$this->assertIsString( $data['completed_at'] );
	}

	/**
	 * @covers Bookit_Setup_Guide_API::update_status
	 */
	public function test_post_complete_persists_to_user_meta() {
		$admin = $this->create_test_staff( array( 'role' => 'admin' ) );
		$this->login_as( $admin, 'bookit_admin' );

		$request = new WP_REST_Request( 'POST', '/' . $this->namespace . '/setup-guide/status' );
		$request->set_body_params( array( 'action' => 'complete' ) );
		rest_get_server()->dispatch( $request );

		$stored = get_user_meta( $admin, $this->meta_key, true );
		$decoded = json_decode( (string) $stored, true );

		$this->assertIsArray( $decoded );
		$this->assertEquals( 'completed', $decoded['status'] );
	}

	/**
	 * @covers Bookit_Setup_Guide_API::update_status
	 */
	public function test_post_complete_logs_audit_entry() {
		global $wpdb;

		$admin = $this->create_test_staff( array( 'role' => 'admin' ) );
		$this->login_as( $admin, 'bookit_admin' );

		$request = new WP_REST_Request( 'POST', '/' . $this->namespace . '/setup-guide/status' );
		$request->set_body_params( array( 'action' => 'complete' ) );
		rest_get_server()->dispatch( $request );

		$count = (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$wpdb->prefix}bookings_audit_log WHERE action = %s",
				'setup_guide_completed'
			)
		);

		$this->assertGreaterThanOrEqual( 1, $count );
	}

	// ========== TESTS FOR: POST /setup-guide/status (action: dismiss) ==========

	/**
	 * @covers Bookit_Setup_Guide_API::update_status
	 */
	public function test_post_dismiss_sets_status_dismissed() {
		$admin = $this->create_test_staff( array( 'role' => 'admin' ) );
		$this->login_as( $admin, 'bookit_admin' );

		$request = new WP_REST_Request( 'POST', '/' . $this->namespace . '/setup-guide/status' );
		$request->set_body_params( array( 'action' => 'dismiss' ) );

		$response = rest_get_server()->dispatch( $request );
		$data     = $response->get_data();

		$this->assertEquals( 200, $response->get_status() );
		$this->assertEquals( 'dismissed', $data['status'] );
		$this->assertNotNull( $data['dismissed_at'] );
		$this->assertIsString( $data['dismissed_at'] );
	}

	/**
	 * @covers Bookit_Setup_Guide_API::update_status
	 */
	public function test_post_dismiss_persists_to_user_meta() {
		$admin = $this->create_test_staff( array( 'role' => 'admin' ) );
		$this->login_as( $admin, 'bookit_admin' );

		$request = new WP_REST_Request( 'POST', '/' . $this->namespace . '/setup-guide/status' );
		$request->set_body_params( array( 'action' => 'dismiss' ) );
		rest_get_server()->dispatch( $request );

		$stored = get_user_meta( $admin, $this->meta_key, true );
		$decoded = json_decode( (string) $stored, true );

		$this->assertIsArray( $decoded );
		$this->assertEquals( 'dismissed', $decoded['status'] );
	}

	/**
	 * @covers Bookit_Setup_Guide_API::update_status
	 */
	public function test_post_dismiss_logs_audit_entry() {
		global $wpdb;

		$admin = $this->create_test_staff( array( 'role' => 'admin' ) );
		$this->login_as( $admin, 'bookit_admin' );

		$request = new WP_REST_Request( 'POST', '/' . $this->namespace . '/setup-guide/status' );
		$request->set_body_params( array( 'action' => 'dismiss' ) );
		rest_get_server()->dispatch( $request );

		$count = (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$wpdb->prefix}bookings_audit_log WHERE action = %s",
				'setup_guide_dismissed'
			)
		);

		$this->assertGreaterThanOrEqual( 1, $count );
	}

	// ========== TESTS FOR: POST /setup-guide/status (action: update_step) ==========

	/**
	 * @covers Bookit_Setup_Guide_API::update_status
	 */
	public function test_post_update_step_changes_current_step() {
		$admin = $this->create_test_staff( array( 'role' => 'admin' ) );
		$this->login_as( $admin, 'bookit_admin' );

		$request = new WP_REST_Request( 'POST', '/' . $this->namespace . '/setup-guide/status' );
		$request->set_body_params(
			array(
				'action'       => 'update_step',
				'current_step' => 3,
			)
		);

		$response = rest_get_server()->dispatch( $request );
		$data     = $response->get_data();

		$this->assertEquals( 200, $response->get_status() );
		$this->assertEquals( 3, $data['current_step'] );
	}

	/**
	 * @covers Bookit_Setup_Guide_API::update_status
	 */
	public function test_post_update_step_appends_step_done() {
		$admin = $this->create_test_staff( array( 'role' => 'admin' ) );
		$this->login_as( $admin, 'bookit_admin' );

		$request = new WP_REST_Request( 'POST', '/' . $this->namespace . '/setup-guide/status' );
		$request->set_body_params(
			array(
				'action'    => 'update_step',
				'step_done' => 2,
			)
		);

		$response = rest_get_server()->dispatch( $request );
		$data     = $response->get_data();

		$this->assertEquals( 200, $response->get_status() );
		$this->assertContains( 2, $data['steps_completed'] );
	}

	/**
	 * @covers Bookit_Setup_Guide_API::update_status
	 */
	public function test_post_update_step_does_not_duplicate_steps() {
		$admin = $this->create_test_staff( array( 'role' => 'admin' ) );
		$this->login_as( $admin, 'bookit_admin' );

		$first_request = new WP_REST_Request( 'POST', '/' . $this->namespace . '/setup-guide/status' );
		$first_request->set_body_params(
			array(
				'action'    => 'update_step',
				'step_done' => 1,
			)
		);
		rest_get_server()->dispatch( $first_request );

		$second_request = new WP_REST_Request( 'POST', '/' . $this->namespace . '/setup-guide/status' );
		$second_request->set_body_params(
			array(
				'action'    => 'update_step',
				'step_done' => 1,
			)
		);
		$response = rest_get_server()->dispatch( $second_request );
		$data     = $response->get_data();

		$ones = array_values(
			array_filter(
				$data['steps_completed'],
				function ( $step ) {
					return 1 === (int) $step;
				}
			)
		);

		$this->assertEquals( 1, count( $ones ) );
	}

	/**
	 * @covers Bookit_Setup_Guide_API::update_status
	 */
	public function test_post_update_step_does_not_audit_log() {
		global $wpdb;

		$admin = $this->create_test_staff( array( 'role' => 'admin' ) );
		$this->login_as( $admin, 'bookit_admin' );

		$request = new WP_REST_Request( 'POST', '/' . $this->namespace . '/setup-guide/status' );
		$request->set_body_params(
			array(
				'action'       => 'update_step',
				'current_step' => 2,
				'step_done'    => 2,
			)
		);
		rest_get_server()->dispatch( $request );

		$count = (int) $wpdb->get_var(
			"SELECT COUNT(*) FROM {$wpdb->prefix}bookings_audit_log WHERE action LIKE 'setup_guide_%'"
		);

		$this->assertEquals( 0, $count );
	}

	// ========== TESTS FOR: POST validation errors ==========

	/**
	 * @covers Bookit_Setup_Guide_API::update_status
	 */
	public function test_post_unknown_action_returns_400() {
		$admin = $this->create_test_staff( array( 'role' => 'admin' ) );
		$this->login_as( $admin, 'bookit_admin' );

		$request = new WP_REST_Request( 'POST', '/' . $this->namespace . '/setup-guide/status' );
		$request->set_body_params( array( 'action' => 'invalid_xyz' ) );

		$response = rest_get_server()->dispatch( $request );
		$error    = $response->as_error();

		$this->assertEquals( 400, $response->get_status() );
		$this->assertEquals( 'E4010', $error->get_error_code() );
	}

	/**
	 * @covers Bookit_Setup_Guide_API::update_status
	 */
	public function test_post_invalid_step_zero_returns_400() {
		$admin = $this->create_test_staff( array( 'role' => 'admin' ) );
		$this->login_as( $admin, 'bookit_admin' );

		$request = new WP_REST_Request( 'POST', '/' . $this->namespace . '/setup-guide/status' );
		$request->set_body_params(
			array(
				'action'       => 'update_step',
				'current_step' => 0,
			)
		);

		$response = rest_get_server()->dispatch( $request );
		$error    = $response->as_error();

		$this->assertEquals( 400, $response->get_status() );
		$this->assertEquals( 'E4011', $error->get_error_code() );
	}

	/**
	 * @covers Bookit_Setup_Guide_API::update_status
	 */
	public function test_post_invalid_step_five_returns_400() {
		$admin = $this->create_test_staff( array( 'role' => 'admin' ) );
		$this->login_as( $admin, 'bookit_admin' );

		$request = new WP_REST_Request( 'POST', '/' . $this->namespace . '/setup-guide/status' );
		$request->set_body_params(
			array(
				'action'       => 'update_step',
				'current_step' => 5,
			)
		);

		$response = rest_get_server()->dispatch( $request );
		$error    = $response->as_error();

		$this->assertEquals( 400, $response->get_status() );
		$this->assertEquals( 'E4011', $error->get_error_code() );
	}

	/**
	 * @covers Bookit_Setup_Guide_API::update_status
	 */
	public function test_post_valid_boundary_steps() {
		$admin = $this->create_test_staff( array( 'role' => 'admin' ) );
		$this->login_as( $admin, 'bookit_admin' );

		$request_one = new WP_REST_Request( 'POST', '/' . $this->namespace . '/setup-guide/status' );
		$request_one->set_body_params(
			array(
				'action'       => 'update_step',
				'current_step' => 1,
			)
		);
		$response_one = rest_get_server()->dispatch( $request_one );

		$request_four = new WP_REST_Request( 'POST', '/' . $this->namespace . '/setup-guide/status' );
		$request_four->set_body_params(
			array(
				'action'       => 'update_step',
				'current_step' => 4,
			)
		);
		$response_four = rest_get_server()->dispatch( $request_four );

		$this->assertEquals( 200, $response_one->get_status() );
		$this->assertEquals( 200, $response_four->get_status() );
	}

	// ========== TESTS FOR: POST /setup-guide/status permission ==========

	/**
	 * @covers Bookit_Setup_Guide_API::check_admin_permission
	 */
	public function test_post_requires_admin() {
		$staff = $this->create_test_staff( array( 'role' => 'staff' ) );
		$this->login_as( $staff, 'bookit_staff' );

		$request = new WP_REST_Request( 'POST', '/' . $this->namespace . '/setup-guide/status' );
		$request->set_body_params( array( 'action' => 'complete' ) );

		$response = rest_get_server()->dispatch( $request );

		$this->assertTrue( $response->is_error() );
		$this->assertEquals( 403, $response->get_status() );
	}

	// ========== TESTS FOR: state isolation ==========

	/**
	 * @covers Bookit_Setup_Guide_API::update_status
	 * @covers Bookit_Setup_Guide_API::get_status
	 */
	public function test_two_admins_have_independent_status() {
		$admin_a = $this->create_test_staff( array( 'role' => 'admin', 'email' => 'admin-a@test.com' ) );
		$admin_b = $this->create_test_staff( array( 'role' => 'admin', 'email' => 'admin-b@test.com' ) );

		$this->login_as( $admin_a, 'bookit_admin' );
		$complete_request = new WP_REST_Request( 'POST', '/' . $this->namespace . '/setup-guide/status' );
		$complete_request->set_body_params( array( 'action' => 'complete' ) );
		rest_get_server()->dispatch( $complete_request );

		$this->login_as( $admin_b, 'bookit_admin' );
		$get_request  = new WP_REST_Request( 'GET', '/' . $this->namespace . '/setup-guide/status' );
		$get_response = rest_get_server()->dispatch( $get_request );
		$data         = $get_response->get_data();

		$this->assertEquals( 200, $get_response->get_status() );
		$this->assertEquals( 'pending', $data['status'] );
		$this->assertEquals( 1, $data['current_step'] );
	}

	// ========== HELPER METHODS ==========

	/**
	 * Simulate Bookit dashboard login via session.
	 *
	 * @param int    $staff_id Staff row ID.
	 * @param string $role     Role in active session.
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

		$staff_id = (int) $wpdb->insert_id;
		$this->created_staff_ids[] = $staff_id;

		return $staff_id;
	}
}
