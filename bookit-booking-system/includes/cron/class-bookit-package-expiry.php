<?php
/**
 * Package Expiry Cron Job
 *
 * Expires overdue customer packages daily.
 *
 * @package    Bookit_Booking_System
 * @subpackage Bookit_Booking_System/includes/cron
 */

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
	die;
}

/**
 * Package expiry cron class.
 */
class Bookit_Package_Expiry {

	/**
	 * Cron hook name.
	 *
	 * @var string
	 */
	const CRON_HOOK = 'bookit_expire_packages';

	/**
	 * Run the cleanup.
	 *
	 * Expires active customer packages whose expires_at is in the past.
	 *
	 * @return int Number of packages expired.
	 */
	public static function run_cleanup(): int {
		global $wpdb;

		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT id FROM {$wpdb->prefix}bookings_customer_packages
				WHERE status = 'active'
					AND expires_at IS NOT NULL
					AND expires_at <= %s",
				current_time( 'mysql', true )
			),
			ARRAY_A
		);

		if ( empty( $rows ) ) {
			return 0;
		}

		$expired_count = 0;

		foreach ( $rows as $row ) {
			$updated = $wpdb->update(
				$wpdb->prefix . 'bookings_customer_packages',
				array(
					'status'     => 'expired',
					'updated_at' => current_time( 'mysql' ),
				),
				array(
					'id'     => (int) $row['id'],
					'status' => 'active',
				),
				array( '%s', '%s' ),
				array( '%d', '%s' )
			);

			if ( $updated ) {
				++$expired_count;

				if ( class_exists( 'Bookit_Audit_Logger' ) ) {
					Bookit_Audit_Logger::log(
						'customer_package.expired',
						'customer_package',
						(int) $row['id'],
						array(
							'expired_by' => 'cron',
							'expired_at' => current_time( 'mysql' ),
						)
					);
				}
			}
		}

		if ( class_exists( 'Bookit_Logger' ) ) {
			Bookit_Logger::info(
				'Package expiry cron completed',
				array(
					'expired_count' => $expired_count,
					'timestamp'     => gmdate( 'Y-m-d H:i:s' ),
				)
			);
		}

		return $expired_count;
	}

	/**
	 * Register cron event on plugin activation.
	 *
	 * @return void
	 */
	public static function register_cron(): void {
		if ( ! wp_next_scheduled( self::CRON_HOOK ) ) {
			$schedule_time = strtotime( 'tomorrow 02:00:00' );
			if ( false === $schedule_time ) {
				$schedule_time = time() + DAY_IN_SECONDS;
			}
			wp_schedule_event( $schedule_time, 'daily', self::CRON_HOOK );
			if ( class_exists( 'Bookit_Logger' ) ) {
				Bookit_Logger::info(
					'Package expiry cron registered',
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
				Bookit_Logger::info( 'Package expiry cron unregistered' );
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
			Bookit_Logger::info( 'Package expiry cleanup: Manual trigger initiated' );
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
		$last_run       = get_option( 'bookit_package_expiry_last_run', null );

		return array(
			'scheduled' => false !== $next_scheduled,
			'next_run'  => $next_scheduled ? gmdate( 'Y-m-d H:i:s', $next_scheduled ) : null,
			'last_run'  => $last_run,
		);
	}

	/**
	 * Initialize the cron hook.
	 *
	 * @return void
	 */
	public static function init(): void {
		add_action( self::CRON_HOOK, array( __CLASS__, 'run_cleanup_with_tracking' ) );
	}

	/**
	 * Run cleanup with last-run tracking.
	 *
	 * @return void
	 */
	public static function run_cleanup_with_tracking(): void {
		$deleted = self::run_cleanup();

		update_option(
			'bookit_package_expiry_last_run',
			gmdate( 'Y-m-d H:i:s' ),
			false
		);

		update_option(
			'bookit_package_expiry_last_count',
			$deleted,
			false
		);
	}
}
