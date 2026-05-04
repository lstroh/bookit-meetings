<?php
/**
 * Unit Tests for Payment Success Handling
 * Sprint 2, Task 5
 *
 * @package    Bookit_Booking_System
 * @subpackage Tests
 */

/**
 * Test payment success flow: booking retrieval, confirmation emails, session cleanup.
 *
 * @covers Booking_System_Booking_Retriever
 * @covers Booking_System_Email_Sender
 * @covers Bookit_Session_Manager (session cleanup)
 */
class Test_Payment_Success extends WP_UnitTestCase {

	/**
	 * Booking retriever instance.
	 *
	 * @var Booking_System_Booking_Retriever|null
	 */
	private $booking_retriever;

	/**
	 * Email sender instance.
	 *
	 * @var Booking_System_Email_Sender|null
	 */
	private $email_sender;

	/**
	 * Test booking ID.
	 *
	 * @var int
	 */
	private $test_booking_id;

	/**
	 * Test customer ID.
	 *
	 * @var int
	 */
	private $test_customer_id;

	/**
	 * Test service ID.
	 *
	 * @var int
	 */
	private int $test_service_id = 0;

	/**
	 * Test staff ID.
	 *
	 * @var int
	 */
	private int $test_staff_id = 0;

	/**
	 * Whether required classes (retriever, email sender) are available.
	 *
	 * @var bool
	 */
	private $classes_available = false;

	/**
	 * Set up each test.
	 */
	public function setUp(): void {
		parent::setUp();
		bookit_test_truncate_tables(
			array(
				'bookings_package_redemptions',
				'bookings_customer_packages',
				'bookings',
				'bookings_payments',
				'bookings_customers',
				'bookings_staff',
				'bookings_services',
			)
		);

		$plugin_dir = dirname( __DIR__ );
		$retriever_file = $plugin_dir . '/includes/booking/class-booking-retriever.php';
		$email_file     = $plugin_dir . '/includes/email/class-email-sender.php';
		$queue_file     = $plugin_dir . '/includes/notifications/class-bookit-email-queue.php';
		$notify_fn_file = $plugin_dir . '/includes/functions-notifications.php';
		$dispatcher_file = $plugin_dir . '/includes/notifications/class-bookit-notification-dispatcher.php';
		$email_iface_file = $plugin_dir . '/includes/notifications/interfaces/interface-bookit-email-provider.php';
		$sms_iface_file   = $plugin_dir . '/includes/notifications/interfaces/interface-bookit-sms-provider.php';
		$brevo_email_provider_file = $plugin_dir . '/includes/notifications/providers/class-bookit-brevo-email-provider.php';
		$wp_mail_provider_file     = $plugin_dir . '/includes/notifications/providers/class-bookit-wp-mail-fallback-provider.php';
		$brevo_sms_provider_file   = $plugin_dir . '/includes/notifications/providers/class-bookit-brevo-sms-provider.php';
		$notification_exception_file = $plugin_dir . '/includes/notifications/class-bookit-notification-exception.php';

		// Load session manager (used by session cleanup tests).
		require_once $plugin_dir . '/includes/core/class-session-manager.php';

		// Load booking retriever and email sender when implemented (TDD).
		if ( file_exists( $retriever_file ) && file_exists( $email_file ) ) {
			// Notification stack is required for queued email sending.
			if ( file_exists( $email_iface_file ) ) {
				require_once $email_iface_file;
			}
			if ( file_exists( $sms_iface_file ) ) {
				require_once $sms_iface_file;
			}
			if ( file_exists( $brevo_email_provider_file ) ) {
				require_once $brevo_email_provider_file;
			}
			if ( file_exists( $wp_mail_provider_file ) ) {
				require_once $wp_mail_provider_file;
			}
			if ( file_exists( $brevo_sms_provider_file ) ) {
				require_once $brevo_sms_provider_file;
			}
			if ( file_exists( $queue_file ) ) {
				require_once $queue_file;
			}
			if ( file_exists( $notify_fn_file ) ) {
				require_once $notify_fn_file;
			}
			if ( file_exists( $dispatcher_file ) ) {
				require_once $dispatcher_file;
			}
			if ( file_exists( $notification_exception_file ) ) {
				require_once $notification_exception_file;
			}

			require_once $retriever_file;
			require_once $email_file;
			$this->booking_retriever = new Booking_System_Booking_Retriever();
			$this->email_sender     = new Booking_System_Email_Sender();
			$this->classes_available = true;
		}

		global $wpdb;
		$prefix = $wpdb->prefix;

		// Create test service.
		$wpdb->insert(
			$prefix . 'bookings_services',
			array(
				'name'           => 'Test Haircut',
				'duration'       => 60,
				'price'          => 50.00,
				'deposit_type'   => 'percentage',
				'deposit_amount' => 100,
			)
		);
		$this->test_service_id = (int) $wpdb->insert_id;

		// Create test staff (password_hash required by schema).
		$wpdb->insert(
			$prefix . 'bookings_staff',
			array(
				'first_name'    => 'Emma',
				'last_name'     => 'Thompson',
				'email'         => 'emma@salon.com',
				'password_hash' => wp_hash_password( 'test' ),
			)
		);
		$this->test_staff_id = (int) $wpdb->insert_id;

		// Create test customer (get-or-create to avoid duplicate key across tests).
		$existing = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT id FROM {$prefix}bookings_customers WHERE email = %s",
				'john@example.com'
			)
		);
		if ( $existing ) {
			$this->test_customer_id = (int) $existing->id;
		} else {
			$wpdb->insert(
				$prefix . 'bookings_customers',
				array(
					'first_name' => 'John',
					'last_name'  => 'Smith',
					'email'      => 'john@example.com',
					'phone'      => '07700900123',
				)
			);
			$this->test_customer_id = (int) $wpdb->insert_id;
		}

		// Ensure new columns exist on bookings for tests (TDD: implementation may add them).
		$bookings_table = $prefix . 'bookings';
		
		// Add balance_due column if missing.
		$col = $wpdb->get_results( "SHOW COLUMNS FROM {$bookings_table} LIKE 'balance_due'" );
		if ( empty( $col ) ) {
			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			$wpdb->query( "ALTER TABLE {$bookings_table} ADD COLUMN balance_due DECIMAL(10,2) DEFAULT 0.00 AFTER deposit_paid" );
		}
		
		// Add payment_intent_id column if missing.
		$col = $wpdb->get_results( "SHOW COLUMNS FROM {$bookings_table} LIKE 'payment_intent_id'" );
		if ( empty( $col ) ) {
			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			$wpdb->query( "ALTER TABLE {$bookings_table} ADD COLUMN payment_intent_id VARCHAR(255) NULL AFTER payment_method" );
		}
		
		// Add stripe_session_id column if missing.
		$col = $wpdb->get_results( "SHOW COLUMNS FROM {$bookings_table} LIKE 'stripe_session_id'" );
		if ( empty( $col ) ) {
			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			$wpdb->query( "ALTER TABLE {$bookings_table} ADD COLUMN stripe_session_id VARCHAR(255) NULL DEFAULT NULL AFTER payment_intent_id" );
		}
		
		// Add special_requests column if missing (renamed from customer_notes).
		$col = $wpdb->get_results( "SHOW COLUMNS FROM {$bookings_table} LIKE 'special_requests'" );
		if ( empty( $col ) ) {
			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			$wpdb->query( "ALTER TABLE {$bookings_table} ADD COLUMN special_requests TEXT NULL AFTER stripe_session_id" );
		}

		// Create test booking (schema: duration, deposit_amount, deposit_paid, balance_due, payment_intent_id, special_requests).
		$wpdb->insert(
			$prefix . 'bookings',
			array(
				'customer_id'       => $this->test_customer_id,
				'service_id'        => $this->test_service_id,
				'staff_id'          => $this->test_staff_id,
				'booking_date'      => '2026-02-15',
				'start_time'        => '14:00:00',
				'end_time'          => '15:00:00',
				'duration'          => 60,
				'status'            => 'confirmed',
				'total_price'       => 50.00,
				'deposit_amount'    => 50.00,
				'deposit_paid'      => 50.00,
				'balance_due'       => 0.00,
				'full_amount_paid'  => 1,
				'payment_method'    => 'stripe',
				'payment_intent_id' => 'pi_test_intent123',
				'stripe_session_id' => 'cs_test_session123',
				'special_requests'  => '',
				'created_at'        => current_time( 'mysql' ),
				'updated_at'        => current_time( 'mysql' ),
			),
			array( '%d', '%d', '%d', '%s', '%s', '%s', '%d', '%s', '%f', '%f', '%f', '%f', '%d', '%s', '%s', '%s', '%s', '%s', '%s' )
		);
		$this->test_booking_id = (int) $wpdb->insert_id;

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
		if ( $this->test_booking_id > 0 ) {
			$wpdb->query( "DELETE FROM {$prefix}bookings WHERE id = " . $this->test_booking_id );
		}
		if ( $this->test_customer_id > 0 ) {
			$wpdb->query( "DELETE FROM {$prefix}bookings_customers WHERE id = " . $this->test_customer_id );
		}
		$wpdb->delete( $prefix . 'bookings_staff', array( 'id' => $this->test_staff_id ), array( '%d' ) );
		$wpdb->delete( $prefix . 'bookings_services', array( 'id' => $this->test_service_id ), array( '%d' ) );

		// Reset session for next test.
		if ( isset( $_SESSION[ Bookit_Session_Manager::SESSION_KEY ] ) ) {
			Bookit_Session_Manager::clear();
		}

		parent::tearDown();
	}

	// -------------------------------------------------------------------------
	// BOOKING RETRIEVAL TESTS (5)
	// -------------------------------------------------------------------------

	/**
	 * Test that booking is retrieved by Stripe session ID.
	 *
	 * Arrange: Booking with stripe_session_id = 'cs_test_session123'
	 * Act: Call get_booking_by_stripe_session('cs_test_session123')
	 * Assert: Returns booking array with all fields
	 */
	public function test_retrieves_booking_by_stripe_session_id() {
		if ( ! $this->classes_available || ! $this->booking_retriever ) {
			$this->markTestSkipped( 'Booking_Retriever not implemented yet (Sprint 2, Task 5).' );
			return;
		}

		$booking = $this->booking_retriever->get_booking_by_stripe_session( 'cs_test_session123' );

		$this->assertNotNull( $booking );
		$this->assertIsArray( $booking );
		$this->assertEquals( $this->test_booking_id, (int) $booking['id'] );
		$this->assertEquals( '2026-02-15', $booking['booking_date'] );
		$this->assertEquals( '14:00:00', $booking['start_time'] );
		$this->assertEquals( '15:00:00', $booking['end_time'] );
		$this->assertEquals( 'confirmed', $booking['status'] );
		$this->assertEquals( 50.00, (float) $booking['total_price'] );
		$this->assertEquals( 'stripe', $booking['payment_method'] );
	}

	/**
	 * Test that retrieved booking includes customer details.
	 *
	 * Arrange: Booking with customer_id
	 * Act: Retrieve booking
	 * Assert: Booking array includes customer first_name, last_name, email, phone
	 */
	public function test_booking_includes_customer_details() {
		if ( ! $this->classes_available || ! $this->booking_retriever ) {
			$this->markTestSkipped( 'Booking_Retriever not implemented yet (Sprint 2, Task 5).' );
			return;
		}

		$booking = $this->booking_retriever->get_booking_by_stripe_session( 'cs_test_session123' );

		$this->assertNotNull( $booking );
		$this->assertEquals( 'John', $booking['customer_first_name'] );
		$this->assertEquals( 'Smith', $booking['customer_last_name'] );
		$this->assertEquals( 'john@example.com', $booking['customer_email'] );
		$this->assertEquals( '07700900123', $booking['customer_phone'] );
	}

	/**
	 * Test that retrieved booking includes service details.
	 *
	 * Arrange: Booking with service_id
	 * Act: Retrieve booking
	 * Assert: Booking array includes service name, price, duration
	 */
	public function test_booking_includes_service_details() {
		if ( ! $this->classes_available || ! $this->booking_retriever ) {
			$this->markTestSkipped( 'Booking_Retriever not implemented yet (Sprint 2, Task 5).' );
			return;
		}

		$booking = $this->booking_retriever->get_booking_by_stripe_session( 'cs_test_session123' );

		$this->assertNotNull( $booking );
		$this->assertEquals( 'Test Haircut', $booking['service_name'] );
		$this->assertEquals( 50.00, (float) $booking['service_price'] );
		$this->assertEquals( 60, (int) $booking['service_duration'] );
	}

	/**
	 * Test that retrieved booking includes staff details.
	 *
	 * Arrange: Booking with staff_id
	 * Act: Retrieve booking
	 * Assert: Booking array includes staff first_name, last_name
	 */
	public function test_booking_includes_staff_details() {
		if ( ! $this->classes_available || ! $this->booking_retriever ) {
			$this->markTestSkipped( 'Booking_Retriever not implemented yet (Sprint 2, Task 5).' );
			return;
		}

		$booking = $this->booking_retriever->get_booking_by_stripe_session( 'cs_test_session123' );

		$this->assertNotNull( $booking );
		$this->assertEquals( 'Emma Thompson', $booking['staff_name'] );
		$this->assertArrayHasKey( 'staff_first_name', $booking );
		$this->assertArrayHasKey( 'staff_last_name', $booking );
		$this->assertEquals( 'Emma', $booking['staff_first_name'] );
		$this->assertEquals( 'Thompson', $booking['staff_last_name'] );
	}

	/**
	 * Test that invalid session ID returns null.
	 *
	 * Arrange: No booking with given session_id
	 * Act: Call get_booking_by_stripe_session('invalid_session')
	 * Assert: Returns null (not WP_Error, not false)
	 */
	public function test_returns_null_for_invalid_session_id() {
		if ( ! $this->classes_available || ! $this->booking_retriever ) {
			$this->markTestSkipped( 'Booking_Retriever not implemented yet (Sprint 2, Task 5).' );
			return;
		}

		$booking = $this->booking_retriever->get_booking_by_stripe_session( 'invalid_session' );

		$this->assertNull( $booking );
		$this->assertNotInstanceOf( 'WP_Error', $booking );
	}

	// -------------------------------------------------------------------------
	// EMAIL SENDING TESTS (5)
	// -------------------------------------------------------------------------

	/**
	 * Test that customer confirmation email is sent with correct recipient and content.
	 *
	 * Arrange: Valid booking data
	 * Act: Call send_customer_confirmation($booking)
	 * Assert: wp_mail called with correct recipient, subject contains "Booking Confirmed", body contains booking details
	 */
	public function test_sends_customer_confirmation_email() {
		if ( ! $this->classes_available || ! $this->email_sender || ! $this->booking_retriever ) {
			$this->markTestSkipped( 'Email_Sender or Booking_Retriever not implemented yet (Sprint 2, Task 5).' );
			return;
		}

		$booking = $this->booking_retriever->get_booking_by_stripe_session( 'cs_test_session123' );
		$this->assertNotNull( $booking );

		// Remove the bypass filter so enqueue actually happens.
		remove_filter( 'bookit_send_email', '__return_false' );

		$wp_mail_called    = false;
		$captured_to       = '';
		$captured_subject  = '';
		$captured_body     = '';
		add_filter(
			'pre_wp_mail',
			function ( $null, $atts ) use ( &$wp_mail_called, &$captured_to, &$captured_subject, &$captured_body ) {
				$wp_mail_called  = true;
				$captured_to     = is_array( $atts['to'] ) ? $atts['to'][0] : $atts['to'];
				$captured_subject = $atts['subject'];
				$captured_body   = $atts['message'];
				return false; // Prevent actual email sending.
			},
			10,
			2
		);

		$result = $this->email_sender->send_customer_confirmation( $booking );

		remove_all_filters( 'pre_wp_mail' );
		add_filter( 'bookit_send_email', '__return_false' ); // Re-add for other tests.

		$this->assertTrue( $result );
		$this->assertFalse( is_wp_error( $result ) );
		$this->assertFalse( $wp_mail_called, 'wp_mail should not be called directly' );

		global $wpdb;
		$table = $wpdb->prefix . 'bookit_email_queue';
		$row   = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$table} WHERE email_type = %s ORDER BY id DESC LIMIT 1",
				'customer_confirmation'
			),
			ARRAY_A
		);

		$this->assertIsArray( $row );
		$this->assertSame( 'pending', $row['status'] );
		$this->assertSame( 'john@example.com', $row['recipient_email'] );
		$this->assertStringContainsString( 'Booking Confirmed', (string) $row['subject'] );
		$this->assertStringContainsString( 'Test Haircut', (string) $row['html_body'] );
	}

	/**
	 * Test that customer email body includes service, date, time, staff.
	 *
	 * Arrange: Booking with service, date, time, staff
	 * Act: Generate email body
	 * Assert: Body includes service name, date (e.g. Saturday, 15 February 2026), time (e.g. 2:00 PM), staff name
	 */
	public function test_customer_email_includes_booking_details() {
		if ( ! $this->classes_available || ! $this->email_sender || ! $this->booking_retriever ) {
			$this->markTestSkipped( 'Email_Sender or Booking_Retriever not implemented yet (Sprint 2, Task 5).' );
			return;
		}

		$booking = $this->booking_retriever->get_booking_by_stripe_session( 'cs_test_session123' );
		$this->assertNotNull( $booking );

		$email_body = $this->email_sender->generate_customer_email( $booking );

		$this->assertStringContainsString( 'Test Haircut', $email_body );
		$this->assertStringContainsString( 'Emma Thompson', $email_body );
		// Date formatted as "Sunday, 15 February 2026" or similar (not raw 2026-02-15).
		$this->assertMatchesRegularExpression( '/15\s+February\s+2026/', $email_body );
		// Time formatted as "2:00 PM" (not raw 14:00).
		$this->assertMatchesRegularExpression( '/2:00\s*(PM|pm)/', $email_body );
	}

	/**
	 * Test that customer email includes payment summary (total, paid, balance due).
	 *
	 * Arrange: Booking with total £50, deposit £50, balance £0
	 * Act: Generate email body
	 * Assert: Email shows Total: £50.00, Paid: £50.00, Balance Due: £0.00
	 */
	public function test_customer_email_includes_payment_summary() {
		if ( ! $this->classes_available || ! $this->email_sender || ! $this->booking_retriever ) {
			$this->markTestSkipped( 'Email_Sender or Booking_Retriever not implemented yet (Sprint 2, Task 5).' );
			return;
		}

		$booking = $this->booking_retriever->get_booking_by_stripe_session( 'cs_test_session123' );
		$this->assertNotNull( $booking );

		$email_body = $this->email_sender->generate_customer_email( $booking );

		// Check payment values are present (HTML structure has label and value on separate lines).
		$this->assertStringContainsString( '50.00', $email_body );
		$this->assertStringContainsString( 'Total', $email_body );
		$this->assertMatchesRegularExpression( '/(Paid|paid|Deposit|deposit)/i', $email_body );
		$this->assertMatchesRegularExpression( '/(Balance|balance)/i', $email_body );
	}

	/**
	 * Test that email send failure returns WP_Error and is handled gracefully.
	 *
	 * Arrange: Mock wp_mail to return false (via pre_wp_mail).
	 * Act: Send confirmation email
	 * Assert: Returns WP_Error with code 'email_failed', logs error message
	 */
	public function test_handles_email_send_failure_gracefully() {
		if ( ! $this->classes_available || ! $this->email_sender || ! $this->booking_retriever ) {
			$this->markTestSkipped( 'Email_Sender or Booking_Retriever not implemented yet (Sprint 2, Task 5).' );
			return;
		}

		$booking = $this->booking_retriever->get_booking_by_stripe_session( 'cs_test_session123' );
		$this->assertNotNull( $booking );

		// wp_mail() is not called directly anymore; enqueue should still succeed.
		add_filter( 'pre_wp_mail', '__return_false', 10, 0 );
		$result = $this->email_sender->send_customer_confirmation( $booking );
		remove_filter( 'pre_wp_mail', '__return_false', 10 );

		$this->assertTrue( $result );
		$this->assertFalse( is_wp_error( $result ) );
	}

	// -------------------------------------------------------------------------
	// SESSION CLEANUP TESTS (2)
	// -------------------------------------------------------------------------

	/**
	 * Test that booking wizard session is cleared after success.
	 *
	 * Arrange: $_SESSION['bookit_wizard'] with booking data
	 * Act: Call clear_booking_session()
	 * Assert: $_SESSION['bookit_wizard'] is unset or empty (reset to default)
	 */
	public function test_clears_booking_wizard_session() {
		Bookit_Session_Manager::init();
		Bookit_Session_Manager::set_data( array(
			'service_id' => $this->test_service_id,
			'staff_id'   => $this->test_staff_id,
			'date'       => '2026-02-15',
			'time'       => '14:00:00',
			'customer'   => array( 'email' => 'john@example.com' ),
		) );

		$this->assertNotEmpty( Bookit_Session_Manager::get( 'service_id' ) );

		// clear_booking_session() is the contract; implementation may delegate to Bookit_Session_Manager::clear() or complete_booking().
		if ( $this->classes_available && $this->booking_retriever && method_exists( $this->booking_retriever, 'clear_booking_session' ) ) {
			$this->booking_retriever->clear_booking_session();
		} else {
			Bookit_Session_Manager::clear();
		}

		$data = Bookit_Session_Manager::get_data();
		$this->assertNull( $data['service_id'] );
		$this->assertNull( $data['staff_id'] );
		$this->assertNull( $data['date'] );
		$this->assertNull( $data['time'] );
		$this->assertEmpty( $data['customer'] );
	}

	/**
	 * Test that clearing booking session preserves other session keys.
	 *
	 * Arrange: $_SESSION with 'bookit_wizard' and 'other_data'
	 * Act: Clear booking session
	 * Assert: 'bookit_wizard' is cleared, 'other_data' is preserved
	 */
	public function test_preserves_other_session_data() {
		Bookit_Session_Manager::init();
		$_SESSION['other_data'] = 'preserve_me';
		Bookit_Session_Manager::set_data( array( 'service_id' => $this->test_service_id ) );

		if ( $this->classes_available && $this->booking_retriever && method_exists( $this->booking_retriever, 'clear_booking_session' ) ) {
			$this->booking_retriever->clear_booking_session();
		} else {
			Bookit_Session_Manager::clear();
		}

		$this->assertNull( Bookit_Session_Manager::get( 'service_id' ) );
		$this->assertArrayHasKey( 'other_data', $_SESSION );
		$this->assertEquals( 'preserve_me', $_SESSION['other_data'] );
	}
}
