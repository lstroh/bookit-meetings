<?php
/**
 * Plugin Name: Bookit Meetings
 * Plugin URI:  https://example.com
 * Description: Online meeting link support for the Bookit Booking System.
 * Version:     1.0.0
 * Requires at least: 6.0
 * Requires PHP:      8.0
 * Author:      Wimbledon Smart
 * License:     GPL v2 or later
 * Text Domain: bookit-meetings
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'BOOKIT_MEETINGS_VERSION', '1.0.0' );
define( 'BOOKIT_MEETINGS_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'BOOKIT_MEETINGS_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'BOOKIT_MEETINGS_REQUIRES_CORE', '1.5.0' );

/**
 * Plugin activation hook.
 *
 * @return void
 */
function bookit_meetings_activate(): void {
	if ( ! class_exists( 'Bookit_Migration_Runner' ) ) {
		return;
	}

	bookit_register_migration_path(
		'bookit-meetings',
		BOOKIT_MEETINGS_PLUGIN_DIR . 'database/migrations/'
	);
	Bookit_Migration_Runner::run_pending( 'bookit-meetings' );
}

/**
 * Plugin deactivation hook.
 *
 * @return void
 */
function bookit_meetings_deactivate(): void {
	if ( ! class_exists( 'Bookit_Migration_Runner' ) ) {
		return;
	}

	bookit_register_migration_path(
		'bookit-meetings',
		BOOKIT_MEETINGS_PLUGIN_DIR . 'database/migrations/'
	);
	Bookit_Migration_Runner::rollback_last( 'bookit-meetings' );
}

register_activation_hook( __FILE__, 'bookit_meetings_activate' );
register_deactivation_hook( __FILE__, 'bookit_meetings_deactivate' );

add_action(
	'plugins_loaded',
	function (): void {
		if ( ! function_exists( 'bookit_register_extension' ) ) {
			return;
		}

		$result = bookit_register_extension(
			array(
				'name'          => 'Bookit Meetings',
				'slug'          => 'bookit-meetings',
				'version'       => BOOKIT_MEETINGS_VERSION,
				'requires_core' => BOOKIT_MEETINGS_REQUIRES_CORE,
				'description'   => 'Online meeting link support for the Bookit Booking System.',
				'author'        => 'Wimbledon Smart',
			)
		);

		if ( is_wp_error( $result ) ) {
			$message = sprintf(
				'Bookit Meetings extension registration failed: %s',
				(string) $result->get_error_message()
			);

			if ( class_exists( 'Bookit_Logger' ) ) {
				Bookit_Logger::error(
					$message,
					array(
						'error_code' => $result->get_error_code(),
						'error_data' => $result->get_error_data(),
					)
				);
			} else {
				error_log( $message ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
			}

			return;
		}

		require_once BOOKIT_MEETINGS_PLUGIN_DIR . 'includes/class-bookit-meetings-loader.php';
		( new Bookit_Meetings_Loader() )->init();
	},
	5
);

