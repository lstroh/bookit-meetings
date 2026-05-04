<?php
/**
 * Security input validation tests.
 *
 * @package    Bookit_Booking_System
 * @subpackage Tests
 */

/**
 * Test security-focused input validation hardening.
 */
class Test_Security_Input_Validation extends WP_UnitTestCase {

	/**
	 * REST namespace.
	 *
	 * @var string
	 */
	private $namespace = 'bookit/v1';

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
				'bookings_package_types',
				'bookings_customer_packages',
				'bookings_customers',
			)
		);

		$_SESSION = array();
		do_action( 'rest_api_init' );

		$admin_id = $this->create_test_staff( array( 'role' => 'admin' ) );
		$this->login_as( $admin_id, 'admin' );
	}

	/**
	 * Tear down each test.
	 */
	public function tearDown(): void {
		global $wpdb;

		bookit_test_truncate_tables(
			array(
				'bookings_package_types',
				'bookings_customer_packages',
				'bookings_customers',
			)
		);

		foreach ( array_unique( $this->created_staff_ids ) as $staff_id ) {
			$wpdb->delete( $wpdb->prefix . 'bookings_staff', array( 'id' => (int) $staff_id ), array( '%d' ) );
		}

		$_SESSION = array();

		parent::tearDown();
	}

	/**
	 * Reject non-JSON applicable_service_ids payload.
	 *
	 * @covers Bookit_Package_Types_API::prepare_write_payload
	 */
	public function test_applicable_service_ids_non_array_rejected() {
		$request = new WP_REST_Request( 'POST', '/' . $this->namespace . '/dashboard/package-types' );
		$request->set_body_params(
			array(
				'name'                   => 'Package With Invalid Services',
				'sessions_count'         => 5,
				'price_mode'             => 'fixed',
				'fixed_price'            => 100,
				'applicable_service_ids' => 'not-json',
			)
		);

		$response = rest_get_server()->dispatch( $request );

		$this->assertEquals( 400, $response->get_status() );
	}

	/**
	 * Reject malformed expires_at values.
	 *
	 * @covers Bookit_Customer_Packages_API::create_customer_package
	 */
	public function test_customer_package_invalid_expires_at_rejected() {
		$customer_id = $this->insert_customer();
		$type_id     = $this->insert_package_type();

		$request = new WP_REST_Request( 'POST', '/' . $this->namespace . '/dashboard/customer-packages' );
		$request->set_body_params(
			array(
				'customer_id'     => $customer_id,
				'package_type_id' => $type_id,
				'expires_at'      => 'not-a-date',
			)
		);

		$response = rest_get_server()->dispatch( $request );

		$this->assertEquals( 400, $response->get_status() );
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
