<?php
/**
 * Tests for audit log API endpoint.
 *
 * @package    Bookit_Booking_System
 * @subpackage Tests
 */

/**
 * Test Bookit_Audit_Log_API endpoints.
 */
class Test_Bookit_Audit_Log_API extends WP_UnitTestCase {

	/**
	 * REST namespace.
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

		$wpdb->query( "TRUNCATE TABLE {$wpdb->prefix}bookings_audit_log" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
		$wpdb->query( "TRUNCATE TABLE {$wpdb->prefix}bookings_staff" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery

		require_once BOOKIT_PLUGIN_DIR . 'includes/config/error-codes.php';
		$_SESSION = array();
		do_action( 'rest_api_init' );
	}

	/**
	 * Tear down each test.
	 */
	public function tearDown(): void {
		global $wpdb;

		$wpdb->query( "TRUNCATE TABLE {$wpdb->prefix}bookings_audit_log" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
		$wpdb->query( "TRUNCATE TABLE {$wpdb->prefix}bookings_staff" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
		$_SESSION = array();

		parent::tearDown();
	}

	/**
	 * @covers Bookit_Audit_Log_API::check_admin_permission
	 */
	public function test_get_audit_log_requires_admin() {
		$staff_id = $this->create_test_staff( array( 'role' => 'staff' ) );
		$this->login_as( $staff_id, 'staff' );

		$request  = new WP_REST_Request( 'GET', '/' . $this->namespace . '/audit-log' );
		$response = rest_get_server()->dispatch( $request );

		$this->assertTrue( $response->is_error() );
		$this->assertSame( 403, $response->get_status() );
	}

	/**
	 * @covers Bookit_Audit_Log_API::get_audit_log
	 */
	public function test_get_audit_log_accessible_by_admin() {
		global $wpdb;

		$admin_id = $this->create_test_staff( array( 'role' => 'admin' ) );
		$this->login_as( $admin_id, 'admin' );

		$this->insert_audit_row(
			array(
				'action'     => 'booking.created',
				'created_at' => current_time( 'mysql' ),
			)
		);

		$request  = new WP_REST_Request( 'GET', '/' . $this->namespace . '/audit-log' );
		$response = rest_get_server()->dispatch( $request );
		$data     = $response->get_data();

		$this->assertSame( 200, $response->get_status() );
		$this->assertArrayHasKey( 'data', $data );
		$this->assertArrayHasKey( 'pagination', $data );
	}

	/**
	 * @covers Bookit_Audit_Log_API::get_audit_log
	 */
	public function test_get_audit_log_date_filter() {
		$admin_id = $this->create_test_staff( array( 'role' => 'admin' ) );
		$this->login_as( $admin_id, 'admin' );

		$this->insert_audit_row(
			array(
				'action'     => 'booking.created',
				'created_at' => current_time( 'mysql' ),
			)
		);

		$this->insert_audit_row(
			array(
				'action'     => 'booking.created',
				'created_at' => '2020-01-01 10:00:00',
			)
		);

		$request = new WP_REST_Request( 'GET', '/' . $this->namespace . '/audit-log' );
		$request->set_param( 'date_from', '2020-01-01' );
		$request->set_param( 'date_to', '2020-01-01' );

		$response = rest_get_server()->dispatch( $request );
		$payload  = $response->get_data();
		$data     = $payload['data'];

		$this->assertSame( 200, $response->get_status() );
		$this->assertNotEmpty( $data );
		foreach ( $data as $row ) {
			$this->assertStringStartsWith( '2020-01-01', $row['created_at'] );
		}
	}

	/**
	 * @covers Bookit_Audit_Log_API::get_audit_log
	 */
	public function test_get_audit_log_action_filter() {
		$admin_id = $this->create_test_staff( array( 'role' => 'admin' ) );
		$this->login_as( $admin_id, 'admin' );

		$this->insert_audit_row( array( 'action' => 'booking.created' ) );
		$this->insert_audit_row( array( 'action' => 'setting.updated' ) );

		$request = new WP_REST_Request( 'GET', '/' . $this->namespace . '/audit-log' );
		$request->set_param( 'action', 'booking.created' );

		$response = rest_get_server()->dispatch( $request );
		$data     = $response->get_data()['data'];

		$this->assertSame( 200, $response->get_status() );
		$this->assertNotEmpty( $data );
		foreach ( $data as $row ) {
			$this->assertSame( 'booking.created', $row['action'] );
		}
	}

	/**
	 * @covers Bookit_Audit_Log_API::get_audit_log
	 */
	public function test_get_audit_log_pagination() {
		$admin_id = $this->create_test_staff( array( 'role' => 'admin' ) );
		$this->login_as( $admin_id, 'admin' );

		for ( $i = 0; $i < 55; $i++ ) {
			$this->insert_audit_row(
				array(
					'action' => 'booking.created',
					'notes'  => 'Row ' . $i,
				)
			);
		}

		$request = new WP_REST_Request( 'GET', '/' . $this->namespace . '/audit-log' );
		$request->set_param( 'per_page', 50 );
		$request->set_param( 'page', 1 );

		$response   = rest_get_server()->dispatch( $request );
		$payload    = $response->get_data();
		$pagination = $payload['pagination'];

		$this->assertSame( 200, $response->get_status() );
		$this->assertGreaterThanOrEqual( 55, (int) $pagination['total'] );
		$this->assertGreaterThanOrEqual( 2, (int) $pagination['total_pages'] );
		$this->assertCount( 50, $payload['data'] );
	}

	/**
	 * @covers Bookit_Audit_Log_API::get_audit_log
	 */
	public function test_viewing_audit_log_creates_audit_entry() {
		global $wpdb;

		$admin_id = $this->create_test_staff( array( 'role' => 'admin' ) );
		$this->login_as( $admin_id, 'admin' );

		$request  = new WP_REST_Request( 'GET', '/' . $this->namespace . '/audit-log' );
		$response = rest_get_server()->dispatch( $request );

		$count = (int) $wpdb->get_var(
			"SELECT COUNT(*) FROM {$wpdb->prefix}bookings_audit_log WHERE action = 'audit_log.viewed'" // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
		);

		$this->assertSame( 200, $response->get_status() );
		$this->assertGreaterThanOrEqual( 1, $count );
	}

	/**
	 * Simulate Bookit dashboard login via session.
	 *
	 * @param int    $staff_id Staff row ID.
	 * @param string $role     Session role.
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
	 * Create a test staff row.
	 *
	 * @param array $args Override defaults.
	 * @return int
	 */
	private function create_test_staff( $args = array() ): int {
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
	 * Insert audit log row directly.
	 *
	 * @param array $args Override defaults.
	 * @return int
	 */
	private function insert_audit_row( $args = array() ): int {
		global $wpdb;

		$defaults = array(
			'actor_id'    => 1,
			'actor_type'  => 'admin',
			'actor_ip'    => '127.0.0.1',
			'action'      => 'booking.created',
			'object_type' => 'booking',
			'object_id'   => 1,
			'old_value'   => null,
			'new_value'   => wp_json_encode( array( 'status' => 'confirmed' ) ),
			'notes'       => 'seed',
			'created_at'  => current_time( 'mysql' ),
		);

		$data = wp_parse_args( $args, $defaults );

		$wpdb->insert(
			$wpdb->prefix . 'bookings_audit_log',
			$data,
			array( '%d', '%s', '%s', '%s', '%s', '%d', '%s', '%s', '%s', '%s' )
		);

		return (int) $wpdb->insert_id;
	}
}
