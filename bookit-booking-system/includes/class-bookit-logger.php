<?php
/**
 * Error logging system.
 *
 * @package    Bookit_Booking_System
 * @subpackage Bookit_Booking_System/includes
 */

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
	die;
}

/**
 * Logger class.
 */
class Bookit_Logger {

	/**
	 * Log directory path.
	 *
	 * @var string
	 */
	private static $log_dir = '';

	/**
	 * Initialize logger.
	 *
	 * @return void
	 */
	public static function init() {
		// Try to store logs OUTSIDE web root for maximum security
		// WP_CONTENT_DIR = /path/to/site/app/public/wp-content
		// dirname(WP_CONTENT_DIR) = /path/to/site/app/public
		// We want /path/to/site/app/booking-logs (outside public directory)
		
		$outside_root   = dirname( WP_CONTENT_DIR ) . '/booking-logs';
		$inside_uploads = wp_upload_dir()['basedir'] . '/bookings/logs';
		
		// Determine best log location
		if ( self::can_create_directory( dirname( $outside_root ) ) ) {
			// Preferred: Outside web root (not accessible via HTTP)
			self::$log_dir = $outside_root;
		} else {
			// Fallback: Inside uploads directory with protection
			self::$log_dir = $inside_uploads;
		}

		// Ensure log directory exists
		if ( ! file_exists( self::$log_dir ) ) {
			wp_mkdir_p( self::$log_dir );
			
			// Add .htaccess for Apache servers
			$htaccess_content  = "# Booking System - Deny all access to log files\n";
			$htaccess_content .= "Order deny,allow\n";
			$htaccess_content .= "Deny from all\n";
			// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
			@file_put_contents( self::$log_dir . '/.htaccess', $htaccess_content );
			
			// Add index.php to prevent directory listing
			$index_content = "<?php\n// Silence is golden.\n";
			// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
			@file_put_contents( self::$log_dir . '/index.php', $index_content );
			
			// Add README for documentation
			$readme_content  = "# Booking System Log Files\n\n";
			$readme_content .= "This directory contains log files for the Booking System plugin.\n";
			$readme_content .= "Log files are retained for 28 days and automatically cleaned up.\n\n";
			$readme_content .= "SECURITY: These files should NOT be accessible via HTTP.\n";
			$readme_content .= "Location: " . self::$log_dir . "\n";
			$readme_content .= "Created: " . date( 'Y-m-d H:i:s' ) . "\n";
			// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
			@file_put_contents( self::$log_dir . '/README.txt', $readme_content );
		}
	}

	/**
	 * Check if we can create a directory in the given parent path.
	 *
	 * @param string $parent_path Parent directory path.
	 * @return bool True if writable.
	 */
	private static function can_create_directory( $parent_path ) {
		// Check if parent directory exists and is writable
		if ( ! file_exists( $parent_path ) ) {
			return false;
		}
		
		if ( ! is_writable( $parent_path ) ) {
			return false;
		}
		
		return true;
	}

	/**
	 * Log INFO level message.
	 *
	 * @param string $message Log message.
	 * @param array  $context Additional context data.
	 * @return void
	 */
	public static function info( $message, $context = array() ) {
		self::log( 'INFO', $message, $context );
	}

	/**
	 * Log WARNING level message.
	 *
	 * @param string $message Log message.
	 * @param array  $context Additional context data.
	 * @return void
	 */
	public static function warning( $message, $context = array() ) {
		self::log( 'WARNING', $message, $context );
	}

	/**
	 * Log ERROR level message.
	 *
	 * @param string $message Log message.
	 * @param array  $context Additional context data.
	 * @return void
	 */
	public static function error( $message, $context = array() ) {
		self::log( 'ERROR', $message, $context );
	}

	/**
	 * Write log entry.
	 *
	 * @param string $level   Log level (INFO, WARNING, ERROR).
	 * @param string $message Log message.
	 * @param array  $context Additional context data.
	 * @return void
	 */
	private static function log( $level, $message, $context = array() ) {
		self::init();

		// Sanitize sensitive data from context
		$context = self::sanitize_context( $context );

		// Format timestamp
		$timestamp = current_time( 'Y-m-d H:i:s' );

		// Build log entry
		$log_entry = sprintf(
			'[%s] [%s] %s',
			$timestamp,
			$level,
			$message
		);

		// Add context if provided
		if ( ! empty( $context ) ) {
			$log_entry .= ' | Context: ' . wp_json_encode( $context );
		}

		$log_entry .= "\n";

		// Get log file path (daily rotation)
		$log_file = self::get_log_file();

		// Write to log file
		// Suppress errors to prevent breaking the application
		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
		@file_put_contents( $log_file, $log_entry, FILE_APPEND );

		// Also write to WordPress debug.log if WP_DEBUG is enabled
		if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
			error_log( '[Bookit Booking System] ' . $log_entry );
		}
	}

	/**
	 * Get current log file path.
	 *
	 * @return string Log file path.
	 */
	private static function get_log_file() {
		$date     = current_time( 'Y-m-d' );
		$filename = 'bookings-' . $date . '.log';
		return self::$log_dir . '/' . $filename;
	}

	/**
	 * Sanitize context data to remove sensitive information.
	 *
	 * @param array $context Context data.
	 * @return array Sanitized context.
	 */
	private static function sanitize_context( $context ) {
		if ( ! is_array( $context ) ) {
			return $context;
		}

		// List of sensitive keys to redact
		$sensitive_keys = array(
			'password',
			'password_hash',
			'api_key',
			'secret',
			'secret_key',
			'stripe_secret',
			'paypal_secret',
			'card_number',
			'cvv',
			'cvc',
			'credit_card',
		);

		foreach ( $context as $key => $value ) {
			// Check if key contains sensitive data
			$key_lower = strtolower( $key );
			foreach ( $sensitive_keys as $sensitive ) {
				if ( strpos( $key_lower, $sensitive ) !== false ) {
					$context[ $key ] = '[REDACTED]';
					break;
				}
			}

			// Recursively sanitize nested arrays
			if ( is_array( $value ) ) {
				$context[ $key ] = self::sanitize_context( $value );
			}
		}

		return $context;
	}

	/**
	 * Clean up old log files (keep 28 days).
	 *
	 * Called by scheduled cron job.
	 *
	 * @return void
	 */
	public static function cleanup_old_logs() {
		self::init();

		$retention_days = 28; // Keep 4 weeks
		$cutoff_time    = strtotime( "-{$retention_days} days" );

		// Get all log files
		$log_files = glob( self::$log_dir . '/bookings-*.log' );

		if ( empty( $log_files ) ) {
			return;
		}

		$deleted_count = 0;

		foreach ( $log_files as $log_file ) {
			// Get file modification time
			$file_time = filemtime( $log_file );

			// Delete if older than retention period
			if ( $file_time < $cutoff_time ) {
				// phpcs:ignore WordPress.WP.AlternativeFunctions.unlink_unlink
				if ( @unlink( $log_file ) ) {
					$deleted_count++;
				}
			}
		}

		if ( $deleted_count > 0 ) {
			self::info(
				"Cleaned up {$deleted_count} old log files (older than {$retention_days} days)",
				array(
					'deleted_count' => $deleted_count,
					'retention_days' => $retention_days,
				)
			);
		}
	}

	/**
	 * Get all log files (for admin viewing).
	 *
	 * @return array Array of log file paths.
	 */
	public static function get_log_files() {
		self::init();
		$log_files = glob( self::$log_dir . '/bookings-*.log' );
		return $log_files ? $log_files : array();
	}

	/**
	 * Get log file contents (for admin viewing).
	 *
	 * @param string $date Date in YYYY-MM-DD format.
	 * @return string|false Log contents or false if not found.
	 */
	public static function get_log_contents( $date ) {
		self::init();
		$filename = 'bookings-' . $date . '.log';
		$filepath = self::$log_dir . '/' . $filename;

		if ( ! file_exists( $filepath ) ) {
			return false;
		}

		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
		return file_get_contents( $filepath );
	}

	/**
	 * Get today's log file path.
	 *
	 * @return string Log file path.
	 */
	public static function get_todays_log_file() {
		return self::get_log_file();
	}

	/**
	 * Check if logging is working.
	 *
	 * @return bool True if can write to log.
	 */
	public static function test_logging() {
		self::init();

		$test_message = 'Test log entry - ' . time();
		$log_file     = self::get_log_file();

		// Try to write test message
		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
		$result = @file_put_contents( $log_file, $test_message . "\n", FILE_APPEND );

		return $result !== false;
	}

	/**
	 * Get log directory path.
	 *
	 * @return string Log directory path.
	 */
	public static function get_log_directory() {
		self::init();
		return self::$log_dir;
	}

	/**
	 * Check if logs are stored outside web root (secure).
	 *
	 * @return bool True if outside web root.
	 */
	public static function is_secure_location() {
		self::init();
		
		// Check if log directory is outside ABSPATH (WordPress root)
		$log_dir_real = realpath( self::$log_dir );
		$abspath_real = realpath( ABSPATH );
		
		// If log directory is NOT inside ABSPATH, it's secure
		return strpos( $log_dir_real, $abspath_real ) === false;
	}

	/**
	 * Migrate logs from old location to new location.
	 *
	 * Called during plugin activation if needed.
	 *
	 * @return void
	 */
	public static function migrate_logs_if_needed() {
		self::init();
		
		// Old location: wp-content/uploads/bookings/logs
		$old_location = wp_upload_dir()['basedir'] . '/bookings/logs';
		$new_location = self::$log_dir;
		
		// If already using the same location, nothing to do
		if ( $old_location === $new_location ) {
			return;
		}
		
		// If old location doesn't exist, nothing to migrate
		if ( ! file_exists( $old_location ) ) {
			return;
		}
		
		// Get all log files from old location
		$old_files = glob( $old_location . '/bookings-*.log' );
		
		if ( empty( $old_files ) ) {
			return;
		}
		
		$migrated_count = 0;
		
		foreach ( $old_files as $old_file ) {
			$filename = basename( $old_file );
			$new_file = $new_location . '/' . $filename;
			
			// Copy file to new location
			// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_copy
			if ( @copy( $old_file, $new_file ) ) {
				// Preserve file modification time
				$old_time = filemtime( $old_file );
				touch( $new_file, $old_time );
				
				// Delete old file after successful copy
				// phpcs:ignore WordPress.WP.AlternativeFunctions.unlink_unlink
				@unlink( $old_file );
				$migrated_count++;
			}
		}
		
		if ( $migrated_count > 0 ) {
			self::info( "Migrated {$migrated_count} log files to new secure location", array(
				'old_location' => $old_location,
				'new_location' => $new_location,
				'is_secure'    => self::is_secure_location(),
			) );
		}
		
		// Try to remove old directory (only if empty)
		// phpcs:ignore WordPress.WP.AlternativeFunctions.unlink_unlink
		@rmdir( $old_location );
		// phpcs:ignore WordPress.WP.AlternativeFunctions.unlink_unlink
		@rmdir( dirname( $old_location ) ); // Try to remove parent /bookings/ if empty
	}
}
