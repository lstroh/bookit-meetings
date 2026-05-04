<?php
/**
 * Tests for customer GDPR data export endpoint.
 *
 * @package    Bookit_Booking_System
 * @subpackage Tests
 */

/**
 * Test customer data export.
 */
class Test_Customer_Data_Export extends WP_UnitTestCase {

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
				'bookings_customers',
				'bookings_services',
				'bookings_staff',
				'bookings_audit_log',
			)
		);

		require_once BOOKIT_PLUGIN_DIR . 'includes/config/error-codes.php';
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
				'bookings_customers',
				'bookings_services',
				'bookings_staff',
				'bookings_audit_log',
			)
		);

		$_SESSION = array();
		parent::tearDown();
	}

	/**
	 * @covers Bookit_Customers_API::export_customer_data
	 */
	public function test_json_export_returns_file_download() {
		$admin_id    = $this->create_test_staff( array( 'role' => 'admin' ) );
		$customer_id = $this->create_test_customer();
		$this->login_as( $admin_id, 'admin' );

		$response = $this->dispatch_export_request( $customer_id, 'json' );
		$content  = $response->get_data();

		$this->assertSame( 200, $response->get_status() );
		$this->assertStringContainsString( 'attachment;', (string) $this->get_response_header( $response, 'Content-Disposition' ) );
		$this->assertIsString( $content );
		$this->assertIsArray( json_decode( $content, true ) );
	}

	/**
	 * @covers Bookit_Customers_API::export_customer_data
	 */
	public function test_json_export_structure() {
		$admin_id    = $this->create_test_staff( array( 'role' => 'admin' ) );
		$service_id  = $this->create_test_service();
		$staff_id    = $this->create_test_staff( array( 'role' => 'staff' ) );
		$customer_id = $this->create_test_customer();
		$this->login_as( $admin_id, 'admin' );

		$this->create_test_booking(
			array(
				'customer_id' => $customer_id,
				'service_id'  => $service_id,
				'staff_id'    => $staff_id,
				'status'      => 'confirmed',
			)
		);
		$this->create_test_booking(
			array(
				'customer_id' => $customer_id,
				'service_id'  => $service_id,
				'staff_id'    => $staff_id,
				'status'      => 'completed',
				'start_time'  => '12:00:00',
				'end_time'    => '13:00:00',
			)
		);

		$response = $this->dispatch_export_request( $customer_id, 'json' );
		$data     = json_decode( (string) $response->get_data(), true );

		$this->assertArrayHasKey( 'customer', $data );
		$this->assertArrayHasKey( 'bookings', $data );
		$this->assertArrayHasKey( 'payments', $data );
		$this->assertArrayNotHasKey( 'audit_log', $data );
		$this->assertArrayHasKey( 'export_date', $data );
	}

	/**
	 * @covers Bookit_Customers_API::export_customer_data
	 */
	public function test_json_export_customer_fields() {
		$admin_id    = $this->create_test_staff( array( 'role' => 'admin' ) );
		$customer_id = $this->create_test_customer(
			array(
				'first_name'        => 'Ada',
				'last_name'         => 'Lovelace',
				'email'             => 'ada-' . wp_generate_password( 5, false ) . '@test.com',
				'phone'             => '07123456789',
				'marketing_consent' => 1,
			)
		);
		$this->login_as( $admin_id, 'admin' );

		$response = $this->dispatch_export_request( $customer_id, 'json' );
		$data     = json_decode( (string) $response->get_data(), true );

		$this->assertArrayHasKey( 'id', $data['customer'] );
		$this->assertArrayHasKey( 'first_name', $data['customer'] );
		$this->assertArrayHasKey( 'last_name', $data['customer'] );
		$this->assertArrayHasKey( 'email', $data['customer'] );
		$this->assertArrayHasKey( 'phone', $data['customer'] );
		$this->assertArrayHasKey( 'marketing_consent', $data['customer'] );
		$this->assertArrayHasKey( 'created_at', $data['customer'] );
	}

	/**
	 * @covers Bookit_Customers_API::export_customer_data
	 */
	public function test_json_export_bookings_count() {
		$admin_id    = $this->create_test_staff( array( 'role' => 'admin' ) );
		$service_id  = $this->create_test_service();
		$staff_id    = $this->create_test_staff( array( 'role' => 'staff' ) );
		$customer_id = $this->create_test_customer();
		$this->login_as( $admin_id, 'admin' );

		$this->create_test_booking(
			array(
				'customer_id' => $customer_id,
				'service_id'  => $service_id,
				'staff_id'    => $staff_id,
				'status'      => 'pending_payment',
			)
		);
		$this->create_test_booking(
			array(
				'customer_id' => $customer_id,
				'service_id'  => $service_id,
				'staff_id'    => $staff_id,
				'status'      => 'completed',
				'start_time'  => '11:00:00',
				'end_time'    => '12:00:00',
			)
		);
		$this->create_test_booking(
			array(
				'customer_id' => $customer_id,
				'service_id'  => $service_id,
				'staff_id'    => $staff_id,
				'status'      => 'cancelled',
				'start_time'  => '13:00:00',
				'end_time'    => '14:00:00',
			)
		);

		$response = $this->dispatch_export_request( $customer_id, 'json' );
		$data     = json_decode( (string) $response->get_data(), true );

		$this->assertCount( 3, $data['bookings'] );
	}

	/**
	 * @covers Bookit_Customers_API::export_customer_data
	 */
	public function test_csv_export_returns_zip() {
		$admin_id    = $this->create_test_staff( array( 'role' => 'admin' ) );
		$customer_id = $this->create_test_customer();
		$this->login_as( $admin_id, 'admin' );

		$response = $this->dispatch_export_request( $customer_id, 'csv' );
		$content  = (string) $response->get_data();

		$this->assertSame( 200, $response->get_status() );
		$this->assertStringContainsString( 'application/zip', (string) $this->get_response_header( $response, 'Content-Type' ) );
		$this->assertStringContainsString( '.zip', (string) $this->get_response_header( $response, 'Content-Disposition' ) );
		$this->assertStringStartsWith( 'PK', $content );
		$this->assertStringNotContainsString( 'audit-log.csv', $content );
	}

	/**
	 * @covers Bookit_Customers_API::export_customer_data
	 */
	public function test_no_cross_customer_data() {
		$admin_id      = $this->create_test_staff( array( 'role' => 'admin' ) );
		$service_id    = $this->create_test_service();
		$staff_id      = $this->create_test_staff( array( 'role' => 'staff' ) );
		$customer_one  = $this->create_test_customer();
		$customer_two  = $this->create_test_customer();
		$this->login_as( $admin_id, 'admin' );

		$this->create_test_booking(
			array(
				'customer_id' => $customer_one,
				'service_id'  => $service_id,
				'staff_id'    => $staff_id,
				'status'      => 'confirmed',
			)
		);
		$this->create_test_booking(
			array(
				'customer_id' => $customer_one,
				'service_id'  => $service_id,
				'staff_id'    => $staff_id,
				'status'      => 'completed',
				'start_time'  => '11:00:00',
				'end_time'    => '12:00:00',
			)
		);

		$other_booking_one = $this->create_test_booking(
			array(
				'customer_id' => $customer_two,
				'service_id'  => $service_id,
				'staff_id'    => $staff_id,
				'status'      => 'confirmed',
				'start_time'  => '14:00:00',
				'end_time'    => '15:00:00',
			)
		);
		$other_booking_two = $this->create_test_booking(
			array(
				'customer_id' => $customer_two,
				'service_id'  => $service_id,
				'staff_id'    => $staff_id,
				'status'      => 'cancelled',
				'start_time'  => '15:00:00',
				'end_time'    => '16:00:00',
			)
		);

		$response = $this->dispatch_export_request( $customer_one, 'json' );
		$data     = json_decode( (string) $response->get_data(), true );

		$exported_booking_ids = array_map( 'intval', array_column( $data['bookings'], 'id' ) );
		$this->assertNotContains( $other_booking_one, $exported_booking_ids );
		$this->assertNotContains( $other_booking_two, $exported_booking_ids );
	}

	/**
	 * @covers Bookit_Customers_API::export_customer_data
	 */
	public function test_export_creates_audit_log_entry() {
		global $wpdb;

		$admin_id    = $this->create_test_staff( array( 'role' => 'admin' ) );
		$customer_id = $this->create_test_customer();
		$this->login_as( $admin_id, 'admin' );

		$response = $this->dispatch_export_request( $customer_id, 'json' );
		$this->assertSame( 200, $response->get_status() );

		$row = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT action, object_id
				FROM {$wpdb->prefix}bookings_audit_log
				WHERE action = 'customer_data_exported' AND object_id = %d
				ORDER BY id DESC
				LIMIT 1",
				$customer_id
			),
			ARRAY_A
		);

		$this->assertNotEmpty( $row );
		$this->assertSame( 'customer_data_exported', $row['action'] );
		$this->assertSame( $customer_id, (int) $row['object_id'] );
	}

	/**
	 * @covers Bookit_Customers_API::check_admin_permission
	 */
	public function test_staff_permission_denied() {
		$staff_id    = $this->create_test_staff( array( 'role' => 'staff' ) );
		$customer_id = $this->create_test_customer();
		$this->login_as( $staff_id, 'staff' );

		$response = $this->dispatch_export_request( $customer_id, 'json' );

		$this->assertSame( 403, $response->get_status() );
	}

	/**
	 * @covers Bookit_Customers_API::export_customer_data
	 */
	public function test_customer_not_found_returns_404() {
		$admin_id = $this->create_test_staff( array( 'role' => 'admin' ) );
		$this->login_as( $admin_id, 'admin' );

		$response = $this->dispatch_export_request( 99999, 'json' );

		$this->assertSame( 404, $response->get_status() );
		$this->assertSame( 'E4013', $response->as_error()->get_error_code() );
	}

	/**
	 * @covers Bookit_Customers_API::export_customer_data
	 */
	public function test_json_export_payments_exclude_gateway_ids() {
		global $wpdb;

		$admin_id    = $this->create_test_staff( array( 'role' => 'admin' ) );
		$service_id  = $this->create_test_service();
		$staff_id    = $this->create_test_staff( array( 'role' => 'staff' ) );
		$customer_id = $this->create_test_customer();
		$this->login_as( $admin_id, 'admin' );

		$booking_id = $this->create_test_booking(
			array(
				'customer_id' => $customer_id,
				'service_id'  => $service_id,
				'staff_id'    => $staff_id,
				'status'      => 'confirmed',
			)
		);

		$gateway_columns = array(
			'stripe_payment_intent_id',
			'stripe_charge_id',
			'paypal_order_id',
			'paypal_capture_id',
		);

		$existing_columns = $wpdb->get_col( "SHOW COLUMNS FROM {$wpdb->prefix}bookings_payments" );
		$existing_columns = is_array( $existing_columns ) ? $existing_columns : array();
		$present_gateway_columns = array_values( array_intersect( $gateway_columns, $existing_columns ) );

		if ( empty( $present_gateway_columns ) ) {
			$this->markTestSkipped( 'No gateway ID columns exist on bookings_payments table in this environment.' );
		}

		$wpdb->insert(
			$wpdb->prefix . 'bookings_payments',
			array(
				'booking_id'                => $booking_id,
				'customer_id'               => $customer_id,
				'amount'                    => 15.50,
				'payment_type'              => 'deposit',
				'payment_method'            => 'stripe',
				'payment_status'            => 'completed',
				'stripe_payment_intent_id'  => 'pi_test_gateway_id',
				'stripe_charge_id'          => 'ch_test_gateway_id',
				'paypal_order_id'           => null,
				'paypal_capture_id'         => null,
				'refund_amount'             => null,
				'refund_reason'             => null,
				'refunded_at'               => null,
				'transaction_date'          => current_time( 'mysql' ),
				'created_at'                => current_time( 'mysql' ),
				'updated_at'                => current_time( 'mysql' ),
			),
			array( '%d', '%d', '%f', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%f', '%s', '%s', '%s', '%s', '%s' )
		);

		$response = $this->dispatch_export_request( $customer_id, 'json' );
		$data     = json_decode( (string) $response->get_data(), true );

		if ( ! isset( $data['payments'] ) || ! is_array( $data['payments'] ) ) {
			$this->markTestSkipped( 'Export response does not include a payments array in this environment.' );
		}

		foreach ( $data['payments'] as $payment_row ) {
			if ( ! is_array( $payment_row ) ) {
				continue;
			}
			foreach ( $present_gateway_columns as $gateway_column ) {
				$this->assertArrayNotHasKey( $gateway_column, $payment_row );
			}
		}
	}

	/**
	 * @covers Bookit_Customers_API::export_customers_csv
	 */
	public function test_existing_bulk_export_still_works() {
		$admin_id = $this->create_test_staff( array( 'role' => 'admin' ) );
		$this->login_as( $admin_id, 'admin' );
		$this->create_test_customer();

		$request  = new WP_REST_Request( 'GET', '/' . $this->namespace . '/dashboard/customers/export' );
		$response = rest_get_server()->dispatch( $request );

		$this->assertSame( 200, $response->get_status() );
		$this->assertFalse( $response->is_error() );
	}

	/**
	 * Dispatch customer export request.
	 *
	 * @param int    $customer_id Customer ID.
	 * @param string $format Export format.
	 * @return WP_REST_Response
	 */
	private function dispatch_export_request( $customer_id, $format ) {
		$request = new WP_REST_Request( 'GET', '/' . $this->namespace . '/dashboard/customers/' . absint( $customer_id ) . '/export' );
		$request->set_param( 'format', $format );
		return rest_get_server()->dispatch( $request );
	}

	/**
	 * Read response header with fallback casing.
	 *
	 * @param WP_REST_Response $response Response.
	 * @param string           $name Header name.
	 * @return string|null
	 */
	private function get_response_header( $response, $name ) {
		$headers = $response->get_headers();

		if ( isset( $headers[ $name ] ) ) {
			return $headers[ $name ];
		}

		$lower_name = strtolower( $name );
		foreach ( $headers as $key => $value ) {
			if ( strtolower( (string) $key ) === $lower_name ) {
				return $value;
			}
		}

		return null;
	}

	/**
	 * Simulate Bookit dashboard login via session.
	 *
	 * @param int    $staff_id Staff row ID.
	 * @param string $role Session role.
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
			'email'             => 'customer-' . wp_generate_password( 6, false ) . '@test.com',
			'first_name'        => 'Test',
			'last_name'         => 'Customer',
			'phone'             => '07700900000',
			'marketing_consent' => 0,
			'created_at'        => current_time( 'mysql' ),
			'updated_at'        => current_time( 'mysql' ),
		);

		$data = wp_parse_args( $args, $defaults );
		$wpdb->insert(
			$wpdb->prefix . 'bookings_customers',
			$data,
			array( '%s', '%s', '%s', '%s', '%d', '%s', '%s' )
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
			'deposit_paid'     => 10.00,
			'balance_due'      => 40.00,
			'full_amount_paid' => 0,
			'payment_method'   => 'cash',
			'created_at'       => current_time( 'mysql' ),
			'updated_at'       => current_time( 'mysql' ),
			'deleted_at'       => null,
		);

		$data = wp_parse_args( $args, $defaults );
		$wpdb->insert( $wpdb->prefix . 'bookings', $data );
		$booking_id = (int) $wpdb->insert_id;

		$wpdb->insert(
			$wpdb->prefix . 'bookings_payments',
			array(
				'booking_id'              => $booking_id,
				'customer_id'             => (int) $data['customer_id'],
				'amount'                  => 10.00,
				'payment_type'            => 'deposit',
				'payment_method'          => 'cash',
				'payment_status'          => 'completed',
				'stripe_payment_intent_id' => null,
				'stripe_charge_id'        => null,
				'paypal_order_id'         => null,
				'paypal_capture_id'       => null,
				'refund_amount'           => null,
				'refund_reason'           => null,
				'refunded_at'             => null,
				'transaction_date'        => current_time( 'mysql' ),
				'created_at'              => current_time( 'mysql' ),
				'updated_at'              => current_time( 'mysql' ),
			),
			array( '%d', '%d', '%f', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%f', '%s', '%s', '%s', '%s', '%s' )
		);

		return $booking_id;
	}
}
