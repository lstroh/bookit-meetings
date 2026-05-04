<?php
/**
 * PHPUnit tests for Stripe Checkout Session creation.
 *
 * Sprint 2, Task 3 - Unit Testing FIRST (Test-Driven Development).
 * Comprehensive tests for Stripe Checkout Session before implementing the feature.
 *
 * @package    Bookit_Booking_System
 * @subpackage Tests
 */

/**
 * Test Stripe Checkout Session class.
 *
 * @covers Booking_System_Stripe_Checkout
 */
class Test_Stripe_Checkout extends WP_UnitTestCase {

	/**
	 * Instance of the class under test.
	 *
	 * @var Booking_System_Stripe_Checkout
	 */
	private $stripe_checkout;

	/**
	 * Test session data (Sprint 1 structure).
	 *
	 * @var array<string, mixed>
	 */
	private $test_session_data;

	/**
	 * Last mock session object created by the mock filter (for assertions).
	 *
	 * @var object|null
	 */
	private $last_mock_session;

	/**
	 * Filter priority for mock (so we can remove in tearDown).
	 *
	 * @var int
	 */
	private $mock_filter_priority = 999;

	/**
	 * Stored filter callbacks for removal in tearDown.
	 *
	 * @var callable[]
	 */
	private $mock_filter_callbacks = array();

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

		// Load Stripe SDK (bootstrap may already load vendor; ensure class path is available).
		$autoload = dirname( __DIR__ ) . '/vendor/autoload.php';
		if ( file_exists( $autoload ) ) {
			require_once $autoload;
		}

		// Load class under test (skip if not yet implemented).
		$checkout_class = dirname( __DIR__ ) . '/includes/payment/class-stripe-checkout.php';
		if ( ! file_exists( $checkout_class ) ) {
			$this->markTestSkipped( 'class-stripe-checkout.php not found. Implement the Stripe Checkout class to run these tests.' );
			return;
		}
		require_once $checkout_class;

		if ( ! class_exists( 'Booking_System_Stripe_Checkout' ) ) {
			$this->markTestSkipped( 'Booking_System_Stripe_Checkout class not found.' );
			return;
		}

		$this->stripe_checkout = new Booking_System_Stripe_Checkout();
		$this->create_test_service_and_staff();

		// Create test session data (CORRECT Sprint 1 structure).
		$this->test_session_data = array(
			'current_step'               => 4,
			'service_id'                 => $this->test_service_id,
			'staff_id'                   => $this->test_staff_id,
			'date'                       => '2026-02-15',
			'time'                       => '14:00:00',
			'customer_first_name'        => 'John',
			'customer_last_name'         => 'Smith',
			'customer_email'             => 'john@example.com',
			'customer_phone'             => '07700900123',
			'customer_special_requests'  => 'Test request',
			'marketing_consent'          => 1,
			'consent_date'               => '2026-02-01 12:00:00',
			'created_at'                 => time(),
			'last_activity'              => time(),
		);

		$this->set_stripe_test_options();
		$this->add_mock_filters();

		// Suppress expected edge-case logs (invalid percentage, negative fixed) so test output is clean.
		add_filter( 'bookit_log_deposit_edge_cases', '__return_false' );
	}

	/**
	 * Create test service and staff in database.
	 */
	private function create_test_service_and_staff(): void {
		global $wpdb;
		$services_table = $wpdb->prefix . 'bookings_services';
		$staff_table    = $wpdb->prefix . 'bookings_staff';

		// Ensure tables exist (plugin activation creates them).
		$service_table_exists = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $services_table ) ) === $services_table;
		$staff_table_exists   = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $staff_table ) ) === $staff_table;
		if ( ! $service_table_exists || ! $staff_table_exists ) {
			return;
		}

		// Insert test service: £50, 60 min, 100% deposit (full payment).
		$wpdb->insert(
			$services_table,
			array(
				'name'            => 'Test Haircut',
				'description'     => null,
				'duration'        => 60,
				'price'           => 50.00,
				'deposit_type'    => 'percentage',
				'deposit_amount'  => 100,
				'buffer_before'   => 0,
				'buffer_after'    => 0,
				'is_active'       => 1,
				'display_order'   => 0,
				'created_at'      => current_time( 'mysql' ),
				'updated_at'      => current_time( 'mysql' ),
				'deleted_at'      => null,
			),
			array( '%s', '%s', '%d', '%f', '%s', '%f', '%d', '%d', '%d', '%d', '%s', '%s', '%s' )
		);
		$this->test_service_id = (int) $wpdb->insert_id;

		// Insert test staff (password_hash required).
		$wpdb->insert(
			$staff_table,
			array(
				'first_name'        => 'Emma',
				'last_name'         => 'Thompson',
				'email'             => 'emma@salon.com',
				'password_hash'    => password_hash( 'test', PASSWORD_BCRYPT ),
				'phone'            => null,
				'photo_url'         => null,
				'bio'               => null,
				'title'             => null,
				'role'              => 'staff',
				'google_calendar_id' => null,
				'is_active'         => 1,
				'display_order'     => 0,
				'created_at'        => current_time( 'mysql' ),
				'updated_at'        => current_time( 'mysql' ),
				'deleted_at'        => null,
			),
			array( '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%d', '%d', '%s', '%s', '%s' )
		);
		$this->test_staff_id = (int) $wpdb->insert_id;
	}

	/**
	 * Set Stripe test keys in wp_bookings_settings (same storage as dashboard).
	 */
	private function set_stripe_test_options(): void {
		$this->upsert_booking_setting( 'stripe_test_mode', true );
		$this->upsert_booking_setting( 'stripe_secret_key', 'sk_test_51234567890abcdef' );
		$this->upsert_booking_setting( 'stripe_publishable_key', 'pk_test_51234567890abcdef' );
	}

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
	 * Add filters to mock Stripe API (prevents actual API calls).
	 */
	private function add_mock_filters(): void {
		$self = $this;
		$this->mock_filter_callbacks['mode'] = function () {
			return 'mock';
		};
		add_filter( 'bookit_stripe_api_mode', $this->mock_filter_callbacks['mode'], $this->mock_filter_priority );
		$this->mock_filter_callbacks['session'] = function ( $session_data ) use ( $self ) {
				global $wpdb;
				$service = $wpdb->get_row(
					$wpdb->prepare(
						"SELECT * FROM {$wpdb->prefix}bookings_services WHERE id = %d",
						isset( $session_data['service_id'] ) ? (int) $session_data['service_id'] : 0
					)
				);
				$deposit_pence = 0;
				if ( $service && isset( $service->price, $service->deposit_amount, $service->deposit_type ) ) {
					if ( $service->deposit_type === 'percentage' ) {
						$deposit_pence = (int) round( ( (float) $service->price * (float) $service->deposit_amount / 100 ) * 100 );
					} else {
						$deposit_pence = (int) round( (float) $service->deposit_amount * 100 );
					}
				}
				$staff = null;
				if ( ! empty( $session_data['staff_id'] ) ) {
					$staff = $wpdb->get_row(
						$wpdb->prepare(
							"SELECT first_name, last_name FROM {$wpdb->prefix}bookings_staff WHERE id = %d",
							(int) $session_data['staff_id']
						)
					);
				}
				$staff_name = $staff ? trim( $staff->first_name . ' ' . $staff->last_name ) : '';
				$date_display = ! empty( $session_data['date'] ) ? date( 'd/m/Y', strtotime( $session_data['date'] ) ) : '';
				$time_display = ! empty( $session_data['time'] ) ? $session_data['time'] : '';
				$description = implode( ' | ', array_filter( array( 'Test Haircut', $staff_name, $date_display, $time_display ) ) );
				$session = (object) array(
					'id'                   => 'cs_test_mock123456',
					'amount_total'         => $deposit_pence,
					'currency'             => 'gbp',
					'customer_email'       => isset( $session_data['customer_email'] ) ? $session_data['customer_email'] : '',
					'metadata'             => array(
						'booking_temp_id'       => function_exists( 'wp_generate_uuid4' ) ? wp_generate_uuid4() : 'mock-uuid-' . uniqid(),
						'service_id'            => isset( $session_data['service_id'] ) ? (string) $session_data['service_id'] : '',
						'staff_id'              => isset( $session_data['staff_id'] ) ? (string) $session_data['staff_id'] : '',
						'booking_date'          => isset( $session_data['date'] ) ? $session_data['date'] : '',
						'booking_time'          => isset( $session_data['time'] ) ? $session_data['time'] : '',
						'customer_first_name'   => isset( $session_data['customer_first_name'] ) ? $session_data['customer_first_name'] : '',
						'customer_last_name'    => isset( $session_data['customer_last_name'] ) ? $session_data['customer_last_name'] : '',
						'customer_email'        => isset( $session_data['customer_email'] ) ? $session_data['customer_email'] : '',
						'customer_phone'        => isset( $session_data['customer_phone'] ) ? $session_data['customer_phone'] : '',
					),
					'success_url'           => home_url( '/booking-confirmed?session_id={CHECKOUT_SESSION_ID}' ),
					'cancel_url'            => home_url( '/book?step=5&cancelled=1' ),
					'payment_method_types'  => array( 'card' ),
					'mode'                  => 'payment',
					'line_items'            => (object) array(
						'data' => array(
							(object) array(
								'amount_total' => $deposit_pence,
								'currency'      => 'gbp',
								'description'   => $description,
								'name'          => 'Test Haircut',
							),
						),
					),
				);
				$self->last_mock_session = $session;
				return $session;
			};
		add_filter( 'bookit_mock_stripe_session', $this->mock_filter_callbacks['session'], $this->mock_filter_priority );
	}

	/**
	 * Tear down each test.
	 */
	public function tearDown(): void {
		global $wpdb;
		$p = $wpdb->prefix;
		$wpdb->delete( $p . 'bookings_staff', array( 'id' => $this->test_staff_id ), array( '%d' ) );
		$wpdb->delete( $p . 'bookings_services', array( 'id' => $this->test_service_id ), array( '%d' ) );
		$wpdb->query( "DELETE FROM {$p}bookings_services WHERE id IN (94, 95, 96, 97, 98, 99)" );

		if ( isset( $this->mock_filter_callbacks['mode'] ) ) {
			remove_filter( 'bookit_stripe_api_mode', $this->mock_filter_callbacks['mode'], $this->mock_filter_priority );
		}
		if ( isset( $this->mock_filter_callbacks['session'] ) ) {
			remove_filter( 'bookit_mock_stripe_session', $this->mock_filter_callbacks['session'], $this->mock_filter_priority );
		}

		remove_filter( 'bookit_log_deposit_edge_cases', '__return_false' );

		foreach ( array( 'stripe_test_mode', 'stripe_secret_key', 'stripe_publishable_key' ) as $stripe_key ) {
			$wpdb->delete( $p . 'bookings_settings', array( 'setting_key' => $stripe_key ), array( '%s' ) );
		}

		$this->last_mock_session = null;
		parent::tearDown();
	}

	// -------------------------------------------------------------------------
	// 1. test_creates_checkout_session_successfully
	// -------------------------------------------------------------------------

	/**
	 * Test that checkout session is created successfully and returns Stripe session ID.
	 *
	 * Arrange: Valid session data, Stripe keys configured, mock mode.
	 * Act: Call create_checkout_session($this->test_session_data).
	 * Assert: Returns Stripe session ID (starts with 'cs_test_').
	 */
	public function test_creates_checkout_session_successfully(): void {
		$result = $this->stripe_checkout->create_checkout_session( $this->test_session_data );

		$this->assertNotEmpty( $result );
		$this->assertIsString( $result );
		$this->assertStringStartsWith( 'cs_test_', $result );
	}

	// -------------------------------------------------------------------------
	// 2. test_checkout_session_has_correct_amount
	// -------------------------------------------------------------------------

	/**
	 * Test checkout session amount: service £50, deposit 100% = £50.00 = 5000 pence.
	 *
	 * Arrange: Service price £50, deposit 100%.
	 * Act: Create checkout session.
	 * Assert: Line item amount = 5000 (£50.00 in pence).
	 */
	public function test_checkout_session_has_correct_amount(): void {
		$this->stripe_checkout->create_checkout_session( $this->test_session_data );

		$this->assertNotNull( $this->last_mock_session );
		$this->assertEquals( 5000, $this->last_mock_session->amount_total );
	}

	// -------------------------------------------------------------------------
	// 3. test_checkout_session_currency_is_gbp
	// -------------------------------------------------------------------------

	/**
	 * Test checkout session currency is GBP.
	 */
	public function test_checkout_session_currency_is_gbp(): void {
		$this->stripe_checkout->create_checkout_session( $this->test_session_data );

		$this->assertNotNull( $this->last_mock_session );
		$this->assertEquals( 'gbp', $this->last_mock_session->currency );
	}

	// -------------------------------------------------------------------------
	// 4. test_checkout_session_includes_service_name
	// -------------------------------------------------------------------------

	/**
	 * Test checkout session line item includes service name.
	 */
	public function test_checkout_session_includes_service_name(): void {
		$this->stripe_checkout->create_checkout_session( $this->test_session_data );

		$this->assertNotNull( $this->last_mock_session );
		$this->assertObjectHasProperty( 'line_items', $this->last_mock_session );
		$this->assertNotEmpty( $this->last_mock_session->line_items->data );
		$first_line = $this->last_mock_session->line_items->data[0];
		$this->assertStringContainsString( 'Test Haircut', $first_line->name );
	}

	// -------------------------------------------------------------------------
	// 5. test_checkout_session_includes_staff_name
	// -------------------------------------------------------------------------

	/**
	 * Test checkout session line item description includes staff name.
	 */
	public function test_checkout_session_includes_staff_name(): void {
		$this->stripe_checkout->create_checkout_session( $this->test_session_data );

		$this->assertNotNull( $this->last_mock_session );
		$this->assertNotEmpty( $this->last_mock_session->line_items->data );
		$first_line = $this->last_mock_session->line_items->data[0];
		$this->assertStringContainsString( 'Emma Thompson', $first_line->description );
	}

	// -------------------------------------------------------------------------
	// 6. test_checkout_session_includes_date_time
	// -------------------------------------------------------------------------

	/**
	 * Test checkout session includes date and time (from session 'date' / 'time').
	 *
	 * NOTE: Uses 'date' field (not 'booking_date').
	 */
	public function test_checkout_session_includes_date_time(): void {
		$this->stripe_checkout->create_checkout_session( $this->test_session_data );

		$this->assertNotNull( $this->last_mock_session );
		$this->assertNotEmpty( $this->last_mock_session->line_items->data );
		$first_line = $this->last_mock_session->line_items->data[0];
		$this->assertStringContainsString( '15/02/2026', $first_line->description );
		$this->assertStringContainsString( '14:00:00', $first_line->description );
	}

	// -------------------------------------------------------------------------
	// 7. test_checkout_session_has_success_url
	// -------------------------------------------------------------------------

	/**
	 * Test checkout session has correct success URL.
	 */
	public function test_checkout_session_has_success_url(): void {
		$this->stripe_checkout->create_checkout_session( $this->test_session_data );

		$this->assertNotNull( $this->last_mock_session );
		$this->assertStringContainsString( '/booking-confirmed?session_id={CHECKOUT_SESSION_ID}', $this->last_mock_session->success_url );
	}

	// -------------------------------------------------------------------------
	// 8. test_checkout_session_has_cancel_url
	// -------------------------------------------------------------------------

	/**
	 * Test checkout session has correct cancel URL.
	 */
	public function test_checkout_session_has_cancel_url(): void {
		$this->stripe_checkout->create_checkout_session( $this->test_session_data );

		$this->assertNotNull( $this->last_mock_session );
		$this->assertStringContainsString( '/book?step=5&cancelled=1', $this->last_mock_session->cancel_url );
	}

	// -------------------------------------------------------------------------
	// 9. test_checkout_session_includes_customer_email
	// -------------------------------------------------------------------------

	/**
	 * Test checkout session includes customer email.
	 */
	public function test_checkout_session_includes_customer_email(): void {
		$this->stripe_checkout->create_checkout_session( $this->test_session_data );

		$this->assertNotNull( $this->last_mock_session );
		$this->assertEquals( 'john@example.com', $this->last_mock_session->customer_email );
	}

	// -------------------------------------------------------------------------
	// 10. test_metadata_includes_all_booking_data
	// -------------------------------------------------------------------------

	/**
	 * Test metadata includes all booking data and session→metadata mapping.
	 *
	 * Session 'date' → metadata 'booking_date', session 'time' → metadata 'booking_time'.
	 * booking_temp_id should be UUID format.
	 */
	public function test_metadata_includes_all_booking_data(): void {
		$this->stripe_checkout->create_checkout_session( $this->test_session_data );

		$this->assertNotNull( $this->last_mock_session );
		$meta = $this->last_mock_session->metadata;

		$this->assertArrayHasKey( 'service_id', $meta );
		$this->assertEquals( (string) $this->test_service_id, $meta['service_id'] );
		$this->assertArrayHasKey( 'staff_id', $meta );
		$this->assertEquals( (string) $this->test_staff_id, $meta['staff_id'] );
		$this->assertArrayHasKey( 'booking_date', $meta );
		$this->assertEquals( '2026-02-15', $meta['booking_date'] );
		$this->assertArrayHasKey( 'booking_time', $meta );
		$this->assertEquals( '14:00:00', $meta['booking_time'] );
		$this->assertArrayHasKey( 'customer_first_name', $meta );
		$this->assertEquals( 'John', $meta['customer_first_name'] );
		$this->assertArrayHasKey( 'customer_last_name', $meta );
		$this->assertEquals( 'Smith', $meta['customer_last_name'] );
		$this->assertArrayHasKey( 'customer_email', $meta );
		$this->assertEquals( 'john@example.com', $meta['customer_email'] );
		$this->assertArrayHasKey( 'customer_phone', $meta );
		$this->assertEquals( '07700900123', $meta['customer_phone'] );
		$this->assertArrayHasKey( 'booking_temp_id', $meta );
		$this->assertNotEmpty( $meta['booking_temp_id'] );
		// UUID format: 8-4-4-4-12 hex chars, or mock-uuid- prefix in fallback.
		$this->assertMatchesRegularExpression( '/^([0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}|mock-uuid-[a-f0-9]+)$/i', $meta['booking_temp_id'] );
	}

	// -------------------------------------------------------------------------
	// 11. test_payment_method_types_includes_card
	// -------------------------------------------------------------------------

	/**
	 * Test payment_method_types includes 'card'.
	 */
	public function test_payment_method_types_includes_card(): void {
		$this->stripe_checkout->create_checkout_session( $this->test_session_data );

		$this->assertNotNull( $this->last_mock_session );
		$this->assertContains( 'card', $this->last_mock_session->payment_method_types );
	}

	// -------------------------------------------------------------------------
	// 12. test_mode_is_payment
	// -------------------------------------------------------------------------

	/**
	 * Test mode is 'payment' (not 'subscription').
	 */
	public function test_mode_is_payment(): void {
		$this->stripe_checkout->create_checkout_session( $this->test_session_data );

		$this->assertNotNull( $this->last_mock_session );
		$this->assertEquals( 'payment', $this->last_mock_session->mode );
	}

	// -------------------------------------------------------------------------
	// 13. test_rejects_missing_service_id
	// -------------------------------------------------------------------------

	/**
	 * Test that missing service_id is rejected (WP_Error or exception).
	 *
	 * Arrange: Session data without service_id.
	 * Act: Create checkout session.
	 * Assert: Returns WP_Error or throws exception.
	 */
	public function test_rejects_missing_service_id(): void {
		$session_data = $this->test_session_data;
		unset( $session_data['service_id'] );

		$result = $this->stripe_checkout->create_checkout_session( $session_data );

		$this->assertInstanceOf( 'WP_Error', $result );
		$this->assertEquals( 'missing_service', $result->get_error_code() );
		$this->assertStringContainsString( 'Service', $result->get_error_message() );
	}

	// -------------------------------------------------------------------------
	// 14. test_rejects_invalid_email
	// -------------------------------------------------------------------------

	/**
	 * Test that invalid email is rejected.
	 *
	 * Arrange: Session data with invalid email.
	 * Assert: Returns WP_Error with 'invalid_email' code.
	 */
	public function test_rejects_invalid_email(): void {
		$session_data = $this->test_session_data;
		$session_data['customer_email'] = 'not-an-email';

		$result = $this->stripe_checkout->create_checkout_session( $session_data );

		$this->assertInstanceOf( 'WP_Error', $result );
		$this->assertEquals( 'invalid_email', $result->get_error_code() );
	}

	// -------------------------------------------------------------------------
	// 15. test_handles_missing_stripe_keys
	// -------------------------------------------------------------------------

	/**
	 * Test that missing Stripe API keys return WP_Error.
	 *
	 * Arrange: Delete Stripe API keys.
	 * Act: Create checkout session.
	 * Assert: Returns WP_Error with 'missing_api_key' code.
	 */
	public function test_handles_missing_stripe_keys(): void {
		$this->upsert_booking_setting( 'stripe_secret_key', '' );

		$result = $this->stripe_checkout->create_checkout_session( $this->test_session_data );

		$this->assertInstanceOf( 'WP_Error', $result );
		$this->assertEquals( 'missing_api_key', $result->get_error_code() );
	}

	// -------------------------------------------------------------------------
	// 16. test_rejects_zero_price_service (calculate_deposit edge case)
	// -------------------------------------------------------------------------

	/**
	 * Test that zero price service is rejected with WP_Error invalid_price.
	 */
	public function test_rejects_zero_price_service(): void {
		global $wpdb;

		$wpdb->insert(
			$wpdb->prefix . 'bookings_services',
			array(
				'id'              => 99,
				'name'            => 'Zero Price Service',
				'description'     => null,
				'duration'        => 30,
				'price'           => 0.00,
				'deposit_type'    => 'percentage',
				'deposit_amount'  => 100,
				'buffer_before'   => 0,
				'buffer_after'    => 0,
				'is_active'       => 1,
				'display_order'   => 0,
				'created_at'      => current_time( 'mysql' ),
				'updated_at'      => current_time( 'mysql' ),
				'deleted_at'      => null,
			),
			array( '%d', '%s', '%s', '%d', '%f', '%s', '%f', '%d', '%d', '%d', '%d', '%s', '%s', '%s' )
		);

		$session_data = $this->test_session_data;
		$session_data['service_id'] = 99;

		$result = $this->stripe_checkout->create_checkout_session( $session_data );

		$this->assertInstanceOf( 'WP_Error', $result );
		$this->assertEquals( 'invalid_price', $result->get_error_code() );
	}

	// -------------------------------------------------------------------------
	// 17. test_handles_null_deposit_configuration
	// -------------------------------------------------------------------------

	/**
	 * Test that null deposit configuration defaults to full payment.
	 */
	public function test_handles_null_deposit_configuration(): void {
		global $wpdb;

		$wpdb->insert(
			$wpdb->prefix . 'bookings_services',
			array(
				'id'              => 98,
				'name'            => 'No Deposit Config',
				'description'     => null,
				'duration'        => 30,
				'price'           => 40.00,
				'deposit_type'    => null,
				'deposit_amount'  => null,
				'buffer_before'   => 0,
				'buffer_after'    => 0,
				'is_active'       => 1,
				'display_order'   => 0,
				'created_at'      => current_time( 'mysql' ),
				'updated_at'      => current_time( 'mysql' ),
				'deleted_at'      => null,
			),
			array( '%d', '%s', '%s', '%d', '%f', '%s', '%s', '%d', '%d', '%d', '%d', '%s', '%s', '%s' )
		);

		$service = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$wpdb->prefix}bookings_services WHERE id = %d",
				98
			),
			ARRAY_A
		);

		$deposit = $this->stripe_checkout->calculate_deposit( $service );

		$this->assertEquals( 40.00, $deposit );
	}

	// -------------------------------------------------------------------------
	// 18. test_clamps_invalid_percentage
	// -------------------------------------------------------------------------

	/**
	 * Test that invalid percentage (> 100) is clamped to 100%.
	 */
	public function test_clamps_invalid_percentage(): void {
		global $wpdb;

		$wpdb->insert(
			$wpdb->prefix . 'bookings_services',
			array(
				'id'              => 97,
				'name'            => 'Invalid Percentage',
				'description'     => null,
				'duration'        => 30,
				'price'           => 40.00,
				'deposit_type'    => 'percentage',
				'deposit_amount'  => 150,
				'buffer_before'   => 0,
				'buffer_after'    => 0,
				'is_active'       => 1,
				'display_order'   => 0,
				'created_at'      => current_time( 'mysql' ),
				'updated_at'      => current_time( 'mysql' ),
				'deleted_at'      => null,
			),
			array( '%d', '%s', '%s', '%d', '%f', '%s', '%f', '%d', '%d', '%d', '%d', '%s', '%s', '%s' )
		);

		$service = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$wpdb->prefix}bookings_services WHERE id = %d",
				97
			),
			ARRAY_A
		);

		$deposit = $this->stripe_checkout->calculate_deposit( $service );

		$this->assertEquals( 40.00, $deposit );
	}

	// -------------------------------------------------------------------------
	// 19. test_fixed_deposit_doesnt_exceed_price
	// -------------------------------------------------------------------------

	/**
	 * Test that fixed deposit does not exceed service price.
	 */
	public function test_fixed_deposit_doesnt_exceed_price(): void {
		global $wpdb;

		$wpdb->insert(
			$wpdb->prefix . 'bookings_services',
			array(
				'id'              => 96,
				'name'            => 'Excessive Fixed Deposit',
				'description'     => null,
				'duration'        => 30,
				'price'           => 30.00,
				'deposit_type'    => 'fixed',
				'deposit_amount'  => 50.00,
				'buffer_before'   => 0,
				'buffer_after'    => 0,
				'is_active'       => 1,
				'display_order'   => 0,
				'created_at'      => current_time( 'mysql' ),
				'updated_at'      => current_time( 'mysql' ),
				'deleted_at'      => null,
			),
			array( '%d', '%s', '%s', '%d', '%f', '%s', '%f', '%d', '%d', '%d', '%d', '%s', '%s', '%s' )
		);

		$service = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$wpdb->prefix}bookings_services WHERE id = %d",
				96
			),
			ARRAY_A
		);

		$deposit = $this->stripe_checkout->calculate_deposit( $service );

		$this->assertEquals( 30.00, $deposit );
	}

	// -------------------------------------------------------------------------
	// 20. test_rounds_to_two_decimal_places
	// -------------------------------------------------------------------------

	/**
	 * Test that deposit rounds to 2 decimal places.
	 */
	public function test_rounds_to_two_decimal_places(): void {
		global $wpdb;

		$wpdb->insert(
			$wpdb->prefix . 'bookings_services',
			array(
				'id'              => 95,
				'name'            => 'Rounding Test',
				'description'     => null,
				'duration'        => 30,
				'price'           => 33.33,
				'deposit_type'    => 'percentage',
				'deposit_amount'  => 50,
				'buffer_before'   => 0,
				'buffer_after'    => 0,
				'is_active'       => 1,
				'display_order'   => 0,
				'created_at'      => current_time( 'mysql' ),
				'updated_at'      => current_time( 'mysql' ),
				'deleted_at'      => null,
			),
			array( '%d', '%s', '%s', '%d', '%f', '%s', '%f', '%d', '%d', '%d', '%d', '%s', '%s', '%s' )
		);

		$service = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$wpdb->prefix}bookings_services WHERE id = %d",
				95
			),
			ARRAY_A
		);

		$deposit = $this->stripe_checkout->calculate_deposit( $service );

		$this->assertEquals( 16.67, $deposit );
	}

	// -------------------------------------------------------------------------
	// 21. test_handles_negative_fixed_deposit
	// -------------------------------------------------------------------------

	/**
	 * Test that negative fixed deposit defaults to full price.
	 */
	public function test_handles_negative_fixed_deposit(): void {
		global $wpdb;

		$wpdb->insert(
			$wpdb->prefix . 'bookings_services',
			array(
				'id'              => 94,
				'name'            => 'Negative Fixed',
				'description'     => null,
				'duration'        => 30,
				'price'           => 40.00,
				'deposit_type'    => 'fixed',
				'deposit_amount'  => -10.00,
				'buffer_before'   => 0,
				'buffer_after'    => 0,
				'is_active'       => 1,
				'display_order'   => 0,
				'created_at'      => current_time( 'mysql' ),
				'updated_at'      => current_time( 'mysql' ),
				'deleted_at'      => null,
			),
			array( '%d', '%s', '%s', '%d', '%f', '%s', '%f', '%d', '%d', '%d', '%d', '%s', '%s', '%s' )
		);

		$service = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$wpdb->prefix}bookings_services WHERE id = %d",
				94
			),
			ARRAY_A
		);

		$deposit = $this->stripe_checkout->calculate_deposit( $service );

		$this->assertEquals( 40.00, $deposit );
	}
}
