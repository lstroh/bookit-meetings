<?php
/**
 * Booking confirmation page (V2 layout).
 * Displayed after successful payment or pay-on-arrival when using the V2 wizard.
 *
 * @package Bookit_Booking_System
 */

// Security check.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Get booking ID (Pay on Arrival) or Stripe session ID from URL.
// phpcs:ignore WordPress.Security.NonceVerification.Recommended
$booking_id = isset( $_GET['booking_id'] ) ? absint( $_GET['booking_id'] ) : 0;
// phpcs:ignore WordPress.Security.NonceVerification.Recommended
$session_id = isset( $_GET['session_id'] ) ? sanitize_text_field( wp_unslash( $_GET['session_id'] ) ) : '';

if ( 0 === $booking_id && '' === $session_id ) {
	?>
	<div class="bookit-confirmation-error">
		<h2><?php esc_html_e( 'Booking Not Found', 'booking-system' ); ?></h2>
		<p><?php esc_html_e( 'We couldn\'t find your booking. The confirmation link may be invalid or expired.', 'booking-system' ); ?></p>
		<p><a href="<?php echo esc_url( home_url( '/book' ) ); ?>" class="bookit-btn-primary">
			<?php esc_html_e( 'Make a New Booking', 'booking-system' ); ?>
		</a></p>
	</div>
	<?php
	return;
}

// Retrieve booking by ID (Pay on Arrival) or Stripe session ID (Stripe/PayPal).
require_once BOOKIT_PLUGIN_DIR . 'includes/booking/class-booking-retriever.php';

$retriever = new Booking_System_Booking_Retriever();

if ( $booking_id > 0 ) {
	$booking = $retriever->get_booking_by_id( $booking_id );
} else {
	$booking = $retriever->get_booking_by_stripe_session( $session_id );
}

if ( ! $booking ) {
	?>
	<div class="bookit-confirmation-error">
		<h2><?php esc_html_e( 'Booking Not Found', 'booking-system' ); ?></h2>
		<p><?php esc_html_e( 'We couldn\'t retrieve your booking details. Please contact us if you need assistance.', 'booking-system' ); ?></p>
		<p><a href="<?php echo esc_url( home_url( '/book' ) ); ?>" class="bookit-btn-primary">
			<?php esc_html_e( 'Make a New Booking', 'booking-system' ); ?>
		</a></p>
	</div>
	<?php
	return;
}

/*
 * Email sending intentionally omitted — Sprint 4H queue handles delivery.
 * send_customer_confirmation() and send_business_notification() are called
 * by the payment processor / Stripe webhook on booking creation.
 * Calling them again here would double-queue emails.
 */

$booking_reference = ! empty( $booking['booking_reference'] )
	? $booking['booking_reference']
	: 'BK-' . str_pad( (string) $booking['id'], 8, '0', STR_PAD_LEFT );

// Clear booking wizard session.
$retriever->clear_booking_session();

/**
 * Fires after the booking confirmation page has loaded and emails
 * have been sent. Extensions hook here to generate and store a
 * meeting link for this booking.
 *
 * @param int   $booking_id The booking ID.
 * @param array $booking    The full booking data array.
 */
do_action( 'bookit_after_booking_confirmed', $booking['id'], $booking );

// Re-fetch booking so bookit_confirmation_meeting_section receives
// fields written by bookit_after_booking_confirmed action callbacks
// (e.g. a meeting link generated and stored by an extension).
global $wpdb;
// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
$refreshed = $wpdb->get_row(
	$wpdb->prepare(
		"SELECT
				b.id,
				b.booking_reference,
				b.customer_id,
				b.service_id,
				b.staff_id,
				b.booking_date,
				b.start_time,
				b.end_time,
				b.duration,
				b.status,
				b.total_price,
				b.deposit_amount,
				b.deposit_paid,
				b.balance_due,
				b.full_amount_paid,
				b.payment_method,
				b.payment_intent_id,
				b.stripe_session_id,
				b.special_requests,
				b.cooling_off_waiver_given,
				b.cooling_off_waiver_at,
				b.magic_link_token,
				b.created_at,
				b.updated_at,
				b.deleted_at,
				c.first_name AS customer_first_name,
				c.last_name AS customer_last_name,
				c.email AS customer_email,
				c.phone AS customer_phone,
				s.name AS service_name,
				s.duration AS service_duration,
				s.price AS service_price,
				st.first_name AS staff_first_name,
				st.last_name AS staff_last_name,
				st.email AS staff_email
			FROM {$wpdb->prefix}bookings b
			LEFT JOIN {$wpdb->prefix}bookings_customers c ON b.customer_id = c.id
			LEFT JOIN {$wpdb->prefix}bookings_services s ON b.service_id = s.id
			LEFT JOIN {$wpdb->prefix}bookings_staff st ON b.staff_id = st.id
			WHERE b.id = %d
			LIMIT 1",
		$booking['id']
	),
	ARRAY_A
);
if ( $refreshed ) {
	$booking                    = $refreshed;
	$booking['staff_name']      = trim( ( $booking['staff_first_name'] ?? '' ) . ' ' . ( $booking['staff_last_name'] ?? '' ) );
	$booking['customer_name'] = trim( ( $booking['customer_first_name'] ?? '' ) . ' ' . ( $booking['customer_last_name'] ?? '' ) );
}

// Format date and time for display.
$date_formatted = $retriever->format_date( $booking['booking_date'] );
$time_formatted = $retriever->format_time( $booking['start_time'] );

$duration_min = isset( $booking['service_duration'] ) ? (int) $booking['service_duration'] : 0;

$deposit_due   = isset( $booking['deposit_paid'] ) ? (float) $booking['deposit_paid'] : 0.0;
$balance_due   = isset( $booking['balance_due'] ) ? (float) $booking['balance_due'] : 0.0;
$total_price   = isset( $booking['total_price'] ) ? (float) $booking['total_price'] : 0.0;
$has_deposit   = $balance_due > 0 && $deposit_due > 0;

global $wpdb;
// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
$business_phone = $wpdb->get_var(
	$wpdb->prepare(
		"SELECT setting_value FROM {$wpdb->prefix}bookings_settings WHERE setting_key = %s LIMIT 1",
		'business_phone'
	)
);
$business_phone = is_string( $business_phone ) ? trim( $business_phone ) : '';

/**
 * Filter the meeting section HTML on the confirmation page.
 * Return non-empty HTML from an extension to display a meeting link.
 * Return empty string (default) to show nothing.
 *
 * @param string $html    The meeting section HTML. Default ''.
 * @param array  $booking The full booking data array.
 */
$bookit_meeting_section_html = apply_filters(
	'bookit_confirmation_meeting_section',
	'',
	$booking
);
?>

<div class="bookit-confirmation-page">
	<div class="bookit-confirmed-layout">
		<div class="bookit-confirmed-header">
			<div class="bookit-confirmed-icon">
				<svg width="28" height="28" viewBox="0 0 28 28" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
					<polyline points="5,14 11,20 23,8" stroke="white" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"/>
				</svg>
			</div>
			<h1 class="bookit-confirmed-heading"><?php esc_html_e( 'Booking confirmed', 'bookit-booking-system' ); ?></h1>
			<p class="bookit-confirmed-email">
				<?php
				echo esc_html__( "We'll send a confirmation to ", 'bookit-booking-system' );
				echo '<strong>' . esc_html( $booking['customer_email'] ) . '</strong>';
				echo esc_html__( ' shortly.', 'bookit-booking-system' );
				?>
			</p>
		</div>

		<div class="bookit-confirmed-card">
			<div class="bookit-confirmed-card-inner">
				<div class="bookit-confirmed-detail-rows">
					<div class="bookit-confirmed-row">
						<span class="bookit-confirmed-label"><?php esc_html_e( 'Service', 'bookit-booking-system' ); ?></span>
						<span class="bookit-confirmed-value"><?php echo esc_html( $booking['service_name'] ); ?></span>
					</div>
					<div class="bookit-confirmed-row">
						<span class="bookit-confirmed-label"><?php esc_html_e( 'Duration', 'bookit-booking-system' ); ?></span>
						<span class="bookit-confirmed-value"><?php echo esc_html( sprintf( '%d min', $duration_min ) ); ?></span>
					</div>
					<div class="bookit-confirmed-row">
						<span class="bookit-confirmed-label"><?php esc_html_e( 'With', 'bookit-booking-system' ); ?></span>
						<span class="bookit-confirmed-value"><?php echo esc_html( $booking['staff_name'] ); ?></span>
					</div>
					<div class="bookit-confirmed-row">
						<span class="bookit-confirmed-label"><?php esc_html_e( 'Date', 'bookit-booking-system' ); ?></span>
						<span class="bookit-confirmed-value"><?php echo esc_html( $date_formatted ); ?></span>
					</div>
					<div class="bookit-confirmed-row">
						<span class="bookit-confirmed-label"><?php esc_html_e( 'Time', 'bookit-booking-system' ); ?></span>
						<span class="bookit-confirmed-value"><?php echo esc_html( $time_formatted ); ?></span>
					</div>
					<div class="bookit-confirmed-row">
						<span class="bookit-confirmed-label"><?php esc_html_e( 'Booking ref', 'bookit-booking-system' ); ?></span>
						<span class="bookit-confirmed-value bookit-confirmed-ref"><?php echo esc_html( $booking_reference ); ?></span>
					</div>
				</div>
			</div>
		</div>

		<div class="bookit-confirmed-card bookit-confirmed-card--payment">
			<div class="bookit-confirmed-card-inner">
				<?php if ( $has_deposit ) : ?>
					<div class="bookit-confirmed-payment-rows">
						<div class="bookit-confirmed-payment-row">
							<span class="bookit-confirmed-payment-key"><?php esc_html_e( 'Today (deposit)', 'bookit-booking-system' ); ?></span>
							<span class="bookit-confirmed-payment-val">&pound;<?php echo esc_html( number_format( $deposit_due, 2 ) ); ?></span>
						</div>
						<div class="bookit-confirmed-payment-row">
							<span class="bookit-confirmed-payment-key"><?php esc_html_e( 'Remaining (on the day)', 'bookit-booking-system' ); ?></span>
							<span class="bookit-confirmed-payment-val">&pound;<?php echo esc_html( number_format( $balance_due, 2 ) ); ?></span>
						</div>
						<div class="bookit-confirmed-payment-row bookit-confirmed-payment-row--total">
							<span class="bookit-confirmed-payment-key"><?php esc_html_e( 'Total', 'bookit-booking-system' ); ?></span>
							<span class="bookit-confirmed-payment-val">&pound;<?php echo esc_html( number_format( $total_price, 2 ) ); ?></span>
						</div>
					</div>
					<div class="bookit-confirmed-balance-note">
						<?php esc_html_e( 'Your remaining balance is payable on the day of your appointment.', 'bookit-booking-system' ); ?>
					</div>
				<?php elseif ( isset( $booking['payment_method'] ) && 'pay_on_arrival' !== $booking['payment_method'] ) : ?>
					<div class="bookit-confirmed-payment-rows">
						<div class="bookit-confirmed-payment-row bookit-confirmed-payment-row--total">
							<span class="bookit-confirmed-payment-key"><?php esc_html_e( 'Total paid today', 'bookit-booking-system' ); ?></span>
							<span class="bookit-confirmed-payment-val">&pound;<?php echo esc_html( number_format( $deposit_due, 2 ) ); ?></span>
						</div>
					</div>
				<?php else : ?>
					<p class="bookit-confirmed-pay-on-arrival-msg">
						<?php
						echo wp_kses_post(
							sprintf(
								/* translators: %s: formatted GBP amount (may include HTML) */
								__( 'No deposit was taken. Please bring %s on the day.', 'bookit-booking-system' ),
								'<strong>&pound;' . esc_html( number_format( $total_price, 2 ) ) . '</strong>'
							)
						);
						?>
					</p>
				<?php endif; ?>
			</div>
		</div>

		<div class="bookit-confirmed-cancel-note">
			<p class="bookit-confirmed-cancel-text">
				<?php esc_html_e( 'To cancel or reschedule, use the link in your confirmation email.', 'bookit-booking-system' ); ?>
			</p>
			<?php if ( '' !== $business_phone ) : ?>
				<p class="bookit-confirmed-cancel-phone">
					<?php
					printf(
						/* translators: %s: business phone number */
						esc_html__( 'Or call us on %s.', 'bookit-booking-system' ),
						esc_html( $business_phone )
					);
					?>
				</p>
			<?php endif; ?>
		</div>

		<?php if ( ! empty( $booking['cooling_off_waiver_given'] ) ) : ?>
			<div class="bookit-confirmed-waiver">
				<?php esc_html_e( '✓ You have waived your 14-day right to cancel for this booking (Consumer Contracts Regulations 2013).', 'bookit-booking-system' ); ?>
			</div>
		<?php endif; ?>

		<?php if ( ! empty( $booking['special_requests'] ) ) : ?>
			<div class="bookit-confirmed-special-requests">
				<p class="bookit-confirmed-sr-label"><?php esc_html_e( 'Your special requests', 'bookit-booking-system' ); ?></p>
				<p class="bookit-confirmed-sr-value"><?php echo esc_html( $booking['special_requests'] ); ?></p>
			</div>
		<?php endif; ?>

		<?php
		if ( '' !== $bookit_meeting_section_html ) {
			echo wp_kses_post( $bookit_meeting_section_html );
		}
		?>

		<div class="bookit-confirmed-actions">
			<a href="<?php echo esc_url( add_query_arg( array( 'booking_id' => (int) $booking['id'], 'token' => isset( $booking['magic_link_token'] ) ? (string) $booking['magic_link_token'] : '' ), rest_url( 'bookit/v1/wizard/ical' ) ) ); ?>" class="bookit-confirmed-btn-primary">
				<svg width="16" height="16" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
					<rect x="3" y="4" width="18" height="18" rx="2" ry="2" stroke="currentColor" stroke-width="2" fill="none"/>
					<line x1="16" y1="2" x2="16" y2="6" stroke="currentColor" stroke-width="2"/>
					<line x1="8" y1="2" x2="8" y2="6" stroke="currentColor" stroke-width="2"/>
					<line x1="3" y1="10" x2="21" y2="10" stroke="currentColor" stroke-width="2"/>
				</svg>
				<?php esc_html_e( 'Add to calendar', 'bookit-booking-system' ); ?>
			</a>
			<a href="<?php echo esc_url( home_url( '/' ) ); ?>" class="bookit-confirmed-btn-secondary">
				<?php esc_html_e( 'Back to home', 'bookit-booking-system' ); ?>
			</a>
			<a href="<?php echo esc_url( home_url( '/book' ) ); ?>" class="bookit-confirmed-btn-text">
				<?php esc_html_e( 'Book again', 'bookit-booking-system' ); ?>
			</a>
		</div>
	</div>
</div>
