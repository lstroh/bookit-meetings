<?php
/**
 * Tests for Package Redemption History API.
 *
 * @package    Bookit_Booking_System
 * @subpackage Tests
 */

/**
 * Test package redemption history endpoint.
 */
class Test_Package_Redemption_History_API extends WP_UnitTestCase {

	/**
	 * REST namespace.
	 *
	 * @var string
	 */
	private $namespace = 'bookit/v1';

	/**
	 * Admin staff ID.
	 *
	 * @var int
	 */
	private $admin_staff_id = 0;

	/**
	 * Regular staff ID.
	 *
	 * @var int
	 */
	private $staff_id = 0;

	/**
	 * Service ID used for bookings.
	 *
	 * @var int
	 */
	private $service_id = 0;

	/**
	 * Created staff IDs for cleanup.
	 *
	 * @var int[]
	 */
	private $created_staff_ids = array();

	/**
	 * Set up each test.
	 */
	public function setUp(): void {
		parent::setUp();

		$this->ensure_package_types_table_exists();
		$this->ensure_customer_packages_table_exists();
		$this->ensure_package_redemptions_table_exists();

		bookit_test_truncate_tables(
			array(
				'bookings_package_redemptions',
				'bookings_customer_packages',
				'bookings_package_types',
				'bookings_customers',
				'bookings',
			)
		);

		$_SESSION = array();
		do_action( 'rest_api_init' );

		$this->admin_staff_id = $this->create_test_staff( array( 'role' => 'admin' ) );
		$this->staff_id       = $this->create_test_staff( array( 'role' => 'staff' ) );
		$this->service_id     = $this->insert_service();
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
			)
		);

		foreach ( array_unique( $this->created_staff_ids ) as $staff_id ) {
			$wpdb->delete( $wpdb->prefix . 'bookings_staff', array( 'id' => (int) $staff_id ), array( '%d' ) );
		}

		if ( $this->service_id > 0 ) {
			$wpdb->delete( $wpdb->prefix . 'bookings_services', array( 'id' => $this->service_id ), array( '%d' ) );
		}

		$_SESSION = array();

		parent::tearDown();
	}

	public function test_get_redemptions_returns_200_for_admin() {
		$this->login_as( $this->admin_staff_id, 'admin' );

		$package_type_id = $this->insert_package_type();
		$customer_id     = $this->insert_customer();
		$package_id      = $this->insert_customer_package(
			array(
				'customer_id'     => $customer_id,
				'package_type_id' => $package_type_id,
			)
		);

		$booking_one = $this->insert_booking( array( 'customer_id' => $customer_id ) );
		$booking_two = $this->insert_booking(
			array(
				'customer_id' => $customer_id,
				'start_time'  => '11:00:00',
				'end_time'    => '12:00:00',
			)
		);

		$this->insert_redemption(
			array(
				'customer_package_id' => $package_id,
				'booking_id'          => $booking_one,
				'redeemed_by'         => $this->admin_staff_id,
			)
		);
		$this->insert_redemption(
			array(
				'customer_package_id' => $package_id,
				'booking_id'          => $booking_two,
				'redeemed_by'         => $this->admin_staff_id,
			)
		);

		$response = $this->dispatch_get_redemptions( $package_id );
		$data     = $response->get_data();

		$this->assertSame( 200, $response->get_status() );
		$this->assertTrue( (bool) $data['success'] );
		$this->assertSame( 2, (int) $data['total'] );
	}

	public function test_get_redemptions_returns_empty_array_for_no_redemptions() {
		$this->login_as( $this->admin_staff_id, 'admin' );

		$package_type_id = $this->insert_package_type();
		$customer_id     = $this->insert_customer();
		$package_id      = $this->insert_customer_package(
			array(
				'customer_id'     => $customer_id,
				'package_type_id' => $package_type_id,
			)
		);

		$response = $this->dispatch_get_redemptions( $package_id );
		$data     = $response->get_data();

		$this->assertSame( 200, $response->get_status() );
		$this->assertSame( array(), $data['redemptions'] );
		$this->assertSame( 0, (int) $data['total'] );
	}

	public function test_get_redemptions_returns_404_for_missing_package() {
		$this->login_as( $this->admin_staff_id, 'admin' );

		$response = $this->dispatch_get_redemptions( 999999 );
		$data     = $response->get_data();

		$this->assertSame( 404, $response->get_status() );
		$this->assertSame( 'E5001', $data['code'] );
	}

	public function test_get_redemptions_returns_401_for_unauthenticated() {
		$package_type_id = $this->insert_package_type();
		$customer_id     = $this->insert_customer();
		$package_id      = $this->insert_customer_package(
			array(
				'customer_id'     => $customer_id,
				'package_type_id' => $package_type_id,
			)
		);

		$_SESSION = array();

		$response = $this->dispatch_get_redemptions( $package_id );
		$this->assertSame( 401, $response->get_status() );
	}

	public function test_get_redemptions_returns_403_for_staff_role() {
		$bookit_staff_id = $this->create_test_staff( array( 'role' => 'bookit_staff' ) );
		$this->login_as( $bookit_staff_id, 'bookit_staff' );

		$package_type_id = $this->insert_package_type();
		$customer_id     = $this->insert_customer();
		$package_id      = $this->insert_customer_package(
			array(
				'customer_id'     => $customer_id,
				'package_type_id' => $package_type_id,
			)
		);

		$response = $this->dispatch_get_redemptions( $package_id );
		$this->assertSame( 403, $response->get_status() );
	}

	public function test_get_redemptions_response_shape() {
		$this->login_as( $this->admin_staff_id, 'admin' );

		$package_type_id = $this->insert_package_type();
		$customer_id     = $this->insert_customer();
		$package_id      = $this->insert_customer_package(
			array(
				'customer_id'     => $customer_id,
				'package_type_id' => $package_type_id,
			)
		);
		$booking_id      = $this->insert_booking( array( 'customer_id' => $customer_id ) );
		$this->insert_redemption(
			array(
				'customer_package_id' => $package_id,
				'booking_id'          => $booking_id,
				'redeemed_by'         => $this->admin_staff_id,
			)
		);

		$response   = $this->dispatch_get_redemptions( $package_id );
		$data       = $response->get_data();
		$redemption = $data['redemptions'][0];

		$this->assertArrayHasKey( 'id', $redemption );
		$this->assertArrayHasKey( 'booking_id', $redemption );
		$this->assertArrayHasKey( 'booking_date', $redemption );
		$this->assertArrayHasKey( 'service_name', $redemption );
		$this->assertArrayHasKey( 'staff_name', $redemption );
		$this->assertArrayHasKey( 'redeemed_at', $redemption );
		$this->assertArrayHasKey( 'redeemed_by_name', $redemption );
	}

	public function test_get_redemptions_redeemed_by_name_is_customer_when_zero() {
		$this->login_as( $this->admin_staff_id, 'admin' );

		$package_type_id = $this->insert_package_type();
		$customer_id     = $this->insert_customer();
		$package_id      = $this->insert_customer_package(
			array(
				'customer_id'     => $customer_id,
				'package_type_id' => $package_type_id,
			)
		);
		$booking_id      = $this->insert_booking( array( 'customer_id' => $customer_id ) );
		$this->insert_redemption(
			array(
				'customer_package_id' => $package_id,
				'booking_id'          => $booking_id,
				'redeemed_by'         => 0,
			)
		);

		$response   = $this->dispatch_get_redemptions( $package_id );
		$data       = $response->get_data();
		$redemption = $data['redemptions'][0];

		$this->assertSame( 'Customer', $redemption['redeemed_by_name'] );
	}

	public function test_get_redemptions_ordered_newest_first() {
		$this->login_as( $this->admin_staff_id, 'admin' );

		$package_type_id = $this->insert_package_type();
		$customer_id     = $this->insert_customer();
		$package_id      = $this->insert_customer_package(
			array(
				'customer_id'     => $customer_id,
				'package_type_id' => $package_type_id,
			)
		);

		$older_booking = $this->insert_booking( array( 'customer_id' => $customer_id ) );
		$newer_booking = $this->insert_booking(
			array(
				'customer_id' => $customer_id,
				'start_time'  => '12:00:00',
				'end_time'    => '13:00:00',
			)
		);

		$this->insert_redemption(
			array(
				'customer_package_id' => $package_id,
				'booking_id'          => $older_booking,
				'redeemed_by'         => $this->admin_staff_id,
				'redeemed_at'         => '2026-01-01 10:00:00',
			)
		);
		$this->insert_redemption(
			array(
				'customer_package_id' => $package_id,
				'booking_id'          => $newer_booking,
				'redeemed_by'         => $this->admin_staff_id,
				'redeemed_at'         => '2026-01-02 10:00:00',
			)
		);

		$response = $this->dispatch_get_redemptions( $package_id );
		$data     = $response->get_data();

		$this->assertSame( 2, (int) $data['total'] );
		$this->assertSame( '2026-01-02 10:00:00', $data['redemptions'][0]['redeemed_at'] );
		$this->assertSame( '2026-01-01 10:00:00', $data['redemptions'][1]['redeemed_at'] );
	}

	/**
	 * Dispatch GET redemption history request.
	 *
	 * @param int $package_id Package ID.
	 * @return WP_REST_Response
	 */
	private function dispatch_get_redemptions( int $package_id ) {
		$request = new WP_REST_Request( 'GET', '/' . $this->namespace . '/dashboard/customer-packages/' . $package_id . '/redemptions' );
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
	}

	/**
	 * Create test staff member.
	 *
	 * @param array $args Optional overrides.
	 * @return int
	 */
	private function create_test_staff( array $args = array() ): int {
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
	 * Insert package type test row.
	 *
	 * @param array $overrides Field overrides.
	 * @return int
	 */
	private function insert_package_type( array $overrides = array() ): int {
		global $wpdb;

		$defaults = array(
			'name'                   => 'History Package',
			'description'            => 'Test package',
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
	private function insert_redemption( array $overrides = array() ): int {
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
	 * Insert service test row.
	 *
	 * @return int
	 */
	private function insert_service(): int {
		global $wpdb;

		$wpdb->insert(
			$wpdb->prefix . 'bookings_services',
			array(
				'name'           => 'Redemption History Service',
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
}
