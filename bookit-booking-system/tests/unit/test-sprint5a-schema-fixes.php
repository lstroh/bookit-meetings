<?php
/**
 * Sprint 5A schema fixes (magic link token, status transitions, payments ENUM, POA payment row).
 *
 * @package    Bookit_Booking_System
 * @subpackage Tests
 */

/**
 * Tests for DB schema and related behavior (Issues 4, 7, 12, 13).
 */
class Test_Sprint5a_Schema_Fixes extends WP_UnitTestCase {

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
				'bookings_payments',
				'bookings',
				'bookings_staff',
				'bookings_services',
				'bookings_customers',
				'bookings_staff_services',
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
			)
		);

		$_SESSION = array();

		parent::tearDown();
	}

	/**
	 * Magic link column exists on bookings table.
	 */
	public function test_magic_link_token_column_exists() {
		global $wpdb;

		$table = $wpdb->prefix . 'bookings';
		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.DirectDatabaseQuery.DirectQuery
		$cols = $wpdb->get_results( "SHOW COLUMNS FROM {$table} LIKE 'magic_link_token'" );
		$this->assertNotEmpty( $cols );
	}

	/**
	 * New booking row receives a magic link token from the booking creator.
	 */
	public function test_new_booking_has_magic_link_token() {
		global $wpdb;

		$staff    = $this->create_test_staff();
		$service  = $this->create_test_service();
		$this->link_staff_to_service( $staff, $service );

		$booking_date = gmdate( 'Y-m-d', strtotime( '+40 days' ) );

		require_once BOOKIT_PLUGIN_DIR . 'includes/booking/class-booking-creator.php';
		$creator = new Booking_System_Booking_Creator();

		$booking_id = $creator->create_booking(
			array(
				'service_id'          => $service,
				'staff_id'            => $staff,
				'booking_date'        => $booking_date,
				'booking_time'          => '10:00',
				'customer_first_name' => 'Test',
				'customer_last_name'  => 'User',
				'customer_email'      => 'magic-' . wp_generate_password( 6, false ) . '@example.com',
				'customer_phone'      => '07700900001',
				'payment_method'      => 'cash',
				'amount_paid'         => 50.00,
				'payment_intent_id'   => null,
				'stripe_session_id'   => null,
			)
		);

		$this->assertIsInt( $booking_id );

		$token = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT magic_link_token FROM {$wpdb->prefix}bookings WHERE id = %d",
				$booking_id
			)
		);

		$this->assertNotNull( $token );
		$this->assertGreaterThanOrEqual( 32, strlen( (string) $token ) );
	}

	/**
	 * Two bookings get distinct magic link tokens.
	 */
	public function test_magic_link_token_is_unique_per_booking() {
		$staff    = $this->create_test_staff();
		$service  = $this->create_test_service();
		$this->link_staff_to_service( $staff, $service );

		require_once BOOKIT_PLUGIN_DIR . 'includes/booking/class-booking-creator.php';
		$creator = new Booking_System_Booking_Creator();

		$base_date = gmdate( 'Y-m-d', strtotime( '+41 days' ) );

		$id1 = $creator->create_booking(
			array(
				'service_id'          => $service,
				'staff_id'            => $staff,
				'booking_date'        => $base_date,
				'booking_time'        => '09:00',
				'customer_first_name' => 'A',
				'customer_last_name'  => 'One',
				'customer_email'      => 'a-' . wp_generate_password( 6, false ) . '@example.com',
				'customer_phone'      => '07700900002',
				'payment_method'      => 'cash',
				'amount_paid'         => 50.00,
				'payment_intent_id'   => null,
				'stripe_session_id'   => null,
			)
		);

		$id2 = $creator->create_booking(
			array(
				'service_id'          => $service,
				'staff_id'            => $staff,
				'booking_date'        => $base_date,
				'booking_time'        => '11:00',
				'customer_first_name' => 'B',
				'customer_last_name'  => 'Two',
				'customer_email'      => 'b-' . wp_generate_password( 6, false ) . '@example.com',
				'customer_phone'      => '07700900003',
				'payment_method'      => 'cash',
				'amount_paid'         => 50.00,
				'payment_intent_id'   => null,
				'stripe_session_id'   => null,
			)
		);

		$this->assertIsInt( $id1 );
		$this->assertIsInt( $id2 );

		global $wpdb;
		$t1 = $wpdb->get_var( $wpdb->prepare( "SELECT magic_link_token FROM {$wpdb->prefix}bookings WHERE id = %d", $id1 ) );
		$t2 = $wpdb->get_var( $wpdb->prepare( "SELECT magic_link_token FROM {$wpdb->prefix}bookings WHERE id = %d", $id2 ) );

		$this->assertNotEmpty( $t1 );
		$this->assertNotEmpty( $t2 );
		$this->assertNotSame( $t1, $t2 );
	}

	/**
	 * Invalid transition completed → confirmed returns 422 E2005.
	 */
	public function test_invalid_status_transition_returns_422() {
		$staff    = $this->create_test_staff();
		$service  = $this->create_test_service();
		$customer = $this->create_test_customer();

		$booking_id = $this->create_test_booking(
			array(
				'staff_id'     => $staff,
				'service_id'   => $service,
				'customer_id'  => $customer,
				'booking_date' => '2026-06-15',
				'start_time'   => '10:00:00',
				'end_time'     => '11:00:00',
				'status'       => 'completed',
			)
		);

		$admin = $this->create_test_staff( array( 'role' => 'admin' ) );
		$this->login_as( $admin, 'admin' );

		$request = new WP_REST_Request( 'PUT', '/' . $this->namespace . '/dashboard/bookings/' . $booking_id );
		$request->set_body_params(
			array(
				'service_id'        => $service,
				'staff_id'          => $staff,
				'booking_date'      => '2026-06-15',
				'booking_time'      => '10:00:00',
				'status'            => 'confirmed',
				'payment_method'    => 'cash',
				'amount_paid'       => 50,
				'send_notification' => false,
			)
		);

		$response = rest_get_server()->dispatch( $request );

		$this->assertTrue( $response->is_error() );
		$this->assertEquals( 422, $response->get_status() );
		$this->assertEquals( 'E2005', $response->as_error()->get_error_code() );
	}

	/**
	 * Valid transition confirmed → completed returns 200.
	 */
	public function test_valid_status_transition_succeeds() {
		$staff    = $this->create_test_staff();
		$service  = $this->create_test_service();
		$customer = $this->create_test_customer();

		$booking_id = $this->create_test_booking(
			array(
				'staff_id'     => $staff,
				'service_id'   => $service,
				'customer_id'  => $customer,
				'booking_date' => '2026-06-15',
				'start_time'   => '10:00:00',
				'end_time'     => '11:00:00',
				'status'       => 'confirmed',
			)
		);

		$admin = $this->create_test_staff( array( 'role' => 'admin' ) );
		$this->login_as( $admin, 'admin' );

		$request = new WP_REST_Request( 'PUT', '/' . $this->namespace . '/dashboard/bookings/' . $booking_id );
		$request->set_body_params(
			array(
				'service_id'        => $service,
				'staff_id'          => $staff,
				'booking_date'      => '2026-06-15',
				'booking_time'      => '10:00:00',
				'status'            => 'completed',
				'payment_method'    => 'cash',
				'amount_paid'       => 50,
				'send_notification' => false,
			)
		);

		$response = rest_get_server()->dispatch( $request );

		$this->assertEquals( 200, $response->get_status() );
		$this->assertEquals( 'completed', $response->get_data()['booking']['status'] );
	}

	/**
	 * Terminal statuses cannot move to confirmed.
	 */
	public function test_terminal_status_cannot_be_changed() {
		$staff    = $this->create_test_staff();
		$service  = $this->create_test_service();
		$customer = $this->create_test_customer();

		$cancelled_id = $this->create_test_booking(
			array(
				'staff_id'     => $staff,
				'service_id'   => $service,
				'customer_id'  => $customer,
				'booking_date' => '2026-06-16',
				'start_time'   => '10:00:00',
				'end_time'     => '11:00:00',
				'status'       => 'cancelled',
			)
		);

		$no_show_id = $this->create_test_booking(
			array(
				'staff_id'     => $staff,
				'service_id'   => $service,
				'customer_id'  => $customer,
				'booking_date' => '2026-06-16',
				'start_time'   => '14:00:00',
				'end_time'     => '15:00:00',
				'status'       => 'no_show',
			)
		);

		$admin = $this->create_test_staff( array( 'role' => 'admin' ) );
		$this->login_as( $admin, 'admin' );

		foreach ( array( $cancelled_id, $no_show_id ) as $bid ) {
			$request = new WP_REST_Request( 'PUT', '/' . $this->namespace . '/dashboard/bookings/' . $bid );
			$request->set_body_params(
				array(
					'service_id'        => $service,
					'staff_id'          => $staff,
					'booking_date'      => '2026-06-16',
					'booking_time'      => $bid === $cancelled_id ? '10:00:00' : '14:00:00',
					'status'            => 'confirmed',
					'payment_method'    => 'cash',
					'amount_paid'       => 50,
					'send_notification' => false,
				)
			);

			$response = rest_get_server()->dispatch( $request );
			$this->assertTrue( $response->is_error(), 'Expected error for booking ' . $bid );
			$this->assertEquals( 422, $response->get_status() );
			$this->assertEquals( 'E2005', $response->as_error()->get_error_code() );
		}
	}

	/**
	 * DB accepts balance_payment in payment_type ENUM.
	 */
	public function test_balance_payment_enum_value_accepted() {
		global $wpdb;

		$staff    = $this->create_test_staff();
		$service  = $this->create_test_service();
		$customer = $this->create_test_customer();

		$booking_id = $this->create_test_booking(
			array(
				'staff_id'     => $staff,
				'service_id'   => $service,
				'customer_id'  => $customer,
				'status'       => 'confirmed',
			)
		);

		$wpdb->insert(
			$wpdb->prefix . 'bookings_payments',
			array(
				'booking_id'       => $booking_id,
				'customer_id'      => $customer,
				'amount'           => 10.00,
				'payment_type'     => 'balance_payment',
				'payment_method'   => 'stripe',
				'payment_status'   => 'pending',
				'transaction_date' => current_time( 'mysql' ),
				'created_at'       => current_time( 'mysql' ),
				'updated_at'       => current_time( 'mysql' ),
			),
			array( '%d', '%d', '%f', '%s', '%s', '%s', '%s', '%s', '%s' )
		);

		$this->assertEmpty( $wpdb->last_error );
		$this->assertGreaterThan( 0, (int) $wpdb->insert_id );
	}

	/**
	 * Pay on arrival creates a pending payment row.
	 */
	public function test_poa_booking_creates_payment_record() {
		global $wpdb;

		$staff    = $this->create_test_staff();
		$service  = $this->create_test_service();
		$this->link_staff_to_service( $staff, $service );

		$date = gmdate( 'Y-m-d', strtotime( '+42 days' ) );

		require_once BOOKIT_PLUGIN_DIR . 'includes/payment/class-payment-processor.php';
		add_filter( 'bookit_send_email', '__return_false' );

		$processor = new Booking_System_Payment_Processor();
		$session   = array(
			'service_id'                => $service,
			'staff_id'                  => $staff,
			'date'                      => $date,
			'time'                      => '10:00:00',
			'customer_first_name'       => 'PoA',
			'customer_last_name'        => 'Test',
			'customer_email'            => 'poa-' . wp_generate_password( 6, false ) . '@example.com',
			'customer_phone'            => '07700900004',
			'customer_special_requests' => '',
			'cooling_off_waiver'        => 1,
			'total_price'               => 50.00,
		);

		$result = $processor->process_pay_on_arrival( $session );

		remove_filter( 'bookit_send_email', '__return_false' );

		$this->assertIsArray( $result );
		$this->assertArrayHasKey( 'booking_id', $result );
		$booking_id = (int) $result['booking_id'];
		$this->assertGreaterThan( 0, $booking_id );

		$row = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$wpdb->prefix}bookings_payments WHERE booking_id = %d",
				$booking_id
			)
		);

		$this->assertNotNull( $row );
		$this->assertSame( 'pay_on_arrival', $row->payment_method );
		$this->assertSame( 'pending', $row->payment_status );
		$this->assertSame( 'full_payment', $row->payment_type );
	}

	/**
	 * @param array $args Staff row overrides.
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
	 * @param array $args Service overrides.
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
	 * @param array $args Customer overrides.
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
	 * @param array $args Booking overrides.
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
		);

		$data = wp_parse_args( $args, $defaults );

		$wpdb->insert( $wpdb->prefix . 'bookings', $data );
		return (int) $wpdb->insert_id;
	}

	/**
	 * @param int $staff_id   Staff ID.
	 * @param int $service_id Service ID.
	 */
	private function link_staff_to_service( $staff_id, $service_id ) {
		global $wpdb;

		$wpdb->insert(
			$wpdb->prefix . 'bookings_staff_services',
			array(
				'staff_id'     => $staff_id,
				'service_id'   => $service_id,
				'custom_price' => null,
				'created_at'   => current_time( 'mysql' ),
			),
			array( '%d', '%d', '%s', '%s' )
		);
	}

	/**
	 * @param int    $staff_id Staff ID.
	 * @param string $role     admin|staff.
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
}
