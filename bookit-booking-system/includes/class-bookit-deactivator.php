<?php
/**
 * Fired during plugin deactivation.
 *
 * @package    Bookit_Booking_System
 * @subpackage Bookit_Booking_System/includes
 */

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
	die;
}

/**
 * Fired during plugin deactivation.
 */
class Bookit_Deactivator {

	/**
	 * Deactivation tasks.
	 *
	 * - Clear scheduled events
	 * - Flush rewrite rules
	 * - DO NOT delete database tables (preserve data)
	 * - DO NOT delete settings
	 *
	 * @return void
	 */
	public static function deactivate() {
		// Clear any scheduled cron events.
		$timestamp = wp_next_scheduled( 'bookit_cleanup_logs' );
		if ( $timestamp ) {
			wp_unschedule_event( $timestamp, 'bookit_cleanup_logs' );
		}

		require_once BOOKIT_PLUGIN_DIR . 'includes/cron/class-session-cleanup.php';
		Bookit_Session_Cleanup::unregister_cron();

		// Unregister idempotency cleanup cron - Sprint 2, Task 6.
		require_once BOOKIT_PLUGIN_DIR . 'includes/cron/class-idempotency-cleanup.php';
		Bookit_Idempotency_Cleanup::unregister_cron();

		require_once BOOKIT_PLUGIN_DIR . 'includes/cron/class-bookit-package-expiry.php';
		Bookit_Package_Expiry::unregister_cron();

		require_once BOOKIT_PLUGIN_DIR . 'includes/cron/class-bookit-staff-digest-daily.php';
		Bookit_Staff_Digest_Daily::unregister_cron();

		require_once BOOKIT_PLUGIN_DIR . 'includes/cron/class-bookit-staff-digest-weekly.php';
		Bookit_Staff_Digest_Weekly::unregister_cron();

		require_once BOOKIT_PLUGIN_DIR . 'includes/cron/class-bookit-staff-schedule-daily.php';
		Bookit_Staff_Schedule_Daily::unregister_cron();

		// Flush rewrite rules.
		flush_rewrite_rules();

		// Log deactivation.
		require_once BOOKIT_PLUGIN_DIR . 'includes/class-bookit-logger.php';
		Bookit_Logger::info( 'Plugin deactivated', array( 'deactivated_at' => current_time( 'mysql' ) ) );
	}
}
