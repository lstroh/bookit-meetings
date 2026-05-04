<?php
/**
 * Magic link cancel/reschedule REST API and shortcodes (Task 5A-3a).
 *
 * @package    Bookit_Booking_System
 * @subpackage Tests
 */

/**
 * @covers Bookit_Wizard_API::cancel_booking_magic_link
 * @covers Bookit_Wizard_API::reschedule_booking_magic_link
 */
class Test_Magic_Link_Flows extends WP_UnitTestCase {

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
		delete_transient( Bookit_Rate_Limiter::KEY_PREFIX . 'magic_reschedule_' . md5( $ip ) );

		do_action( 'rest_api_init' );
	}

	/**
	 * Tear down each test.
	 */
	public function tearDown(): void {
		$_GET = array();
		parent::tearDown();
	}

	/**
	 * Wrong token returns 403.
	 */
	public function test_cancel_endpoint_requires_valid_token() {
		$staff    = $this->create_test_staff();
		$service  = $this->create_test_service();
		$customer = $this->create_test_customer();
		$this->link_staff_to_service( $staff, $service );

		$future = gmdate( 'Y-m-d', strtotime( '+5 days' ) );
		$bid    = $this->create_test_booking(
			array(
				'staff_id'     => $staff,
				'service_id'   => $service,
				'customer_id'  => $customer,
				'booking_date' => $future,
				'start_time'   => '10:00:00',
				'end_time'     => '11:00:00',
				'status'       => 'confirmed',
			)
		);

		global $wpdb;
		$wpdb->update(
			$wpdb->prefix . 'bookings',
			array( 'magic_link_token' => 'correct-token-abc' ),
			array( 'id' => $bid ),
			array( '%s' ),
			array( '%d' )
		);

		$request = new WP_REST_Request( 'POST', '/' . $this->namespace . '/wizard/cancel' );
		$request->set_body_params(
			array(
				'booking_id' => $bid,
				'token'      => 'wrong-token',
			)
		);

		$response = rest_get_server()->dispatch( $request );
		$this->assertEquals( 403, $response->get_status() );
	}

	/**
	 * Valid token cancels booking outside policy window.
	 */
	public function test_cancel_endpoint_cancels_booking_with_valid_token() {
		$staff    = $this->create_test_staff();
		$service  = $this->create_test_service();
		$customer = $this->create_test_customer();
		$this->link_staff_to_service( $staff, $service );

		$future = gmdate( 'Y-m-d', strtotime( '+3 days' ) );
		$bid    = $this->create_test_booking(
			array(
				'staff_id'     => $staff,
				'service_id'   => $service,
				'customer_id'  => $customer,
				'booking_date' => $future,
				'start_time'   => '10:00:00',
				'end_time'     => '11:00:00',
				'status'       => 'confirmed',
			)
		);

		$token = 'magic-token-' . wp_generate_password( 12, false );
		global $wpdb;
		$wpdb->update(
			$wpdb->prefix . 'bookings',
			array( 'magic_link_token' => $token ),
			array( 'id' => $bid ),
			array( '%s' ),
			array( '%d' )
		);

		$request = new WP_REST_Request( 'POST', '/' . $this->namespace . '/wizard/cancel' );
		$request->set_body_params(
			array(
				'booking_id' => $bid,
				'token'      => $token,
				'reason'     => 'Test cancel',
			)
		);

		$response = rest_get_server()->dispatch( $request );
		$this->assertEquals( 200, $response->get_status() );

		$row = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT status, cancelled_by, deleted_at FROM {$wpdb->prefix}bookings WHERE id = %d",
				$bid
			),
			ARRAY_A
		);

		$this->assertSame( 'cancelled', $row['status'] );
		$this->assertSame( 'customer', $row['cancelled_by'] );
		$this->assertNotNull( $row['deleted_at'] );
	}

	/**
	 * cancelled_by is customer.
	 */
	public function test_cancel_endpoint_sets_cancelled_by_customer() {
		$staff    = $this->create_test_staff();
		$service  = $this->create_test_service();
		$customer = $this->create_test_customer();
		$this->link_staff_to_service( $staff, $service );

		$future = gmdate( 'Y-m-d', strtotime( '+4 days' ) );
		$bid    = $this->create_test_booking(
			array(
				'staff_id'     => $staff,
				'service_id'   => $service,
				'customer_id'  => $customer,
				'booking_date' => $future,
				'start_time'   => '11:00:00',
				'end_time'     => '12:00:00',
				'status'       => 'confirmed',
			)
		);

		$token = 'tok-' . wp_generate_password( 8, false );
		global $wpdb;
		$wpdb->update(
			$wpdb->prefix . 'bookings',
			array( 'magic_link_token' => $token ),
			array( 'id' => $bid ),
			array( '%s' ),
			array( '%d' )
		);

		$request = new WP_REST_Request( 'POST', '/' . $this->namespace . '/wizard/cancel' );
		$request->set_body_params(
			array(
				'booking_id' => $bid,
				'token'      => $token,
			)
		);

		$response = rest_get_server()->dispatch( $request );
		$this->assertEquals( 200, $response->get_status() );

		$cancelled_by = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT cancelled_by FROM {$wpdb->prefix}bookings WHERE id = %d",
				$bid
			)
		);
		$this->assertSame( 'customer', $cancelled_by );
	}

	/**
	 * Terminal status returns 422 E2003.
	 */
	public function test_cancel_endpoint_rejects_already_cancelled_booking() {
		$staff    = $this->create_test_staff();
		$service  = $this->create_test_service();
		$customer = $this->create_test_customer();
		$this->link_staff_to_service( $staff, $service );

		$future = gmdate( 'Y-m-d', strtotime( '+6 days' ) );
		$bid    = $this->create_test_booking(
			array(
				'staff_id'     => $staff,
				'service_id'   => $service,
				'customer_id'  => $customer,
				'booking_date' => $future,
				'start_time'   => '09:00:00',
				'end_time'     => '10:00:00',
				'status'       => 'cancelled',
			)
		);

		$token = 'tok-' . wp_generate_password( 8, false );
		global $wpdb;
		$wpdb->update(
			$wpdb->prefix . 'bookings',
			array( 'magic_link_token' => $token ),
			array( 'id' => $bid ),
			array( '%s' ),
			array( '%d' )
		);

		$request = new WP_REST_Request( 'POST', '/' . $this->namespace . '/wizard/cancel' );
		$request->set_body_params(
			array(
				'booking_id' => $bid,
				'token'      => $token,
			)
		);

		$response = rest_get_server()->dispatch( $request );
		$this->assertEquals( 422, $response->get_status() );
		$this->assertSame( 'E2003', $response->as_error()->get_error_code() );
	}

	/**
	 * Too close to appointment returns within_cancellation_window.
	 */
	public function test_cancel_endpoint_rejects_within_policy_window() {
		global $wpdb;

		$wpdb->insert(
			$wpdb->prefix . 'bookings_settings',
			array(
				'setting_key'   => 'cancellation_window_hours',
				'setting_value' => '24',
				'setting_type'  => 'integer',
			),
			array( '%s', '%s', '%s' )
		);

		$staff    = $this->create_test_staff();
		$service  = $this->create_test_service();
		$customer = $this->create_test_customer();
		$this->link_staff_to_service( $staff, $service );

		$today = wp_date( 'Y-m-d' );
		$start = wp_date( 'H:i:s', time() + HOUR_IN_SECONDS );

		$bid = $this->create_test_booking(
			array(
				'staff_id'     => $staff,
				'service_id'   => $service,
				'customer_id'  => $customer,
				'booking_date' => $today,
				'start_time'   => $start,
				'end_time'     => wp_date( 'H:i:s', time() + 2 * HOUR_IN_SECONDS ),
				'status'       => 'confirmed',
			)
		);

		$token = 'tok-' . wp_generate_password( 8, false );
		$wpdb->update(
			$wpdb->prefix . 'bookings',
			array( 'magic_link_token' => $token ),
			array( 'id' => $bid ),
			array( '%s' ),
			array( '%d' )
		);

		$request = new WP_REST_Request( 'POST', '/' . $this->namespace . '/wizard/cancel' );
		$request->set_body_params(
			array(
				'booking_id' => $bid,
				'token'      => $token,
			)
		);

		$response = rest_get_server()->dispatch( $request );
		$this->assertEquals( 422, $response->get_status() );
		$err = $response->as_error();
		$this->assertSame( 'within_cancellation_window', $err->get_error_code() );
	}

	/**
	 * 11th request in one hour returns 429.
	 */
	public function test_cancel_endpoint_rate_limited_after_threshold() {
		$staff    = $this->create_test_staff();
		$service  = $this->create_test_service();
		$customer = $this->create_test_customer();
		$this->link_staff_to_service( $staff, $service );

		$future = gmdate( 'Y-m-d', strtotime( '+7 days' ) );
		$bid    = $this->create_test_booking(
			array(
				'staff_id'     => $staff,
				'service_id'   => $service,
				'customer_id'  => $customer,
				'booking_date' => $future,
				'start_time'   => '15:00:00',
				'end_time'     => '16:00:00',
				'status'       => 'confirmed',
			)
		);

		global $wpdb;
		$wpdb->update(
			$wpdb->prefix . 'bookings',
			array( 'magic_link_token' => 'real-token' ),
			array( 'id' => $bid ),
			array( '%s' ),
			array( '%d' )
		);

		$last_status = 0;
		for ( $i = 0; $i < 11; $i++ ) {
			$request = new WP_REST_Request( 'POST', '/' . $this->namespace . '/wizard/cancel' );
			$request->set_body_params(
				array(
					'booking_id' => $bid,
					'token'      => 'wrong-for-rate-limit',
				)
			);
			$response      = rest_get_server()->dispatch( $request );
			$last_status = $response->get_status();
		}

		$this->assertEquals( 429, $last_status );
	}

	/**
	 * Reschedule wrong token 403.
	 */
	public function test_reschedule_endpoint_requires_valid_token() {
		$staff    = $this->create_test_staff();
		$service  = $this->create_test_service();
		$customer = $this->create_test_customer();
		$this->link_staff_to_service( $staff, $service );

		$future = gmdate( 'Y-m-d', strtotime( '+8 days' ) );
		$bid    = $this->create_test_booking(
			array(
				'staff_id'     => $staff,
				'service_id'   => $service,
				'customer_id'  => $customer,
				'booking_date' => $future,
				'start_time'   => '10:00:00',
				'end_time'     => '11:00:00',
				'status'       => 'confirmed',
			)
		);

		global $wpdb;
		$wpdb->update(
			$wpdb->prefix . 'bookings',
			array( 'magic_link_token' => 'good' ),
			array( 'id' => $bid ),
			array( '%s' ),
			array( '%d' )
		);

		$new_date = gmdate( 'Y-m-d', strtotime( '+10 days' ) );
		$request  = new WP_REST_Request( 'POST', '/' . $this->namespace . '/wizard/reschedule' );
		$request->set_body_params(
			array(
				'booking_id' => $bid,
				'token'      => 'bad',
				'new_date'   => $new_date,
				'new_time'   => '14:00',
			)
		);

		$response = rest_get_server()->dispatch( $request );
		$this->assertEquals( 403, $response->get_status() );
	}

	/**
	 * Successful reschedule updates date/time/end_time.
	 */
	public function test_reschedule_endpoint_updates_booking_date_and_time() {
		$staff    = $this->create_test_staff();
		$service  = $this->create_test_service( array( 'duration' => 45 ) );
		$customer = $this->create_test_customer();
		$this->link_staff_to_service( $staff, $service );

		$future = gmdate( 'Y-m-d', strtotime( '+9 days' ) );
		$bid    = $this->create_test_booking(
			array(
				'staff_id'     => $staff,
				'service_id'   => $service,
				'customer_id'  => $customer,
				'booking_date' => $future,
				'start_time'   => '10:00:00',
				'end_time'     => '11:00:00',
				'duration'     => 45,
				'status'       => 'confirmed',
			)
		);

		$token = 'rs-' . wp_generate_password( 10, false );
		global $wpdb;
		$wpdb->update(
			$wpdb->prefix . 'bookings',
			array( 'magic_link_token' => $token ),
			array( 'id' => $bid ),
			array( '%s' ),
			array( '%d' )
		);

		$new_date = gmdate( 'Y-m-d', strtotime( '+12 days' ) );
		$request  = new WP_REST_Request( 'POST', '/' . $this->namespace . '/wizard/reschedule' );
		$request->set_body_params(
			array(
				'booking_id' => $bid,
				'token'      => $token,
				'new_date'   => $new_date,
				'new_time'   => '15:30',
			)
		);

		$response = rest_get_server()->dispatch( $request );
		$this->assertEquals( 200, $response->get_status() );

		$row = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT booking_date, start_time, end_time FROM {$wpdb->prefix}bookings WHERE id = %d",
				$bid
			),
			ARRAY_A
		);

		$this->assertSame( $new_date, $row['booking_date'] );
		$this->assertStringStartsWith( '15:30:00', $row['start_time'] );
		$this->assertNotEmpty( $row['end_time'] );
	}

	/**
	 * Slot conflict returns 409 E2001.
	 */
	public function test_reschedule_endpoint_rejects_unavailable_slot() {
		$staff    = $this->create_test_staff();
		$service  = $this->create_test_service();
		$customer = $this->create_test_customer();
		$this->link_staff_to_service( $staff, $service );

		$day = gmdate( 'Y-m-d', strtotime( '+14 days' ) );

		$this->create_test_booking(
			array(
				'staff_id'     => $staff,
				'service_id'   => $service,
				'customer_id'  => $customer,
				'booking_date' => $day,
				'start_time'   => '14:00:00',
				'end_time'     => '15:00:00',
				'status'       => 'confirmed',
			)
		);

		$bid_a = $this->create_test_booking(
			array(
				'staff_id'     => $staff,
				'service_id'   => $service,
				'customer_id'  => $customer,
				'booking_date' => $day,
				'start_time'   => '10:00:00',
				'end_time'     => '11:00:00',
				'status'       => 'confirmed',
			)
		);

		$token = 'conflict-' . wp_generate_password( 6, false );
		global $wpdb;
		$wpdb->update(
			$wpdb->prefix . 'bookings',
			array( 'magic_link_token' => $token ),
			array( 'id' => $bid_a ),
			array( '%s' ),
			array( '%d' )
		);

		$request = new WP_REST_Request( 'POST', '/' . $this->namespace . '/wizard/reschedule' );
		$request->set_body_params(
			array(
				'booking_id' => $bid_a,
				'token'      => $token,
				'new_date'   => $day,
				'new_time'   => '14:00',
			)
		);

		$response = rest_get_server()->dispatch( $request );
		$this->assertEquals( 409, $response->get_status() );
		$this->assertSame( 'E2001', $response->as_error()->get_error_code() );
	}

	/**
	 * Hook fires once on reschedule.
	 */
	public function test_reschedule_endpoint_fires_rescheduled_hook() {
		$staff    = $this->create_test_staff();
		$service  = $this->create_test_service();
		$customer = $this->create_test_customer();
		$this->link_staff_to_service( $staff, $service );

		$future = gmdate( 'Y-m-d', strtotime( '+15 days' ) );
		$bid    = $this->create_test_booking(
			array(
				'staff_id'     => $staff,
				'service_id'   => $service,
				'customer_id'  => $customer,
				'booking_date' => $future,
				'start_time'   => '10:00:00',
				'end_time'     => '11:00:00',
				'status'       => 'confirmed',
			)
		);

		$token = 'hook-' . wp_generate_password( 6, false );
		global $wpdb;
		$wpdb->update(
			$wpdb->prefix . 'bookings',
			array( 'magic_link_token' => $token ),
			array( 'id' => $bid ),
			array( '%s' ),
			array( '%d' )
		);

		$before = did_action( 'bookit_booking_rescheduled' );

		$new_date = gmdate( 'Y-m-d', strtotime( '+16 days' ) );
		$request  = new WP_REST_Request( 'POST', '/' . $this->namespace . '/wizard/reschedule' );
		$request->set_body_params(
			array(
				'booking_id' => $bid,
				'token'      => $token,
				'new_date'   => $new_date,
				'new_time'   => '16:00',
			)
		);

		rest_get_server()->dispatch( $request );

		$this->assertSame( $before + 1, did_action( 'bookit_booking_rescheduled' ) );
	}

	/**
	 * Shortcode without query args shows error.
	 */
	public function test_cancel_shortcode_renders_error_without_params() {
		$_GET = array();
		$out = do_shortcode( '[bookit_cancel_booking]' );
		$this->assertStringContainsString( 'bookit-error', $out );
	}

	/**
	 * Reschedule shortcode without query args shows error.
	 */
	public function test_reschedule_shortcode_renders_error_without_params() {
		$_GET = array();
		$out = do_shortcode( '[bookit_reschedule_booking]' );
		$this->assertStringContainsString( 'bookit-error', $out );
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
}
