<?php
/**
 * Database Migration: Idempotency Table
 *
 * Creates wp_bookings_idempotency table for database-backed idempotency tracking.
 * Prevents duplicate operations (Stripe checkouts, emails, webhooks).
 *
 * Sprint 2, Task 6
 *
 * @package    Bookit_Booking_System
 * @subpackage Database
 */

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
	die;
}

/**
 * Create the idempotency tracking table.
 *
 * Table Schema:
 * - id: Primary key
 * - idempotency_key: Unique key for operation (max 255 chars)
 * - operation_type: Type of operation (stripe_checkout, email_send, webhook)
 * - request_hash: SHA256 hash of request data (64 chars)
 * - response_data: JSON or text response (for completed operations)
 * - status: processing, completed, failed
 * - created_at: When operation started
 * - completed_at: When operation finished
 * - expires_at: 24 hours after created_at
 *
 * Indexes:
 * - unique_key: UNIQUE on idempotency_key (prevents duplicates)
 * - idx_expires: For fast cleanup of expired records
 * - idx_status: For filtering by status
 * - idx_operation_type: For filtering by operation type
 *
 * @return bool True on success, false on failure.
 */
function bookit_create_idempotency_table(): bool {
	global $wpdb;

	$table_name      = $wpdb->prefix . 'bookings_idempotency';
	$charset_collate = $wpdb->get_charset_collate();

	// Check if table already exists.
	// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
	$table_exists = $wpdb->get_var(
		$wpdb->prepare(
			'SHOW TABLES LIKE %s',
			$table_name
		)
	);

	if ( $table_exists === $table_name ) {
		// Table already exists.
		if ( class_exists( 'Bookit_Logger' ) ) {
			Bookit_Logger::info( 'Idempotency table already exists, skipping creation' );
		}
		return true;
	}

	// Load WordPress upgrade functions.
	require_once ABSPATH . 'wp-admin/includes/upgrade.php';

	// NOTE: dbDelta() is picky about formatting:
	// - Two spaces after PRIMARY KEY
	// - No trailing commas
	// - Specific KEY syntax (not INDEX).
	$sql = "CREATE TABLE {$table_name} (
		id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
		idempotency_key VARCHAR(255) NOT NULL,
		operation_type VARCHAR(50) NOT NULL,
		request_hash VARCHAR(64) NOT NULL,
		response_data TEXT NULL,
		status VARCHAR(20) NOT NULL DEFAULT 'processing',
		created_at DATETIME NOT NULL,
		completed_at DATETIME NULL,
		expires_at DATETIME NOT NULL,
		PRIMARY KEY  (id),
		UNIQUE KEY unique_key (idempotency_key),
		KEY idx_expires (expires_at),
		KEY idx_status (status),
		KEY idx_operation_type (operation_type)
	) {$charset_collate};";

	dbDelta( $sql );

	// Verify table was created.
	// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
	$table_exists = $wpdb->get_var(
		$wpdb->prepare(
			'SHOW TABLES LIKE %s',
			$table_name
		)
	);

	if ( $table_exists === $table_name ) {
		if ( class_exists( 'Bookit_Logger' ) ) {
			Bookit_Logger::info( 'Idempotency table created successfully' );
		}
		return true;
	} else {
		if ( class_exists( 'Bookit_Logger' ) ) {
			Bookit_Logger::error( 'Failed to create idempotency table: ' . $wpdb->last_error );
		}
		return false;
	}
}

/**
 * Drop the idempotency table.
 *
 * WARNING: This deletes all idempotency data. Only call from uninstall.php.
 *
 * @return bool True on success.
 */
function bookit_drop_idempotency_table(): bool {
	global $wpdb;

	$table_name = $wpdb->prefix . 'bookings_idempotency';

	// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
	$result = $wpdb->query( "DROP TABLE IF EXISTS {$table_name}" );

	if ( class_exists( 'Bookit_Logger' ) ) {
		Bookit_Logger::info( 'Idempotency table dropped' );
	}

	return false !== $result;
}

/**
 * Check if idempotency table exists.
 *
 * @return bool True if table exists.
 */
function bookit_idempotency_table_exists(): bool {
	global $wpdb;

	$table_name = $wpdb->prefix . 'bookings_idempotency';

	// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
	$table_exists = $wpdb->get_var(
		$wpdb->prepare(
			'SHOW TABLES LIKE %s',
			$table_name
		)
	);

	return $table_exists === $table_name;
}

/**
 * Verify idempotency table structure.
 *
 * Checks that all required columns and indexes exist.
 *
 * @return array{valid: bool, missing_columns: array<string>, missing_indexes: array<string>}
 */
function bookit_verify_idempotency_table(): array {
	global $wpdb;

	$table_name = $wpdb->prefix . 'bookings_idempotency';
	$result     = array(
		'valid'           => true,
		'missing_columns' => array(),
		'missing_indexes' => array(),
	);

	// Check if table exists.
	if ( ! bookit_idempotency_table_exists() ) {
		$result['valid'] = false;
		return $result;
	}

	// Required columns.
	$required_columns = array(
		'id',
		'idempotency_key',
		'operation_type',
		'request_hash',
		'response_data',
		'status',
		'created_at',
		'completed_at',
		'expires_at',
	);

	// Get existing columns.
	// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
	$columns        = $wpdb->get_results( "SHOW COLUMNS FROM {$table_name}" );
	$existing_cols  = array_column( $columns, 'Field' );

	foreach ( $required_columns as $col ) {
		if ( ! in_array( $col, $existing_cols, true ) ) {
			$result['valid']             = false;
			$result['missing_columns'][] = $col;
		}
	}

	// Required indexes.
	$required_indexes = array(
		'PRIMARY',
		'unique_key',
		'idx_expires',
		'idx_status',
		'idx_operation_type',
	);

	// Get existing indexes.
	// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
	$indexes       = $wpdb->get_results( "SHOW INDEX FROM {$table_name}" );
	$existing_idxs = array_unique( array_column( $indexes, 'Key_name' ) );

	foreach ( $required_indexes as $idx ) {
		if ( ! in_array( $idx, $existing_idxs, true ) ) {
			$result['valid']             = false;
			$result['missing_indexes'][] = $idx;
		}
	}

	return $result;
}

/**
 * Get idempotency table statistics.
 *
 * @return array{total: int, processing: int, completed: int, failed: int, expired: int}
 */
function bookit_get_idempotency_stats(): array {
	global $wpdb;

	$table_name = $wpdb->prefix . 'bookings_idempotency';
	$now        = gmdate( 'Y-m-d H:i:s' );

	$stats = array(
		'total'      => 0,
		'processing' => 0,
		'completed'  => 0,
		'failed'     => 0,
		'expired'    => 0,
	);

	if ( ! bookit_idempotency_table_exists() ) {
		return $stats;
	}

	// Get counts by status.
	// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
	$results = $wpdb->get_results(
		"SELECT status, COUNT(*) as count FROM {$table_name} GROUP BY status",
		ARRAY_A
	);

	foreach ( $results as $row ) {
		$status = $row['status'];
		$count  = (int) $row['count'];

		if ( isset( $stats[ $status ] ) ) {
			$stats[ $status ] = $count;
		}
		$stats['total'] += $count;
	}

	// Get expired count.
	// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
	$stats['expired'] = (int) $wpdb->get_var(
		$wpdb->prepare(
			"SELECT COUNT(*) FROM {$table_name} WHERE expires_at < %s",
			$now
		)
	);

	return $stats;
}
