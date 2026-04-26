<?php
// tests/bootstrap.php

$_tests_dir = getenv( 'WP_TESTS_DIR' ) ?: '/tmp/wordpress-tests-lib';

if ( ! file_exists( $_tests_dir . '/includes/functions.php' ) ) {
	die( "ERROR: WordPress test library not found. Is wp-env running?\n" );
}

if ( ! defined( 'WP_DEBUG' ) ) {
	define( 'WP_DEBUG', false );
}
if ( ! defined( 'WP_DEBUG_DISPLAY' ) ) {
	define( 'WP_DEBUG_DISPLAY', false );
}
ini_set( 'log_errors', '0' );

require_once $_tests_dir . '/includes/functions.php';

function _manually_load_plugins() {
	require dirname( __DIR__, 2 ) . '/bookit-booking-system/bookit-booking-system.php';
	require dirname( __DIR__ ) . '/bookit-meetings.php';
}
tests_add_filter( 'muplugins_loaded', '_manually_load_plugins' );

require $_tests_dir . '/includes/bootstrap.php';

