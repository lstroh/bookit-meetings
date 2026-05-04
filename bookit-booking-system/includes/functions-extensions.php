<?php
/**
 * Extension registration helper functions.
 *
 * @package    Bookit_Booking_System
 * @subpackage Bookit_Booking_System/includes
 */

if ( ! defined( 'WPINC' ) ) {
	die;
}

/**
 * Register a Bookit extension plugin.
 *
 * Call this on plugins_loaded (priority 5).
 *
 * @param array $args Extension registration args.
 * @return true|WP_Error
 */
function bookit_register_extension( array $args ): true|WP_Error {
	return Bookit_Extension_Registry::register_extension( $args );
}

/**
 * Register a dashboard sidebar nav item for an extension.
 *
 * @param array $args Nav item registration args.
 * @return true|WP_Error
 */
function bookit_register_nav_item( array $args ): true|WP_Error {
	return Bookit_Extension_Registry::register_nav_item( $args );
}
