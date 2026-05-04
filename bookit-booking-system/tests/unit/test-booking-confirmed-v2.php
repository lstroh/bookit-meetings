<?php
/**
 * Tests for booking confirmation V2 shortcode and template.
 *
 * @package    Bookit_Booking_System
 * @subpackage Tests
 */

/**
 * Test [bookit_booking_confirmed_v2] and booking-confirmed-v2.php output.
 */
class Test_Booking_Confirmed_V2 extends WP_UnitTestCase {

	/**
	 * IDs created for the current test case (cleanup in tearDown).
	 *
	 * @var array<string, int>
	 */
	private $fixture_ids = array(
		'customer_id' => 0,
		'service_id'  => 0,
		'staff_id'    => 0,
		'booking_id'  => 0,
	);

	/**
	 * Set up.
	 */
	public function setUp(): void {
		parent::setUp();
		Bookit_Session_Manager::clear();
		unset( $_GET['booking_id'], $_GET['session_id'] );
	}

	/**
	 * Tear down.
	 */
	public function tearDown(): void {
		global $wpdb;
		$p = $wpdb->prefix;

		if ( ! empty( $this->fixture_ids['booking_id'] ) ) {
			$wpdb->delete( $p . 'bookings', array( 'id' => $this->fixture_ids['booking_id'] ), array( '%d' ) );
		}
		if ( ! empty( $this->fixture_ids['customer_id'] ) ) {
			$wpdb->delete( $p . 'bookings_customers', array( 'id' => $this->fixture_ids['customer_id'] ), array( '%d' ) );
		}
		if ( ! empty( $this->fixture_ids['service_id'] ) ) {
			$wpdb->delete( $p . 'bookings_services', array( 'id' => $this->fixture_ids['service_id'] ), array( '%d' ) );
		}
		if ( ! empty( $this->fixture_ids['staff_id'] ) ) {
			$wpdb->delete( $p . 'bookings_staff', array( 'id' => $this->fixture_ids['staff_id'] ), array( '%d' ) );
		}

		$this->fixture_ids = array(
			'customer_id' => 0,
			'service_id'  => 0,
			'staff_id'    => 0,
			'booking_id'  => 0,
		);

		Bookit_Session_Manager::clear();
		unset( $_GET['booking_id'], $_GET['session_id'] );
		remove_all_filters( 'bookit_confirmation_meeting_section' );

		parent::tearDown();
	}

	/**
	 * Create customer, service, staff, and booking rows; set fixture_ids.
	 *
	 * @param array<string, mixed> $booking_overrides Booking column overrides.
	 * @return int Booking ID.
	 */
	private function create_booking_fixture( array $booking_overrides = array() ): int {
		global $wpdb;
		$p = $wpdb->prefix;

		$wpdb->insert(
			$p . 'bookings_customers',
			array(
				'email'       => 'v2confirm-' . wp_generate_password( 8, false ) . '@example.com',
				'first_name'  => 'Test',
				'last_name'   => 'Customer',
				'phone'       => '07700900000',
				'created_at'  => current_time( 'mysql' ),
				'updated_at'  => current_time( 'mysql' ),
			),
			array( '%s', '%s', '%s', '%s', '%s', '%s' )
		);
		$this->fixture_ids['customer_id'] = (int) $wpdb->insert_id;

		$wpdb->insert(
			$p . 'bookings_services',
			array(
				'name'           => 'Swedish Massage',
				'duration'       => 60,
				'price'          => 100.00,
				'deposit_type'   => 'percentage',
				'deposit_amount' => 25,
				'is_active'      => 1,
				'created_at'     => current_time( 'mysql' ),
				'updated_at'     => current_time( 'mysql' ),
			),
			array( '%s', '%d', '%f', '%s', '%f', '%d', '%s', '%s' )
		);
		$this->fixture_ids['service_id'] = (int) $wpdb->insert_id;

		$wpdb->insert(
			$p . 'bookings_staff',
			array(
				'first_name'    => 'Elena',
				'last_name'     => 'Torres',
				'email'         => 'staff-' . wp_generate_password( 6, false ) . '@example.com',
				'password_hash' => wp_hash_password( 'test' ),
				'is_active'     => 1,
				'created_at'    => current_time( 'mysql' ),
				'updated_at'    => current_time( 'mysql' ),
			),
			array( '%s', '%s', '%s', '%s', '%d', '%s', '%s' )
		);
		$this->fixture_ids['staff_id'] = (int) $wpdb->insert_id;

		$defaults = array(
			'customer_id'               => $this->fixture_ids['customer_id'],
			'service_id'                => $this->fixture_ids['service_id'],
			'staff_id'                  => $this->fixture_ids['staff_id'],
			'booking_date'              => '2026-04-15',
			'start_time'                => '11:00:00',
			'end_time'                  => '12:00:00',
			'duration'                  => 60,
			'status'                    => 'confirmed',
			'total_price'               => 100.00,
			'deposit_paid'              => 0.00,
			'balance_due'               => 100.00,
			'payment_method'            => 'pay_on_arrival',
			'special_requests'          => null,
			'cooling_off_waiver_given'  => 0,
			'booking_reference'         => null,
			'created_at'                => current_time( 'mysql' ),
			'updated_at'                => current_time( 'mysql' ),
		);

		$data = array_merge( $defaults, $booking_overrides );
		$wpdb->insert( $p . 'bookings', $data );
		$this->fixture_ids['booking_id'] = (int) $wpdb->insert_id;

		return $this->fixture_ids['booking_id'];
	}

	/**
	 * @covers Bookit_Shortcodes::__construct
	 */
	public function test_v2_confirmation_shortcode_registered() {
		$this->assertTrue( shortcode_exists( 'bookit_booking_confirmed_v2' ) );
	}

	/**
	 * @covers Bookit_Shortcodes::render_booking_confirmed_v2
	 */
	public function test_v2_confirmation_page_renders_success_header() {
		$this->create_booking_fixture();
		$_GET['booking_id'] = $this->fixture_ids['booking_id'];

		$output = do_shortcode( '[bookit_booking_confirmed_v2]' );
		unset( $_GET['booking_id'] );

		$this->assertStringContainsString( 'bookit-confirmed-header', $output );
	}

	/**
	 * @covers Bookit_Shortcodes::render_booking_confirmed_v2
	 */
	public function test_v2_confirmation_page_renders_booking_reference() {
		$this->create_booking_fixture( array( 'booking_reference' => null ) );
		$_GET['booking_id'] = $this->fixture_ids['booking_id'];

		$output = do_shortcode( '[bookit_booking_confirmed_v2]' );
		unset( $_GET['booking_id'] );

		$this->assertStringContainsString( 'BK-', $output );
	}

	/**
	 * @covers Bookit_Shortcodes::render_booking_confirmed_v2
	 */
	public function test_v2_confirmation_page_does_not_show_raw_id_as_reference() {
		$this->create_booking_fixture( array( 'booking_reference' => null ) );
		$bid                = $this->fixture_ids['booking_id'];
		$_GET['booking_id'] = $bid;

		$output = do_shortcode( '[bookit_booking_confirmed_v2]' );
		unset( $_GET['booking_id'] );

		$expected_ref = 'BK-' . str_pad( (string) $bid, 8, '0', STR_PAD_LEFT );
		$this->assertStringContainsString( $expected_ref, $output );
		$this->assertStringNotContainsString( 'class="bookit-confirmed-ref">' . (string) $bid . '</span>', $output );
	}

	/**
	 * @covers Bookit_Shortcodes::render_booking_confirmed_v2
	 */
	public function test_v2_confirmation_page_email_copy_says_shortly() {
		$this->create_booking_fixture();
		$_GET['booking_id'] = $this->fixture_ids['booking_id'];

		$output = do_shortcode( '[bookit_booking_confirmed_v2]' );
		unset( $_GET['booking_id'] );

		$this->assertStringContainsString( 'shortly', strtolower( $output ) );
		$this->assertStringNotContainsString( 'has been sent', strtolower( $output ) );
	}

	/**
	 * @covers Bookit_Shortcodes::render_booking_confirmed_v2
	 */
	public function test_v2_confirmation_page_shows_deposit_rows_when_deposit_exists() {
		$this->create_booking_fixture(
			array(
				'payment_method'   => 'stripe',
				'total_price'      => 100.00,
				'deposit_paid'     => 25.00,
				'balance_due'        => 75.00,
			)
		);
		$_GET['booking_id'] = $this->fixture_ids['booking_id'];

		$output = do_shortcode( '[bookit_booking_confirmed_v2]' );
		unset( $_GET['booking_id'] );

		$this->assertStringContainsString( 'Today (deposit)', $output );
	}

	/**
	 * @covers Bookit_Shortcodes::render_booking_confirmed_v2
	 */
	public function test_v2_confirmation_page_shows_pay_on_arrival_block() {
		$this->create_booking_fixture(
			array(
				'payment_method' => 'pay_on_arrival',
				'total_price'    => 80.00,
				'deposit_paid'   => 0.00,
				'balance_due'    => 80.00,
			)
		);
		$_GET['booking_id'] = $this->fixture_ids['booking_id'];

		$output = do_shortcode( '[bookit_booking_confirmed_v2]' );
		unset( $_GET['booking_id'] );

		$this->assertStringContainsString( 'No deposit was taken', $output );
	}

	/**
	 * @covers Bookit_Shortcodes::render_booking_confirmed_v2
	 */
	public function test_v2_confirmation_page_hides_waiver_when_not_given() {
		$this->create_booking_fixture( array( 'cooling_off_waiver_given' => 0 ) );
		$_GET['booking_id'] = $this->fixture_ids['booking_id'];

		$output = do_shortcode( '[bookit_booking_confirmed_v2]' );
		unset( $_GET['booking_id'] );

		$this->assertStringNotContainsString( 'bookit-confirmed-waiver', $output );
	}

	/**
	 * @covers Bookit_Shortcodes::render_booking_confirmed_v2
	 */
	public function test_v2_confirmation_page_shows_waiver_when_given() {
		$this->create_booking_fixture( array( 'cooling_off_waiver_given' => 1 ) );
		$_GET['booking_id'] = $this->fixture_ids['booking_id'];

		$output = do_shortcode( '[bookit_booking_confirmed_v2]' );
		unset( $_GET['booking_id'] );

		$this->assertStringContainsString( 'bookit-confirmed-waiver', $output );
	}

	/**
	 * @covers Bookit_Shortcodes::render_booking_confirmed_v2
	 */
	public function test_v2_confirmation_page_hides_special_requests_when_empty() {
		$this->create_booking_fixture( array( 'special_requests' => null ) );
		$_GET['booking_id'] = $this->fixture_ids['booking_id'];

		$output = do_shortcode( '[bookit_booking_confirmed_v2]' );
		unset( $_GET['booking_id'] );

		$this->assertStringNotContainsString( 'bookit-confirmed-special-requests', $output );
	}

	/**
	 * @covers Bookit_Shortcodes::render_booking_confirmed_v2
	 */
	public function test_v2_confirmation_page_shows_special_requests_when_set() {
		$this->create_booking_fixture( array( 'special_requests' => 'Window seat please' ) );
		$_GET['booking_id'] = $this->fixture_ids['booking_id'];

		$output = do_shortcode( '[bookit_booking_confirmed_v2]' );
		unset( $_GET['booking_id'] );

		$this->assertStringContainsString( 'bookit-confirmed-special-requests', $output );
		$this->assertStringContainsString( 'Window seat please', $output );
	}

	/**
	 * @covers Bookit_Template_Loader::locate_template
	 */
	public function test_v2_confirmation_template_resolves_in_plugin() {
		$path = Bookit_Template_Loader::locate_template( 'booking-confirmed-v2.php' );

		$this->assertStringEndsWith( 'public/templates/booking-confirmed-v2.php', $path );
		$this->assertFileExists( $path );
	}

	/**
	 * @covers Bookit_Template_Loader::get_template
	 */
	public function test_v2_confirmation_template_renders_non_empty() {
		$this->create_booking_fixture();
		$_GET['booking_id'] = $this->fixture_ids['booking_id'];

		$output = Bookit_Template_Loader::get_template( 'booking-confirmed-v2.php', array(), true );
		unset( $_GET['booking_id'] );

		$this->assertNotEmpty( $output );
		$this->assertStringContainsString( 'bookit-confirmation-page', $output );
	}
}
