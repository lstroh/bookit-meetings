<?php
/**
 * Bookings list page (WordPress admin).
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
	<h1 class="wp-heading-inline"><?php esc_html_e( 'Bookings', 'bookit-booking-system' ); ?></h1>
	<a href="<?php echo esc_url( admin_url( 'admin.php?page=booking-add-new' ) ); ?>" class="page-title-action">
		<?php esc_html_e( 'Add New', 'bookit-booking-system' ); ?>
	</a>
	<hr class="wp-header-end">

	<div class="booking-admin-notice notice notice-info">
		<p>
			<strong><?php esc_html_e( 'Sprint 0: Foundation Phase', 'bookit-booking-system' ); ?></strong>
		</p>
		<p>
			<?php esc_html_e( 'This is a placeholder page. Booking management functionality will be implemented in Sprint 1-2.', 'bookit-booking-system' ); ?>
		</p>
		<p>
			<?php esc_html_e( 'Expected features:', 'bookit-booking-system' ); ?>
		</p>
		<ul>
			<li><?php esc_html_e( 'View all bookings in table format', 'bookit-booking-system' ); ?></li>
			<li><?php esc_html_e( 'Filter by date, status, staff, customer', 'bookit-booking-system' ); ?></li>
			<li><?php esc_html_e( 'Quick actions: Confirm, Cancel, Edit', 'bookit-booking-system' ); ?></li>
			<li><?php esc_html_e( 'Bulk actions for multiple bookings', 'bookit-booking-system' ); ?></li>
		</ul>
	</div>

	<div class="booking-placeholder-content">
		<h2><?php esc_html_e( 'Recent Bookings', 'bookit-booking-system' ); ?></h2>
		<p><?php esc_html_e( 'No bookings yet. Booking list will appear here.', 'bookit-booking-system' ); ?></p>
	</div>
</div>
