<?php
/**
 * Global migration helper functions.
 *
 * @package    Bookit_Booking_System
 * @subpackage Bookit_Booking_System/includes
 */

if ( ! defined( 'WPINC' ) ) {
	die;
}

/**
 * Register a migration path for an extension plugin.
 * Extensions call this on the plugins_loaded hook.
 *
 * @param string $plugin_slug Extension's plugin slug (e.g. 'bookit-recurring').
 * @param string $path        Absolute filesystem path to the migrations directory.
 * @return void
 */
function bookit_register_migration_path( string $plugin_slug, string $path ): void {
	Bookit_Migration_Runner::register_migration_path( $plugin_slug, $path );
}

// Register the core plugin migration path so new numbered migrations (including 0009) are discoverable.
if ( defined( 'BOOKIT_PLUGIN_DIR' ) ) {
	bookit_register_migration_path( 'bookit-booking-system', BOOKIT_PLUGIN_DIR . 'database/migrations/' );
}
