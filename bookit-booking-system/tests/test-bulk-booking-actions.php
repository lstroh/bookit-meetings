<?php
/**
 * Tests for bulk booking actions endpoint.
 *
 * @package    Bookit_Booking_System
 * @subpackage Tests
 */

/**
 * Test bulk booking actions.
 */
class Test_Bulk_Booking_Actions extends WP_UnitTestCase {

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

		bookit_test_truncate_tables(
			array(
				'bookings_package_redemptions',
				'bookings_customer_packages',
				'bookings_package_types',
				'bookings',
				'bookings_staff',
				'bookings_services',
				'bookings_customers',
				'bookings_status_log',
				'bookings_audit_log',
			)
		);

		$_SESSION = array();

		do_action( 'rest_api_init' );
	}

	/**
	 * Tear down each test.
	 */
	public function tearDown(): void {
		bookit_test_truncate_tables(
			array(
				'bookings_package_redemptions',
				'bookings_customer_packages',
				'bookings_package_types',
				'bookings',
				'bookings_staff',
				'bookings_services',
				'bookings_customers',
				'bookings_status_log',
				'bookings_audit_log',
			)
		);

		$_SESSION = array();

		parent::tearDown();
	}

	/**
	 * @covers Bookit_Dashboard_Bookings_API::bulk_action
	 */
	public function test_bulk_cancel_success() {
		global $wpdb;

		list( $admin, $booking_ids ) = $this->create_admin_and_bookings( 3, 'confirmed' );
		$this->login_as( $admin, 'admin' );

		$response = $this->dispatch_bulk_request( 'cancel', $booking_ids );
		$data     = $response->get_data();

		$this->assertEquals( 200, $response->get_status() );
		$this->assertSame( $booking_ids, $data['succeeded'] );
		$this->assertCount( 0, $data['failed'] );

		foreach ( $booking_ids as $booking_id ) {
			$status = $wpdb->get_var(
				$wpdb->prepare(
					"SELECT status FROM {$wpdb->prefix}bookings WHERE id = %d",
					$booking_id
				)
			);
			$this->assertEquals( 'cancelled', $status );
		}

		$log_count = (int) $wpdb->get_var(
			"SELECT COUNT(*) FROM {$wpdb->prefix}bookings_audit_log WHERE action = 'booking_bulk_cancelled'"
		);
		$this->assertEquals( 3, $log_count );
	}

	/**
	 * @covers Bookit_Dashboard_Bookings_API::bulk_action
	 */
	public function test_bulk_complete_success() {
		global $wpdb;

		list( $admin, $booking_ids ) = $this->create_admin_and_bookings( 2, 'confirmed' );
		$this->login_as( $admin, 'admin' );

		$response = $this->dispatch_bulk_request( 'complete', $booking_ids );
		$data     = $response->get_data();

		$this->assertEquals( 200, $response->get_status() );
		$this->assertSame( $booking_ids, $data['succeeded'] );
		$this->assertCount( 0, $data['failed'] );

		foreach ( $booking_ids as $booking_id ) {
			$status = $wpdb->get_var(
				$wpdb->prepare(
					"SELECT status FROM {$wpdb->prefix}bookings WHERE id = %d",
					$booking_id
				)
			);
			$this->assertEquals( 'completed', $status );
		}
	}

	/**
	 * @covers Bookit_Dashboard_Bookings_API::bulk_action
	 */
	public function test_bulk_no_show_success() {
		global $wpdb;

		list( $admin, $booking_ids ) = $this->create_admin_and_bookings( 2, 'confirmed' );
		$this->login_as( $admin, 'admin' );

		$response = $this->dispatch_bulk_request( 'no_show', $booking_ids );
		$data     = $response->get_data();

		$this->assertEquals( 200, $response->get_status() );
		$this->assertSame( $booking_ids, $data['succeeded'] );
		$this->assertCount( 0, $data['failed'] );

		foreach ( $booking_ids as $booking_id ) {
			$status = $wpdb->get_var(
				$wpdb->prepare(
					"SELECT status FROM {$wpdb->prefix}bookings WHERE id = %d",
					$booking_id
				)
			);
			$this->assertEquals( 'no_show', $status );
		}
	}

	/**
	 * @covers Bookit_Dashboard_Bookings_API::bulk_action
	 */
	public function test_bulk_invalid_action() {
		list( $admin, $booking_ids ) = $this->create_admin_and_bookings( 1, 'confirmed' );
		$this->login_as( $admin, 'admin' );

		$response = $this->dispatch_bulk_request( 'delete', $booking_ids );
		$this->assertTrue( $response->is_error() );
		$this->assertEquals( 400, $response->get_status() );
		$this->assertEquals( 'BULK_INVALID_ACTION', $response->as_error()->get_error_code() );
	}

	/**
	 * @covers Bookit_Dashboard_Bookings_API::bulk_action
	 */
	public function test_bulk_empty_ids() {
		$admin = $this->create_test_staff( array( 'role' => 'admin' ) );
		$this->login_as( $admin, 'admin' );

		$response = $this->dispatch_bulk_request( 'cancel', array() );
		$this->assertTrue( $response->is_error() );
		$this->assertEquals( 400, $response->get_status() );
		$this->assertEquals( 'BULK_EMPTY_IDS', $response->as_error()->get_error_code() );
	}

	/**
	 * @covers Bookit_Dashboard_Bookings_API::bulk_action
	 */
	public function test_bulk_too_many_ids() {
		$admin = $this->create_test_staff( array( 'role' => 'admin' ) );
		$this->login_as( $admin, 'admin' );

		$booking_ids = range( 1, 101 );
		$response    = $this->dispatch_bulk_request( 'cancel', $booking_ids );

		$this->assertTrue( $response->is_error() );
		$this->assertEquals( 400, $response->get_status() );
		$this->assertEquals( 'BULK_TOO_MANY_IDS', $response->as_error()->get_error_code() );
	}

	/**
	 * @covers Bookit_Dashboard_Bookings_API::bulk_action
	 */
	public function test_bulk_mixed_success_failure() {
		list( $admin, $booking_ids ) = $this->create_admin_and_bookings( 2, 'confirmed' );
		$cancelled_id                = $this->create_booking_with_status( 'cancelled' );
		$all_ids                     = array( $booking_ids[0], $cancelled_id, $booking_ids[1] );
		$this->login_as( $admin, 'admin' );

		$response = $this->dispatch_bulk_request( 'cancel', $all_ids );
		$data     = $response->get_data();

		$this->assertEquals( 200, $response->get_status() );
		$this->assertCount( 2, $data['succeeded'] );
		$this->assertCount( 1, $data['failed'] );
		$this->assertEquals( $cancelled_id, $data['failed'][0]['id'] );
		$this->assertNotEmpty( $data['failed'][0]['reason'] );
	}

	/**
	 * @covers Bookit_Dashboard_Bookings_API::check_admin_permission
	 */
	public function test_bulk_staff_permission_denied() {
		list( , $booking_ids ) = $this->create_admin_and_bookings( 1, 'confirmed' );
		$staff = $this->create_test_staff( array( 'role' => 'staff' ) );
		$this->login_as( $staff, 'staff' );

		$response = $this->dispatch_bulk_request( 'cancel', $booking_ids );
		$this->assertTrue( $response->is_error() );
		$this->assertEquals( 403, $response->get_status() );
	}

	/**
	 * @covers Bookit_Dashboard_Bookings_API::check_admin_permission
	 */
	public function test_bulk_unauthenticated_denied() {
		list( , $booking_ids ) = $this->create_admin_and_bookings( 1, 'confirmed' );
		$_SESSION = array();

		$response = $this->dispatch_bulk_request( 'cancel', $booking_ids );
		$this->assertTrue( $response->is_error() );
		$this->assertContains( $response->get_status(), array( 401, 403 ) );
	}

	/**
	 * @covers Bookit_Dashboard_Bookings_API::bulk_action
	 */
	public function test_bulk_audit_log_per_booking() {
		global $wpdb;

		list( $admin, $booking_ids ) = $this->create_admin_and_bookings( 3, 'confirmed' );
		$this->login_as( $admin, 'admin' );

		$response = $this->dispatch_bulk_request( 'cancel', $booking_ids );
		$this->assertEquals( 200, $response->get_status() );

		$rows = $wpdb->get_results(
			"SELECT object_id FROM {$wpdb->prefix}bookings_audit_log WHERE action = 'booking_bulk_cancelled'",
			ARRAY_A
		);

		$this->assertCount( 3, $rows );
		$object_ids = array_map( 'intval', array_column( $rows, 'object_id' ) );
		sort( $object_ids );
		$expected = $booking_ids;
		sort( $expected );
		$this->assertSame( $expected, $object_ids );
	}

	/**
	 * Create test admin and bookings.
	 *
	 * @param int    $count  Number of bookings.
	 * @param string $status Initial status.
	 * @return array
	 */
	private function create_admin_and_bookings( $count, $status ) {
		$admin      = $this->create_test_staff( array( 'role' => 'admin' ) );
		$service    = $this->create_test_service();
		$staff      = $this->create_test_staff( array( 'role' => 'staff' ) );
		$customer   = $this->create_test_customer();
		$booking_ids = array();

		for ( $i = 0; $i < $count; $i++ ) {
			$start_hour = 9 + $i;
			$end_hour   = 10 + $i;
			$booking_ids[] = $this->create_test_booking(
				array(
					'staff_id'    => $staff,
					'service_id'  => $service,
					'customer_id' => $customer,
					'status'      => $status,
					'start_time'  => sprintf( '%02d:00:00', $start_hour ),
					'end_time'    => sprintf( '%02d:00:00', $end_hour ),
				)
			);
		}

		return array( $admin, $booking_ids );
	}

	/**
	 * Create booking with specific status.
	 *
	 * @param string $status Booking status.
	 * @return int
	 */
	private function create_booking_with_status( $status ) {
		$service  = $this->create_test_service();
		$staff    = $this->create_test_staff( array( 'role' => 'staff' ) );
		$customer = $this->create_test_customer();

		return $this->create_test_booking(
			array(
				'staff_id'    => $staff,
				'service_id'  => $service,
				'customer_id' => $customer,
				'status'      => $status,
			)
		);
	}

	/**
	 * Dispatch bulk action request.
	 *
	 * @param string $action     Bulk action.
	 * @param array  $booking_ids Booking IDs.
	 * @return WP_REST_Response
	 */
	private function dispatch_bulk_request( $action, $booking_ids ) {
		$request = new WP_REST_Request( 'POST', '/' . $this->namespace . '/dashboard/bookings/bulk-action' );
		$request->set_body_params(
			array(
				'action'      => $action,
				'booking_ids' => $booking_ids,
				'_wpnonce'    => wp_create_nonce( 'wp_rest' ),
			)
		);

		return rest_get_server()->dispatch( $request );
	}

	/**
	 * Simulate Bookit dashboard login via session.
	 *
	 * @param int    $staff_id Staff row ID.
	 * @param string $role     Staff role.
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
	 * Create test staff.
	 *
	 * @param array $args Optional overrides.
	 * @return int
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
	 * @param array $args Optional overrides.
	 * @return int
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
	 * @param array $args Optional overrides.
	 * @return int
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
	 * Create test booking.
	 *
	 * @param array $args Optional overrides.
	 * @return int
	 */
	private function create_test_booking( $args = array() ) {
		global $wpdb;

		$defaults = array(
			'customer_id'      => 0,
			'service_id'       => 0,
			'staff_id'         => 0,
			'booking_date'     => '2026-06-15',
			'start_time'       => '10:00:00',
			'end_time'         => '11:00:00',
			'duration'         => 60,
			'status'           => 'confirmed',
			'total_price'      => 50.00,
			'deposit_paid'     => 0.00,
			'balance_due'      => 50.00,
			'full_amount_paid' => 0,
			'payment_method'   => 'cash',
			'created_at'       => current_time( 'mysql' ),
			'updated_at'       => current_time( 'mysql' ),
			'deleted_at'       => null,
		);

		$data = wp_parse_args( $args, $defaults );
		$wpdb->insert( $wpdb->prefix . 'bookings', $data );
		return (int) $wpdb->insert_id;
	}
}
