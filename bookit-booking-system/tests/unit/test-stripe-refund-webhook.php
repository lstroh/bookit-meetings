<?php
/**
 * Stripe charge.refunded webhook and migration 0015.
 *
 * @package Bookit_Booking_System
 */

/**
 * @covers Bookit_Migration_0015_Add_Refunded_Amount_To_Bookings
 * @covers Booking_System_Stripe_Webhook::handle_charge_refunded
 */
class Test_Stripe_Refund_Webhook extends WP_UnitTestCase {

	/**
	 * Whether refunded_amount exists on wp_bookings.
	 *
	 * @param string $table Full table name.
	 * @return bool
	 */
	private function column_exists( string $table ): bool {
		global $wpdb;

		$sql = $wpdb->prepare(
			'SHOW COLUMNS FROM ' . $table . ' LIKE %s',
			'refunded_amount'
		);

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.PreparedSQL.NotPrepared
		$result = $wpdb->get_var( $sql );
		return ! empty( $result );
	}

	/**
	 * Insert service, staff, customer, and a booking row.
	 *
	 * @param array<string, mixed> $booking_overrides Booking column overrides.
	 * @return array{booking_id: int, customer_id: int, service_id: int, staff_id: int}
	 */
	private function insert_minimal_booking( array $booking_overrides = array() ): array {
		global $wpdb;

		$wpdb->insert(
			$wpdb->prefix . 'bookings_services',
			array(
				'name'         => 'Refund Webhook Service',
				'duration'     => 60,
				'price'        => 50.00,
				'deposit_type' => 'fixed',
				'is_active'    => 1,
				'created_at'   => current_time( 'mysql' ),
				'updated_at'   => current_time( 'mysql' ),
			),
			array( '%s', '%d', '%f', '%s', '%d', '%s', '%s' )
		);
		$service_id = (int) $wpdb->insert_id;

		$wpdb->insert(
			$wpdb->prefix . 'bookings_staff',
			array(
				'first_name'    => 'R',
				'last_name'     => 'W',
				'email'         => 'rw-' . wp_generate_password( 10, false, false ) . '@example.com',
				'password_hash' => wp_hash_password( 'x' ),
				'is_active'     => 1,
				'created_at'    => current_time( 'mysql' ),
				'updated_at'    => current_time( 'mysql' ),
			),
			array( '%s', '%s', '%s', '%s', '%d', '%s', '%s' )
		);
		$staff_id = (int) $wpdb->insert_id;

		$wpdb->insert(
			$wpdb->prefix . 'bookings_customers',
			array(
				'email'      => 'refund-cust-' . wp_generate_password( 8, false, false ) . '@example.com',
				'first_name' => 'Ref',
				'last_name'  => 'Und',
				'phone'      => '07700900123',
				'created_at' => current_time( 'mysql' ),
				'updated_at' => current_time( 'mysql' ),
			),
			array( '%s', '%s', '%s', '%s', '%s', '%s' )
		);
		$customer_id = (int) $wpdb->insert_id;

		$defaults = array(
			'customer_id'        => $customer_id,
			'service_id'         => $service_id,
			'staff_id'           => $staff_id,
			'booking_date'       => wp_date( 'Y-m-d', strtotime( '+20 days' ), wp_timezone() ),
			'start_time'         => '10:00:00',
			'end_time'           => '11:00:00',
			'duration'           => 60,
			'status'             => 'confirmed',
			'total_price'        => 50.00,
			'deposit_paid'       => 0.00,
			'balance_due'        => 50.00,
			'full_amount_paid'   => 1,
			'payment_method'     => 'stripe',
			'payment_intent_id'  => 'pi_test_refund_' . wp_generate_password( 8, false, false ),
			'created_at'         => current_time( 'mysql' ),
			'updated_at'         => current_time( 'mysql' ),
		);

		$data = wp_parse_args( $booking_overrides, $defaults );

		$wpdb->insert( $wpdb->prefix . 'bookings', $data );
		$booking_id = (int) $wpdb->insert_id;

		return array(
			'booking_id'   => $booking_id,
			'customer_id'  => $customer_id,
			'service_id'   => $service_id,
			'staff_id'     => $staff_id,
			'payment_intent' => (string) $data['payment_intent_id'],
		);
	}

	/**
	 * Load webhook handler dependencies.
	 *
	 * @return void
	 */
	private function load_webhook(): void {
		$plugin_dir = dirname( dirname( __DIR__ ) );
		require_once $plugin_dir . '/includes/payment/class-stripe-config.php';
		require_once $plugin_dir . '/includes/api/class-stripe-webhook.php';
	}

	/**
	 * Migration 0015 up/down/up leaves refunded_amount column present.
	 */
	public function test_migration_0015_adds_refunded_amount_column(): void {
		global $wpdb;

		$migration_file = dirname( dirname( __DIR__ ) ) . '/database/migrations/0015-add-refunded-amount-to-bookings.php';
		$this->assertFileExists( $migration_file );
		require_once $migration_file;

		$table = $wpdb->prefix . 'bookings';

		$migration = new Bookit_Migration_0015_Add_Refunded_Amount_To_Bookings();
		$migration->up();
		$this->assertTrue( $this->column_exists( $table ), 'refunded_amount should exist after up()' );

		$migration->down();
		$this->assertFalse( $this->column_exists( $table ), 'refunded_amount should be removed after down()' );

		$migration->up();
		$this->assertTrue( $this->column_exists( $table ), 'refunded_amount should exist after second up()' );
	}

	/**
	 * Partial refund updates refunded_amount only.
	 */
	public function test_charge_refunded_updates_refunded_amount(): void {
		$this->load_webhook();

		$ctx = $this->insert_minimal_booking(
			array(
				'total_price' => 50.00,
				'status'      => 'confirmed',
			)
		);

		$event = (object) array(
			'type' => 'charge.refunded',
			'data' => (object) array(
				'object' => (object) array(
					'payment_intent'   => $ctx['payment_intent'],
					'amount_refunded' => 2500,
				),
			),
		);

		$handler = new Booking_System_Stripe_Webhook();
		$method  = new ReflectionMethod( Booking_System_Stripe_Webhook::class, 'handle_charge_refunded' );
		$method->setAccessible( true );
		$result = $method->invoke( $handler, $event );

		$this->assertTrue( $result );

		global $wpdb;
		$row = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT refunded_amount, status FROM {$wpdb->prefix}bookings WHERE id = %d",
				$ctx['booking_id']
			),
			ARRAY_A
		);
		$this->assertNotNull( $row );
		$this->assertSame( '25.00', $row['refunded_amount'] );
		$this->assertSame( 'confirmed', $row['status'] );

		$this->cleanup_refund_test( $ctx );
	}

	/**
	 * Full refund sets cancelled and refunded_amount.
	 */
	public function test_charge_refunded_full_refund_cancels_booking(): void {
		$this->load_webhook();

		$ctx = $this->insert_minimal_booking(
			array(
				'total_price' => 50.00,
				'status'      => 'confirmed',
			)
		);

		$event = (object) array(
			'type' => 'charge.refunded',
			'data' => (object) array(
				'object' => (object) array(
					'payment_intent'   => $ctx['payment_intent'],
					'amount_refunded' => 5000,
				),
			),
		);

		$handler = new Booking_System_Stripe_Webhook();
		$method  = new ReflectionMethod( Booking_System_Stripe_Webhook::class, 'handle_charge_refunded' );
		$method->setAccessible( true );
		$result = $method->invoke( $handler, $event );

		$this->assertTrue( $result );

		global $wpdb;
		$row = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT refunded_amount, status, cancelled_by FROM {$wpdb->prefix}bookings WHERE id = %d",
				$ctx['booking_id']
			),
			ARRAY_A
		);
		$this->assertNotNull( $row );
		$this->assertSame( '50.00', $row['refunded_amount'] );
		$this->assertSame( 'cancelled', $row['status'] );
		$this->assertSame( '0', $row['cancelled_by'] );

		$this->cleanup_refund_test( $ctx );
	}

	/**
	 * Unknown PI returns true (acknowledge).
	 */
	public function test_charge_refunded_no_booking_returns_true(): void {
		$this->load_webhook();

		$event = (object) array(
			'type' => 'charge.refunded',
			'data' => (object) array(
				'object' => (object) array(
					'payment_intent'   => 'pi_no_such_booking_xxx',
					'amount_refunded' => 1000,
				),
			),
		);

		$handler = new Booking_System_Stripe_Webhook();
		$method  = new ReflectionMethod( Booking_System_Stripe_Webhook::class, 'handle_charge_refunded' );
		$method->setAccessible( true );
		$result = $method->invoke( $handler, $event );

		$this->assertTrue( $result );
	}

	/**
	 * Payment row negative amount and type refund.
	 */
	public function test_charge_refunded_inserts_payment_record(): void {
		$this->load_webhook();

		$ctx = $this->insert_minimal_booking(
			array(
				'total_price' => 50.00,
				'status'      => 'confirmed',
			)
		);

		$event = (object) array(
			'type' => 'charge.refunded',
			'data' => (object) array(
				'object' => (object) array(
					'payment_intent'   => $ctx['payment_intent'],
					'amount_refunded' => 1500,
				),
			),
		);

		$handler = new Booking_System_Stripe_Webhook();
		$method  = new ReflectionMethod( Booking_System_Stripe_Webhook::class, 'handle_charge_refunded' );
		$method->setAccessible( true );
		$method->invoke( $handler, $event );

		global $wpdb;
		$count = (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$wpdb->prefix}bookings_payments
				WHERE booking_id = %d AND payment_type = %s AND amount < 0",
				$ctx['booking_id'],
				'refund'
			)
		);
		$this->assertSame( 1, $count );

		$this->cleanup_refund_test( $ctx );
	}

	/**
	 * Delete rows created by insert_minimal_booking.
	 *
	 * @param array<string, int|string> $ctx Context from insert_minimal_booking.
	 */
	private function cleanup_refund_test( array $ctx ): void {
		global $wpdb;

		$bid = (int) $ctx['booking_id'];
		$wpdb->delete( $wpdb->prefix . 'bookings_payments', array( 'booking_id' => $bid ), array( '%d' ) );
		$wpdb->delete( $wpdb->prefix . 'bookings', array( 'id' => $bid ), array( '%d' ) );
		$wpdb->delete( $wpdb->prefix . 'bookings_customers', array( 'id' => (int) $ctx['customer_id'] ), array( '%d' ) );
		$wpdb->delete( $wpdb->prefix . 'bookings_services', array( 'id' => (int) $ctx['service_id'] ), array( '%d' ) );
		$wpdb->delete( $wpdb->prefix . 'bookings_staff', array( 'id' => (int) $ctx['staff_id'] ), array( '%d' ) );
	}
}
