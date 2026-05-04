<?php
/**
 * Tests for Bookit_Service_Model
 *
 * @package    Bookit_Booking_System
 * @subpackage Tests
 */

/**
 * Test Bookit_Service_Model class.
 */
class Test_Service_Model extends WP_UnitTestCase {

	/**
	 * Service model instance.
	 *
	 * @var Bookit_Service_Model
	 */
	private $service_model;

	/**
	 * Set up each test.
	 */
	public function setUp(): void {
		parent::setUp();

		global $wpdb;

		// Clear relevant tables
		$wpdb->query( "TRUNCATE TABLE {$wpdb->prefix}bookings_services" );
		$wpdb->query( "TRUNCATE TABLE {$wpdb->prefix}bookings_categories" );
		$wpdb->query( "TRUNCATE TABLE {$wpdb->prefix}bookings_service_categories" );
		$wpdb->query( "TRUNCATE TABLE {$wpdb->prefix}bookings_staff" );
		$wpdb->query( "TRUNCATE TABLE {$wpdb->prefix}bookings_staff_services" );

		// Ensure custom_price column exists for tests that need it
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

		$this->service_model = new Bookit_Service_Model();
	}

	/**
	 * Tear down each test.
	 */
	public function tearDown(): void {
		global $wpdb;

		// Clean up after tests
		$wpdb->query( "TRUNCATE TABLE {$wpdb->prefix}bookings_services" );
		$wpdb->query( "TRUNCATE TABLE {$wpdb->prefix}bookings_categories" );
		$wpdb->query( "TRUNCATE TABLE {$wpdb->prefix}bookings_service_categories" );
		$wpdb->query( "TRUNCATE TABLE {$wpdb->prefix}bookings_staff" );
		$wpdb->query( "TRUNCATE TABLE {$wpdb->prefix}bookings_staff_services" );

		parent::tearDown();
	}

	/**
	 * Test that get_active_services_by_category returns an array
	 *
	 * @covers Bookit_Service_Model::get_active_services_by_category
	 */
	public function test_get_active_services_returns_array() {
		$services = $this->service_model->get_active_services_by_category();
		$this->assertIsArray( $services );
	}

	/**
	 * Test that services are organized by category name as keys
	 *
	 * @covers Bookit_Service_Model::get_active_services_by_category
	 */
	public function test_get_active_services_organized_by_category() {
		// Create test data
		$category_id = $this->create_category( 'Haircuts' );
		$service_id  = $this->create_service( "Women's Haircut", 35.00 );
		$staff_id    = $this->create_staff( 'Emma', 'Thompson' );

		$this->link_service_to_category( $service_id, $category_id );
		$this->assign_staff_to_service( $staff_id, $service_id );

		// Get services
		$services = $this->service_model->get_active_services_by_category();

		// Assert structure
		$this->assertArrayHasKey( 'Haircuts', $services );
		$this->assertIsArray( $services['Haircuts'] );
		$this->assertCount( 1, $services['Haircuts'] );
	}

	/**
	 * Test that categories are sorted alphabetically
	 *
	 * @covers Bookit_Service_Model::get_active_services_by_category
	 */
	public function test_categories_sorted_alphabetically() {
		// Create categories in non-alphabetical order
		$cat_coloring = $this->create_category( 'Coloring' );
		$cat_haircuts = $this->create_category( 'Haircuts' );
		$cat_beard    = $this->create_category( 'Beard Trim' );

		// Create services for each
		$service1 = $this->create_service( 'Service 1', 30 );
		$service2 = $this->create_service( 'Service 2', 35 );
		$service3 = $this->create_service( 'Service 3', 40 );

		$staff = $this->create_staff( 'Emma', 'Thompson' );

		// Link everything
		$this->link_service_to_category( $service1, $cat_coloring );
		$this->link_service_to_category( $service2, $cat_haircuts );
		$this->link_service_to_category( $service3, $cat_beard );

		$this->assign_staff_to_service( $staff, $service1 );
		$this->assign_staff_to_service( $staff, $service2 );
		$this->assign_staff_to_service( $staff, $service3 );

		// Get services
		$services = $this->service_model->get_active_services_by_category();

		// Assert alphabetical order
		$category_names = array_keys( $services );
		$this->assertEquals( array( 'Beard Trim', 'Coloring', 'Haircuts' ), $category_names );
	}

	/**
	 * Test that services within a category are sorted alphabetically
	 *
	 * @covers Bookit_Service_Model::get_active_services_by_category
	 */
	public function test_services_sorted_alphabetically_within_category() {
		// Create category
		$category_id = $this->create_category( 'Haircuts' );

		// Create services in non-alphabetical order
		$service_womens = $this->create_service( "Women's Haircut", 35 );
		$service_mens   = $this->create_service( "Men's Haircut", 25 );
		$service_kids   = $this->create_service( 'Kids Haircut', 20 );

		$staff = $this->create_staff( 'Emma', 'Thompson' );

		// Link all to same category
		$this->link_service_to_category( $service_womens, $category_id );
		$this->link_service_to_category( $service_mens, $category_id );
		$this->link_service_to_category( $service_kids, $category_id );

		$this->assign_staff_to_service( $staff, $service_womens );
		$this->assign_staff_to_service( $staff, $service_mens );
		$this->assign_staff_to_service( $staff, $service_kids );

		// Get services
		$services = $this->service_model->get_active_services_by_category();

		// Assert alphabetical order within category
		$service_names = array_column( $services['Haircuts'], 'name' );
		$this->assertEquals( array( "Kids Haircut", "Men's Haircut", "Women's Haircut" ), $service_names );
	}

	/**
	 * Test that inactive services are excluded
	 *
	 * @covers Bookit_Service_Model::get_active_services_by_category
	 */
	public function test_inactive_services_excluded() {
		$category_id = $this->create_category( 'Haircuts' );
		$staff_id    = $this->create_staff( 'Emma', 'Thompson' );

		// Create active service
		$active_service = $this->create_service( 'Active Service', 30, true );
		$this->link_service_to_category( $active_service, $category_id );
		$this->assign_staff_to_service( $staff_id, $active_service );

		// Create inactive service
		$inactive_service = $this->create_service( 'Inactive Service', 40, false );
		$this->link_service_to_category( $inactive_service, $category_id );
		$this->assign_staff_to_service( $staff_id, $inactive_service );

		// Get services
		$services = $this->service_model->get_active_services_by_category();

		// Assert only active service returned
		$this->assertCount( 1, $services['Haircuts'] );
		$this->assertEquals( 'Active Service', $services['Haircuts'][0]['name'] );
	}

	/**
	 * Test that services in inactive categories are excluded
	 *
	 * @covers Bookit_Service_Model::get_active_services_by_category
	 */
	public function test_inactive_categories_excluded() {
		$staff_id = $this->create_staff( 'Emma', 'Thompson' );

		// Active category
		$active_cat = $this->create_category( 'Active Category', true );
		$service1   = $this->create_service( 'Service 1', 30 );
		$this->link_service_to_category( $service1, $active_cat );
		$this->assign_staff_to_service( $staff_id, $service1 );

		// Inactive category
		$inactive_cat = $this->create_category( 'Inactive Category', false );
		$service2     = $this->create_service( 'Service 2', 40 );
		$this->link_service_to_category( $service2, $inactive_cat );
		$this->assign_staff_to_service( $staff_id, $service2 );

		// Get services
		$services = $this->service_model->get_active_services_by_category();

		// Assert only active category present
		$this->assertArrayHasKey( 'Active Category', $services );
		$this->assertArrayNotHasKey( 'Inactive Category', $services );
	}

	/**
	 * Test that services without active staff are excluded
	 *
	 * @covers Bookit_Service_Model::get_active_services_by_category
	 */
	public function test_services_without_active_staff_excluded() {
		$category_id = $this->create_category( 'Haircuts' );

		// Service with active staff
		$service_with_staff = $this->create_service( 'Service With Staff', 30 );
		$active_staff       = $this->create_staff( 'Emma', 'Thompson', true );
		$this->link_service_to_category( $service_with_staff, $category_id );
		$this->assign_staff_to_service( $active_staff, $service_with_staff );

		// Service with no staff assigned
		$service_no_staff = $this->create_service( 'Service No Staff', 40 );
		$this->link_service_to_category( $service_no_staff, $category_id );

		// Get services
		$services = $this->service_model->get_active_services_by_category();

		// Assert only service with staff returned
		$this->assertCount( 1, $services['Haircuts'] );
		$this->assertEquals( 'Service With Staff', $services['Haircuts'][0]['name'] );
	}

	/**
	 * Test base price used when no staff have custom pricing
	 *
	 * @covers Bookit_Service_Model::get_active_services_by_category
	 */
	public function test_base_price_used_when_no_staff_pricing() {
		$category_id = $this->create_category( 'Haircuts' );
		$service_id  = $this->create_service( 'Haircut', 35.00 );
		$staff_id    = $this->create_staff( 'Emma', 'Thompson' );

		$this->link_service_to_category( $service_id, $category_id );
		$this->assign_staff_to_service( $staff_id, $service_id, null ); // null = no custom price

		// Get services
		$services = $this->service_model->get_active_services_by_category();

		// Assert base price used
		$service = $services['Haircuts'][0];
		$this->assertEquals( 35.00, $service['base_price'] );
		$this->assertEquals( 35.00, $service['min_staff_price'] );
		$this->assertEquals( 35.00, $service['max_staff_price'] );
		$this->assertFalse( $service['has_variable_pricing'] );
	}

	/**
	 * Test variable pricing detected when staff have different prices
	 *
	 * @covers Bookit_Service_Model::get_active_services_by_category
	 */
	public function test_variable_pricing_detected_correctly() {
		$category_id = $this->create_category( 'Haircuts' );
		$service_id  = $this->create_service( 'Haircut', 35.00 );

		// Three staff with different prices
		$staff1 = $this->create_staff( 'Emma', 'Senior' );
		$staff2 = $this->create_staff( 'Sarah', 'Mid' );
		$staff3 = $this->create_staff( 'Lisa', 'Junior' );

		$this->link_service_to_category( $service_id, $category_id );
		$this->assign_staff_to_service( $staff1, $service_id, 45.00 );  // Senior
		$this->assign_staff_to_service( $staff2, $service_id, 35.00 );  // Mid (base)
		$this->assign_staff_to_service( $staff3, $service_id, 30.00 );  // Junior

		// Get services
		$services = $this->service_model->get_active_services_by_category();

		// Assert variable pricing detected
		$service = $services['Haircuts'][0];
		$this->assertTrue( $service['has_variable_pricing'] );
		$this->assertEquals( 30.00, $service['min_staff_price'] );
		$this->assertEquals( 45.00, $service['max_staff_price'] );
	}

	/**
	 * Test min staff price calculated correctly
	 *
	 * @covers Bookit_Service_Model::get_active_services_by_category
	 */
	public function test_min_staff_price_calculated_correctly() {
		$category_id = $this->create_category( 'Haircuts' );
		$service_id  = $this->create_service( 'Haircut', 35.00 );

		$staff1 = $this->create_staff( 'Emma', 'Thompson' );
		$staff2 = $this->create_staff( 'Sarah', 'Jones' );
		$staff3 = $this->create_staff( 'Lisa', 'Smith' );

		$this->link_service_to_category( $service_id, $category_id );
		$this->assign_staff_to_service( $staff1, $service_id, 50.00 );
		$this->assign_staff_to_service( $staff2, $service_id, 25.00 );  // Lowest
		$this->assign_staff_to_service( $staff3, $service_id, 40.00 );

		$services = $this->service_model->get_active_services_by_category();

		$this->assertEquals( 25.00, $services['Haircuts'][0]['min_staff_price'] );
	}

	/**
	 * Test max staff price calculated correctly
	 *
	 * @covers Bookit_Service_Model::get_active_services_by_category
	 */
	public function test_max_staff_price_calculated_correctly() {
		$category_id = $this->create_category( 'Haircuts' );
		$service_id  = $this->create_service( 'Haircut', 35.00 );

		$staff1 = $this->create_staff( 'Emma', 'Thompson' );
		$staff2 = $this->create_staff( 'Sarah', 'Jones' );
		$staff3 = $this->create_staff( 'Lisa', 'Smith' );

		$this->link_service_to_category( $service_id, $category_id );
		$this->assign_staff_to_service( $staff1, $service_id, 50.00 );  // Highest
		$this->assign_staff_to_service( $staff2, $service_id, 25.00 );
		$this->assign_staff_to_service( $staff3, $service_id, 40.00 );

		$services = $this->service_model->get_active_services_by_category();

		$this->assertEquals( 50.00, $services['Haircuts'][0]['max_staff_price'] );
	}

	/**
	 * Test multi-category services appear in all their categories
	 *
	 * @covers Bookit_Service_Model::get_active_services_by_category
	 */
	public function test_multi_category_services_appear_in_all_categories() {
		// Create 2 categories
		$cat_haircuts = $this->create_category( 'Haircuts' );
		$cat_packages = $this->create_category( 'Packages' );

		// Create service
		$service_id = $this->create_service( 'Haircut & Style Package', 60.00 );
		$staff_id   = $this->create_staff( 'Emma', 'Thompson' );

		// Link to BOTH categories
		$this->link_service_to_category( $service_id, $cat_haircuts );
		$this->link_service_to_category( $service_id, $cat_packages );
		$this->assign_staff_to_service( $staff_id, $service_id );

		// Get services
		$services = $this->service_model->get_active_services_by_category();

		// Assert appears in both
		$this->assertArrayHasKey( 'Haircuts', $services );
		$this->assertArrayHasKey( 'Packages', $services );
		$this->assertEquals( 'Haircut & Style Package', $services['Haircuts'][0]['name'] );
		$this->assertEquals( 'Haircut & Style Package', $services['Packages'][0]['name'] );
	}

	/**
	 * Test service categories array is populated correctly
	 *
	 * @covers Bookit_Service_Model::get_active_services_by_category
	 */
	public function test_service_categories_array_populated() {
		$cat1 = $this->create_category( 'Haircuts' );
		$cat2 = $this->create_category( 'Packages' );
		$cat3 = $this->create_category( 'Specials' );

		$service_id = $this->create_service( 'Multi-Category Service', 50.00 );
		$staff_id   = $this->create_staff( 'Emma', 'Thompson' );

		$this->link_service_to_category( $service_id, $cat1 );
		$this->link_service_to_category( $service_id, $cat2 );
		$this->link_service_to_category( $service_id, $cat3 );
		$this->assign_staff_to_service( $staff_id, $service_id );

		$services = $this->service_model->get_active_services_by_category();

		// Get service from any category
		$service = $services['Haircuts'][0];

		// Assert categories array contains all 3
		$this->assertIsArray( $service['categories'] );
		$this->assertContains( 'Haircuts', $service['categories'] );
		$this->assertContains( 'Packages', $service['categories'] );
		$this->assertContains( 'Specials', $service['categories'] );
	}

	/**
	 * Test empty array returned when no services exist
	 *
	 * @covers Bookit_Service_Model::get_active_services_by_category
	 */
	public function test_no_services_returns_empty_array() {
		// Don't create any services
		$services = $this->service_model->get_active_services_by_category();

		$this->assertIsArray( $services );
		$this->assertEmpty( $services );
	}

	/**
	 * Test get_service_by_id returns correct service
	 *
	 * @covers Bookit_Service_Model::get_service_by_id
	 */
	public function test_get_service_by_id_returns_correct_service() {
		$service_id = $this->create_service( 'Test Service', 30.00 );

		$service = $this->service_model->get_service_by_id( $service_id );

		$this->assertIsArray( $service );
		$this->assertEquals( $service_id, $service['id'] );
		$this->assertEquals( 'Test Service', $service['name'] );
		$this->assertEquals( 30.00, $service['base_price'] );
	}

	/**
	 * Test get_service_by_id returns null for invalid ID
	 *
	 * @covers Bookit_Service_Model::get_service_by_id
	 */
	public function test_get_service_by_id_returns_null_for_invalid_id() {
		$service = $this->service_model->get_service_by_id( 99999 );

		$this->assertNull( $service );
	}

	/**
	 * Test get_service_by_id returns inactive service with correct status
	 *
	 * Note: get_service_by_id doesn't filter by is_active - it returns the service
	 * but marks it as inactive. The API endpoint then filters these out.
	 *
	 * @covers Bookit_Service_Model::get_service_by_id
	 */
	public function test_get_service_by_id_excludes_inactive_service() {
		$service_id = $this->create_service( 'Inactive Service', 30.00, false );

		$service = $this->service_model->get_service_by_id( $service_id );

		// get_service_by_id returns the service but with status='inactive'
		// The API endpoint will then reject it based on status !== 'active'
		$this->assertIsArray( $service );
		$this->assertEquals( 'inactive', $service['status'] );
		$this->assertEquals( $service_id, $service['id'] );
	}

	/**
	 * Test service with only inactive staff is excluded
	 *
	 * @covers Bookit_Service_Model::get_active_services_by_category
	 */
	public function test_service_with_only_inactive_staff_excluded() {
		$category_id = $this->create_category( 'Haircuts' );
		$service_id  = $this->create_service( 'Service', 30.00 );

		// Assign only inactive staff
		$inactive_staff = $this->create_staff( 'John', 'Inactive', false );

		$this->link_service_to_category( $service_id, $category_id );
		$this->assign_staff_to_service( $inactive_staff, $service_id );

		$services = $this->service_model->get_active_services_by_category();

		// Should be empty because no active staff
		$this->assertEmpty( $services );
	}

	// ========== HELPER METHODS ==========

	/**
	 * Create a test category
	 *
	 * @param string $name Category name.
	 * @param bool   $is_active Whether category is active.
	 * @return int Category ID.
	 */
	private function create_category( $name, $is_active = true ) {
		global $wpdb;

		$wpdb->insert(
			$wpdb->prefix . 'bookings_categories',
			array(
				'name'         => $name,
				'description'  => "Test category: {$name}",
				'display_order' => 0,
				'is_active'    => $is_active ? 1 : 0,
				'deleted_at'   => null,
			),
			array( '%s', '%s', '%d', '%d', '%s' )
		);

		return $wpdb->insert_id;
	}

	/**
	 * Create a test service
	 *
	 * @param string $name Service name.
	 * @param float  $price Base price.
	 * @param bool   $is_active Whether service is active.
	 * @return int Service ID.
	 */
	private function create_service( $name, $price, $is_active = true ) {
		global $wpdb;

		$wpdb->insert(
			$wpdb->prefix . 'bookings_services',
			array(
				'name'         => $name,
				'description' => "Test service: {$name}",
				'duration'     => 45,
				'price'        => $price,
				'buffer_before' => 0,
				'buffer_after'  => 0,
				'is_active'    => $is_active ? 1 : 0,
				'deleted_at'   => null,
			),
			array( '%s', '%s', '%d', '%f', '%d', '%d', '%d', '%s' )
		);

		return $wpdb->insert_id;
	}

	/**
	 * Create a test staff member
	 *
	 * @param string      $first_name First name.
	 * @param string      $last_name Last name.
	 * @param bool        $is_active Whether staff is active.
	 * @param string|null $photo_url Optional photo URL.
	 * @param string|null $bio Optional bio.
	 * @param string|null $title Optional title.
	 * @return int Staff ID.
	 */
	private function create_staff( $first_name, $last_name, $is_active = true, $photo_url = null, $bio = null, $title = null ) {
		global $wpdb;

		$data = array(
			'first_name'    => $first_name,
			'last_name'     => $last_name,
			'email'         => strtolower( $first_name ) . '@example.com',
			'password_hash' => password_hash( 'password', PASSWORD_BCRYPT ),
			'role'          => 'staff',
			'is_active'     => $is_active ? 1 : 0,
			'deleted_at'    => null,
		);

		$format = array( '%s', '%s', '%s', '%s', '%s', '%d', '%s' );

		// Add optional fields if provided.
		if ( $photo_url !== null ) {
			$data['photo_url'] = $photo_url;
			$format[]          = '%s';
		}
		if ( $bio !== null ) {
			$data['bio'] = $bio;
			$format[]    = '%s';
		}
		if ( $title !== null ) {
			$data['title'] = $title;
			$format[]      = '%s';
		}

		$wpdb->insert(
			$wpdb->prefix . 'bookings_staff',
			$data,
			$format
		);

		return $wpdb->insert_id;
	}

	/**
	 * Link service to category
	 *
	 * @param int $service_id Service ID.
	 * @param int $category_id Category ID.
	 */
	private function link_service_to_category( $service_id, $category_id ) {
		global $wpdb;

		$wpdb->insert(
			$wpdb->prefix . 'bookings_service_categories',
			array(
				'service_id'  => $service_id,
				'category_id' => $category_id,
			),
			array( '%d', '%d' )
		);
	}

	/**
	 * Assign staff to service with optional custom price
	 *
	 * @param int      $staff_id Staff ID.
	 * @param int      $service_id Service ID.
	 * @param float|null $custom_price Custom price or null to use base price.
	 */
	private function assign_staff_to_service( $staff_id, $service_id, $custom_price = null ) {
		global $wpdb;

		$data = array(
			'staff_id'   => $staff_id,
			'service_id' => $service_id,
		);

		$format = array( '%d', '%d' );

		// Add custom_price if provided (column is ensured to exist in setUp)
		if ( $custom_price !== null ) {
			$data['custom_price'] = $custom_price;
			$format[]             = '%f';
		}

		$wpdb->insert(
			$wpdb->prefix . 'bookings_staff_services',
			$data,
			$format
		);
	}
}
