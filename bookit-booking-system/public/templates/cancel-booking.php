<?php
/**
 * Magic-link cancel booking page.
 *
 * Variables: $booking_id, $token, $rest_url (see Bookit_Shortcodes::render_cancel_booking).
 *
 * @package Bookit_Booking_System
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

global $wpdb;

// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
$booking = $wpdb->get_row(
	$wpdb->prepare(
		"SELECT b.id, b.booking_reference, b.booking_date, b.start_time,
				b.end_time, b.status, b.magic_link_token,
				s.name AS service_name,
				st.first_name AS staff_first_name,
				st.last_name AS staff_last_name
		 FROM {$wpdb->prefix}bookings b
		 LEFT JOIN {$wpdb->prefix}bookings_services s ON s.id = b.service_id
		 LEFT JOIN {$wpdb->prefix}bookings_staff st ON st.id = b.staff_id
		 WHERE b.id = %d",
		$booking_id
	),
	ARRAY_A
);

if ( ! $booking || ! hash_equals( (string) $booking['magic_link_token'], (string) $token ) ) {
	?>
<div class="bookit-confirmation-page bookit-magic-link-page">
	<div class="bookit-confirmation-card">
		<div class="bookit-magic-link-notice bookit-magic-link-notice--error">
			<h2 class="bookit-magic-link-notice__title"><?php esc_html_e( 'Invalid or expired link', 'bookit-booking-system' ); ?></h2>
			<p class="bookit-magic-link-notice__text"><?php esc_html_e( 'This link is not valid. Please use the link from your confirmation email or contact us for help.', 'bookit-booking-system' ); ?></p>
		</div>
	</div>
</div>
	<?php
	return;
}

$status = (string) $booking['status'];

if ( 'cancelled' === $status ) {
	?>
<div class="bookit-confirmation-page bookit-magic-link-page">
	<div class="bookit-confirmation-card">
		<div class="bookit-magic-link-notice bookit-magic-link-notice--neutral">
			<h2 class="bookit-magic-link-notice__title"><?php esc_html_e( 'Already cancelled', 'bookit-booking-system' ); ?></h2>
			<p class="bookit-magic-link-notice__text"><?php esc_html_e( 'This booking has already been cancelled.', 'bookit-booking-system' ); ?></p>
		</div>
	</div>
</div>
	<?php
	return;
}

if ( in_array( $status, array( 'completed', 'no_show' ), true ) ) {
	?>
<div class="bookit-confirmation-page bookit-magic-link-page">
	<div class="bookit-confirmation-card">
		<div class="bookit-magic-link-notice bookit-magic-link-notice--neutral">
			<h2 class="bookit-magic-link-notice__title"><?php esc_html_e( 'Cannot cancel this booking', 'bookit-booking-system' ); ?></h2>
			<p class="bookit-magic-link-notice__text"><?php esc_html_e( 'This booking cannot be cancelled online.', 'bookit-booking-system' ); ?></p>
		</div>
	</div>
</div>
	<?php
	return;
}

// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
$notice_hours = (int) $wpdb->get_var(
	$wpdb->prepare(
		"SELECT setting_value FROM {$wpdb->prefix}bookings_settings WHERE setting_key = %s",
		'cancellation_window_hours'
	)
);
if ( ! $notice_hours ) {
	$notice_hours = 24;
}

// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
$business_phone = $wpdb->get_var(
	$wpdb->prepare(
		"SELECT setting_value FROM {$wpdb->prefix}bookings_settings WHERE setting_key = %s LIMIT 1",
		'business_phone'
	)
);
$business_phone = is_string( $business_phone ) ? trim( $business_phone ) : '';

$tz          = new DateTimeZone( get_option( 'timezone_string' ) ?: 'Europe/London' );
$appt_dt     = new DateTime( $booking['booking_date'] . ' ' . $booking['start_time'], $tz );
$now_dt      = new DateTime( 'now', $tz );
$hours_until   = ( $appt_dt->getTimestamp() - $now_dt->getTimestamp() ) / 3600;
$within_window = $hours_until < $notice_hours;

$service_label = isset( $booking['service_name'] ) ? (string) $booking['service_name'] : '';
$staff_label   = trim(
	trim( (string) ( $booking['staff_first_name'] ?? '' ) ) . ' ' . trim( (string) ( $booking['staff_last_name'] ?? '' ) )
);
?>
<div class="bookit-confirmation-page bookit-magic-link-page">
	<div class="bookit-confirmation-card">

		<div class="bookit-confirmation-details">
			<h2><?php esc_html_e( 'Cancel Your Booking', 'bookit-booking-system' ); ?></h2>
			<table class="bookit-summary-table">
				<tr>
					<th scope="row"><?php esc_html_e( 'Reference', 'bookit-booking-system' ); ?></th>
					<td><?php echo esc_html( (string) $booking['booking_reference'] ); ?></td>
				</tr>
				<tr>
					<th scope="row"><?php esc_html_e( 'Service', 'bookit-booking-system' ); ?></th>
					<td><?php echo esc_html( $service_label ); ?></td>
				</tr>
				<tr>
					<th scope="row"><?php esc_html_e( 'Staff', 'bookit-booking-system' ); ?></th>
					<td><?php echo esc_html( $staff_label ); ?></td>
				</tr>
				<tr>
					<th scope="row"><?php esc_html_e( 'Date', 'bookit-booking-system' ); ?></th>
					<td><?php echo esc_html( date_i18n( 'l, j F Y', strtotime( $booking['booking_date'] ) ) ); ?></td>
				</tr>
				<tr>
					<th scope="row"><?php esc_html_e( 'Time', 'bookit-booking-system' ); ?></th>
					<td><?php echo esc_html( date_i18n( 'g:i a', strtotime( $booking['start_time'] ) ) ); ?></td>
				</tr>
			</table>
		</div>

		<div class="bookit-policy-notice">
			<?php if ( $within_window ) : ?>
				<p class="bookit-policy-notice__text">
					<?php
					printf(
						/* translators: %d: hours before appointment */
						esc_html__( 'Online cancellation is not available within %d hours of your appointment. Please contact us directly.', 'bookit-booking-system' ),
						(int) $notice_hours
					);
					?>
				</p>
				<?php if ( $business_phone ) : ?>
					<p class="bookit-policy-notice__phone">
						<a href="tel:<?php echo esc_attr( preg_replace( '/\s+/', '', $business_phone ) ); ?>">
							<?php echo esc_html( $business_phone ); ?>
						</a>
					</p>
				<?php endif; ?>
			<?php else : ?>
				<p class="bookit-policy-notice__text">
					<?php
					printf(
						/* translators: %d: minimum hours before appointment for free cancellation */
						esc_html__( 'You can cancel free of charge up to %d hours before your appointment.', 'bookit-booking-system' ),
						(int) $notice_hours
					);
					?>
				</p>
			<?php endif; ?>
		</div>

		<?php if ( ! $within_window ) : ?>
		<div class="bookit-magic-action" id="bookit-cancel-action">
			<div class="bookit-magic-message" id="bookit-cancel-message" style="display:none;" role="status" aria-live="polite"></div>
			<button
				type="button"
				class="bookit-btn-primary"
				id="bookit-cancel-confirm"
				data-booking-id="<?php echo esc_attr( (string) $booking_id ); ?>"
				data-token="<?php echo esc_attr( $token ); ?>"
				data-rest-url="<?php echo esc_url( $rest_url . 'cancel' ); ?>"
				data-confirming-label="<?php esc_attr_e( 'Cancelling…', 'bookit-booking-system' ); ?>"
			>
				<?php esc_html_e( 'Confirm Cancellation', 'bookit-booking-system' ); ?>
			</button>
		</div>
		<?php endif; ?>

	</div>
</div>
