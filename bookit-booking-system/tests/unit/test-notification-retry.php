<?php
/**
 * Tests for notification retry, rate limiting, and exception context.
 *
 * @package    Bookit_Booking_System
 * @subpackage Tests
 */

/**
 * Test notification retry and limiter behavior.
 */
class Test_Notification_Retry extends WP_UnitTestCase {

	/**
	 * Set up each test.
	 */
	public function setUp(): void {
		parent::setUp();
		$this->ensure_queue_table_exists();
		$this->clear_test_data();
	}

	/**
	 * Tear down each test.
	 */
	public function tearDown(): void {
		$this->clear_test_data();
		parent::tearDown();
	}

	/**
	 * @covers Bookit_Notification_Dispatcher::process_email_queue_item
	 */
	public function test_rate_limiter_blocks_when_cap_reached() {
		$rate_key = 'bookit_email_rate_' . gmdate( 'YmdHi' );
		set_transient( $rate_key, 30, 90 );

		$queue_id = $this->insert_minimal_queue_row();
		$before   = time();

		Bookit_Notification_Dispatcher::process_email_queue_item( $queue_id );
		$row = Bookit_Email_Queue::get_row( $queue_id );

		$this->assertSame( 'pending', $row['status'] );
		$this->assertSame( 0, (int) $row['attempts'] );
		$this->assertGreaterThanOrEqual( $before, strtotime( (string) $row['scheduled_at'] ) );
	}

	/**
	 * @covers Bookit_Notification_Dispatcher::process_email_queue_item
	 */
	public function test_rate_limiter_increments_transient_on_send() {
		$rate_key = 'bookit_email_rate_' . gmdate( 'YmdHi' );
		delete_transient( $rate_key );

		add_filter(
			'pre_wp_mail',
			array( $this, 'force_wp_mail_success' ),
			10,
			2
		);

		$queue_id = $this->insert_minimal_queue_row();
		Bookit_Notification_Dispatcher::process_email_queue_item( $queue_id );

		remove_filter(
			'pre_wp_mail',
			array( $this, 'force_wp_mail_success' ),
			10
		);

		$this->assertSame( 1, (int) get_transient( $rate_key ) );
	}

	/**
	 * @covers Bookit_Notification_Dispatcher::process_email_queue_item
	 */
	public function test_rate_limiter_does_not_increment_attempts() {
		$rate_key = 'bookit_email_rate_' . gmdate( 'YmdHi' );
		set_transient( $rate_key, 30, 90 );

		$queue_id = $this->insert_minimal_queue_row();
		Bookit_Notification_Dispatcher::process_email_queue_item( $queue_id );
		$row = Bookit_Email_Queue::get_row( $queue_id );

		$this->assertSame( 0, (int) $row['attempts'] );
		$this->assertSame( 'pending', $row['status'] );
	}

	/**
	 * @covers Bookit_Notification_Dispatcher::process_email_queue_item
	 */
	public function test_429_does_not_increment_attempts() {
		$queue_id = $this->insert_minimal_queue_row();
		$row      = Bookit_Email_Queue::get_row( $queue_id );
		$error    = new WP_Error( 'brevo_rate_limited', 'Rate limited' );

		$this->invoke_handle_send_failure( $queue_id, $row, $error );
		$updated = Bookit_Email_Queue::get_row( $queue_id );

		$this->assertSame( 0, (int) $updated['attempts'] );
		$this->assertSame( 'pending', $updated['status'] );
	}

	/**
	 * @covers Bookit_Notification_Dispatcher::process_email_queue_item
	 */
	public function test_retry_attempt_1_uses_300s_delay() {
		$rate_key = 'bookit_email_rate_' . gmdate( 'YmdHi' );
		delete_transient( $rate_key );

		add_filter(
			'pre_wp_mail',
			array( $this, 'force_wp_mail_failure' ),
			10,
			2
		);

		$queue_id = $this->insert_minimal_queue_row();
		$start    = time();
		Bookit_Notification_Dispatcher::process_email_queue_item( $queue_id );
		remove_filter(
			'pre_wp_mail',
			array( $this, 'force_wp_mail_failure' ),
			10
		);

		$row      = Bookit_Email_Queue::get_row( $queue_id );
		$delta    = strtotime( (string) $row['scheduled_at'] ) - $start;

		$this->assertSame( 'pending', $row['status'] );
		$this->assertSame( 1, (int) $row['attempts'] );
		$this->assertTrue( abs( $delta - 300 ) <= 5 );
	}

	/**
	 * @covers Bookit_Notification_Dispatcher::process_email_queue_item
	 */
	public function test_retry_attempt_2_uses_1800s_delay() {
		$rate_key = 'bookit_email_rate_' . gmdate( 'YmdHi' );
		delete_transient( $rate_key );

		add_filter(
			'pre_wp_mail',
			array( $this, 'force_wp_mail_failure' ),
			10,
			2
		);

		$queue_id = $this->insert_minimal_queue_row();
		Bookit_Email_Queue::update_status( $queue_id, 'pending', array( 'attempts' => 1 ) );

		$start = time();
		Bookit_Notification_Dispatcher::process_email_queue_item( $queue_id );
		remove_filter(
			'pre_wp_mail',
			array( $this, 'force_wp_mail_failure' ),
			10
		);

		$row   = Bookit_Email_Queue::get_row( $queue_id );
		$delta = strtotime( (string) $row['scheduled_at'] ) - $start;

		$this->assertSame( 'pending', $row['status'] );
		$this->assertSame( 2, (int) $row['attempts'] );
		$this->assertTrue( abs( $delta - 1800 ) <= 5 );
	}

	/**
	 * @covers Bookit_Notification_Dispatcher::process_email_queue_item
	 */
	public function test_final_failure_marks_failed_and_fires_hook() {
		$queue_id = $this->insert_minimal_queue_row();
		Bookit_Email_Queue::update_status(
			$queue_id,
			'pending',
			array(
				'attempts'     => 2,
				'max_attempts' => 3,
			)
		);
		$row          = Bookit_Email_Queue::get_row( $queue_id );
		$before_count = did_action( 'bookit_email_permanently_failed' );

		$this->invoke_handle_send_failure(
			$queue_id,
			$row,
			new WP_Error( 'wp_mail_failed', 'Permanent failure' )
		);

		$updated = Bookit_Email_Queue::get_row( $queue_id );
		$this->assertSame( 'failed', $updated['status'] );
		$this->assertNotEmpty( $updated['last_error'] );
		$this->assertSame( $before_count + 1, did_action( 'bookit_email_permanently_failed' ) );
	}

	/**
	 * @covers Bookit_Notification_Exception
	 */
	public function test_notification_exception_carries_context() {
		$exception = new Bookit_Notification_Exception( 'Test', 'customer_confirmation', 42 );

		$this->assertSame( 'customer_confirmation', $exception->get_email_type() );
		$this->assertSame( 42, $exception->get_queue_id() );
	}

	/**
	 * Short-circuit wp_mail to success in tests.
	 *
	 * @return bool
	 */
	public function force_wp_mail_success(): bool {
		return true;
	}

	/**
	 * Short-circuit wp_mail to failure in tests.
	 *
	 * @return bool
	 */
	public function force_wp_mail_failure(): bool {
		return false;
	}

	/**
	 * Invoke private handle_send_failure through reflection.
	 *
	 * @param int      $queue_id Queue ID.
	 * @param array    $row Queue row.
	 * @param WP_Error $error Error.
	 * @return void
	 */
	private function invoke_handle_send_failure( int $queue_id, array $row, WP_Error $error ): void {
		$method = new ReflectionMethod( 'Bookit_Notification_Dispatcher', 'handle_send_failure' );
		$method->setAccessible( true );
		$method->invoke( null, $queue_id, $row, $error );
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
	 * Clear queue table, transient, and settings rows used by these tests.
	 *
	 * @return void
	 */
	private function clear_test_data(): void {
		global $wpdb;

		$queue_table    = $wpdb->prefix . 'bookit_email_queue';
		$settings_table = $wpdb->prefix . 'bookings_settings';

		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.DirectDatabaseQuery.DirectQuery
		$wpdb->query( "DELETE FROM {$queue_table}" );

		$wpdb->delete( $settings_table, array( 'setting_key' => 'email_rate_limit_per_minute' ), array( '%s' ) );
		delete_transient( 'bookit_email_rate_' . gmdate( 'YmdHi' ) );
	}

	/**
	 * Insert a minimal queue row and return ID.
	 *
	 * @return int
	 */
	private function insert_minimal_queue_row(): int {
		return (int) Bookit_Email_Queue::insert(
			array(
				'email_type'      => 'customer_confirmation',
				'recipient_email' => 'test@example.com',
				'recipient_name'  => 'Test User',
				'subject'         => 'Test Subject',
				'html_body'       => '<p>Hello</p>',
				'scheduled_at'    => gmdate( 'Y-m-d H:i:s', time() - 60 ),
			)
		);
	}
}
