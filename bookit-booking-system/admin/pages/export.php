<?php
/**
 * Export customers page (WordPress admin).
 *
 * @package Booking_System
 */

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
	die;
}

// Check user capabilities
if ( ! current_user_can( 'manage_options' ) ) {
	wp_die( esc_html__( 'You do not have sufficient permissions to access this page.', 'bookit-booking-system' ) );
}
?>

<div class="wrap">
	<h1><?php esc_html_e( 'Export Customers', 'bookit-booking-system' ); ?></h1>
	<hr class="wp-header-end">

	<div class="booking-admin-notice notice notice-info">
		<p>
			<strong><?php esc_html_e( 'Sprint 0: Foundation Phase', 'bookit-booking-system' ); ?></strong>
		</p>
		<p>
			<?php esc_html_e( 'Customer export functionality will be implemented in Sprint 2 (GDPR compliance).', 'bookit-booking-system' ); ?>
		</p>
	</div>
</div>
