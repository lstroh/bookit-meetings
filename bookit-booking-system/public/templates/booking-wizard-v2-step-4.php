<?php
/**
 * Booking Wizard V2 — Step 4: Contact details
 *
 * @package    Bookit_Booking_System
 * @subpackage Bookit_Booking_System/public/templates
 */

if ( ! defined( 'WPINC' ) ) {
	die;
}

require_once BOOKIT_PLUGIN_DIR . 'includes/core/class-session-manager.php';
Bookit_Session_Manager::init();
$session           = Bookit_Session_Manager::get_data();
$service_name      = isset( $session['service_name'] ) ? $session['service_name'] : '';
$service_duration  = isset( $session['service_duration'] ) ? (int) $session['service_duration'] : 0;
$staff_name        = isset( $session['staff_name'] ) ? $session['staff_name'] : '';
$booking_date      = isset( $session['date'] ) ? $session['date'] : '';
$booking_time      = isset( $session['time'] ) ? $session['time'] : '';
$first_name        = isset( $session['customer_first_name'] ) ? $session['customer_first_name'] : '';
$last_name         = isset( $session['customer_last_name'] ) ? $session['customer_last_name'] : '';
$email             = isset( $session['customer_email'] ) ? $session['customer_email'] : '';
$phone             = isset( $session['customer_phone'] ) ? $session['customer_phone'] : '';
$special_requests  = isset( $session['customer_special_requests'] ) ? $session['customer_special_requests'] : '';
$marketing_consent = isset( $session['marketing_consent'] ) ? (bool) $session['marketing_consent'] : false;
$waiver_given      = isset( $session['cooling_off_waiver'] ) ? (bool) $session['cooling_off_waiver'] : false;

require_once BOOKIT_PLUGIN_DIR . 'includes/functions-cooling-off.php';
$requires_waiver = ! empty( $booking_date ) && bookit_booking_requires_waiver( $booking_date );

$display_date = ! empty( $booking_date )
	? date_i18n( 'd M Y', strtotime( $booking_date ) )
	: '';
$display_time = ! empty( $booking_time )
	? date_i18n( 'H:i', strtotime( $booking_time ) )
	: '';

// Match legacy step 4 prerequisites (session uses 'date' and 'time').
if ( ! isset( $session['service_id'], $session['staff_id'], $session['date'], $session['time'] ) ) {
	?>
<div class="bookit-v2-step bookit-v2-step--4 bookit-step-4">
	<div class="bookit-v2-step-body">
		<p class="bookit-error"><?php esc_html_e( 'Please complete the previous steps first.', 'bookit-booking-system' ); ?></p>
	</div>
</div>
	<?php
	return;
}

$banner_duration = $service_duration > 0 ? sprintf( /* translators: %d: duration in minutes */ '%d min', $service_duration ) : '';
$banner_when     = trim( $display_date . ( $display_date && $display_time ? ' ' : '' ) . $display_time );
$banner_bits       = array_filter(
	array(
		$service_name,
		$banner_duration,
		$staff_name,
		$banner_when,
	),
	function ( $part ) {
		return '' !== (string) $part;
	}
);
$banner_text = implode( ' · ', $banner_bits );

$special_requests_prefilled = '' !== trim( (string) $special_requests );
?>
<div class="bookit-v2-step bookit-v2-step--4 bookit-step-4">
	<div class="bookit-v2-step-body">
		<div class="bookit-v2-confirm-banner">
			<span class="bookit-v2-confirm-banner-text"><?php echo esc_html( $banner_text ); ?></span>
			<button type="button" class="bookit-v2-confirm-banner-change" data-goto-step="3"><?php esc_html_e( 'Change', 'bookit-booking-system' ); ?></button>
		</div>

		<h2 class="bookit-v2-step-heading"><?php esc_html_e( 'Your details', 'bookit-booking-system' ); ?></h2>
		<p class="bookit-v2-step-subheading"><?php esc_html_e( 'Almost there — just a few details to confirm your booking.', 'bookit-booking-system' ); ?></p>

		<form id="bookit-contact-form" class="bookit-contact-form bookit-v2-contact-form" novalidate>
			<?php
			require_once BOOKIT_PLUGIN_DIR . 'includes/class-csrf-protection.php';
			Bookit_CSRF_Protection::nonce_field( true, true );
			?>

			<div class="bookit-v2-form-group">
				<label class="bookit-v2-form-label" for="first-name"><?php esc_html_e( 'First name', 'bookit-booking-system' ); ?></label>
				<input class="bookit-v2-form-input" type="text" id="first-name" name="first_name"
					value="<?php echo esc_attr( $first_name ); ?>" autocomplete="given-name"
					inputmode="text" maxlength="100" aria-required="true"
					aria-describedby="first-name-error" />
				<span id="first-name-error" class="bookit-v2-field-error" role="alert"></span>
			</div>

			<div class="bookit-v2-form-group">
				<label class="bookit-v2-form-label" for="last-name"><?php esc_html_e( 'Last name', 'bookit-booking-system' ); ?></label>
				<input class="bookit-v2-form-input" type="text" id="last-name" name="last_name"
					value="<?php echo esc_attr( $last_name ); ?>" autocomplete="family-name"
					inputmode="text" maxlength="100" aria-required="true"
					aria-describedby="last-name-error" />
				<span id="last-name-error" class="bookit-v2-field-error" role="alert"></span>
			</div>

			<div class="bookit-v2-form-group">
				<label class="bookit-v2-form-label" for="email"><?php esc_html_e( 'Email address', 'bookit-booking-system' ); ?></label>
				<input class="bookit-v2-form-input" type="email" id="email" name="email"
					value="<?php echo esc_attr( $email ); ?>" autocomplete="email"
					inputmode="email" maxlength="255" aria-required="true"
					aria-describedby="email-error" />
				<span id="email-error" class="bookit-v2-field-error" role="alert"></span>
			</div>

			<div class="bookit-v2-form-group">
				<label class="bookit-v2-form-label" for="phone"><?php esc_html_e( 'Phone number', 'bookit-booking-system' ); ?></label>
				<input class="bookit-v2-form-input" type="tel" id="phone" name="phone"
					value="<?php echo esc_attr( $phone ); ?>" autocomplete="tel"
					inputmode="tel" placeholder="07700 900000" maxlength="20"
					aria-required="true" aria-describedby="phone-error" />
				<span id="phone-error" class="bookit-v2-field-error" role="alert"></span>
			</div>

			<?php if ( ! $special_requests_prefilled ) : ?>
			<button type="button" class="bookit-v2-special-requests-toggle" id="bookit-v2-special-requests-toggle">
				<span class="bookit-v2-sr-plus">+</span> <?php esc_html_e( 'Add special requests', 'bookit-booking-system' ); ?>
			</button>
			<?php endif; ?>
			<textarea id="special-requests" name="special_requests"
				class="bookit-v2-form-input" rows="3"
				<?php echo $special_requests_prefilled ? '' : ' style="display:none;"'; ?>
				maxlength="500"
				aria-label="<?php esc_attr_e( 'Special requests', 'bookit-booking-system' ); ?>"><?php echo esc_textarea( $special_requests ); ?></textarea>

			<div class="bookit-v2-form-divider"></div>

			<div class="bookit-v2-checkbox-group">
				<input type="checkbox" id="marketing-consent" name="marketing_consent"
					value="1" <?php checked( $marketing_consent, true ); ?> />
				<label class="bookit-v2-checkbox-label" for="marketing-consent">
					<?php esc_html_e( 'Keep me updated with offers and news', 'bookit-booking-system' ); ?>
				</label>
			</div>
			<p class="bookit-v2-checkbox-helper"><?php esc_html_e( 'You can unsubscribe at any time.', 'bookit-booking-system' ); ?></p>

			<?php if ( $requires_waiver ) : ?>
			<div class="bookit-v2-waiver-block" id="cooling-off-waiver-group">
				<p class="bookit-v2-waiver-heading"><?php esc_html_e( 'Important: Right to Cancel', 'bookit-booking-system' ); ?></p>
				<p class="bookit-v2-waiver-body">
					<?php esc_html_e( 'Your appointment is within 14 days. Under the Consumer Contracts Regulations 2013, you normally have a 14-day right to cancel. By checking the box below, you request that we begin the service before this period expires and acknowledge that you will lose this cancellation right once the service has been performed.', 'bookit-booking-system' ); ?>
				</p>
				<div class="bookit-v2-checkbox-group">
					<input type="checkbox" id="cooling-off-waiver" name="cooling_off_waiver"
						value="1" <?php checked( $waiver_given, true ); ?>
						aria-required="true" aria-describedby="cooling-off-waiver-error" />
					<label class="bookit-v2-checkbox-label" for="cooling-off-waiver">
						<?php esc_html_e( 'I expressly request this service to begin before the 14-day cancellation period expires, and I understand that I will lose my right to cancel once the service has begun.', 'bookit-booking-system' ); ?>
					</label>
				</div>
				<span id="cooling-off-waiver-error" class="bookit-v2-field-error" role="alert"></span>
			</div>
			<?php endif; ?>

			<div class="bookit-v2-sticky-footer">
				<div class="bookit-v2-footer-inner">
					<button type="submit" class="bookit-v2-cta-btn">
						<?php esc_html_e( 'Continue', 'bookit-booking-system' ); ?>
					</button>
					<a href="?step=3" class="bookit-v2-btn-back"><?php esc_html_e( 'Back', 'bookit-booking-system' ); ?></a>
				</div>
			</div>
		</form>
	</div>
</div>
