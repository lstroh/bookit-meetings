<?php
/**
 * Tests for Available Packages API.
 *
 * @package    Bookit_Booking_System
 * @subpackage Tests
 */

/**
 * Test available packages endpoint for booking wizard.
 */
class Test_Available_Packages_API extends WP_UnitTestCase {

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

		bookit_test_truncate_tables(
			array(
				'bookings_package_types',
				'bookings_settings',
			)
		);
		$this->set_packages_enabled( '1' );
		do_action( 'rest_api_init' );
	}

	/**
	 * Tear down each test.
	 */
	public function tearDown(): void {
		global $wpdb;

		bookit_test_truncate_tables(
			array(
				'bookings_package_types',
				'bookings_settings',
			)
		);

		foreach ( array_unique( $this->created_staff_ids ) as $staff_id ) {
			$wpdb->delete( $wpdb->prefix . 'bookings_staff', array( 'id' => (int) $staff_id ), array( '%d' ) );
		}

		$_SESSION = array();

		parent::tearDown();
	}

	public function test_endpoint_is_registered() {
		$routes = rest_get_server()->get_routes();
		$this->assertArrayHasKey( '/bookit/v1/wizard/available-packages', $routes );
	}

	public function test_endpoint_is_public() {
		$request = new WP_REST_Request( 'GET', '/' . $this->namespace . '/wizard/available-packages' );
		$request->set_param( 'service_id', 1 );

		$response = rest_get_server()->dispatch( $request );
		$this->assertEquals( 200, $response->get_status() );
	}

	public function test_service_id_is_required() {
		$request  = new WP_REST_Request( 'GET', '/' . $this->namespace . '/wizard/available-packages' );
		$response = rest_get_server()->dispatch( $request );

		$this->assertEquals( 400, $response->get_status() );
	}

	public function test_returns_empty_array_when_no_packages() {
		$request = new WP_REST_Request( 'GET', '/' . $this->namespace . '/wizard/available-packages' );
		$request->set_param( 'service_id', 1 );

		$response = rest_get_server()->dispatch( $request );
		$data     = $response->get_data();

		$this->assertEquals( 200, $response->get_status() );
		$this->assertSame( array(), $data );
	}

	public function test_returns_packages_applicable_to_all_services() {
		$package_id = $this->insert_package_type(
			array(
				'name'                   => 'All Services Package',
				'applicable_service_ids' => null,
				'is_active'              => 1,
			)
		);

		$request = new WP_REST_Request( 'GET', '/' . $this->namespace . '/wizard/available-packages' );
		$request->set_param( 'service_id', 999 );

		$response = rest_get_server()->dispatch( $request );
		$data     = $response->get_data();

		$this->assertEquals( 200, $response->get_status() );
		$this->assertCount( 1, $data );
		$this->assertSame( $package_id, $data[0]['id'] );
	}

	public function test_returns_packages_matching_service_id() {
		$package_id = $this->insert_package_type(
			array(
				'name'                   => 'Service 2 or 3 Package',
				'applicable_service_ids' => wp_json_encode( array( 2, 3 ) ),
				'is_active'              => 1,
			)
		);

		$request = new WP_REST_Request( 'GET', '/' . $this->namespace . '/wizard/available-packages' );
		$request->set_param( 'service_id', 2 );

		$response = rest_get_server()->dispatch( $request );
		$data     = $response->get_data();

		$this->assertEquals( 200, $response->get_status() );
		$this->assertCount( 1, $data );
		$this->assertSame( $package_id, $data[0]['id'] );
	}

	public function test_excludes_packages_not_matching_service_id() {
		$this->insert_package_type(
			array(
				'name'                   => 'Service 5 Only',
				'applicable_service_ids' => wp_json_encode( array( 5 ) ),
				'is_active'              => 1,
			)
		);

		$request = new WP_REST_Request( 'GET', '/' . $this->namespace . '/wizard/available-packages' );
		$request->set_param( 'service_id', 2 );

		$response = rest_get_server()->dispatch( $request );
		$data     = $response->get_data();

		$this->assertEquals( 200, $response->get_status() );
		$this->assertSame( array(), $data );
	}

	public function test_excludes_inactive_packages() {
		$this->insert_package_type(
			array(
				'name'                   => 'Inactive Package',
				'applicable_service_ids' => null,
				'is_active'              => 0,
			)
		);

		$request = new WP_REST_Request( 'GET', '/' . $this->namespace . '/wizard/available-packages' );
		$request->set_param( 'service_id', 1 );

		$response = rest_get_server()->dispatch( $request );
		$data     = $response->get_data();

		$this->assertEquals( 200, $response->get_status() );
		$this->assertSame( array(), $data );
	}

	public function test_response_shape() {
		$this->insert_package_type(
			array(
				'name'           => 'Shape Package',
				'sessions_count' => 12,
				'price_mode'     => 'fixed',
				'fixed_price'    => 149.99,
				'expiry_enabled' => 1,
				'expiry_days'    => 90,
				'is_active'      => 1,
			)
		);

		$request = new WP_REST_Request( 'GET', '/' . $this->namespace . '/wizard/available-packages' );
		$request->set_param( 'service_id', 1 );

		$response = rest_get_server()->dispatch( $request );
		$data     = $response->get_data();

		$this->assertEquals( 200, $response->get_status() );
		$this->assertNotEmpty( $data );
		$this->assertArrayHasKey( 'id', $data[0] );
		$this->assertArrayHasKey( 'name', $data[0] );
		$this->assertArrayHasKey( 'sessions_count', $data[0] );
		$this->assertArrayHasKey( 'price_mode', $data[0] );
		$this->assertArrayHasKey( 'fixed_price', $data[0] );
		$this->assertArrayHasKey( 'expiry_enabled', $data[0] );
		$this->assertArrayHasKey( 'expiry_days', $data[0] );
	}

	public function test_returns_empty_when_packages_disabled() {
		$this->set_packages_enabled( '0' );
		$this->insert_package_type(
			array(
				'name'      => 'Disabled by Settings',
				'is_active' => 1,
			)
		);

		$request = new WP_REST_Request( 'GET', '/' . $this->namespace . '/wizard/available-packages' );
		$request->set_param( 'service_id', 1 );

		$response = rest_get_server()->dispatch( $request );
		$data     = $response->get_data();

		$this->assertEquals( 200, $response->get_status() );
		$this->assertSame( array(), $data );
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
	 * Set packages_enabled setting value.
	 *
	 * @param string $value Setting value.
	 * @return void
	 */
	private function set_packages_enabled( $value ) {
		global $wpdb;

		$settings_table = $wpdb->prefix . 'bookings_settings';
		$existing_id    = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT id FROM {$settings_table} WHERE setting_key = %s LIMIT 1",
				'packages_enabled'
			)
		);

		if ( $existing_id ) {
			$wpdb->update(
				$settings_table,
				array(
					'setting_value' => (string) $value,
					'setting_type'  => 'boolean',
				),
				array( 'id' => (int) $existing_id ),
				array( '%s', '%s' ),
				array( '%d' )
			);
			return;
		}

		$wpdb->insert(
			$settings_table,
			array(
				'setting_key'   => 'packages_enabled',
				'setting_value' => (string) $value,
				'setting_type'  => 'boolean',
			),
			array( '%s', '%s', '%s' )
		);
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
}
