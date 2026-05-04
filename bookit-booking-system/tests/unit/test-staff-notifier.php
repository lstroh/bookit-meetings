<?php
/**
 * Tests for Bookit_Staff_Notifier.
 *
 * @package Bookit_Booking_System
 * @subpackage Tests
 */

class Test_Staff_Notifier extends WP_UnitTestCase {

	/**
	 * Prevent wp_mail side effects.
	 *
	 * @var callable|null
	 */
	private $pre_wp_mail_cb = null;

	public function setUp(): void {
		parent::setUp();

		bookit_test_truncate_tables(
			array(
				'bookit_email_queue',
				'bookit_notification_digest_queue',
				'bookings_audit_log',
				'bookings',
				'bookings_staff_services',
				'bookings_services',
				'bookings_staff',
				'bookings_customers',
			)
		);

		$this->pre_wp_mail_cb = static fn() => true;
		add_filter( 'pre_wp_mail', $this->pre_wp_mail_cb );

		if ( class_exists( 'Bookit_Staff_Notifier' ) ) {
			Bookit_Staff_Notifier::init();
		}
	}

	public function tearDown(): void {
		if ( $this->pre_wp_mail_cb ) {
			remove_filter( 'pre_wp_mail', $this->pre_wp_mail_cb );
		}

		bookit_test_truncate_tables(
			array(
				'bookit_email_queue',
				'bookit_notification_digest_queue',
				'bookings_audit_log',
				'bookings',
				'bookings_staff_services',
				'bookings_services',
				'bookings_staff',
				'bookings_customers',
			)
		);

		parent::tearDown();
	}

	public function test_new_booking_enqueues_email_for_assigned_staff(): void {
		global $wpdb;

		$staff_id    = $this->insert_staff( array( 'email' => 'assigned@example.com' ) );
		$service_id  = $this->insert_service();
		$customer_id = $this->insert_customer();
		$booking_id  = $this->insert_booking( $customer_id, $service_id, $staff_id );

		do_action( 'bookit_after_booking_created', $booking_id, array() );

		$row = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT email_type, recipient_email FROM {$wpdb->prefix}bookit_email_queue WHERE booking_id = %d ORDER BY id ASC LIMIT 1",
				$booking_id
			),
			ARRAY_A
		);

		$this->assertIsArray( $row );
		$this->assertSame( 'staff_new_booking_immediate', (string) $row['email_type'] );
		$this->assertSame( 'assigned@example.com', (string) $row['recipient_email'] );
	}

	public function test_new_booking_enqueues_email_for_all_admin_staff(): void {
		global $wpdb;

		$assigned_id  = $this->insert_staff( array( 'email' => 'assigned@example.com' ) );
		$admin_one_id = $this->insert_staff(
			array(
				'email' => 'admin1@example.com',
				'role'  => 'admin',
			)
		);
		$admin_two_id = $this->insert_staff(
			array(
				'email' => 'admin2@example.com',
				'role'  => 'admin',
			)
		);

		$service_id  = $this->insert_service();
		$customer_id = $this->insert_customer();
		$booking_id  = $this->insert_booking( $customer_id, $service_id, $assigned_id );

		do_action( 'bookit_after_booking_created', $booking_id, array() );

		$emails = $wpdb->get_col(
			$wpdb->prepare(
				"SELECT recipient_email
				FROM {$wpdb->prefix}bookit_email_queue
				WHERE booking_id = %d
					AND email_type = %s
				ORDER BY id ASC",
				$booking_id,
				'staff_new_booking_immediate'
			)
		);

		$this->assertIsArray( $emails );

		// Only admin staff should be asserted here (assigned staff tested separately).
		$this->assertContains( 'admin1@example.com', $emails );
		$this->assertContains( 'admin2@example.com', $emails );
		$this->assertSame( 3, count( $emails ) );

		$this->assertSame( $admin_one_id, (int) $wpdb->get_var( $wpdb->prepare( "SELECT id FROM {$wpdb->prefix}bookings_staff WHERE email = %s", 'admin1@example.com' ) ) );
		$this->assertSame( $admin_two_id, (int) $wpdb->get_var( $wpdb->prepare( "SELECT id FROM {$wpdb->prefix}bookings_staff WHERE email = %s", 'admin2@example.com' ) ) );
	}

	public function test_new_booking_deduplicates_when_admin_is_assignee(): void {
		global $wpdb;

		$staff_id    = $this->insert_staff(
			array(
				'email' => 'admin-assignee@example.com',
				'role'  => 'admin',
			)
		);
		$service_id  = $this->insert_service();
		$customer_id = $this->insert_customer();
		$booking_id  = $this->insert_booking( $customer_id, $service_id, $staff_id );

		do_action( 'bookit_after_booking_created', $booking_id, array() );

		$count = (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$wpdb->prefix}bookit_email_queue WHERE booking_id = %d AND recipient_email = %s",
				$booking_id,
				'admin-assignee@example.com'
			)
		);

		$this->assertSame( 1, $count );
	}

	public function test_reschedule_enqueues_via_reschedule_preference(): void {
		global $wpdb;

		$staff_id    = $this->insert_staff( array( 'email' => 'assigned@example.com' ) );
		$service_id  = $this->insert_service();
		$customer_id = $this->insert_customer();
		$booking_id  = $this->insert_booking( $customer_id, $service_id, $staff_id );

		do_action( 'bookit_booking_rescheduled', $booking_id, array() );

		$type = (string) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT email_type FROM {$wpdb->prefix}bookit_email_queue WHERE booking_id = %d ORDER BY id DESC LIMIT 1",
				$booking_id
			)
		);

		$this->assertSame( 'staff_reschedule_immediate', $type );
	}

	public function test_cancellation_enqueues_via_cancellation_preference(): void {
		global $wpdb;

		$staff_id    = $this->insert_staff( array( 'email' => 'assigned@example.com' ) );
		$service_id  = $this->insert_service();
		$customer_id = $this->insert_customer();
		$booking_id  = $this->insert_booking( $customer_id, $service_id, $staff_id );

		do_action( 'bookit_after_booking_cancelled', $booking_id, array() );

		$type = (string) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT email_type FROM {$wpdb->prefix}bookit_email_queue WHERE booking_id = %d ORDER BY id DESC LIMIT 1",
				$booking_id
			)
		);

		$this->assertSame( 'staff_cancellation_immediate', $type );
	}

	public function test_reassignment_notifies_new_assignee_via_new_booking_preference(): void {
		global $wpdb;

		$old_id      = $this->insert_staff( array( 'email' => 'old@example.com' ) );
		$new_id      = $this->insert_staff( array( 'email' => 'new@example.com' ) );
		$service_id  = $this->insert_service();
		$customer_id = $this->insert_customer();
		$booking_id  = $this->insert_booking( $customer_id, $service_id, $new_id );

		do_action( 'bookit_booking_reassigned', $booking_id, $old_id, $new_id, array() );

		$count = (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$wpdb->prefix}bookit_email_queue
				WHERE booking_id = %d AND recipient_email = %s AND email_type = %s",
				$booking_id,
				'new@example.com',
				'staff_reassigned_to_immediate'
			)
		);

		$this->assertSame( 1, $count );
	}

	public function test_reassignment_notifies_old_assignee_via_cancellation_preference(): void {
		global $wpdb;

		$old_id      = $this->insert_staff( array( 'email' => 'old@example.com' ) );
		$new_id      = $this->insert_staff( array( 'email' => 'new@example.com' ) );
		$service_id  = $this->insert_service();
		$customer_id = $this->insert_customer();
		$booking_id  = $this->insert_booking( $customer_id, $service_id, $new_id );

		do_action( 'bookit_booking_reassigned', $booking_id, $old_id, $new_id, array() );

		$count = (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$wpdb->prefix}bookit_email_queue
				WHERE booking_id = %d AND recipient_email = %s AND email_type = %s",
				$booking_id,
				'old@example.com',
				'staff_reassigned_away_immediate'
			)
		);

		$this->assertSame( 1, $count );
	}

	public function test_staff_with_no_email_is_skipped_silently(): void {
		global $wpdb;

		$staff_id    = $this->insert_staff( array( 'email' => '' ) );
		$service_id  = $this->insert_service();
		$customer_id = $this->insert_customer();
		$booking_id  = $this->insert_booking( $customer_id, $service_id, $staff_id );

		do_action( 'bookit_after_booking_created', $booking_id, array() );

		$email_count = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->prefix}bookit_email_queue" );
		$this->assertSame( 0, $email_count );

		$audit_count = (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$wpdb->prefix}bookings_audit_log WHERE action = %s",
				'staff_notification.skipped_no_email'
			)
		);
		$this->assertSame( 1, $audit_count );
	}

	public function test_inactive_staff_not_notified(): void {
		global $wpdb;

		$staff_id    = $this->insert_staff(
			array(
				'email'     => 'inactive@example.com',
				'is_active' => 0,
			)
		);
		$service_id  = $this->insert_service();
		$customer_id = $this->insert_customer();
		$booking_id  = $this->insert_booking( $customer_id, $service_id, $staff_id );

		do_action( 'bookit_after_booking_created', $booking_id, array() );

		$email_count = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->prefix}bookit_email_queue" );
		$this->assertSame( 0, $email_count );
	}

	public function test_digest_preference_inserts_into_digest_queue_not_email_queue(): void {
		global $wpdb;

		$staff_id    = $this->insert_staff(
			array(
				'email'                    => 'digest@example.com',
				'notification_preferences' => wp_json_encode( array( 'new_booking' => 'daily' ) ),
			)
		);
		$service_id  = $this->insert_service();
		$customer_id = $this->insert_customer();
		$booking_id  = $this->insert_booking( $customer_id, $service_id, $staff_id );

		do_action( 'bookit_after_booking_created', $booking_id, array() );

		$email_count = (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$wpdb->prefix}bookit_email_queue WHERE recipient_email = %s",
				'digest@example.com'
			)
		);
		$this->assertSame( 0, $email_count );

		$row = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT event_type, processed, booking_id FROM {$wpdb->prefix}bookit_notification_digest_queue WHERE staff_id = %d ORDER BY id ASC LIMIT 1",
				$staff_id
			),
			ARRAY_A
		);

		$this->assertIsArray( $row );
		$this->assertSame( 'new_booking', (string) $row['event_type'] );
		$this->assertSame( 0, (int) $row['processed'] );
		$this->assertSame( $booking_id, (int) $row['booking_id'] );
	}

	public function test_weekly_preference_inserts_into_digest_queue(): void {
		global $wpdb;

		$staff_id    = $this->insert_staff(
			array(
				'email'                    => 'weekly@example.com',
				'notification_preferences' => wp_json_encode( array( 'new_booking' => 'weekly' ) ),
			)
		);
		$service_id  = $this->insert_service();
		$customer_id = $this->insert_customer();
		$booking_id  = $this->insert_booking( $customer_id, $service_id, $staff_id );

		do_action( 'bookit_after_booking_created', $booking_id, array() );

		$count = (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$wpdb->prefix}bookit_notification_digest_queue WHERE staff_id = %d AND event_type = %s",
				$staff_id,
				'new_booking'
			)
		);

		$this->assertSame( 1, $count );
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

		// Required columns.
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
}

