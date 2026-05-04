<?php
/**
 * Database tests.
 *
 * @package Bookit_Booking_System
 */

use Yoast\PHPUnitPolyfills\TestCases\TestCase;

/**
 * Test database functionality.
 */
class Test_Database extends TestCase {

	/**
	 * Test core plugin tables exist (legacy wp_bookings_working_hours removed in migration 0011).
	 */
	public function test_all_tables_exist() {
		global $wpdb;

		$tables = array(
			'bookings_services',
			'bookings_categories',
			'bookings_service_categories',
			'bookings_staff',
			'bookings_staff_services',
			'bookings_customers',
			'bookings',
			'bookings_payments',
			'bookings_staff_working_hours',
			'bookings_settings',
		);

		foreach ( $tables as $table ) {
			$table_name = $wpdb->prefix . $table;
			$exists     = $wpdb->get_var( "SHOW TABLES LIKE '$table_name'" );
			
			$this->assertEquals( $table_name, $exists, "Table $table_name should exist" );
		}
	}

	/**
	 * Test bookings table has unique constraint.
	 */
	public function test_bookings_table_unique_constraint() {
		global $wpdb;

		$table_name = $wpdb->prefix . 'bookings';
		
		// Get table indexes
		$indexes = $wpdb->get_results( "SHOW INDEX FROM $table_name" );
		
		// Look for unique_booking_slot index
		$found_unique = false;
		foreach ( $indexes as $index ) {
			if ( $index->Key_name === 'unique_booking_slot' && $index->Non_unique == 0 ) {
				$found_unique = true;
				break;
			}
		}
		
		$this->assertTrue( $found_unique, 'Bookings table should have unique_booking_slot constraint' );
	}

	/**
	 * Test services table structure.
	 */
	public function test_services_table_structure() {
		global $wpdb;

		$table_name = $wpdb->prefix . 'bookings_services';
		
		// Get table columns
		$columns = $wpdb->get_results( "SHOW COLUMNS FROM $table_name" );
		
		$column_names = array_column( $columns, 'Field' );
		
		$required_columns = array(
			'id',
			'name',
			'description',
			'duration',
			'price',
			'deposit_amount',
			'is_active',
			'created_at',
			'updated_at',
			'deleted_at',
		);
		
		foreach ( $required_columns as $column ) {
			$this->assertContains( $column, $column_names, "Services table should have $column column" );
		}
	}

	/**
	 * Test staff table has unique email constraint.
	 */
	public function test_staff_table_unique_email() {
		global $wpdb;

		$table_name = $wpdb->prefix . 'bookings_staff';
		
		// Get table indexes
		$indexes = $wpdb->get_results( "SHOW INDEX FROM $table_name" );
		
		// Look for unique email index
		$found_unique = false;
		foreach ( $indexes as $index ) {
			if ( $index->Key_name === 'unique_email' && $index->Non_unique == 0 ) {
				$found_unique = true;
				break;
			}
		}
		
		$this->assertTrue( $found_unique, 'Staff table should have unique email constraint' );
	}

	/**
	 * Test can insert and retrieve data.
	 */
	public function test_database_insert_and_retrieve() {
		global $wpdb;

		// Insert test service
		$table_name = $wpdb->prefix . 'bookings_services';
		
		$result = $wpdb->insert(
			$table_name,
			array(
				'name'     => 'Test Service',
				'duration' => 60,
				'price'    => 50.00,
			),
			array( '%s', '%d', '%f' )
		);
		
		$this->assertNotFalse( $result, 'Should insert test service' );
		
		$inserted_id = $wpdb->insert_id;
		
		// Retrieve the service
		$service = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM $table_name WHERE id = %d",
				$inserted_id
			),
			ARRAY_A
		);
		
		$this->assertNotNull( $service );
		$this->assertEquals( 'Test Service', $service['name'] );
		$this->assertEquals( 60, $service['duration'] );
		$this->assertEquals( '50.00', $service['price'] );
		
		// Cleanup
		$wpdb->delete( $table_name, array( 'id' => $inserted_id ), array( '%d' ) );
	}

	/**
	 * Test wp_bookings_staff has photo_url column.
	 */
	public function test_staff_table_has_photo_url_column() {
		global $wpdb;

		$table_name = $wpdb->prefix . 'bookings_staff';
		$columns    = $wpdb->get_results( "DESCRIBE $table_name" );
		$column_names = array_column( $columns, 'Field' );

		$this->assertContains( 'photo_url', $column_names, 'Staff table should have photo_url column' );
	}

	/**
	 * Test wp_bookings_staff has bio column.
	 */
	public function test_staff_table_has_bio_column() {
		global $wpdb;

		$table_name = $wpdb->prefix . 'bookings_staff';
		$columns    = $wpdb->get_results( "DESCRIBE $table_name" );
		$column_names = array_column( $columns, 'Field' );

		$this->assertContains( 'bio', $column_names, 'Staff table should have bio column' );
	}

	/**
	 * Test wp_bookings_staff has title column.
	 */
	public function test_staff_table_has_title_column() {
		global $wpdb;

		$table_name = $wpdb->prefix . 'bookings_staff';
		$columns    = $wpdb->get_results( "DESCRIBE $table_name" );
		$column_names = array_column( $columns, 'Field' );

		$this->assertContains( 'title', $column_names, 'Staff table should have title column' );
	}

	/**
	 * Test wp_bookings_staff_services has custom_price column.
	 */
	public function test_staff_services_table_has_custom_price_column() {
		global $wpdb;

		$table_name = $wpdb->prefix . 'bookings_staff_services';
		$columns    = $wpdb->get_results( "DESCRIBE $table_name" );
		$column_names = array_column( $columns, 'Field' );

		$this->assertContains( 'custom_price', $column_names, 'Staff services table should have custom_price column' );
	}

	/**
	 * Test staff can be created with photo, bio, title.
	 */
	public function test_staff_creation_with_optional_fields() {
		global $wpdb;

		$table_name = $wpdb->prefix . 'bookings_staff';

		$result = $wpdb->insert(
			$table_name,
			array(
				'email'         => 'emma@example.com',
				'password_hash' => password_hash( 'password', PASSWORD_BCRYPT ),
				'first_name'    => 'Emma',
				'last_name'     => 'Thompson',
				'phone'         => '07700900123',
				'photo_url'     => 'https://example.com/emma.jpg',
				'bio'           => '10+ years experience in balayage',
				'title'         => 'Senior Stylist',
				'role'          => 'staff',
				'is_active'     => 1,
			),
			array( '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%d' )
		);

		$this->assertNotFalse( $result, 'Should insert staff with optional fields' );
		$staff_id = $wpdb->insert_id;
		$this->assertGreaterThan( 0, $staff_id, 'Staff ID should be greater than 0' );

		// Verify data was saved.
		$staff = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM $table_name WHERE id = %d",
				$staff_id
			)
		);

		$this->assertEquals( 'https://example.com/emma.jpg', $staff->photo_url, 'Photo URL should be saved' );
		$this->assertEquals( '10+ years experience in balayage', $staff->bio, 'Bio should be saved' );
		$this->assertEquals( 'Senior Stylist', $staff->title, 'Title should be saved' );

		// Cleanup.
		$wpdb->delete( $table_name, array( 'id' => $staff_id ), array( '%d' ) );
	}

	/**
	 * Test staff can be created without optional fields (NULL).
	 */
	public function test_staff_creation_without_optional_fields() {
		global $wpdb;

		$table_name = $wpdb->prefix . 'bookings_staff';

		$result = $wpdb->insert(
			$table_name,
			array(
				'email'         => 'basic@example.com',
				'password_hash' => password_hash( 'password', PASSWORD_BCRYPT ),
				'first_name'    => 'Basic',
				'last_name'     => 'Staff',
				'role'          => 'staff',
				'is_active'     => 1,
			),
			array( '%s', '%s', '%s', '%s', '%s', '%d' )
		);

		$this->assertNotFalse( $result, 'Should insert staff without optional fields' );
		$staff_id = $wpdb->insert_id;

		// Verify NULL values accepted.
		$staff = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM $table_name WHERE id = %d",
				$staff_id
			)
		);

		$this->assertNull( $staff->photo_url, 'Photo URL should be NULL' );
		$this->assertNull( $staff->bio, 'Bio should be NULL' );
		$this->assertNull( $staff->title, 'Title should be NULL' );
		$this->assertNull( $staff->phone, 'Phone should be NULL' );

		// Cleanup.
		$wpdb->delete( $table_name, array( 'id' => $staff_id ), array( '%d' ) );
	}

	/**
	 * Test staff-service relationship with custom price.
	 */
	public function test_staff_service_with_custom_price() {
		global $wpdb;

		// Create service.
		$service_table = $wpdb->prefix . 'bookings_services';
		$wpdb->insert(
			$service_table,
			array(
				'name'      => 'Haircut',
				'description' => 'Basic haircut',
				'duration' => 45,
				'price'     => 35.00,
				'is_active' => 1,
			),
			array( '%s', '%s', '%d', '%f', '%d' )
		);
		$service_id = $wpdb->insert_id;

		// Create staff.
		$staff_table = $wpdb->prefix . 'bookings_staff';
		$wpdb->insert(
			$staff_table,
			array(
				'email'         => 'senior@example.com',
				'password_hash' => password_hash( 'password', PASSWORD_BCRYPT ),
				'first_name'    => 'Senior',
				'last_name'     => 'Stylist',
				'title'         => 'Senior Stylist',
				'role'          => 'staff',
				'is_active'     => 1,
			),
			array( '%s', '%s', '%s', '%s', '%s', '%s', '%d' )
		);
		$staff_id = $wpdb->insert_id;

		// Assign with custom price.
		$relationship_table = $wpdb->prefix . 'bookings_staff_services';
		$result = $wpdb->insert(
			$relationship_table,
			array(
				'staff_id'     => $staff_id,
				'service_id'   => $service_id,
				'custom_price' => 45.00, // Senior charges more.
			),
			array( '%d', '%d', '%f' )
		);

		$this->assertNotFalse( $result, 'Should insert staff-service relationship with custom price' );

		// Verify custom price saved.
		$relationship = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM $relationship_table 
				 WHERE staff_id = %d AND service_id = %d",
				$staff_id,
				$service_id
			)
		);

		$this->assertEquals( 45.00, (float) $relationship->custom_price, 'Custom price should be saved' );

		// Cleanup.
		$wpdb->delete( $relationship_table, array( 'id' => $relationship->id ), array( '%d' ) );
		$wpdb->delete( $service_table, array( 'id' => $service_id ), array( '%d' ) );
		$wpdb->delete( $staff_table, array( 'id' => $staff_id ), array( '%d' ) );
	}

	/**
	 * Test staff-service relationship without custom price (NULL = use base).
	 */
	public function test_staff_service_without_custom_price() {
		global $wpdb;

		// Create service.
		$service_table = $wpdb->prefix . 'bookings_services';
		$wpdb->insert(
			$service_table,
			array(
				'name'        => 'Haircut',
				'description' => 'Basic haircut',
				'duration'    => 45,
				'price'       => 35.00,
				'is_active'   => 1,
			),
			array( '%s', '%s', '%d', '%f', '%d' )
		);
		$service_id = $wpdb->insert_id;

		// Create staff.
		$staff_table = $wpdb->prefix . 'bookings_staff';
		$wpdb->insert(
			$staff_table,
			array(
				'email'         => 'standard@example.com',
				'password_hash' => password_hash( 'password', PASSWORD_BCRYPT ),
				'first_name'    => 'Standard',
				'last_name'     => 'Stylist',
				'role'          => 'staff',
				'is_active'     => 1,
			),
			array( '%s', '%s', '%s', '%s', '%s', '%d' )
		);
		$staff_id = $wpdb->insert_id;

		// Assign WITHOUT custom price.
		$relationship_table = $wpdb->prefix . 'bookings_staff_services';
		$result = $wpdb->insert(
			$relationship_table,
			array(
				'staff_id'   => $staff_id,
				'service_id' => $service_id,
				// No custom_price = NULL.
			),
			array( '%d', '%d' )
		);

		$this->assertNotFalse( $result, 'Should insert staff-service relationship without custom price' );

		// Verify custom_price is NULL.
		$relationship = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM $relationship_table 
				 WHERE staff_id = %d AND service_id = %d",
				$staff_id,
				$service_id
			)
		);

		$this->assertNull( $relationship->custom_price, 'Custom price should be NULL when not provided' );

		// Cleanup.
		$wpdb->delete( $relationship_table, array( 'id' => $relationship->id ), array( '%d' ) );
		$wpdb->delete( $service_table, array( 'id' => $service_id ), array( '%d' ) );
		$wpdb->delete( $staff_table, array( 'id' => $staff_id ), array( '%d' ) );
	}
}
