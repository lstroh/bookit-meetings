<?php
/**
 * Tests for Dashboard Staff API (Sprint 6A - notification preferences)
 *
 * @package    Bookit_Booking_System
 * @subpackage Tests
 */

/**
 * Test Bookit_Dashboard_Bookings_API staff endpoints.
 */
class Test_Dashboard_Staff_API extends WP_UnitTestCase {

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
				'bookings_staff',
				'bookings_staff_services',
				'bookings_services',
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
				'bookings_staff',
				'bookings_staff_services',
				'bookings_services',
			)
		);

		$_SESSION = array();

		parent::tearDown();
	}

	/**
	 * Test admin PUT saves notification_preferences and GET returns them.
	 *
	 * @covers Bookit_Dashboard_Bookings_API::update_staff
	 * @covers Bookit_Dashboard_Bookings_API::get_staff_details
	 */
	public function test_update_staff_saves_notification_preferences() {
		$staff_id = $this->insert_staff(
			array(
				'first_name' => 'Alice',
				'last_name'  => 'Prefs',
			)
		);
		$admin_id = $this->insert_staff(
			array(
				'first_name' => 'Admin',
				'last_name'  => 'User',
				'role'       => 'admin',
			)
		);

		$this->login_as( $admin_id, 'admin' );

		$put_request = new WP_REST_Request( 'PUT', '/' . $this->namespace . '/dashboard/staff/' . $staff_id );
		$put_request->set_body_params(
			array(
				'email'               => 'alice.prefs@example.com',
				'first_name'          => 'Alice',
				'last_name'           => 'Prefs',
				'phone'               => '07700900000',
				'photo_url'           => '',
				'bio'                 => '',
				'title'               => '',
				'role'                => 'staff',
				'google_calendar_id'  => '',
				'is_active'           => true,
				'display_order'       => 0,
				'service_assignments' => array(),
				'notification_preferences' => array(
					'new_booking'    => 'daily',
					'reschedule'     => 'weekly',
					'cancellation'   => 'immediate',
					'daily_schedule' => true,
				),
			)
		);

		$put_response = rest_get_server()->dispatch( $put_request );
		$this->assertEquals( 200, $put_response->get_status() );
		$this->assertTrue( $put_response->get_data()['success'] );

		$get_request  = new WP_REST_Request( 'GET', '/' . $this->namespace . '/dashboard/staff/' . $staff_id );
		$get_response = rest_get_server()->dispatch( $get_request );

		$this->assertEquals( 200, $get_response->get_status() );
		$data = $get_response->get_data();
		$this->assertTrue( $data['success'] );
		$this->assertArrayHasKey( 'notification_preferences', $data['staff'] );

		$this->assertEquals(
			array(
				'new_booking'    => 'daily',
				'reschedule'     => 'weekly',
				'cancellation'   => 'immediate',
				'daily_schedule' => true,
			),
			$data['staff']['notification_preferences']
		);
	}

	/**
	 * Test staff details include notification_preferences with defaults.
	 *
	 * @covers Bookit_Dashboard_Bookings_API::get_staff_details
	 */
	public function test_get_staff_detail_includes_notification_preferences() {
		$staff_id = $this->insert_staff(
			array(
				'first_name' => 'No',
				'last_name'  => 'Prefs',
			)
		);
		$admin_id = $this->insert_staff( array( 'role' => 'admin' ) );
		$this->login_as( $admin_id, 'admin' );

		$request  = new WP_REST_Request( 'GET', '/' . $this->namespace . '/dashboard/staff/' . $staff_id );
		$response = rest_get_server()->dispatch( $request );

		$this->assertEquals( 200, $response->get_status() );
		$data = $response->get_data();
		$this->assertTrue( $data['success'] );
		$this->assertArrayHasKey( 'notification_preferences', $data['staff'] );

		$prefs = $data['staff']['notification_preferences'];
		$this->assertIsArray( $prefs );
		$this->assertArrayHasKey( 'new_booking', $prefs );
		$this->assertArrayHasKey( 'reschedule', $prefs );
		$this->assertArrayHasKey( 'cancellation', $prefs );
		$this->assertArrayHasKey( 'daily_schedule', $prefs );
	}

	/**
	 * Test staff role cannot update other staff via admin-only endpoint.
	 *
	 * @covers Bookit_Dashboard_Bookings_API::check_admin_permission
	 */
	public function test_staff_role_cannot_update_other_staff_preferences() {
		$staff_id       = $this->insert_staff( array( 'first_name' => 'Target', 'last_name' => 'Staff' ) );
		$other_staff_id = $this->insert_staff( array( 'first_name' => 'Caller', 'last_name' => 'Staff', 'role' => 'staff' ) );
		$this->login_as( $other_staff_id, 'staff' );

		$request = new WP_REST_Request( 'PUT', '/' . $this->namespace . '/dashboard/staff/' . $staff_id );
		$request->set_body_params(
			array(
				'email'               => 'target.staff@example.com',
				'first_name'          => 'Target',
				'last_name'           => 'Staff',
				'phone'               => '07700900000',
				'photo_url'           => '',
				'bio'                 => '',
				'title'               => '',
				'role'                => 'staff',
				'google_calendar_id'  => '',
				'is_active'           => true,
				'display_order'       => 0,
				'service_assignments' => array(),
				'notification_preferences' => array(
					'new_booking'    => 'daily',
					'reschedule'     => 'weekly',
					'cancellation'   => 'immediate',
					'daily_schedule' => true,
				),
			)
		);

		$response = rest_get_server()->dispatch( $request );
		$this->assertTrue( $response->is_error() );
		$this->assertContains( $response->get_status(), array( 401, 403 ) );
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
	 * Insert a staff row with sensible defaults.
	 *
	 * @param array $overrides Field overrides.
	 * @return int Inserted staff id.
	 */
	private function insert_staff( array $overrides = array() ) {
		global $wpdb;

		$defaults = array(
			'first_name'         => 'Test',
			'last_name'          => 'Staff',
			'email'              => 'staff-' . wp_generate_password( 8, false, false ) . '@example.com',
			'password_hash'      => wp_hash_password( 'x' ),
			'phone'              => '07700900000',
			'photo_url'          => null,
			'bio'                => '',
			'title'              => '',
			'role'               => 'staff',
			'google_calendar_id' => null,
			'is_active'          => 1,
			'display_order'      => 0,
			'created_at'         => current_time( 'mysql' ),
			'updated_at'         => current_time( 'mysql' ),
			'deleted_at'         => null,
		);

		$data = wp_parse_args( $overrides, $defaults );

		$formats = array();
		$insert  = array();
		foreach ( $data as $key => $value ) {
			$insert[ $key ] = $value;
			if ( in_array( $key, array( 'is_active', 'display_order' ), true ) ) {
				$formats[] = '%d';
			} else {
				$formats[] = '%s';
			}
		}

		$wpdb->insert( $wpdb->prefix . 'bookings_staff', $insert, $formats );
		return (int) $wpdb->insert_id;
	}
}

