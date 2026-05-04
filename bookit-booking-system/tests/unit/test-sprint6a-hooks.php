<?php
/**
 * Sprint 6A-1: Dashboard lifecycle hooks (bookit_booking_rescheduled, bookit_booking_reassigned).
 *
 * @package    Bookit_Booking_System
 * @subpackage Tests
 */

/**
 * @covers Bookit_Dashboard_Bookings_API::update_booking
 */
class Test_Sprint6a_Hooks extends WP_UnitTestCase {

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

		bookit_test_truncate_tables(
			array(
				'bookings_package_redemptions',
				'bookings_customer_packages',
				'bookings_package_types',
				'bookings_payments',
				'bookings',
				'bookings_staff',
				'bookings_services',
				'bookings_customers',
				'bookings_staff_services',
				'bookings_staff_working_hours',
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
				'bookings_payments',
				'bookings',
				'bookings_staff',
				'bookings_services',
				'bookings_customers',
				'bookings_staff_services',
				'bookings_staff_working_hours',
			)
		);

		$_SESSION = array();

		parent::tearDown();
	}

	/**
	 * Monday / Tuesday working hours for slot checks when moving between dates.
	 *
	 * @param int $staff_id Staff ID.
	 */
	private function add_weekday_working_hours( $staff_id ) {
		$this->add_working_hours_row( $staff_id, 1, null, '09:00:00', '17:00:00' );
		$this->add_working_hours_row( $staff_id, 2, null, '09:00:00', '17:00:00' );
	}

	/**
	 * Insert a staff working hours row.
	 *
	 * @param int         $staff_id      Staff ID.
	 * @param int|null    $day_of_week   1–7 or null.
	 * @param string|null $specific_date Y-m-d or null.
	 * @param string      $start_time    Start time.
	 * @param string      $end_time      End time.
	 */
	private function add_working_hours_row( $staff_id, $day_of_week, $specific_date, $start_time, $end_time ) {
		global $wpdb;
		$wpdb->insert(
			$wpdb->prefix . 'bookings_staff_working_hours',
			array(
				'staff_id'      => $staff_id,
				'day_of_week'   => $day_of_week,
				'specific_date' => $specific_date,
				'start_time'    => $start_time,
				'end_time'      => $end_time,
				'is_working'    => 1,
				'break_start'   => null,
				'break_end'     => null,
			),
			array( '%d', '%d', '%s', '%s', '%s', '%d', '%s', '%s' )
		);
	}

	/**
	 * @param int    $staff_id Staff row ID.
	 * @param string $role     'admin' or 'staff'.
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
	 * @param array $args Override defaults.
	 * @return int Customer ID.
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
	 * @param array $args Override defaults.
	 * @return int Booking ID.
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
		);

		$data = wp_parse_args( $args, $defaults );

		$wpdb->insert( $wpdb->prefix . 'bookings', $data );
		return (int) $wpdb->insert_id;
	}

	/**
	 * @param int        $staff_id     Staff ID.
	 * @param int        $service_id   Service ID.
	 * @param float|null $custom_price Custom price or null.
	 */
	private function link_staff_to_service( $staff_id, $service_id, $custom_price = null ) {
		global $wpdb;

		$wpdb->insert(
			$wpdb->prefix . 'bookings_staff_services',
			array(
				'staff_id'     => $staff_id,
				'service_id'   => $service_id,
				'custom_price' => $custom_price,
				'created_at'   => current_time( 'mysql' ),
			),
			array( '%d', '%d', $custom_price === null ? '%s' : '%f', '%s' )
		);
	}

	/**
	 * PUT body for a full dashboard booking update (matches existing dashboard tests).
	 *
	 * @param int    $service_id Service ID.
	 * @param int    $staff_id   Staff ID.
	 * @param string $date       Y-m-d.
	 * @param string $time       Booking time.
	 * @param string $status     Status.
	 * @return array<string, mixed>
	 */
	private function dashboard_update_body( $service_id, $staff_id, $date, $time, $status ) {
		return array(
			'service_id'        => $service_id,
			'staff_id'          => $staff_id,
			'booking_date'      => $date,
			'booking_time'      => $time,
			'status'            => $status,
			'payment_method'    => 'cash',
			'amount_paid'       => 50,
			'send_notification' => false,
		);
	}

	public function test_rescheduled_hook_fires_on_date_change_in_update_booking() {
		$staff    = $this->create_test_staff();
		$service  = $this->create_test_service();
		$customer = $this->create_test_customer();
		$this->link_staff_to_service( $staff, $service );
		$this->add_weekday_working_hours( $staff );

		$booking_id = $this->create_test_booking(
			array(
				'staff_id'     => $staff,
				'service_id'   => $service,
				'customer_id'  => $customer,
				'booking_date' => '2026-06-15',
				'start_time'   => '10:00:00',
				'end_time'     => '11:00:00',
				'status'       => 'pending',
			)
		);

		$admin = $this->create_test_staff( array( 'role' => 'admin' ) );
		$this->login_as( $admin, 'admin' );

		$before = did_action( 'bookit_booking_rescheduled' );

		$request = new WP_REST_Request( 'PUT', '/' . $this->namespace . '/dashboard/bookings/' . $booking_id );
		$request->set_body_params(
			$this->dashboard_update_body( $service, $staff, '2026-06-16', '10:00:00', 'pending' )
		);

		$response = rest_get_server()->dispatch( $request );
		$this->assertEquals( 200, $response->get_status(), 'Update should succeed' );
		$this->assertEquals( $before + 1, did_action( 'bookit_booking_rescheduled' ) );
	}

	public function test_rescheduled_hook_fires_on_time_change_in_update_booking() {
		$staff    = $this->create_test_staff();
		$service  = $this->create_test_service();
		$customer = $this->create_test_customer();
		$this->link_staff_to_service( $staff, $service );
		$this->add_weekday_working_hours( $staff );

		$booking_id = $this->create_test_booking(
			array(
				'staff_id'     => $staff,
				'service_id'   => $service,
				'customer_id'  => $customer,
				'booking_date' => '2026-06-15',
				'start_time'   => '10:00:00',
				'end_time'     => '11:00:00',
				'status'       => 'pending',
			)
		);

		$admin = $this->create_test_staff( array( 'role' => 'admin' ) );
		$this->login_as( $admin, 'admin' );

		$before = did_action( 'bookit_booking_rescheduled' );

		$request = new WP_REST_Request( 'PUT', '/' . $this->namespace . '/dashboard/bookings/' . $booking_id );
		$request->set_body_params(
			$this->dashboard_update_body( $service, $staff, '2026-06-15', '11:00:00', 'pending' )
		);

		$response = rest_get_server()->dispatch( $request );
		$this->assertEquals( 200, $response->get_status(), 'Update should succeed' );
		$this->assertEquals( $before + 1, did_action( 'bookit_booking_rescheduled' ) );
	}

	public function test_rescheduled_hook_does_not_fire_when_date_unchanged() {
		$staff    = $this->create_test_staff();
		$service  = $this->create_test_service();
		$customer = $this->create_test_customer();
		$this->link_staff_to_service( $staff, $service );
		$this->add_weekday_working_hours( $staff );

		$booking_id = $this->create_test_booking(
			array(
				'staff_id'     => $staff,
				'service_id'   => $service,
				'customer_id'  => $customer,
				'booking_date' => '2026-06-15',
				'start_time'   => '10:00:00',
				'end_time'     => '11:00:00',
				'status'       => 'pending',
			)
		);

		$admin = $this->create_test_staff( array( 'role' => 'admin' ) );
		$this->login_as( $admin, 'admin' );

		$before = did_action( 'bookit_booking_rescheduled' );

		$request = new WP_REST_Request( 'PUT', '/' . $this->namespace . '/dashboard/bookings/' . $booking_id );
		$request->set_body_params(
			$this->dashboard_update_body( $service, $staff, '2026-06-15', '10:00:00', 'confirmed' )
		);

		$response = rest_get_server()->dispatch( $request );
		$this->assertEquals( 200, $response->get_status(), 'Update should succeed' );
		$this->assertEquals( $before, did_action( 'bookit_booking_rescheduled' ) );
	}

	public function test_reassigned_hook_fires_on_staff_id_change() {
		$staff_a  = $this->create_test_staff( array( 'first_name' => 'Alice' ) );
		$staff_b  = $this->create_test_staff( array( 'first_name' => 'Bob' ) );
		$service  = $this->create_test_service();
		$customer = $this->create_test_customer();
		$this->link_staff_to_service( $staff_a, $service );
		$this->link_staff_to_service( $staff_b, $service );
		$this->add_weekday_working_hours( $staff_a );
		$this->add_weekday_working_hours( $staff_b );

		$booking_id = $this->create_test_booking(
			array(
				'staff_id'     => $staff_a,
				'service_id'   => $service,
				'customer_id'  => $customer,
				'booking_date' => '2026-06-15',
				'start_time'   => '10:00:00',
				'end_time'     => '11:00:00',
				'status'       => 'pending',
			)
		);

		$admin = $this->create_test_staff( array( 'role' => 'admin' ) );
		$this->login_as( $admin, 'admin' );

		$before = did_action( 'bookit_booking_reassigned' );

		$request = new WP_REST_Request( 'PUT', '/' . $this->namespace . '/dashboard/bookings/' . $booking_id );
		$request->set_body_params(
			$this->dashboard_update_body( $service, $staff_b, '2026-06-15', '10:00:00', 'pending' )
		);

		$response = rest_get_server()->dispatch( $request );
		$this->assertEquals( 200, $response->get_status(), 'Update should succeed' );
		$this->assertEquals( $before + 1, did_action( 'bookit_booking_reassigned' ) );
	}

	public function test_reassigned_hook_passes_old_and_new_staff_ids() {
		$staff_a  = $this->create_test_staff( array( 'first_name' => 'Alice' ) );
		$staff_b  = $this->create_test_staff( array( 'first_name' => 'Bob' ) );
		$service  = $this->create_test_service();
		$customer = $this->create_test_customer();
		$this->link_staff_to_service( $staff_a, $service );
		$this->link_staff_to_service( $staff_b, $service );
		$this->add_weekday_working_hours( $staff_a );
		$this->add_weekday_working_hours( $staff_b );

		$booking_id = $this->create_test_booking(
			array(
				'staff_id'     => $staff_a,
				'service_id'   => $service,
				'customer_id'  => $customer,
				'booking_date' => '2026-06-15',
				'start_time'   => '10:00:00',
				'end_time'     => '11:00:00',
				'status'       => 'pending',
			)
		);

		$admin = $this->create_test_staff( array( 'role' => 'admin' ) );
		$this->login_as( $admin, 'admin' );

		$captured_old = null;
		$captured_new = null;
		$cb           = function ( $bid, $old_id, $new_id ) use ( &$captured_old, &$captured_new, $booking_id ) {
			if ( (int) $bid === $booking_id ) {
				$captured_old = (int) $old_id;
				$captured_new = (int) $new_id;
			}
		};
		add_action( 'bookit_booking_reassigned', $cb, 10, 3 );

		$request = new WP_REST_Request( 'PUT', '/' . $this->namespace . '/dashboard/bookings/' . $booking_id );
		$request->set_body_params(
			$this->dashboard_update_body( $service, $staff_b, '2026-06-15', '10:00:00', 'pending' )
		);

		$response = rest_get_server()->dispatch( $request );
		remove_action( 'bookit_booking_reassigned', $cb, 10 );

		$this->assertEquals( 200, $response->get_status(), 'Update should succeed' );
		$this->assertSame( $staff_a, $captured_old );
		$this->assertSame( $staff_b, $captured_new );
	}

	public function test_reassigned_hook_does_not_fire_when_staff_unchanged() {
		$staff    = $this->create_test_staff();
		$service  = $this->create_test_service();
		$customer = $this->create_test_customer();
		$this->link_staff_to_service( $staff, $service );
		$this->add_weekday_working_hours( $staff );

		$booking_id = $this->create_test_booking(
			array(
				'staff_id'     => $staff,
				'service_id'   => $service,
				'customer_id'  => $customer,
				'booking_date' => '2026-06-15',
				'start_time'   => '10:00:00',
				'end_time'     => '11:00:00',
				'status'       => 'pending',
			)
		);

		$admin = $this->create_test_staff( array( 'role' => 'admin' ) );
		$this->login_as( $admin, 'admin' );

		$before = did_action( 'bookit_booking_reassigned' );

		$request = new WP_REST_Request( 'PUT', '/' . $this->namespace . '/dashboard/bookings/' . $booking_id );
		$request->set_body_params(
			$this->dashboard_update_body( $service, $staff, '2026-06-15', '10:00:00', 'confirmed' )
		);

		$response = rest_get_server()->dispatch( $request );
		$this->assertEquals( 200, $response->get_status(), 'Update should succeed' );
		$this->assertEquals( $before, did_action( 'bookit_booking_reassigned' ) );
	}
}
