<?php
/**
 * Idempotency Cleanup Cron Job
 *
 * Cleans up expired idempotency records daily.
 * Records are deleted when their expires_at timestamp has passed.
 *
 * Sprint 2, Task 6
 *
 * @package    Bookit_Booking_System
 * @subpackage Bookit_Booking_System/includes/cron
 */

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
	die;
}

/**
 * Idempotency cleanup cron class.
 */
class Bookit_Idempotency_Cleanup {

	/**
	 * Cron hook name.
	 *
	 * @var string
	 */
	const CRON_HOOK = 'bookit_cleanup_expired_idempotency';

	/**
	 * Default time to run cleanup (03:00 AM).
	 *
	 * @var string
	 */
	const DEFAULT_SCHEDULE_TIME = '03:00:00';

	/**
	 * Run the cleanup.
	 *
	 * Deletes idempotency records that have expired.
	 * This includes both completed and failed operations past their expiry time.
	 *
	 * @return int Number of records deleted.
	 */
	public static function run_cleanup(): int {
		// Load the idempotency handler if not already loaded.
		$handler_file = dirname( __DIR__ ) . '/core/class-idempotency-handler.php';

		if ( ! class_exists( 'Booking_System_Idempotency_Handler' ) ) {
			if ( ! file_exists( $handler_file ) ) {
				if ( class_exists( 'Bookit_Logger' ) ) {
					Bookit_Logger::warning( 'Idempotency cleanup: Handler file not found' );
				}
				return 0;
			}
			require_once $handler_file;
		}

		$handler = new Booking_System_Idempotency_Handler();
		$deleted = $handler->cleanup_expired();

		// Log the cleanup result.
		if ( class_exists( 'Bookit_Logger' ) ) {
			Bookit_Logger::info(
				'Idempotency cleanup completed',
				array(
					'deleted_count' => $deleted,
					'timestamp'     => gmdate( 'Y-m-d H:i:s' ),
				)
			);
		}

		return $deleted;
	}

	/**
	 * Register cron event on plugin activation.
	 *
	 * Schedules daily cleanup at 3:00 AM local time.
	 *
	 * @return void
	 */
	public static function register_cron(): void {
		if ( ! wp_next_scheduled( self::CRON_HOOK ) ) {
			// Schedule at 3:00 AM to avoid peak hours.
			$schedule_time = strtotime( 'tomorrow ' . self::DEFAULT_SCHEDULE_TIME );

			// If strtotime fails, fall back to current time + 24 hours.
			if ( false === $schedule_time ) {
				$schedule_time = time() + DAY_IN_SECONDS;
			}

			wp_schedule_event(
				$schedule_time,
				'daily',
				self::CRON_HOOK
			);

			if ( class_exists( 'Bookit_Logger' ) ) {
				Bookit_Logger::info(
					'Idempotency cleanup cron registered',
					array(
						'next_run' => gmdate( 'Y-m-d H:i:s', $schedule_time ),
					)
				);
			}
		}
	}

	/**
	 * Unregister cron event on plugin deactivation.
	 *
	 * @return void
	 */
	public static function unregister_cron(): void {
		$timestamp = wp_next_scheduled( self::CRON_HOOK );

		if ( $timestamp ) {
			wp_unschedule_event( $timestamp, self::CRON_HOOK );

			if ( class_exists( 'Bookit_Logger' ) ) {
				Bookit_Logger::info( 'Idempotency cleanup cron unregistered' );
			}
		}
	}

	/**
	 * Check if cleanup cron is scheduled.
	 *
	 * @return bool True if scheduled.
	 */
	public static function is_scheduled(): bool {
		return false !== wp_next_scheduled( self::CRON_HOOK );
	}

	/**
	 * Get the next scheduled run time.
	 *
	 * @return int|false Timestamp of next run, or false if not scheduled.
	 */
	public static function get_next_scheduled() {
		return wp_next_scheduled( self::CRON_HOOK );
	}

	/**
	 * Force immediate cleanup (for testing or manual triggers).
	 *
	 * @return int Number of records deleted.
	 */
	public static function force_cleanup(): int {
		if ( class_exists( 'Bookit_Logger' ) ) {
			Bookit_Logger::info( 'Idempotency cleanup: Manual trigger initiated' );
		}

		return self::run_cleanup();
	}

	/**
	 * Get cleanup statistics.
	 *
	 * @return array{scheduled: bool, next_run: string|null, last_run: string|null}
	 */
	public static function get_stats(): array {
		$next_scheduled = wp_next_scheduled( self::CRON_HOOK );
		$last_run       = get_option( 'bookit_idempotency_last_cleanup', null );

		return array(
			'scheduled' => false !== $next_scheduled,
			'next_run'  => $next_scheduled ? gmdate( 'Y-m-d H:i:s', $next_scheduled ) : null,
			'last_run'  => $last_run,
		);
	}

	/**
	 * Initialize the cron hook.
	 *
	 * This method should be called during plugin initialization
	 * to register the cron action handler.
	 *
	 * @return void
	 */
	public static function init(): void {
		// Register the cron action handler.
		add_action( self::CRON_HOOK, array( __CLASS__, 'run_cleanup_with_tracking' ) );
	}

	/**
	 * Run cleanup with last-run tracking.
	 *
	 * This is the callback for the cron action. It runs cleanup
	 * and records the last run time for monitoring.
	 *
	 * @return void
	 */
	public static function run_cleanup_with_tracking(): void {
		$deleted = self::run_cleanup();

		// Record the last run time and result.
		update_option(
			'bookit_idempotency_last_cleanup',
			gmdate( 'Y-m-d H:i:s' ),
			false // Don't autoload.
		);

		update_option(
			'bookit_idempotency_last_cleanup_count',
			$deleted,
			false // Don't autoload.
		);
	}
}
