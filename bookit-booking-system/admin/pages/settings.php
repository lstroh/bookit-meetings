<?php
/**
 * Settings page (WordPress admin).
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
	<h1><?php esc_html_e( 'Booking System Settings', 'bookit-booking-system' ); ?></h1>
	<hr class="wp-header-end">

	<div class="booking-admin-notice notice notice-info">
		<p>
			<strong><?php esc_html_e( 'Sprint 0: Foundation Phase', 'bookit-booking-system' ); ?></strong>
		</p>
		<p>
			<?php esc_html_e( 'Settings pages will be implemented across Sprints 1-3.', 'bookit-booking-system' ); ?>
		</p>
		<p>
			<?php esc_html_e( 'Expected settings tabs:', 'bookit-booking-system' ); ?>
		</p>
		<ul>
			<li><strong><?php esc_html_e( 'General:', 'bookit-booking-system' ); ?></strong> <?php esc_html_e( 'Business name, timezone, date/time formats, booking rules', 'bookit-booking-system' ); ?></li>
			<li><strong><?php esc_html_e( 'Payment:', 'bookit-booking-system' ); ?></strong> <?php esc_html_e( 'Stripe/PayPal API keys, deposit settings, refund policy', 'bookit-booking-system' ); ?></li>
			<li><strong><?php esc_html_e( 'Email:', 'bookit-booking-system' ); ?></strong> <?php esc_html_e( 'SMTP configuration, email templates, notification settings', 'bookit-booking-system' ); ?></li>
			<li><strong><?php esc_html_e( 'Calendar:', 'bookit-booking-system' ); ?></strong> <?php esc_html_e( 'Google Calendar sync, working hours, holidays', 'bookit-booking-system' ); ?></li>
		</ul>
	</div>

	<h2><?php esc_html_e( 'Current Settings (from activation):', 'bookit-booking-system' ); ?></h2>
	<?php
	$settings = get_option( 'bookit_settings', array() );
	if ( ! empty( $settings ) ) {
		echo '<pre>';
		print_r( $settings );
		echo '</pre>';
	} else {
		echo '<p>' . esc_html__( 'No settings found.', 'bookit-booking-system' ) . '</p>';
	}
	?>
</div>
