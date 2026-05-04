<?php
/**
 * Tests for Bookit_Staff_Model (Staff Selection UI - Task 3)
 *
 * @package    Bookit_Booking_System
 * @subpackage Tests
 */

/**
 * Test Bookit_Staff_Model class.
 */
class Test_Staff_Model extends WP_UnitTestCase {

	/**
	 * Staff model instance.
	 *
	 * @var Bookit_Staff_Model
	 */
	private $staff_model;

	/**
	 * Set up each test.
	 */
	public function setUp(): void {
		parent::setUp();

		global $wpdb;

		$wpdb->query( "TRUNCATE TABLE {$wpdb->prefix}bookings_staff" );
		$wpdb->query( "TRUNCATE TABLE {$wpdb->prefix}bookings_staff_services" );
		$wpdb->query( "TRUNCATE TABLE {$wpdb->prefix}bookings_services" );
		$wpdb->query( "TRUNCATE TABLE {$wpdb->prefix}bookings_categories" );
		$wpdb->query( "TRUNCATE TABLE {$wpdb->prefix}bookings_service_categories" );

		$column_exists = $wpdb->get_results(
			$wpdb->prepare(
				'SHOW COLUMNS FROM %1s LIKE %s',
				$wpdb->prefix . 'bookings_staff_services',
				'custom_price'
			)
		);
		if ( empty( $column_exists ) ) {
			$wpdb->query(
				"ALTER TABLE {$wpdb->prefix}bookings_staff_services 
				ADD COLUMN custom_price DECIMAL(10,2) NULL DEFAULT NULL 
				COMMENT 'Custom price for this staff member for this service'"
			);
		}

		$this->staff_model = new Bookit_Staff_Model();
	}

	/**
	 * Tear down each test.
	 */
	public function tearDown(): void {
		global $wpdb;

		$wpdb->query( "TRUNCATE TABLE {$wpdb->prefix}bookings_staff" );
		$wpdb->query( "TRUNCATE TABLE {$wpdb->prefix}bookings_staff_services" );
		$wpdb->query( "TRUNCATE TABLE {$wpdb->prefix}bookings_services" );
		$wpdb->query( "TRUNCATE TABLE {$wpdb->prefix}bookings_categories" );
		$wpdb->query( "TRUNCATE TABLE {$wpdb->prefix}bookings_service_categories" );

		parent::tearDown();
	}

	/**
	 * Test get_staff_for_service returns only active staff.
	 *
	 * @covers Bookit_Staff_Model::get_staff_for_service
	 */
	public function test_get_staff_for_service_returns_only_active_staff() {
		$service_id = $this->create_test_service();
		$active_staff_id   = $this->create_test_staff( array( 'first_name' => 'Active', 'is_active' => 1 ) );
		$inactive_staff_id = $this->create_test_staff( array( 'first_name' => 'Inactive', 'is_active' => 0 ) );

		$this->link_staff_to_service( $active_staff_id, $service_id );
		$this->link_staff_to_service( $inactive_staff_id, $service_id );

		$staff = $this->staff_model->get_staff_for_service( $service_id );

		$this->assertCount( 1, $staff );
		$this->assertEquals( $active_staff_id, (int) $staff[0]['id'] );
		$this->assertEquals( 'Active', $staff[0]['first_name'] );
	}

	/**
	 * Test get_staff_for_service returns only staff offering the service.
	 *
	 * @covers Bookit_Staff_Model::get_staff_for_service
	 */
	public function test_get_staff_for_service_returns_only_staff_offering_service() {
		$service_a = $this->create_test_service( array( 'name' => 'Service A' ) );
		$service_b = $this->create_test_service( array( 'name' => 'Service B' ) );
		$staff_a   = $this->create_test_staff( array( 'first_name' => 'Alice' ) );
		$staff_b   = $this->create_test_staff( array( 'first_name' => 'Bob' ) );

		$this->link_staff_to_service( $staff_a, $service_a );
		$this->link_staff_to_service( $staff_b, $service_b );

		$staff = $this->staff_model->get_staff_for_service( $service_a );

		$this->assertCount( 1, $staff );
		$this->assertEquals( $staff_a, (int) $staff[0]['id'] );
		$this->assertEquals( 'Alice', $staff[0]['first_name'] );
	}

	/**
	 * Test get_staff_for_service sorts alphabetically by first_name.
	 *
	 * @covers Bookit_Staff_Model::get_staff_for_service
	 */
	public function test_get_staff_for_service_sorts_alphabetically_by_first_name() {
		$service_id = $this->create_test_service();
		$charlie_id = $this->create_test_staff( array( 'first_name' => 'Charlie', 'last_name' => 'A' ) );
		$alice_id   = $this->create_test_staff( array( 'first_name' => 'Alice', 'last_name' => 'B' ) );
		$bob_id     = $this->create_test_staff( array( 'first_name' => 'Bob', 'last_name' => 'C' ) );

		$this->link_staff_to_service( $charlie_id, $service_id );
		$this->link_staff_to_service( $alice_id, $service_id );
		$this->link_staff_to_service( $bob_id, $service_id );

		$staff = $this->staff_model->get_staff_for_service( $service_id );

		$this->assertCount( 3, $staff );
		$this->assertEquals( 'Alice', $staff[0]['first_name'] );
		$this->assertEquals( 'Bob', $staff[1]['first_name'] );
		$this->assertEquals( 'Charlie', $staff[2]['first_name'] );
	}

	/**
	 * Test get_staff_for_service uses custom_price when present.
	 *
	 * @covers Bookit_Staff_Model::get_staff_for_service
	 */
	public function test_get_staff_for_service_uses_custom_price_when_present() {
		$service_id = $this->create_test_service( array( 'price' => 50.00 ) );
		$staff_id   = $this->create_test_staff();
		$this->link_staff_to_service( $staff_id, $service_id, 60.00 );

		$staff = $this->staff_model->get_staff_for_service( $service_id );

		$this->assertCount( 1, $staff );
		$this->assertEquals( 60.00, (float) $staff[0]['price'] );
	}

	/**
	 * Test get_staff_for_service falls back to service price when custom_price is NULL.
	 *
	 * @covers Bookit_Staff_Model::get_staff_for_service
	 */
	public function test_get_staff_for_service_falls_back_to_service_price() {
		$service_id = $this->create_test_service( array( 'price' => 50.00 ) );
		$staff_id   = $this->create_test_staff();
		$this->link_staff_to_service( $staff_id, $service_id, null );

		$staff = $this->staff_model->get_staff_for_service( $service_id );

		$this->assertCount( 1, $staff );
		$this->assertEquals( 50.00, (float) $staff[0]['price'] );
	}

	/**
	 * Test get_staff_for_service returns empty array when no staff available.
	 *
	 * @covers Bookit_Staff_Model::get_staff_for_service
	 */
	public function test_get_staff_for_service_returns_empty_array_when_no_staff() {
		$service_id = $this->create_test_service();
		// No staff linked to this service.

		$staff = $this->staff_model->get_staff_for_service( $service_id );

		$this->assertIsArray( $staff );
		$this->assertEmpty( $staff );
	}

	/**
	 * Test get_lowest_staff_price_for_service returns minimum price.
	 *
	 * @covers Bookit_Staff_Model::get_lowest_staff_price_for_service
	 */
	public function test_get_lowest_staff_price_for_service_returns_minimum() {
		$service_id = $this->create_test_service( array( 'price' => 50.00 ) );
		$staff1     = $this->create_test_staff( array( 'first_name' => 'A' ) );
		$staff2     = $this->create_test_staff( array( 'first_name' => 'B' ) );
		$staff3     = $this->create_test_staff( array( 'first_name' => 'C' ) );

		$this->link_staff_to_service( $staff1, $service_id, 45.00 );
		$this->link_staff_to_service( $staff2, $service_id, 40.00 );
		$this->link_staff_to_service( $staff3, $service_id, 55.00 );

		$lowest = $this->staff_model->get_lowest_staff_price_for_service( $service_id );

		$this->assertEquals( 40.00, $lowest );
	}

	/**
	 * Test get_lowest_staff_price considers both custom and base prices.
	 *
	 * @covers Bookit_Staff_Model::get_lowest_staff_price_for_service
	 */
	public function test_get_lowest_staff_price_considers_custom_and_base_prices() {
		$service_id = $this->create_test_service( array( 'price' => 50.00 ) );
		$staff1     = $this->create_test_staff( array( 'first_name' => 'A' ) );
		$staff2     = $this->create_test_staff( array( 'first_name' => 'B' ) );

		$this->link_staff_to_service( $staff1, $service_id, null );
		$this->link_staff_to_service( $staff2, $service_id, 35.00 );

		$lowest = $this->staff_model->get_lowest_staff_price_for_service( $service_id );

		$this->assertEquals( 35.00, $lowest );
	}

	/**
	 * Test get_staff_by_id returns staff with correct fields.
	 *
	 * @covers Bookit_Staff_Model::get_staff_by_id
	 */
	public function test_get_staff_by_id_returns_correct_data() {
		$staff_id = $this->create_test_staff( array(
			'first_name' => 'Jane',
			'last_name'  => 'Doe',
			'email'      => 'jane.doe@example.com',
			'phone'      => '07700900123',
			'title'      => 'Senior Therapist',
		) );

		$staff = $this->staff_model->get_staff_by_id( $staff_id );

		$this->assertIsArray( $staff );
		$this->assertEquals( $staff_id, (int) $staff['id'] );
		$this->assertEquals( 'Jane', $staff['first_name'] );
		$this->assertEquals( 'Doe', $staff['last_name'] );
		$this->assertEquals( 'Jane Doe', $staff['full_name'] );
		$this->assertEquals( 'jane.doe@example.com', $staff['email'] );
		$this->assertEquals( '07700900123', $staff['phone'] );
		$this->assertEquals( 'Senior Therapist', $staff['title'] );
		$this->assertEquals( 1, (int) $staff['is_active'] );
	}

	/**
	 * Test inactive staff (is_active = 0) excluded from get_staff_by_id.
	 *
	 * @covers Bookit_Staff_Model::get_staff_by_id
	 */
	public function test_inactive_staff_excluded_from_results() {
		$staff_id = $this->create_test_staff( array( 'is_active' => 0 ) );

		$staff = $this->staff_model->get_staff_by_id( $staff_id );

		$this->assertNull( $staff );
	}

	/**
	 * Test get_staff_by_id returns null for invalid ID.
	 *
	 * @covers Bookit_Staff_Model::get_staff_by_id
	 */
	public function test_get_staff_by_id_returns_null_for_invalid_id() {
		$staff = $this->staff_model->get_staff_by_id( 99999 );
		$this->assertNull( $staff );
	}

	/**
	 * Test get_staff_for_service returns empty when all staff inactive.
	 *
	 * @covers Bookit_Staff_Model::get_staff_for_service
	 */
	public function test_get_staff_for_service_returns_empty_when_all_staff_inactive() {
		$service_id = $this->create_test_service();
		$staff_id   = $this->create_test_staff( array( 'is_active' => 0 ) );
		$this->link_staff_to_service( $staff_id, $service_id );

		$staff = $this->staff_model->get_staff_for_service( $service_id );

		$this->assertIsArray( $staff );
		$this->assertEmpty( $staff );
	}

	/**
	 * Test get_staff_for_service excludes soft-deleted staff.
	 *
	 * @covers Bookit_Staff_Model::get_staff_for_service
	 */
	public function test_get_staff_for_service_excludes_soft_deleted_staff() {
		$service_id = $this->create_test_service();
		$staff_id   = $this->create_test_staff( array( 'deleted_at' => current_time( 'mysql' ) ) );
		$this->link_staff_to_service( $staff_id, $service_id );

		$staff = $this->staff_model->get_staff_for_service( $service_id );

		$this->assertIsArray( $staff );
		$this->assertEmpty( $staff );
	}

	/**
	 * Test get_lowest_staff_price_for_service returns 0 when no staff.
	 *
	 * @covers Bookit_Staff_Model::get_lowest_staff_price_for_service
	 */
	public function test_get_lowest_staff_price_returns_zero_when_no_staff() {
		$service_id = $this->create_test_service();
		$lowest     = $this->staff_model->get_lowest_staff_price_for_service( $service_id );
		$this->assertEquals( 0.00, $lowest );
	}

	// ========== HELPER METHODS ==========

	/**
	 * Create test staff member.
	 *
	 * @param array $args Override defaults.
	 * @return int Staff ID.
	 */
	protected function create_test_staff( $args = array() ) {
		global $wpdb;

		$defaults = array(
			'email'            => 'test-' . wp_generate_password( 6, false ) . '@example.com',
			'password_hash'    => wp_hash_password( 'password123' ),
			'first_name'       => 'Test',
			'last_name'        => 'Staff',
			'phone'            => '07700900000',
			'photo_url'        => null,
			'bio'              => 'Test bio',
			'title'            => 'Senior Therapist',
			'role'             => 'staff',
			'google_calendar_id' => null,
			'is_active'        => 1,
			'display_order'    => 0,
			'created_at'       => current_time( 'mysql' ),
			'updated_at'       => current_time( 'mysql' ),
			'deleted_at'       => null,
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
	 * @param array $args Override defaults.
	 * @return int Service ID.
	 */
	protected function create_test_service( $args = array() ) {
		global $wpdb;

		$defaults = array(
			'name'          => 'Test Service ' . wp_generate_password( 4, false ),
			'description'   => 'Test service description',
			'duration'      => 60,
			'price'         => 50.00,
			'deposit_amount' => 10.00,
			'deposit_type'  => 'fixed',
			'buffer_before' => 0,
			'buffer_after'  => 0,
			'is_active'     => 1,
			'display_order' => 0,
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
	 * Link staff to service with optional custom price.
	 *
	 * @param int        $staff_id     Staff ID.
	 * @param int        $service_id   Service ID.
	 * @param float|null $custom_price Custom price or null to use service.price.
	 */
	protected function link_staff_to_service( $staff_id, $service_id, $custom_price = null ) {
		global $wpdb;

		$data   = array(
			'staff_id'     => $staff_id,
			'service_id'   => $service_id,
			'custom_price' => $custom_price,
			'created_at'   => current_time( 'mysql' ),
		);
		$format = array( '%d', '%d', $custom_price === null ? '%s' : '%f', '%s' );

		$wpdb->insert(
			$wpdb->prefix . 'bookings_staff_services',
			$data,
			$format
		);
	}
}
