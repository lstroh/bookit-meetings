<?php
/**
 * Tests for cooling-off waiver legal requirement.
 *
 * @package    Bookit_Booking_System
 * @subpackage Tests
 */

/**
 * Test cooling-off waiver behavior across helper/API/booking creation.
 */
class Test_Cooling_Off_Waiver extends WP_UnitTestCase {

	/**
	 * Test service ID.
	 *
	 * @var int
	 */
	private $service_id = 0;

	/**
	 * Test staff ID.
	 *
	 * @var int
	 */
	private $staff_id = 0;

	/**
	 * Set up each test.
	 */
	public function setUp(): void {
		parent::setUp();

		global $wpdb;

		$this->ensure_waiver_columns();

		bookit_test_truncate_tables(
			array(
				'bookings_package_redemptions',
				'bookings_customer_packages',
				'bookings_package_types',
				'bookings_payments',
				'bookings',
				'bookings_customers',
				'bookings_staff',
				'bookings_services',
			)
		);

		$wpdb->insert(
			$wpdb->prefix . 'bookings_services',
			array(
				'name'       => 'Cooling Off Service',
				'duration'   => 45,
				'price'      => 60.00,
				'is_active'  => 1,
				'created_at' => current_time( 'mysql' ),
				'updated_at' => current_time( 'mysql' ),
			),
			array( '%s', '%d', '%f', '%d', '%s', '%s' )
		);
		$this->service_id = (int) $wpdb->insert_id;

		$wpdb->insert(
			$wpdb->prefix . 'bookings_staff',
			array(
				'email'         => 'waiver.staff@example.com',
				'password_hash' => wp_hash_password( 'test123' ),
				'first_name'    => 'Waiver',
				'last_name'     => 'Staff',
				'is_active'     => 1,
				'created_at'    => current_time( 'mysql' ),
				'updated_at'    => current_time( 'mysql' ),
			),
			array( '%s', '%s', '%s', '%s', '%d', '%s', '%s' )
		);
		$this->staff_id = (int) $wpdb->insert_id;

		Bookit_Session_Manager::clear();
		do_action( 'rest_api_init' );
	}

	/**
	 * Tear down each test.
	 */
	public function tearDown(): void {
		Bookit_Session_Manager::clear();
		if ( session_status() === PHP_SESSION_ACTIVE ) {
			session_destroy();
		}
		if ( isset( $_SESSION ) ) {
			$_SESSION = array();
		}

		parent::tearDown();
	}

	/**
	 * @covers ::bookit_booking_requires_waiver
	 */
	public function test_waiver_required_within_14_days() {
		$today   = wp_date( 'Y-m-d', null, wp_timezone() );
		$plus_7  = wp_date( 'Y-m-d', strtotime( '+7 days' ), wp_timezone() );
		$plus_13 = wp_date( 'Y-m-d', strtotime( '+13 days' ), wp_timezone() );

		$this->assertTrue( bookit_booking_requires_waiver( $today ) );
		$this->assertTrue( bookit_booking_requires_waiver( $plus_7 ) );
		$this->assertTrue( bookit_booking_requires_waiver( $plus_13 ) );
	}

	/**
	 * @covers ::bookit_booking_requires_waiver
	 */
	public function test_waiver_not_required_beyond_14_days() {
		$plus_14 = wp_date( 'Y-m-d', strtotime( '+14 days' ), wp_timezone() );
		$plus_30 = wp_date( 'Y-m-d', strtotime( '+30 days' ), wp_timezone() );

		$this->assertFalse( bookit_booking_requires_waiver( $plus_14 ) );
		$this->assertFalse( bookit_booking_requires_waiver( $plus_30 ) );
	}

	/**
	 * @covers ::bookit_booking_requires_waiver
	 */
	public function test_waiver_boundary_exactly_14_days() {
		$plus_14 = wp_date( 'Y-m-d', strtotime( '+14 days' ), wp_timezone() );
		$this->assertFalse( bookit_booking_requires_waiver( $plus_14 ) );
	}

	/**
	 * @covers Booking_System_Booking_Creator::create_booking
	 */
	public function test_waiver_saved_to_booking_when_required() {
		global $wpdb;

		$booking_creator = new Booking_System_Booking_Creator();
		$booking_date    = wp_date( 'Y-m-d', strtotime( '+3 days' ), wp_timezone() );
		$booking_id      = $booking_creator->create_booking(
			$this->build_booking_data(
				array(
					'booking_date'       => $booking_date,
					'cooling_off_waiver' => 1,
				)
			)
		);

		$this->assertIsInt( $booking_id );

		$row = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT cooling_off_waiver_given, cooling_off_waiver_at FROM {$wpdb->prefix}bookings WHERE id = %d",
				$booking_id
			),
			ARRAY_A
		);

		$this->assertEquals( 1, (int) $row['cooling_off_waiver_given'] );
		$this->assertNotEmpty( $row['cooling_off_waiver_at'] );
	}

	/**
	 * @covers Booking_System_Booking_Creator::create_booking
	 */
	public function test_waiver_not_saved_when_not_required() {
		global $wpdb;

		$booking_creator = new Booking_System_Booking_Creator();
		$booking_date    = wp_date( 'Y-m-d', strtotime( '+30 days' ), wp_timezone() );
		$booking_id      = $booking_creator->create_booking(
			$this->build_booking_data(
				array(
					'booking_date'       => $booking_date,
					'cooling_off_waiver' => 0,
				)
			)
		);

		$this->assertIsInt( $booking_id );

		$row = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT cooling_off_waiver_given, cooling_off_waiver_at FROM {$wpdb->prefix}bookings WHERE id = %d",
				$booking_id
			),
			ARRAY_A
		);

		$this->assertEquals( 0, (int) $row['cooling_off_waiver_given'] );
		$this->assertNull( $row['cooling_off_waiver_at'] );
	}

	/**
	 * @covers Booking_System_Booking_Creator::create_booking
	 */
	public function test_dashboard_booking_skips_waiver_check() {
		$booking_creator = new Booking_System_Booking_Creator();
		$booking_date    = wp_date( 'Y-m-d', strtotime( '+3 days' ), wp_timezone() );
		$booking_id      = $booking_creator->create_booking(
			$this->build_booking_data(
				array(
					'booking_date'       => $booking_date,
					'cooling_off_waiver' => 0,
					'skip_waiver'        => true,
				)
			)
		);

		$this->assertFalse( is_wp_error( $booking_id ) );
		$this->assertIsInt( $booking_id );
		$this->assertGreaterThan( 0, $booking_id );
	}

	/**
	 * @covers Bookit_Contact_API::save_contact_details
	 */
	public function test_booking_rejected_if_waiver_missing_when_required() {
		$required_date = wp_date( 'Y-m-d', strtotime( '+5 days' ), wp_timezone() );
		Bookit_Session_Manager::init();
		Bookit_Session_Manager::set_data(
			array(
				'service_id' => $this->service_id,
				'staff_id'   => $this->staff_id,
				'date'       => $required_date,
				'time'       => '10:00',
			)
		);

		$request = new WP_REST_Request( 'POST', '/bookit/v1/contact/save' );
		$request->set_header( 'X-WP-Nonce', wp_create_nonce( 'wp_rest' ) );
		$request->set_body_params(
			array(
				'first_name'         => 'Jane',
				'last_name'          => 'Doe',
				'email'              => 'jane.waiver@example.com',
				'phone'              => '07700900123',
				'special_requests'   => '',
				'marketing_consent'  => false,
				'cooling_off_waiver' => 0,
			)
		);

		$response = rest_get_server()->dispatch( $request );
		$data     = $response->get_data();

		$this->assertEquals( 400, $response->get_status() );
		$this->assertFalse( $data['success'] );
		$this->assertEquals( 'Cooling-off waiver is required for bookings within 14 days.', $data['message'] );
	}

	/**
	 * Build valid booking creator data with optional overrides.
	 *
	 * @param array $overrides Optional overrides.
	 * @return array
	 */
	private function build_booking_data( array $overrides = array() ) {
		$defaults = array(
			'service_id'          => $this->service_id,
			'staff_id'            => $this->staff_id,
			'booking_date'        => wp_date( 'Y-m-d', strtotime( '+2 days' ), wp_timezone() ),
			'booking_time'        => '10:00',
			'customer_first_name' => 'Jane',
			'customer_last_name'  => 'Doe',
			'customer_email'      => 'jane.waiver@example.com',
			'customer_phone'      => '07700900123',
			'special_requests'    => '',
			'payment_method'      => 'pay_on_arrival',
			'payment_intent_id'   => null,
			'stripe_session_id'   => null,
			'amount_paid'         => 0,
			'cooling_off_waiver'  => 1,
		);

		return array_merge( $defaults, $overrides );
	}

	/**
	 * Ensure waiver columns exist on bookings table for test environments
	 * where migrations might not have run yet.
	 *
	 * @return void
	 */
	private function ensure_waiver_columns() {
		global $wpdb;

		$table_name = $wpdb->prefix . 'bookings';

		$given_col = $wpdb->get_var(
			$wpdb->prepare(
				"SHOW COLUMNS FROM {$table_name} LIKE %s",
				'cooling_off_waiver_given'
			)
		);

		if ( empty( $given_col ) ) {
			$wpdb->query(
				"ALTER TABLE {$table_name} ADD COLUMN cooling_off_waiver_given TINYINT(1) DEFAULT 0"
			);
		}

		$at_col = $wpdb->get_var(
			$wpdb->prepare(
				"SHOW COLUMNS FROM {$table_name} LIKE %s",
				'cooling_off_waiver_at'
			)
		);

		if ( empty( $at_col ) ) {
			$wpdb->query(
				"ALTER TABLE {$table_name} ADD COLUMN cooling_off_waiver_at DATETIME DEFAULT NULL"
			);
		}
	}
}
