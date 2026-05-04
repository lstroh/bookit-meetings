<?php
/**
 * Tests for email queue dashboard REST API.
 *
 * @package    Bookit_Booking_System
 * @subpackage Tests
 */

/**
 * Test Bookit_Email_Queue_API endpoints.
 */
class Test_Bookit_Email_Queue_API extends WP_UnitTestCase {

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

		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.DirectDatabaseQuery.DirectQuery
		$wpdb->query( 'DELETE FROM ' . $wpdb->prefix . 'bookit_email_queue' );

		require_once BOOKIT_PLUGIN_DIR . 'includes/config/error-codes.php';
		$_SESSION = array();

		do_action( 'rest_api_init' );
	}

	/**
	 * Tear down each test.
	 */
	public function tearDown(): void {
		global $wpdb;

		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.DirectDatabaseQuery.DirectQuery
		$wpdb->query( 'DELETE FROM ' . $wpdb->prefix . 'bookit_email_queue' );
		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.DirectDatabaseQuery.DirectQuery
		$wpdb->query( 'DELETE FROM ' . $wpdb->prefix . 'bookings_staff' );

		$_SESSION = array();

		parent::tearDown();
	}

	/**
	 * @covers Bookit_Email_Queue_API::check_dashboard_permission
	 */
	public function test_email_queue_endpoint_requires_auth() {
		$request  = new WP_REST_Request( 'GET', '/' . $this->namespace . '/dashboard/email-queue' );
		$response = rest_get_server()->dispatch( $request );

		$this->assertTrue( $response->is_error() );
		$this->assertSame( 401, $response->get_status() );
	}

	/**
	 * @covers Bookit_Email_Queue_API::get_email_queue
	 */
	public function test_email_queue_returns_paginated_results() {
		$this->insert_queue_row( array( 'recipient_email' => 'a@example.com' ) );
		$this->insert_queue_row( array( 'recipient_email' => 'b@example.com' ) );
		$this->insert_queue_row( array( 'recipient_email' => 'c@example.com' ) );

		$admin_id = $this->create_test_staff( array( 'role' => 'admin' ) );
		$this->login_as( $admin_id, 'admin' );

		$request = new WP_REST_Request( 'GET', '/' . $this->namespace . '/dashboard/email-queue' );
		$request->set_param( 'page', 1 );
		$request->set_param( 'per_page', 2 );

		$response = rest_get_server()->dispatch( $request );
		$data     = $response->get_data();

		$this->assertSame( 200, $response->get_status() );
		$this->assertArrayHasKey( 'items', $data );
		$this->assertArrayHasKey( 'total', $data );
		$this->assertArrayHasKey( 'pages', $data );
		$this->assertCount( 2, $data['items'] );
		$this->assertSame( 3, (int) $data['total'] );
		$this->assertSame( 2, (int) $data['pages'] );
	}

	/**
	 * @covers Bookit_Email_Queue_API::get_email_queue
	 */
	public function test_email_queue_filters_by_status() {
		$this->insert_queue_row( array( 'recipient_email' => 'p1@example.com' ) );
		$this->insert_queue_row( array( 'recipient_email' => 'p2@example.com' ) );
		$sent_id = $this->insert_queue_row( array( 'recipient_email' => 's@example.com' ) );

		Bookit_Email_Queue::update_status( $sent_id, 'sent' );

		$admin_id = $this->create_test_staff( array( 'role' => 'admin' ) );
		$this->login_as( $admin_id, 'admin' );

		$request = new WP_REST_Request( 'GET', '/' . $this->namespace . '/dashboard/email-queue' );
		$request->set_param( 'status', 'pending' );

		$response = rest_get_server()->dispatch( $request );
		$data     = $response->get_data();

		$this->assertSame( 200, $response->get_status() );
		$this->assertCount( 2, $data['items'] );
		$emails = wp_list_pluck( $data['items'], 'recipient_email' );
		$this->assertContains( 'p1@example.com', $emails );
		$this->assertContains( 'p2@example.com', $emails );
		$this->assertNotContains( 's@example.com', $emails );
	}

	/**
	 * @covers Bookit_Email_Queue_API::get_email_queue
	 */
	public function test_email_queue_staff_role_is_blocked() {
		$staff_id = $this->create_test_staff( array( 'role' => 'bookit_staff' ) );
		$this->login_as( $staff_id, 'bookit_staff' );

		$request  = new WP_REST_Request( 'GET', '/' . $this->namespace . '/dashboard/email-queue' );
		$response = rest_get_server()->dispatch( $request );

		$this->assertTrue( $response->is_error() );
		$this->assertSame( 403, $response->get_status() );
	}

	/**
	 * @covers Bookit_Email_Queue_API::get_email_queue
	 */
	public function test_email_queue_returns_correct_fields() {
		$this->insert_queue_row(
			array(
				'email_type'      => 'customer_confirmation',
				'recipient_email' => 'fields@example.com',
				'html_body'       => '<p>secret</p>',
				'params'          => '{"x":1}',
			)
		);

		$admin_id = $this->create_test_staff( array( 'role' => 'admin' ) );
		$this->login_as( $admin_id, 'admin' );

		$request  = new WP_REST_Request( 'GET', '/' . $this->namespace . '/dashboard/email-queue' );
		$response = rest_get_server()->dispatch( $request );
		$data     = $response->get_data();

		$this->assertSame( 200, $response->get_status() );
		$this->assertNotEmpty( $data['items'] );
		$row = $data['items'][0];

		$this->assertArrayHasKey( 'email_type', $row );
		$this->assertArrayHasKey( 'recipient_email', $row );
		$this->assertArrayHasKey( 'status', $row );
		$this->assertArrayHasKey( 'attempts', $row );
		$this->assertArrayNotHasKey( 'html_body', $row );
		$this->assertArrayNotHasKey( 'params', $row );
		$this->assertArrayNotHasKey( 'subject', $row );
	}

	/**
	 * @covers Bookit_Email_Queue_API::get_email_queue
	 */
	public function test_email_queue_rejects_invalid_status_filter() {
		$admin_id = $this->create_test_staff( array( 'role' => 'admin' ) );
		$this->login_as( $admin_id, 'admin' );

		$request = new WP_REST_Request( 'GET', '/' . $this->namespace . '/dashboard/email-queue' );
		$request->set_param( 'status', 'invalid_value' );

		$response = rest_get_server()->dispatch( $request );

		$this->assertTrue( $response->is_error() );
		$this->assertSame( 400, $response->get_status() );
	}

	/**
	 * Insert a queue row; optional overrides.
	 *
	 * @param array $overrides Overrides for Bookit_Email_Queue::insert.
	 * @return int Row ID.
	 */
	private function insert_queue_row( array $overrides = array() ): int {
		$base = array(
			'email_type'      => 'customer_confirmation',
			'recipient_email' => 'test@example.com',
			'html_body'       => '<p>Hi</p>',
			'scheduled_at'    => gmdate( 'Y-m-d H:i:s', time() - 60 ),
		);

		$data = array_merge( $base, $overrides );

		return (int) Bookit_Email_Queue::insert( $data );
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
}
