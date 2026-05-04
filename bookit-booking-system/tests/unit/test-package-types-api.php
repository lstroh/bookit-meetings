<?php
/**
 * Tests for Package Types API.
 *
 * @package    Bookit_Booking_System
 * @subpackage Tests
 */

/**
 * Test Package Types API endpoints.
 */
class Test_Package_Types_API extends WP_UnitTestCase {

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

		bookit_test_truncate_tables(
			array(
				'bookings_package_types',
				'bookings_audit_log',
			)
		);

		$_SESSION = array();
		do_action( 'rest_api_init' );

		$this->admin_id = $this->create_test_staff( array( 'role' => 'admin' ) );
		$this->login_as( $this->admin_id, 'admin' );
	}

	/**
	 * Tear down each test.
	 */
	public function tearDown(): void {
		global $wpdb;

		bookit_test_truncate_tables(
			array(
				'bookings_package_types',
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

		$request  = new WP_REST_Request( 'GET', '/' . $this->namespace . '/dashboard/package-types' );
		$response = rest_get_server()->dispatch( $request );

		$this->assertEquals( 401, $response->get_status() );
	}

	public function test_list_requires_admin_role() {
		$staff_id = $this->create_test_staff( array( 'role' => 'staff' ) );
		$this->login_as( $staff_id, 'staff' );

		$request  = new WP_REST_Request( 'GET', '/' . $this->namespace . '/dashboard/package-types' );
		$response = rest_get_server()->dispatch( $request );

		$this->assertEquals( 403, $response->get_status() );
	}

	public function test_create_requires_admin_role() {
		$staff_id = $this->create_test_staff( array( 'role' => 'staff' ) );
		$this->login_as( $staff_id, 'staff' );

		$request = new WP_REST_Request( 'POST', '/' . $this->namespace . '/dashboard/package-types' );
		$request->set_body_params( $this->get_valid_fixed_payload() );
		$response = rest_get_server()->dispatch( $request );

		$this->assertEquals( 403, $response->get_status() );
	}

	public function test_list_returns_all_package_types() {
		$id_one = $this->insert_package_type(
			array(
				'name' => 'Package A',
			)
		);
		$id_two = $this->insert_package_type(
			array(
				'name' => 'Package B',
			)
		);

		$request  = new WP_REST_Request( 'GET', '/' . $this->namespace . '/dashboard/package-types' );
		$response = rest_get_server()->dispatch( $request );
		$data     = $response->get_data();

		$this->assertEquals( 200, $response->get_status() );
		$this->assertCount( 2, $data );
		$this->assertEquals( array( $id_one, $id_two ), array_column( $data, 'id' ) );
	}

	public function test_list_active_only_filter() {
		$this->insert_package_type( array( 'name' => 'Active Package', 'is_active' => 1 ) );
		$this->insert_package_type( array( 'name' => 'Inactive Package', 'is_active' => 0 ) );

		$request = new WP_REST_Request( 'GET', '/' . $this->namespace . '/dashboard/package-types' );
		$request->set_param( 'active_only', true );
		$response = rest_get_server()->dispatch( $request );
		$data     = $response->get_data();

		$this->assertEquals( 200, $response->get_status() );
		$this->assertCount( 1, $data );
		$this->assertSame( 'Active Package', $data[0]['name'] );
		$this->assertTrue( $data[0]['is_active'] );
	}

	public function test_list_decodes_applicable_service_ids() {
		$this->insert_package_type(
			array(
				'applicable_service_ids' => wp_json_encode( array( 1, 2, 3 ) ),
			)
		);

		$request  = new WP_REST_Request( 'GET', '/' . $this->namespace . '/dashboard/package-types' );
		$response = rest_get_server()->dispatch( $request );
		$data     = $response->get_data();

		$this->assertEquals( 200, $response->get_status() );
		$this->assertIsArray( $data[0]['applicable_service_ids'] );
		$this->assertSame( array( 1, 2, 3 ), $data[0]['applicable_service_ids'] );
	}

	public function test_list_returns_null_for_all_services() {
		$this->insert_package_type(
			array(
				'applicable_service_ids' => null,
			)
		);

		$request  = new WP_REST_Request( 'GET', '/' . $this->namespace . '/dashboard/package-types' );
		$response = rest_get_server()->dispatch( $request );
		$data     = $response->get_data();

		$this->assertEquals( 200, $response->get_status() );
		$this->assertNull( $data[0]['applicable_service_ids'] );
	}

	public function test_create_valid_fixed_price_package() {
		global $wpdb;

		$request = new WP_REST_Request( 'POST', '/' . $this->namespace . '/dashboard/package-types' );
		$request->set_body_params( $this->get_valid_fixed_payload() );
		$response = rest_get_server()->dispatch( $request );
		$data     = $response->get_data();

		$row = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$wpdb->prefix}bookings_package_types WHERE id = %d",
				(int) $data['id']
			),
			ARRAY_A
		);

		$this->assertEquals( 201, $response->get_status() );
		$this->assertNotEmpty( $row );
		$this->assertSame( 'fixed', $data['price_mode'] );
		$this->assertSame( '120.00', $data['fixed_price'] );
	}

	public function test_create_valid_discount_package() {
		$request = new WP_REST_Request( 'POST', '/' . $this->namespace . '/dashboard/package-types' );
		$request->set_body_params(
			array(
				'name'                => 'Discount Package',
				'sessions_count'      => 8,
				'price_mode'          => 'discount',
				'discount_percentage' => 20,
				'expiry_enabled'      => false,
			)
		);
		$response = rest_get_server()->dispatch( $request );
		$data     = $response->get_data();

		$this->assertEquals( 201, $response->get_status() );
		$this->assertSame( 'discount', $data['price_mode'] );
		$this->assertSame( '20.00', $data['discount_percentage'] );
		$this->assertNull( $data['fixed_price'] );
	}

	public function test_create_requires_name() {
		$payload = $this->get_valid_fixed_payload();
		unset( $payload['name'] );

		$request = new WP_REST_Request( 'POST', '/' . $this->namespace . '/dashboard/package-types' );
		$request->set_body_params( $payload );
		$response = rest_get_server()->dispatch( $request );

		$this->assertEquals( 400, $response->get_status() );
	}

	public function test_create_requires_sessions_count() {
		$payload = $this->get_valid_fixed_payload();
		unset( $payload['sessions_count'] );

		$request = new WP_REST_Request( 'POST', '/' . $this->namespace . '/dashboard/package-types' );
		$request->set_body_params( $payload );
		$response = rest_get_server()->dispatch( $request );

		$this->assertEquals( 400, $response->get_status() );
	}

	public function test_create_fixed_price_requires_fixed_price_field() {
		$payload = $this->get_valid_fixed_payload();
		unset( $payload['fixed_price'] );

		$request = new WP_REST_Request( 'POST', '/' . $this->namespace . '/dashboard/package-types' );
		$request->set_body_params( $payload );
		$response = rest_get_server()->dispatch( $request );

		$this->assertEquals( 400, $response->get_status() );
	}

	public function test_create_discount_requires_discount_percentage() {
		$request = new WP_REST_Request( 'POST', '/' . $this->namespace . '/dashboard/package-types' );
		$request->set_body_params(
			array(
				'name'           => 'Discount Package',
				'sessions_count' => 6,
				'price_mode'     => 'discount',
			)
		);
		$response = rest_get_server()->dispatch( $request );

		$this->assertEquals( 400, $response->get_status() );
	}

	public function test_create_expiry_enabled_requires_expiry_days() {
		$payload                   = $this->get_valid_fixed_payload();
		$payload['expiry_enabled'] = true;
		unset( $payload['expiry_days'] );

		$request = new WP_REST_Request( 'POST', '/' . $this->namespace . '/dashboard/package-types' );
		$request->set_body_params( $payload );
		$response = rest_get_server()->dispatch( $request );

		$this->assertEquals( 400, $response->get_status() );
	}

	public function test_create_fires_audit_log() {
		global $wpdb;

		$request = new WP_REST_Request( 'POST', '/' . $this->namespace . '/dashboard/package-types' );
		$request->set_body_params( $this->get_valid_fixed_payload() );
		$response = rest_get_server()->dispatch( $request );

		$audit_row = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT action FROM {$wpdb->prefix}bookings_audit_log WHERE action = %s ORDER BY id DESC LIMIT 1",
				'package_type.created'
			),
			ARRAY_A
		);

		$this->assertEquals( 201, $response->get_status() );
		$this->assertNotEmpty( $audit_row );
		$this->assertSame( 'package_type.created', $audit_row['action'] );
	}

	public function test_get_single_returns_correct_package() {
		$package_id = $this->insert_package_type(
			array(
				'name' => 'Single Package',
			)
		);

		$request  = new WP_REST_Request( 'GET', '/' . $this->namespace . '/dashboard/package-types/' . $package_id );
		$response = rest_get_server()->dispatch( $request );
		$data     = $response->get_data();

		$this->assertEquals( 200, $response->get_status() );
		$this->assertSame( $package_id, $data['id'] );
		$this->assertSame( 'Single Package', $data['name'] );
	}

	public function test_get_single_returns_404_for_missing() {
		$request  = new WP_REST_Request( 'GET', '/' . $this->namespace . '/dashboard/package-types/999999' );
		$response = rest_get_server()->dispatch( $request );
		$data     = $response->get_data();

		$this->assertEquals( 404, $response->get_status() );
		$this->assertSame( 'E5001', $data['code'] );
	}

	public function test_update_changes_name() {
		global $wpdb;

		$package_id = $this->insert_package_type();

		$request = new WP_REST_Request( 'PUT', '/' . $this->namespace . '/dashboard/package-types/' . $package_id );
		$request->set_body_params(
			array(
				'name' => 'Updated Package Name',
			)
		);
		$response = rest_get_server()->dispatch( $request );
		$data     = $response->get_data();

		$db_name = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT name FROM {$wpdb->prefix}bookings_package_types WHERE id = %d",
				$package_id
			)
		);

		$this->assertEquals( 200, $response->get_status() );
		$this->assertSame( 'Updated Package Name', $data['name'] );
		$this->assertSame( 'Updated Package Name', $db_name );
	}

	public function test_update_returns_404_for_missing() {
		$request = new WP_REST_Request( 'PUT', '/' . $this->namespace . '/dashboard/package-types/999999' );
		$request->set_body_params( array( 'name' => 'Will Fail' ) );
		$response = rest_get_server()->dispatch( $request );
		$data     = $response->get_data();

		$this->assertEquals( 404, $response->get_status() );
		$this->assertSame( 'E5001', $data['code'] );
	}

	public function test_update_fires_audit_log() {
		global $wpdb;

		$package_id = $this->insert_package_type();

		$request = new WP_REST_Request( 'PUT', '/' . $this->namespace . '/dashboard/package-types/' . $package_id );
		$request->set_body_params( array( 'name' => 'Renamed Package' ) );
		$response = rest_get_server()->dispatch( $request );

		$audit_row = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT action FROM {$wpdb->prefix}bookings_audit_log WHERE action = %s ORDER BY id DESC LIMIT 1",
				'package_type.updated'
			),
			ARRAY_A
		);

		$this->assertEquals( 200, $response->get_status() );
		$this->assertNotEmpty( $audit_row );
		$this->assertSame( 'package_type.updated', $audit_row['action'] );
	}

	public function test_deactivate_sets_is_active_false() {
		global $wpdb;

		$package_id = $this->insert_package_type( array( 'is_active' => 1 ) );

		$request  = new WP_REST_Request( 'POST', '/' . $this->namespace . '/dashboard/package-types/' . $package_id . '/deactivate' );
		$response = rest_get_server()->dispatch( $request );

		$is_active = (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT is_active FROM {$wpdb->prefix}bookings_package_types WHERE id = %d",
				$package_id
			)
		);

		$this->assertEquals( 200, $response->get_status() );
		$this->assertSame( 0, $is_active );
	}

	public function test_deactivate_returns_404_for_missing() {
		$request  = new WP_REST_Request( 'POST', '/' . $this->namespace . '/dashboard/package-types/999999/deactivate' );
		$response = rest_get_server()->dispatch( $request );
		$data     = $response->get_data();

		$this->assertEquals( 404, $response->get_status() );
		$this->assertSame( 'E5001', $data['code'] );
	}

	public function test_deactivate_fires_audit_log() {
		global $wpdb;

		$package_id = $this->insert_package_type( array( 'is_active' => 1 ) );

		$request  = new WP_REST_Request( 'POST', '/' . $this->namespace . '/dashboard/package-types/' . $package_id . '/deactivate' );
		$response = rest_get_server()->dispatch( $request );

		$audit_row = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT action FROM {$wpdb->prefix}bookings_audit_log WHERE action = %s ORDER BY id DESC LIMIT 1",
				'package_type.deactivated'
			),
			ARRAY_A
		);

		$this->assertEquals( 200, $response->get_status() );
		$this->assertNotEmpty( $audit_row );
		$this->assertSame( 'package_type.deactivated', $audit_row['action'] );
	}

	public function test_deactivating_package_type_does_not_affect_active_customer_packages() {
		global $wpdb;

		$this->ensure_customer_packages_table_exists();
		bookit_test_truncate_tables(
			array(
				'bookings_customer_packages',
				'bookings_customers',
			)
		);

		$customer_id         = $this->insert_customer();
		$package_type_id     = $this->insert_package_type( array( 'is_active' => 1 ) );
		$customer_package_id = $this->insert_customer_package(
			array(
				'customer_id'        => $customer_id,
				'package_type_id'    => $package_type_id,
				'status'             => 'active',
				'sessions_total'     => 8,
				'sessions_remaining' => 8,
			)
		);

		$request  = new WP_REST_Request( 'POST', '/' . $this->namespace . '/dashboard/package-types/' . $package_type_id . '/deactivate' );
		$response = rest_get_server()->dispatch( $request );

		$row = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT status, sessions_remaining FROM {$wpdb->prefix}bookings_customer_packages WHERE id = %d",
				$customer_package_id
			),
			ARRAY_A
		);

		$this->assertEquals( 200, $response->get_status() );
		$this->assertSame( 'active', $row['status'] );
		$this->assertSame( 8, (int) $row['sessions_remaining'] );
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
			'name'                   => 'Default Package',
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
	 * Valid fixed-price payload.
	 *
	 * @return array
	 */
	private function get_valid_fixed_payload() {
		return array(
			'name'                   => '10-session block',
			'description'            => 'Ten sessions package',
			'sessions_count'         => 10,
			'price_mode'             => 'fixed',
			'fixed_price'            => 120,
			'expiry_enabled'         => false,
			'applicable_service_ids' => array( 1, 2 ),
		);
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

		$staff_id                   = (int) $wpdb->insert_id;
		$this->created_staff_ids[]  = $staff_id;

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
