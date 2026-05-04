<?php
/**
 * Email Sender
 * Sends booking confirmation and notification emails.
 *
 * @package    Bookit_Booking_System
 * @subpackage Bookit_Booking_System/includes/email
 */

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
	die;
}

/**
 * Email Sender class.
 *
 * Sends booking confirmation and customer lifecycle emails (cancellation, reschedule, etc.).
 */
class Booking_System_Email_Sender {

	/**
	 * Whether to write messages to error_log (disabled during unit tests).
	 *
	 * @return bool
	 */
	private static function should_log() {
		return ! defined( 'WP_TESTS_TABLE_PREFIX' ) && function_exists( 'error_log' );
	}

	/**
	 * Send customer confirmation email.
	 *
	 * @param array $booking Booking data with customer, service, staff details.
	 * @return bool|WP_Error True on success, WP_Error on failure.
	 */
	public function send_customer_confirmation( $booking ) {
		// Allow tests to bypass actual email sending.
		$bypass = apply_filters( 'bookit_send_email', true );
		if ( $bypass === false ) {
			return true; // Test mode - don't send.
		}

		$to      = $booking['customer_email'];
		$subject = sprintf(
			__( 'Booking Confirmed - %s', 'booking-system' ),
			$booking['service_name']
		);

		$recipient = array(
			'email' => sanitize_email( $booking['customer_email'] ),
			'name'  => trim(
				( $booking['customer_first_name'] ?? '' ) . ' ' .
				( $booking['customer_last_name'] ?? '' )
			),
		);

		$html_body = $this->generate_customer_email( $booking );

		$queue_id = bookit_enqueue_email(
			'customer_confirmation',
			$recipient,
			$subject,
			$html_body,
			(int) ( $booking['id'] ?? 0 )
		);

		if ( false === $queue_id ) {
			if ( self::should_log() ) {
				error_log( 'Email Sender: Failed to enqueue customer confirmation for ' . $booking['customer_email'] );
			}
			return new \WP_Error( 'email_queue_failed', 'Failed to queue confirmation email' );
		}

		if ( self::should_log() ) {
			error_log( 'Email Sender: Customer confirmation queued (queue_id=' . $queue_id . ') for ' . $booking['customer_email'] );
		}
		return true;
	}

	/**
	 * Send customer cancellation email.
	 *
	 * @param array $booking Booking data with customer, service, staff details.
	 * @return bool|WP_Error True on success, WP_Error on failure.
	 */
	public function send_customer_cancellation( array $booking ) {
		// Allow tests to bypass actual email sending.
		$bypass = apply_filters( 'bookit_send_email', true );
		if ( $bypass === false ) {
			return true; // Test mode - don't send.
		}

		$subject = sprintf(
			__( 'Booking Cancelled — %s', 'bookit-booking-system' ),
			$booking['service_name']
		);

		$recipient = array(
			'email' => sanitize_email( $booking['customer_email'] ),
			'name'  => trim(
				( $booking['customer_first_name'] ?? '' ) . ' ' .
				( $booking['customer_last_name'] ?? '' )
			),
		);

		$html_body = $this->generate_cancellation_email( $booking );

		$queue_id = bookit_enqueue_email(
			'customer_cancellation',
			$recipient,
			$subject,
			$html_body,
			(int) ( $booking['id'] ?? 0 )
		);

		if ( false === $queue_id ) {
			if ( self::should_log() ) {
				error_log( 'Email Sender: Failed to enqueue customer cancellation for ' . ( $booking['customer_email'] ?? '' ) );
			}
			return new \WP_Error( 'email_queue_failed', 'Failed to queue cancellation email' );
		}

		if ( self::should_log() ) {
			error_log( 'Email Sender: Customer cancellation queued (queue_id=' . $queue_id . ') for ' . ( $booking['customer_email'] ?? '' ) );
		}
		return true;
	}

	/**
	 * Send customer reschedule email.
	 *
	 * @param array $booking Booking data with customer, service, staff details.
	 * @return bool|WP_Error True on success, WP_Error on failure.
	 */
	public function send_customer_reschedule( array $booking ) {
		// Allow tests to bypass actual email sending.
		$bypass = apply_filters( 'bookit_send_email', true );
		if ( $bypass === false ) {
			return true; // Test mode - don't send.
		}

		$subject = sprintf(
			__( 'Booking Rescheduled — %s', 'bookit-booking-system' ),
			$booking['service_name']
		);

		$recipient = array(
			'email' => sanitize_email( $booking['customer_email'] ),
			'name'  => trim(
				( $booking['customer_first_name'] ?? '' ) . ' ' .
				( $booking['customer_last_name'] ?? '' )
			),
		);

		$html_body = $this->generate_reschedule_email( $booking );

		$queue_id = bookit_enqueue_email(
			'customer_reschedule',
			$recipient,
			$subject,
			$html_body,
			(int) ( $booking['id'] ?? 0 )
		);

		if ( false === $queue_id ) {
			if ( self::should_log() ) {
				error_log( 'Email Sender: Failed to enqueue customer reschedule for ' . ( $booking['customer_email'] ?? '' ) );
			}
			return new \WP_Error( 'email_queue_failed', 'Failed to queue reschedule email' );
		}

		if ( self::should_log() ) {
			error_log( 'Email Sender: Customer reschedule queued (queue_id=' . $queue_id . ') for ' . ( $booking['customer_email'] ?? '' ) );
		}
		return true;
	}

	/**
	 * Generate customer confirmation email HTML.
	 *
	 * @param array $booking Booking data.
	 * @return string HTML email body.
	 */
	public function generate_customer_email( $booking ) {
		$date_formatted = $this->format_date( $booking['booking_date'] );
		$time_formatted = $this->format_time( $booking['start_time'] );
		$cancellation_policy_text = $this->get_cancellation_policy_text();

		ob_start();
		?>
		<!DOCTYPE html>
		<html>
		<head>
			<meta charset="UTF-8">
			<style>
				body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
				.container { max-width: 600px; margin: 0 auto; padding: 20px; }
				.header { background: #0073aa; color: white; padding: 20px; text-align: center; }
				.content { background: #f9f9f9; padding: 20px; }
				.booking-details { background: white; padding: 15px; margin: 15px 0; border-left: 4px solid #0073aa; }
				.detail-row { padding: 8px 0; border-bottom: 1px solid #eee; }
				.label { font-weight: bold; color: #666; }
				.value { color: #333; }
				.payment-summary { background: #e8f5e9; padding: 15px; margin: 15px 0; }
				.footer { text-align: center; padding: 20px; color: #666; font-size: 12px; }
			</style>
		</head>
		<body>
			<div class="container">
				<div class="header">
					<h1><?php esc_html_e( 'Booking Confirmed!', 'booking-system' ); ?></h1>
				</div>

				<div class="content">
					<p><?php printf( esc_html__( 'Hi %s,', 'booking-system' ), esc_html( $booking['customer_first_name'] ) ); ?></p>
					<p><?php esc_html_e( 'Your booking has been confirmed. Here are the details:', 'booking-system' ); ?></p>

					<div class="booking-details">
						<div class="detail-row">
							<span class="label"><?php esc_html_e( 'Service:', 'booking-system' ); ?></span>
							<span class="value"><?php echo esc_html( $booking['service_name'] ); ?></span>
						</div>

						<div class="detail-row">
							<span class="label"><?php esc_html_e( 'Date:', 'booking-system' ); ?></span>
							<span class="value"><?php echo esc_html( $date_formatted ); ?></span>
						</div>

						<div class="detail-row">
							<span class="label"><?php esc_html_e( 'Time:', 'booking-system' ); ?></span>
							<span class="value"><?php echo esc_html( $time_formatted ); ?></span>
						</div>

						<div class="detail-row">
							<span class="label"><?php esc_html_e( 'Staff:', 'booking-system' ); ?></span>
							<span class="value"><?php echo esc_html( $booking['staff_name'] ); ?></span>
						</div>
						<?php if ( ! empty( $booking['booking_reference'] ) ) : ?>
						<div class="detail-row">
							<span class="label"><?php esc_html_e( 'Booking ref:', 'booking-system' ); ?></span>
							<span class="value"><?php echo esc_html( $booking['booking_reference'] ); ?></span>
						</div>
						<?php endif; ?>
					</div>

					<?php if ( ! empty( $booking['cooling_off_waiver_given'] ) ) : ?>
						<p><strong><?php esc_html_e( '✓ You have waived your 14-day right to cancel for this booking (Consumer Contracts Regulations 2013).', 'bookit-booking-system' ); ?></strong></p>
					<?php endif; ?>

					<div class="payment-summary">
						<h3><?php esc_html_e( 'Payment Summary', 'booking-system' ); ?></h3>
						<div class="detail-row">
							<span class="label"><?php esc_html_e( 'Total:', 'booking-system' ); ?></span>
							<span class="value">&pound;<?php echo esc_html( number_format( (float) $booking['total_price'], 2 ) ); ?></span>
						</div>

						<?php if ( isset( $booking['payment_method'] ) && 'pay_on_arrival' === $booking['payment_method'] ) : ?>
							<div style="background: #fff3cd; padding: 15px; margin: 15px 0; border-left: 4px solid #ffc107; border-radius: 4px;">
								<strong style="color: #856404;"><?php esc_html_e( 'Payment Due on Arrival', 'booking-system' ); ?></strong>
								<p style="margin: 10px 0 0; color: #856404;">
									<?php
									printf(
										/* translators: %s: formatted total price */
										esc_html__( 'Please bring %s to pay when you arrive for your appointment.', 'booking-system' ),
										'<strong>&pound;' . esc_html( number_format( (float) $booking['total_price'], 2 ) ) . '</strong>'
									);
									?>
								</p>
								<p style="margin: 10px 0 0; font-size: 14px; color: #856404;">
									<?php esc_html_e( 'We accept cash and card payments.', 'booking-system' ); ?>
								</p>
							</div>
						<?php else : ?>
							<div class="detail-row">
								<span class="label"><?php esc_html_e( 'Paid:', 'booking-system' ); ?></span>
								<span class="value">&pound;<?php echo esc_html( number_format( (float) $booking['deposit_paid'], 2 ) ); ?></span>
							</div>
							<div class="detail-row">
								<span class="label"><?php esc_html_e( 'Balance Due:', 'booking-system' ); ?></span>
								<span class="value">&pound;<?php echo esc_html( number_format( (float) $booking['balance_due'], 2 ) ); ?></span>
							</div>
						<?php endif; ?>

						<div class="detail-row">
							<span class="label"><?php esc_html_e( 'Payment Method:', 'booking-system' ); ?></span>
							<span class="value"><?php echo esc_html( ucwords( str_replace( '_', ' ', $booking['payment_method'] ?? '' ) ) ); ?></span>
						</div>
					</div>

					<?php if ( ! empty( $booking['special_requests'] ) ) : ?>
						<div class="detail-row">
							<span class="label"><?php esc_html_e( 'Special Requests:', 'booking-system' ); ?></span>
							<p><?php echo esc_html( $booking['special_requests'] ); ?></p>
						</div>
					<?php endif; ?>

					<?php
					$is_package_payment_method = isset( $booking['payment_method'] ) && in_array( $booking['payment_method'], array( 'package_redemption', 'use_package' ), true );
					if ( ! empty( $booking['customer_package_id'] ) && $is_package_payment_method ) :
						$package_info = $this->get_package_info_for_email( (int) $booking['customer_package_id'] );
						if ( $package_info ) :
							$package_name = isset( $package_info['package_type_name'] ) ? (string) $package_info['package_type_name'] : '';
							$expires_at   = isset( $package_info['expires_at'] ) ? (string) $package_info['expires_at'] : '';
							?>
							<div style="background:#eef7ff; border:1px solid #c7ddf5; border-left:4px solid #0073aa; border-radius:6px; padding:12px 16px; margin:20px 0;">
								<p style="margin:0; font-size:14px; color:#1f3d56;">
									<?php
									printf(
										/* translators: 1: package name, 2: sessions remaining, 3: total sessions */
										esc_html__( 'Sessions remaining on your %1$s package: %2$d of %3$d', 'booking-system' ),
										esc_html( $package_name ),
										(int) $package_info['sessions_remaining'],
										(int) $package_info['sessions_total']
									);
									?>
								</p>
								<?php if ( ! empty( $expires_at ) ) : ?>
									<p style="margin:8px 0 0; font-size:14px; color:#1f3d56;">
										<?php
										printf(
											/* translators: %s: package expiry date */
											esc_html__( 'Your package expires on: %s', 'booking-system' ),
											esc_html( date_i18n( get_option( 'date_format' ), strtotime( $expires_at ) ) )
										);
										?>
									</p>
								<?php endif; ?>
							</div>
							<?php
						endif;
					endif;
					?>

					<?php if ( ! empty( $cancellation_policy_text ) ) : ?>
						<div style="background:#f0f4f8; border:1px solid #b0c4d8; border-left:4px solid #0073aa; border-radius:6px; padding:12px 16px; margin:20px 0;">
							<p style="margin:0 0 6px; font-weight:600; font-size:13px; color:#1a3a52;">
								📋 <?php esc_html_e( 'Cancellation Policy', 'bookit-booking-system' ); ?>
							</p>
							<p style="margin:0; font-size:13px; color:#2c5282; line-height:1.6;">
								<?php echo nl2br( esc_html( $cancellation_policy_text ) ); ?>
							</p>
						</div>
					<?php endif; ?>

					<?php
					/**
					 * Filter the meeting section HTML in the customer confirmation email.
					 * Return non-empty HTML from an extension to display a meeting link row.
					 * Return empty string (default) to show nothing.
					 *
					 * @param string $html    The meeting section HTML. Default ''.
					 * @param array  $booking The booking data array passed to this method.
					 */
					$bookit_email_meeting_html = apply_filters(
						'bookit_email_meeting_section',
						'',
						$booking
					);
					if ( '' !== $bookit_email_meeting_html ) {
						echo wp_kses_post( $bookit_email_meeting_html );
					}
					?>

					<?php
					global $wpdb;
					$booking_id       = (int) ( $booking['id'] ?? 0 );
					$magic_link_token = isset( $booking['magic_link_token'] )
						? $booking['magic_link_token']
						: $wpdb->get_var(
							$wpdb->prepare(
								"SELECT magic_link_token FROM {$wpdb->prefix}bookings WHERE id = %d",
								$booking_id
							)
						);
					if ( ! empty( $magic_link_token ) ) {
						$ical_url       = add_query_arg(
							array(
								'booking_id' => $booking_id,
								'token'      => $magic_link_token,
							),
							rest_url( 'bookit/v1/wizard/ical' )
						);
						$cancel_url     = add_query_arg(
							array(
								'booking_id' => $booking_id,
								'token'      => $magic_link_token,
							),
							home_url( '/bookit-cancel/' )
						);
						$reschedule_url = add_query_arg(
							array(
								'booking_id' => $booking_id,
								'token'      => $magic_link_token,
							),
							home_url( '/bookit-reschedule/' )
						);
						?>
					<table cellpadding="0" cellspacing="0" border="0" width="100%" style="margin: 0;">
						<tr>
							<td style="padding: 24px 0 8px; border-top: 1px solid #E5E7EB;">
								<p style="margin: 0 0 12px; font-size: 14px; color: #6B7280; font-family: Arial, sans-serif;">
									<?php esc_html_e( 'Need to make changes?', 'bookit-booking-system' ); ?>
								</p>
								<table cellpadding="0" cellspacing="0" border="0">
									<tr>
										<td style="padding-right: 12px;">
											<a href="<?php echo esc_url( $ical_url ); ?>"
												style="display:inline-block; padding: 10px 20px; background-color: #005FB8;
													color: #ffffff; text-decoration: none; border-radius: 4px;
													font-size: 14px; font-family: Arial, sans-serif; font-weight: 600;">
												<?php esc_html_e( '📅 Add to Calendar', 'bookit-booking-system' ); ?>
											</a>
										</td>
										<td style="padding-right: 12px;">
											<a href="<?php echo esc_url( $reschedule_url ); ?>"
												style="display:inline-block; padding: 10px 20px; background-color: #005FB8;
													color: #ffffff; text-decoration: none; border-radius: 4px;
													font-size: 14px; font-family: Arial, sans-serif; font-weight: 600;">
												<?php esc_html_e( 'Reschedule', 'bookit-booking-system' ); ?>
											</a>
										</td>
										<td>
											<a href="<?php echo esc_url( $cancel_url ); ?>"
												style="display:inline-block; padding: 10px 20px; background-color: #ffffff;
													color: #374151; text-decoration: none; border-radius: 4px;
													font-size: 14px; font-family: Arial, sans-serif; font-weight: 600;
													border: 1px solid #D1D5DB;">
												<?php esc_html_e( 'Cancel Booking', 'bookit-booking-system' ); ?>
											</a>
										</td>
									</tr>
								</table>
							</td>
						</tr>
					</table>
						<?php
					}
					?>

					<p><?php esc_html_e( 'We look forward to seeing you!', 'booking-system' ); ?></p>
				</div>

				<div class="footer">
					<p><?php echo esc_html( get_bloginfo( 'name' ) ); ?></p>
					<p><?php esc_html_e( 'If you need to cancel or reschedule, please contact us.', 'booking-system' ); ?></p>
				</div>
			</div>
		</body>
		</html>
		<?php

		return ob_get_clean();
	}

	/**
	 * Generate email change verification email HTML.
	 *
	 * @param array  $customer Customer row (expects id, first_name, last_name).
	 * @param string $token Verification token.
	 * @return string
	 */
	public function generate_email_change_verification_email( array $customer, string $token ): string {
		$customer_name = trim( (string) ( $customer['first_name'] ?? '' ) . ' ' . (string) ( $customer['last_name'] ?? '' ) );
		$verify_url    = rest_url( 'bookit/v1/wizard/verify-email-change' ) . '?token=' . rawurlencode( $token ) . '&customer_id=' . rawurlencode( (string) (int) ( $customer['id'] ?? 0 ) );

		ob_start();
		?>
		<!DOCTYPE html>
		<html>
		<head>
			<meta charset="UTF-8">
			<style>
				body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
				.container { max-width: 600px; margin: 0 auto; padding: 20px; }
				.header { background: #0073aa; color: white; padding: 20px; text-align: center; }
				.content { background: #f9f9f9; padding: 20px; }
				.card { background: white; padding: 16px; border-left: 4px solid #0073aa; margin: 16px 0; }
				.btn { display: inline-block; padding: 12px 20px; background-color: #005FB8; color: #fff; text-decoration: none; border-radius: 4px; font-weight: 600; }
				.footer { text-align: center; padding: 20px; color: #666; font-size: 12px; }
			</style>
		</head>
		<body>
			<div class="container">
				<div class="header">
					<h1><?php esc_html_e( 'Verify your new email address', 'bookit-booking-system' ); ?></h1>
				</div>
				<div class="content">
					<?php if ( ! empty( $customer_name ) ) : ?>
						<p><?php printf( esc_html__( 'Hi %s,', 'bookit-booking-system' ), esc_html( $customer_name ) ); ?></p>
					<?php endif; ?>
					<p><?php esc_html_e( 'An administrator has requested an email address change for your booking account.', 'bookit-booking-system' ); ?></p>

					<div class="card">
						<p style="margin:0 0 12px;"><?php esc_html_e( 'Please verify this change by clicking the button below.', 'bookit-booking-system' ); ?></p>
						<p style="margin:0;">
							<a class="btn" href="<?php echo esc_url( $verify_url ); ?>">
								<?php esc_html_e( 'Verify Email Change', 'bookit-booking-system' ); ?>
							</a>
						</p>
					</div>

					<p style="font-size: 13px; color:#6B7280; margin: 0;">
						<?php esc_html_e( 'If you were not expecting this email, please contact us.', 'bookit-booking-system' ); ?>
					</p>
				</div>
				<div class="footer">
					<p><?php echo esc_html( get_bloginfo( 'name' ) ); ?></p>
				</div>
			</div>
		</body>
		</html>
		<?php
		return (string) ob_get_clean();
	}

	/**
	 * Generate email change notification email HTML (sent to the old address).
	 *
	 * @param array $customer Customer row.
	 * @return string
	 */
	public function generate_email_change_notification_email( array $customer ): string {
		$customer_name = trim( (string) ( $customer['first_name'] ?? '' ) . ' ' . (string) ( $customer['last_name'] ?? '' ) );

		ob_start();
		?>
		<!DOCTYPE html>
		<html>
		<head>
			<meta charset="UTF-8">
			<style>
				body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
				.container { max-width: 600px; margin: 0 auto; padding: 20px; }
				.header { background: #0073aa; color: white; padding: 20px; text-align: center; }
				.content { background: #f9f9f9; padding: 20px; }
				.card { background: white; padding: 16px; border-left: 4px solid #0073aa; margin: 16px 0; }
				.footer { text-align: center; padding: 20px; color: #666; font-size: 12px; }
			</style>
		</head>
		<body>
			<div class="container">
				<div class="header">
					<h1><?php esc_html_e( 'Email change requested', 'bookit-booking-system' ); ?></h1>
				</div>
				<div class="content">
					<?php if ( ! empty( $customer_name ) ) : ?>
						<p><?php printf( esc_html__( 'Hi %s,', 'bookit-booking-system' ), esc_html( $customer_name ) ); ?></p>
					<?php endif; ?>

					<div class="card">
						<p style="margin:0;">
							<?php esc_html_e( 'An email change has been requested for your booking account. If you did not request this, please contact us.', 'bookit-booking-system' ); ?>
						</p>
					</div>
				</div>
				<div class="footer">
					<p><?php echo esc_html( get_bloginfo( 'name' ) ); ?></p>
				</div>
			</div>
		</body>
		</html>
		<?php
		return (string) ob_get_clean();
	}

	/**
	 * Generate email change confirmed email HTML.
	 *
	 * @param string $new_email New email address.
	 * @return string
	 */
	public function generate_email_change_confirmed_email( string $new_email ): string {
		$new_email = sanitize_email( $new_email );

		ob_start();
		?>
		<!DOCTYPE html>
		<html>
		<head>
			<meta charset="UTF-8">
			<style>
				body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
				.container { max-width: 600px; margin: 0 auto; padding: 20px; }
				.header { background: #0073aa; color: white; padding: 20px; text-align: center; }
				.content { background: #f9f9f9; padding: 20px; }
				.card { background: white; padding: 16px; border-left: 4px solid #0073aa; margin: 16px 0; }
				.footer { text-align: center; padding: 20px; color: #666; font-size: 12px; }
			</style>
		</head>
		<body>
			<div class="container">
				<div class="header">
					<h1><?php esc_html_e( 'Email Updated', 'bookit-booking-system' ); ?></h1>
				</div>
				<div class="content">
					<div class="card">
						<p style="margin:0 0 10px;">
							<?php esc_html_e( 'Your booking account email has been updated. Future booking communications will be sent to your new address.', 'bookit-booking-system' ); ?>
						</p>
						<?php if ( ! empty( $new_email ) ) : ?>
							<p style="margin:0; font-size:13px; color:#6B7280;">
								<?php
								printf(
									/* translators: %s: email address */
									esc_html__( 'New email: %s', 'bookit-booking-system' ),
									esc_html( $new_email )
								);
								?>
							</p>
						<?php endif; ?>
					</div>
				</div>
				<div class="footer">
					<p><?php echo esc_html( get_bloginfo( 'name' ) ); ?></p>
				</div>
			</div>
		</body>
		</html>
		<?php
		return (string) ob_get_clean();
	}

	/**
	 * Generate customer cancellation email HTML.
	 *
	 * @param array $booking Booking data.
	 * @return string HTML email body.
	 */
	private function generate_cancellation_email( array $booking ): string {
		$date_formatted = $this->format_date( $booking['booking_date'] );
		$time_formatted = $this->format_time( $booking['start_time'] );
		$book_again_url = home_url( '/bookit/' );

		ob_start();
		?>
		<!DOCTYPE html>
		<html>
		<head>
			<meta charset="UTF-8">
			<style>
				body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
				.container { max-width: 600px; margin: 0 auto; padding: 20px; }
				.header { background: #0073aa; color: white; padding: 20px; text-align: center; }
				.content { background: #f9f9f9; padding: 20px; }
				.booking-details { background: white; padding: 15px; margin: 15px 0; border-left: 4px solid #0073aa; }
				.detail-row { padding: 8px 0; border-bottom: 1px solid #eee; }
				.label { font-weight: bold; color: #666; }
				.value { color: #333; }
				.footer { text-align: center; padding: 20px; color: #666; font-size: 12px; }
			</style>
		</head>
		<body>
			<div class="container">
				<div class="header">
					<h1><?php esc_html_e( 'Your Booking Has Been Cancelled', 'bookit-booking-system' ); ?></h1>
				</div>

				<div class="content">
					<p><?php printf( esc_html__( 'Hi %s,', 'bookit-booking-system' ), esc_html( $booking['customer_first_name'] ) ); ?></p>

					<div class="booking-details">
						<div class="detail-row">
							<span class="label"><?php esc_html_e( 'Service:', 'bookit-booking-system' ); ?></span>
							<span class="value"><?php echo esc_html( $booking['service_name'] ); ?></span>
						</div>

						<div class="detail-row">
							<span class="label"><?php esc_html_e( 'Date:', 'bookit-booking-system' ); ?></span>
							<span class="value"><?php echo esc_html( $date_formatted ); ?></span>
						</div>

						<div class="detail-row">
							<span class="label"><?php esc_html_e( 'Time:', 'bookit-booking-system' ); ?></span>
							<span class="value"><?php echo esc_html( $time_formatted ); ?></span>
						</div>

						<div class="detail-row">
							<span class="label"><?php esc_html_e( 'Staff:', 'bookit-booking-system' ); ?></span>
							<span class="value"><?php echo esc_html( $booking['staff_name'] ); ?></span>
						</div>
					</div>

					<table cellpadding="0" cellspacing="0" border="0" width="100%" style="margin: 0;">
						<tr>
							<td style="padding: 24px 0 8px; border-top: 1px solid #E5E7EB;">
								<table cellpadding="0" cellspacing="0" border="0">
									<tr>
										<td>
											<a href="<?php echo esc_url( $book_again_url ); ?>"
												style="display:inline-block; padding: 10px 20px; background-color: #005FB8;
													color: #ffffff; text-decoration: none; border-radius: 4px;
													font-size: 14px; font-family: Arial, sans-serif; font-weight: 600;">
												<?php esc_html_e( 'Book Again', 'bookit-booking-system' ); ?>
											</a>
										</td>
									</tr>
								</table>
							</td>
						</tr>
					</table>
				</div>

				<div class="footer">
					<p><?php echo esc_html( get_bloginfo( 'name' ) ); ?></p>
				</div>
			</div>
		</body>
		</html>
		<?php

		return (string) ob_get_clean();
	}

	/**
	 * Generate customer reschedule email HTML.
	 *
	 * @param array $booking Booking data.
	 * @return string HTML email body.
	 */
	private function generate_reschedule_email( array $booking ): string {
		$date_formatted = $this->format_date( $booking['booking_date'] );
		$time_formatted = $this->format_time( $booking['start_time'] );

		ob_start();
		?>
		<!DOCTYPE html>
		<html>
		<head>
			<meta charset="UTF-8">
			<style>
				body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
				.container { max-width: 600px; margin: 0 auto; padding: 20px; }
				.header { background: #0073aa; color: white; padding: 20px; text-align: center; }
				.content { background: #f9f9f9; padding: 20px; }
				.booking-details { background: white; padding: 15px; margin: 15px 0; border-left: 4px solid #0073aa; }
				.detail-row { padding: 8px 0; border-bottom: 1px solid #eee; }
				.label { font-weight: bold; color: #666; }
				.value { color: #333; }
				.footer { text-align: center; padding: 20px; color: #666; font-size: 12px; }
			</style>
		</head>
		<body>
			<div class="container">
				<div class="header">
					<h1><?php esc_html_e( 'Your Booking Has Been Rescheduled', 'bookit-booking-system' ); ?></h1>
				</div>

				<div class="content">
					<p><?php printf( esc_html__( 'Hi %s,', 'bookit-booking-system' ), esc_html( $booking['customer_first_name'] ) ); ?></p>

					<div class="booking-details">
						<div class="detail-row">
							<span class="label"><?php esc_html_e( 'Service:', 'bookit-booking-system' ); ?></span>
							<span class="value"><?php echo esc_html( $booking['service_name'] ); ?></span>
						</div>

						<div class="detail-row">
							<span class="label"><?php esc_html_e( 'Date:', 'bookit-booking-system' ); ?></span>
							<span class="value"><?php echo esc_html( $date_formatted ); ?></span>
						</div>

						<div class="detail-row">
							<span class="label"><?php esc_html_e( 'Time:', 'bookit-booking-system' ); ?></span>
							<span class="value"><?php echo esc_html( $time_formatted ); ?></span>
						</div>

						<div class="detail-row">
							<span class="label"><?php esc_html_e( 'Staff:', 'bookit-booking-system' ); ?></span>
							<span class="value"><?php echo esc_html( $booking['staff_name'] ); ?></span>
						</div>
					</div>

					<?php
					global $wpdb;
					$booking_id       = (int) ( $booking['id'] ?? 0 );
					$magic_link_token = isset( $booking['magic_link_token'] )
						? $booking['magic_link_token']
						: $wpdb->get_var(
							$wpdb->prepare(
								"SELECT magic_link_token FROM {$wpdb->prefix}bookings WHERE id = %d",
								$booking_id
							)
						);
					if ( ! empty( $magic_link_token ) ) {
						$ical_url       = add_query_arg(
							array(
								'booking_id' => $booking_id,
								'token'      => $magic_link_token,
							),
							rest_url( 'bookit/v1/wizard/ical' )
						);
						$cancel_url     = add_query_arg(
							array(
								'booking_id' => $booking_id,
								'token'      => $magic_link_token,
							),
							home_url( '/bookit-cancel/' )
						);
						$reschedule_url = add_query_arg(
							array(
								'booking_id' => $booking_id,
								'token'      => $magic_link_token,
							),
							home_url( '/bookit-reschedule/' )
						);
						?>
					<table cellpadding="0" cellspacing="0" border="0" width="100%" style="margin: 0;">
						<tr>
							<td style="padding: 24px 0 8px; border-top: 1px solid #E5E7EB;">
								<p style="margin: 0 0 12px; font-size: 14px; color: #6B7280; font-family: Arial, sans-serif;">
									<?php esc_html_e( 'Need to make changes?', 'bookit-booking-system' ); ?>
								</p>
								<table cellpadding="0" cellspacing="0" border="0">
									<tr>
										<td style="padding-right: 12px;">
											<a href="<?php echo esc_url( $ical_url ); ?>"
												style="display:inline-block; padding: 10px 20px; background-color: #005FB8;
													color: #ffffff; text-decoration: none; border-radius: 4px;
													font-size: 14px; font-family: Arial, sans-serif; font-weight: 600;">
												<?php esc_html_e( '📅 Add to Calendar', 'bookit-booking-system' ); ?>
											</a>
										</td>
										<td style="padding-right: 12px;">
											<a href="<?php echo esc_url( $reschedule_url ); ?>"
												style="display:inline-block; padding: 10px 20px; background-color: #005FB8;
													color: #ffffff; text-decoration: none; border-radius: 4px;
													font-size: 14px; font-family: Arial, sans-serif; font-weight: 600;">
												<?php esc_html_e( 'Reschedule', 'bookit-booking-system' ); ?>
											</a>
										</td>
										<td>
											<a href="<?php echo esc_url( $cancel_url ); ?>"
												style="display:inline-block; padding: 10px 20px; background-color: #ffffff;
													color: #374151; text-decoration: none; border-radius: 4px;
													font-size: 14px; font-family: Arial, sans-serif; font-weight: 600;
													border: 1px solid #D1D5DB;">
												<?php esc_html_e( 'Cancel Booking', 'bookit-booking-system' ); ?>
											</a>
										</td>
									</tr>
								</table>
							</td>
						</tr>
					</table>
						<?php
					}
					?>
				</div>

				<div class="footer">
					<p><?php echo esc_html( get_bloginfo( 'name' ) ); ?></p>
				</div>
			</div>
		</body>
		</html>
		<?php

		return (string) ob_get_clean();
	}

	/**
	 * Format date for email display.
	 *
	 * @param string $date Date string.
	 * @return string Formatted date.
	 */
	private function format_date( $date ) {
		return date( 'l, j F Y', strtotime( $date ) );
	}

	/**
	 * Format time for email display.
	 *
	 * @param string $time Time string.
	 * @return string Formatted time.
	 */
	private function format_time( $time ) {
		return date( 'g:i A', strtotime( $time ) );
	}

	/**
	 * Get cancellation policy text from settings storage.
	 *
	 * @return string
	 */
	private function get_cancellation_policy_text() {
		global $wpdb;

		$default_policy = __( 'Please contact us if you need to cancel or reschedule your appointment.', 'bookit-booking-system' );

		$policy_text = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT setting_value FROM {$wpdb->prefix}bookings_settings WHERE setting_key = %s LIMIT 1",
				'cancellation_policy_text'
			)
		);

		if ( null === $policy_text || '' === trim( (string) $policy_text ) ) {
			$policy_text = get_option( 'bookit_setting_cancellation_policy_text', '' );
		}

		if ( '' === trim( (string) $policy_text ) ) {
			return $default_policy;
		}

		return (string) $policy_text;
	}

	/**
	 * Fetch customer package details for customer email context.
	 *
	 * @param int $customer_package_id Customer package ID.
	 * @return array<string,mixed>|null
	 */
	private function get_package_info_for_email( int $customer_package_id ): ?array {
		global $wpdb;

		$row = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT cp.sessions_remaining, cp.sessions_total, cp.expires_at,
						pt.name AS package_type_name
				FROM {$wpdb->prefix}bookings_customer_packages cp
				JOIN {$wpdb->prefix}bookings_package_types pt ON pt.id = cp.package_type_id
				WHERE cp.id = %d
				LIMIT 1",
				$customer_package_id
			),
			ARRAY_A
		);

		return $row ? $row : null;
	}
}
