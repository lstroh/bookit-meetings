<?php
/**
 * Idempotency Handler
 *
 * Database-backed idempotency tracking for operations.
 * Prevents duplicate operations (Stripe checkouts, emails, webhooks).
 *
 * Sprint 2, Task 6
 *
 * @package    Bookit_Booking_System
 * @subpackage Core
 */

/**
 * Handles idempotency key generation and operation tracking.
 *
 * Lifecycle:
 *   start_operation() → status='processing'
 *     ↓ (success)
 *   complete_operation() → status='completed', response stored
 *     ↓ OR (failure)
 *   fail_operation() → status='failed', error stored
 *     ↓ (after expiry)
 *   cleanup_expired() → record deleted
 */
class Booking_System_Idempotency_Handler {

	/**
	 * Database table name for idempotency records.
	 *
	 * @var string
	 */
	private $table_name;

	/**
	 * Default expiry time in hours.
	 *
	 * @var int
	 */
	private const EXPIRY_HOURS = 24;

	/**
	 * Maximum key length.
	 *
	 * @var int
	 */
	private const MAX_KEY_LENGTH = 255;

	/**
	 * Constructor.
	 */
	public function __construct() {
		global $wpdb;
		$this->table_name = $wpdb->prefix . 'bookings_idempotency';
	}

	/**
	 * Generate a unique idempotency key.
	 *
	 * Generates a URL-safe key using hexadecimal characters (0-9, a-f).
	 * 16 random bytes = 32 hex characters.
	 *
	 * @return string 32 character unique key.
	 */
	public function generate_key(): string {
		// Generate 16 random bytes and convert to hex = exactly 32 characters.
		return bin2hex( random_bytes( 16 ) );
	}

	/**
	 * Validate an idempotency key.
	 *
	 * @param string $key The key to validate.
	 * @return bool|WP_Error True if valid, WP_Error if invalid.
	 */
	private function validate_key( $key ) {
		// Check for empty key.
		if ( empty( $key ) || ! is_string( $key ) ) {
			return new WP_Error(
				'invalid_idempotency_key',
				'Idempotency key is required and must be a non-empty string'
			);
		}

		// Check for maximum length.
		if ( strlen( $key ) > self::MAX_KEY_LENGTH ) {
			return new WP_Error(
				'invalid_idempotency_key',
				'Idempotency key exceeds maximum length of ' . self::MAX_KEY_LENGTH . ' characters'
			);
		}

		// Check for valid characters (alphanumeric, dashes, underscores only).
		if ( ! preg_match( '/^[a-zA-Z0-9_-]+$/', $key ) ) {
			return new WP_Error(
				'invalid_idempotency_key',
				'Idempotency key contains invalid characters. Only alphanumeric, dashes, and underscores allowed.'
			);
		}

		return true;
	}

	/**
	 * Start an operation with idempotency tracking.
	 *
	 * @param string|null $operation_type Type of operation (e.g., 'stripe_checkout', 'email_send').
	 * @param string      $idempotency_key Unique key for this operation.
	 * @param array|null  $request_data Request data to hash for duplicate detection.
	 * @return array|WP_Error Operation record array or WP_Error on failure.
	 */
	public function start_operation( $operation_type, $idempotency_key, $request_data = array() ) {
		global $wpdb;

		// Validate operation type.
		if ( empty( $operation_type ) || ! is_string( $operation_type ) ) {
			return new WP_Error(
				'invalid_operation_type',
				'Operation type is required and must be a non-empty string'
			);
		}

		// Validate idempotency key.
		$key_validation = $this->validate_key( $idempotency_key );
		if ( is_wp_error( $key_validation ) ) {
			return $key_validation;
		}

		// Validate request data.
		if ( null === $request_data || ( ! is_array( $request_data ) && ! is_object( $request_data ) ) ) {
			return new WP_Error(
				'invalid_request_data',
				'Request data must be an array or object'
			);
		}

		// Create hash of request data for comparison.
		$request_hash = hash( 'sha256', wp_json_encode( $request_data ) );

		// Check if operation already exists.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$existing = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$this->table_name} WHERE idempotency_key = %s",
				$idempotency_key
			),
			ARRAY_A
		);

		if ( $existing ) {
			return $this->handle_existing_operation( $existing, $request_hash, $request_data );
		}

		// Create new operation record.
		return $this->create_operation_record( $operation_type, $idempotency_key, $request_hash );
	}

	/**
	 * Handle an existing operation record.
	 *
	 * @param array  $existing Existing operation record.
	 * @param string $request_hash Hash of new request data.
	 * @param array  $request_data Original request data.
	 * @return array|WP_Error Operation record or error.
	 */
	private function handle_existing_operation( array $existing, string $request_hash, $request_data ) {
		global $wpdb;

		// Check if operation has expired.
		$is_expired = strtotime( $existing['expires_at'] ) < time();

		// Check if operation failed (allow retry).
		$is_failed = 'failed' === $existing['status'];

		// If expired or failed, allow retry by resetting the operation.
		if ( $is_expired || $is_failed ) {
			// Update existing record to reset for retry.
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
			$wpdb->update(
				$this->table_name,
				array(
					'request_hash'  => $request_hash,
					'status'        => 'processing',
					'response_data' => null,
					'completed_at'  => null,
					'created_at'    => current_time( 'mysql', true ),
					'expires_at'    => gmdate( 'Y-m-d H:i:s', strtotime( '+' . self::EXPIRY_HOURS . ' hours' ) ),
				),
				array( 'id' => $existing['id'] ),
				array( '%s', '%s', '%s', '%s', '%s', '%s' ),
				array( '%d' )
			);

			// Return updated record.
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			return $wpdb->get_row(
				$wpdb->prepare(
					"SELECT * FROM {$this->table_name} WHERE id = %d",
					$existing['id']
				),
				ARRAY_A
			);
		}

		// Check if data matches (prevent key reuse with different data).
		if ( $existing['request_hash'] !== $request_hash ) {
			return new WP_Error(
				'idempotency_data_mismatch',
				'Idempotency key already used with different request data'
			);
		}

		// Return existing record (idempotent response).
		return $existing;
	}

	/**
	 * Create a new operation record in the database.
	 *
	 * @param string $operation_type Type of operation.
	 * @param string $idempotency_key Unique key.
	 * @param string $request_hash Hash of request data.
	 * @return array|WP_Error New operation record or error.
	 */
	private function create_operation_record( string $operation_type, string $idempotency_key, string $request_hash ) {
		global $wpdb;

		$now        = current_time( 'mysql', true );
		$expires_at = gmdate( 'Y-m-d H:i:s', strtotime( '+' . self::EXPIRY_HOURS . ' hours' ) );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
		$inserted = $wpdb->insert(
			$this->table_name,
			array(
				'idempotency_key' => $idempotency_key,
				'operation_type'  => $operation_type,
				'request_hash'    => $request_hash,
				'status'          => 'processing',
				'created_at'      => $now,
				'expires_at'      => $expires_at,
			),
			array( '%s', '%s', '%s', '%s', '%s', '%s' )
		);

		if ( ! $inserted ) {
			// Handle race condition - another request may have inserted first.
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			$existing = $wpdb->get_row(
				$wpdb->prepare(
					"SELECT * FROM {$this->table_name} WHERE idempotency_key = %s",
					$idempotency_key
				),
				ARRAY_A
			);

			if ( $existing ) {
				// Another request won the race, return that record.
				if ( $existing['request_hash'] !== $request_hash ) {
					return new WP_Error(
						'idempotency_data_mismatch',
						'Idempotency key already used with different request data'
					);
				}
				return $existing;
			}

			// Log error for debugging.
			if ( class_exists( 'Bookit_Logger' ) ) {
				Bookit_Logger::error( 'Idempotency: Failed to insert record - ' . $wpdb->last_error );
			}

			return new WP_Error(
				'database_error',
				'Failed to create idempotency record: ' . $wpdb->last_error
			);
		}

		$insert_id = $wpdb->insert_id;

		// Return newly created record.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		return $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$this->table_name} WHERE id = %d",
				$insert_id
			),
			ARRAY_A
		);
	}

	/**
	 * Complete an operation successfully.
	 *
	 * @param string     $idempotency_key The operation's idempotency key.
	 * @param array|null $response_data Response data to store for future duplicate requests.
	 * @return bool True on success, false on failure.
	 */
	public function complete_operation( string $idempotency_key, $response_data = null ): bool {
		global $wpdb;

		$data_to_store = null;
		if ( null !== $response_data ) {
			$data_to_store = is_array( $response_data ) || is_object( $response_data )
				? wp_json_encode( $response_data )
				: (string) $response_data;
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
		$updated = $wpdb->update(
			$this->table_name,
			array(
				'status'        => 'completed',
				'response_data' => $data_to_store,
				'completed_at'  => current_time( 'mysql', true ),
			),
			array( 'idempotency_key' => $idempotency_key ),
			array( '%s', '%s', '%s' ),
			array( '%s' )
		);

		if ( false === $updated ) {
			if ( class_exists( 'Bookit_Logger' ) ) {
				Bookit_Logger::error( 'Idempotency: Failed to complete operation - ' . $wpdb->last_error );
			}
			return false;
		}

		return true;
	}

	/**
	 * Mark an operation as failed.
	 *
	 * @param string $idempotency_key The operation's idempotency key.
	 * @param string $error_message Error message describing the failure.
	 * @return bool True on success, false on failure.
	 */
	public function fail_operation( string $idempotency_key, string $error_message ): bool {
		global $wpdb;

		// Store error in JSON format for consistency.
		$error_data = wp_json_encode( array( 'error' => $error_message ) );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
		$updated = $wpdb->update(
			$this->table_name,
			array(
				'status'        => 'failed',
				'response_data' => $error_data,
				'completed_at'  => current_time( 'mysql', true ),
			),
			array( 'idempotency_key' => $idempotency_key ),
			array( '%s', '%s', '%s' ),
			array( '%s' )
		);

		if ( false === $updated ) {
			if ( class_exists( 'Bookit_Logger' ) ) {
				Bookit_Logger::error( 'Idempotency: Failed to mark operation as failed - ' . $wpdb->last_error );
			}
			return false;
		}

		return true;
	}

	/**
	 * Get the status of an operation.
	 *
	 * @param string $idempotency_key The operation's idempotency key.
	 * @return string|null Status string ('processing', 'completed', 'failed') or null if not found.
	 */
	public function get_operation_status( string $idempotency_key ): ?string {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$status = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT status FROM {$this->table_name} WHERE idempotency_key = %s",
				$idempotency_key
			)
		);

		return $status;
	}

	/**
	 * Get the full operation record.
	 *
	 * @param string $idempotency_key The operation's idempotency key.
	 * @return array|null Operation record or null if not found.
	 */
	public function get_operation( string $idempotency_key ): ?array {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$record = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$this->table_name} WHERE idempotency_key = %s",
				$idempotency_key
			),
			ARRAY_A
		);

		return $record ?: null;
	}

	/**
	 * Get the stored response data for a completed operation.
	 *
	 * @param string $idempotency_key The operation's idempotency key.
	 * @return array|null Decoded response data or null if not found/not completed.
	 */
	public function get_completed_response( string $idempotency_key ): ?array {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$record = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT status, response_data FROM {$this->table_name} WHERE idempotency_key = %s",
				$idempotency_key
			),
			ARRAY_A
		);

		if ( ! $record || 'completed' !== $record['status'] || empty( $record['response_data'] ) ) {
			return null;
		}

		$decoded = json_decode( $record['response_data'], true );

		return is_array( $decoded ) ? $decoded : null;
	}

	/**
	 * Check if an operation can be retried.
	 *
	 * Operations can be retried if:
	 * - They don't exist yet
	 * - They failed
	 * - They have expired
	 *
	 * @param string $idempotency_key The operation's idempotency key.
	 * @return bool True if operation can be retried.
	 */
	public function can_retry( string $idempotency_key ): bool {
		$operation = $this->get_operation( $idempotency_key );

		// No operation exists, can proceed.
		if ( ! $operation ) {
			return true;
		}

		// Can retry if failed.
		if ( 'failed' === $operation['status'] ) {
			return true;
		}

		// Can retry if expired.
		if ( strtotime( $operation['expires_at'] ) < time() ) {
			return true;
		}

		// Cannot retry if processing or completed and not expired.
		return false;
	}

	/**
	 * Clean up expired idempotency records.
	 *
	 * Should be run via cron job daily.
	 *
	 * @return int Number of records deleted.
	 */
	public function cleanup_expired(): int {
		global $wpdb;

		$now = gmdate( 'Y-m-d H:i:s' );

		// Delete records where expires_at is in the past.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$deleted = $wpdb->query(
			$wpdb->prepare(
				"DELETE FROM {$this->table_name} WHERE expires_at < %s",
				$now
			)
		);

		if ( $deleted > 0 && class_exists( 'Bookit_Logger' ) ) {
			Bookit_Logger::info( "Idempotency: Cleaned up {$deleted} expired records" );
		}

		return (int) $deleted;
	}

	/**
	 * Delete an operation by key.
	 *
	 * @param string $idempotency_key The operation's idempotency key.
	 * @return bool True on success, false on failure.
	 */
	public function delete_operation( string $idempotency_key ): bool {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$deleted = $wpdb->delete(
			$this->table_name,
			array( 'idempotency_key' => $idempotency_key ),
			array( '%s' )
		);

		return false !== $deleted && $deleted > 0;
	}

	/**
	 * Get table name.
	 *
	 * @return string The full table name with prefix.
	 */
	public function get_table_name(): string {
		return $this->table_name;
	}

	/**
	 * Create the idempotency table.
	 *
	 * Called during plugin activation.
	 *
	 * @return bool True on success.
	 */
	public static function create_table(): bool {
		global $wpdb;

		$table_name      = $wpdb->prefix . 'bookings_idempotency';
		$charset_collate = $wpdb->get_charset_collate();

		$sql = "CREATE TABLE IF NOT EXISTS {$table_name} (
			id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
			idempotency_key VARCHAR(255) NOT NULL,
			operation_type VARCHAR(50) NOT NULL,
			request_hash VARCHAR(64) NOT NULL,
			response_data TEXT NULL,
			status VARCHAR(20) NOT NULL DEFAULT 'processing',
			created_at DATETIME NOT NULL,
			completed_at DATETIME NULL,
			expires_at DATETIME NOT NULL,
			UNIQUE KEY unique_key (idempotency_key),
			KEY idx_expires (expires_at),
			KEY idx_status (status),
			KEY idx_operation_type (operation_type)
		) {$charset_collate}";

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		dbDelta( $sql );

		// Verify table was created.
		$table_exists = $wpdb->get_var(
			$wpdb->prepare(
				'SHOW TABLES LIKE %s',
				$table_name
			)
		);

		return $table_exists === $table_name;
	}
}
