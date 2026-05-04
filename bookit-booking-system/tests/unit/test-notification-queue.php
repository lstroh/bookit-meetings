<?php
/**
 * Tests for notification queue and dispatcher provider resolution.
 *
 * @package    Bookit_Booking_System
 * @subpackage Tests
 */

/**
 * Test queue storage, fetch, cancellation, and enqueue helpers.
 */
class Test_Notification_Queue extends WP_UnitTestCase {

	/**
	 * Set up each test.
	 *
	 * @return void
	 */
	public function setUp(): void {
		parent::setUp();
		$this->ensure_queue_table_exists();
		$this->clear_test_data();
	}

	/**
	 * Tear down each test.
	 *
	 * @return void
	 */
	public function tearDown(): void {
		$this->clear_test_data();
		parent::tearDown();
	}

	/**
	 * @covers Bookit_Email_Queue::insert
	 * @covers Bookit_Email_Queue::get_row
	 */
	public function test_insert_returns_id_and_row_is_pending() {
		$id = Bookit_Email_Queue::insert(
			array(
				'email_type'      => 'customer_confirmation',
				'recipient_email' => 'test@example.com',
				'html_body'       => '<p>Hello</p>',
			)
		);

		$this->assertIsInt( $id );
		$this->assertGreaterThan( 0, $id );

		$row = Bookit_Email_Queue::get_row( $id );
		$this->assertIsArray( $row );
		$this->assertSame( 'pending', $row['status'] );
	}

	/**
	 * @covers Bookit_Email_Queue::insert
	 * @covers Bookit_Email_Queue::get_row
	 */
	public function test_insert_stores_null_booking_id_when_zero() {
		$id = Bookit_Email_Queue::insert(
			array(
				'booking_id'      => 0,
				'email_type'      => 'customer_confirmation',
				'recipient_email' => 'test@example.com',
				'html_body'       => '<p>Hello</p>',
			)
		);

		$row = Bookit_Email_Queue::get_row( (int) $id );
		$this->assertNull( $row['booking_id'] );
	}

	/**
	 * @covers Bookit_Email_Queue::update_status
	 * @covers Bookit_Email_Queue::get_row
	 */
	public function test_update_status_changes_status() {
		$id = $this->insert_minimal_queue_row();

		Bookit_Email_Queue::update_status(
			$id,
			'sent',
			array(
				'sent_at' => gmdate( 'Y-m-d H:i:s' ),
			)
		);

		$row = Bookit_Email_Queue::get_row( $id );
		$this->assertSame( 'sent', $row['status'] );
		$this->assertNotEmpty( $row['sent_at'] );
	}

	/**
	 * @covers Bookit_Email_Queue::fetch_pending
	 */
	public function test_fetch_pending_returns_due_rows() {
		$id = Bookit_Email_Queue::insert(
			array(
				'email_type'      => 'customer_confirmation',
				'recipient_email' => 'test@example.com',
				'html_body'       => '<p>Hello</p>',
				'scheduled_at'    => gmdate( 'Y-m-d H:i:s', time() - 120 ),
			)
		);

		$rows = Bookit_Email_Queue::fetch_pending( 10 );
		$ids  = wp_list_pluck( $rows, 'id' );

		$this->assertContains( (string) $id, $ids );
	}

	/**
	 * @covers Bookit_Email_Queue::fetch_pending
	 */
	public function test_fetch_pending_excludes_future_rows() {
		$id = Bookit_Email_Queue::insert(
			array(
				'email_type'      => 'customer_confirmation',
				'recipient_email' => 'test@example.com',
				'html_body'       => '<p>Hello</p>',
				'scheduled_at'    => gmdate( 'Y-m-d H:i:s', time() + HOUR_IN_SECONDS ),
			)
		);

		$rows = Bookit_Email_Queue::fetch_pending( 10 );
		$ids  = wp_list_pluck( $rows, 'id' );

		$this->assertNotContains( (string) $id, $ids );
	}

	/**
	 * @covers Bookit_Email_Queue::fetch_pending
	 * @covers Bookit_Email_Queue::update_status
	 */
	public function test_fetch_pending_excludes_non_pending_status() {
		$id = $this->insert_minimal_queue_row();
		Bookit_Email_Queue::update_status( $id, 'sent' );

		$rows = Bookit_Email_Queue::fetch_pending( 10 );
		$ids  = wp_list_pluck( $rows, 'id' );

		$this->assertNotContains( (string) $id, $ids );
	}

	/**
	 * @covers Bookit_Email_Queue::cancel_for_booking
	 * @covers Bookit_Email_Queue::get_row
	 */
	public function test_cancel_for_booking_cancels_pending_rows() {
		$booking_id = 12345;
		$id_1       = $this->insert_queue_row_for_booking( $booking_id, 'pending' );
		$id_2       = $this->insert_queue_row_for_booking( $booking_id, 'pending' );

		Bookit_Email_Queue::cancel_for_booking( $booking_id );

		$row_1 = Bookit_Email_Queue::get_row( $id_1 );
		$row_2 = Bookit_Email_Queue::get_row( $id_2 );

		$this->assertSame( 'cancelled', $row_1['status'] );
		$this->assertSame( 'cancelled', $row_2['status'] );
	}

	/**
	 * @covers Bookit_Email_Queue::cancel_for_booking
	 * @covers Bookit_Email_Queue::get_row
	 */
	public function test_cancel_for_booking_does_not_cancel_sent_rows() {
		$booking_id = 23456;
		$pending_id = $this->insert_queue_row_for_booking( $booking_id, 'pending' );
		$sent_id    = $this->insert_queue_row_for_booking( $booking_id, 'sent' );

		Bookit_Email_Queue::cancel_for_booking( $booking_id );

		$pending_row = Bookit_Email_Queue::get_row( $pending_id );
		$sent_row    = Bookit_Email_Queue::get_row( $sent_id );

		$this->assertSame( 'cancelled', $pending_row['status'] );
		$this->assertSame( 'sent', $sent_row['status'] );
	}

	/**
	 * @covers bookit_enqueue_email
	 * @covers Bookit_Email_Queue::get_row
	 */
	public function test_bookit_enqueue_email_inserts_pending_row() {
		$queue_id = bookit_enqueue_email(
			'customer_confirmation',
			array(
				'email' => 'test@example.com',
				'name'  => 'Test',
			),
			'Subject',
			'<p>Body</p>'
		);

		$this->assertIsInt( $queue_id );
		$this->assertGreaterThan( 0, $queue_id );

		$row = Bookit_Email_Queue::get_row( $queue_id );
		$this->assertIsArray( $row );
		$this->assertSame( 'pending', $row['status'] );
	}

	/**
	 * @covers Bookit_Notification_Dispatcher::resolve_email_provider
	 */
	public function test_resolve_provider_returns_fallback_when_no_brevo_key() {
		$this->insert_setting( 'email_provider', 'brevo' );
		$this->insert_setting( 'brevo_api_key', '' );

		$provider = Bookit_Notification_Dispatcher::resolve_email_provider();
		$this->assertInstanceOf( 'Bookit_WP_Mail_Fallback_Provider', $provider );
	}

	/**
	 * @covers Bookit_Notification_Dispatcher::resolve_email_provider
	 */
	public function test_resolve_provider_returns_brevo_when_configured() {
		$this->insert_setting( 'email_provider', 'brevo' );
		$this->insert_setting( 'brevo_api_key', 'test_brevo_api_key' );

		$provider = Bookit_Notification_Dispatcher::resolve_email_provider();
		$this->assertInstanceOf( 'Bookit_Brevo_Email_Provider', $provider );
	}

	/**
	 * @covers Bookit_Email_Queue::rescue_stuck_processing
	 */
	public function test_rescue_stuck_processing_resets_stale_items() {
		global $wpdb;

		$id = $this->insert_minimal_queue_row();

		$wpdb->update(
			$wpdb->prefix . 'bookit_email_queue',
			array(
				'status'     => 'processing',
				'updated_at' => gmdate( 'Y-m-d H:i:s', time() - 600 ),
			),
			array( 'id' => $id ),
			array( '%s', '%s' ),
			array( '%d' )
		);

		Bookit_Email_Queue::rescue_stuck_processing( 5 );

		$row = Bookit_Email_Queue::get_row( $id );
		$this->assertSame( 'pending', $row['status'] );
	}

	/**
	 * @covers Bookit_Email_Queue::rescue_stuck_processing
	 */
	public function test_rescue_stuck_processing_ignores_recent_processing_items() {
		global $wpdb;

		$id = $this->insert_minimal_queue_row();

		$now = gmdate( 'Y-m-d H:i:s' );
		$wpdb->update(
			$wpdb->prefix . 'bookit_email_queue',
			array(
				'status'     => 'processing',
				'updated_at' => $now,
			),
			array( 'id' => $id ),
			array( '%s', '%s' ),
			array( '%d' )
		);

		Bookit_Email_Queue::rescue_stuck_processing( 5 );

		$row = Bookit_Email_Queue::get_row( $id );
		$this->assertSame( 'processing', $row['status'] );
	}

	/**
	 * @covers Bookit_Email_Queue::rescue_stuck_processing
	 */
	public function test_rescue_stuck_processing_returns_count() {
		global $wpdb;

		$id_1 = (int) Bookit_Email_Queue::insert(
			array(
				'email_type'      => 'customer_confirmation',
				'recipient_email' => 'stale1@example.com',
				'html_body'       => '<p>A</p>',
				'scheduled_at'    => gmdate( 'Y-m-d H:i:s', time() - 60 ),
			)
		);
		$id_2 = (int) Bookit_Email_Queue::insert(
			array(
				'email_type'      => 'customer_confirmation',
				'recipient_email' => 'stale2@example.com',
				'html_body'       => '<p>B</p>',
				'scheduled_at'    => gmdate( 'Y-m-d H:i:s', time() - 60 ),
			)
		);

		$stale = gmdate( 'Y-m-d H:i:s', time() - 600 );
		$wpdb->update(
			$wpdb->prefix . 'bookit_email_queue',
			array(
				'status'     => 'processing',
				'updated_at' => $stale,
			),
			array( 'id' => $id_1 ),
			array( '%s', '%s' ),
			array( '%d' )
		);
		$wpdb->update(
			$wpdb->prefix . 'bookit_email_queue',
			array(
				'status'     => 'processing',
				'updated_at' => $stale,
			),
			array( 'id' => $id_2 ),
			array( '%s', '%s' ),
			array( '%d' )
		);

		$count = Bookit_Email_Queue::rescue_stuck_processing( 5 );
		$this->assertSame( 2, $count );
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
	 * Clear queue table and inserted settings rows.
	 *
	 * @return void
	 */
	private function clear_test_data(): void {
		global $wpdb;

		$queue_table    = $wpdb->prefix . 'bookit_email_queue';
		$settings_table = $wpdb->prefix . 'bookings_settings';

		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.DirectDatabaseQuery.DirectQuery
		$wpdb->query( "DELETE FROM {$queue_table}" );

		$wpdb->delete( $settings_table, array( 'setting_key' => 'email_provider' ), array( '%s' ) );
		$wpdb->delete( $settings_table, array( 'setting_key' => 'brevo_api_key' ), array( '%s' ) );
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
				'html_body'       => '<p>Hello</p>',
				'scheduled_at'    => gmdate( 'Y-m-d H:i:s', time() - 60 ),
			)
		);
	}

	/**
	 * Insert queue row for booking with specified status.
	 *
	 * @param int    $booking_id Booking ID.
	 * @param string $status Row status.
	 * @return int
	 */
	private function insert_queue_row_for_booking( int $booking_id, string $status ): int {
		$id = (int) Bookit_Email_Queue::insert(
			array(
				'booking_id'      => $booking_id,
				'email_type'      => 'customer_confirmation',
				'recipient_email' => 'test@example.com',
				'html_body'       => '<p>Hello</p>',
				'scheduled_at'    => gmdate( 'Y-m-d H:i:s', time() - 60 ),
			)
		);

		if ( 'pending' !== $status ) {
			Bookit_Email_Queue::update_status( $id, $status );
		}

		return $id;
	}

	/**
	 * Upsert a single setting row using direct wpdb writes.
	 *
	 * @param string $key Setting key.
	 * @param string $value Setting value.
	 * @return void
	 */
	private function insert_setting( string $key, string $value ): void {
		global $wpdb;

		$settings_table = $wpdb->prefix . 'bookings_settings';

		$wpdb->delete( $settings_table, array( 'setting_key' => $key ), array( '%s' ) );
		$wpdb->insert(
			$settings_table,
			array(
				'setting_key'   => $key,
				'setting_value' => $value,
				'created_at'    => current_time( 'mysql' ),
				'updated_at'    => current_time( 'mysql' ),
			),
			array( '%s', '%s', '%s', '%s' )
		);
	}
}
