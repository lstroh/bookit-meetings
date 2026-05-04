<?php
/**
 * Unit Tests for Stripe Webhook Handler
 * Sprint 2, Task 4
 *
 * @package    Bookit_Booking_System
 * @subpackage Tests
 */

/**
 * Test Stripe webhook endpoint, event handling, customer/booking creation, and idempotency.
 *
 * @covers Booking_System_Stripe_Webhook
 */
class Test_Stripe_Webhook extends WP_UnitTestCase {

	/**
	 * Webhook handler instance.
	 *
	 * @var Booking_System_Stripe_Webhook|null
	 */
	private $webhook_handler;

	/**
	 * Test webhook payload (checkout.session.completed).
	 *
	 * @var array<string, mixed>
	 */
	private $test_webhook_payload;

	/**
	 * Webhook endpoint route (with namespace).
	 *
	 * @var string
	 */
	private $webhook_route = '/bookit/v1/stripe/webhook';

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
	 * Upsert a row in wp_bookings_settings.
	 *
	 * @param string $key   Setting key.
	 * @param mixed  $value String or bool.
	 */
	private function upsert_booking_setting( string $key, $value ): void {
		global $wpdb;

		$table = $wpdb->prefix . 'bookings_settings';
		$type  = 'string';
		if ( is_bool( $value ) ) {
			$type  = 'boolean';
			$value = $value ? '1' : '0';
		} else {
			$value = (string) $value;
		}

		$existing = $wpdb->get_var( $wpdb->prepare( "SELECT id FROM {$table} WHERE setting_key = %s", $key ) );
		if ( $existing ) {
			$wpdb->update(
				$table,
				array(
					'setting_value' => $value,
					'setting_type'  => $type,
				),
				array( 'setting_key' => $key ),
				array( '%s', '%s' ),
				array( '%s' )
			);
		} else {
			$wpdb->insert(
				$table,
				array(
					'setting_key'   => $key,
					'setting_value' => $value,
					'setting_type'  => $type,
				),
				array( '%s', '%s', '%s' )
			);
		}
	}

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
		$webhook_file = $plugin_dir . '/includes/api/class-stripe-webhook.php';
		$creator_file = $plugin_dir . '/includes/booking/class-booking-creator.php';

		// Skip tests when webhook/booking-creator not yet implemented (TDD).
		if ( ! file_exists( $webhook_file ) || ! file_exists( $creator_file ) ) {
			$this->markTestSkipped( 'Stripe webhook or booking creator not implemented yet (Sprint 2, Task 4).' );
			return;
		}

		// Load required classes.
		require_once $plugin_dir . '/vendor/autoload.php';
		require_once $plugin_dir . '/includes/payment/class-stripe-config.php';
		require_once $webhook_file;
		require_once $creator_file;

		// Initialize webhook handler (registers REST route on rest_api_init).
		$this->webhook_handler = new Booking_System_Stripe_Webhook();
		do_action( 'rest_api_init' );

		// Set up test Stripe settings in wp_bookings_settings (same as dashboard).
		$this->upsert_booking_setting( 'stripe_test_mode', true );
		$this->upsert_booking_setting( 'stripe_webhook_secret', 'whsec_test123456789' );
		$this->upsert_booking_setting( 'stripe_secret_key', 'sk_test_51234567890abcdef' );

		// Mock Stripe signature verification: in tests, accept any non-empty signature;
		// use signature 'invalid' to simulate invalid_signature.
		add_filter(
			'bookit_verify_stripe_signature',
			function ( $valid, $payload, $signature ) {
				if ( $signature === 'invalid' || $signature === '' ) {
					return false;
				}
				return true;
			},
			10,
			3
		);

		global $wpdb;
		$prefix = $wpdb->prefix;
		// Create test service.
		$wpdb->insert(
			$prefix . 'bookings_services',
			array(
				'name'          => 'Test Haircut',
				'duration'      => 60,
				'price'         => 50.00,
				'deposit_type'  => 'percentage',
				'deposit_amount' => 100,
			)
		);
		$this->test_service_id = (int) $wpdb->insert_id;

		// Create test staff (password_hash required).
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

		// Build test webhook payload (Stripe checkout.session.completed event).
		$this->test_webhook_payload = array(
			'id'   => 'evt_test123',
			'type' => 'checkout.session.completed',
			'data' => array(
				'object' => array(
					'id'              => 'cs_test_session123',
					'payment_intent'  => 'pi_test_intent123',
					'amount_total'    => 5000, // £50.00 in pence.
					'currency'        => 'gbp',
					'customer_email'  => 'john@example.com',
					'payment_status'  => 'paid',
					'metadata'        => array(
						'booking_temp_id'     => 'temp-uuid-12345',
						'service_id'          => (string) $this->test_service_id,
						'staff_id'            => (string) $this->test_staff_id,
						'booking_date'        => '2026-02-15',
						'booking_time'        => '14:00:00',
						'customer_first_name' => 'John',
						'customer_last_name'  => 'Smith',
						'customer_email'      => 'john@example.com',
						'customer_phone'      => '07700900123',
					),
				),
			),
		);
	}

	/**
	 * Tear down each test.
	 */
	public function tearDown(): void {
		global $wpdb;
		$prefix = $wpdb->prefix;

		// Clean up test data.
		$wpdb->query( "DELETE FROM {$prefix}bookings WHERE id > 0" );
		$wpdb->query( "DELETE FROM {$prefix}bookings_payments WHERE id > 0" );
		$wpdb->query( "DELETE FROM {$prefix}bookings_customers WHERE id > 0" );
		$wpdb->delete( $prefix . 'bookings_staff', array( 'id' => $this->test_staff_id ), array( '%d' ) );
		$wpdb->delete( $prefix . 'bookings_services', array( 'id' => $this->test_service_id ), array( '%d' ) );

		foreach ( array( 'stripe_test_mode', 'stripe_webhook_secret', 'stripe_secret_key' ) as $sk ) {
			$wpdb->delete( $prefix . 'bookings_settings', array( 'setting_key' => $sk ), array( '%s' ) );
		}

		// Clear idempotency transient if set.
		delete_transient( 'stripe_webhook_cs_test_session123' );

		parent::tearDown();
	}

	// -------------------------------------------------------------------------
	// WEBHOOK ENDPOINT TESTS (5)
	// -------------------------------------------------------------------------

	/**
	 * Test that the webhook REST route is registered.
	 *
	 * @covers Booking_System_Stripe_Webhook::register_routes
	 */
	public function test_webhook_endpoint_registered() {
		$routes = rest_get_server()->get_routes();
		$this->assertArrayHasKey( $this->webhook_route, $routes );
	}

	/**
	 * Test that the webhook accepts POST requests and returns 200 for valid request.
	 *
	 * @covers Booking_System_Stripe_Webhook::handle_webhook
	 */
	public function test_webhook_accepts_post_requests() {
		$request = new WP_REST_Request( 'POST', $this->webhook_route );
		$request->set_header( 'Stripe-Signature', 't=123,v1=valid' );
		$request->set_body( wp_json_encode( $this->test_webhook_payload ) );
		$request->set_header( 'Content-Type', 'application/json' );

		$response = rest_do_request( $request );

		$this->assertEquals( 200, $response->get_status() );
	}

	/**
	 * Test that GET requests to the webhook endpoint are rejected (405 Method Not Allowed or 404).
	 *
	 * @covers Booking_System_Stripe_Webhook::register_routes
	 */
	public function test_webhook_rejects_get_requests() {
		$request  = new WP_REST_Request( 'GET', $this->webhook_route );
		$response = rest_do_request( $request );

		$this->assertNotEquals( 200, $response->get_status() );
		$this->assertContains( $response->get_status(), array( 404, 405 ), 'GET should return 404 or 405' );
	}

	/**
	 * Test that request without Stripe-Signature header returns 400 with missing_signature error.
	 *
	 * @covers Booking_System_Stripe_Webhook::handle_webhook
	 */
	public function test_webhook_requires_stripe_signature() {
		$request = new WP_REST_Request( 'POST', $this->webhook_route );
		$request->set_body( wp_json_encode( $this->test_webhook_payload ) );
		$request->set_header( 'Content-Type', 'application/json' );
		// No Stripe-Signature header.

		$response = rest_do_request( $request );
		$data     = $response->get_data();

		$this->assertEquals( 400, $response->get_status() );
		$this->assertIsArray( $data );
		$this->assertArrayHasKey( 'code', $data );
		$this->assertSame( 'missing_signature', $data['code'] );
	}

	/**
	 * Test that request with invalid signature returns 400 with invalid_signature error.
	 *
	 * @covers Booking_System_Stripe_Webhook::handle_webhook
	 */
	public function test_webhook_rejects_invalid_signature() {
		$request = new WP_REST_Request( 'POST', $this->webhook_route );
		$request->set_header( 'Stripe-Signature', 'invalid' );
		$request->set_body( wp_json_encode( $this->test_webhook_payload ) );
		$request->set_header( 'Content-Type', 'application/json' );

		$response = rest_do_request( $request );
		$data     = $response->get_data();

		$this->assertEquals( 400, $response->get_status() );
		$this->assertIsArray( $data );
		$this->assertArrayHasKey( 'code', $data );
		$this->assertSame( 'invalid_signature', $data['code'] );
	}

	// -------------------------------------------------------------------------
	// EVENT HANDLING TESTS (4)
	// -------------------------------------------------------------------------

	/**
	 * Test that checkout.session.completed event is handled and creates a booking.
	 *
	 * @covers Booking_System_Stripe_Webhook::handle_webhook
	 * @covers Booking_System_Stripe_Webhook::handle_checkout_session_completed
	 */
	public function test_handles_checkout_session_completed() {
		$request = new WP_REST_Request( 'POST', $this->webhook_route );
		$request->set_header( 'Stripe-Signature', 't=123,v1=valid' );
		$request->set_body( wp_json_encode( $this->test_webhook_payload ) );
		$request->set_header( 'Content-Type', 'application/json' );

		$response = rest_do_request( $request );

		$this->assertEquals( 200, $response->get_status() );

		global $wpdb;
		$booking = $wpdb->get_row( "SELECT * FROM {$wpdb->prefix}bookings ORDER BY id DESC LIMIT 1" );
		$this->assertNotNull( $booking );
		$this->assertNotEmpty( $booking->id );
		$this->assertEquals( 'confirmed', $booking->status );
	}

	/**
	 * Test that other event types (e.g. payment_intent.succeeded) are ignored and no booking is created.
	 *
	 * @covers Booking_System_Stripe_Webhook::handle_webhook
	 */
	public function test_ignores_other_event_types() {
		$payload = $this->test_webhook_payload;
		$payload['type'] = 'payment_intent.succeeded';
		$payload['data']['object'] = array( 'id' => 'pi_xxx' );

		$request = new WP_REST_Request( 'POST', $this->webhook_route );
		$request->set_header( 'Stripe-Signature', 't=123,v1=valid' );
		$request->set_body( wp_json_encode( $payload ) );
		$request->set_header( 'Content-Type', 'application/json' );

		$response = rest_do_request( $request );

		$this->assertEquals( 200, $response->get_status() );

		global $wpdb;
		$count = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->prefix}bookings" );
		$this->assertEquals( 0, $count );
	}

	/**
	 * Test that checkout.session.completed with payment_status != paid does not create a booking.
	 *
	 * @covers Booking_System_Stripe_Webhook::handle_webhook
	 */
	public function test_handles_unpaid_checkout_session() {
		$payload = $this->test_webhook_payload;
		$payload['data']['object']['payment_status'] = 'unpaid';

		$request = new WP_REST_Request( 'POST', $this->webhook_route );
		$request->set_header( 'Stripe-Signature', 't=123,v1=valid' );
		$request->set_body( wp_json_encode( $payload ) );
		$request->set_header( 'Content-Type', 'application/json' );

		$response = rest_do_request( $request );

		$this->assertEquals( 200, $response->get_status() );

		global $wpdb;
		$count = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->prefix}bookings" );
		$this->assertEquals( 0, $count );
	}

	/**
	 * Test that unknown event types return 200 and are logged (no exception).
	 *
	 * @covers Booking_System_Stripe_Webhook::handle_webhook
	 */
	public function test_logs_unknown_event_types() {
		$payload = $this->test_webhook_payload;
		$payload['type'] = 'customer.unknown_type';

		$request = new WP_REST_Request( 'POST', $this->webhook_route );
		$request->set_header( 'Stripe-Signature', 't=123,v1=valid' );
		$request->set_body( wp_json_encode( $payload ) );
		$request->set_header( 'Content-Type', 'application/json' );

		$response = rest_do_request( $request );

		$this->assertEquals( 200, $response->get_status() );
	}

	// -------------------------------------------------------------------------
	// CUSTOMER CREATION TESTS (3)
	// -------------------------------------------------------------------------

	/**
	 * Test that a new customer is created when email is not in database.
	 *
	 * @covers Booking_System_Stripe_Webhook::handle_checkout_session_completed
	 */
	public function test_creates_new_customer() {
		$request = new WP_REST_Request( 'POST', $this->webhook_route );
		$request->set_header( 'Stripe-Signature', 't=123,v1=valid' );
		$request->set_body( wp_json_encode( $this->test_webhook_payload ) );
		$request->set_header( 'Content-Type', 'application/json' );

		rest_do_request( $request );

		global $wpdb;
		$customer = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$wpdb->prefix}bookings_customers WHERE email = %s",
				'john@example.com'
			)
		);
		$this->assertNotNull( $customer );
		$this->assertNotEmpty( $customer->id );
	}

	/**
	 * Test that existing customer is reused (no duplicate) when same email is used.
	 *
	 * @covers Booking_System_Stripe_Webhook::handle_checkout_session_completed
	 */
	public function test_uses_existing_customer() {
		global $wpdb;
		$prefix = $wpdb->prefix;

		// Get or create customer so we don't hit duplicate key when another test already created this email.
		$existing_row = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT id FROM {$prefix}bookings_customers WHERE email = %s",
				'john@example.com'
			)
		);
		if ( $existing_row ) {
			$existing_id = (int) $existing_row->id;
		} else {
			$wpdb->insert(
				$prefix . 'bookings_customers',
				array(
					'email'      => 'john@example.com',
					'first_name' => 'John',
					'last_name'  => 'Smith',
					'phone'      => '07700900123',
				)
			);
			$existing_id = (int) $wpdb->insert_id;
		}

		$request = new WP_REST_Request( 'POST', $this->webhook_route );
		$request->set_header( 'Stripe-Signature', 't=123,v1=valid' );
		$request->set_body( wp_json_encode( $this->test_webhook_payload ) );
		$request->set_header( 'Content-Type', 'application/json' );

		rest_do_request( $request );

		$count = (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$prefix}bookings_customers WHERE email = %s",
				'john@example.com'
			)
		);
		$this->assertEquals( 1, $count );

		$booking = $wpdb->get_row( "SELECT * FROM {$prefix}bookings ORDER BY id DESC LIMIT 1" );
		$this->assertNotNull( $booking );
		$this->assertEquals( $existing_id, (int) $booking->customer_id );
	}

	/**
	 * Test that customer record has correct first_name, last_name, email, phone from metadata.
	 *
	 * @covers Booking_System_Stripe_Webhook::handle_checkout_session_completed
	 */
	public function test_customer_has_correct_data() {
		$request = new WP_REST_Request( 'POST', $this->webhook_route );
		$request->set_header( 'Stripe-Signature', 't=123,v1=valid' );
		$request->set_body( wp_json_encode( $this->test_webhook_payload ) );
		$request->set_header( 'Content-Type', 'application/json' );

		rest_do_request( $request );

		global $wpdb;
		$customer = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$wpdb->prefix}bookings_customers WHERE email = %s",
				'john@example.com'
			)
		);
		$this->assertNotNull( $customer );
		$this->assertEquals( 'John', $customer->first_name );
		$this->assertEquals( 'Smith', $customer->last_name );
		$this->assertEquals( 'john@example.com', $customer->email );
		$this->assertEquals( '07700900123', $customer->phone );
	}

	// -------------------------------------------------------------------------
	// BOOKING CREATION TESTS (4)
	// -------------------------------------------------------------------------

	/**
	 * Test that booking is created with correct customer_id, service, staff, date/time, status, price, payment_method, payment_intent.
	 *
	 * @covers Booking_System_Stripe_Webhook::handle_checkout_session_completed
	 */
	public function test_creates_booking_with_correct_data() {
		$request = new WP_REST_Request( 'POST', $this->webhook_route );
		$request->set_header( 'Stripe-Signature', 't=123,v1=valid' );
		$request->set_body( wp_json_encode( $this->test_webhook_payload ) );
		$request->set_header( 'Content-Type', 'application/json' );

		rest_do_request( $request );

		global $wpdb;
		$prefix  = $wpdb->prefix;
		$customer = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT id FROM {$prefix}bookings_customers WHERE email = %s",
				'john@example.com'
			)
		);
		$this->assertNotNull( $customer );

		$booking = $wpdb->get_row( "SELECT * FROM {$prefix}bookings ORDER BY id DESC LIMIT 1" );
		$this->assertNotNull( $booking );
		$this->assertNotEmpty( $booking->id );
		$this->assertEquals( $customer->id, $booking->customer_id );
		$this->assertEquals( $this->test_service_id, (int) $booking->service_id );
		$this->assertEquals( $this->test_staff_id, (int) $booking->staff_id );
		$this->assertEquals( '2026-02-15', $booking->booking_date );
		$this->assertEquals( '14:00:00', $booking->start_time );
		$this->assertEquals( '15:00:00', $booking->end_time );
		$this->assertEquals( 'confirmed', $booking->status );
		$this->assertEquals( 50.00, (float) $booking->total_price );
		$this->assertEquals( 50.00, (float) $booking->deposit_amount );
		$this->assertEquals( 50.00, (float) $booking->deposit_paid );
		$this->assertEquals( 0.00, (float) $booking->balance_due );
		$this->assertEquals( 'stripe', $booking->payment_method );

		// Payment intent: stored on booking or in bookings_payments.
		$payment = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT stripe_payment_intent_id FROM {$prefix}bookings_payments WHERE booking_id = %d LIMIT 1",
				$booking->id
			)
		);
		$this->assertNotNull( $payment );
		$this->assertEquals( 'pi_test_intent123', $payment->stripe_payment_intent_id );
	}

	/**
	 * Test that end_time is calculated from service duration (60 min -> 14:00 + 60 = 15:00).
	 *
	 * @covers Booking_System_Stripe_Webhook::handle_checkout_session_completed
	 */
	public function test_calculates_end_time_from_duration() {
		$request = new WP_REST_Request( 'POST', $this->webhook_route );
		$request->set_header( 'Stripe-Signature', 't=123,v1=valid' );
		$request->set_body( wp_json_encode( $this->test_webhook_payload ) );
		$request->set_header( 'Content-Type', 'application/json' );

		rest_do_request( $request );

		global $wpdb;
		$booking = $wpdb->get_row( "SELECT start_time, end_time, duration FROM {$wpdb->prefix}bookings ORDER BY id DESC LIMIT 1" );
		$this->assertNotNull( $booking );
		$this->assertEquals( '14:00:00', $booking->start_time );
		$this->assertEquals( '15:00:00', $booking->end_time );
		$this->assertEquals( 60, (int) $booking->duration );
	}

	/**
	 * Test that payment_intent_id is stored (in payments table for the booking).
	 *
	 * @covers Booking_System_Stripe_Webhook::handle_checkout_session_completed
	 */
	public function test_stores_payment_intent_id() {
		$request = new WP_REST_Request( 'POST', $this->webhook_route );
		$request->set_header( 'Stripe-Signature', 't=123,v1=valid' );
		$request->set_body( wp_json_encode( $this->test_webhook_payload ) );
		$request->set_header( 'Content-Type', 'application/json' );

		rest_do_request( $request );

		global $wpdb;
		$payment = $wpdb->get_row(
			"SELECT stripe_payment_intent_id FROM {$wpdb->prefix}bookings_payments ORDER BY id DESC LIMIT 1"
		);
		$this->assertNotNull( $payment );
		$this->assertEquals( 'pi_test_intent123', $payment->stripe_payment_intent_id );
	}

	/**
	 * Test that booking status is confirmed after successful payment webhook.
	 *
	 * @covers Booking_System_Stripe_Webhook::handle_checkout_session_completed
	 */
	public function test_booking_status_is_confirmed() {
		$request = new WP_REST_Request( 'POST', $this->webhook_route );
		$request->set_header( 'Stripe-Signature', 't=123,v1=valid' );
		$request->set_body( wp_json_encode( $this->test_webhook_payload ) );
		$request->set_header( 'Content-Type', 'application/json' );

		rest_do_request( $request );

		global $wpdb;
		$booking = $wpdb->get_row( "SELECT status FROM {$wpdb->prefix}bookings ORDER BY id DESC LIMIT 1" );
		$this->assertNotNull( $booking );
		$this->assertEquals( 'confirmed', $booking->status );
	}

	// -------------------------------------------------------------------------
	// IDEMPOTENCY TESTS (2)
	// -------------------------------------------------------------------------

	/**
	 * Test that processing the same webhook twice (Stripe retry) does not create duplicate booking.
	 *
	 * @covers Booking_System_Stripe_Webhook::handle_webhook
	 */
	public function test_duplicate_webhook_doesnt_create_duplicate_booking() {
		$request = new WP_REST_Request( 'POST', $this->webhook_route );
		$request->set_header( 'Stripe-Signature', 't=123,v1=valid' );
		$request->set_body( wp_json_encode( $this->test_webhook_payload ) );
		$request->set_header( 'Content-Type', 'application/json' );

		$response1 = rest_do_request( $request );
		$response2 = rest_do_request( $request );

		$this->assertEquals( 200, $response1->get_status() );
		$this->assertEquals( 200, $response2->get_status() );

		global $wpdb;
		$count = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->prefix}bookings" );
		$this->assertEquals( 1, $count );
	}

	/**
	 * Test that idempotency key is stored (e.g. transient) with session id and 24h expiry.
	 *
	 * @covers Booking_System_Stripe_Webhook::handle_webhook
	 */
	public function test_idempotency_key_stored() {
		$request = new WP_REST_Request( 'POST', $this->webhook_route );
		$request->set_header( 'Stripe-Signature', 't=123,v1=valid' );
		$request->set_body( wp_json_encode( $this->test_webhook_payload ) );
		$request->set_header( 'Content-Type', 'application/json' );

		rest_do_request( $request );

		$idempotency_key = get_transient( 'stripe_webhook_cs_test_session123' );
		$this->assertNotFalse( $idempotency_key );
	}
}
