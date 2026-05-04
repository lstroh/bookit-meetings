<?php
/**
 * Tests for staff digest and schedule cron jobs.
 *
 * @package Bookit_Booking_System
 * @subpackage Tests
 */

class Test_Staff_Digest_Cron extends WP_UnitTestCase {

	public function setUp(): void {
		parent::setUp();

		$this->load_cron_classes();
		$this->ensure_digest_queue_table_exists();

		bookit_test_truncate_tables(
			array(
				'bookit_notification_digest_queue',
				'bookit_email_queue',
				'bookings_audit_log',
				'bookings',
				'bookings_staff_services',
				'bookings_services',
				'bookings_staff',
				'bookings_customers',
				'bookings_settings',
			)
		);

		$this->clear_scheduled_hook( Bookit_Staff_Digest_Daily::CRON_HOOK );
		$this->clear_scheduled_hook( Bookit_Staff_Digest_Weekly::CRON_HOOK );
		$this->clear_scheduled_hook( Bookit_Staff_Schedule_Daily::CRON_HOOK );

		delete_option( 'bookit_staff_digest_daily_last_run' );
		delete_option( 'bookit_staff_digest_daily_last_count' );
		delete_option( 'bookit_staff_digest_weekly_last_run' );
		delete_option( 'bookit_staff_digest_weekly_last_count' );
		delete_option( 'bookit_staff_schedule_daily_last_run' );
		delete_option( 'bookit_staff_schedule_daily_last_count' );
	}

	public function tearDown(): void {
		bookit_test_truncate_tables(
			array(
				'bookit_notification_digest_queue',
				'bookit_email_queue',
				'bookings_audit_log',
				'bookings',
				'bookings_staff_services',
				'bookings_services',
				'bookings_staff',
				'bookings_customers',
				'bookings_settings',
			)
		);

		$this->clear_scheduled_hook( Bookit_Staff_Digest_Daily::CRON_HOOK );
		$this->clear_scheduled_hook( Bookit_Staff_Digest_Weekly::CRON_HOOK );
		$this->clear_scheduled_hook( Bookit_Staff_Schedule_Daily::CRON_HOOK );

		delete_option( 'bookit_staff_digest_daily_last_run' );
		delete_option( 'bookit_staff_digest_daily_last_count' );
		delete_option( 'bookit_staff_digest_weekly_last_run' );
		delete_option( 'bookit_staff_digest_weekly_last_count' );
		delete_option( 'bookit_staff_schedule_daily_last_run' );
		delete_option( 'bookit_staff_schedule_daily_last_count' );

		parent::tearDown();
	}

	public function test_daily_digest_sends_combined_email_for_pending_items(): void {
		global $wpdb;

		$staff_id = $this->insert_staff(
			array(
				'email'                   => 'daily@example.com',
				'notification_preferences' => wp_json_encode( array( 'new_booking' => 'daily' ) ),
			)
		);
		$service_id  = $this->insert_service();
		$customer_id = $this->insert_customer();

		$booking_one = $this->insert_booking( $customer_id, $service_id, $staff_id );
		$booking_two = $this->insert_booking(
			$customer_id,
			$service_id,
			$staff_id,
			array(
				'booking_reference' => 'BKTEST-2',
				'start_time'        => '11:00:00',
				'end_time'          => '12:00:00',
			)
		);

		$this->insert_digest_queue_row( $staff_id, 'new_booking', $booking_one );
		$this->insert_digest_queue_row( $staff_id, 'new_booking', $booking_two );

		$count = Bookit_Staff_Digest_Daily::run_digest();

		$queued = (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$wpdb->prefix}bookit_email_queue WHERE email_type = %s AND recipient_email = %s",
				'staff_daily_digest',
				'daily@example.com'
			)
		);

		$this->assertSame( 1, $count );
		$this->assertSame( 1, $queued );
	}

	public function test_daily_digest_skips_cancelled_bookings(): void {
		global $wpdb;

		$staff_id = $this->insert_staff(
			array(
				'email'                   => 'daily@example.com',
				'notification_preferences' => wp_json_encode( array( 'new_booking' => 'daily' ) ),
			)
		);
		$service_id  = $this->insert_service();
		$customer_id = $this->insert_customer();
		$booking_id  = $this->insert_booking(
			$customer_id,
			$service_id,
			$staff_id,
			array(
				'status' => 'cancelled',
			)
		);

		$row_id = $this->insert_digest_queue_row( $staff_id, 'new_booking', $booking_id );

		$count = Bookit_Staff_Digest_Daily::run_digest();

		$queued = (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$wpdb->prefix}bookit_email_queue WHERE email_type = %s",
				'staff_daily_digest'
			)
		);

		$processed = (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT processed FROM {$wpdb->prefix}bookit_notification_digest_queue WHERE id = %d",
				$row_id
			)
		);

		$this->assertSame( 0, $count );
		$this->assertSame( 0, $queued );
		$this->assertSame( 1, $processed );
	}

	public function test_daily_digest_skips_inactive_staff(): void {
		global $wpdb;

		$staff_id = $this->insert_staff(
			array(
				'email'                   => 'inactive@example.com',
				'is_active'               => 0,
				'notification_preferences' => wp_json_encode( array( 'new_booking' => 'daily' ) ),
			)
		);
		$service_id  = $this->insert_service();
		$customer_id = $this->insert_customer();
		$booking_id  = $this->insert_booking( $customer_id, $service_id, $staff_id );

		$this->insert_digest_queue_row( $staff_id, 'new_booking', $booking_id );

		$count = Bookit_Staff_Digest_Daily::run_digest();

		$queued = (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$wpdb->prefix}bookit_email_queue WHERE email_type = %s",
				'staff_daily_digest'
			)
		);

		$this->assertSame( 0, $count );
		$this->assertSame( 0, $queued );
	}

	public function test_daily_digest_marks_rows_processed(): void {
		global $wpdb;

		$staff_id = $this->insert_staff(
			array(
				'email'                   => 'daily@example.com',
				'notification_preferences' => wp_json_encode( array( 'new_booking' => 'daily' ) ),
			)
		);
		$service_id  = $this->insert_service();
		$customer_id = $this->insert_customer();
		$booking_one = $this->insert_booking( $customer_id, $service_id, $staff_id );
		$booking_two = $this->insert_booking(
			$customer_id,
			$service_id,
			$staff_id,
			array(
				'booking_reference' => 'BKTEST-2',
				'start_time'        => '11:00:00',
				'end_time'          => '12:00:00',
			)
		);

		$this->insert_digest_queue_row( $staff_id, 'new_booking', $booking_one );
		$this->insert_digest_queue_row( $staff_id, 'new_booking', $booking_two );

		Bookit_Staff_Digest_Daily::run_digest();

		$processed_count = (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$wpdb->prefix}bookit_notification_digest_queue WHERE staff_id = %d AND processed = 1",
				$staff_id
			)
		);

		$this->assertSame( 2, $processed_count );
	}

	public function test_daily_digest_skips_when_no_pending_items(): void {
		$count = Bookit_Staff_Digest_Daily::run_digest();
		$this->assertSame( 0, $count );
	}

	public function test_weekly_digest_only_processes_weekly_preference_items(): void {
		global $wpdb;

		$daily_staff_id = $this->insert_staff(
			array(
				'email'                   => 'daily@example.com',
				'notification_preferences' => wp_json_encode( array( 'new_booking' => 'daily' ) ),
			)
		);
		$weekly_staff_id = $this->insert_staff(
			array(
				'email'                   => 'weekly@example.com',
				'notification_preferences' => wp_json_encode( array( 'new_booking' => 'weekly' ) ),
			)
		);

		$service_id  = $this->insert_service();
		$customer_id = $this->insert_customer();
		$booking_one = $this->insert_booking( $customer_id, $service_id, $daily_staff_id );
		$booking_two = $this->insert_booking( $customer_id, $service_id, $weekly_staff_id, array( 'booking_reference' => 'BKTEST-2' ) );

		$this->insert_digest_queue_row( $daily_staff_id, 'new_booking', $booking_one );
		$this->insert_digest_queue_row( $weekly_staff_id, 'new_booking', $booking_two );

		$count = Bookit_Staff_Digest_Weekly::run_digest();

		$weekly_queued = (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$wpdb->prefix}bookit_email_queue WHERE email_type = %s AND recipient_email = %s",
				'staff_weekly_digest',
				'weekly@example.com'
			)
		);
		$daily_queued  = (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$wpdb->prefix}bookit_email_queue WHERE email_type = %s AND recipient_email = %s",
				'staff_weekly_digest',
				'daily@example.com'
			)
		);

		$this->assertSame( 1, $count );
		$this->assertSame( 1, $weekly_queued );
		$this->assertSame( 0, $daily_queued );
	}

	public function test_schedule_digest_sends_when_bookings_exist_today(): void {
		global $wpdb;

		$staff_id = $this->insert_staff(
			array(
				'email'                   => 'schedule@example.com',
				'notification_preferences' => wp_json_encode( array( 'daily_schedule' => true ) ),
			)
		);
		$service_id  = $this->insert_service();
		$customer_id = $this->insert_customer();

		$this->insert_booking(
			$customer_id,
			$service_id,
			$staff_id,
			array(
				'booking_date' => gmdate( 'Y-m-d' ),
				'status'       => 'confirmed',
			)
		);

		$count = Bookit_Staff_Schedule_Daily::run_schedule();

		$queued = (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$wpdb->prefix}bookit_email_queue WHERE email_type = %s AND recipient_email = %s",
				'staff_daily_schedule',
				'schedule@example.com'
			)
		);

		$this->assertSame( 1, $count );
		$this->assertSame( 1, $queued );
	}

	public function test_schedule_digest_skips_when_no_bookings_today(): void {
		global $wpdb;

		$this->insert_staff(
			array(
				'email'                   => 'schedule@example.com',
				'notification_preferences' => wp_json_encode( array( 'daily_schedule' => true ) ),
			)
		);

		$count = Bookit_Staff_Schedule_Daily::run_schedule();

		$queued = (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$wpdb->prefix}bookit_email_queue WHERE email_type = %s",
				'staff_daily_schedule'
			)
		);

		$this->assertSame( 0, $count );
		$this->assertSame( 0, $queued );
	}

	public function test_schedule_digest_only_sends_to_opted_in_staff(): void {
		global $wpdb;

		$opted_in_id = $this->insert_staff(
			array(
				'email'                   => 'opted-in@example.com',
				'notification_preferences' => wp_json_encode( array( 'daily_schedule' => true ) ),
			)
		);
		$opted_out_id = $this->insert_staff(
			array(
				'email' => 'opted-out@example.com',
			)
		);

		$service_id  = $this->insert_service();
		$customer_id = $this->insert_customer();

		$this->insert_booking(
			$customer_id,
			$service_id,
			$opted_in_id,
			array(
				'booking_date' => gmdate( 'Y-m-d' ),
				'status'       => 'confirmed',
				'start_time'   => '09:00:00',
			)
		);
		$this->insert_booking(
			$customer_id,
			$service_id,
			$opted_out_id,
			array(
				'booking_date' => gmdate( 'Y-m-d' ),
				'status'       => 'confirmed',
				'start_time'   => '10:00:00',
			)
		);

		$count = Bookit_Staff_Schedule_Daily::run_schedule();

		$total_queued = (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$wpdb->prefix}bookit_email_queue WHERE email_type = %s",
				'staff_daily_schedule'
			)
		);
		$opted_in_queued = (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$wpdb->prefix}bookit_email_queue WHERE email_type = %s AND recipient_email = %s",
				'staff_daily_schedule',
				'opted-in@example.com'
			)
		);
		$opted_out_queued = (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$wpdb->prefix}bookit_email_queue WHERE email_type = %s AND recipient_email = %s",
				'staff_daily_schedule',
				'opted-out@example.com'
			)
		);

		$this->assertSame( 1, $count );
		$this->assertSame( 1, $total_queued );
		$this->assertSame( 1, $opted_in_queued );
		$this->assertSame( 0, $opted_out_queued );
	}

	private function load_cron_classes(): void {
		$root = dirname( __DIR__, 2 );

		$files = array(
			$root . '/includes/cron/class-bookit-staff-digest-daily.php',
			$root . '/includes/cron/class-bookit-staff-digest-weekly.php',
			$root . '/includes/cron/class-bookit-staff-schedule-daily.php',
		);

		foreach ( $files as $file ) {
			if ( file_exists( $file ) ) {
				require_once $file;
			}
		}
	}

	private function ensure_digest_queue_table_exists(): void {
		global $wpdb;

		$table_name = $wpdb->prefix . 'bookit_notification_digest_queue';
		if ( function_exists( 'bookit_test_table_exists' ) && bookit_test_table_exists( $table_name ) ) {
			return;
		}

		$migration_file = dirname( __DIR__, 2 ) . '/database/migrations/0017-create-notification-digest-queue.php';
		if ( file_exists( $migration_file ) ) {
			require_once $migration_file;
		}

		if ( class_exists( 'Bookit_Migration_0017_Create_Notification_Digest_Queue' ) ) {
			$migration = new Bookit_Migration_0017_Create_Notification_Digest_Queue();
			$migration->up();
		}
	}

	private function insert_digest_queue_row( int $staff_id, string $event_type, int $booking_id ): int {
		global $wpdb;

		$wpdb->insert(
			$wpdb->prefix . 'bookit_notification_digest_queue',
			array(
				'staff_id'   => $staff_id,
				'event_type' => $event_type,
				'booking_id' => $booking_id,
				'processed'  => 0,
				'created_at' => current_time( 'mysql' ),
			),
			array( '%d', '%s', '%d', '%d', '%s' )
		);

		return (int) $wpdb->insert_id;
	}

	private function insert_staff( array $overrides = array() ): int {
		global $wpdb;

		$defaults = array(
			'first_name' => 'Test',
			'last_name'  => 'Staff',
			'email'      => 'staff-' . wp_generate_password( 8, false, false ) . '@example.com',
			'role'       => 'staff',
			'is_active'  => 1,
		);

		$data = wp_parse_args( $overrides, $defaults );

		if ( ! isset( $data['password_hash'] ) ) {
			$data['password_hash'] = wp_hash_password( 'x' );
		}
		$data['created_at'] = $data['created_at'] ?? current_time( 'mysql' );
		$data['updated_at'] = $data['updated_at'] ?? current_time( 'mysql' );

		$formats = array();
		$insert  = array();

		foreach ( $data as $key => $value ) {
			$insert[ $key ] = $value;
			if ( 'is_active' === $key ) {
				$formats[] = '%d';
			} else {
				$formats[] = '%s';
			}
		}

		$wpdb->insert( $wpdb->prefix . 'bookings_staff', $insert, $formats );
		return (int) $wpdb->insert_id;
	}

	private function insert_service( array $overrides = array() ): int {
		global $wpdb;

		$defaults = array(
			'name'           => 'Test Service',
			'duration'       => 60,
			'price'          => 50.00,
			'deposit_type'   => 'percentage',
			'deposit_amount' => 100,
			'is_active'      => 1,
			'created_at'     => current_time( 'mysql' ),
			'updated_at'     => current_time( 'mysql' ),
		);

		$data = wp_parse_args( $overrides, $defaults );

		$wpdb->insert(
			$wpdb->prefix . 'bookings_services',
			$data,
			array( '%s', '%d', '%f', '%s', '%f', '%d', '%s', '%s' )
		);

		return (int) $wpdb->insert_id;
	}

	private function insert_customer( array $overrides = array() ): int {
		global $wpdb;

		$defaults = array(
			'email'      => 'customer-' . wp_generate_password( 8, false, false ) . '@example.com',
			'first_name' => 'Test',
			'last_name'  => 'Customer',
			'phone'      => '07700900000',
			'created_at' => current_time( 'mysql' ),
			'updated_at' => current_time( 'mysql' ),
		);

		$data = wp_parse_args( $overrides, $defaults );

		$wpdb->insert(
			$wpdb->prefix . 'bookings_customers',
			$data,
			array( '%s', '%s', '%s', '%s', '%s', '%s' )
		);

		return (int) $wpdb->insert_id;
	}

	private function insert_booking( int $customer_id, int $service_id, int $staff_id, array $overrides = array() ): int {
		global $wpdb;

		$defaults = array(
			'booking_reference' => 'BKTEST-' . wp_generate_password( 4, false, false ),
			'customer_id'       => $customer_id,
			'service_id'        => $service_id,
			'staff_id'          => $staff_id,
			'booking_date'      => gmdate( 'Y-m-d', strtotime( '+10 days' ) ),
			'start_time'        => '10:00:00',
			'end_time'          => '11:00:00',
			'duration'          => 60,
			'status'            => 'confirmed',
			'total_price'       => 50.00,
			'deposit_amount'    => 50.00,
			'deposit_paid'      => 0.00,
			'balance_due'       => 50.00,
			'full_amount_paid'  => 0,
			'payment_method'    => 'manual',
			'created_at'        => current_time( 'mysql' ),
			'updated_at'        => current_time( 'mysql' ),
			'deleted_at'        => null,
		);

		$data = wp_parse_args( $overrides, $defaults );

		$wpdb->insert(
			$wpdb->prefix . 'bookings',
			$data,
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

	private function clear_scheduled_hook( string $hook ): void {
		if ( function_exists( 'wp_clear_scheduled_hook' ) ) {
			wp_clear_scheduled_hook( $hook );
			return;
		}

		$timestamp = wp_next_scheduled( $hook );
		while ( false !== $timestamp ) {
			wp_unschedule_event( $timestamp, $hook );
			$timestamp = wp_next_scheduled( $hook );
		}
	}
}

