<?php
/**
 * Tests for Bookit_Google_Calendar_Sync hook listeners.
 *
 * @package    Bookit_Booking_System
 * @subpackage Tests
 */

/**
 * @covers Bookit_Google_Calendar_Sync
 */
class Test_Google_Calendar_Hook_Listeners extends WP_UnitTestCase {

	/**
	 * @var array<int, array{0: string, 1: int, 2: int|null}>
	 */
	private array $sync_enqueued = array();

	/**
	 * @var callable|null
	 */
	private $sync_enqueued_listener;

	/**
	 * Set up.
	 */
	public function setUp(): void {
		parent::setUp();

		$this->sync_enqueued          = array();
		$this->sync_enqueued_listener = function ( string $operation, int $booking_id, ?int $calendar_staff_id ): void {
			$this->sync_enqueued[] = array( $operation, $booking_id, $calendar_staff_id );
		};

		add_action( 'bookit_calendar_sync_enqueued', $this->sync_enqueued_listener, 10, 3 );

		bookit_test_truncate_tables(
			array(
				'bookings_audit_log',
				'bookings',
				'bookings_staff_services',
				'bookings_services',
				'bookings_staff',
				'bookings_customers',
				'bookings_settings',
			)
		);
	}

	/**
	 * Tear down.
	 */
	public function tearDown(): void {
		if ( is_callable( $this->sync_enqueued_listener ) ) {
			remove_action( 'bookit_calendar_sync_enqueued', $this->sync_enqueued_listener, 10 );
		}

		bookit_test_truncate_tables(
			array(
				'bookings_audit_log',
				'bookings',
				'bookings_staff_services',
				'bookings_services',
				'bookings_staff',
				'bookings_customers',
				'bookings_settings',
			)
		);

		parent::tearDown();
	}

	/**
	 * @covers Bookit_Google_Calendar_Sync::on_booking_created
	 */
	public function test_booking_created_confirmed_enqueues_sync(): void {
		$this->seed_google_oauth_settings();

		$staff_id = $this->create_staff( array( 'role' => 'staff' ) );
		$this->set_staff_google_connected( $staff_id, true );

		$booking_id = $this->create_booking_row( $staff_id, 'confirmed' );

		do_action( 'bookit_after_booking_created', $booking_id, array( 'staff_id' => $staff_id ) );

		$this->assertCount( 1, $this->sync_enqueued );
		$this->assertSame( array( 'create', $booking_id, $staff_id ), $this->sync_enqueued[0] );
	}

	/**
	 * @covers Bookit_Google_Calendar_Sync::on_booking_created
	 */
	public function test_booking_created_pending_payment_enqueues_sync(): void {
		$this->seed_google_oauth_settings();

		$staff_id = $this->create_staff( array( 'role' => 'staff' ) );
		$this->set_staff_google_connected( $staff_id, true );

		$booking_id = $this->create_booking_row( $staff_id, 'pending_payment' );

		do_action( 'bookit_after_booking_created', $booking_id, array( 'staff_id' => $staff_id ) );

		$this->assertCount( 1, $this->sync_enqueued );
		$this->assertSame( 'create', $this->sync_enqueued[0][0] );
		$this->assertSame( $booking_id, $this->sync_enqueued[0][1] );
	}

	/**
	 * @covers Bookit_Google_Calendar_Sync::on_booking_created
	 */
	public function test_booking_created_cancelled_does_not_enqueue(): void {
		$this->seed_google_oauth_settings();

		$staff_id = $this->create_staff( array( 'role' => 'staff' ) );
		$this->set_staff_google_connected( $staff_id, true );

		$booking_id = $this->create_booking_row( $staff_id, 'cancelled' );

		do_action( 'bookit_after_booking_created', $booking_id, array( 'staff_id' => $staff_id ) );

		$this->assertCount( 0, $this->sync_enqueued );
	}

	/**
	 * @covers Bookit_Google_Calendar_Sync::on_booking_rescheduled
	 */
	public function test_booking_rescheduled_enqueues_update(): void {
		$this->seed_google_oauth_settings();

		$staff_id = $this->create_staff( array( 'role' => 'staff' ) );
		$this->set_staff_google_connected( $staff_id, true );

		$booking_id = $this->create_booking_row( $staff_id, 'confirmed' );

		do_action( 'bookit_booking_rescheduled', $booking_id, array( 'staff_id' => $staff_id ) );

		$this->assertCount( 1, $this->sync_enqueued );
		$this->assertSame( array( 'update', $booking_id, $staff_id ), $this->sync_enqueued[0] );
	}

	/**
	 * @covers Bookit_Google_Calendar_Sync::on_booking_cancelled
	 */
	public function test_booking_cancelled_enqueues_delete(): void {
		$this->seed_google_oauth_settings();

		$staff_id = $this->create_staff( array( 'role' => 'staff' ) );

		$booking_id = $this->create_booking_row( $staff_id, 'cancelled' );

		do_action( 'bookit_after_booking_cancelled', $booking_id, array() );

		$this->assertCount( 1, $this->sync_enqueued );
		$this->assertSame( array( 'delete', $booking_id, null ), $this->sync_enqueued[0] );
	}

	/**
	 * @covers Bookit_Google_Calendar_Sync::on_booking_created
	 */
	public function test_fallback_used_when_staff_not_connected_and_enabled(): void {
		$this->seed_google_oauth_settings();
		$this->insert_setting( 'google_calendar_fallback_enabled', '1' );

		$staff_id = $this->create_staff( array( 'role' => 'staff' ) );
		$this->set_staff_google_connected( $staff_id, false );

		$admin_id = $this->create_staff( array( 'role' => 'admin', 'email' => 'admin-fb-' . wp_generate_password( 6, false ) . '@test.com' ) );
		$this->set_staff_google_connected( $admin_id, true );

		$booking_id = $this->create_booking_row( $staff_id, 'confirmed' );

		do_action( 'bookit_after_booking_created', $booking_id, array( 'staff_id' => $staff_id ) );

		$this->assertCount( 1, $this->sync_enqueued );
		$this->assertSame( array( 'create', $booking_id, $admin_id ), $this->sync_enqueued[0] );
	}

	/**
	 * @covers Bookit_Google_Calendar_Sync::on_booking_created
	 */
	public function test_sync_skipped_when_no_connected_staff_and_fallback_disabled(): void {
		global $wpdb;

		$this->seed_google_oauth_settings();
		$this->insert_setting( 'google_calendar_fallback_enabled', '0' );

		$staff_id = $this->create_staff( array( 'role' => 'staff' ) );
		$this->set_staff_google_connected( $staff_id, false );

		$booking_id = $this->create_booking_row( $staff_id, 'confirmed' );

		do_action( 'bookit_after_booking_created', $booking_id, array( 'staff_id' => $staff_id ) );

		$this->assertCount( 0, $this->sync_enqueued );

		$notes = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT notes FROM {$wpdb->prefix}bookings_audit_log WHERE action = %s AND object_type = %s AND object_id = %d",
				'google_calendar.sync_skipped',
				'booking',
				$booking_id
			)
		);
		$this->assertSame( 'no_connected_staff', $notes );
	}

	/**
	 * @covers Bookit_Google_Calendar_Sync::on_booking_created
	 */
	public function test_sync_skipped_when_no_connected_staff_and_no_admin_fallback(): void {
		global $wpdb;

		$this->seed_google_oauth_settings();
		$this->insert_setting( 'google_calendar_fallback_enabled', '1' );

		$staff_id = $this->create_staff( array( 'role' => 'staff' ) );
		$this->set_staff_google_connected( $staff_id, false );

		$admin_id = $this->create_staff( array( 'role' => 'admin', 'email' => 'admin-nc-' . wp_generate_password( 6, false ) . '@test.com' ) );
		$this->set_staff_google_connected( $admin_id, false );

		$booking_id = $this->create_booking_row( $staff_id, 'confirmed' );

		do_action( 'bookit_after_booking_created', $booking_id, array( 'staff_id' => $staff_id ) );

		$this->assertCount( 0, $this->sync_enqueued );

		$notes = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT notes FROM {$wpdb->prefix}bookings_audit_log WHERE action = %s AND object_type = %s AND object_id = %d",
				'google_calendar.sync_skipped',
				'booking',
				$booking_id
			)
		);
		$this->assertSame( 'no_connected_staff', $notes );
	}

	/**
	 * @return void
	 */
	private function seed_google_oauth_settings(): void {
		global $wpdb;

		$wpdb->insert(
			$wpdb->prefix . 'bookings_settings',
			array(
				'setting_key'   => 'google_client_id',
				'setting_value' => 'test-client-id.apps.googleusercontent.com',
				'setting_type'  => 'string',
			),
			array( '%s', '%s', '%s' )
		);
		$wpdb->insert(
			$wpdb->prefix . 'bookings_settings',
			array(
				'setting_key'   => 'google_client_secret',
				'setting_value' => 'test-client-secret',
				'setting_type'  => 'string',
			),
			array( '%s', '%s', '%s' )
		);
	}

	/**
	 * @param string $key   Setting key.
	 * @param string $value Setting value.
	 * @return void
	 */
	private function insert_setting( string $key, string $value ): void {
		global $wpdb;

		$wpdb->insert(
			$wpdb->prefix . 'bookings_settings',
			array(
				'setting_key'   => $key,
				'setting_value' => $value,
				'setting_type'  => 'string',
			),
			array( '%s', '%s', '%s' )
		);
	}

	/**
	 * @param array<string, mixed> $overrides Row overrides.
	 * @return int Staff ID.
	 */
	private function create_staff( array $overrides = array() ): int {
		global $wpdb;

		$defaults = array(
			'email'                    => 'staff-' . wp_generate_password( 8, false ) . '@test.com',
			'password_hash'            => password_hash( 'password123', PASSWORD_BCRYPT ),
			'first_name'               => 'Test',
			'last_name'                => 'Staff',
			'phone'                    => '07700900000',
			'photo_url'                => null,
			'bio'                      => 'Bio',
			'title'                    => 'Role',
			'role'                     => 'staff',
			'google_calendar_id'       => null,
			'is_active'                => 1,
			'display_order'            => 0,
			'notification_preferences' => null,
			'created_at'               => current_time( 'mysql' ),
			'updated_at'               => current_time( 'mysql' ),
			'deleted_at'               => null,
		);

		$data = array_merge( $defaults, $overrides );

		$wpdb->insert(
			$wpdb->prefix . 'bookings_staff',
			$data,
			array( '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%d', '%d', '%s', '%s', '%s', '%s' )
		);

		return (int) $wpdb->insert_id;
	}

	/**
	 * @param int  $staff_id Staff ID.
	 * @param bool $on       Connected flag.
	 * @return void
	 */
	private function set_staff_google_connected( int $staff_id, bool $on ): void {
		global $wpdb;

		if ( $on ) {
			$wpdb->update(
				$wpdb->prefix . 'bookings_staff',
				array(
					'google_calendar_connected'  => 1,
					'google_oauth_access_token'  => Bookit_Encryption::encrypt( 'access-test' ),
					'google_oauth_refresh_token' => Bookit_Encryption::encrypt( 'refresh-test' ),
					'google_oauth_token_expiry'  => gmdate( 'Y-m-d H:i:s', time() + 7200 ),
					'updated_at'                 => current_time( 'mysql' ),
				),
				array( 'id' => $staff_id ),
				array( '%d', '%s', '%s', '%s', '%s' ),
				array( '%d' )
			);
		} else {
			$wpdb->update(
				$wpdb->prefix . 'bookings_staff',
				array(
					'google_calendar_connected'  => 0,
					'google_oauth_access_token'  => null,
					'google_oauth_refresh_token' => null,
					'google_oauth_token_expiry'  => null,
					'updated_at'                 => current_time( 'mysql' ),
				),
				array( 'id' => $staff_id ),
				array( '%d', '%s', '%s', '%s', '%s' ),
				array( '%d' )
			);
		}
	}

	/**
	 * @param int    $staff_id Assigned staff.
	 * @param string $status   Booking status.
	 * @return int Booking ID.
	 */
	private function create_booking_row( int $staff_id, string $status ): int {
		global $wpdb;

		$wpdb->insert(
			$wpdb->prefix . 'bookings_customers',
			array(
				'email'      => 'c-' . wp_generate_password( 6, false ) . '@example.com',
				'first_name' => 'C',
				'last_name'  => 'D',
				'phone'      => '07700900001',
				'created_at' => current_time( 'mysql' ),
				'updated_at' => current_time( 'mysql' ),
			),
			array( '%s', '%s', '%s', '%s', '%s', '%s' )
		);
		$customer_id = (int) $wpdb->insert_id;

		$wpdb->insert(
			$wpdb->prefix . 'bookings_services',
			array(
				'name'         => 'Svc',
				'duration'     => 60,
				'price'        => 50.00,
				'deposit_type' => 'percentage',
				'deposit_amount' => 100,
				'is_active'    => 1,
				'created_at'   => current_time( 'mysql' ),
				'updated_at'   => current_time( 'mysql' ),
			),
			array( '%s', '%d', '%f', '%s', '%f', '%d', '%s', '%s' )
		);
		$service_id = (int) $wpdb->insert_id;

		$wpdb->insert(
			$wpdb->prefix . 'bookings',
			array(
				'booking_reference' => 'BK' . wp_generate_password( 6, false, false ),
				'customer_id'       => $customer_id,
				'service_id'        => $service_id,
				'staff_id'          => $staff_id,
				'booking_date'      => gmdate( 'Y-m-d', strtotime( '+10 days' ) ),
				'start_time'        => '10:00:00',
				'end_time'          => '11:00:00',
				'duration'          => 60,
				'status'            => $status,
				'total_price'       => 50.00,
				'deposit_amount'    => 50.00,
				'deposit_paid'      => 0.00,
				'balance_due'       => 50.00,
				'full_amount_paid'  => 0,
				'payment_method'    => 'manual',
				'created_at'        => current_time( 'mysql' ),
				'updated_at'        => current_time( 'mysql' ),
				'deleted_at'        => null,
			),
			array(
				'%s',
				'%d',
				'%d',
				'%d',
				'%s',
				'%s',
				'%s',
				'%d',
				'%s',
				'%f',
				'%f',
				'%f',
				'%f',
				'%d',
				'%s',
				'%s',
				'%s',
				'%s',
			)
		);

		return (int) $wpdb->insert_id;
	}
}
