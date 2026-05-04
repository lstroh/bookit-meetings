<?php
/**
 * Tests for Customer Packages API.
 *
 * @package    Bookit_Booking_System
 * @subpackage Tests
 */

/**
 * Test Customer Packages API endpoints.
 */
class Test_Customer_Packages_API extends WP_UnitTestCase {

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
	private $admin_id = 0;

	/**
	 * Primary customer ID.
	 *
	 * @var int
	 */
	private $customer_id = 0;

	/**
	 * Primary package type ID.
	 *
	 * @var int
	 */
	private $package_type_id = 0;

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

		bookit_test_truncate_tables(
			array(
				'bookings_package_redemptions',
				'bookings_customer_packages',
				'bookings_package_types',
				'bookings_customers',
				'bookings_audit_log',
			)
		);

		$_SESSION = array();
		do_action( 'rest_api_init' );

		$this->admin_id         = $this->create_test_staff( array( 'role' => 'admin' ) );
		$this->customer_id      = $this->insert_customer();
		$this->package_type_id  = $this->insert_package_type();
		$this->login_as( $this->admin_id, 'admin' );
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
				'bookings_audit_log',
			)
		);

		foreach ( array_unique( $this->created_staff_ids ) as $staff_id ) {
			$wpdb->delete( $wpdb->prefix . 'bookings_staff', array( 'id' => (int) $staff_id ), array( '%d' ) );
		}

		$_SESSION = array();

		parent::tearDown();
	}

	public function test_list_requires_auth() {
		$_SESSION = array();

		$request  = new WP_REST_Request( 'GET', '/' . $this->namespace . '/dashboard/customer-packages' );
		$response = rest_get_server()->dispatch( $request );

		$this->assertEquals( 401, $response->get_status() );
	}

	public function test_list_requires_admin_role() {
		$staff_id = $this->create_test_staff( array( 'role' => 'staff' ) );
		$this->login_as( $staff_id, 'staff' );

		$request  = new WP_REST_Request( 'GET', '/' . $this->namespace . '/dashboard/customer-packages' );
		$response = rest_get_server()->dispatch( $request );

		$this->assertEquals( 403, $response->get_status() );
	}

	public function test_create_requires_admin_role() {
		$staff_id = $this->create_test_staff( array( 'role' => 'staff' ) );
		$this->login_as( $staff_id, 'staff' );

		$request = new WP_REST_Request( 'POST', '/' . $this->namespace . '/dashboard/customer-packages' );
		$request->set_body_params(
			array(
				'customer_id'     => $this->customer_id,
				'package_type_id' => $this->package_type_id,
			)
		);
		$response = rest_get_server()->dispatch( $request );

		$this->assertEquals( 403, $response->get_status() );
	}

	public function test_list_returns_all_packages() {
		$id_one = $this->insert_customer_package();
		$id_two = $this->insert_customer_package(
			array(
				'notes' => 'Second package',
			)
		);

		$request  = new WP_REST_Request( 'GET', '/' . $this->namespace . '/dashboard/customer-packages' );
		$response = rest_get_server()->dispatch( $request );
		$data     = $response->get_data();

		$this->assertEquals( 200, $response->get_status() );
		$this->assertCount( 2, $data );
		$this->assertSame( array( $id_two, $id_one ), array_column( $data, 'id' ) );
	}

	public function test_list_filters_by_customer_id() {
		$other_customer_id = $this->insert_customer(
			array(
				'email' => 'other-' . wp_generate_password( 6, false ) . '@test.com',
			)
		);

		$this->insert_customer_package( array( 'customer_id' => $this->customer_id ) );
		$other_package_id = $this->insert_customer_package( array( 'customer_id' => $other_customer_id ) );

		$request = new WP_REST_Request( 'GET', '/' . $this->namespace . '/dashboard/customer-packages' );
		$request->set_param( 'customer_id', $other_customer_id );
		$response = rest_get_server()->dispatch( $request );
		$data     = $response->get_data();

		$this->assertEquals( 200, $response->get_status() );
		$this->assertCount( 1, $data );
		$this->assertSame( $other_customer_id, $data[0]['customer_id'] );
		$this->assertSame( $other_package_id, $data[0]['id'] );
	}

	public function test_list_filters_by_status() {
		$this->insert_customer_package( array( 'status' => 'active' ) );
		$cancelled_id = $this->insert_customer_package( array( 'status' => 'cancelled' ) );

		$request = new WP_REST_Request( 'GET', '/' . $this->namespace . '/dashboard/customer-packages' );
		$request->set_param( 'status', 'cancelled' );
		$response = rest_get_server()->dispatch( $request );
		$data     = $response->get_data();

		$this->assertEquals( 200, $response->get_status() );
		$this->assertCount( 1, $data );
		$this->assertSame( 'cancelled', $data[0]['status'] );
		$this->assertSame( $cancelled_id, $data[0]['id'] );
	}

	public function test_list_includes_package_type_name() {
		$named_type_id = $this->insert_package_type(
			array(
				'name' => 'Premium Package',
			)
		);
		$this->insert_customer_package(
			array(
				'package_type_id' => $named_type_id,
			)
		);

		$request  = new WP_REST_Request( 'GET', '/' . $this->namespace . '/dashboard/customer-packages' );
		$response = rest_get_server()->dispatch( $request );
		$data     = $response->get_data();

		$this->assertEquals( 200, $response->get_status() );
		$this->assertSame( 'Premium Package', $data[0]['package_type_name'] );
	}

	public function test_list_returns_empty_array_when_none() {
		bookit_test_truncate_tables(
			array(
				'bookings_package_redemptions',
				'bookings_customer_packages',
			)
		);

		$request  = new WP_REST_Request( 'GET', '/' . $this->namespace . '/dashboard/customer-packages' );
		$response = rest_get_server()->dispatch( $request );
		$data     = $response->get_data();

		$this->assertEquals( 200, $response->get_status() );
		$this->assertSame( array(), $data );
	}

	public function test_create_sets_sessions_from_package_type() {
		$type_id = $this->insert_package_type(
			array(
				'sessions_count' => 7,
			)
		);

		$request = new WP_REST_Request( 'POST', '/' . $this->namespace . '/dashboard/customer-packages' );
		$request->set_body_params(
			array(
				'customer_id'     => $this->customer_id,
				'package_type_id' => $type_id,
			)
		);
		$response = rest_get_server()->dispatch( $request );
		$data     = $response->get_data();

		$this->assertEquals( 201, $response->get_status() );
		$this->assertSame( 7, $data['sessions_total'] );
		$this->assertSame( 7, $data['sessions_remaining'] );
	}

	public function test_create_sets_status_active() {
		$request = new WP_REST_Request( 'POST', '/' . $this->namespace . '/dashboard/customer-packages' );
		$request->set_body_params(
			array(
				'customer_id'     => $this->customer_id,
				'package_type_id' => $this->package_type_id,
			)
		);
		$response = rest_get_server()->dispatch( $request );
		$data     = $response->get_data();

		$this->assertEquals( 201, $response->get_status() );
		$this->assertSame( 'active', $data['status'] );
	}

	public function test_create_computes_expires_at_when_expiry_enabled() {
		$type_id = $this->insert_package_type(
			array(
				'expiry_enabled' => 1,
				'expiry_days'    => 30,
			)
		);

		$request = new WP_REST_Request( 'POST', '/' . $this->namespace . '/dashboard/customer-packages' );
		$request->set_body_params(
			array(
				'customer_id'     => $this->customer_id,
				'package_type_id' => $type_id,
				'purchased_at'    => '2026-01-01 10:00:00',
			)
		);
		$response = rest_get_server()->dispatch( $request );
		$data     = $response->get_data();

		$this->assertEquals( 201, $response->get_status() );
		$this->assertSame( '2026-01-31', substr( (string) $data['expires_at'], 0, 10 ) );
	}

	public function test_create_expires_at_null_when_expiry_disabled() {
		$type_id = $this->insert_package_type(
			array(
				'expiry_enabled' => 0,
				'expiry_days'    => null,
			)
		);

		$request = new WP_REST_Request( 'POST', '/' . $this->namespace . '/dashboard/customer-packages' );
		$request->set_body_params(
			array(
				'customer_id'     => $this->customer_id,
				'package_type_id' => $type_id,
			)
		);
		$response = rest_get_server()->dispatch( $request );
		$data     = $response->get_data();

		$this->assertEquals( 201, $response->get_status() );
		$this->assertNull( $data['expires_at'] );
	}

	public function test_create_returns_404_for_unknown_package_type() {
		$request = new WP_REST_Request( 'POST', '/' . $this->namespace . '/dashboard/customer-packages' );
		$request->set_body_params(
			array(
				'customer_id'     => $this->customer_id,
				'package_type_id' => 999999,
			)
		);
		$response = rest_get_server()->dispatch( $request );
		$data     = $response->get_data();

		$this->assertEquals( 404, $response->get_status() );
		$this->assertSame( 'E5001', $data['code'] );
	}

	public function test_create_rejects_inactive_package_type() {
		$type_id = $this->insert_package_type(
			array(
				'is_active' => 0,
			)
		);

		$request = new WP_REST_Request( 'POST', '/' . $this->namespace . '/dashboard/customer-packages' );
		$request->set_body_params(
			array(
				'customer_id'     => $this->customer_id,
				'package_type_id' => $type_id,
			)
		);
		$response = rest_get_server()->dispatch( $request );

		$this->assertEquals( 422, $response->get_status() );
	}

	public function test_create_fires_audit_log() {
		global $wpdb;

		$request = new WP_REST_Request( 'POST', '/' . $this->namespace . '/dashboard/customer-packages' );
		$request->set_body_params(
			array(
				'customer_id'     => $this->customer_id,
				'package_type_id' => $this->package_type_id,
			)
		);
		$response = rest_get_server()->dispatch( $request );

		$audit_row = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT action FROM {$wpdb->prefix}bookings_audit_log WHERE action = %s ORDER BY id DESC LIMIT 1",
				'customer_package.created'
			),
			ARRAY_A
		);

		$this->assertEquals( 201, $response->get_status() );
		$this->assertNotEmpty( $audit_row );
		$this->assertSame( 'customer_package.created', $audit_row['action'] );
	}

	public function test_create_returns_201() {
		$request = new WP_REST_Request( 'POST', '/' . $this->namespace . '/dashboard/customer-packages' );
		$request->set_body_params(
			array(
				'customer_id'     => $this->customer_id,
				'package_type_id' => $this->package_type_id,
			)
		);
		$response = rest_get_server()->dispatch( $request );

		$this->assertEquals( 201, $response->get_status() );
	}

	public function test_get_single_returns_correct_package() {
		$package_id = $this->insert_customer_package(
			array(
				'status' => 'active',
			)
		);

		$request  = new WP_REST_Request( 'GET', '/' . $this->namespace . '/dashboard/customer-packages/' . $package_id );
		$response = rest_get_server()->dispatch( $request );
		$data     = $response->get_data();

		$this->assertEquals( 200, $response->get_status() );
		$this->assertSame( $package_id, $data['id'] );
		$this->assertSame( $this->customer_id, $data['customer_id'] );
		$this->assertSame( $this->package_type_id, $data['package_type_id'] );
	}

	public function test_get_single_returns_404_for_missing() {
		$request  = new WP_REST_Request( 'GET', '/' . $this->namespace . '/dashboard/customer-packages/999999' );
		$response = rest_get_server()->dispatch( $request );
		$data     = $response->get_data();

		$this->assertEquals( 404, $response->get_status() );
		$this->assertSame( 'E5001', $data['code'] );
	}

	public function test_cancel_sets_status_cancelled() {
		global $wpdb;

		$package_id = $this->insert_customer_package( array( 'status' => 'active' ) );
		$request    = new WP_REST_Request( 'POST', '/' . $this->namespace . '/dashboard/customer-packages/' . $package_id . '/cancel' );
		$response   = rest_get_server()->dispatch( $request );

		$status = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT status FROM {$wpdb->prefix}bookings_customer_packages WHERE id = %d",
				$package_id
			)
		);

		$this->assertEquals( 200, $response->get_status() );
		$this->assertSame( 'cancelled', $status );
	}

	public function test_cancel_returns_404_for_missing() {
		$request  = new WP_REST_Request( 'POST', '/' . $this->namespace . '/dashboard/customer-packages/999999/cancel' );
		$response = rest_get_server()->dispatch( $request );

		$this->assertEquals( 404, $response->get_status() );
	}

	public function test_cancel_rejects_already_cancelled() {
		$package_id = $this->insert_customer_package( array( 'status' => 'cancelled' ) );
		$request    = new WP_REST_Request( 'POST', '/' . $this->namespace . '/dashboard/customer-packages/' . $package_id . '/cancel' );
		$response   = rest_get_server()->dispatch( $request );

		$this->assertEquals( 422, $response->get_status() );
	}

	public function test_cancel_rejects_exhausted_package() {
		$package_id = $this->insert_customer_package( array( 'status' => 'exhausted' ) );
		$request    = new WP_REST_Request( 'POST', '/' . $this->namespace . '/dashboard/customer-packages/' . $package_id . '/cancel' );
		$response   = rest_get_server()->dispatch( $request );

		$this->assertEquals( 422, $response->get_status() );
	}

	public function test_cancel_fires_audit_log() {
		global $wpdb;

		$package_id = $this->insert_customer_package( array( 'status' => 'active' ) );
		$request    = new WP_REST_Request( 'POST', '/' . $this->namespace . '/dashboard/customer-packages/' . $package_id . '/cancel' );
		$response   = rest_get_server()->dispatch( $request );

		$audit_row = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT action FROM {$wpdb->prefix}bookings_audit_log WHERE action = %s ORDER BY id DESC LIMIT 1",
				'customer_package.cancelled'
			),
			ARRAY_A
		);

		$this->assertEquals( 200, $response->get_status() );
		$this->assertNotEmpty( $audit_row );
		$this->assertSame( 'customer_package.cancelled', $audit_row['action'] );
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
			'customer_id'        => $this->customer_id,
			'package_type_id'    => $this->package_type_id,
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
	 * Ensure package types table exists for this test class.
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
	 * Ensure customer packages table exists for this test class.
	 *
	 * Uses a FK-free definition because WP tests can create TEMPORARY tables,
	 * and MySQL does not allow foreign keys on temporary tables.
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

}
