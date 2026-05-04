<?php
/**
 * Dashboard logout handler.
 *
 * @package Bookit_Booking_System
 */

// If this file is called directly, abort.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

require_once BOOKIT_PLUGIN_DIR . 'includes/class-bookit-session.php';
require_once BOOKIT_PLUGIN_DIR . 'includes/class-bookit-auth.php';

Bookit_Auth::logout();

wp_redirect( home_url( '/bookit-dashboard/?logged_out=1' ) );
exit;

