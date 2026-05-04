<?php
/**
 * Tests for Package Redemption API.
 *
 * @package    Bookit_Booking_System
 * @subpackage Tests
 */

/**
 * Test Package Redemption API endpoint.
 */
class Test_Package_Redemption_API extends WP_UnitTestCase {

	/**
	 * REST namespace.
	 *
	 * @var string
	 */
	private $namespace = 'bookit/v1';

	/**
	 * Test service ID.
	 *
	 * @var int
	 */
	private $test_service_id = 0;

	/**
	 * Test staff ID.
	 *
	 * @var int
	 */
	private $test_staff_id = 0;

	/**
	 * Admin staff ID.
	 *
	 * @var int
	 */
	private $admin_id = 0;

	/**
	 * Created staff IDs for cleanup.
	 *
	 * @var int[]
	 */
	private $created_staff_ids = array();

	/**
	 * Original superglobals for restoration.
	 *
	 * @var array
	 */
	private $original_server = array();
	private $original_request = array();

	/**
	 * Set up each test.
	 */
	public function setUp(): void {
		parent::setUp();

		$this->original_server  = $_SERVER;
		$this->original_request = $_REQUEST;

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
		do_action( 'rest_api_init' );

		$this->test_service_id = $this->insert_service();
		$this->test_staff_id   = $this->create_test_staff( array( 'role' => 'staff' ) );
		$this->admin_id        = $this->create_test_staff( array( 'role' => 'admin' ) );
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

		if ( $this->test_service_id > 0 ) {
			$wpdb->delete( $wpdb->prefix . 'bookings_services', array( 'id' => (int) $this->test_service_id ), array( '%d' ) );
		}

		$_SESSION = array();
		$_SERVER  = $this->original_server;
		$_REQUEST = $this->original_request;

		parent::tearDown();
	}

	public function test_redeem_package_returns_201() {
		$customer_id         = $this->insert_customer();
		$package_type_id     = $this->insert_package_type();
		$customer_package_id = $this->insert_customer_package(
			array(
				'customer_id'     => $customer_id,
				'package_type_id' => $package_type_id,
			)
		);
		$booking_id          = $this->insert_booking( array( 'customer_id' => $customer_id ) );

		$this->login_as( $this->admin_id, 'admin' );

		$request = new WP_REST_Request( 'POST', '/' . $this->namespace . '/dashboard/package-redemptions' );
		$request->set_body_params(
			array(
				'customer_package_id' => $customer_package_id,
				'booking_id'          => $booking_id,
				'notes'               => 'Manual redeem',
			)
		);
		$response = rest_get_server()->dispatch( $request );

		$this->assertSame( 201, $response->get_status() );
	}

	public function test_redeem_package_decrements_sessions_remaining() {
		global $wpdb;

		$customer_id         = $this->insert_customer();
		$package_type_id     = $this->insert_package_type();
		$customer_package_id = $this->insert_customer_package(
			array(
				'customer_id'        => $customer_id,
				'package_type_id'    => $package_type_id,
				'sessions_remaining' => 4,
			)
		);
		$booking_id          = $this->insert_booking( array( 'customer_id' => $customer_id ) );

		$this->login_as( $this->admin_id, 'admin' );
		$this->dispatch_redeem( $customer_package_id, $booking_id );

		$remaining = (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT sessions_remaining FROM {$wpdb->prefix}bookings_customer_packages WHERE id = %d",
				$customer_package_id
			)
		);

		$this->assertSame( 3, $remaining );
	}

	public function test_redeem_package_sets_booking_customer_package_id() {
		global $wpdb;

		$customer_id         = $this->insert_customer();
		$package_type_id     = $this->insert_package_type();
		$customer_package_id = $this->insert_customer_package(
			array(
				'customer_id'     => $customer_id,
				'package_type_id' => $package_type_id,
			)
		);
		$booking_id          = $this->insert_booking( array( 'customer_id' => $customer_id ) );

		$this->login_as( $this->admin_id, 'admin' );
		$this->dispatch_redeem( $customer_package_id, $booking_id );

		$link = (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT customer_package_id FROM {$wpdb->prefix}bookings WHERE id = %d",
				$booking_id
			)
		);
		$this->assertSame( $customer_package_id, $link );
	}

	public function test_redeem_package_sets_booking_payment_method() {
		global $wpdb;

		$customer_id         = $this->insert_customer();
		$package_type_id     = $this->insert_package_type();
		$customer_package_id = $this->insert_customer_package(
			array(
				'customer_id'     => $customer_id,
				'package_type_id' => $package_type_id,
			)
		);
		$booking_id          = $this->insert_booking( array( 'customer_id' => $customer_id ) );

		$this->login_as( $this->admin_id, 'admin' );
		$this->dispatch_redeem( $customer_package_id, $booking_id );

		$method = (string) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT payment_method FROM {$wpdb->prefix}bookings WHERE id = %d",
				$booking_id
			)
		);
		$this->assertSame( 'package_redemption', $method );
	}

	public function test_redeem_package_creates_redemption_record() {
		global $wpdb;

		$customer_id         = $this->insert_customer();
		$package_type_id     = $this->insert_package_type();
		$customer_package_id = $this->insert_customer_package(
			array(
				'customer_id'     => $customer_id,
				'package_type_id' => $package_type_id,
			)
		);
		$booking_id          = $this->insert_booking( array( 'customer_id' => $customer_id ) );

		$this->login_as( $this->admin_id, 'admin' );
		$this->dispatch_redeem( $customer_package_id, $booking_id, 'Redeem note' );

		$row = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT booking_id, customer_package_id FROM {$wpdb->prefix}bookings_package_redemptions WHERE booking_id = %d LIMIT 1",
				$booking_id
			),
			ARRAY_A
		);

		$this->assertNotEmpty( $row );
		$this->assertSame( $booking_id, (int) $row['booking_id'] );
		$this->assertSame( $customer_package_id, (int) $row['customer_package_id'] );
	}

	public function test_redeem_package_sets_status_exhausted_on_last_session() {
		global $wpdb;

		$customer_id         = $this->insert_customer();
		$package_type_id     = $this->insert_package_type();
		$customer_package_id = $this->insert_customer_package(
			array(
				'customer_id'        => $customer_id,
				'package_type_id'    => $package_type_id,
				'sessions_total'     => 1,
				'sessions_remaining' => 1,
			)
		);
		$booking_id          = $this->insert_booking( array( 'customer_id' => $customer_id ) );

		$this->login_as( $this->admin_id, 'admin' );
		$this->dispatch_redeem( $customer_package_id, $booking_id );

		$status = (string) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT status FROM {$wpdb->prefix}bookings_customer_packages WHERE id = %d",
				$customer_package_id
			)
		);
		$this->assertSame( 'exhausted', $status );
	}

	public function test_redeem_package_fires_audit_log() {
		global $wpdb;

		$customer_id         = $this->insert_customer();
		$package_type_id     = $this->insert_package_type();
		$customer_package_id = $this->insert_customer_package(
			array(
				'customer_id'     => $customer_id,
				'package_type_id' => $package_type_id,
			)
		);
		$booking_id          = $this->insert_booking( array( 'customer_id' => $customer_id ) );

		$this->login_as( $this->admin_id, 'admin' );
		$this->dispatch_redeem( $customer_package_id, $booking_id );

		$audit = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT action FROM {$wpdb->prefix}bookings_audit_log WHERE action = %s ORDER BY id DESC LIMIT 1",
				'customer_package.redeemed'
			)
		);
		$this->assertSame( 'customer_package.redeemed', $audit );
	}

	public function test_redeem_package_redeemed_by_is_current_user() {
		global $wpdb;

		$customer_id         = $this->insert_customer();
		$package_type_id     = $this->insert_package_type();
		$customer_package_id = $this->insert_customer_package(
			array(
				'customer_id'     => $customer_id,
				'package_type_id' => $package_type_id,
			)
		);
		$booking_id          = $this->insert_booking( array( 'customer_id' => $customer_id ) );

		$this->login_as( $this->admin_id, 'admin' );
		$this->dispatch_redeem( $customer_package_id, $booking_id );

		$redeemed_by = (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT redeemed_by FROM {$wpdb->prefix}bookings_package_redemptions WHERE booking_id = %d LIMIT 1",
				$booking_id
			)
		);
		$this->assertSame( get_current_user_id(), $redeemed_by );
	}

	public function test_redeem_package_returns_401_for_unauthenticated() {
		$customer_id         = $this->insert_customer();
		$package_type_id     = $this->insert_package_type();
		$customer_package_id = $this->insert_customer_package(
			array(
				'customer_id'     => $customer_id,
				'package_type_id' => $package_type_id,
			)
		);
		$booking_id = $this->insert_booking( array( 'customer_id' => $customer_id ) );

		$_SESSION = array();
		wp_set_current_user( 0 );

		$response = $this->dispatch_redeem( $customer_package_id, $booking_id );
		$this->assertSame( 401, $response->get_status() );
	}

	public function test_redeem_package_returns_403_for_staff_role() {
		$customer_id         = $this->insert_customer();
		$package_type_id     = $this->insert_package_type();
		$customer_package_id = $this->insert_customer_package(
			array(
				'customer_id'     => $customer_id,
				'package_type_id' => $package_type_id,
			)
		);
		$booking_id = $this->insert_booking( array( 'customer_id' => $customer_id ) );
		$staff_id   = $this->create_test_staff( array( 'role' => 'bookit_staff' ) );

		$this->login_as( $staff_id, 'bookit_staff' );
		$response = $this->dispatch_redeem( $customer_package_id, $booking_id );

		$this->assertSame( 403, $response->get_status() );
	}

	public function test_redeem_package_returns_404_for_missing_package() {
		$customer_id = $this->insert_customer();
		$booking_id  = $this->insert_booking( array( 'customer_id' => $customer_id ) );

		$this->login_as( $this->admin_id, 'admin' );
		$response = $this->dispatch_redeem( 999999, $booking_id );

		$this->assertSame( 404, $response->get_status() );
		$this->assertSame( 'E5001', $response->as_error()->get_error_code() );
	}

	public function test_redeem_package_returns_404_for_missing_booking() {
		$customer_id         = $this->insert_customer();
		$package_type_id     = $this->insert_package_type();
		$customer_package_id = $this->insert_customer_package(
			array(
				'customer_id'     => $customer_id,
				'package_type_id' => $package_type_id,
			)
		);

		$this->login_as( $this->admin_id, 'admin' );
		$response = $this->dispatch_redeem( $customer_package_id, 999999 );

		$this->assertSame( 404, $response->get_status() );
		$this->assertSame( 'booking_not_found', $response->as_error()->get_error_code() );
	}

	public function test_redeem_package_returns_422_for_exhausted_package() {
		$customer_id         = $this->insert_customer();
		$package_type_id     = $this->insert_package_type();
		$customer_package_id = $this->insert_customer_package(
			array(
				'customer_id'        => $customer_id,
				'package_type_id'    => $package_type_id,
				'status'             => 'exhausted',
				'sessions_remaining' => 0,
			)
		);
		$booking_id          = $this->insert_booking( array( 'customer_id' => $customer_id ) );

		$this->login_as( $this->admin_id, 'admin' );
		$response = $this->dispatch_redeem( $customer_package_id, $booking_id );

		$this->assertSame( 422, $response->get_status() );
		$this->assertSame( 'E5002', $response->as_error()->get_error_code() );
	}

	public function test_redeem_package_returns_422_for_expired_package() {
		$customer_id         = $this->insert_customer();
		$package_type_id     = $this->insert_package_type();
		$customer_package_id = $this->insert_customer_package(
			array(
				'customer_id'     => $customer_id,
				'package_type_id' => $package_type_id,
				'status'          => 'expired',
				'expires_at'      => gmdate( 'Y-m-d H:i:s', strtotime( '-1 day' ) ),
			)
		);
		$booking_id          = $this->insert_booking( array( 'customer_id' => $customer_id ) );

		$this->login_as( $this->admin_id, 'admin' );
		$response = $this->dispatch_redeem( $customer_package_id, $booking_id );

		$this->assertSame( 422, $response->get_status() );
		$this->assertSame( 'E5003', $response->as_error()->get_error_code() );
	}

	public function test_redeem_package_returns_422_for_already_redeemed_booking() {
		$customer_id         = $this->insert_customer();
		$package_type_id     = $this->insert_package_type();
		$customer_package_id = $this->insert_customer_package(
			array(
				'customer_id'     => $customer_id,
				'package_type_id' => $package_type_id,
			)
		);
		$booking_id          = $this->insert_booking(
			array(
				'customer_id'         => $customer_id,
				'customer_package_id' => $customer_package_id,
			)
		);

		$this->login_as( $this->admin_id, 'admin' );
		$response = $this->dispatch_redeem( $customer_package_id, $booking_id );

		$this->assertSame( 422, $response->get_status() );
		$this->assertSame( 'booking_already_redeemed', $response->as_error()->get_error_code() );
	}

	public function test_redeem_package_returns_422_for_cancelled_booking() {
		$customer_id         = $this->insert_customer();
		$package_type_id     = $this->insert_package_type();
		$customer_package_id = $this->insert_customer_package(
			array(
				'customer_id'     => $customer_id,
				'package_type_id' => $package_type_id,
			)
		);
		$booking_id          = $this->insert_booking(
			array(
				'customer_id' => $customer_id,
				'status'      => 'cancelled',
			)
		);

		$this->login_as( $this->admin_id, 'admin' );
		$response = $this->dispatch_redeem( $customer_package_id, $booking_id );

		$this->assertSame( 422, $response->get_status() );
		$this->assertSame( 'booking_not_redeemable', $response->as_error()->get_error_code() );
	}

	public function test_redeem_package_returns_422_for_service_mismatch() {
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
		$booking_id = $this->insert_booking( array( 'customer_id' => $customer_id ) );

		$this->login_as( $this->admin_id, 'admin' );
		$response = $this->dispatch_redeem( $customer_package_id, $booking_id );

		$this->assertSame( 422, $response->get_status() );
		$this->assertSame( 'E5004', $response->as_error()->get_error_code() );
	}

	public function test_redeem_package_returns_422_for_zero_sessions_remaining() {
		$customer_id         = $this->insert_customer();
		$package_type_id     = $this->insert_package_type();
		$customer_package_id = $this->insert_customer_package(
			array(
				'customer_id'        => $customer_id,
				'package_type_id'    => $package_type_id,
				'status'             => 'active',
				'sessions_remaining' => 0,
			)
		);
		$booking_id          = $this->insert_booking( array( 'customer_id' => $customer_id ) );

		$this->login_as( $this->admin_id, 'admin' );
		$response = $this->dispatch_redeem( $customer_package_id, $booking_id );

		$this->assertSame( 422, $response->get_status() );
		$this->assertSame( 'E5002', $response->as_error()->get_error_code() );
	}

	public function test_redeem_package_does_not_double_decrement() {
		global $wpdb;

		$customer_id         = $this->insert_customer();
		$package_type_id     = $this->insert_package_type();
		$customer_package_id = $this->insert_customer_package(
			array(
				'customer_id'        => $customer_id,
				'package_type_id'    => $package_type_id,
				'sessions_remaining' => 5,
			)
		);
		$booking_id          = $this->insert_booking( array( 'customer_id' => $customer_id ) );

		$this->login_as( $this->admin_id, 'admin' );

		$first_response  = $this->dispatch_redeem( $customer_package_id, $booking_id );
		$second_response = $this->dispatch_redeem( $customer_package_id, $booking_id );

		$this->assertSame( 201, $first_response->get_status() );
		$this->assertSame( 422, $second_response->get_status() );
		$this->assertSame( 'booking_already_redeemed', $second_response->as_error()->get_error_code() );

		$remaining = (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT sessions_remaining FROM {$wpdb->prefix}bookings_customer_packages WHERE id = %d",
				$customer_package_id
			)
		);
		$this->assertSame( 4, $remaining );
	}

	/**
	 * Dispatch package redemption request.
	 *
	 * @param int    $customer_package_id Customer package ID.
	 * @param int    $booking_id Booking ID.
	 * @param string $notes Optional notes.
	 * @return WP_REST_Response
	 */
	private function dispatch_redeem( $customer_package_id, $booking_id, $notes = '' ) {
		$request = new WP_REST_Request( 'POST', '/' . $this->namespace . '/dashboard/package-redemptions' );
		$request->set_body_params(
			array(
				'customer_package_id' => (int) $customer_package_id,
				'booking_id'          => (int) $booking_id,
				'notes'               => $notes,
			)
		);

		return rest_get_server()->dispatch( $request );
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

		$user_id = $this->factory()->user->create( array( 'role' => 'administrator' ) );
		wp_set_current_user( $user_id );
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
	 * Insert service test row.
	 *
	 * @return int
	 */
	private function insert_service(): int {
		global $wpdb;

		$wpdb->insert(
			$wpdb->prefix . 'bookings_services',
			array(
				'name'           => 'Service For Redemption Tests',
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
	 * Insert package type test row.
	 *
	 * @param array $overrides Field overrides.
	 * @return int
	 */
	private function insert_package_type( array $overrides = array() ): int {
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
	private function insert_customer( array $overrides = array() ): int {
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
	private function insert_customer_package( array $overrides = array() ): int {
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
	private function insert_booking( array $overrides = array() ): int {
		global $wpdb;
		$defaults = array(
			'customer_id'         => 1,
			'service_id'          => $this->test_service_id,
			'staff_id'            => $this->test_staff_id,
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
			'created_at'          => current_time( 'mysql' ),
			'updated_at'          => current_time( 'mysql' ),
		);
		$data     = array_merge( $defaults, $overrides );
		$wpdb->insert( $wpdb->prefix . 'bookings', $data );
		return (int) $wpdb->insert_id;
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
	 * Ensure bookings table has customer_package_id column for tests.
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
