<?php
/**
 * Tests for Bulk Working Hours API (Sprint 3, Task 11.5)
 *
 * Tests bulk operations for staff working hours including:
 * - Conflict detection before applying changes
 * - Bulk exception addition (day off, special hours)
 * - Bulk schedule updates (working hours, breaks)
 * - Overwrite handling for existing exceptions
 * - Validation and error handling
 *
 * @package    Bookit_Booking_System
 * @subpackage Tests
 */

/**
 * Test Bulk Working Hours API endpoints.
 */
class Test_Bulk_Working_Hours_API extends WP_UnitTestCase {

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

		$this->ensure_staff_working_hours_table();

		$wpdb->query( "TRUNCATE TABLE {$wpdb->prefix}bookings_staff" );
		$wpdb->query( "DELETE FROM {$wpdb->prefix}bookings_staff_working_hours" );

		$_SESSION = array();

		do_action( 'rest_api_init' );
	}

	/**
	 * Tear down each test.
	 */
	public function tearDown(): void {
		global $wpdb;

		$wpdb->query( "TRUNCATE TABLE {$wpdb->prefix}bookings_staff" );
		$wpdb->query( "DELETE FROM {$wpdb->prefix}bookings_staff_working_hours" );

		$_SESSION = array();

		parent::tearDown();
	}

	// ========== TESTS FOR: POST /dashboard/staff/bulk-hours/check-conflicts ==========

	/**
	 * Test conflict detection finds existing exception.
	 *
	 * @covers Bookit_Dashboard_Bookings_API::check_bulk_conflicts
	 */
	public function test_conflict_detection_finds_existing_exception() {
		$staff_a = $this->create_test_staff( array( 'first_name' => 'Alice' ) );
		$staff_b = $this->create_test_staff( array( 'first_name' => 'Bob' ) );

		$this->add_exception( $staff_a, '2026-12-25', '00:00:00', '00:00:00', 0 );

		$admin = $this->create_test_staff( array( 'role' => 'admin', 'first_name' => 'Admin' ) );
		$this->login_as( $admin, 'admin' );

		$request = new WP_REST_Request( 'POST', '/' . $this->namespace . '/dashboard/staff/bulk-hours/check-conflicts' );
		$request->set_body_params( array(
			'staff_ids'     => array( $staff_a, $staff_b ),
			'specific_date' => '2026-12-25',
		) );

		$response = rest_get_server()->dispatch( $request );

		$this->assertEquals( 200, $response->get_status() );

		$data = $response->get_data();
		$this->assertTrue( $data['success'] );
		$this->assertCount( 1, $data['conflicts'] );
		$this->assertEquals( (int) $staff_a, $data['conflicts'][0]['staff_id'] );
		$this->assertEquals( '2026-12-25', $data['conflicts'][0]['specific_date'] );
		$this->assertEquals( 'exception', $data['conflicts'][0]['conflict_type'] );
	}

	/**
	 * Test no conflicts when staff have no existing exceptions.
	 *
	 * @covers Bookit_Dashboard_Bookings_API::check_bulk_conflicts
	 */
	public function test_no_conflicts_when_date_is_clear() {
		$staff_a = $this->create_test_staff();
		$staff_b = $this->create_test_staff();

		$admin = $this->create_test_staff( array( 'role' => 'admin' ) );
		$this->login_as( $admin, 'admin' );

		$request = new WP_REST_Request( 'POST', '/' . $this->namespace . '/dashboard/staff/bulk-hours/check-conflicts' );
		$request->set_body_params( array(
			'staff_ids'     => array( $staff_a, $staff_b ),
			'specific_date' => '2026-12-25',
		) );

		$response = rest_get_server()->dispatch( $request );

		$data = $response->get_data();
		$this->assertTrue( $data['success'] );
		$this->assertEmpty( $data['conflicts'] );
	}

	/**
	 * Test conflict detection returns full exception details.
	 *
	 * @covers Bookit_Dashboard_Bookings_API::check_bulk_conflicts
	 */
	public function test_conflict_includes_existing_exception_details() {
		$staff = $this->create_test_staff( array( 'first_name' => 'Alice', 'last_name' => 'Smith' ) );

		$this->add_exception( $staff, '2026-12-24', '10:00:00', '14:00:00', 1 );

		$admin = $this->create_test_staff( array( 'role' => 'admin' ) );
		$this->login_as( $admin, 'admin' );

		$request = new WP_REST_Request( 'POST', '/' . $this->namespace . '/dashboard/staff/bulk-hours/check-conflicts' );
		$request->set_body_params( array(
			'staff_ids'     => array( $staff ),
			'specific_date' => '2026-12-24',
		) );

		$response = rest_get_server()->dispatch( $request );

		$data     = $response->get_data();
		$conflict = $data['conflicts'][0];

		$this->assertEquals( '10:00:00', $conflict['start_time'] );
		$this->assertEquals( '14:00:00', $conflict['end_time'] );
		$this->assertTrue( $conflict['is_working'] );
		$this->assertEquals( 'Alice Smith', $conflict['staff_name'] );
		$this->assertArrayHasKey( 'exception_id', $conflict );
	}

	/**
	 * Test empty staff_ids returns empty conflicts.
	 *
	 * @covers Bookit_Dashboard_Bookings_API::check_bulk_conflicts
	 */
	public function test_check_conflicts_with_empty_staff_ids() {
		$admin = $this->create_test_staff( array( 'role' => 'admin' ) );
		$this->login_as( $admin, 'admin' );

		$request = new WP_REST_Request( 'POST', '/' . $this->namespace . '/dashboard/staff/bulk-hours/check-conflicts' );
		$request->set_body_params( array(
			'staff_ids'     => array(),
			'specific_date' => '2026-12-25',
		) );

		$response = rest_get_server()->dispatch( $request );

		$data = $response->get_data();
		$this->assertTrue( $data['success'] );
		$this->assertEmpty( $data['conflicts'] );
	}

	// ========== TESTS FOR: POST /dashboard/staff/bulk-hours/add-exception ==========

	/**
	 * Test bulk add day off exception to multiple staff.
	 *
	 * @covers Bookit_Dashboard_Bookings_API::bulk_add_exception
	 */
	public function test_bulk_add_day_off_exception() {
		$staff_a = $this->create_test_staff( array( 'first_name' => 'Alice' ) );
		$staff_b = $this->create_test_staff( array( 'first_name' => 'Bob' ) );
		$staff_c = $this->create_test_staff( array( 'first_name' => 'Charlie' ) );

		$admin = $this->create_test_staff( array( 'role' => 'admin' ) );
		$this->login_as( $admin, 'admin' );

		$request = new WP_REST_Request( 'POST', '/' . $this->namespace . '/dashboard/staff/bulk-hours/add-exception' );
		$request->set_body_params( array(
			'staff_ids'     => array( $staff_a, $staff_b, $staff_c ),
			'specific_date' => '2026-12-25',
			'is_working'    => false,
		) );

		$response = rest_get_server()->dispatch( $request );

		$this->assertEquals( 200, $response->get_status() );

		$data = $response->get_data();
		$this->assertTrue( $data['success'] );
		$this->assertEquals( 3, $data['added'] );
		$this->assertEquals( 0, $data['skipped'] );

		global $wpdb;
		$count = $wpdb->get_var( $wpdb->prepare(
			"SELECT COUNT(*) FROM {$wpdb->prefix}bookings_staff_working_hours
			WHERE specific_date = %s AND is_working = 0",
			'2026-12-25'
		) );
		$this->assertEquals( 3, (int) $count );
	}

	/**
	 * Test bulk add special hours exception with breaks.
	 *
	 * @covers Bookit_Dashboard_Bookings_API::bulk_add_exception
	 */
	public function test_bulk_add_special_hours_exception() {
		$staff_a = $this->create_test_staff();
		$staff_b = $this->create_test_staff();

		$admin = $this->create_test_staff( array( 'role' => 'admin' ) );
		$this->login_as( $admin, 'admin' );

		$request = new WP_REST_Request( 'POST', '/' . $this->namespace . '/dashboard/staff/bulk-hours/add-exception' );
		$request->set_body_params( array(
			'staff_ids'     => array( $staff_a, $staff_b ),
			'specific_date' => '2026-12-24',
			'is_working'    => true,
			'start_time'    => '10:00:00',
			'end_time'      => '14:00:00',
			'break_start'   => '12:00:00',
			'break_end'     => '12:30:00',
		) );

		$response = rest_get_server()->dispatch( $request );

		$this->assertEquals( 200, $response->get_status() );
		$this->assertEquals( 2, $response->get_data()['added'] );

		global $wpdb;
		$row = $wpdb->get_row( $wpdb->prepare(
			"SELECT start_time, end_time, break_start, break_end, is_working
			FROM {$wpdb->prefix}bookings_staff_working_hours
			WHERE staff_id = %d AND specific_date = %s",
			$staff_a,
			'2026-12-24'
		) );
		$this->assertEquals( '10:00:00', $row->start_time );
		$this->assertEquals( '14:00:00', $row->end_time );
		$this->assertEquals( '12:00:00', $row->break_start );
		$this->assertEquals( '12:30:00', $row->break_end );
		$this->assertEquals( 1, (int) $row->is_working );
	}

	/**
	 * Test bulk add skips conflicts without overwrite.
	 *
	 * @covers Bookit_Dashboard_Bookings_API::bulk_add_exception
	 */
	public function test_bulk_add_skips_conflicts_without_overwrite() {
		$staff_a = $this->create_test_staff( array( 'first_name' => 'Alice' ) );
		$staff_b = $this->create_test_staff( array( 'first_name' => 'Bob' ) );

		$this->add_exception( $staff_a, '2026-12-25', '00:00:00', '00:00:00', 0 );

		$admin = $this->create_test_staff( array( 'role' => 'admin' ) );
		$this->login_as( $admin, 'admin' );

		$request = new WP_REST_Request( 'POST', '/' . $this->namespace . '/dashboard/staff/bulk-hours/add-exception' );
		$request->set_body_params( array(
			'staff_ids'            => array( $staff_a, $staff_b ),
			'specific_date'        => '2026-12-25',
			'is_working'           => false,
			'overwrite_conflicts'  => array(),
		) );

		$response = rest_get_server()->dispatch( $request );

		$data = $response->get_data();
		$this->assertTrue( $data['success'] );
		$this->assertEquals( 1, $data['added'] );
		$this->assertEquals( 1, $data['skipped'] );
	}

	/**
	 * Test bulk add overwrites when specified via overwrite_conflicts.
	 *
	 * @covers Bookit_Dashboard_Bookings_API::bulk_add_exception
	 */
	public function test_bulk_add_overwrites_when_specified() {
		$staff_a = $this->create_test_staff();
		$staff_b = $this->create_test_staff();

		$this->add_exception( $staff_a, '2026-12-25', '09:00:00', '17:00:00', 1 );
		$this->add_exception( $staff_b, '2026-12-25', '10:00:00', '18:00:00', 1 );

		$admin = $this->create_test_staff( array( 'role' => 'admin' ) );
		$this->login_as( $admin, 'admin' );

		$request = new WP_REST_Request( 'POST', '/' . $this->namespace . '/dashboard/staff/bulk-hours/add-exception' );
		$request->set_body_params( array(
			'staff_ids'           => array( $staff_a, $staff_b ),
			'specific_date'       => '2026-12-25',
			'is_working'          => false,
			'overwrite_conflicts' => array( $staff_a ),
		) );

		$response = rest_get_server()->dispatch( $request );

		$data = $response->get_data();
		$this->assertEquals( 1, $data['added'] );
		$this->assertEquals( 1, $data['skipped'] );

		global $wpdb;
		$alice_row = $wpdb->get_row( $wpdb->prepare(
			"SELECT is_working FROM {$wpdb->prefix}bookings_staff_working_hours
			WHERE staff_id = %d AND specific_date = %s",
			$staff_a,
			'2026-12-25'
		) );
		$this->assertEquals( 0, (int) $alice_row->is_working, 'Alice exception should be overwritten to day off' );

		$bob_row = $wpdb->get_row( $wpdb->prepare(
			"SELECT is_working, start_time FROM {$wpdb->prefix}bookings_staff_working_hours
			WHERE staff_id = %d AND specific_date = %s",
			$staff_b,
			'2026-12-25'
		) );
		$this->assertEquals( 1, (int) $bob_row->is_working, 'Bob exception should remain unchanged' );
		$this->assertEquals( '10:00:00', $bob_row->start_time );
	}

	/**
	 * Test bulk add validates date format.
	 *
	 * @covers Bookit_Dashboard_Bookings_API::bulk_add_exception
	 */
	public function test_bulk_add_validates_date_format() {
		$staff = $this->create_test_staff();

		$admin = $this->create_test_staff( array( 'role' => 'admin' ) );
		$this->login_as( $admin, 'admin' );

		$request = new WP_REST_Request( 'POST', '/' . $this->namespace . '/dashboard/staff/bulk-hours/add-exception' );
		$request->set_body_params( array(
			'staff_ids'     => array( $staff ),
			'specific_date' => 'invalid-date',
			'is_working'    => false,
		) );

		$response = rest_get_server()->dispatch( $request );

		$this->assertTrue( $response->is_error() );
		$this->assertEquals( 400, $response->get_status() );
		$error = $response->as_error();
		$this->assertEquals( 'invalid_date', $error->get_error_code() );
	}

	/**
	 * Test bulk add requires times when is_working is true.
	 *
	 * @covers Bookit_Dashboard_Bookings_API::bulk_add_exception
	 */
	public function test_bulk_add_requires_times_when_working() {
		$staff = $this->create_test_staff();

		$admin = $this->create_test_staff( array( 'role' => 'admin' ) );
		$this->login_as( $admin, 'admin' );

		$request = new WP_REST_Request( 'POST', '/' . $this->namespace . '/dashboard/staff/bulk-hours/add-exception' );
		$request->set_body_params( array(
			'staff_ids'     => array( $staff ),
			'specific_date' => '2026-12-25',
			'is_working'    => true,
		) );

		$response = rest_get_server()->dispatch( $request );

		$this->assertTrue( $response->is_error() );
		$this->assertEquals( 400, $response->get_status() );
		$error = $response->as_error();
		$this->assertEquals( 'missing_times', $error->get_error_code() );
	}

	/**
	 * Test bulk add results array includes per-staff status.
	 *
	 * @covers Bookit_Dashboard_Bookings_API::bulk_add_exception
	 */
	public function test_bulk_add_returns_per_staff_results() {
		$staff_a = $this->create_test_staff();
		$staff_b = $this->create_test_staff();

		$this->add_exception( $staff_a, '2026-12-25', '00:00:00', '00:00:00', 0 );

		$admin = $this->create_test_staff( array( 'role' => 'admin' ) );
		$this->login_as( $admin, 'admin' );

		$request = new WP_REST_Request( 'POST', '/' . $this->namespace . '/dashboard/staff/bulk-hours/add-exception' );
		$request->set_body_params( array(
			'staff_ids'     => array( $staff_a, $staff_b ),
			'specific_date' => '2026-12-25',
			'is_working'    => false,
		) );

		$response = rest_get_server()->dispatch( $request );

		$data    = $response->get_data();
		$results = $data['results'];

		$this->assertCount( 2, $results );

		$alice_result = $this->find_result_for_staff( $results, $staff_a );
		$bob_result   = $this->find_result_for_staff( $results, $staff_b );

		$this->assertEquals( 'skipped', $alice_result['status'] );
		$this->assertEquals( 'conflict', $alice_result['reason'] );
		$this->assertEquals( 'added', $bob_result['status'] );
	}

	// ========== TESTS FOR: POST /dashboard/staff/bulk-hours/update-schedule ==========

	/**
	 * Test bulk update working hours for specific day.
	 *
	 * @covers Bookit_Dashboard_Bookings_API::bulk_update_schedule
	 */
	public function test_bulk_update_working_hours() {
		$staff_a = $this->create_test_staff();
		$staff_b = $this->create_test_staff();

		$this->add_schedule( $staff_a, 1, '09:00:00', '17:00:00' );
		$this->add_schedule( $staff_b, 1, '10:00:00', '18:00:00' );

		$admin = $this->create_test_staff( array( 'role' => 'admin' ) );
		$this->login_as( $admin, 'admin' );

		$request = new WP_REST_Request( 'POST', '/' . $this->namespace . '/dashboard/staff/bulk-hours/update-schedule' );
		$request->set_body_params( array(
			'staff_ids'   => array( $staff_a, $staff_b ),
			'day_of_week' => 1,
			'updates'     => array(
				'start_time' => '08:00:00',
				'end_time'   => '16:00:00',
			),
		) );

		$response = rest_get_server()->dispatch( $request );

		$this->assertEquals( 200, $response->get_status() );

		$data = $response->get_data();
		$this->assertTrue( $data['success'] );
		$this->assertEquals( 2, $data['updated'] );

		global $wpdb;
		$alice_schedule = $wpdb->get_row( $wpdb->prepare(
			"SELECT start_time, end_time FROM {$wpdb->prefix}bookings_staff_working_hours
			WHERE staff_id = %d AND day_of_week = 1 AND specific_date IS NULL",
			$staff_a
		) );
		$this->assertEquals( '08:00:00', $alice_schedule->start_time );
		$this->assertEquals( '16:00:00', $alice_schedule->end_time );
	}

	/**
	 * Test bulk update break times only.
	 *
	 * @covers Bookit_Dashboard_Bookings_API::bulk_update_schedule
	 */
	public function test_bulk_update_break_times_only() {
		$staff_a = $this->create_test_staff();
		$staff_b = $this->create_test_staff();

		$this->add_schedule( $staff_a, 1, '09:00:00', '17:00:00', '12:00:00', '13:00:00' );
		$this->add_schedule( $staff_b, 1, '09:00:00', '17:00:00', '12:00:00', '13:00:00' );

		$admin = $this->create_test_staff( array( 'role' => 'admin' ) );
		$this->login_as( $admin, 'admin' );

		$request = new WP_REST_Request( 'POST', '/' . $this->namespace . '/dashboard/staff/bulk-hours/update-schedule' );
		$request->set_body_params( array(
			'staff_ids'   => array( $staff_a, $staff_b ),
			'day_of_week' => 1,
			'updates'     => array(
				'break_start' => '12:30:00',
				'break_end'   => '13:30:00',
			),
		) );

		$response = rest_get_server()->dispatch( $request );

		$this->assertEquals( 200, $response->get_status() );
		$this->assertEquals( 2, $response->get_data()['updated'] );

		global $wpdb;
		$alice_schedule = $wpdb->get_row( $wpdb->prepare(
			"SELECT start_time, end_time, break_start, break_end
			FROM {$wpdb->prefix}bookings_staff_working_hours
			WHERE staff_id = %d AND day_of_week = 1 AND specific_date IS NULL",
			$staff_a
		) );
		$this->assertEquals( '09:00:00', $alice_schedule->start_time, 'Working hours should be unchanged' );
		$this->assertEquals( '17:00:00', $alice_schedule->end_time, 'Working hours should be unchanged' );
		$this->assertEquals( '12:30:00', $alice_schedule->break_start, 'Break start should be updated' );
		$this->assertEquals( '13:30:00', $alice_schedule->break_end, 'Break end should be updated' );
	}

	/**
	 * Test bulk update only updates staff with existing schedules.
	 *
	 * @covers Bookit_Dashboard_Bookings_API::bulk_update_schedule
	 */
	public function test_bulk_update_only_updates_existing_schedules() {
		$staff_a = $this->create_test_staff();
		$staff_b = $this->create_test_staff();

		$this->add_schedule( $staff_a, 1, '09:00:00', '17:00:00' );

		$admin = $this->create_test_staff( array( 'role' => 'admin' ) );
		$this->login_as( $admin, 'admin' );

		$request = new WP_REST_Request( 'POST', '/' . $this->namespace . '/dashboard/staff/bulk-hours/update-schedule' );
		$request->set_body_params( array(
			'staff_ids'   => array( $staff_a, $staff_b ),
			'day_of_week' => 1,
			'updates'     => array(
				'start_time' => '08:00:00',
				'end_time'   => '16:00:00',
			),
		) );

		$response = rest_get_server()->dispatch( $request );

		$data = $response->get_data();
		$this->assertEquals( 1, $data['updated'] );

		$bob_skipped = $this->find_result_for_staff( $data['results'], $staff_b );
		$this->assertEquals( 'skipped', $bob_skipped['status'] );
		$this->assertEquals( 'no_schedule', $bob_skipped['reason'] );
	}

	/**
	 * Test bulk update validates day_of_week range.
	 *
	 * @covers Bookit_Dashboard_Bookings_API::bulk_update_schedule
	 */
	public function test_bulk_update_validates_day_of_week() {
		$staff = $this->create_test_staff();

		$admin = $this->create_test_staff( array( 'role' => 'admin' ) );
		$this->login_as( $admin, 'admin' );

		$request = new WP_REST_Request( 'POST', '/' . $this->namespace . '/dashboard/staff/bulk-hours/update-schedule' );
		$request->set_body_params( array(
			'staff_ids'   => array( $staff ),
			'day_of_week' => 8,
			'updates'     => array(
				'start_time' => '09:00:00',
				'end_time'   => '17:00:00',
			),
		) );

		$response = rest_get_server()->dispatch( $request );

		$this->assertTrue( $response->is_error() );
		$this->assertEquals( 400, $response->get_status() );
		$error = $response->as_error();
		$this->assertEquals( 'invalid_day', $error->get_error_code() );
	}

	/**
	 * Test bulk update rejects empty updates.
	 *
	 * @covers Bookit_Dashboard_Bookings_API::bulk_update_schedule
	 */
	public function test_bulk_update_rejects_empty_updates() {
		$staff = $this->create_test_staff();

		$admin = $this->create_test_staff( array( 'role' => 'admin' ) );
		$this->login_as( $admin, 'admin' );

		$request = new WP_REST_Request( 'POST', '/' . $this->namespace . '/dashboard/staff/bulk-hours/update-schedule' );
		$request->set_body_params( array(
			'staff_ids'   => array( $staff ),
			'day_of_week' => 1,
			'updates'     => array(),
		) );

		$response = rest_get_server()->dispatch( $request );

		$this->assertTrue( $response->is_error() );
		$this->assertEquals( 400, $response->get_status() );
		$error = $response->as_error();
		$this->assertEquals( 'no_updates', $error->get_error_code() );
	}

	// ========== TESTS FOR: Permissions ==========

	/**
	 * Test bulk operations require admin permission.
	 *
	 * @covers Bookit_Dashboard_Bookings_API::check_admin_permission
	 */
	public function test_bulk_operations_require_admin_permission() {
		$staff = $this->create_test_staff( array( 'role' => 'staff' ) );

		$this->login_as( $staff, 'staff' );

		$request = new WP_REST_Request( 'POST', '/' . $this->namespace . '/dashboard/staff/bulk-hours/add-exception' );
		$request->set_body_params( array(
			'staff_ids'     => array( $staff ),
			'specific_date' => '2026-12-25',
			'is_working'    => false,
		) );

		$response = rest_get_server()->dispatch( $request );

		$this->assertTrue( $response->is_error() );
		$this->assertEquals( 403, $response->get_status() );
	}

	/**
	 * Test unauthenticated user cannot access bulk endpoints.
	 *
	 * @covers Bookit_Dashboard_Bookings_API::check_admin_permission
	 */
	public function test_unauthenticated_cannot_access_bulk_endpoints() {
		$_SESSION = array();

		$request = new WP_REST_Request( 'POST', '/' . $this->namespace . '/dashboard/staff/bulk-hours/check-conflicts' );
		$request->set_body_params( array(
			'staff_ids'     => array( 1 ),
			'specific_date' => '2026-12-25',
		) );

		$response = rest_get_server()->dispatch( $request );

		$this->assertTrue( $response->is_error() );
		$this->assertEquals( 401, $response->get_status() );
	}

	// ========== TESTS FOR: Route registration ==========

	/**
	 * Test bulk working hours endpoints are registered.
	 *
	 * @covers Bookit_Dashboard_Bookings_API::register_routes
	 */
	public function test_bulk_working_hours_endpoints_registered() {
		$routes = rest_get_server()->get_routes();

		$this->assertArrayHasKey( '/' . $this->namespace . '/dashboard/staff/bulk-hours/check-conflicts', $routes );
		$this->assertArrayHasKey( '/' . $this->namespace . '/dashboard/staff/bulk-hours/add-exception', $routes );
		$this->assertArrayHasKey( '/' . $this->namespace . '/dashboard/staff/bulk-hours/update-schedule', $routes );
	}

	// ========== HELPER METHODS ==========

	/**
	 * Ensure the staff working hours table exists (migration may not have run).
	 */
	private function ensure_staff_working_hours_table() {
		global $wpdb;
		$table = $wpdb->prefix . 'bookings_staff_working_hours';

		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		if ( $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) ) === $table ) {
			return;
		}

		$migration = new Bookit_Migration_Add_Staff_Working_Hours();
		$migration->up();
	}

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
	 * Add exception (specific_date entry) directly to DB.
	 *
	 * @param int    $staff_id   Staff ID.
	 * @param string $date       Date Y-m-d.
	 * @param string $start_time Start time.
	 * @param string $end_time   End time.
	 * @param int    $is_working 1 or 0.
	 */
	private function add_exception( $staff_id, $date, $start_time, $end_time, $is_working ) {
		global $wpdb;

		$wpdb->insert(
			$wpdb->prefix . 'bookings_staff_working_hours',
			array(
				'staff_id'      => $staff_id,
				'day_of_week'   => null,
				'specific_date' => $date,
				'start_time'    => $start_time,
				'end_time'      => $end_time,
				'is_working'    => $is_working,
			),
			array( '%d', '%s', '%s', '%s', '%s', '%d' )
		);
	}

	/**
	 * Add recurring schedule (day_of_week entry) directly to DB.
	 *
	 * @param int         $staff_id    Staff ID.
	 * @param int         $day_of_week Day 1-7 (1=Monday, 7=Sunday).
	 * @param string      $start_time  Start time.
	 * @param string      $end_time    End time.
	 * @param string|null $break_start Break start.
	 * @param string|null $break_end   Break end.
	 */
	private function add_schedule( $staff_id, $day_of_week, $start_time, $end_time, $break_start = null, $break_end = null ) {
		global $wpdb;

		$wpdb->insert(
			$wpdb->prefix . 'bookings_staff_working_hours',
			array(
				'staff_id'      => $staff_id,
				'day_of_week'   => $day_of_week,
				'specific_date' => null,
				'start_time'    => $start_time,
				'end_time'      => $end_time,
				'is_working'    => 1,
				'break_start'   => $break_start,
				'break_end'     => $break_end,
			),
			array( '%d', '%d', '%s', '%s', '%s', '%d', '%s', '%s' )
		);
	}

	/**
	 * Find result entry for a specific staff ID in results array.
	 *
	 * @param array $results   Results array from API response.
	 * @param int   $staff_id  Staff ID to find.
	 * @return array|null Matching result or null.
	 */
	private function find_result_for_staff( $results, $staff_id ) {
		foreach ( $results as $result ) {
			if ( (int) $result['staff_id'] === (int) $staff_id ) {
				return $result;
			}
		}
		return null;
	}
}
