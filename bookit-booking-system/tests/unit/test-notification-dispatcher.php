<?php
/**
 * Tests for notification dispatcher enqueue and email sender queue wiring.
 *
 * @package    Bookit_Booking_System
 * @subpackage Tests
 */

/**
 * Test enqueue behavior through Booking_System_Email_Sender and queue processing outcomes.
 */
class Test_Notification_Dispatcher extends WP_UnitTestCase {

	/**
	 * Whether wp_mail was attempted (via pre_wp_mail short-circuit).
	 *
	 * @var int
	 */
	private $pre_wp_mail_calls = 0;

	/**
	 * Set up each test.
	 */
	public function setUp(): void {
		parent::setUp();
		$this->ensure_queue_table_exists();
		$this->clear_queue_table();
		$this->pre_wp_mail_calls = 0;
	}

	/**
	 * Tear down each test.
	 */
	public function tearDown(): void {
		$this->clear_queue_table();
		remove_all_filters( 'pre_wp_mail' );
		remove_all_filters( 'bookit_send_email' );
		parent::tearDown();
	}

	/**
	 * @covers Booking_System_Email_Sender::send_customer_confirmation
	 * @covers bookit_enqueue_email
	 * @covers Bookit_Email_Queue::insert
	 */
	public function test_send_customer_confirmation_enqueues_pending_row() {
		$email_sender = new Booking_System_Email_Sender();
		$booking      = $this->build_minimal_booking();

		$result = $email_sender->send_customer_confirmation( $booking );
		$this->assertTrue( $result );

		$row = $this->get_latest_queue_row_by_type( 'customer_confirmation' );
		$this->assertIsArray( $row );
		$this->assertSame( 'customer_confirmation', $row['email_type'] );
		$this->assertSame( 'pending', $row['status'] );
	}

	/**
	 * Pay-on-arrival flow fires bookit_after_booking_created before customer email; staff rows use staff_new_booking_immediate.
	 *
	 * @covers Bookit_Staff_Notifier::on_booking_created
	 */
	public function test_poa_booking_created_action_enqueues_staff_notification() {
		global $wpdb;

		bookit_test_truncate_tables(
			array(
				'bookit_email_queue',
				'bookings_audit_log',
				'bookings',
				'bookings_staff_services',
				'bookings_services',
				'bookings_staff',
				'bookings_customers',
			)
		);

		$staff_id = $this->insert_staff_row(
			array(
				'email'     => 'poa-staff-notifier@example.com',
				'is_active' => 1,
			)
		);
		$service_id  = $this->insert_service_row();
		$customer_id = $this->insert_customer_row();
		$booking_id  = $this->insert_booking_row(
			$customer_id,
			$service_id,
			$staff_id,
			array(
				'payment_method' => 'pay_on_arrival',
			)
		);

		$booking_data = array(
			'payment_method' => 'pay_on_arrival',
		);

		do_action( 'bookit_after_booking_created', $booking_id, $booking_data );

		$queue_count = (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$wpdb->prefix}bookit_email_queue WHERE email_type = %s AND booking_id = %d",
				'staff_new_booking_immediate',
				$booking_id
			)
		);
		$this->assertSame( 1, $queue_count );

		$row = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$wpdb->prefix}bookit_email_queue WHERE email_type = %s AND booking_id = %d ORDER BY id ASC LIMIT 1",
				'staff_new_booking_immediate',
				$booking_id
			),
			ARRAY_A
		);

		$this->assertIsArray( $row );
		$this->assertSame( 'staff_new_booking_immediate', $row['email_type'] );
		$this->assertSame( (string) $booking_id, (string) $row['booking_id'] );
		$this->assertSame( 'pending', $row['status'] );
	}

	/**
	 * @covers Booking_System_Email_Sender::send_customer_confirmation
	 */
	public function test_send_customer_confirmation_returns_true_on_success() {
		$email_sender = new Booking_System_Email_Sender();
		$booking      = $this->build_minimal_booking();

		$result = $email_sender->send_customer_confirmation( $booking );

		$this->assertTrue( $result );
		$this->assertFalse( is_wp_error( $result ) );
	}

	/**
	 * @covers Booking_System_Email_Sender::send_customer_confirmation
	 */
	public function test_send_customer_confirmation_does_not_call_wp_mail() {
		$email_sender = new Booking_System_Email_Sender();
		$booking      = $this->build_minimal_booking();

		add_filter(
			'pre_wp_mail',
			function () {
				$this->pre_wp_mail_calls++;
				return null;
			},
			10,
			2
		);

		$email_sender->send_customer_confirmation( $booking );

		$this->assertSame( 0, $this->pre_wp_mail_calls );
	}

	/**
	 * @covers Booking_System_Email_Sender::generate_customer_email
	 */
	public function test_confirmation_email_contains_cancel_link() {
		$email_sender = new Booking_System_Email_Sender();
		$booking      = $this->build_minimal_booking();
		$booking['magic_link_token'] = 'email-test-magic-token';

		$html = $email_sender->generate_customer_email( $booking );

		$this->assertStringContainsString( 'bookit-cancel', $html );
		$this->assertStringContainsString( 'bookit-reschedule', $html );
	}

	/**
	 * @covers Booking_System_Email_Sender::generate_customer_email
	 */
	public function test_customer_email_includes_add_to_calendar_link() {
		$email_sender = new Booking_System_Email_Sender();
		$booking      = $this->build_minimal_booking();
		$booking['magic_link_token'] = 'ical-email-test-token';

		$html = $email_sender->generate_customer_email( $booking );

		$has_ical_path = ( str_contains( $html, 'wizard/ical' ) || str_contains( $html, 'wizard%2Fical' ) );
		$this->assertTrue( $has_ical_path, 'Expected bookit/v1/wizard/ical in customer email (pretty or rest_route form).' );
		$this->assertStringContainsString( 'booking_id=123', $html );
		$this->assertStringContainsString( 'ical-email-test-token', $html );
	}

	/**
	 * @covers Booking_System_Email_Sender::generate_customer_email
	 */
	public function test_confirmation_email_contains_booking_reference(): void {
		$email_sender = new Booking_System_Email_Sender();
		$booking      = $this->build_minimal_booking();

		// Add a booking reference in the format produced by
		// Bookit_Reference_Generator::generate() — BK + YYMM + hyphen + 4 chars
		$booking['booking_reference'] = 'BK2504-TEST';

		$html = $email_sender->generate_customer_email( $booking );

		$this->assertStringContainsString( 'BK2504-TEST', $html );
		$this->assertStringContainsString( 'Booking ref', $html );
	}

	/**
	 * @covers Booking_System_Email_Sender::generate_customer_email
	 */
	public function test_confirmation_email_omits_ref_row_when_reference_empty(): void {
		$email_sender = new Booking_System_Email_Sender();
		$booking      = $this->build_minimal_booking();

		// No booking_reference key — should not render an empty row
		unset( $booking['booking_reference'] );

		$html = $email_sender->generate_customer_email( $booking );

		$this->assertStringNotContainsString( 'Booking ref', $html );
	}

	/**
	 * @covers Bookit_Notification_Dispatcher::process_email_queue_item
	 * @covers Bookit_WP_Mail_Fallback_Provider::send
	 */
	public function test_process_item_marks_sent_on_provider_success() {
		$queue_id = (int) Bookit_Email_Queue::insert(
			array(
				'booking_id'      => 123,
				'email_type'      => 'customer_confirmation',
				'recipient_email' => 'test@example.com',
				'recipient_name'  => 'Test User',
				'subject'         => 'Subject',
				'html_body'       => '<p>Body</p>',
				'scheduled_at'    => gmdate( 'Y-m-d H:i:s', time() - 60 ),
			)
		);

		add_filter( 'pre_wp_mail', fn() => true, 10, 2 );

		Bookit_Notification_Dispatcher::process_email_queue_item( $queue_id );
		$row = Bookit_Email_Queue::get_row( $queue_id );

		$this->assertIsArray( $row );
		$this->assertSame( 'sent', $row['status'] );
	}

	/**
	 * @covers Bookit_Notification_Dispatcher::process_email_queue_item
	 * @covers Bookit_WP_Mail_Fallback_Provider::send
	 */
	public function test_process_item_marks_failed_after_max_attempts() {
		$queue_id = (int) Bookit_Email_Queue::insert(
			array(
				'booking_id'      => 123,
				'email_type'      => 'customer_confirmation',
				'recipient_email' => 'test@example.com',
				'recipient_name'  => 'Test User',
				'subject'         => 'Subject',
				'html_body'       => '<p>Body</p>',
				'max_attempts'    => 1,
				'scheduled_at'    => gmdate( 'Y-m-d H:i:s', time() - 60 ),
			)
		);

		add_filter( 'pre_wp_mail', fn() => false, 10, 2 );

		Bookit_Notification_Dispatcher::process_email_queue_item( $queue_id );
		$row = Bookit_Email_Queue::get_row( $queue_id );

		$this->assertIsArray( $row );
		$this->assertSame( 'failed', $row['status'] );
	}

	/**
	 * @covers Booking_System_Email_Sender::send_customer_confirmation
	 * @covers bookit_enqueue_email
	 */
	public function test_bookit_send_email_filter_bypasses_queue() {
		$email_sender = new Booking_System_Email_Sender();
		$booking      = $this->build_minimal_booking();

		add_filter( 'bookit_send_email', fn() => false );

		$result = $email_sender->send_customer_confirmation( $booking );
		$this->assertTrue( $result );

		$row = $this->get_latest_queue_row_by_type( 'customer_confirmation' );
		$this->assertNull( $row );
	}

	/**
	 * Build a minimal booking array that can render both email templates.
	 *
	 * @return array<string,mixed>
	 */
	private function build_minimal_booking(): array {
		return array(
			'id'                 => 123,
			'customer_email'     => 'customer@test.com',
			'customer_first_name'=> 'Test',
			'customer_last_name' => 'Customer',
			'customer_name'      => 'Test Customer',
			'customer_phone'     => '07700900111',
			'service_name'       => 'Example Service',
			'booking_date'       => gmdate( 'Y-m-d', strtotime( '+7 days' ) ),
			'start_time'         => '10:00:00',
			'staff_name'         => 'Example Staff',
			'total_price'        => 50.00,
			'deposit_paid'       => 50.00,
			'balance_due'        => 0.00,
			'payment_method'     => 'stripe',
			'special_requests'   => '',
		);
	}

	/**
	 * Insert a staff row for Staff Notifier integration tests.
	 *
	 * @param array<string,mixed> $overrides Column overrides.
	 * @return int Staff ID.
	 */
	private function insert_staff_row( array $overrides = array() ): int {
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

	/**
	 * Insert a service row.
	 *
	 * @param array<string,mixed> $overrides Column overrides.
	 * @return int Service ID.
	 */
	private function insert_service_row( array $overrides = array() ): int {
		global $wpdb;

		$defaults = array(
			'name'           => 'POA Test Service',
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

	/**
	 * Insert a customer row.
	 *
	 * @param array<string,mixed> $overrides Column overrides.
	 * @return int Customer ID.
	 */
	private function insert_customer_row( array $overrides = array() ): int {
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

	/**
	 * Insert a booking row (minimal schema for Staff Notifier get_full_booking join).
	 *
	 * @param int                   $customer_id Customer ID.
	 * @param int                   $service_id  Service ID.
	 * @param int                   $staff_id    Staff ID.
	 * @param array<string,mixed>   $overrides   Column overrides.
	 * @return int Booking ID.
	 */
	private function insert_booking_row( int $customer_id, int $service_id, int $staff_id, array $overrides = array() ): int {
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
			'status'            => 'pending_payment',
			'total_price'       => 50.00,
			'deposit_amount'    => 0.00,
			'deposit_paid'      => 0.00,
			'balance_due'       => 50.00,
			'full_amount_paid'  => 0,
			'payment_method'    => 'pay_on_arrival',
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

	/**
	 * Fetch the most recently inserted queue row for an email type.
	 *
	 * @param string $email_type Email type.
	 * @return array<string,mixed>|null
	 */
	private function get_latest_queue_row_by_type( string $email_type ): ?array {
		global $wpdb;

		$table = $wpdb->prefix . 'bookit_email_queue';
		$row   = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$table} WHERE email_type = %s ORDER BY id DESC LIMIT 1",
				$email_type
			),
			ARRAY_A
		);

		return $row ? $row : null;
	}

	/**
	 * Ensure queue table exists for unit tests.
	 *
	 * @return void
	 */
	private function ensure_queue_table_exists(): void {
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
	 * Clear queue rows created by these tests.
	 *
	 * @return void
	 */
	private function clear_queue_table(): void {
		global $wpdb;

		$queue_table = $wpdb->prefix . 'bookit_email_queue';

		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.DirectDatabaseQuery.DirectQuery
		$wpdb->query( "DELETE FROM {$queue_table}" );
	}
}

