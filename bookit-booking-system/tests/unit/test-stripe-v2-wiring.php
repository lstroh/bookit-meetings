<?php
/**
 * V2 wizard Stripe Checkout wiring (complete_booking REST).
 *
 * @package Bookit_Booking_System
 */

/**
 * @covers Bookit_Wizard_API::complete_booking
 */
class Test_Stripe_V2_Wiring extends WP_UnitTestCase {

	/**
	 * REST namespace.
	 *
	 * @var string
	 */
	private $namespace = 'bookit/v1';

	/**
	 * Mock filter priority.
	 *
	 * @var int
	 */
	private $mock_priority = 999;

	/**
	 * @var callable|null
	 */
	private $stripe_mode_cb;

	/**
	 * @var callable|null
	 */
	private $stripe_session_cb;

	/**
	 * Upsert a row in wp_bookings_settings (same storage as dashboard).
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
	 * Set up.
	 */
	public function setUp(): void {
		parent::setUp();
		$this->ensure_package_types_table_exists();
		$this->ensure_customer_packages_table_exists();
		$this->ensure_package_redemptions_table_exists();

		Bookit_Session_Manager::clear();
		$ip = Bookit_Rate_Limiter::get_client_ip();
		delete_transient( Bookit_Rate_Limiter::KEY_PREFIX . 'wizard_book_' . md5( $ip ) );
		do_action( 'rest_api_init' );

		$autoload = dirname( dirname( __DIR__ ) ) . '/vendor/autoload.php';
		if ( file_exists( $autoload ) ) {
			require_once $autoload;
		}

		// create_checkout_session() requires a configured secret key before mock mode runs (see class-stripe-checkout.php).
		$this->upsert_booking_setting( 'stripe_test_mode', true );
		$this->upsert_booking_setting( 'stripe_secret_key', 'sk_test_51234567890abcdef' );
		$this->upsert_booking_setting( 'stripe_publishable_key', 'pk_test_51234567890abcdef' );
	}

	/**
	 * Tear down.
	 */
	public function tearDown(): void {
		if ( $this->stripe_mode_cb ) {
			remove_filter( 'bookit_stripe_api_mode', $this->stripe_mode_cb, $this->mock_priority );
			$this->stripe_mode_cb = null;
		}
		if ( $this->stripe_session_cb ) {
			remove_filter( 'bookit_mock_stripe_session', $this->stripe_session_cb, $this->mock_priority );
			$this->stripe_session_cb = null;
		}
		global $wpdb;
		foreach ( array( 'stripe_test_mode', 'stripe_secret_key', 'stripe_publishable_key' ) as $sk ) {
			$wpdb->delete( $wpdb->prefix . 'bookings_settings', array( 'setting_key' => $sk ), array( '%s' ) );
		}
		Bookit_Session_Manager::clear();
		parent::tearDown();
	}

	/**
	 * Ensure package types table exists (wp-env test DB may not run all migrations).
	 *
	 * @return void
	 */
	private function ensure_package_types_table_exists() {
		global $wpdb;

		$table_name = $wpdb->prefix . 'bookings_package_types';
		if ( function_exists( 'bookit_test_table_exists' ) && bookit_test_table_exists( $table_name ) ) {
			return;
		}

		$migration_file = dirname( __DIR__, 2 ) . '/database/migrations/0005-create-package-types-table.php';
		if ( file_exists( $migration_file ) ) {
			require_once $migration_file;
		}

		if ( class_exists( 'Bookit_Migration_0005_Create_Package_Types_Table' ) ) {
			$migration = new Bookit_Migration_0005_Create_Package_Types_Table();
			$migration->up();
		}
	}

	/**
	 * Ensure customer packages table exists.
	 *
	 * @return void
	 */
	private function ensure_customer_packages_table_exists() {
		global $wpdb;

		$table_name = $wpdb->prefix . 'bookings_customer_packages';
		if ( function_exists( 'bookit_test_table_exists' ) && bookit_test_table_exists( $table_name ) ) {
			return;
		}

		$charset_collate = $wpdb->get_charset_collate();
		$wpdb->query(
			"CREATE TABLE IF NOT EXISTS {$table_name} (
				id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
				customer_id BIGINT UNSIGNED NOT NULL,
				package_type_id BIGINT UNSIGNED NOT NULL,
				sessions_total INT UNSIGNED NOT NULL,
				sessions_remaining INT UNSIGNED NOT NULL,
				purchase_price DECIMAL(10,2) NULL,
				purchased_at DATETIME NULL,
				expires_at DATETIME NULL,
				status ENUM('active','exhausted','expired','cancelled') NOT NULL DEFAULT 'active',
				payment_method VARCHAR(50) NULL,
				payment_reference VARCHAR(255) NULL,
				notes TEXT NULL,
				created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
				updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
				PRIMARY KEY (id),
				KEY idx_customer_id (customer_id),
				KEY idx_package_type_id (package_type_id),
				KEY idx_status (status),
				KEY idx_expires_at (expires_at)
			) ENGINE=InnoDB {$charset_collate};"
		); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.DirectDatabaseQuery.DirectQuery
	}

	/**
	 * Ensure package redemptions table exists.
	 *
	 * @return void
	 */
	private function ensure_package_redemptions_table_exists() {
		global $wpdb;

		$table_name = $wpdb->prefix . 'bookings_package_redemptions';
		if ( function_exists( 'bookit_test_table_exists' ) && bookit_test_table_exists( $table_name ) ) {
			return;
		}

		$charset_collate = $wpdb->get_charset_collate();
		$wpdb->query(
			"CREATE TABLE IF NOT EXISTS {$table_name} (
				id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
				customer_package_id BIGINT UNSIGNED NOT NULL,
				booking_id BIGINT UNSIGNED NOT NULL,
				redeemed_at DATETIME NOT NULL,
				redeemed_by BIGINT UNSIGNED NOT NULL,
				notes TEXT NULL,
				created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
				PRIMARY KEY (id),
				KEY idx_customer_package_id (customer_package_id),
				KEY idx_booking_id (booking_id)
			) ENGINE=InnoDB {$charset_collate};"
		); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.DirectDatabaseQuery.DirectQuery
	}

	/**
	 * Insert minimal service and staff for Stripe session validation.
	 *
	 * @return array{service_id: int, staff_id: int}
	 */
	private function insert_service_and_staff(): array {
		global $wpdb;
		$wpdb->insert(
			$wpdb->prefix . 'bookings_services',
			array(
				'name'            => 'V2 Stripe Wiring',
				'duration'        => 60,
				'price'           => 50.00,
				'deposit_type'    => 'percentage',
				'deposit_amount'  => 100,
				'is_active'       => 1,
				'created_at'      => current_time( 'mysql' ),
				'updated_at'      => current_time( 'mysql' ),
			),
			array( '%s', '%d', '%f', '%s', '%f', '%d', '%s', '%s' )
		);
		$service_id = (int) $wpdb->insert_id;

		$wpdb->insert(
			$wpdb->prefix . 'bookings_staff',
			array(
				'first_name'    => 'Test',
				'last_name'     => 'Stylist',
				'email'         => 'v2-sw-' . wp_generate_password( 12, false, false ) . '@example.com',
				'password_hash' => wp_hash_password( 'x' ),
				'is_active'     => 1,
				'created_at'    => current_time( 'mysql' ),
				'updated_at'    => current_time( 'mysql' ),
			),
			array( '%s', '%s', '%s', '%s', '%d', '%s', '%s' )
		);
		$staff_id = (int) $wpdb->insert_id;

		return array(
			'service_id' => $service_id,
			'staff_id'   => $staff_id,
		);
	}

	/**
	 * Insert a package type row for buy-package tests.
	 *
	 * @param array<string, mixed> $overrides Field overrides.
	 * @return int Package type ID.
	 */
	private function insert_package_type( array $overrides = array() ): int {
		global $wpdb;

		$defaults = array(
			'name'                   => 'V2 Buy Package Type',
			'description'          => '',
			'sessions_count'         => 5,
			'price_mode'             => 'fixed',
			'fixed_price'            => 120.00,
			'discount_percentage'    => null,
			'expiry_enabled'         => 0,
			'expiry_days'            => null,
			'applicable_service_ids' => null,
			'is_active'              => 1,
			'created_at'             => current_time( 'mysql' ),
			'updated_at'             => current_time( 'mysql' ),
		);

		$data = wp_parse_args( $overrides, $defaults );

		$wpdb->insert(
			$wpdb->prefix . 'bookings_package_types',
			$data,
			array( '%s', '%s', '%d', '%s', '%f', '%f', '%d', '%d', '%s', '%d', '%s', '%s' )
		);

		return (int) $wpdb->insert_id;
	}

	/**
	 * Stripe mock: success with checkout URL.
	 */
	private function enable_stripe_mock_success(): void {
		$this->stripe_mode_cb = function () {
			return 'mock';
		};
		add_filter( 'bookit_stripe_api_mode', $this->stripe_mode_cb, $this->mock_priority );

		$this->stripe_session_cb = function () {
			return (object) array(
				'id'           => 'cs_test_mock123456',
				'url'          => 'https://checkout.stripe.com/c/pay/cs_test_mock123456',
				'amount_total' => 5000,
				'currency'     => 'gbp',
			);
		};
		add_filter( 'bookit_mock_stripe_session', $this->stripe_session_cb, $this->mock_priority );
	}

	/**
	 * @covers Bookit_Wizard_API::complete_booking
	 */
	public function test_complete_booking_stripe_returns_redirect_url(): void {
		$ids = $this->insert_service_and_staff();
		$this->enable_stripe_mock_success();

		$booking_date = wp_date( 'Y-m-d', strtotime( '+30 days' ), wp_timezone() );

		Bookit_Session_Manager::clear();
		Bookit_Session_Manager::set_data(
			array(
				'current_step'              => 5,
				'service_id'                => $ids['service_id'],
				'staff_id'                  => $ids['staff_id'],
				'date'                      => $booking_date,
				'time'                      => '10:00:00',
				'customer_first_name'       => 'Stripe',
				'customer_last_name'        => 'Tester',
				'customer_email'            => 'stripe-v2-wiring@example.com',
				'customer_phone'            => '07700900111',
				'customer_special_requests' => '',
				'cooling_off_waiver'        => 1,
				'payment_method'            => 'stripe',
				'wizard_version'            => 'v2',
			)
		);

		$request = new WP_REST_Request( 'POST', '/' . $this->namespace . '/wizard/complete' );
		$request->set_header( 'X-WP-Nonce', wp_create_nonce( 'wp_rest' ) );

		$response = rest_get_server()->dispatch( $request );
		$this->assertEquals( 200, $response->get_status(), 'Expected HTTP 200 for Stripe redirect' );
		$data = $response->get_data();
		$this->assertIsArray( $data );
		$this->assertTrue( ! empty( $data['success'] ) );
		$this->assertArrayHasKey( 'redirect_url', $data );
		$this->assertStringStartsWith( 'https://checkout.stripe.com/', $data['redirect_url'] );

		global $wpdb;
		$wpdb->delete( $wpdb->prefix . 'bookings_services', array( 'id' => $ids['service_id'] ), array( '%d' ) );
		$wpdb->delete( $wpdb->prefix . 'bookings_staff', array( 'id' => $ids['staff_id'] ), array( '%d' ) );
	}

	/**
	 * @covers Bookit_Wizard_API::complete_booking
	 */
	public function test_complete_booking_paypal_returns_501(): void {
		Bookit_Session_Manager::clear();
		Bookit_Session_Manager::set_data(
			array(
				'current_step'   => 5,
				'payment_method' => 'paypal',
				'service_id'     => 1,
			)
		);

		$request = new WP_REST_Request( 'POST', '/' . $this->namespace . '/wizard/complete' );
		$request->set_header( 'X-WP-Nonce', wp_create_nonce( 'wp_rest' ) );

		$response = rest_get_server()->dispatch( $request );
		$this->assertEquals( 501, $response->get_status() );
		$data = $response->get_data();
		$this->assertIsArray( $data );
		$this->assertSame( 'PAYMENT_METHOD_NOT_SUPPORTED', $data['code'] );
	}

	/**
	 * @covers Bookit_Wizard_API::complete_booking
	 */
	public function test_complete_booking_stripe_exception_returns_500(): void {
		global $wpdb;

		$ids = $this->insert_service_and_staff();

		$this->stripe_mode_cb = function () {
			return 'mock';
		};
		add_filter( 'bookit_stripe_api_mode', $this->stripe_mode_cb, $this->mock_priority );

		$this->stripe_session_cb = function () {
			throw \Stripe\Exception\InvalidRequestException::factory( 'Simulated Stripe API failure' );
		};
		add_filter( 'bookit_mock_stripe_session', $this->stripe_session_cb, $this->mock_priority );

		$booking_date = wp_date( 'Y-m-d', strtotime( '+30 days' ), wp_timezone() );

		$before = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->prefix}bookings" );

		Bookit_Session_Manager::clear();
		Bookit_Session_Manager::set_data(
			array(
				'current_step'              => 5,
				'service_id'                => $ids['service_id'],
				'staff_id'                  => $ids['staff_id'],
				'date'                      => $booking_date,
				'time'                      => '10:00:00',
				'customer_first_name'       => 'Ex',
				'customer_last_name'        => 'ception',
				'customer_email'            => 'stripe-v2-exception@example.com',
				'customer_phone'            => '07700900222',
				'customer_special_requests' => '',
				'cooling_off_waiver'        => 1,
				'payment_method'            => 'stripe',
				'wizard_version'            => 'v2',
			)
		);

		$request = new WP_REST_Request( 'POST', '/' . $this->namespace . '/wizard/complete' );
		$request->set_header( 'X-WP-Nonce', wp_create_nonce( 'wp_rest' ) );

		$response = rest_get_server()->dispatch( $request );
		$this->assertEquals( 500, $response->get_status() );
		$data = $response->get_data();
		$this->assertIsArray( $data );
		$this->assertSame( 'E3010', $data['code'] );

		$after = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->prefix}bookings" );
		$this->assertSame( $before, $after, 'No booking row should be created when Stripe fails' );

		$wpdb->delete( $wpdb->prefix . 'bookings_services', array( 'id' => $ids['service_id'] ), array( '%d' ) );
		$wpdb->delete( $wpdb->prefix . 'bookings_staff', array( 'id' => $ids['staff_id'] ), array( '%d' ) );
	}

	/**
	 * StripeObject metadata must use toArray() in the webhook; (array) cast does not expose keys like service_id.
	 *
	 * @covers Booking_System_Stripe_Webhook::handle_booking_checkout_completed
	 */
	public function test_webhook_metadata_toArray_finds_service_id(): void {
		$plugin_dir = dirname( dirname( __DIR__ ) );
		$webhook_file = $plugin_dir . '/includes/api/class-stripe-webhook.php';
		$creator_file = $plugin_dir . '/includes/booking/class-booking-creator.php';
		if ( ! file_exists( $webhook_file ) || ! file_exists( $creator_file ) ) {
			$this->markTestSkipped( 'Stripe webhook or booking creator not available.' );
			return;
		}
		require_once $plugin_dir . '/includes/payment/class-stripe-config.php';
		require_once $webhook_file;
		require_once $creator_file;

		$ids = $this->insert_service_and_staff();
		$booking_date = wp_date( 'Y-m-d', strtotime( '+30 days' ), wp_timezone() );

		$session_id = 'cs_test_metadata_toArray_' . wp_generate_password( 12, false );
		delete_transient( 'stripe_webhook_' . $session_id );

		$session = \Stripe\Checkout\Session::constructFrom(
			array(
				'id'             => $session_id,
				'payment_status' => 'paid',
				'payment_intent' => 'pi_test_metadata_toArray',
				'amount_total'   => 5000,
				'currency'       => 'gbp',
				'metadata'       => array(
					'service_id'           => (string) $ids['service_id'],
					'staff_id'             => (string) $ids['staff_id'],
					'booking_date'         => $booking_date,
					'booking_time'         => '10:00:00',
					'customer_email'       => 'metadata-toarray@example.com',
					'customer_first_name'  => 'Meta',
					'customer_last_name'   => 'Data',
					'customer_phone'       => '07700900999',
				),
			)
		);

		$event  = (object) array(
			'data' => (object) array(
				'object' => $session,
			),
		);
		$handler = new Booking_System_Stripe_Webhook();
		$method  = new ReflectionMethod( Booking_System_Stripe_Webhook::class, 'handle_booking_checkout_completed' );
		$method->setAccessible( true );
		$result = $method->invoke( $handler, $event );

		$this->assertTrue(
			! ( is_wp_error( $result ) && 'missing_metadata' === $result->get_error_code() && false !== strpos( $result->get_error_message(), 'service_id' ) ),
			'StripeObject metadata must resolve service_id (use metadata->toArray(), not (array) cast).'
		);

		global $wpdb;
		$wpdb->delete( $wpdb->prefix . 'bookings', array( 'stripe_session_id' => $session_id ), array( '%s' ) );
		$wpdb->delete( $wpdb->prefix . 'bookings_services', array( 'id' => $ids['service_id'] ), array( '%d' ) );
		$wpdb->delete( $wpdb->prefix . 'bookings_staff', array( 'id' => $ids['staff_id'] ), array( '%d' ) );
	}

	/**
	 * @covers Bookit_Wizard_API::complete_booking
	 */
	public function test_complete_booking_buy_package_returns_redirect_url(): void {
		$ids         = $this->insert_service_and_staff();
		$package_tid = $this->insert_package_type();
		$this->enable_stripe_mock_success();

		$booking_date = wp_date( 'Y-m-d', strtotime( '+30 days' ), wp_timezone() );

		Bookit_Session_Manager::clear();
		Bookit_Session_Manager::set_data(
			array(
				'current_step'              => 5,
				'service_id'                => $ids['service_id'],
				'staff_id'                  => $ids['staff_id'],
				'date'                      => $booking_date,
				'time'                      => '10:00:00',
				'customer_first_name'       => 'Buy',
				'customer_last_name'        => 'Package',
				'customer_email'            => 'buy-pkg-v2@example.com',
				'customer_phone'            => '07700900111',
				'customer_special_requests' => '',
				'cooling_off_waiver'        => 1,
				'payment_method'            => 'buy_' . $package_tid,
				'wizard_version'            => 'v2',
			)
		);

		$request = new WP_REST_Request( 'POST', '/' . $this->namespace . '/wizard/complete' );
		$request->set_header( 'X-WP-Nonce', wp_create_nonce( 'wp_rest' ) );

		$response = rest_get_server()->dispatch( $request );
		$this->assertEquals( 200, $response->get_status() );
		$data = $response->get_data();
		$this->assertIsArray( $data );
		$this->assertTrue( ! empty( $data['success'] ) );
		$this->assertArrayHasKey( 'redirect_url', $data );
		$this->assertStringStartsWith( 'https://checkout.stripe.com/', $data['redirect_url'] );

		global $wpdb;
		$wpdb->delete( $wpdb->prefix . 'bookings_package_types', array( 'id' => $package_tid ), array( '%d' ) );
		$wpdb->delete( $wpdb->prefix . 'bookings_services', array( 'id' => $ids['service_id'] ), array( '%d' ) );
		$wpdb->delete( $wpdb->prefix . 'bookings_staff', array( 'id' => $ids['staff_id'] ), array( '%d' ) );
	}

	/**
	 * @covers Bookit_Wizard_API::complete_booking
	 */
	public function test_complete_booking_buy_package_not_found_returns_404(): void {
		$ids = $this->insert_service_and_staff();
		Bookit_Session_Manager::clear();
		Bookit_Session_Manager::set_data(
			array(
				'current_step'        => 5,
				'service_id'          => $ids['service_id'],
				'staff_id'            => $ids['staff_id'],
				'date'                => wp_date( 'Y-m-d', strtotime( '+30 days' ), wp_timezone() ),
				'time'                => '10:00:00',
				'customer_first_name' => 'X',
				'customer_last_name'  => 'Y',
				'customer_email'      => 'notfound-pkg@example.com',
				'payment_method'      => 'buy_99999',
				'wizard_version'      => 'v2',
			)
		);

		$request = new WP_REST_Request( 'POST', '/' . $this->namespace . '/wizard/complete' );
		$request->set_header( 'X-WP-Nonce', wp_create_nonce( 'wp_rest' ) );

		$response = rest_get_server()->dispatch( $request );
		$this->assertEquals( 404, $response->get_status() );
		$data = $response->get_data();
		$this->assertIsArray( $data );
		$this->assertSame( 'E5001', $data['code'] );

		global $wpdb;
		$wpdb->delete( $wpdb->prefix . 'bookings_services', array( 'id' => $ids['service_id'] ), array( '%d' ) );
		$wpdb->delete( $wpdb->prefix . 'bookings_staff', array( 'id' => $ids['staff_id'] ), array( '%d' ) );
	}

	/**
	 * @covers Bookit_Wizard_API::complete_booking
	 */
	public function test_complete_booking_buy_package_price_invalid_returns_422(): void {
		$ids         = $this->insert_service_and_staff();
		$package_tid = $this->insert_package_type(
			array(
				'fixed_price' => 0.00,
			)
		);

		Bookit_Session_Manager::clear();
		Bookit_Session_Manager::set_data(
			array(
				'current_step'        => 5,
				'service_id'          => $ids['service_id'],
				'staff_id'            => $ids['staff_id'],
				'date'                => wp_date( 'Y-m-d', strtotime( '+30 days' ), wp_timezone() ),
				'time'                => '10:00:00',
				'customer_first_name' => 'Z',
				'customer_last_name'  => 'W',
				'customer_email'      => 'badprice-pkg@example.com',
				'payment_method'      => 'buy_' . $package_tid,
				'wizard_version'      => 'v2',
			)
		);

		$request = new WP_REST_Request( 'POST', '/' . $this->namespace . '/wizard/complete' );
		$request->set_header( 'X-WP-Nonce', wp_create_nonce( 'wp_rest' ) );

		$response = rest_get_server()->dispatch( $request );
		$this->assertEquals( 422, $response->get_status() );
		$data = $response->get_data();
		$this->assertIsArray( $data );
		$this->assertSame( 'PACKAGE_PRICE_INVALID', $data['code'] );

		global $wpdb;
		$wpdb->delete( $wpdb->prefix . 'bookings_package_types', array( 'id' => $package_tid ), array( '%d' ) );
		$wpdb->delete( $wpdb->prefix . 'bookings_services', array( 'id' => $ids['service_id'] ), array( '%d' ) );
		$wpdb->delete( $wpdb->prefix . 'bookings_staff', array( 'id' => $ids['staff_id'] ), array( '%d' ) );
	}

	/**
	 * @covers Booking_System_Stripe_Webhook::handle_package_purchase_completed
	 */
	public function test_webhook_package_flow_creates_customer_package_and_booking(): void {
		$plugin_dir   = dirname( dirname( __DIR__ ) );
		$webhook_file = $plugin_dir . '/includes/api/class-stripe-webhook.php';
		$creator_file = $plugin_dir . '/includes/booking/class-booking-creator.php';
		if ( ! file_exists( $webhook_file ) || ! file_exists( $creator_file ) ) {
			$this->markTestSkipped( 'Stripe webhook or booking creator not available.' );
			return;
		}
		require_once $plugin_dir . '/includes/payment/class-stripe-config.php';
		require_once $webhook_file;
		require_once $creator_file;

		global $wpdb;

		$ids         = $this->insert_service_and_staff();
		$package_tid = $this->insert_package_type(
			array(
				'sessions_count' => 5,
				'name'           => 'Webhook Package',
			)
		);

		$booking_date = wp_date( 'Y-m-d', strtotime( '+30 days' ), wp_timezone() );
		$session_id   = 'cs_test_pkg_flow_' . wp_generate_password( 8, false );

		delete_transient( 'stripe_pkg_' . $session_id );

		$session = \Stripe\Checkout\Session::constructFrom(
			array(
				'id'             => $session_id,
				'payment_status' => 'paid',
				'payment_intent' => 'pi_test_pkg_flow',
				'amount_total'   => 12000,
				'currency'       => 'gbp',
				'metadata'       => array(
					'flow_type'           => 'package',
					'package_type_id'     => (string) $package_tid,
					'package_name'        => 'Webhook Package',
					'sessions_total'      => '5',
					'expiry_enabled'      => '0',
					'expiry_days'         => '',
					'service_id'          => (string) $ids['service_id'],
					'staff_id'            => (string) $ids['staff_id'],
					'booking_date'        => $booking_date,
					'booking_time'        => '10:00:00',
					'customer_email'      => 'webhook-pkg@example.com',
					'customer_first_name' => 'Webhook',
					'customer_last_name'  => 'Buyer',
					'customer_phone'      => '07700900888',
					'cooling_off_waiver'  => '1',
				),
			)
		);

		$event   = (object) array(
			'data' => (object) array(
				'object' => $session,
			),
		);
		$handler = new Booking_System_Stripe_Webhook();
		$method  = new ReflectionMethod( Booking_System_Stripe_Webhook::class, 'handle_package_purchase_completed' );
		$method->setAccessible( true );
		$result = $method->invoke( $handler, $event );

		$this->assertTrue( true === $result );

		$cp = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$wpdb->prefix}bookings_customer_packages WHERE payment_reference = %s",
				$session_id
			),
			ARRAY_A
		);
		$this->assertNotNull( $cp );
		$this->assertSame( 'active', $cp['status'] );
		$this->assertSame( 'stripe', $cp['payment_method'] );
		$this->assertSame( 4, (int) $cp['sessions_remaining'] );
		$this->assertSame( 5, (int) $cp['sessions_total'] );

		$b = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$wpdb->prefix}bookings WHERE stripe_session_id = %s",
				$session_id
			),
			ARRAY_A
		);
		$this->assertNotNull( $b );
		$this->assertSame( 'package_redemption', $b['payment_method'] );

		$r = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$wpdb->prefix}bookings_package_redemptions WHERE booking_id = %d",
				(int) $b['id']
			),
			ARRAY_A
		);
		$this->assertNotNull( $r );

		$wpdb->delete( $wpdb->prefix . 'bookings_package_redemptions', array( 'booking_id' => (int) $b['id'] ), array( '%d' ) );
		$wpdb->delete( $wpdb->prefix . 'bookings_payments', array( 'booking_id' => (int) $b['id'] ), array( '%d' ) );
		$wpdb->delete( $wpdb->prefix . 'bookings', array( 'id' => (int) $b['id'] ), array( '%d' ) );
		$wpdb->delete( $wpdb->prefix . 'bookings_customer_packages', array( 'id' => (int) $cp['id'] ), array( '%d' ) );
		$wpdb->delete( $wpdb->prefix . 'bookings_customers', array( 'email' => 'webhook-pkg@example.com' ), array( '%s' ) );
		$wpdb->delete( $wpdb->prefix . 'bookings_package_types', array( 'id' => $package_tid ), array( '%d' ) );
		$wpdb->delete( $wpdb->prefix . 'bookings_services', array( 'id' => $ids['service_id'] ), array( '%d' ) );
		$wpdb->delete( $wpdb->prefix . 'bookings_staff', array( 'id' => $ids['staff_id'] ), array( '%d' ) );
		delete_transient( 'stripe_pkg_' . $session_id );
	}

	/**
	 * @covers Booking_System_Stripe_Webhook::handle_package_purchase_completed
	 */
	public function test_webhook_package_flow_idempotency_prevents_duplicate(): void {
		$plugin_dir   = dirname( dirname( __DIR__ ) );
		$webhook_file = $plugin_dir . '/includes/api/class-stripe-webhook.php';
		$creator_file = $plugin_dir . '/includes/booking/class-booking-creator.php';
		if ( ! file_exists( $webhook_file ) || ! file_exists( $creator_file ) ) {
			$this->markTestSkipped( 'Stripe webhook or booking creator not available.' );
			return;
		}
		require_once $plugin_dir . '/includes/payment/class-stripe-config.php';
		require_once $webhook_file;
		require_once $creator_file;

		global $wpdb;

		$ids         = $this->insert_service_and_staff();
		$package_tid = $this->insert_package_type( array( 'name' => 'Idempotent Pkg' ) );

		$booking_date = wp_date( 'Y-m-d', strtotime( '+30 days' ), wp_timezone() );
		$session_id   = 'cs_test_pkg_idem_' . wp_generate_password( 8, false );

		delete_transient( 'stripe_pkg_' . $session_id );

		$session = \Stripe\Checkout\Session::constructFrom(
			array(
				'id'             => $session_id,
				'payment_status' => 'paid',
				'payment_intent' => 'pi_test_pkg_idem',
				'amount_total'   => 12000,
				'currency'       => 'gbp',
				'metadata'       => array(
					'flow_type'           => 'package',
					'package_type_id'     => (string) $package_tid,
					'package_name'        => 'Idempotent Pkg',
					'sessions_total'      => '3',
					'expiry_enabled'      => '0',
					'service_id'          => (string) $ids['service_id'],
					'staff_id'            => (string) $ids['staff_id'],
					'booking_date'        => $booking_date,
					'booking_time'        => '11:00:00',
					'customer_email'      => 'idem-pkg@example.com',
					'customer_first_name' => 'Idem',
					'customer_last_name'  => 'Test',
					'customer_phone'      => '',
					'cooling_off_waiver'  => '1',
				),
			)
		);

		$event   = (object) array(
			'data' => (object) array(
				'object' => $session,
			),
		);
		$handler = new Booking_System_Stripe_Webhook();
		$method  = new ReflectionMethod( Booking_System_Stripe_Webhook::class, 'handle_package_purchase_completed' );
		$method->setAccessible( true );

		$method->invoke( $handler, $event );
		$method->invoke( $handler, $event );

		$cp_count = (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$wpdb->prefix}bookings_customer_packages WHERE payment_reference = %s",
				$session_id
			)
		);
		$this->assertSame( 1, $cp_count );

		$b_count = (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$wpdb->prefix}bookings WHERE stripe_session_id = %s",
				$session_id
			)
		);
		$this->assertSame( 1, $b_count );

		$b_id = (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT id FROM {$wpdb->prefix}bookings WHERE stripe_session_id = %s LIMIT 1",
				$session_id
			)
		);
		if ( $b_id > 0 ) {
			$wpdb->delete( $wpdb->prefix . 'bookings_package_redemptions', array( 'booking_id' => $b_id ), array( '%d' ) );
			$wpdb->delete( $wpdb->prefix . 'bookings_payments', array( 'booking_id' => $b_id ), array( '%d' ) );
			$wpdb->delete( $wpdb->prefix . 'bookings', array( 'id' => $b_id ), array( '%d' ) );
		}
		$wpdb->delete( $wpdb->prefix . 'bookings_customer_packages', array( 'payment_reference' => $session_id ), array( '%s' ) );
		$wpdb->delete( $wpdb->prefix . 'bookings_customers', array( 'email' => 'idem-pkg@example.com' ), array( '%s' ) );
		$wpdb->delete( $wpdb->prefix . 'bookings_package_types', array( 'id' => $package_tid ), array( '%d' ) );
		$wpdb->delete( $wpdb->prefix . 'bookings_services', array( 'id' => $ids['service_id'] ), array( '%d' ) );
		$wpdb->delete( $wpdb->prefix . 'bookings_staff', array( 'id' => $ids['staff_id'] ), array( '%d' ) );
		delete_transient( 'stripe_pkg_' . $session_id );
	}

	/**
	 * Ensure email queue table exists (same shape as test-notification-dispatcher).
	 *
	 * @return void
	 */
	private function ensure_email_queue_table_exists(): void {
		global $wpdb;

		$table_name = $wpdb->prefix . 'bookit_email_queue';

		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.DirectDatabaseQuery.DirectQuery
		$wpdb->query(
			"CREATE TABLE IF NOT EXISTS {$table_name} (
				id              BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
				booking_id      BIGINT UNSIGNED NULL,
				email_type      VARCHAR(50) NOT NULL,
				recipient_email VARCHAR(255) NOT NULL,
				recipient_name  VARCHAR(255) NOT NULL DEFAULT '',
				subject         VARCHAR(500) NOT NULL DEFAULT '',
				html_body       LONGTEXT NOT NULL,
				params          LONGTEXT NULL,
				status          ENUM('pending','processing','sent','failed','cancelled') NOT NULL DEFAULT 'pending',
				attempts        TINYINT UNSIGNED NOT NULL DEFAULT 0,
				max_attempts    TINYINT UNSIGNED NOT NULL DEFAULT 3,
				scheduled_at    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
				sent_at         DATETIME NULL,
				last_error      TEXT NULL,
				created_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
				updated_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
				PRIMARY KEY (id),
				KEY idx_status_scheduled (status, scheduled_at),
				KEY idx_booking_id (booking_id)
			) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;"
		);
	}

	/**
	 * @param int $booking_id Booking ID.
	 * @return void
	 */
	private function clear_email_queue_for_booking( int $booking_id ): void {
		global $wpdb;
		$wpdb->delete(
			$wpdb->prefix . 'bookit_email_queue',
			array( 'booking_id' => $booking_id ),
			array( '%d' )
		);
	}

	/**
	 * @covers Booking_System_Stripe_Webhook::handle_checkout_session_completed
	 */
	public function test_stripe_webhook_booking_flow_enqueues_customer_email(): void {
		$plugin_dir   = dirname( dirname( __DIR__ ) );
		$webhook_file = $plugin_dir . '/includes/api/class-stripe-webhook.php';
		$creator_file = $plugin_dir . '/includes/booking/class-booking-creator.php';
		if ( ! file_exists( $webhook_file ) || ! file_exists( $creator_file ) ) {
			$this->markTestSkipped( 'Stripe webhook or booking creator not available.' );
			return;
		}

		$this->ensure_email_queue_table_exists();

		require_once $plugin_dir . '/includes/payment/class-stripe-config.php';
		require_once $webhook_file;
		require_once $creator_file;

		$ids          = $this->insert_service_and_staff();
		$booking_date = wp_date( 'Y-m-d', strtotime( '+30 days' ), wp_timezone() );

		$session_id = 'cs_test_webhook_email_booking_' . wp_generate_password( 10, false );
		delete_transient( 'stripe_webhook_' . $session_id );

		$session = \Stripe\Checkout\Session::constructFrom(
			array(
				'id'             => $session_id,
				'payment_status' => 'paid',
				'payment_intent' => 'pi_test_webhook_email_booking',
				'amount_total'   => 5000,
				'currency'       => 'gbp',
				'metadata'       => array(
					'service_id'          => (string) $ids['service_id'],
					'staff_id'            => (string) $ids['staff_id'],
					'booking_date'        => $booking_date,
					'booking_time'        => '10:00:00',
					'customer_email'      => 'webhook-email-booking@example.com',
					'customer_first_name' => 'Mail',
					'customer_last_name'  => 'Booking',
					'customer_phone'      => '07700900111',
				),
			)
		);

		$event   = (object) array(
			'data' => (object) array(
				'object' => $session,
			),
		);
		$handler = new Booking_System_Stripe_Webhook();
		$method  = new ReflectionMethod( Booking_System_Stripe_Webhook::class, 'handle_checkout_session_completed' );
		$method->setAccessible( true );
		$result = $method->invoke( $handler, $event );

		$this->assertTrue( true === $result );

		global $wpdb;
		$booking_id = (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT id FROM {$wpdb->prefix}bookings WHERE stripe_session_id = %s LIMIT 1",
				$session_id
			)
		);
		$this->assertGreaterThan( 0, $booking_id );

		$cust = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$wpdb->prefix}bookit_email_queue WHERE booking_id = %d AND email_type = %s ORDER BY id DESC LIMIT 1",
				$booking_id,
				'customer_confirmation'
			),
			ARRAY_A
		);
		$this->assertIsArray( $cust );
		$this->assertSame( 'customer_confirmation', $cust['email_type'] );
		$this->assertSame( 'pending', $cust['status'] );

		$biz_count = (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$wpdb->prefix}bookit_email_queue WHERE booking_id = %d AND email_type = %s",
				$booking_id,
				'business_notification'
			)
		);
		$this->assertSame( 0, $biz_count, 'Sprint 6A-8: webhook flow must not enqueue legacy business_notification.' );

		$this->clear_email_queue_for_booking( $booking_id );
		$wpdb->delete( $wpdb->prefix . 'bookings', array( 'id' => $booking_id ), array( '%d' ) );
		$wpdb->delete( $wpdb->prefix . 'bookings_customers', array( 'email' => 'webhook-email-booking@example.com' ), array( '%s' ) );
		$wpdb->delete( $wpdb->prefix . 'bookings_services', array( 'id' => $ids['service_id'] ), array( '%d' ) );
		$wpdb->delete( $wpdb->prefix . 'bookings_staff', array( 'id' => $ids['staff_id'] ), array( '%d' ) );
		delete_transient( 'stripe_webhook_' . $session_id );
	}

	/**
	 * @covers Booking_System_Stripe_Webhook::handle_checkout_session_completed
	 */
	public function test_stripe_webhook_package_flow_enqueues_customer_email(): void {
		$plugin_dir   = dirname( dirname( __DIR__ ) );
		$webhook_file = $plugin_dir . '/includes/api/class-stripe-webhook.php';
		$creator_file = $plugin_dir . '/includes/booking/class-booking-creator.php';
		if ( ! file_exists( $webhook_file ) || ! file_exists( $creator_file ) ) {
			$this->markTestSkipped( 'Stripe webhook or booking creator not available.' );
			return;
		}

		$this->ensure_email_queue_table_exists();

		require_once $plugin_dir . '/includes/payment/class-stripe-config.php';
		require_once $webhook_file;
		require_once $creator_file;

		global $wpdb;

		$ids         = $this->insert_service_and_staff();
		$package_tid = $this->insert_package_type(
			array(
				'sessions_count' => 5,
				'name'           => 'Webhook Email Package',
			)
		);

		$booking_date = wp_date( 'Y-m-d', strtotime( '+30 days' ), wp_timezone() );
		$session_id   = 'cs_test_webhook_email_pkg_' . wp_generate_password( 8, false );

		delete_transient( 'stripe_pkg_' . $session_id );

		$session = \Stripe\Checkout\Session::constructFrom(
			array(
				'id'             => $session_id,
				'payment_status' => 'paid',
				'payment_intent' => 'pi_test_webhook_email_pkg',
				'amount_total'   => 12000,
				'currency'       => 'gbp',
				'metadata'       => array(
					'flow_type'           => 'package',
					'package_type_id'     => (string) $package_tid,
					'package_name'        => 'Webhook Email Package',
					'sessions_total'      => '5',
					'expiry_enabled'      => '0',
					'expiry_days'         => '',
					'service_id'          => (string) $ids['service_id'],
					'staff_id'            => (string) $ids['staff_id'],
					'booking_date'        => $booking_date,
					'booking_time'        => '10:00:00',
					'customer_email'      => 'webhook-email-pkg@example.com',
					'customer_first_name' => 'Mail',
					'customer_last_name'  => 'Package',
					'customer_phone'      => '07700900888',
					'cooling_off_waiver'  => '1',
				),
			)
		);

		$event   = (object) array(
			'data' => (object) array(
				'object' => $session,
			),
		);
		$handler = new Booking_System_Stripe_Webhook();
		$method  = new ReflectionMethod( Booking_System_Stripe_Webhook::class, 'handle_checkout_session_completed' );
		$method->setAccessible( true );
		$result = $method->invoke( $handler, $event );

		$this->assertTrue( true === $result );

		$b = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT id FROM {$wpdb->prefix}bookings WHERE stripe_session_id = %s LIMIT 1",
				$session_id
			),
			ARRAY_A
		);
		$this->assertIsArray( $b );
		$booking_id = (int) $b['id'];
		$this->assertGreaterThan( 0, $booking_id );

		$cust = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$wpdb->prefix}bookit_email_queue WHERE booking_id = %d AND email_type = %s ORDER BY id DESC LIMIT 1",
				$booking_id,
				'customer_confirmation'
			),
			ARRAY_A
		);
		$this->assertIsArray( $cust );
		$this->assertSame( 'customer_confirmation', $cust['email_type'] );

		$biz_count = (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$wpdb->prefix}bookit_email_queue WHERE booking_id = %d AND email_type = %s",
				$booking_id,
				'business_notification'
			)
		);
		$this->assertSame( 0, $biz_count, 'Sprint 6A-8: package webhook flow must not enqueue legacy business_notification.' );

		$this->clear_email_queue_for_booking( $booking_id );
		$wpdb->delete( $wpdb->prefix . 'bookings_package_redemptions', array( 'booking_id' => $booking_id ), array( '%d' ) );
		$wpdb->delete( $wpdb->prefix . 'bookings_payments', array( 'booking_id' => $booking_id ), array( '%d' ) );
		$wpdb->delete( $wpdb->prefix . 'bookings', array( 'id' => $booking_id ), array( '%d' ) );
		$cp_id = (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT id FROM {$wpdb->prefix}bookings_customer_packages WHERE payment_reference = %s LIMIT 1",
				$session_id
			)
		);
		if ( $cp_id > 0 ) {
			$wpdb->delete( $wpdb->prefix . 'bookings_customer_packages', array( 'id' => $cp_id ), array( '%d' ) );
		}
		$wpdb->delete( $wpdb->prefix . 'bookings_customers', array( 'email' => 'webhook-email-pkg@example.com' ), array( '%s' ) );
		$wpdb->delete( $wpdb->prefix . 'bookings_package_types', array( 'id' => $package_tid ), array( '%d' ) );
		$wpdb->delete( $wpdb->prefix . 'bookings_services', array( 'id' => $ids['service_id'] ), array( '%d' ) );
		$wpdb->delete( $wpdb->prefix . 'bookings_staff', array( 'id' => $ids['staff_id'] ), array( '%d' ) );
		delete_transient( 'stripe_pkg_' . $session_id );
	}
}
