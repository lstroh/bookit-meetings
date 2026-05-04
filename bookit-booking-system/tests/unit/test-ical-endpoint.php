<?php
/**
 * Tests for GET bookit/v1/wizard/ical (.ics download).
 *
 * @package    Bookit_Booking_System
 * @subpackage Tests
 */

/**
 * Exposes protected build_ical_content for unit tests.
 */
class Bookit_Wizard_API_Ical_TestDouble extends Bookit_Wizard_API {

	/**
	 * @param int    $booking_id Booking ID.
	 * @param string $token      Magic link token.
	 * @return string|WP_Error
	 */
	public function expose_build_ical_content( int $booking_id, string $token ) {
		return $this->build_ical_content( $booking_id, $token );
	}
}

/**
 * @covers Bookit_Wizard_API::get_ical
 * @covers Bookit_Wizard_API::build_ical_content
 * @covers Bookit_Wizard_API::register_routes
 */
class Test_Ical_Endpoint extends WP_UnitTestCase {

	/**
	 * REST namespace.
	 *
	 * @var string
	 */
	private $namespace = 'bookit/v1';

	/**
	 * @var Bookit_Wizard_API_Ical_TestDouble
	 */
	private $api_double;

	/**
	 * Set up each test.
	 */
	public function setUp(): void {
		parent::setUp();

		bookit_test_truncate_tables(
			array(
				'bookings_audit_log',
				'bookings_settings',
				'bookings',
				'bookings_staff_services',
				'bookings_services',
				'bookings_staff',
				'bookings_customers',
			)
		);

		$this->api_double = new Bookit_Wizard_API_Ical_TestDouble();

		do_action( 'rest_api_init' );
	}

	/**
	 * GET /wizard/ical without token returns 400 (missing required arg).
	 */
	public function test_ical_endpoint_rejects_missing_token() {
		$request = new WP_REST_Request( 'GET', '/' . $this->namespace . '/wizard/ical' );
		$request->set_query_params(
			array(
				'booking_id' => 1,
			)
		);

		$response = rest_get_server()->dispatch( $request );
		$this->assertEquals( 400, $response->get_status() );
	}

	/**
	 * Wrong magic link token returns 403.
	 */
	public function test_ical_endpoint_rejects_wrong_token() {
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
			array( 'magic_link_token' => 'good-token-ical' ),
			array( 'id' => $bid ),
			array( '%s' ),
			array( '%d' )
		);

		$request = new WP_REST_Request( 'GET', '/' . $this->namespace . '/wizard/ical' );
		$request->set_query_params(
			array(
				'booking_id' => $bid,
				'token'      => 'wrong-token',
			)
		);

		$response = rest_get_server()->dispatch( $request );
		$this->assertEquals( 403, $response->get_status() );
		$data = $response->get_data();
		$this->assertArrayHasKey( 'code', $data );
		$this->assertSame( 'invalid_token', $data['code'] );
	}

	/**
	 * Non-existent booking returns 404.
	 */
	public function test_ical_endpoint_rejects_invalid_booking_id() {
		$request = new WP_REST_Request( 'GET', '/' . $this->namespace . '/wizard/ical' );
		$request->set_query_params(
			array(
				'booking_id' => 99999,
				'token'      => 'any-token-value',
			)
		);

		$response = rest_get_server()->dispatch( $request );
		$this->assertEquals( 404, $response->get_status() );
		$data = $response->get_data();
		$this->assertArrayHasKey( 'code', $data );
		$this->assertSame( 'E2002', $data['code'] );
	}

	/**
	 * build_ical_content returns VCALENDAR body with required properties.
	 */
	public function test_ical_endpoint_returns_ics_content_type() {
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
			array(
				'magic_link_token'  => 'ical-test-token',
				'booking_reference' => 'BK2604-ICAL',
			),
			array( 'id' => $bid ),
			array( '%s', '%s' ),
			array( '%d' )
		);

		$ics = $this->api_double->expose_build_ical_content( $bid, 'ical-test-token' );
		$this->assertIsString( $ics );
		$this->assertStringContainsString( 'BEGIN:VCALENDAR', $ics );
		$this->assertStringContainsString( 'BEGIN:VEVENT', $ics );
		$this->assertStringContainsString( 'DTSTART', $ics );
		$this->assertStringContainsString( 'DTEND', $ics );
		$this->assertStringContainsString( 'SUMMARY:', $ics );
		$this->assertStringContainsString( 'UID:', $ics );
		$this->assertStringContainsString( "\r\n", $ics );
	}

	/**
	 * ICS contains known service name and CRLF line endings.
	 */
	public function test_ical_content_contains_required_fields() {
		global $wpdb;

		$wpdb->insert(
			$wpdb->prefix . 'bookings_settings',
			array(
				'setting_key'   => 'business_name',
				'setting_value' => 'Ical Test Salon',
				'setting_type'  => 'string',
			),
			array( '%s', '%s', '%s' )
		);
		$wpdb->insert(
			$wpdb->prefix . 'bookings_settings',
			array(
				'setting_key'   => 'business_address',
				'setting_value' => '123 Calendar Street',
				'setting_type'  => 'string',
			),
			array( '%s', '%s', '%s' )
		);

		$staff    = $this->create_test_staff(
			array(
				'first_name' => 'Ada',
				'last_name'  => 'Lovelace',
			)
		);
		$service  = $this->create_test_service(
			array(
				'name' => 'Deep Tissue Ical Test',
			)
		);
		$customer = $this->create_test_customer();
		$this->link_staff_to_service( $staff, $service );

		$booking_date = '2026-07-20';
		$bid          = $this->create_test_booking(
			array(
				'staff_id'          => $staff,
				'service_id'        => $service,
				'customer_id'       => $customer,
				'booking_date'      => $booking_date,
				'start_time'        => '14:30:00',
				'end_time'          => '15:30:00',
				'status'            => 'confirmed',
				'booking_reference' => 'BK-ICAL-001',
			)
		);

		$wpdb->update(
			$wpdb->prefix . 'bookings',
			array( 'magic_link_token' => 'token-for-fields-test' ),
			array( 'id' => $bid ),
			array( '%s' ),
			array( '%d' )
		);

		$ics = $this->api_double->expose_build_ical_content( $bid, 'token-for-fields-test' );

		$this->assertStringContainsString( 'BEGIN:VCALENDAR', $ics );
		$this->assertStringContainsString( 'BEGIN:VEVENT', $ics );
		$this->assertStringContainsString( 'DTSTART', $ics );
		$this->assertStringContainsString( 'DTEND', $ics );
		$this->assertStringContainsString( 'SUMMARY:', $ics );
		$this->assertStringContainsString( 'Deep Tissue Ical Test', $ics );
		$this->assertStringContainsString( 'UID:', $ics );
		$this->assertStringContainsString( '123 Calendar Street', $ics );
		$this->assertMatchesRegularExpression( '/\r\n/', $ics );
	}

	/**
	 * DESCRIPTION includes cancel page URL when magic link token is set.
	 */
	public function test_ical_description_contains_cancel_url() {
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
			array( 'magic_link_token' => 'token-cancel-url-test' ),
			array( 'id' => $bid ),
			array( '%s' ),
			array( '%d' )
		);

		$ics = $this->api_double->expose_build_ical_content( $bid, 'token-cancel-url-test' );
		$this->assertIsString( $ics );
		$this->assertStringContainsString( 'DESCRIPTION:', $ics );
		$this->assertStringContainsString( 'bookit-cancel', $ics );
	}

	/**
	 * DESCRIPTION includes reschedule page URL when magic link token is set.
	 */
	public function test_ical_description_contains_reschedule_url() {
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
			array( 'magic_link_token' => 'token-reschedule-url-test' ),
			array( 'id' => $bid ),
			array( '%s' ),
			array( '%d' )
		);

		$ics = $this->api_double->expose_build_ical_content( $bid, 'token-reschedule-url-test' );
		$this->assertIsString( $ics );
		$this->assertStringContainsString( 'DESCRIPTION:', $ics );
		$this->assertStringContainsString( 'bookit-reschedule', $ics );
	}

	/**
	 * Soft-deleted booking is not returned (404).
	 */
	public function test_ical_endpoint_rejects_deleted_booking() {
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
			array(
				'magic_link_token' => 'token-deleted-test',
				'deleted_at'       => gmdate( 'Y-m-d H:i:s', strtotime( '-1 day' ) ),
			),
			array( 'id' => $bid ),
			array( '%s', '%s' ),
			array( '%d' )
		);

		$request = new WP_REST_Request( 'GET', '/' . $this->namespace . '/wizard/ical' );
		$request->set_query_params(
			array(
				'booking_id' => $bid,
				'token'      => 'token-deleted-test',
			)
		);

		$response = rest_get_server()->dispatch( $request );
		$this->assertEquals( 404, $response->get_status() );
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
