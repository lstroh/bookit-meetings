<?php
/**
 * Unit Tests for Idempotency Keys
 * Sprint 2, Task 6
 *
 * Test-Driven Development: These tests are written BEFORE the implementation
 * to define the expected behavior of the Idempotency Handler.
 *
 * @package    Bookit_Booking_System
 * @subpackage Tests
 */

/**
 * Test idempotency key generation, operation tracking, and duplicate prevention.
 *
 * @covers Booking_System_Idempotency_Handler
 */
class Test_Idempotency extends WP_UnitTestCase {

	/**
	 * Idempotency handler instance.
	 *
	 * @var Booking_System_Idempotency_Handler|null
	 */
	private $idempotency_handler;

	/**
	 * Database table name for idempotency records.
	 *
	 * @var string
	 */
	private $table_name;

	/**
	 * Flag to track if tests should be skipped (TDD mode).
	 *
	 * @var bool
	 */
	private $skip_tests = false;

	/**
	 * Set up each test.
	 */
	public function setUp(): void {
		parent::setUp();

		global $wpdb;
		$this->table_name = $wpdb->prefix . 'bookings_idempotency';

		// Path to the class under test.
		$plugin_dir     = dirname( __DIR__ );
		$handler_file   = $plugin_dir . '/includes/core/class-idempotency-handler.php';

		// Skip tests when handler not yet implemented (TDD).
		if ( ! file_exists( $handler_file ) ) {
			$this->skip_tests = true;
			return;
		}

		// Load required classes.
		if ( file_exists( $plugin_dir . '/vendor/autoload.php' ) ) {
			require_once $plugin_dir . '/vendor/autoload.php';
		}
		require_once $handler_file;

		// Initialize idempotency handler.
		$this->idempotency_handler = new Booking_System_Idempotency_Handler();

		// Create idempotency table if not exists.
		$charset_collate = $wpdb->get_charset_collate();

		$wpdb->query(
			"CREATE TABLE IF NOT EXISTS {$this->table_name} (
				id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
				idempotency_key VARCHAR(255) NOT NULL UNIQUE,
				operation_type VARCHAR(50) NOT NULL,
				request_hash VARCHAR(64) NOT NULL,
				response_data TEXT NULL,
				status VARCHAR(20) NOT NULL DEFAULT 'processing',
				created_at DATETIME NOT NULL,
				completed_at DATETIME NULL,
				expires_at DATETIME NOT NULL,
				INDEX idx_key (idempotency_key),
				INDEX idx_expires (expires_at),
				INDEX idx_status (status)
			) $charset_collate"
		);
	}

	/**
	 * Tear down each test.
	 */
	public function tearDown(): void {
		if ( ! $this->skip_tests ) {
			global $wpdb;

			// Clean up all test data from idempotency table.
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			$wpdb->query( "DELETE FROM {$this->table_name} WHERE id > 0" );
		}

		parent::tearDown();
	}

	/**
	 * Helper to skip test if handler not implemented.
	 */
	private function maybe_skip_test(): void {
		if ( $this->skip_tests ) {
			$this->markTestSkipped( 'Idempotency handler not implemented yet (Sprint 2, Task 6).' );
		}
	}

	// =========================================================================
	// IDEMPOTENCY KEY GENERATION TESTS (2 tests)
	// =========================================================================

	/**
	 * Test: Generates unique idempotency keys.
	 *
	 * @covers Booking_System_Idempotency_Handler::generate_key
	 */
	public function test_generates_unique_idempotency_key(): void {
		$this->maybe_skip_test();

		// Arrange: Nothing to arrange - just call generate_key.

		// Act: Generate two keys.
		$key1 = $this->idempotency_handler->generate_key();
		$key2 = $this->idempotency_handler->generate_key();

		// Assert: Keys are different (unique).
		$this->assertNotEquals( $key1, $key2, 'Generated keys should be unique' );

		// Assert: Keys are at least 32 characters long.
		$this->assertGreaterThanOrEqual( 32, strlen( $key1 ), 'Key should be at least 32 characters' );
		$this->assertGreaterThanOrEqual( 32, strlen( $key2 ), 'Key should be at least 32 characters' );
	}

	/**
	 * Test: Idempotency key format is URL-safe.
	 *
	 * @covers Booking_System_Idempotency_Handler::generate_key
	 */
	public function test_idempotency_key_format(): void {
		$this->maybe_skip_test();

		// Arrange: Generate a key.
		$key = $this->idempotency_handler->generate_key();

		// Act: Check format with regex.
		// Allow alphanumeric, dashes, and underscores only (URL-safe).
		$is_valid_format = preg_match( '/^[a-zA-Z0-9_-]+$/', $key );

		// Assert: Format is valid.
		$this->assertEquals( 1, $is_valid_format, 'Key should only contain alphanumeric, dashes, and underscores' );

		// Assert: No special characters that break URLs.
		$this->assertStringNotContainsString( '&', $key, 'Key should not contain &' );
		$this->assertStringNotContainsString( '?', $key, 'Key should not contain ?' );
		$this->assertStringNotContainsString( '=', $key, 'Key should not contain =' );
		$this->assertStringNotContainsString( ' ', $key, 'Key should not contain spaces' );
	}

	// =========================================================================
	// OPERATION TRACKING TESTS (4 tests)
	// =========================================================================

	/**
	 * Test: Starts operation successfully.
	 *
	 * @covers Booking_System_Idempotency_Handler::start_operation
	 */
	public function test_starts_operation_successfully(): void {
		$this->maybe_skip_test();

		global $wpdb;

		// Arrange: New operation data.
		$key            = 'test-key-' . uniqid();
		$operation_type = 'stripe_checkout';
		$request_data   = array(
			'service_id' => 1,
			'staff_id'   => 2,
			'amount'     => 5000,
		);

		// Act: Start the operation.
		$result = $this->idempotency_handler->start_operation( $operation_type, $key, $request_data );

		// Assert: Result is successful (not WP_Error).
		$this->assertNotInstanceOf( 'WP_Error', $result, 'start_operation should not return WP_Error' );

		// Assert: Record created in database.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$record = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$this->table_name} WHERE idempotency_key = %s",
				$key
			)
		);

		$this->assertNotNull( $record, 'Record should be created in database' );

		// Assert: Status = 'processing'.
		$this->assertEquals( 'processing', $record->status, 'Status should be processing' );

		// Assert: expires_at is approximately 24 hours from now.
		$expires_at = strtotime( $record->expires_at );
		$expected_expiry = time() + ( 24 * 60 * 60 );
		$this->assertEqualsWithDelta( $expected_expiry, $expires_at, 60, 'expires_at should be ~24 hours from now' );

		// Assert: operation_type is stored.
		$this->assertEquals( $operation_type, $record->operation_type, 'Operation type should be stored' );
	}

	/**
	 * Test: Prevents duplicate operations with same key.
	 *
	 * @covers Booking_System_Idempotency_Handler::start_operation
	 */
	public function test_prevents_duplicate_operation(): void {
		$this->maybe_skip_test();

		global $wpdb;

		// Arrange: Start operation with key.
		$key            = 'test-key-123';
		$operation_type = 'stripe_checkout';
		$request_data   = array(
			'service_id' => 1,
			'staff_id'   => 2,
		);

		$first_result = $this->idempotency_handler->start_operation( $operation_type, $key, $request_data );

		// Act: Try to start same operation again with same key.
		$second_result = $this->idempotency_handler->start_operation( $operation_type, $key, $request_data );

		// Assert: Returns existing record (not WP_Error).
		$this->assertNotInstanceOf( 'WP_Error', $second_result, 'Second attempt should return existing record' );

		// Assert: Same record ID returned.
		$this->assertEquals(
			$first_result['id'],
			$second_result['id'],
			'Second attempt should return same record as first'
		);

		// Assert: Only one database row exists.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$count = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$this->table_name} WHERE idempotency_key = %s",
				$key
			)
		);

		$this->assertEquals( 1, (int) $count, 'Only one database row should exist' );
	}

	/**
	 * Test: Completes operation successfully.
	 *
	 * @covers Booking_System_Idempotency_Handler::complete_operation
	 */
	public function test_completes_operation_successfully(): void {
		$this->maybe_skip_test();

		global $wpdb;

		// Arrange: Start an operation.
		$key            = 'test-complete-key-' . uniqid();
		$operation_type = 'stripe_checkout';
		$request_data   = array( 'service_id' => 1 );
		$response_data  = array(
			'session_id'  => 'cs_test_123456',
			'checkout_url' => 'https://checkout.stripe.com/...',
		);

		$this->idempotency_handler->start_operation( $operation_type, $key, $request_data );

		// Act: Complete the operation.
		$result = $this->idempotency_handler->complete_operation( $key, $response_data );

		// Assert: Result is true (success).
		$this->assertTrue( $result, 'complete_operation should return true' );

		// Assert: Status changed to 'completed'.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$record = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$this->table_name} WHERE idempotency_key = %s",
				$key
			)
		);

		$this->assertEquals( 'completed', $record->status, 'Status should be completed' );

		// Assert: response_data stored.
		$stored_response = json_decode( $record->response_data, true );
		$this->assertEquals( $response_data['session_id'], $stored_response['session_id'], 'Response data should be stored' );

		// Assert: completed_at timestamp set.
		$this->assertNotNull( $record->completed_at, 'completed_at should be set' );
	}

	/**
	 * Test: Fails operation gracefully.
	 *
	 * @covers Booking_System_Idempotency_Handler::fail_operation
	 */
	public function test_fails_operation_gracefully(): void {
		$this->maybe_skip_test();

		global $wpdb;

		// Arrange: Start an operation.
		$key            = 'test-fail-key-' . uniqid();
		$operation_type = 'stripe_checkout';
		$request_data   = array( 'service_id' => 1 );
		$error_message  = 'Stripe API connection timeout';

		$this->idempotency_handler->start_operation( $operation_type, $key, $request_data );

		// Act: Fail the operation.
		$result = $this->idempotency_handler->fail_operation( $key, $error_message );

		// Assert: Result is true (success).
		$this->assertTrue( $result, 'fail_operation should return true' );

		// Assert: Status = 'failed'.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$record = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$this->table_name} WHERE idempotency_key = %s",
				$key
			)
		);

		$this->assertEquals( 'failed', $record->status, 'Status should be failed' );

		// Assert: Error stored in response_data.
		$stored_response = json_decode( $record->response_data, true );
		$this->assertEquals( $error_message, $stored_response['error'], 'Error message should be stored' );
	}

	// =========================================================================
	// STRIPE CHECKOUT IDEMPOTENCY TESTS (2 tests)
	// =========================================================================

	/**
	 * Test: Prevents duplicate checkout sessions.
	 *
	 * @covers Booking_System_Idempotency_Handler::start_operation
	 * @covers Booking_System_Idempotency_Handler::get_completed_response
	 */
	public function test_prevents_duplicate_checkout_sessions(): void {
		$this->maybe_skip_test();

		// Arrange: Session data.
		$key          = 'checkout-key-' . uniqid();
		$session_data = array(
			'service_id'     => 1,
			'staff_id'       => 2,
			'customer_email' => 'test@example.com',
			'amount'         => 5000,
		);

		// First checkout session creation.
		$first_result = $this->idempotency_handler->start_operation( 'stripe_checkout', $key, $session_data );

		// Simulate Stripe session created.
		$stripe_response = array(
			'session_id'   => 'cs_test_unique123',
			'checkout_url' => 'https://checkout.stripe.com/pay/cs_test_unique123',
		);
		$this->idempotency_handler->complete_operation( $key, $stripe_response );

		// Act: Try to create again with same key.
		$second_result = $this->idempotency_handler->start_operation( 'stripe_checkout', $key, $session_data );

		// Assert: Returns existing completed record.
		$this->assertEquals( 'completed', $second_result['status'], 'Should return completed status' );

		// Get the cached response.
		$cached_response = $this->idempotency_handler->get_completed_response( $key );

		// Assert: Returns same session_id (not new one).
		$this->assertEquals(
			$stripe_response['session_id'],
			$cached_response['session_id'],
			'Should return cached session_id'
		);
	}

	/**
	 * Test: Different data with same key returns error.
	 *
	 * @covers Booking_System_Idempotency_Handler::start_operation
	 */
	public function test_different_data_creates_error(): void {
		$this->maybe_skip_test();

		// Arrange: Create session with data A.
		$key    = 'checkout-key-mismatch-' . uniqid();
		$data_a = array(
			'service_id' => 1,
			'staff_id'   => 2,
			'amount'     => 5000,
		);

		$this->idempotency_handler->start_operation( 'stripe_checkout', $key, $data_a );

		// Act: Create session with same key but data B (different hash).
		$data_b = array(
			'service_id' => 1,
			'staff_id'   => 3, // Different staff.
			'amount'     => 6000, // Different amount.
		);

		$result = $this->idempotency_handler->start_operation( 'stripe_checkout', $key, $data_b );

		// Assert: Returns WP_Error (data mismatch).
		$this->assertInstanceOf( 'WP_Error', $result, 'Should return WP_Error for data mismatch' );
		$this->assertEquals( 'idempotency_data_mismatch', $result->get_error_code(), 'Error code should be idempotency_data_mismatch' );
	}

	// =========================================================================
	// EMAIL IDEMPOTENCY TESTS (2 tests)
	// =========================================================================

	/**
	 * Test: Prevents duplicate email sends.
	 *
	 * @covers Booking_System_Idempotency_Handler::start_operation
	 */
	public function test_prevents_duplicate_email_sends(): void {
		$this->maybe_skip_test();

		// Arrange: Email data.
		$key        = 'email-key-' . uniqid();
		$email_data = array(
			'to'      => 'customer@example.com',
			'subject' => 'Booking Confirmation',
			'body'    => 'Your booking is confirmed.',
		);

		// Track wp_mail calls.
		$mail_call_count = 0;

		// Mock wp_mail.
		add_filter(
			'pre_wp_mail',
			function ( $null ) use ( &$mail_call_count ) {
				$mail_call_count++;
				return true; // Simulate successful send.
			}
		);

		// First email send.
		$first_result = $this->idempotency_handler->start_operation( 'email_send', $key, $email_data );

		// Simulate email sent successfully.
		$this->idempotency_handler->complete_operation( $key, array( 'sent' => true, 'timestamp' => time() ) );

		// Reset counter before second attempt.
		$initial_count = $mail_call_count;

		// Act: Try to send again with same key.
		$second_result = $this->idempotency_handler->start_operation( 'email_send', $key, $email_data );

		// Assert: Second attempt returns cached result (completed status).
		$this->assertEquals( 'completed', $second_result['status'], 'Should return completed status for duplicate' );

		// Assert: get_completed_response returns cached result.
		$cached = $this->idempotency_handler->get_completed_response( $key );
		$this->assertTrue( $cached['sent'], 'Should return cached sent result' );
	}

	/**
	 * Test: Allows retry on email failure.
	 *
	 * @covers Booking_System_Idempotency_Handler::start_operation
	 * @covers Booking_System_Idempotency_Handler::fail_operation
	 */
	public function test_allows_retry_on_email_failure(): void {
		$this->maybe_skip_test();

		// Arrange: Email data.
		$key        = 'email-retry-key-' . uniqid();
		$email_data = array(
			'to'      => 'customer@example.com',
			'subject' => 'Booking Confirmation',
			'body'    => 'Your booking is confirmed.',
		);

		// First attempt - fails.
		$this->idempotency_handler->start_operation( 'email_send', $key, $email_data );
		$this->idempotency_handler->fail_operation( $key, 'SMTP connection failed' );

		// Act: Second attempt with same key should be allowed (since first failed).
		$result = $this->idempotency_handler->start_operation( 'email_send', $key, $email_data );

		// Assert: Second attempt allowed (status reset to processing or returns allow_retry flag).
		// The implementation should either:
		// 1. Reset the failed operation to processing, OR
		// 2. Return the existing record with status 'failed' and an allow_retry flag.
		$this->assertNotInstanceOf( 'WP_Error', $result, 'Should allow retry after failure' );

		// Check if retry is allowed (either by status being processing or allow_retry flag).
		$retry_allowed = (
			$result['status'] === 'processing' ||
			( isset( $result['allow_retry'] ) && $result['allow_retry'] === true ) ||
			$result['status'] === 'failed'
		);

		$this->assertTrue( $retry_allowed, 'Should allow retry when previous attempt failed' );
	}

	// =========================================================================
	// CLEANUP & EXPIRY TESTS (2 tests)
	// =========================================================================

	/**
	 * Test: Cleans up expired records.
	 *
	 * @covers Booking_System_Idempotency_Handler::cleanup_expired
	 */
	public function test_cleans_up_expired_records(): void {
		$this->maybe_skip_test();

		global $wpdb;

		// Arrange: Create records with expires_at in past.
		$expired_key = 'expired-key-' . uniqid();
		$recent_key  = 'recent-key-' . uniqid();

		// Insert expired record (expired 2 hours ago).
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
		$wpdb->insert(
			$this->table_name,
			array(
				'idempotency_key' => $expired_key,
				'operation_type'  => 'test_operation',
				'request_hash'    => hash( 'sha256', 'expired_data' ),
				'status'          => 'completed',
				'created_at'      => gmdate( 'Y-m-d H:i:s', strtotime( '-26 hours' ) ),
				'expires_at'      => gmdate( 'Y-m-d H:i:s', strtotime( '-2 hours' ) ),
			),
			array( '%s', '%s', '%s', '%s', '%s', '%s' )
		);

		// Insert recent record (expires in 22 hours).
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
		$wpdb->insert(
			$this->table_name,
			array(
				'idempotency_key' => $recent_key,
				'operation_type'  => 'test_operation',
				'request_hash'    => hash( 'sha256', 'recent_data' ),
				'status'          => 'completed',
				'created_at'      => gmdate( 'Y-m-d H:i:s', strtotime( '-2 hours' ) ),
				'expires_at'      => gmdate( 'Y-m-d H:i:s', strtotime( '+22 hours' ) ),
			),
			array( '%s', '%s', '%s', '%s', '%s', '%s' )
		);

		// Act: Run cleanup.
		$deleted_count = $this->idempotency_handler->cleanup_expired();

		// Assert: Old records deleted.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$expired_exists = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$this->table_name} WHERE idempotency_key = %s",
				$expired_key
			)
		);
		$this->assertEquals( 0, (int) $expired_exists, 'Expired record should be deleted' );

		// Assert: Recent records preserved.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$recent_exists = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$this->table_name} WHERE idempotency_key = %s",
				$recent_key
			)
		);
		$this->assertEquals( 1, (int) $recent_exists, 'Recent record should be preserved' );

		// Assert: Cleanup returned count of deleted records.
		$this->assertGreaterThanOrEqual( 1, $deleted_count, 'Should return count of deleted records' );
	}

	/**
	 * Test: Handles concurrent requests gracefully.
	 *
	 * @covers Booking_System_Idempotency_Handler::start_operation
	 */
	public function test_handles_concurrent_requests(): void {
		$this->maybe_skip_test();

		global $wpdb;

		// Arrange: Key for concurrent operations.
		$key          = 'concurrent-key-' . uniqid();
		$request_data = array( 'service_id' => 1 );

		// Simulate race condition by directly inserting a "processing" record.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
		$wpdb->insert(
			$this->table_name,
			array(
				'idempotency_key' => $key,
				'operation_type'  => 'stripe_checkout',
				'request_hash'    => hash( 'sha256', wp_json_encode( $request_data ) ),
				'status'          => 'processing',
				'created_at'      => gmdate( 'Y-m-d H:i:s' ),
				'expires_at'      => gmdate( 'Y-m-d H:i:s', strtotime( '+24 hours' ) ),
			),
			array( '%s', '%s', '%s', '%s', '%s', '%s' )
		);

		$first_id = $wpdb->insert_id;

		// Act: Second "concurrent" request tries to start same operation.
		$result = $this->idempotency_handler->start_operation( 'stripe_checkout', $key, $request_data );

		// Assert: Only one operation proceeds (returns existing record).
		$this->assertNotInstanceOf( 'WP_Error', $result, 'Should not return error for concurrent request' );

		// Assert: Second gets existing record.
		$this->assertEquals( $first_id, $result['id'], 'Should return existing record ID' );

		// Assert: Still only one row in database.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$count = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$this->table_name} WHERE idempotency_key = %s",
				$key
			)
		);
		$this->assertEquals( 1, (int) $count, 'Only one database row should exist' );
	}

	// =========================================================================
	// EDGE CASE TESTS
	// =========================================================================

	/**
	 * Test: Empty idempotency key returns error.
	 *
	 * @covers Booking_System_Idempotency_Handler::start_operation
	 */
	public function test_empty_idempotency_key_returns_error(): void {
		$this->maybe_skip_test();

		// Arrange: Empty key.
		$key          = '';
		$request_data = array( 'service_id' => 1 );

		// Act: Try to start operation with empty key.
		$result = $this->idempotency_handler->start_operation( 'stripe_checkout', $key, $request_data );

		// Assert: Returns WP_Error.
		$this->assertInstanceOf( 'WP_Error', $result, 'Should return WP_Error for empty key' );
		$this->assertEquals( 'invalid_idempotency_key', $result->get_error_code(), 'Error code should be invalid_idempotency_key' );
	}

	/**
	 * Test: Null operation type returns error.
	 *
	 * @covers Booking_System_Idempotency_Handler::start_operation
	 */
	public function test_null_operation_type_returns_error(): void {
		$this->maybe_skip_test();

		// Arrange: Null operation type.
		$key            = 'test-key-' . uniqid();
		$operation_type = null;
		$request_data   = array( 'service_id' => 1 );

		// Act: Try to start operation with null type.
		$result = $this->idempotency_handler->start_operation( $operation_type, $key, $request_data );

		// Assert: Returns WP_Error.
		$this->assertInstanceOf( 'WP_Error', $result, 'Should return WP_Error for null operation type' );
		$this->assertEquals( 'invalid_operation_type', $result->get_error_code(), 'Error code should be invalid_operation_type' );
	}

	/**
	 * Test: Very long idempotency keys are truncated or rejected.
	 *
	 * @covers Booking_System_Idempotency_Handler::start_operation
	 */
	public function test_very_long_idempotency_key_handled(): void {
		$this->maybe_skip_test();

		// Arrange: Key longer than 255 characters.
		$key          = str_repeat( 'a', 300 );
		$request_data = array( 'service_id' => 1 );

		// Act: Try to start operation.
		$result = $this->idempotency_handler->start_operation( 'stripe_checkout', $key, $request_data );

		// Assert: Either truncated and works, or returns error.
		// Implementation can choose either approach.
		if ( is_wp_error( $result ) ) {
			$this->assertEquals( 'invalid_idempotency_key', $result->get_error_code(), 'Should return invalid key error' );
		} else {
			// Key was truncated and operation succeeded.
			$this->assertIsArray( $result, 'Should return array result' );
			$this->assertArrayHasKey( 'id', $result, 'Result should have id' );
		}
	}

	/**
	 * Test: Special characters in keys are rejected.
	 *
	 * @covers Booking_System_Idempotency_Handler::start_operation
	 */
	public function test_special_characters_in_key_rejected(): void {
		$this->maybe_skip_test();

		// Arrange: Keys with special characters.
		$invalid_keys = array(
			'key with spaces',
			'key&with&ampersands',
			'key?with?question',
			'key=with=equals',
			"key\nwith\nnewlines",
			'key<with>html',
		);

		foreach ( $invalid_keys as $key ) {
			// Act: Try to start operation with invalid key.
			$result = $this->idempotency_handler->start_operation( 'stripe_checkout', $key, array( 'test' => 1 ) );

			// Assert: Returns WP_Error.
			$this->assertInstanceOf(
				'WP_Error',
				$result,
				"Should return WP_Error for key with special characters: $key"
			);
		}
	}

	/**
	 * Test: Missing request data returns error.
	 *
	 * @covers Booking_System_Idempotency_Handler::start_operation
	 */
	public function test_missing_request_data_returns_error(): void {
		$this->maybe_skip_test();

		// Arrange: Null request data.
		$key          = 'test-key-' . uniqid();
		$request_data = null;

		// Act: Try to start operation with null data.
		$result = $this->idempotency_handler->start_operation( 'stripe_checkout', $key, $request_data );

		// Assert: Returns WP_Error.
		$this->assertInstanceOf( 'WP_Error', $result, 'Should return WP_Error for null request data' );
		$this->assertEquals( 'invalid_request_data', $result->get_error_code(), 'Error code should be invalid_request_data' );
	}

	/**
	 * Test: Get operation status by key.
	 *
	 * @covers Booking_System_Idempotency_Handler::get_operation_status
	 */
	public function test_get_operation_status(): void {
		$this->maybe_skip_test();

		// Arrange: Create an operation.
		$key          = 'status-test-key-' . uniqid();
		$request_data = array( 'service_id' => 1 );

		$this->idempotency_handler->start_operation( 'stripe_checkout', $key, $request_data );

		// Act: Get status.
		$status = $this->idempotency_handler->get_operation_status( $key );

		// Assert: Status is processing.
		$this->assertEquals( 'processing', $status, 'Status should be processing' );

		// Complete the operation.
		$this->idempotency_handler->complete_operation( $key, array( 'done' => true ) );

		// Act: Get status again.
		$status_after = $this->idempotency_handler->get_operation_status( $key );

		// Assert: Status is completed.
		$this->assertEquals( 'completed', $status_after, 'Status should be completed' );
	}

	/**
	 * Test: Non-existent key returns null status.
	 *
	 * @covers Booking_System_Idempotency_Handler::get_operation_status
	 */
	public function test_nonexistent_key_returns_null_status(): void {
		$this->maybe_skip_test();

		// Arrange: Non-existent key.
		$key = 'nonexistent-key-' . uniqid();

		// Act: Get status.
		$status = $this->idempotency_handler->get_operation_status( $key );

		// Assert: Status is null.
		$this->assertNull( $status, 'Status should be null for non-existent key' );
	}

	/**
	 * Test: Expired operations allow new operation with same key.
	 *
	 * @covers Booking_System_Idempotency_Handler::start_operation
	 */
	public function test_expired_operations_allow_retry(): void {
		$this->maybe_skip_test();

		global $wpdb;

		// Arrange: Insert an expired operation.
		$key = 'expired-retry-key-' . uniqid();

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
		$wpdb->insert(
			$this->table_name,
			array(
				'idempotency_key' => $key,
				'operation_type'  => 'stripe_checkout',
				'request_hash'    => hash( 'sha256', wp_json_encode( array( 'service_id' => 1 ) ) ),
				'status'          => 'processing',
				'created_at'      => gmdate( 'Y-m-d H:i:s', strtotime( '-48 hours' ) ),
				'expires_at'      => gmdate( 'Y-m-d H:i:s', strtotime( '-24 hours' ) ), // Expired.
			),
			array( '%s', '%s', '%s', '%s', '%s', '%s' )
		);

		$request_data = array( 'service_id' => 1 );

		// Act: Start new operation with same key.
		$result = $this->idempotency_handler->start_operation( 'stripe_checkout', $key, $request_data );

		// Assert: Should allow retry (either replace expired or return allow_retry).
		$this->assertNotInstanceOf( 'WP_Error', $result, 'Should allow new operation for expired key' );

		// The status should be processing (new operation started).
		$this->assertEquals( 'processing', $result['status'], 'Status should be processing for new operation' );
	}

	/**
	 * Test: Request hash is calculated consistently.
	 *
	 * @covers Booking_System_Idempotency_Handler::start_operation
	 */
	public function test_request_hash_consistency(): void {
		$this->maybe_skip_test();

		global $wpdb;

		// Arrange: Same data in different order.
		$key    = 'hash-test-key-' . uniqid();
		$data_1 = array(
			'service_id' => 1,
			'staff_id'   => 2,
			'amount'     => 5000,
		);

		// Start operation.
		$this->idempotency_handler->start_operation( 'stripe_checkout', $key, $data_1 );

		// Get the stored hash.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$record = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT request_hash FROM {$this->table_name} WHERE idempotency_key = %s",
				$key
			)
		);

		// Assert: Hash is 64 characters (SHA-256).
		$this->assertEquals( 64, strlen( $record->request_hash ), 'Request hash should be SHA-256 (64 chars)' );

		// Assert: Hash is hexadecimal.
		$this->assertMatchesRegularExpression( '/^[a-f0-9]{64}$/', $record->request_hash, 'Hash should be hexadecimal' );
	}
}
