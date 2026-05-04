<?php
/**
 * Session cleanup cron job.
 *
 * Cleans abandoned booking wizard sessions older than 24 hours.
 *
 * @package    Bookit_Booking_System
 * @subpackage Bookit_Booking_System/includes/cron
 */

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
	die;
}

/**
 * Session cleanup cron class.
 */
class Bookit_Session_Cleanup {

	/**
	 * Cron hook name.
	 *
	 * @var string
	 */
	const CRON_HOOK = 'bookit_cleanup_abandoned_sessions';

	/**
	 * Age threshold in seconds (24 hours).
	 *
	 * @var int
	 */
	const SESSION_MAX_AGE = 86400;

	/**
	 * Search strings to identify booking session files.
	 *
	 * @var array
	 */
	const BOOKING_MARKERS = array( 'bookit_wizard', 'bookit_booking' );

	/**
	 * Run the cleanup.
	 *
	 * Finds session files containing booking data that are older than 24 hours
	 * and deletes them safely.
	 *
	 * @param string|null $path Optional directory path for testing. If null, uses session_save_path().
	 * @return void
	 */
	public static function run_cleanup( $path = null ) {
		$save_path = null !== $path ? $path : session_save_path();

		if ( empty( $save_path ) || ! is_dir( $save_path ) ) {
			// Use default if session path is not set.
			$save_path = sys_get_temp_dir();
		}

		$deleted_count = 0;
		$checked_count = 0;
		$cutoff_time   = time() - self::SESSION_MAX_AGE;

		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_opendir
		$handle = @opendir( $save_path );

		if ( ! $handle ) {
			Bookit_Logger::warning(
				'Session cleanup: Cannot read session directory',
				array( 'path' => $save_path )
			);
			return;
		}

		while ( false !== ( $file = readdir( $handle ) ) ) {
			if ( '.' === $file || '..' === $file ) {
				continue;
			}

			$filepath = $save_path . DIRECTORY_SEPARATOR . $file;

			// Only process session files (sess_* or similar).
			if ( ! is_file( $filepath ) ) {
				continue;
			}

			// Check modification time before reading content.
			$mtime = filemtime( $filepath );
			if ( false === $mtime || $mtime > $cutoff_time ) {
				continue;
			}

			$checked_count++;

			// Check if file contains booking session data.
			if ( ! self::is_booking_session_file( $filepath ) ) {
				continue;
			}

			// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_unlink
			if ( @unlink( $filepath ) ) {
				$deleted_count++;
			}
		}

		closedir( $handle );

		Bookit_Logger::info(
			'Session cleanup completed',
			array(
				'deleted' => $deleted_count,
				'checked' => $checked_count,
				'path'    => $save_path,
			)
		);
	}

	/**
	 * Check if session file contains booking data.
	 *
	 * @param string $filepath Full path to session file.
	 * @return bool True if booking session.
	 */
	private static function is_booking_session_file( $filepath ) {
		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
		$content = @file_get_contents( $filepath, false, null, 0, 4096 );

		if ( false === $content || '' === $content ) {
			return false;
		}

		foreach ( self::BOOKING_MARKERS as $marker ) {
			if ( strpos( $content, $marker ) !== false ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Register cron event on plugin activation.
	 *
	 * @return void
	 */
	public static function register_cron() {
		if ( ! wp_next_scheduled( self::CRON_HOOK ) ) {
			wp_schedule_event(
				strtotime( '03:30:00' ),
				'daily',
				self::CRON_HOOK
			);
		}
	}

	/**
	 * Unregister cron event on plugin deactivation.
	 *
	 * @return void
	 */
	public static function unregister_cron() {
		$timestamp = wp_next_scheduled( self::CRON_HOOK );
		if ( $timestamp ) {
			wp_unschedule_event( $timestamp, self::CRON_HOOK );
		}
	}
}
