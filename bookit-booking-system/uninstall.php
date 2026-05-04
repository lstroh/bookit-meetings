<?php
/**
 * Fired when the plugin is uninstalled.
 *
 * @package Bookit_Booking_System
 */

// If uninstall not called from WordPress, then exit.
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

/**
 * NOTE: Uninstall currently does NOT delete data.
 * This preserves customer bookings, payment records, etc.
 *
 * To enable data deletion on uninstall:
 * 1. Uncomment the code below
 * 2. Add a settings option for "Delete data on uninstall"
 * 3. Only delete if that option is checked
 */

// Global database object.
global $wpdb;

// Uncomment to enable data deletion.
/*
// Delete all database tables.
$tables = array(
	$wpdb->prefix . 'bookings',
	$wpdb->prefix . 'bookings_services',
	$wpdb->prefix . 'bookings_categories',
	$wpdb->prefix . 'bookings_service_categories',
	$wpdb->prefix . 'bookings_staff',
	$wpdb->prefix . 'bookings_staff_services',
	$wpdb->prefix . 'bookings_customers',
	$wpdb->prefix . 'bookings_payments',
	$wpdb->prefix . 'bookings_working_hours',
	$wpdb->prefix . 'bookings_settings',
);

foreach ( $tables as $table ) {
	$wpdb->query( "DROP TABLE IF EXISTS $table" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
}

// Delete all plugin options.
delete_option( 'bookit_version' );
delete_option( 'bookit_db_version' );
delete_option( 'bookit_settings' );

// Delete log directory.
$upload_dir = wp_upload_dir();
$base_dir   = isset( $upload_dir['basedir'] ) ? $upload_dir['basedir'] : '';
$log_dir    = trailingslashit( $base_dir ) . 'bookings/logs';

if ( ! empty( $base_dir ) && file_exists( $log_dir ) ) {
	$log_files = glob( trailingslashit( $log_dir ) . '*.*' );
	if ( is_array( $log_files ) ) {
		array_map( 'unlink', $log_files );
	}
	rmdir( $log_dir );
}
*/
