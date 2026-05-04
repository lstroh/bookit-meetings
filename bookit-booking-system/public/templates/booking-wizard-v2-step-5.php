<?php
/**
 * Booking Wizard V2 — Step 5: Payment
 *
 * @package    Bookit_Booking_System
 * @subpackage Bookit_Booking_System/public/templates
 */

if ( ! defined( 'WPINC' ) ) {
	die;
}

/**
 * Whether a package type applies to the booking service (PHP filter; mirrors Bookit_Available_Packages_API::get_available_packages()).
 *
 * @param string|null $applicable_service_ids Raw package_types.applicable_service_ids value.
 * @param int         $service_id             Current booking service ID.
 * @return bool
 */
if ( ! function_exists( 'bookit_v2_step5_package_matches_service' ) ) {
	function bookit_v2_step5_package_matches_service( $applicable_service_ids, $service_id ) {
		$service_id = absint( $service_id );

		if ( null === $applicable_service_ids || '' === (string) $applicable_service_ids || '[]' === (string) $applicable_service_ids ) {
			return true;
		}

		$service_ids = null;
		if ( isset( $applicable_service_ids ) && null !== $applicable_service_ids && '' !== (string) $applicable_service_ids ) {
			$decoded = json_decode( (string) $applicable_service_ids, true );
			if ( is_array( $decoded ) ) {
				$service_ids = array_values( array_map( 'absint', $decoded ) );
			}
		}

		if ( null !== $service_ids && ! in_array( $service_id, $service_ids, true ) ) {
			return false;
		}

		return true;
	}
}

require_once BOOKIT_PLUGIN_DIR . 'includes/core/class-session-manager.php';
Bookit_Session_Manager::init();
$session_data = Bookit_Session_Manager::get_data();

if ( empty( $session_data['service_id'] ) || empty( $session_data['staff_id'] ) || empty( $session_data['date'] ) || empty( $session_data['time'] )
	|| empty( $session_data['customer_email'] ) ) {
	?>
<div class="bookit-v2-step bookit-v2-step--5">
	<div class="bookit-v2-step-body">
		<p class="bookit-error"><?php esc_html_e( 'Please complete the previous steps first.', 'bookit-booking-system' ); ?></p>
	</div>
</div>
	<?php
	return;
}

global $wpdb;

$service = $wpdb->get_row(
	$wpdb->prepare(
		"SELECT * FROM {$wpdb->prefix}bookings_services WHERE id = %d",
		(int) $session_data['service_id']
	),
	ARRAY_A
);

if ( ! $service ) {
	echo '<p>' . esc_html__( 'Service not found.', 'bookit-booking-system' ) . '</p>';
	return;
}

$staff_name = '';
if ( ! empty( $session_data['staff_id'] ) ) {
	$staff = $wpdb->get_row(
		$wpdb->prepare(
			"SELECT first_name, last_name FROM {$wpdb->prefix}bookings_staff WHERE id = %d",
			(int) $session_data['staff_id']
		),
		ARRAY_A
	);
	if ( $staff ) {
		$staff_name = trim( $staff['first_name'] . ' ' . $staff['last_name'] );
	}
}
if ( empty( $staff_name ) ) {
	$staff_name = isset( $session_data['staff_name'] ) ? $session_data['staff_name'] : '';
}

require_once BOOKIT_PLUGIN_DIR . 'includes/wizard-v2-payment-amounts.php';
$amounts            = bookit_v2_compute_payment_amounts_from_service( $service );
$has_deposit        = $amounts['has_deposit'];
$deposit_due        = $amounts['deposit_due'];
$balance_due        = $amounts['balance_due'];
$total_price        = $amounts['total_price'];
$stripe_charge_amount = bookit_v2_stripe_charge_amount( $amounts );
$show_online_payment  = $stripe_charge_amount > 0;

$cancellation_policy_text = $wpdb->get_var(
	$wpdb->prepare(
		"SELECT setting_value FROM {$wpdb->prefix}bookings_settings WHERE setting_key = %s LIMIT 1",
		'cancellation_policy_text'
	)
);
if ( null === $cancellation_policy_text || '' === trim( (string) $cancellation_policy_text ) ) {
	$cancellation_policy_text = get_option( 'bookit_cancellation_policy_text', '' );
}

$cancellation_policy_text = trim( (string) $cancellation_policy_text );

$policy_first_sentence = '';
$policy_remainder      = '';

if ( ! empty( $cancellation_policy_text ) ) {
	$dot_pos = strpos( $cancellation_policy_text, '. ' );
	if ( false !== $dot_pos ) {
		$policy_first_sentence = substr(
			$cancellation_policy_text,
			0,
			$dot_pos + 1
		);
		$policy_remainder = trim(
			substr( $cancellation_policy_text, $dot_pos + 2 )
		);
	} else {
		// Single sentence — show all, no expand link.
		$policy_first_sentence = $cancellation_policy_text;
		$policy_remainder      = '';
	}
}

$packages_enabled = $wpdb->get_var(
	$wpdb->prepare(
		"SELECT setting_value FROM {$wpdb->prefix}bookings_settings WHERE setting_key = %s LIMIT 1",
		'packages_enabled'
	)
);

$customer_packages = array();
$customer_email    = isset( $session_data['customer_email'] ) ? $session_data['customer_email'] : '';
if ( '1' === $packages_enabled && ! empty( $customer_email ) ) {
	$customer_packages = $wpdb->get_results(
		$wpdb->prepare(
			"SELECT cp.*, pt.name as package_name, pt.applicable_service_ids AS pt_applicable_service_ids
			 FROM {$wpdb->prefix}bookings_customer_packages cp
			 INNER JOIN {$wpdb->prefix}bookings_package_types pt ON cp.package_type_id = pt.id
			 WHERE cp.customer_id = (
				 SELECT id FROM {$wpdb->prefix}bookings_customers WHERE email = %s LIMIT 1
			 )
			 AND cp.status = 'active'
			 AND cp.sessions_remaining > 0
			 AND (cp.expires_at IS NULL OR cp.expires_at > NOW())",
			$customer_email
		),
		ARRAY_A
	);
	if ( ! is_array( $customer_packages ) ) {
		$customer_packages = array();
	}

	$service_id_for_packages = (int) $session_data['service_id'];
	$filtered_customer_pkgs  = array();
	foreach ( $customer_packages as $cp_row ) {
		$applicable = isset( $cp_row['pt_applicable_service_ids'] ) ? $cp_row['pt_applicable_service_ids'] : null;
		if ( bookit_v2_step5_package_matches_service( $applicable, $service_id_for_packages ) ) {
			unset( $cp_row['pt_applicable_service_ids'] );
			$filtered_customer_pkgs[] = $cp_row;
		}
	}
	$customer_packages = $filtered_customer_pkgs;
}

$available_packages = array();
if ( '1' === $packages_enabled && empty( $customer_packages ) ) {
	$available_packages = $wpdb->get_results(
		"SELECT * FROM {$wpdb->prefix}bookings_package_types WHERE is_active = 1",
		ARRAY_A
	);
	if ( ! is_array( $available_packages ) ) {
		$available_packages = array();
	} else {
		$service_id_for_packages = (int) $session_data['service_id'];
		$filtered_available_pkgs = array();
		foreach ( $available_packages as $pkg_row ) {
			$applicable = isset( $pkg_row['applicable_service_ids'] ) ? $pkg_row['applicable_service_ids'] : null;
			if ( bookit_v2_step5_package_matches_service( $applicable, $service_id_for_packages ) ) {
				$filtered_available_pkgs[] = $pkg_row;
			}
		}
		$available_packages = $filtered_available_pkgs;
	}
}

$zone_b_variant = 'none';
if ( ! empty( $customer_packages ) ) {
	$zone_b_variant = 'use_package';
} elseif ( ! empty( $available_packages ) ) {
	$zone_b_variant = 'buy_package';
}

$service_name     = isset( $session_data['service_name'] ) ? $session_data['service_name'] : $service['name'];
$service_duration = isset( $session_data['service_duration'] ) ? (int) $session_data['service_duration'] : (int) $service['duration'];
$booking_date     = isset( $session_data['date'] ) ? $session_data['date'] : '';
$booking_time     = isset( $session_data['time'] ) ? $session_data['time'] : '';
$display_date       = ! empty( $booking_date ) ? date_i18n( 'l, d F Y', strtotime( $booking_date ) ) : '';
$display_time       = ! empty( $booking_time ) ? date_i18n( 'H:i', strtotime( $booking_time ) ) : '';
$banner_duration    = $service_duration > 0 ? sprintf( /* translators: %d: minutes */ '%d min', $service_duration ) : '';
$banner_bits        = array_filter(
	array(
		$service_name,
		$banner_duration,
		$staff_name,
		trim( $display_date . ( $display_date && $display_time ? ' ' : '' ) . $display_time ),
	),
	static function ( $p ) {
		return '' !== (string) $p;
	}
);
$banner_line = implode( ' · ', $banner_bits );

$zone_c_label = __( 'How would you like to pay?', 'bookit-booking-system' );
if ( 'buy_package' === $zone_b_variant ) {
	$zone_c_label = __( 'Or pay for this session only', 'bookit-booking-system' );
} elseif ( 'use_package' === $zone_b_variant ) {
	$zone_c_label = __( 'Or pay now', 'bookit-booking-system' );
}
?>
<div class="bookit-v2-step bookit-v2-step--5">
	<div class="bookit-v2-step-body">

		<div class="bookit-v2-confirm-banner">
			<span class="bookit-v2-confirm-banner-text"><?php echo esc_html( $banner_line ); ?></span>
			<button type="button" class="bookit-v2-confirm-banner-change" data-goto-step="4"><?php esc_html_e( 'Change', 'bookit-booking-system' ); ?></button>
		</div>

		<div class="bookit-v2-zone-a">
			<p class="bookit-v2-zone-label"><?php esc_html_e( 'Review your booking', 'bookit-booking-system' ); ?></p>
			<div class="bookit-v2-summary-rows">
				<div class="bookit-v2-summary-row">
					<span class="bookit-v2-summary-key"><?php esc_html_e( 'Service', 'bookit-booking-system' ); ?></span>
					<span class="bookit-v2-summary-val"><?php echo esc_html( $service_name ); ?></span>
				</div>
				<div class="bookit-v2-summary-row">
					<span class="bookit-v2-summary-key"><?php esc_html_e( 'Duration', 'bookit-booking-system' ); ?></span>
					<span class="bookit-v2-summary-val"><?php echo esc_html( (string) $service_duration ); ?> <?php esc_html_e( 'min', 'bookit-booking-system' ); ?></span>
				</div>
				<div class="bookit-v2-summary-row">
					<span class="bookit-v2-summary-key"><?php esc_html_e( 'With', 'bookit-booking-system' ); ?></span>
					<span class="bookit-v2-summary-val"><?php echo esc_html( $staff_name ); ?></span>
				</div>
				<div class="bookit-v2-summary-row">
					<span class="bookit-v2-summary-key"><?php esc_html_e( 'Date', 'bookit-booking-system' ); ?></span>
					<span class="bookit-v2-summary-val"><?php echo esc_html( $display_date ); ?></span>
				</div>
				<div class="bookit-v2-summary-row">
					<span class="bookit-v2-summary-key"><?php esc_html_e( 'Time', 'bookit-booking-system' ); ?></span>
					<span class="bookit-v2-summary-val"><?php echo esc_html( $display_time ); ?></span>
				</div>
			</div>
			<div class="bookit-v2-zone-divider"></div>

			<?php if ( $has_deposit ) : ?>
			<div class="bookit-v2-deposit-rows">
				<div class="bookit-v2-deposit-row">
					<span class="bookit-v2-deposit-key"><?php esc_html_e( 'Today (deposit)', 'bookit-booking-system' ); ?></span>
					<span class="bookit-v2-deposit-val"><?php echo esc_html( '£' . number_format( $deposit_due, 2 ) ); ?></span>
				</div>
				<div class="bookit-v2-deposit-row">
					<span class="bookit-v2-deposit-key"><?php esc_html_e( 'Remaining (on the day)', 'bookit-booking-system' ); ?></span>
					<span class="bookit-v2-deposit-val"><?php echo esc_html( '£' . number_format( $balance_due, 2 ) ); ?></span>
				</div>
				<div class="bookit-v2-deposit-row bookit-v2-deposit-row--total">
					<span class="bookit-v2-deposit-key"><?php esc_html_e( 'Total', 'bookit-booking-system' ); ?></span>
					<span class="bookit-v2-deposit-val"><?php echo esc_html( '£' . number_format( $total_price, 2 ) ); ?></span>
				</div>
			</div>
			<?php else : ?>
			<div class="bookit-v2-deposit-rows">
				<div class="bookit-v2-deposit-row bookit-v2-deposit-row--total">
					<span class="bookit-v2-deposit-key"><?php esc_html_e( 'Total due today', 'bookit-booking-system' ); ?></span>
					<span class="bookit-v2-deposit-val"><?php echo esc_html( '£' . number_format( $total_price, 2 ) ); ?></span>
				</div>
			</div>
			<?php endif; ?>

			<?php if ( ! empty( $cancellation_policy_text ) ) : ?>
			<div class="bookit-v2-policy-notice">
				<p class="bookit-v2-policy-notice__summary">
					<?php echo esc_html( $policy_first_sentence ); ?>
					<?php if ( ! empty( $policy_remainder ) ) : ?>
					<button
						type="button"
						class="bookit-v2-policy-expand-btn"
						aria-expanded="false"
						aria-controls="bookit-v2-policy-full">
						<?php esc_html_e( 'See full policy', 'bookit-booking-system' ); ?>
					</button>
					<?php endif; ?>
				</p>
				<?php if ( ! empty( $policy_remainder ) ) : ?>
				<p class="bookit-v2-policy-notice__full"
					id="bookit-v2-policy-full"
					hidden>
					<?php echo esc_html( $policy_remainder ); ?>
				</p>
				<?php endif; ?>
			</div>
			<?php if ( ! empty( $policy_remainder ) ) : ?>
			<script>
(function() {
	var btn = document.querySelector(
		'.bookit-v2-policy-expand-btn'
	);
	if ( ! btn ) return;
	btn.addEventListener( 'click', function() {
		var full = document.getElementById(
			'bookit-v2-policy-full'
		);
		var expanded = btn.getAttribute(
			'aria-expanded'
		) === 'true';
		if ( expanded ) {
			full.hidden = true;
			btn.setAttribute( 'aria-expanded', 'false' );
			btn.textContent = <?php echo wp_json_encode( __( 'See full policy', 'bookit-booking-system' ) ); ?>;
		} else {
			full.hidden = false;
			btn.setAttribute( 'aria-expanded', 'true' );
			btn.textContent = <?php echo wp_json_encode( __( 'Hide full policy', 'bookit-booking-system' ) ); ?>;
		}
	} );
} )();
			</script>
			<?php endif; ?>
			<?php endif; ?>
		</div>

		<?php if ( 'use_package' === $zone_b_variant ) : ?>
		<div class="bookit-v2-zone-b bookit-v2-zone-b--use-package">
			<p class="bookit-v2-zone-label"><?php esc_html_e( 'Your packages', 'bookit-booking-system' ); ?></p>
			<p class="bookit-v2-zone-b-intro">
				<?php esc_html_e( 'You have an active package for this service — use a session instead of paying now.', 'bookit-booking-system' ); ?>
			</p>
			<?php foreach ( $customer_packages as $pkg ) : ?>
			<div class="bookit-v2-package-row" data-package-id="<?php echo esc_attr( $pkg['id'] ); ?>" data-value="use_package_<?php echo esc_attr( $pkg['id'] ); ?>">
				<input type="radio" name="bookit_v2_payment_choice"
					id="pkg-<?php echo esc_attr( $pkg['id'] ); ?>" value="use_package_<?php echo esc_attr( $pkg['id'] ); ?>"
					data-package-id="<?php echo esc_attr( $pkg['id'] ); ?>" />
				<div class="bookit-v2-package-info">
					<p class="bookit-v2-package-name"><?php echo esc_html( $pkg['package_name'] ); ?></p>
					<p class="bookit-v2-package-meta">
						<?php
						echo esc_html( (string) (int) $pkg['sessions_remaining'] );
						echo ' ';
						esc_html_e( 'sessions remaining', 'bookit-booking-system' );
						?>
						<?php if ( ! empty( $pkg['expires_at'] ) ) : ?>
							<?php echo ' · '; ?>
							<?php
							echo esc_html(
								sprintf(
									/* translators: %s: formatted expiry date */
									__( 'expires %s', 'bookit-booking-system' ),
									date_i18n( 'd M Y', strtotime( (string) $pkg['expires_at'] ) )
								)
							);
							?>
						<?php endif; ?>
					</p>
				</div>
			</div>
			<?php endforeach; ?>
		</div>
		<?php elseif ( 'buy_package' === $zone_b_variant ) : ?>
		<div class="bookit-v2-zone-b bookit-v2-zone-b--buy-package">
			<p class="bookit-v2-zone-label"><?php esc_html_e( 'Save with a package', 'bookit-booking-system' ); ?></p>
			<p class="bookit-v2-zone-b-intro">
				<?php esc_html_e( 'Book multiple sessions and save — use your first session for this appointment.', 'bookit-booking-system' ); ?>
			</p>
			<?php foreach ( $available_packages as $pkg ) : ?>
			<div class="bookit-v2-package-row" data-value="buy_<?php echo esc_attr( $pkg['id'] ); ?>">
				<input type="radio" name="bookit_v2_payment_choice"
					id="buy-pkg-<?php echo esc_attr( $pkg['id'] ); ?>" value="buy_<?php echo esc_attr( $pkg['id'] ); ?>" />
				<div class="bookit-v2-package-info">
					<p class="bookit-v2-package-name"><?php echo esc_html( $pkg['name'] ); ?></p>
					<?php if ( 'discount' === ( $pkg['price_mode'] ?? '' ) && ! empty( $pkg['discount_percentage'] ) ) : ?>
					<p class="bookit-v2-package-saving">
						<?php
						echo esc_html(
							sprintf(
								/* translators: %s: discount percentage */
								__( 'Save %s%%', 'bookit-booking-system' ),
								number_format( (float) $pkg['discount_percentage'], 0 )
							)
						);
						?>
					</p>
					<?php endif; ?>
				</div>
				<?php
				$pkg_display_price = '';
				if ( 'fixed' === ( $pkg['price_mode'] ?? '' ) && ! empty( $pkg['fixed_price'] ) ) {
					$pkg_display_price = '£' . number_format( (float) $pkg['fixed_price'], 2 );
				}
				?>
				<?php if ( '' !== $pkg_display_price ) : ?>
				<span class="bookit-v2-package-price"><?php echo esc_html( $pkg_display_price ); ?></span>
				<?php endif; ?>
			</div>
			<?php endforeach; ?>
			<p style="font-size:12px;color:var(--bookit-text-secondary);margin-top:8px;">
				<?php esc_html_e( 'Your appointment will be confirmed after the package purchase.', 'bookit-booking-system' ); ?>
			</p>
		</div>
		<?php endif; ?>

		<div class="bookit-v2-zone-c" id="bookit-v2-zone-c">
			<p class="bookit-v2-zone-label"><?php echo esc_html( $zone_c_label ); ?></p>
			<div class="bookit-v2-payment-rows">

				<?php if ( $show_online_payment ) : ?>
				<div class="bookit-v2-payment-row bookit-v2-payment-row--selected" id="bookit-v2-pay-card"
					data-value="card">
					<input type="radio" name="bookit_v2_payment_choice"
						id="bookit-v2-radio-card" value="card" checked />
					<div class="bookit-v2-payment-label-group">
						<p class="bookit-v2-payment-label"><?php esc_html_e( 'Pay by card', 'bookit-booking-system' ); ?></p>
					</div>
					<div class="bookit-v2-payment-logos">
						<span class="bookit-v2-logo-pill bookit-v2-logo-pill--visa">VISA</span>
						<span class="bookit-v2-logo-pill bookit-v2-logo-pill--mc">MC</span>
					</div>
				</div>
				<?php endif; ?>

				<?php if ( $show_online_payment ) : ?>
				<div class="bookit-v2-payment-row" id="bookit-v2-pay-paypal" data-value="paypal">
					<input type="radio" name="bookit_v2_payment_choice"
						id="bookit-v2-radio-paypal" value="paypal" />
					<div class="bookit-v2-payment-label-group">
						<p class="bookit-v2-payment-label"><?php esc_html_e( 'PayPal', 'bookit-booking-system' ); ?></p>
					</div>
					<div class="bookit-v2-payment-logos">
						<span class="bookit-v2-logo-pill bookit-v2-logo-pill--paypal">PayPal</span>
					</div>
				</div>
				<?php endif; ?>

				<div class="bookit-v2-payment-row<?php echo $show_online_payment ? '' : ' bookit-v2-payment-row--selected'; ?>" id="bookit-v2-pay-person" data-value="person">
					<input type="radio" name="bookit_v2_payment_choice"
						id="bookit-v2-radio-person" value="person"<?php echo $show_online_payment ? '' : ' checked'; ?> />
					<div class="bookit-v2-payment-label-group">
						<p class="bookit-v2-payment-label"><?php esc_html_e( 'Pay in person', 'bookit-booking-system' ); ?></p>
						<p class="bookit-v2-payment-sub"><?php esc_html_e( 'No payment needed now', 'bookit-booking-system' ); ?></p>
					</div>
				</div>

			</div>
		</div>

		<script>
		( function() {
			document.addEventListener( 'DOMContentLoaded', function() {
				if ( typeof bookitWizardV2 !== 'undefined' ) {
					bookitWizardV2.showOnlinePayment = <?php echo wp_json_encode( (bool) $show_online_payment ); ?>;
				}
			} );
		} )();
		</script>

		<div class="bookit-v2-sticky-footer">
			<div class="bookit-v2-footer-inner">
				<button type="button" class="bookit-v2-cta-btn" id="bookit-v2-cta-btn">
					<?php esc_html_e( 'Continue', 'bookit-booking-system' ); ?>
				</button>
				<a href="?step=4" class="bookit-v2-btn-back"><?php esc_html_e( 'Back', 'bookit-booking-system' ); ?></a>
			</div>
		</div>

	</div>
</div>
