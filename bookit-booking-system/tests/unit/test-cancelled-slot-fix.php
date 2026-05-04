<?php
/**
 * Cancellation slot freeing tests (Sprint 6E).
 *
 * @package    Bookit_Booking_System
 * @subpackage Tests
 */

/**
 * @covers Bookit_Dashboard_Bookings_API::cancel_booking
 * @covers Bookit_Dashboard_Bookings_API::bulk_action
 * @covers Bookit_Wizard_API::cancel_booking_magic_link
 */
class Test_Cancelled_Slot_Fix extends WP_UnitTestCase {

	/**
	 * REST namespace.
	 *
	 * @var string
	 */
	private $namespace = 'bookit/v1';

	public function setUp(): void {
		parent::setUp();

		bookit_test_truncate_tables(
			array(
				'bookings_email_queue',
				'bookings_audit_log',
				'bookings_settings',
				'bookings',
				'bookings_staff_services',
				'bookings_services',
				'bookings_staff',
				'bookings_customers',
			)
		);

		$ip = sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ?? '' ) );
		delete_transient( Bookit_Rate_Limiter::KEY_PREFIX . 'magic_cancel_' . md5( $ip ) );

		do_action( 'rest_api_init' );
	}

	public function tearDown(): void {
		$_GET = array();
		parent::tearDown();
	}

	public function test_cancelled_booking_frees_unique_slot() {
		$staff    = $this->create_test_staff();
		$service  = $this->create_test_service();
		$customer = $this->create_test_customer();
		$this->link_staff_to_service( $staff, $service );

		$date = '2026-06-15';
		$time = '10:00:00';

		$booking_id = $this->create_test_booking(
			array(
				'staff_id'     => $staff,
				'service_id'   => $service,
				'customer_id'  => $customer,
				'booking_date' => $date,
				'start_time'   => $time,
				'end_time'     => '11:00:00',
				'status'       => 'confirmed',
			)
		);

		global $wpdb;
		$wpdb->update(
			$wpdb->prefix . 'bookings',
			array(
				'status'               => 'cancelled',
				'deleted_at'           => current_time( 'mysql' ),
				'updated_at'           => current_time( 'mysql' ),
				'cancelled_start_time' => $time,
				'cancelled_end_time'   => '11:00:00',
				'start_time'           => null,
				'end_time'             => null,
			),
			array( 'id' => $booking_id ),
			array( '%s', '%s', '%s', '%s', '%s', '%s', '%s' ),
			array( '%d' )
		);

		$second_id = $this->create_test_booking(
			array(
				'staff_id'     => $staff,
				'service_id'   => $service,
				'customer_id'  => $customer,
				'booking_date' => $date,
				'start_time'   => $time,
				'end_time'     => '11:00:00',
				'status'       => 'confirmed',
			)
		);

		$this->assertIsInt( $second_id );
		$this->assertGreaterThan( 0, $second_id );
	}

	public function test_cancel_preserves_original_times_in_cancelled_columns() {
		$staff    = $this->create_test_staff();
		$service  = $this->create_test_service();
		$customer = $this->create_test_customer();
		$this->link_staff_to_service( $staff, $service );

		$booking_id = $this->create_test_booking(
			array(
				'staff_id'     => $staff,
				'service_id'   => $service,
				'customer_id'  => $customer,
				'booking_date' => '2026-06-16',
				'start_time'   => '10:00:00',
				'end_time'     => '11:00:00',
				'status'       => 'confirmed',
			)
		);

		global $wpdb;
		$wpdb->update(
			$wpdb->prefix . 'bookings',
			array(
				'status'               => 'cancelled',
				'deleted_at'           => current_time( 'mysql' ),
				'updated_at'           => current_time( 'mysql' ),
				'cancelled_start_time' => '10:00:00',
				'cancelled_end_time'   => '11:00:00',
				'start_time'           => null,
				'end_time'             => null,
			),
			array( 'id' => $booking_id ),
			array( '%s', '%s', '%s', '%s', '%s', '%s', '%s' ),
			array( '%d' )
		);

		$row = $wpdb->get_row(
			$wpdb->prepare( "SELECT cancelled_start_time, cancelled_end_time FROM {$wpdb->prefix}bookings WHERE id = %d", $booking_id ),
			ARRAY_A
		);

		$this->assertSame( '10:00:00', $row['cancelled_start_time'] );
		$this->assertSame( '11:00:00', $row['cancelled_end_time'] );
	}

	public function test_cancelled_booking_has_null_start_time() {
		$staff    = $this->create_test_staff();
		$service  = $this->create_test_service();
		$customer = $this->create_test_customer();
		$this->link_staff_to_service( $staff, $service );

		$booking_id = $this->create_test_booking(
			array(
				'staff_id'     => $staff,
				'service_id'   => $service,
				'customer_id'  => $customer,
				'booking_date' => '2026-06-17',
				'start_time'   => '10:00:00',
				'end_time'     => '11:00:00',
				'status'       => 'confirmed',
			)
		);

		global $wpdb;
		$wpdb->update(
			$wpdb->prefix . 'bookings',
			array(
				'status'               => 'cancelled',
				'deleted_at'           => current_time( 'mysql' ),
				'updated_at'           => current_time( 'mysql' ),
				'cancelled_start_time' => '10:00:00',
				'cancelled_end_time'   => '11:00:00',
				'start_time'           => null,
				'end_time'             => null,
			),
			array( 'id' => $booking_id ),
			array( '%s', '%s', '%s', '%s', '%s', '%s', '%s' ),
			array( '%d' )
		);

		$row = $wpdb->get_row(
			$wpdb->prepare( "SELECT start_time FROM {$wpdb->prefix}bookings WHERE id = %d", $booking_id ),
			ARRAY_A
		);

		$this->assertNull( $row['start_time'] );
	}

	public function test_magic_link_cancel_also_frees_slot() {
		$staff    = $this->create_test_staff();
		$service  = $this->create_test_service();
		$customer = $this->create_test_customer();
		$this->link_staff_to_service( $staff, $service );

		$date  = gmdate( 'Y-m-d', strtotime( '+3 days' ) );
		$start = '10:00:00';

		$booking_id = $this->create_test_booking(
			array(
				'staff_id'     => $staff,
				'service_id'   => $service,
				'customer_id'  => $customer,
				'booking_date' => $date,
				'start_time'   => $start,
				'end_time'     => '11:00:00',
				'status'       => 'confirmed',
			)
		);

		$token = 'magic-token-' . wp_generate_password( 12, false );
		global $wpdb;
		$wpdb->update(
			$wpdb->prefix . 'bookings',
			array( 'magic_link_token' => $token ),
			array( 'id' => $booking_id ),
			array( '%s' ),
			array( '%d' )
		);

		$request = new WP_REST_Request( 'POST', '/' . $this->namespace . '/wizard/cancel' );
		$request->set_body_params(
			array(
				'booking_id' => $booking_id,
				'token'      => $token,
				'reason'     => 'Test cancel',
			)
		);

		$response = rest_get_server()->dispatch( $request );
		$this->assertEquals( 200, $response->get_status() );

		$start_time = $wpdb->get_var(
			$wpdb->prepare( "SELECT start_time FROM {$wpdb->prefix}bookings WHERE id = %d", $booking_id )
		);
		$this->assertNull( $start_time );

		$new_id = $this->create_test_booking(
			array(
				'staff_id'     => $staff,
				'service_id'   => $service,
				'customer_id'  => $customer,
				'booking_date' => $date,
				'start_time'   => $start,
				'end_time'     => '11:00:00',
				'status'       => 'confirmed',
			)
		);
		$this->assertGreaterThan( 0, $new_id );
	}

	public function test_availability_check_ignores_cancelled_bookings() {
		$staff    = $this->create_test_staff();
		$service  = $this->create_test_service();
		$customer = $this->create_test_customer();
		$this->link_staff_to_service( $staff, $service );

		$date = '2026-06-18';
		$time = '10:00:00';

		$booking_a = $this->create_test_booking(
			array(
				'staff_id'     => $staff,
				'service_id'   => $service,
				'customer_id'  => $customer,
				'booking_date' => $date,
				'start_time'   => $time,
				'end_time'     => '11:00:00',
				'status'       => 'confirmed',
			)
		);

		global $wpdb;
		$wpdb->update(
			$wpdb->prefix . 'bookings',
			array(
				'status'               => 'cancelled',
				'deleted_at'           => current_time( 'mysql' ),
				'updated_at'           => current_time( 'mysql' ),
				'cancelled_start_time' => $time,
				'cancelled_end_time'   => '11:00:00',
				'start_time'           => null,
				'end_time'             => null,
			),
			array( 'id' => $booking_a ),
			array( '%s', '%s', '%s', '%s', '%s', '%s', '%s' ),
			array( '%d' )
		);

		$booking_b = $this->create_test_booking(
			array(
				'staff_id'     => $staff,
				'service_id'   => $service,
				'customer_id'  => $customer,
				'booking_date' => $date,
				'start_time'   => $time,
				'end_time'     => '11:00:00',
				'status'       => 'confirmed',
			)
		);
		$this->assertGreaterThan( 0, $booking_b );

		$count_non_cancelled = (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$wpdb->prefix}bookings
				WHERE staff_id = %d AND booking_date = %s AND start_time = %s AND status != 'cancelled'",
				$staff,
				$date,
				$time
			)
		);
		$this->assertSame( 1, $count_non_cancelled );
	}

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
}

