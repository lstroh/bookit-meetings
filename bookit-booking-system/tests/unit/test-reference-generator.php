<?php
/**
 * Tests for reference generator.
 *
 * @package    Bookit_Booking_System
 * @subpackage Tests
 */

/**
 * Test Bookit_Reference_Generator.
 */
class Test_Bookit_Reference_Generator extends WP_UnitTestCase {

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
				'bookings',
			)
		);
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
				'bookings',
			)
		);

		parent::tearDown();
	}

	/**
	 * @covers Bookit_Reference_Generator::generate
	 */
	public function test_generate_returns_correct_format() {
		$reference = Bookit_Reference_Generator::generate( 1, '2026-02-28 10:00:00' );

		$this->assertMatchesRegularExpression( '/^BK\d{4}-[A-Z0-9]{4}$/', $reference );
		$this->assertStringStartsWith( 'BK2602', $reference );
	}

	/**
	 * @covers Bookit_Reference_Generator::generate
	 */
	public function test_generate_is_deterministic() {
		$first  = Bookit_Reference_Generator::generate( 1, '2026-02-28 10:00:00' );
		$second = Bookit_Reference_Generator::generate( 1, '2026-02-28 10:00:00' );

		$this->assertSame( $first, $second );
	}

	/**
	 * @covers Bookit_Reference_Generator::generate_unique
	 */
	public function test_generate_unique_returns_correct_format() {
		$reference = Bookit_Reference_Generator::generate_unique( 1, '2026-02-28 10:00:00' );

		$this->assertMatchesRegularExpression( '/^BK\d{4}-[A-Z0-9]{4}$/', $reference );
	}

	/**
	 * @covers Bookit_Reference_Generator::generate_unique
	 */
	public function test_generate_unique_avoids_collision() {
		$this->create_test_booking(
			array(
				'id'                => 1001,
				'booking_reference' => 'BK2602-XXXX',
			)
		);

		$reference = Bookit_Reference_Generator::generate_unique( 2, '2026-02-28 10:00:00' );

		$this->assertNotNull( $reference );
		$this->assertMatchesRegularExpression( '/^BK\d{4}-[A-Z0-9]{4}$/', $reference );
	}

	/**
	 * @covers Bookit_Reference_Generator::generate_lock_version
	 */
	public function test_generate_lock_version_returns_32_char_hex() {
		$lock_version = Bookit_Reference_Generator::generate_lock_version( 1, '2026-02-28 10:00:00' );

		$this->assertSame( 32, strlen( $lock_version ) );
		$this->assertMatchesRegularExpression( '/^[a-f0-9]{32}$/', $lock_version );
	}

	/**
	 * @covers Bookit_Reference_Generator::generate_lock_version
	 */
	public function test_generate_lock_version_is_deterministic() {
		$first  = Bookit_Reference_Generator::generate_lock_version( 1, '2026-02-28 10:00:00' );
		$second = Bookit_Reference_Generator::generate_lock_version( 1, '2026-02-28 10:00:00' );

		$this->assertSame( $first, $second );
	}

	/**
	 * @covers Bookit_Reference_Generator::generate
	 */
	public function test_different_booking_ids_produce_different_references() {
		$first  = Bookit_Reference_Generator::generate( 1, '2026-02-28 10:00:00' );
		$second = Bookit_Reference_Generator::generate( 2, '2026-02-28 10:00:00' );

		$this->assertNotSame( $first, $second );
	}

	/**
	 * Create a booking row for collision-related tests.
	 *
	 * @param array $args Override defaults.
	 * @return int
	 */
	private function create_test_booking( $args = array() ): int {
		global $wpdb;

		$defaults = array(
			'customer_id'      => 0,
			'service_id'       => 0,
			'staff_id'         => 0,
			'booking_date'     => '2026-02-28',
			'start_time'       => '10:00:00',
			'end_time'         => '11:00:00',
			'duration'         => 60,
			'status'           => 'confirmed',
			'total_price'      => 50.00,
			'deposit_paid'     => 0.00,
			'balance_due'      => 50.00,
			'full_amount_paid' => 0,
			'payment_method'   => 'cash',
			'booking_reference' => null,
			'created_at'       => current_time( 'mysql' ),
			'updated_at'       => current_time( 'mysql' ),
		);

		$data = wp_parse_args( $args, $defaults );
		$wpdb->insert( $wpdb->prefix . 'bookings', $data );

		return (int) $wpdb->insert_id;
	}
}
