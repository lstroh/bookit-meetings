<?php
/**
 * Tests for use-package redemption flow.
 *
 * @package    Bookit_Booking_System
 * @subpackage Tests
 */

/**
 * Test package redemption from booking wizard Step 5.
 */
class Test_Use_Package_Redemption extends WP_UnitTestCase {

	/**
	 * Test service ID.
	 *
	 * @var int
	 */
	private $service_id = 0;

	/**
	 * Test staff ID.
	 *
	 * @var int
	 */
	private $staff_id = 0;

	/**
	 * Created staff IDs for cleanup.
	 *
	 * @var int[]
	 */
	private $created_staff_ids = array();

	/**
	 * Payment processor instance.
	 *
	 * @var Booking_System_Payment_Processor
	 */
	private $payment_processor;

	/**
	 * Set up each test.
	 */
	public function setUp(): void {
		parent::setUp();

		$this->ensure_package_types_table_exists();
		$this->ensure_customer_packages_table_exists();
		$this->ensure_package_redemptions_table_exists();
		$this->ensure_bookings_customer_package_column_exists();

		bookit_test_truncate_tables(
			array(
				'bookings_package_redemptions',
				'bookings_customer_packages',
				'bookings_package_types',
				'bookings_customers',
				'bookings',
				'bookings_audit_log',
			)
		);

		$_SESSION = array();
		$this->set_packages_enabled( '1' );

		$this->service_id = $this->insert_test_service();
		$this->staff_id   = $this->create_test_staff( array( 'role' => 'staff' ) );

		require_once dirname( __DIR__, 2 ) . '/includes/payment/class-payment-processor.php';
		require_once dirname( __DIR__, 2 ) . '/includes/booking/class-booking-creator.php';
		$this->payment_processor = new Booking_System_Payment_Processor();

		do_action( 'rest_api_init' );
	}

	/**
	 * Tear down each test.
	 */
	public function tearDown(): void {
		global $wpdb;

		bookit_test_truncate_tables(
			array(
				'bookings_package_redemptions',
				'bookings_customer_packages',
				'bookings_package_types',
				'bookings_customers',
				'bookings',
				'bookings_audit_log',
			)
		);

		foreach ( array_unique( $this->created_staff_ids ) as $staff_id ) {
			$wpdb->delete( $wpdb->prefix . 'bookings_staff', array( 'id' => (int) $staff_id ), array( '%d' ) );
		}

		if ( $this->service_id > 0 ) {
			$wpdb->delete( $wpdb->prefix . 'bookings_services', array( 'id' => (int) $this->service_id ), array( '%d' ) );
		}

		$_SESSION = array();
		parent::tearDown();
	}

	public function test_booking_creator_writes_customer_package_id() {
		global $wpdb;

		$customer_id = $this->insert_customer();
		$booking_creator = new Booking_System_Booking_Creator();
		$booking_id = $booking_creator->create_booking(
			$this->build_booking_data(
				array(
					'customer_email'      => $this->get_customer_email( $customer_id ),
					'customer_package_id' => 123,
				)
			)
		);

		$this->assertIsInt( $booking_id );

		$value = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT customer_package_id FROM {$wpdb->prefix}bookings WHERE id = %d",
				$booking_id
			)
		);
		$this->assertSame( 123, (int) $value );
	}

	public function test_booking_creator_null_when_no_package() {
		global $wpdb;

		$customer_id = $this->insert_customer();
		$booking_creator = new Booking_System_Booking_Creator();
		$booking_id = $booking_creator->create_booking(
			$this->build_booking_data(
				array(
					'customer_email' => $this->get_customer_email( $customer_id ),
				)
			)
		);

		$this->assertIsInt( $booking_id );

		$value = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT customer_package_id FROM {$wpdb->prefix}bookings WHERE id = %d",
				$booking_id
			)
		);
		$this->assertNull( $value );
	}

	public function test_use_package_creates_booking() {
		$customer_id        = $this->insert_customer();
		$package_type_id    = $this->insert_package_type();
		$customer_package_id = $this->insert_customer_package(
			array(
				'customer_id'     => $customer_id,
				'package_type_id' => $package_type_id,
			)
		);

		$result = $this->invoke_process_use_package(
			$this->build_session_data(
				$this->get_customer_email( $customer_id ),
				$customer_package_id
			)
		);

		$this->assertIsArray( $result );
		$this->assertTrue( $result['success'] );
		$this->assertGreaterThan( 0, $result['booking_id'] );
	}

	public function test_use_package_decrements_sessions_remaining() {
		global $wpdb;

		$customer_id        = $this->insert_customer();
		$package_type_id    = $this->insert_package_type();
		$customer_package_id = $this->insert_customer_package(
			array(
				'customer_id'        => $customer_id,
				'package_type_id'    => $package_type_id,
				'sessions_remaining' => 3,
			)
		);

		$this->invoke_process_use_package(
			$this->build_session_data(
				$this->get_customer_email( $customer_id ),
				$customer_package_id
			)
		);

		$sessions_remaining = (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT sessions_remaining FROM {$wpdb->prefix}bookings_customer_packages WHERE id = %d",
				$customer_package_id
			)
		);
		$this->assertSame( 2, $sessions_remaining );
	}

	public function test_use_package_sets_status_exhausted_when_last_session() {
		global $wpdb;

		$customer_id        = $this->insert_customer();
		$package_type_id    = $this->insert_package_type();
		$customer_package_id = $this->insert_customer_package(
			array(
				'customer_id'        => $customer_id,
				'package_type_id'    => $package_type_id,
				'sessions_remaining' => 1,
				'sessions_total'     => 1,
			)
		);

		$this->invoke_process_use_package(
			$this->build_session_data(
				$this->get_customer_email( $customer_id ),
				$customer_package_id
			)
		);

		$status = (string) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT status FROM {$wpdb->prefix}bookings_customer_packages WHERE id = %d",
				$customer_package_id
			)
		);
		$this->assertSame( 'exhausted', $status );
	}

	public function test_use_package_creates_redemption_record() {
		global $wpdb;

		$customer_id        = $this->insert_customer();
		$package_type_id    = $this->insert_package_type();
		$customer_package_id = $this->insert_customer_package(
			array(
				'customer_id'     => $customer_id,
				'package_type_id' => $package_type_id,
			)
		);

		$result = $this->invoke_process_use_package(
			$this->build_session_data(
				$this->get_customer_email( $customer_id ),
				$customer_package_id
			)
		);

		$row = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT booking_id, customer_package_id FROM {$wpdb->prefix}bookings_package_redemptions WHERE booking_id = %d LIMIT 1",
				(int) $result['booking_id']
			),
			ARRAY_A
		);

		$this->assertNotEmpty( $row );
		$this->assertSame( (int) $result['booking_id'], (int) $row['booking_id'] );
		$this->assertSame( $customer_package_id, (int) $row['customer_package_id'] );
	}

	public function test_use_package_fires_audit_log() {
		global $wpdb;

		$customer_id        = $this->insert_customer();
		$package_type_id    = $this->insert_package_type();
		$customer_package_id = $this->insert_customer_package(
			array(
				'customer_id'     => $customer_id,
				'package_type_id' => $package_type_id,
			)
		);

		$this->invoke_process_use_package(
			$this->build_session_data(
				$this->get_customer_email( $customer_id ),
				$customer_package_id
			)
		);

		$audit = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT action FROM {$wpdb->prefix}bookings_audit_log WHERE action = %s ORDER BY id DESC LIMIT 1",
				'customer_package.redeemed'
			)
		);
		$this->assertSame( 'customer_package.redeemed', $audit );
	}

	public function test_use_package_returns_error_for_exhausted_package() {
		$customer_id        = $this->insert_customer();
		$package_type_id    = $this->insert_package_type();
		$customer_package_id = $this->insert_customer_package(
			array(
				'customer_id'        => $customer_id,
				'package_type_id'    => $package_type_id,
				'status'             => 'exhausted',
				'sessions_remaining' => 0,
			)
		);

		$result = $this->invoke_process_use_package(
			$this->build_session_data(
				$this->get_customer_email( $customer_id ),
				$customer_package_id
			)
		);

		$this->assertInstanceOf( 'WP_Error', $result );
		$this->assertSame( 'E5002', $result->get_error_code() );
	}

	public function test_use_package_returns_error_for_expired_package() {
		$customer_id        = $this->insert_customer();
		$package_type_id    = $this->insert_package_type();
		$customer_package_id = $this->insert_customer_package(
			array(
				'customer_id'     => $customer_id,
				'package_type_id' => $package_type_id,
				'status'          => 'expired',
				'expires_at'      => gmdate( 'Y-m-d H:i:s', strtotime( '-1 day' ) ),
			)
		);

		$result = $this->invoke_process_use_package(
			$this->build_session_data(
				$this->get_customer_email( $customer_id ),
				$customer_package_id
			)
		);

		$this->assertInstanceOf( 'WP_Error', $result );
		$this->assertSame( 'E5003', $result->get_error_code() );
	}

	public function test_use_package_returns_error_for_service_mismatch() {
		$customer_id     = $this->insert_customer();
		$package_type_id = $this->insert_package_type(
			array(
				'applicable_service_ids' => wp_json_encode( array( 99 ) ),
			)
		);
		$customer_package_id = $this->insert_customer_package(
			array(
				'customer_id'     => $customer_id,
				'package_type_id' => $package_type_id,
			)
		);

		$result = $this->invoke_process_use_package(
			$this->build_session_data(
				$this->get_customer_email( $customer_id ),
				$customer_package_id
			)
		);

		$this->assertInstanceOf( 'WP_Error', $result );
		$this->assertSame( 'E5004', $result->get_error_code() );
	}

	public function test_use_package_returns_error_for_missing_package() {
		$customer_id = $this->insert_customer();

		$result = $this->invoke_process_use_package(
			$this->build_session_data(
				$this->get_customer_email( $customer_id ),
				999999
			)
		);

		$this->assertInstanceOf( 'WP_Error', $result );
		$this->assertSame( 'E5001', $result->get_error_code() );
	}

	public function test_use_package_returns_error_for_zero_sessions_remaining() {
		$customer_id        = $this->insert_customer();
		$package_type_id    = $this->insert_package_type();
		$customer_package_id = $this->insert_customer_package(
			array(
				'customer_id'        => $customer_id,
				'package_type_id'    => $package_type_id,
				'status'             => 'active',
				'sessions_remaining' => 0,
			)
		);

		$result = $this->invoke_process_use_package(
			$this->build_session_data(
				$this->get_customer_email( $customer_id ),
				$customer_package_id
			)
		);

		$this->assertInstanceOf( 'WP_Error', $result );
		$this->assertSame( 'E5002', $result->get_error_code() );
	}

	public function test_my_packages_endpoint_is_public() {
		$customer_id     = $this->insert_customer( array( 'email' => 'public@test.com' ) );
		$package_type_id = $this->insert_package_type();
		$this->insert_customer_package(
			array(
				'customer_id'     => $customer_id,
				'package_type_id' => $package_type_id,
			)
		);

		$_SESSION = array();

		$request = new WP_REST_Request( 'GET', '/bookit/v1/wizard/my-packages' );
		$request->set_param( 'customer_email', 'public@test.com' );
		$response = rest_get_server()->dispatch( $request );

		$this->assertSame( 200, $response->get_status() );
	}

	public function test_my_packages_requires_valid_email() {
		$request_missing = new WP_REST_Request( 'GET', '/bookit/v1/wizard/my-packages' );
		$response_missing = rest_get_server()->dispatch( $request_missing );
		$this->assertSame( 400, $response_missing->get_status() );

		$request_invalid = new WP_REST_Request( 'GET', '/bookit/v1/wizard/my-packages' );
		$request_invalid->set_param( 'customer_email', 'not-an-email' );
		$response_invalid = rest_get_server()->dispatch( $request_invalid );
		$this->assertSame( 400, $response_invalid->get_status() );
	}

	public function test_my_packages_returns_empty_for_unknown_customer() {
		$request = new WP_REST_Request( 'GET', '/bookit/v1/wizard/my-packages' );
		$request->set_param( 'customer_email', 'unknown@example.com' );
		$response = rest_get_server()->dispatch( $request );

		$this->assertSame( 200, $response->get_status() );
		$this->assertSame( array(), $response->get_data() );
	}

	public function test_my_packages_returns_empty_when_packages_disabled() {
		$this->set_packages_enabled( '0' );

		$customer_id     = $this->insert_customer( array( 'email' => 'disabled-packages@test.com' ) );
		$package_type_id = $this->insert_package_type();
		$this->insert_customer_package(
			array(
				'customer_id'     => $customer_id,
				'package_type_id' => $package_type_id,
				'status'          => 'active',
			)
		);

		$request = new WP_REST_Request( 'GET', '/bookit/v1/wizard/my-packages' );
		$request->set_param( 'customer_email', 'disabled-packages@test.com' );
		$response = rest_get_server()->dispatch( $request );

		$this->set_packages_enabled( '1' );

		$this->assertSame( 200, $response->get_status() );
		$this->assertSame( array(), $response->get_data() );
	}

	public function test_my_packages_returns_active_packages_only() {
		$customer_id     = $this->insert_customer( array( 'email' => 'active-only@test.com' ) );
		$package_type_id = $this->insert_package_type();

		$this->insert_customer_package(
			array(
				'customer_id'     => $customer_id,
				'package_type_id' => $package_type_id,
				'status'          => 'active',
			)
		);
		$this->insert_customer_package(
			array(
				'customer_id'        => $customer_id,
				'package_type_id'    => $package_type_id,
				'status'             => 'cancelled',
				'sessions_remaining' => 5,
			)
		);
		$this->insert_customer_package(
			array(
				'customer_id'        => $customer_id,
				'package_type_id'    => $package_type_id,
				'status'             => 'exhausted',
				'sessions_remaining' => 0,
			)
		);

		$request = new WP_REST_Request( 'GET', '/bookit/v1/wizard/my-packages' );
		$request->set_param( 'customer_email', 'active-only@test.com' );
		$response = rest_get_server()->dispatch( $request );
		$data = $response->get_data();

		$this->assertSame( 200, $response->get_status() );
		$this->assertCount( 1, $data );
	}

	public function test_my_packages_excludes_expired_packages() {
		$customer_id     = $this->insert_customer( array( 'email' => 'expiry@test.com' ) );
		$package_type_id = $this->insert_package_type();

		$this->insert_customer_package(
			array(
				'customer_id'     => $customer_id,
				'package_type_id' => $package_type_id,
				'expires_at'      => gmdate( 'Y-m-d H:i:s', strtotime( '-1 day' ) ),
			)
		);

		$request = new WP_REST_Request( 'GET', '/bookit/v1/wizard/my-packages' );
		$request->set_param( 'customer_email', 'expiry@test.com' );
		$response = rest_get_server()->dispatch( $request );

		$this->assertSame( 200, $response->get_status() );
		$this->assertSame( array(), $response->get_data() );
	}

	public function test_my_packages_filters_by_service_id() {
		$customer_id = $this->insert_customer( array( 'email' => 'service-filter@test.com' ) );
		$type_mismatch = $this->insert_package_type(
			array(
				'applicable_service_ids' => wp_json_encode( array( 5 ) ),
			)
		);
		$type_match = $this->insert_package_type(
			array(
				'applicable_service_ids' => wp_json_encode( array( 1 ) ),
			)
		);

		$this->insert_customer_package(
			array(
				'customer_id'     => $customer_id,
				'package_type_id' => $type_mismatch,
			)
		);
		$this->insert_customer_package(
			array(
				'customer_id'     => $customer_id,
				'package_type_id' => $type_match,
			)
		);

		$request = new WP_REST_Request( 'GET', '/bookit/v1/wizard/my-packages' );
		$request->set_param( 'customer_email', 'service-filter@test.com' );
		$request->set_param( 'service_id', 1 );
		$response = rest_get_server()->dispatch( $request );
		$data = $response->get_data();

		$this->assertSame( 200, $response->get_status() );
		$this->assertCount( 1, $data );
	}

	public function test_package_redemptions_returns_empty_for_unknown_email() {
		$request = new WP_REST_Request( 'GET', '/bookit/v1/wizard/package-redemptions' );
		$request->set_param( 'customer_email', 'missing-customer@test.com' );
		$request->set_param( 'customer_package_id', 1 );
		$response = rest_get_server()->dispatch( $request );

		$this->assertSame( 200, $response->get_status() );
		$this->assertSame( array(), $response->get_data() );
	}

	public function test_package_redemptions_returns_403_if_package_belongs_to_different_customer() {
		$customer_a_id     = $this->insert_customer( array( 'email' => 'owner@test.com' ) );
		$customer_b_id     = $this->insert_customer( array( 'email' => 'other@test.com' ) );
		$package_type_id   = $this->insert_package_type();
		$customer_package_id = $this->insert_customer_package(
			array(
				'customer_id'     => $customer_a_id,
				'package_type_id' => $package_type_id,
			)
		);

		$this->assertGreaterThan( 0, $customer_b_id );

		$request = new WP_REST_Request( 'GET', '/bookit/v1/wizard/package-redemptions' );
		$request->set_param( 'customer_email', 'other@test.com' );
		$request->set_param( 'customer_package_id', $customer_package_id );
		$response = rest_get_server()->dispatch( $request );

		$this->assertSame( 403, $response->get_status() );
	}

	public function test_package_redemptions_returns_correct_shape() {
		$customer_id        = $this->insert_customer( array( 'email' => 'shape@test.com' ) );
		$package_type_id    = $this->insert_package_type();
		$customer_package_id = $this->insert_customer_package(
			array(
				'customer_id'     => $customer_id,
				'package_type_id' => $package_type_id,
			)
		);
		$booking_id = $this->insert_booking(
			array(
				'customer_id' => $customer_id,
			)
		);
		$this->insert_redemption(
			array(
				'customer_package_id' => $customer_package_id,
				'booking_id'          => $booking_id,
			)
		);

		$request = new WP_REST_Request( 'GET', '/bookit/v1/wizard/package-redemptions' );
		$request->set_param( 'customer_email', 'shape@test.com' );
		$request->set_param( 'customer_package_id', $customer_package_id );
		$response = rest_get_server()->dispatch( $request );
		$data     = $response->get_data();

		$this->assertSame( 200, $response->get_status() );
		$this->assertCount( 1, $data );
		$this->assertArrayHasKey( 'redeemed_at', $data[0] );
		$this->assertArrayHasKey( 'booking_date', $data[0] );
		$this->assertArrayHasKey( 'service_name', $data[0] );
		$this->assertArrayHasKey( 'staff_name', $data[0] );
	}

	public function test_package_redemptions_respects_packages_enabled_gate() {
		$this->set_packages_enabled( '0' );

		$customer_id        = $this->insert_customer( array( 'email' => 'disabled-redemptions@test.com' ) );
		$package_type_id    = $this->insert_package_type();
		$customer_package_id = $this->insert_customer_package(
			array(
				'customer_id'     => $customer_id,
				'package_type_id' => $package_type_id,
			)
		);
		$booking_id = $this->insert_booking(
			array(
				'customer_id' => $customer_id,
			)
		);
		$this->insert_redemption(
			array(
				'customer_package_id' => $customer_package_id,
				'booking_id'          => $booking_id,
			)
		);

		$request = new WP_REST_Request( 'GET', '/bookit/v1/wizard/package-redemptions' );
		$request->set_param( 'customer_email', 'disabled-redemptions@test.com' );
		$request->set_param( 'customer_package_id', $customer_package_id );
		$response = rest_get_server()->dispatch( $request );

		$this->set_packages_enabled( '1' );

		$this->assertSame( 200, $response->get_status() );
		$this->assertSame( array(), $response->get_data() );
	}

	public function test_package_redemptions_returns_at_most_10_results() {
		$customer_id        = $this->insert_customer( array( 'email' => 'limit-redemptions@test.com' ) );
		$package_type_id    = $this->insert_package_type();
		$customer_package_id = $this->insert_customer_package(
			array(
				'customer_id'        => $customer_id,
				'package_type_id'    => $package_type_id,
				'sessions_total'     => 20,
				'sessions_remaining' => 20,
			)
		);

		for ( $i = 0; $i < 12; $i++ ) {
			$booking_id = $this->insert_booking(
				array(
					'customer_id' => $customer_id,
					'booking_date' => gmdate( 'Y-m-d', strtotime( '+' . ( 7 + $i ) . ' days' ) ),
					'start_time'  => '10:00:00',
					'end_time'    => '11:00:00',
				)
			);
			$this->insert_redemption(
				array(
					'customer_package_id' => $customer_package_id,
					'booking_id'          => $booking_id,
					'redeemed_at'         => gmdate( 'Y-m-d H:i:s', strtotime( "+{$i} minutes" ) ),
				)
			);
		}

		$request = new WP_REST_Request( 'GET', '/bookit/v1/wizard/package-redemptions' );
		$request->set_param( 'customer_email', 'limit-redemptions@test.com' );
		$request->set_param( 'customer_package_id', $customer_package_id );
		$response = rest_get_server()->dispatch( $request );
		$data     = $response->get_data();

		$this->assertSame( 200, $response->get_status() );
		$this->assertCount( 10, $data );
	}

	/**
	 * Build standard booking data.
	 *
	 * @param array $overrides Data overrides.
	 * @return array
	 */
	private function build_booking_data( $overrides = array() ) {
		$defaults = array(
			'service_id'          => $this->service_id,
			'staff_id'            => $this->staff_id,
			'booking_date'        => wp_date( 'Y-m-d', strtotime( '+30 days' ), wp_timezone() ),
			'booking_time'        => '10:00:00',
			'customer_first_name' => 'Jane',
			'customer_last_name'  => 'Doe',
			'customer_email'      => 'booking-data@test.com',
			'customer_phone'      => '07700900111',
			'special_requests'    => '',
			'payment_method'      => 'stripe',
			'payment_intent_id'   => null,
			'stripe_session_id'   => null,
			'amount_paid'         => 0,
		);

		return wp_parse_args( $overrides, $defaults );
	}

	/**
	 * Build session data for process_use_package.
	 *
	 * @param string $email Customer email.
	 * @param int    $customer_package_id Customer package ID.
	 * @return array
	 */
	private function build_session_data( $email, $customer_package_id ) {
		return array(
			'service_id'                => $this->service_id,
			'staff_id'                  => $this->staff_id,
			'date'                      => wp_date( 'Y-m-d', strtotime( '+30 days' ), wp_timezone() ),
			'time'                      => '10:00:00',
			'customer_first_name'       => 'Jane',
			'customer_last_name'        => 'Doe',
			'customer_email'            => $email,
			'customer_phone'            => '07700900111',
			'customer_special_requests' => '',
			'cooling_off_waiver'        => 1,
			'customer_package_id'       => (int) $customer_package_id,
		);
	}

	/**
	 * Invoke private process_use_package() via reflection.
	 *
	 * @param array $session_data Session data payload.
	 * @return array|WP_Error
	 */
	private function invoke_process_use_package( $session_data ) {
		$method = new ReflectionMethod( 'Booking_System_Payment_Processor', 'process_use_package' );
		$method->setAccessible( true );
		return $method->invoke( $this->payment_processor, $session_data );
	}

	/**
	 * Insert package type test row.
	 *
	 * @param array $overrides Field overrides.
	 * @return int
	 */
	private function insert_package_type( $overrides = array() ) {
		global $wpdb;

		$defaults = array(
			'name'                   => 'Default Package Type',
			'description'            => 'Default description',
			'sessions_count'         => 10,
			'price_mode'             => 'fixed',
			'fixed_price'            => 120.00,
			'discount_percentage'    => null,
			'expiry_enabled'         => 0,
			'expiry_days'            => null,
			'applicable_service_ids' => null,
			'is_active'              => 1,
			'created_at'             => current_time( 'mysql' ),
			'updated_at'             => current_time( 'mysql' ),
		);

		$data = wp_parse_args( $overrides, $defaults );

		$wpdb->insert(
			$wpdb->prefix . 'bookings_package_types',
			$data,
			array( '%s', '%s', '%d', '%s', '%f', '%f', '%d', '%d', '%s', '%d', '%s', '%s' )
		);

		return (int) $wpdb->insert_id;
	}

	/**
	 * Insert customer test row.
	 *
	 * @param array $overrides Field overrides.
	 * @return int
	 */
	private function insert_customer( $overrides = array() ) {
		global $wpdb;

		$defaults = array(
			'email'      => 'customer-' . wp_generate_password( 6, false ) . '@test.com',
			'first_name' => 'Test',
			'last_name'  => 'Customer',
			'phone'      => '07700900000',
			'created_at' => current_time( 'mysql' ),
			'updated_at' => current_time( 'mysql' ),
		);

		$data = wp_parse_args( $overrides, $defaults );

		$wpdb->insert(
			$wpdb->prefix . 'bookings_customers',
			$data,
			array( '%s', '%s', '%s', '%s', '%s', '%s' )
		);

		return (int) $wpdb->insert_id;
	}

	/**
	 * Insert customer package test row.
	 *
	 * @param array $overrides Field overrides.
	 * @return int
	 */
	private function insert_customer_package( $overrides = array() ) {
		global $wpdb;

		$defaults = array(
			'customer_id'        => 0,
			'package_type_id'    => 0,
			'sessions_total'     => 10,
			'sessions_remaining' => 10,
			'purchase_price'     => 120.00,
			'purchased_at'       => current_time( 'mysql' ),
			'expires_at'         => null,
			'status'             => 'active',
			'payment_method'     => 'manual',
			'payment_reference'  => null,
			'notes'              => null,
			'created_at'         => current_time( 'mysql' ),
			'updated_at'         => current_time( 'mysql' ),
		);

		$data = wp_parse_args( $overrides, $defaults );

		$wpdb->insert(
			$wpdb->prefix . 'bookings_customer_packages',
			$data,
			array( '%d', '%d', '%d', '%d', '%f', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s' )
		);

		return (int) $wpdb->insert_id;
	}

	/**
	 * Insert booking test row.
	 *
	 * @param array $overrides Field overrides.
	 * @return int
	 */
	private function insert_booking( $overrides = array() ) {
		global $wpdb;

		$defaults = array(
			'customer_id'         => 1,
			'service_id'          => $this->service_id,
			'staff_id'            => $this->staff_id,
			'booking_date'        => date( 'Y-m-d', strtotime( '+7 days' ) ),
			'start_time'          => '10:00:00',
			'end_time'            => '11:00:00',
			'duration'            => 60,
			'status'              => 'confirmed',
			'total_price'         => 50.00,
			'deposit_amount'      => 0,
			'deposit_paid'        => 0,
			'balance_due'         => 50.00,
			'payment_method'      => 'pay_on_arrival',
			'customer_package_id' => null,
			'booking_reference'   => 'REF-' . wp_generate_password( 6, false ),
			'created_at'          => current_time( 'mysql' ),
			'updated_at'          => current_time( 'mysql' ),
		);

		$data = array_merge( $defaults, $overrides );
		$wpdb->insert( $wpdb->prefix . 'bookings', $data );

		return (int) $wpdb->insert_id;
	}

	/**
	 * Insert redemption test row.
	 *
	 * @param array $overrides Field overrides.
	 * @return int
	 */
	private function insert_redemption( $overrides = array() ) {
		global $wpdb;

		$defaults = array(
			'customer_package_id' => 0,
			'booking_id'          => 0,
			'redeemed_at'         => current_time( 'mysql' ),
			'redeemed_by'         => 0,
			'notes'               => null,
			'created_at'          => current_time( 'mysql' ),
		);

		$data = array_merge( $defaults, $overrides );
		$wpdb->insert( $wpdb->prefix . 'bookings_package_redemptions', $data );

		return (int) $wpdb->insert_id;
	}

	/**
	 * Simulate dashboard login via session.
	 *
	 * @param int    $staff_id Staff ID.
	 * @param string $role Role value.
	 * @return void
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
	 * @param array $args Optional overrides.
	 * @return int
	 */
	private function create_test_staff( $args = array() ) {
		global $wpdb;

		$defaults = array(
			'email'              => 'staff-' . wp_generate_password( 6, false ) . '@test.com',
			'password_hash'      => password_hash( 'password123', PASSWORD_BCRYPT ),
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

		$staff_id                  = (int) $wpdb->insert_id;
		$this->created_staff_ids[] = $staff_id;

		return $staff_id;
	}

	/**
	 * Insert test service.
	 *
	 * @return int
	 */
	private function insert_test_service() {
		global $wpdb;

		$wpdb->insert(
			$wpdb->prefix . 'bookings_services',
			array(
				'name'           => 'Service For Package Tests',
				'duration'       => 60,
				'price'          => 75.00,
				'deposit_type'   => 'none',
				'deposit_amount' => 0,
				'is_active'      => 1,
				'created_at'     => current_time( 'mysql' ),
				'updated_at'     => current_time( 'mysql' ),
			),
			array( '%s', '%d', '%f', '%s', '%f', '%d', '%s', '%s' )
		);

		return (int) $wpdb->insert_id;
	}

	/**
	 * Get customer email by ID.
	 *
	 * @param int $customer_id Customer ID.
	 * @return string
	 */
	private function get_customer_email( $customer_id ) {
		global $wpdb;

		return (string) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT email FROM {$wpdb->prefix}bookings_customers WHERE id = %d",
				$customer_id
			)
		);
	}

	/**
	 * Set packages_enabled setting value.
	 *
	 * @param string $value Setting value.
	 * @return void
	 */
	private function set_packages_enabled( $value ) {
		global $wpdb;

		$wpdb->replace(
			$wpdb->prefix . 'bookings_settings',
			array(
				'setting_key'   => 'packages_enabled',
				'setting_value' => (string) $value,
			),
			array( '%s', '%s' )
		);
	}

	/**
	 * Ensure package types table exists for this class.
	 *
	 * @return void
	 */
	private function ensure_package_types_table_exists() {
		global $wpdb;

		$table_name = $wpdb->prefix . 'bookings_package_types';
		if ( function_exists( 'bookit_test_table_exists' ) && bookit_test_table_exists( $table_name ) ) {
			return;
		}

		$migration_file = dirname( __DIR__, 2 ) . '/database/migrations/0005-create-package-types-table.php';
		if ( file_exists( $migration_file ) ) {
			require_once $migration_file;
		}

		if ( class_exists( 'Bookit_Migration_0005_Create_Package_Types_Table' ) ) {
			$migration = new Bookit_Migration_0005_Create_Package_Types_Table();
			$migration->up();
		}
	}

	/**
	 * Ensure customer packages table exists for this class.
	 *
	 * @return void
	 */
	private function ensure_customer_packages_table_exists() {
		global $wpdb;

		$table_name = $wpdb->prefix . 'bookings_customer_packages';
		if ( function_exists( 'bookit_test_table_exists' ) && bookit_test_table_exists( $table_name ) ) {
			return;
		}

		$charset_collate = $wpdb->get_charset_collate();
		$wpdb->query(
			"CREATE TABLE IF NOT EXISTS {$table_name} (
				id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
				customer_id BIGINT UNSIGNED NOT NULL,
				package_type_id BIGINT UNSIGNED NOT NULL,
				sessions_total INT UNSIGNED NOT NULL,
				sessions_remaining INT UNSIGNED NOT NULL,
				purchase_price DECIMAL(10,2) NULL,
				purchased_at DATETIME NULL,
				expires_at DATETIME NULL,
				status ENUM('active','exhausted','expired','cancelled') NOT NULL DEFAULT 'active',
				payment_method VARCHAR(50) NULL,
				payment_reference VARCHAR(255) NULL,
				notes TEXT NULL,
				created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
				updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
				PRIMARY KEY (id),
				KEY idx_customer_id (customer_id),
				KEY idx_package_type_id (package_type_id),
				KEY idx_status (status),
				KEY idx_expires_at (expires_at)
			) ENGINE=InnoDB {$charset_collate};"
		); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.DirectDatabaseQuery.DirectQuery
	}

	/**
	 * Ensure package redemptions table exists for this class.
	 *
	 * @return void
	 */
	private function ensure_package_redemptions_table_exists() {
		global $wpdb;

		$table_name = $wpdb->prefix . 'bookings_package_redemptions';
		if ( function_exists( 'bookit_test_table_exists' ) && bookit_test_table_exists( $table_name ) ) {
			return;
		}

		$charset_collate = $wpdb->get_charset_collate();
		$wpdb->query(
			"CREATE TABLE IF NOT EXISTS {$table_name} (
				id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
				customer_package_id BIGINT UNSIGNED NOT NULL,
				booking_id BIGINT UNSIGNED NOT NULL,
				redeemed_at DATETIME NOT NULL,
				redeemed_by BIGINT UNSIGNED NOT NULL,
				notes TEXT NULL,
				created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
				PRIMARY KEY (id),
				KEY idx_customer_package_id (customer_package_id),
				KEY idx_booking_id (booking_id)
			) ENGINE=InnoDB {$charset_collate};"
		); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.DirectDatabaseQuery.DirectQuery
	}

	/**
	 * Ensure bookings table has customer_package_id column for feature tests.
	 *
	 * @return void
	 */
	private function ensure_bookings_customer_package_column_exists() {
		global $wpdb;

		$table_name = $wpdb->prefix . 'bookings';
		$column     = $wpdb->get_var( "SHOW COLUMNS FROM {$table_name} LIKE 'customer_package_id'" ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared

		if ( empty( $column ) ) {
			$wpdb->query(
				"ALTER TABLE {$table_name} ADD COLUMN customer_package_id BIGINT UNSIGNED NULL DEFAULT NULL AFTER stripe_session_id"
			); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.DirectDatabaseQuery.DirectQuery
		}
	}
}
