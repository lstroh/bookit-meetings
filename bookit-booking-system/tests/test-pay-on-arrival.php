<?php
/**
 * Unit Tests for Pay on Arrival
 * Sprint 2, Task 13 - Test-Driven Development
 *
 * Tests written BEFORE implementation to define the expected behaviour of the
 * Pay on Arrival payment method. Covers booking creation, customer handling,
 * validation, and confirmation flow.
 *
 * @package    Bookit_Booking_System
 * @subpackage Tests
 */

/**
 * Test Pay on Arrival payment method.
 *
 * @covers Booking_System_Payment_Processor::process_pay_on_arrival
 * @covers Booking_System_Booking_Creator::create_booking
 */
class Test_Pay_On_Arrival extends WP_UnitTestCase {

	/**
	 * Payment processor instance.
	 *
	 * @var Booking_System_Payment_Processor|null
	 */
	private $payment_processor;

	/**
	 * Booking creator instance.
	 *
	 * @var Booking_System_Booking_Creator|null
	 */
	private $booking_creator;

	/**
	 * Test service ID.
	 *
	 * @var int
	 */
	private $test_service_id;

	/**
	 * Test staff ID.
	 *
	 * @var int
	 */
	private $test_staff_id;

	/**
	 * Whether pay_on_arrival method exists on the payment processor.
	 *
	 * @var bool
	 */
	private $poa_method_available = false;

	/**
	 * Set up each test.
	 */
	public function setUp(): void {
		parent::setUp();

		$plugin_dir    = dirname( __DIR__ );
		$processor_file = $plugin_dir . '/includes/payment/class-payment-processor.php';
		$creator_file   = $plugin_dir . '/includes/booking/class-booking-creator.php';

		// Load required classes (skip entire suite if booking creator is missing).
		if ( ! file_exists( $creator_file ) ) {
			$this->markTestSkipped( 'Booking creator not implemented yet (Sprint 2, Task 13).' );
			return;
		}

		require_once $creator_file;
		$this->booking_creator = new Booking_System_Booking_Creator();

		if ( file_exists( $processor_file ) ) {
			require_once $processor_file;
			$this->payment_processor = new Booking_System_Payment_Processor();
			$this->poa_method_available = method_exists( $this->payment_processor, 'process_pay_on_arrival' );
		}

		// Load session manager for session cleanup tests.
		$session_manager_file = $plugin_dir . '/includes/core/class-session-manager.php';
		if ( file_exists( $session_manager_file ) ) {
			require_once $session_manager_file;
		}

		global $wpdb;
		$prefix = $wpdb->prefix;

		// Create test service: £75, 90 min, 50% deposit normally.
		$wpdb->insert(
			$prefix . 'bookings_services',
			array(
				'name'           => 'Test Massage',
				'duration'       => 90,
				'price'          => 75.00,
				'deposit_type'   => 'percentage',
				'deposit_amount' => 50,
				'is_active'      => 1,
				'created_at'     => current_time( 'mysql' ),
				'updated_at'     => current_time( 'mysql' ),
			),
			array( '%s', '%d', '%f', '%s', '%f', '%d', '%s', '%s' )
		);
		$this->test_service_id = (int) $wpdb->insert_id;

		// Create test staff (password_hash required by schema).
		$wpdb->insert(
			$prefix . 'bookings_staff',
			array(
				'first_name'    => 'Sarah',
				'last_name'     => 'Johnson',
				'email'         => 'sarah@salon.com',
				'password_hash' => wp_hash_password( 'test' ),
				'is_active'     => 1,
				'created_at'    => current_time( 'mysql' ),
				'updated_at'    => current_time( 'mysql' ),
			),
			array( '%s', '%s', '%s', '%s', '%d', '%s', '%s' )
		);
		$this->test_staff_id = (int) $wpdb->insert_id;

		// Ensure pay-on-arrival-relevant columns and ENUM values exist.
		$bookings_table = $prefix . 'bookings';

		// Add 'pending_payment' to status ENUM if not already present.
		$col_info = $wpdb->get_row( "SHOW COLUMNS FROM {$bookings_table} LIKE 'status'" );
		if ( $col_info && strpos( $col_info->Type, 'pending_payment' ) === false ) {
			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			$wpdb->query( "ALTER TABLE {$bookings_table} MODIFY COLUMN status ENUM('pending','pending_payment','confirmed','cancelled','completed','no_show') DEFAULT 'pending'" );
		}

		$col = $wpdb->get_results( "SHOW COLUMNS FROM {$bookings_table} LIKE 'balance_due'" );
		if ( empty( $col ) ) {
			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			$wpdb->query( "ALTER TABLE {$bookings_table} ADD COLUMN balance_due DECIMAL(10,2) DEFAULT 0.00 AFTER deposit_paid" );
		}

		$col = $wpdb->get_results( "SHOW COLUMNS FROM {$bookings_table} LIKE 'payment_intent_id'" );
		if ( empty( $col ) ) {
			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			$wpdb->query( "ALTER TABLE {$bookings_table} ADD COLUMN payment_intent_id VARCHAR(255) NULL AFTER payment_method" );
		}

		$col = $wpdb->get_results( "SHOW COLUMNS FROM {$bookings_table} LIKE 'stripe_session_id'" );
		if ( empty( $col ) ) {
			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			$wpdb->query( "ALTER TABLE {$bookings_table} ADD COLUMN stripe_session_id VARCHAR(255) NULL DEFAULT NULL AFTER payment_intent_id" );
		}

		$col = $wpdb->get_results( "SHOW COLUMNS FROM {$bookings_table} LIKE 'special_requests'" );
		if ( empty( $col ) ) {
			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			$wpdb->query( "ALTER TABLE {$bookings_table} ADD COLUMN special_requests TEXT NULL AFTER stripe_session_id" );
		}

		// Mock session data (Sprint 1 wizard structure).
		$_SESSION['bookit_wizard'] = array(
			'service_id'                => $this->test_service_id,
			'staff_id'                  => $this->test_staff_id,
			'date'                      => wp_date( 'Y-m-d', strtotime( '+30 days' ), wp_timezone() ),
			'time'                      => '10:00:00',
			'customer_first_name'       => 'Jane',
			'customer_last_name'        => 'Doe',
			'customer_email'            => 'jane@example.com',
			'customer_phone'            => '07700900456',
			'customer_special_requests' => 'First time client',
			'cooling_off_waiver'        => 1,
		);

		// Mock email sending (prevent actual emails in tests).
		add_filter( 'bookit_send_email', '__return_false' );
	}

	/**
	 * Tear down each test.
	 */
	public function tearDown(): void {
		global $wpdb;
		$prefix = $wpdb->prefix;

		remove_filter( 'bookit_send_email', '__return_false' );

		// Clean up test data.
		if ( $this->test_service_id > 0 ) {
			$wpdb->query(
				$wpdb->prepare(
					"DELETE FROM {$prefix}bookings WHERE service_id = %d",
					$this->test_service_id
				)
			);
			$wpdb->query(
				$wpdb->prepare(
					"DELETE FROM {$prefix}bookings_services WHERE id = %d",
					$this->test_service_id
				)
			);
		}
		if ( $this->test_staff_id > 0 ) {
			$wpdb->query(
				$wpdb->prepare(
					"DELETE FROM {$prefix}bookings_staff WHERE id = %d",
					$this->test_staff_id
				)
			);
		}
		$wpdb->query(
			$wpdb->prepare(
				"DELETE FROM {$prefix}bookings_customers WHERE email = %s",
				'jane@example.com'
			)
		);
		$wpdb->query(
			$wpdb->prepare(
				"DELETE FROM {$prefix}bookings_payments WHERE id > %d",
				0
			)
		);

		unset( $_SESSION['bookit_wizard'] );

		parent::tearDown();
	}

	// -------------------------------------------------------------------------
	// Helper: build booking data array for the booking creator.
	// -------------------------------------------------------------------------

	/**
	 * Build pay-on-arrival booking data from session wizard data.
	 *
	 * Maps the wizard session structure to the Booking_System_Booking_Creator
	 * expected format (booking_date / booking_time keys, amount_paid, etc.).
	 *
	 * @param array $overrides Optional overrides for individual fields.
	 * @return array Booking data ready for create_booking().
	 */
	private function build_poa_booking_data( array $overrides = array() ): array {
		$session = $_SESSION['bookit_wizard'];

		$defaults = array(
			'service_id'          => $session['service_id'],
			'staff_id'            => $session['staff_id'],
			'booking_date'        => $session['date'],
			'booking_time'        => $session['time'],
			'customer_first_name' => $session['customer_first_name'],
			'customer_last_name'  => $session['customer_last_name'],
			'customer_email'      => $session['customer_email'],
			'customer_phone'      => $session['customer_phone'],
			'special_requests'    => isset( $session['customer_special_requests'] ) ? $session['customer_special_requests'] : '',
			'payment_method'      => 'pay_on_arrival',
			'amount_paid'         => 0,
			'payment_intent_id'   => null,
			'stripe_session_id'   => null,
		);

		return array_merge( $defaults, $overrides );
	}

	// =========================================================================
	// BOOKING CREATION TESTS (4)
	// =========================================================================

	/**
	 * Test 1: Creates booking without triggering any payment processor (Stripe / PayPal).
	 *
	 * Arrange: Session with booking data, payment_method = 'pay_on_arrival'.
	 * Act:     Create booking via booking creator with amount_paid = 0.
	 * Assert:  Booking record exists in the database.
	 * Assert:  No payment record created (no Stripe / PayPal called).
	 *
	 * @covers Booking_System_Booking_Creator::create_booking
	 */
	public function test_creates_booking_without_payment() {
		$data   = $this->build_poa_booking_data();
		$result = $this->booking_creator->create_booking( $data );

		// Booking should be created successfully (returns integer booking ID).
		$this->assertIsInt( $result, 'create_booking() should return an integer booking ID' );
		$this->assertGreaterThan( 0, $result );

		global $wpdb;
		$prefix = $wpdb->prefix;

		// Verify booking exists in database.
		$booking = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$prefix}bookings WHERE id = %d",
				$result
			),
			ARRAY_A
		);
		$this->assertNotNull( $booking, 'Booking record should exist in the database' );

		// No payment record should be created (amount_paid = 0, no Stripe involved).
		$payment_count = (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$prefix}bookings_payments WHERE booking_id = %d",
				$result
			)
		);
		$this->assertEquals( 0, $payment_count, 'No payment record should exist for pay on arrival bookings' );
	}

	/**
	 * Test 2: Booking status is 'pending_payment' (not 'confirmed').
	 *
	 * Arrange: Create a pay-on-arrival booking.
	 * Act:     Inspect the booking record status.
	 * Assert:  status = 'pending_payment'.
	 *
	 * @covers Booking_System_Booking_Creator::create_booking
	 */
	public function test_booking_status_is_pending_payment() {
		$data       = $this->build_poa_booking_data();
		$booking_id = $this->booking_creator->create_booking( $data );

		$this->assertIsInt( $booking_id );

		global $wpdb;
		$booking = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT status FROM {$wpdb->prefix}bookings WHERE id = %d",
				$booking_id
			)
		);

		$this->assertNotNull( $booking );
		$this->assertEquals(
			'pending_payment',
			$booking->status,
			'Pay on arrival booking status should be "pending_payment", not "confirmed"'
		);
	}

	/**
	 * Test 3: Full amount is marked as balance due (no deposit paid).
	 *
	 * Arrange: Service costs £75.00, amount_paid = 0.
	 * Act:     Create pay-on-arrival booking.
	 * Assert:  deposit_paid = 0.00, balance_due = 75.00, total_price = 75.00.
	 *
	 * @covers Booking_System_Booking_Creator::create_booking
	 */
	public function test_full_amount_marked_as_balance_due() {
		$data       = $this->build_poa_booking_data();
		$booking_id = $this->booking_creator->create_booking( $data );

		$this->assertIsInt( $booking_id );

		global $wpdb;
		$booking = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT total_price, deposit_paid, balance_due FROM {$wpdb->prefix}bookings WHERE id = %d",
				$booking_id
			)
		);

		$this->assertNotNull( $booking );
		$this->assertEquals( 75.00, (float) $booking->total_price, 'Total price should be £75.00' );
		$this->assertEquals( 0.00, (float) $booking->deposit_paid, 'Deposit paid should be £0.00 for pay on arrival' );
		$this->assertEquals( 75.00, (float) $booking->balance_due, 'Balance due should equal the full price (£75.00)' );
	}

	/**
	 * Test 4: Payment method and payment identifiers stored correctly.
	 *
	 * Arrange: Pay on arrival booking data.
	 * Act:     Inspect booking record.
	 * Assert:  payment_method = 'pay_on_arrival'.
	 * Assert:  payment_intent_id = NULL.
	 * Assert:  stripe_session_id = NULL.
	 *
	 * @covers Booking_System_Booking_Creator::create_booking
	 */
	public function test_payment_method_stored_correctly() {
		$data       = $this->build_poa_booking_data();
		$booking_id = $this->booking_creator->create_booking( $data );

		$this->assertIsInt( $booking_id );

		global $wpdb;
		$booking = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT payment_method, payment_intent_id, stripe_session_id FROM {$wpdb->prefix}bookings WHERE id = %d",
				$booking_id
			)
		);

		$this->assertNotNull( $booking );
		$this->assertEquals( 'pay_on_arrival', $booking->payment_method, 'Payment method should be "pay_on_arrival"' );
		$this->assertNull( $booking->payment_intent_id, 'payment_intent_id should be NULL for pay on arrival' );
		$this->assertNull( $booking->stripe_session_id, 'stripe_session_id should be NULL for pay on arrival' );
	}

	// =========================================================================
	// CUSTOMER CREATION TESTS (2)
	// =========================================================================

	/**
	 * Test 5: Creates customer record for a new customer.
	 *
	 * Arrange: New customer (jane@example.com) not yet in database.
	 * Act:     Create pay-on-arrival booking.
	 * Assert:  Customer record created in wp_bookings_customers.
	 * Assert:  first_name, last_name, email, phone stored correctly.
	 *
	 * @covers Booking_System_Booking_Creator::create_booking
	 */
	public function test_creates_customer_record() {
		$data       = $this->build_poa_booking_data();
		$booking_id = $this->booking_creator->create_booking( $data );

		$this->assertIsInt( $booking_id );

		global $wpdb;
		$customer = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$wpdb->prefix}bookings_customers WHERE email = %s",
				'jane@example.com'
			)
		);

		$this->assertNotNull( $customer, 'Customer record should be created' );
		$this->assertEquals( 'Jane', $customer->first_name );
		$this->assertEquals( 'Doe', $customer->last_name );
		$this->assertEquals( 'jane@example.com', $customer->email );
		$this->assertEquals( '07700900456', $customer->phone );

		// Verify booking references the customer.
		$booking = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT customer_id FROM {$wpdb->prefix}bookings WHERE id = %d",
				$booking_id
			)
		);
		$this->assertEquals( (int) $customer->id, (int) $booking->customer_id );
	}

	/**
	 * Test 6: Reuses existing customer record (no duplicate).
	 *
	 * Arrange: Customer with jane@example.com already exists.
	 * Act:     Create another pay-on-arrival booking for same email.
	 * Assert:  Uses existing customer_id.
	 * Assert:  No duplicate customer record created.
	 *
	 * @covers Booking_System_Booking_Creator::create_booking
	 */
	public function test_reuses_existing_customer() {
		global $wpdb;
		$prefix = $wpdb->prefix;

		// Pre-create customer record.
		$existing_row = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT id FROM {$prefix}bookings_customers WHERE email = %s",
				'jane@example.com'
			)
		);
		if ( $existing_row ) {
			$existing_id = (int) $existing_row->id;
		} else {
			$wpdb->insert(
				$prefix . 'bookings_customers',
				array(
					'first_name' => 'Jane',
					'last_name'  => 'Doe',
					'email'      => 'jane@example.com',
					'phone'      => '07700900456',
					'created_at' => current_time( 'mysql' ),
					'updated_at' => current_time( 'mysql' ),
				),
				array( '%s', '%s', '%s', '%s', '%s', '%s' )
			);
			$existing_id = (int) $wpdb->insert_id;
		}

		// Create pay-on-arrival booking.
		$data       = $this->build_poa_booking_data();
		$booking_id = $this->booking_creator->create_booking( $data );

		$this->assertIsInt( $booking_id );

		// Assert no duplicate customer.
		$count = (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$prefix}bookings_customers WHERE email = %s",
				'jane@example.com'
			)
		);
		$this->assertEquals( 1, $count, 'Should not create a duplicate customer record' );

		// Assert booking uses the existing customer_id.
		$booking = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT customer_id FROM {$prefix}bookings WHERE id = %d",
				$booking_id
			)
		);
		$this->assertNotNull( $booking );
		$this->assertEquals( $existing_id, (int) $booking->customer_id, 'Should reuse existing customer_id' );
	}

	// =========================================================================
	// VALIDATION TESTS (2)
	// =========================================================================

	/**
	 * Test 7: Validates required fields (missing customer email).
	 *
	 * Arrange: Session data missing customer_email.
	 * Act:     Try to create booking.
	 * Assert:  Returns WP_Error with code 'missing_field'.
	 *
	 * @covers Booking_System_Booking_Creator::create_booking
	 */
	public function test_validates_required_fields() {
		$data = $this->build_poa_booking_data( array( 'customer_email' => '' ) );

		$result = $this->booking_creator->create_booking( $data );

		$this->assertInstanceOf( 'WP_Error', $result, 'Should return WP_Error when required field is missing' );
		$this->assertEquals( 'missing_field', $result->get_error_code() );
		$this->assertStringContainsString( 'customer_email', $result->get_error_message() );
	}

	/**
	 * Test 8: Prevents double booking (staff already booked at same time).
	 *
	 * Arrange: Staff already has a booking at the same date/time.
	 * Act:     Try to create a conflicting pay-on-arrival booking.
	 * Assert:  Returns WP_Error with code 'slot_unavailable'.
	 *
	 * @covers Booking_System_Booking_Creator::create_booking
	 */
	public function test_prevents_double_booking() {
		// First, create a legitimate booking to occupy the slot.
		$data       = $this->build_poa_booking_data();
		$booking_id = $this->booking_creator->create_booking( $data );
		$this->assertIsInt( $booking_id, 'First booking should succeed' );

		// Now try to create a conflicting booking at the same date/time/staff.
		$conflict_data = $this->build_poa_booking_data( array(
			'customer_email'      => 'another@example.com',
			'customer_first_name' => 'Another',
			'customer_last_name'  => 'Customer',
		) );

		$result = $this->booking_creator->create_booking( $conflict_data );

		$this->assertInstanceOf( 'WP_Error', $result, 'Should return WP_Error for conflicting booking' );
		$this->assertEquals( 'slot_unavailable', $result->get_error_code() );

		// Clean up extra customer.
		global $wpdb;
		$wpdb->query(
			$wpdb->prepare(
				"DELETE FROM {$wpdb->prefix}bookings_customers WHERE email = %s",
				'another@example.com'
			)
		);
	}

	// =========================================================================
	// CONFIRMATION TESTS (2)
	// =========================================================================

	/**
	 * Test 9: Successful booking returns data for redirect to confirmation page.
	 *
	 * Arrange: Successful pay-on-arrival booking.
	 * Act:     Process via payment processor (if available) or verify booking ID.
	 * Assert:  Result contains booking_id that can be used for confirmation URL.
	 * Assert:  Confirmation URL would include /booking-confirmed and booking_id.
	 *
	 * @covers Booking_System_Payment_Processor::process_pay_on_arrival
	 */
	public function test_redirects_to_confirmation_page() {
		if ( $this->poa_method_available ) {
			// Test the payment processor's process_pay_on_arrival method.
			$result = $this->payment_processor->process_pay_on_arrival( $_SESSION['bookit_wizard'] );

			$this->assertIsArray( $result, 'process_pay_on_arrival should return an array' );
			$this->assertArrayHasKey( 'booking_id', $result, 'Result should contain booking_id' );
			$this->assertGreaterThan( 0, $result['booking_id'] );

			// Check redirect URL.
			$this->assertArrayHasKey( 'redirect_url', $result, 'Result should contain redirect_url' );
			$this->assertStringContainsString( '/booking-confirmed', $result['redirect_url'] );
			$this->assertStringContainsString( 'booking_id=' . $result['booking_id'], $result['redirect_url'] );
		} else {
			// Fallback: test via booking creator directly.
			$data       = $this->build_poa_booking_data();
			$booking_id = $this->booking_creator->create_booking( $data );

			$this->assertIsInt( $booking_id, 'Booking should be created successfully' );
			$this->assertGreaterThan( 0, $booking_id );

			// Build the expected redirect URL and verify it's valid.
			$redirect_url = home_url( '/booking-confirmed?booking_id=' . $booking_id );
			$this->assertStringContainsString( '/booking-confirmed', $redirect_url );
			$this->assertStringContainsString( 'booking_id=' . $booking_id, $redirect_url );

			// Mark as incomplete: process_pay_on_arrival() not yet implemented.
			$this->markTestIncomplete(
				'Payment processor process_pay_on_arrival() not yet implemented. '
				. 'Booking creation verified via Booking_System_Booking_Creator.'
			);
		}
	}

	/**
	 * Test 10: Session wizard data is cleared after successful booking.
	 *
	 * Arrange: $_SESSION['bookit_wizard'] populated with booking data.
	 * Act:     Complete a pay-on-arrival booking.
	 * Assert:  $_SESSION['bookit_wizard'] is cleared / reset.
	 *
	 * @covers Booking_System_Payment_Processor::process_pay_on_arrival
	 */
	public function test_clears_session_after_booking() {
		if ( $this->poa_method_available ) {
			// Test that process_pay_on_arrival clears/resets session.
			$this->assertNotEmpty( $_SESSION['bookit_wizard'], 'Session should be populated before booking' );

			// Capture session data before the call (process_pay_on_arrival takes data by value).
			$session_snapshot = $_SESSION['bookit_wizard'];
			$result = $this->payment_processor->process_pay_on_arrival( $session_snapshot );

			$this->assertIsArray( $result );
			$this->assertArrayHasKey( 'booking_id', $result );

			// Session wizard data should be reset to defaults (service_id = null, etc.).
			// Bookit_Session_Manager::complete_booking() resets to defaults rather than unsetting.
			if ( isset( $_SESSION['bookit_wizard'] ) && is_array( $_SESSION['bookit_wizard'] ) ) {
				// Session was reset to defaults (service_id = null, etc.).
				// NOTE: Do NOT use ?? here -- null ?? 'fallback' returns 'fallback'.
				$this->assertArrayHasKey( 'service_id', $_SESSION['bookit_wizard'] );
				$this->assertNull( $_SESSION['bookit_wizard']['service_id'], 'service_id should be null (reset) after pay-on-arrival booking' );
				$this->assertNull( $_SESSION['bookit_wizard']['staff_id'], 'staff_id should be null (reset) after pay-on-arrival booking' );
				$this->assertNull( $_SESSION['bookit_wizard']['date'], 'date should be null (reset) after pay-on-arrival booking' );
				$this->assertNull( $_SESSION['bookit_wizard']['time'], 'time should be null (reset) after pay-on-arrival booking' );
			} else {
				// Session key was fully unset -- also acceptable.
				$this->assertFalse(
					isset( $_SESSION['bookit_wizard'] ),
					'Session wizard data should be cleared after successful pay-on-arrival booking'
				);
			}
		} elseif ( class_exists( 'Bookit_Session_Manager' ) ) {
			// Fallback: verify session manager can clear wizard data.
			Bookit_Session_Manager::init();
			Bookit_Session_Manager::set_data( array(
				'service_id' => $this->test_service_id,
				'staff_id'   => $this->test_staff_id,
				'date'       => '2026-03-15',
				'time'       => '10:00:00',
			) );

			$this->assertNotEmpty( Bookit_Session_Manager::get( 'service_id' ), 'Session should have service_id before clear' );

			// Create booking first to prove the flow works.
			$data       = $this->build_poa_booking_data();
			$booking_id = $this->booking_creator->create_booking( $data );
			$this->assertIsInt( $booking_id );

			// Now clear the session (as the payment processor should do).
			Bookit_Session_Manager::clear();

			$data_after = Bookit_Session_Manager::get_data();
			$this->assertNull( $data_after['service_id'], 'service_id should be cleared after booking' );
			$this->assertNull( $data_after['staff_id'], 'staff_id should be cleared after booking' );
			$this->assertNull( $data_after['date'], 'date should be cleared after booking' );
			$this->assertNull( $data_after['time'], 'time should be cleared after booking' );

			$this->markTestIncomplete(
				'Payment processor process_pay_on_arrival() not yet implemented. '
				. 'Session cleanup verified via Bookit_Session_Manager::clear().'
			);
		} else {
			// Direct session manipulation fallback.
			$this->assertNotEmpty( $_SESSION['bookit_wizard'] );

			$data       = $this->build_poa_booking_data();
			$booking_id = $this->booking_creator->create_booking( $data );
			$this->assertIsInt( $booking_id );

			// Simulate session clear as the processor should do.
			unset( $_SESSION['bookit_wizard'] );

			$this->assertFalse(
				isset( $_SESSION['bookit_wizard'] ),
				'Session wizard data should be cleared after pay-on-arrival booking'
			);

			$this->markTestIncomplete(
				'Payment processor process_pay_on_arrival() not yet implemented.'
			);
		}
	}

	// =========================================================================
	// EDGE CASE TESTS
	// =========================================================================

	/**
	 * Test that empty payment method is rejected.
	 *
	 * @covers Booking_System_Booking_Creator::create_booking
	 */
	public function test_rejects_empty_payment_method() {
		$data = $this->build_poa_booking_data( array( 'payment_method' => '' ) );

		$result = $this->booking_creator->create_booking( $data );

		$this->assertInstanceOf( 'WP_Error', $result );
		$this->assertEquals( 'missing_field', $result->get_error_code() );
	}

	/**
	 * Test that invalid service_id is rejected.
	 *
	 * @covers Booking_System_Booking_Creator::create_booking
	 */
	public function test_rejects_invalid_service_id() {
		$data = $this->build_poa_booking_data( array( 'service_id' => 99999 ) );

		$result = $this->booking_creator->create_booking( $data );

		$this->assertInstanceOf( 'WP_Error', $result );
		$this->assertEquals( 'invalid_service', $result->get_error_code() );
	}

	/**
	 * Test that invalid staff_id is rejected.
	 *
	 * @covers Booking_System_Booking_Creator::create_booking
	 */
	public function test_rejects_invalid_staff_id() {
		$data = $this->build_poa_booking_data( array( 'staff_id' => 99999 ) );

		$result = $this->booking_creator->create_booking( $data );

		$this->assertInstanceOf( 'WP_Error', $result );
		$this->assertEquals( 'invalid_staff', $result->get_error_code() );
	}

	/**
	 * Test that missing customer first name is rejected.
	 *
	 * @covers Booking_System_Booking_Creator::create_booking
	 */
	public function test_rejects_missing_customer_name() {
		$data = $this->build_poa_booking_data( array( 'customer_first_name' => '' ) );

		$result = $this->booking_creator->create_booking( $data );

		$this->assertInstanceOf( 'WP_Error', $result );
		$this->assertEquals( 'missing_field', $result->get_error_code() );
	}

	/**
	 * Test that invalid email format is rejected.
	 *
	 * @covers Booking_System_Booking_Creator::create_booking
	 */
	public function test_rejects_invalid_email_format() {
		$data = $this->build_poa_booking_data( array( 'customer_email' => 'not-an-email' ) );

		$result = $this->booking_creator->create_booking( $data );

		$this->assertInstanceOf( 'WP_Error', $result );
		$this->assertEquals( 'invalid_email', $result->get_error_code() );
	}

	/**
	 * Test that end time is correctly calculated from service duration (90 min).
	 *
	 * Arrange: Service duration = 90 min, start_time = 10:00:00.
	 * Act:     Create booking.
	 * Assert:  end_time = 11:30:00.
	 *
	 * @covers Booking_System_Booking_Creator::create_booking
	 */
	public function test_calculates_end_time_from_duration() {
		$data       = $this->build_poa_booking_data();
		$booking_id = $this->booking_creator->create_booking( $data );

		$this->assertIsInt( $booking_id );

		global $wpdb;
		$booking = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT start_time, end_time, duration FROM {$wpdb->prefix}bookings WHERE id = %d",
				$booking_id
			)
		);

		$this->assertNotNull( $booking );
		$this->assertEquals( '10:00:00', $booking->start_time );
		$this->assertEquals( '11:30:00', $booking->end_time, 'End time should be 10:00 + 90min = 11:30' );
		$this->assertEquals( 90, (int) $booking->duration );
	}

	/**
	 * Test that special requests are stored in the booking.
	 *
	 * @covers Booking_System_Booking_Creator::create_booking
	 */
	public function test_stores_special_requests() {
		$data       = $this->build_poa_booking_data();
		$booking_id = $this->booking_creator->create_booking( $data );

		$this->assertIsInt( $booking_id );

		global $wpdb;
		$booking = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT special_requests FROM {$wpdb->prefix}bookings WHERE id = %d",
				$booking_id
			)
		);

		$this->assertNotNull( $booking );
		$this->assertEquals( 'First time client', $booking->special_requests );
	}
}
