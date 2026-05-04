<?php
/**
 * My Packages public template.
 *
 * @package    Bookit_Booking_System
 * @subpackage Bookit_Booking_System/public/templates
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$render_email_form = static function ( $error_message = '' ) {
	?>
	<div class="bookit-my-packages">
		<div class="bookit-my-packages__email-form">
			<h2 class="bookit-my-packages__title"><?php esc_html_e( 'My Packages', 'bookit-booking-system' ); ?></h2>
			<p class="bookit-my-packages__intro">
				<?php esc_html_e( 'Enter your email address to view your active packages.', 'bookit-booking-system' ); ?>
			</p>
			<?php if ( '' !== $error_message ) : ?>
				<p class="bookit-redemption-history__error"><?php echo esc_html( $error_message ); ?></p>
			<?php endif; ?>
			<form method="get" action="" class="bookit-email-form">
				<?php wp_nonce_field( 'bookit_my_packages_lookup', '_bookit_nonce' ); ?>
				<div class="bookit-form-group">
					<label for="bookit-customer-email" class="bookit-form-label">
						<?php esc_html_e( 'Email address', 'bookit-booking-system' ); ?>
					</label>
					<input
						type="email"
						id="bookit-customer-email"
						name="customer_email"
						class="bookit-form-input"
						required
						placeholder="your@email.com"
						value=""
					/>
				</div>
				<button type="submit" class="bookit-btn-primary">
					<?php esc_html_e( 'View My Packages', 'bookit-booking-system' ); ?>
				</button>
			</form>
		</div>
	</div>
	<?php
};

// phpcs:ignore WordPress.Security.NonceVerification.Recommended
$submitted_email_raw = isset( $_GET['customer_email'] ) ? wp_unslash( $_GET['customer_email'] ) : '';
$customer_email      = sanitize_email( (string) $submitted_email_raw );

if ( '' !== $submitted_email_raw ) {
	// phpcs:ignore WordPress.Security.NonceVerification.Recommended
	$nonce_value = isset( $_GET['_bookit_nonce'] ) ? sanitize_text_field( wp_unslash( $_GET['_bookit_nonce'] ) ) : '';
	if ( ! wp_verify_nonce( $nonce_value, 'bookit_my_packages_lookup' ) ) {
		$render_email_form( __( 'Please submit the form again to continue.', 'bookit-booking-system' ) );
		return;
	}
}

if ( '' === $customer_email && is_user_logged_in() ) {
	$current_user = wp_get_current_user();
	// Only auto-fill for front-end customers, not WP admins/editors/authors.
	if ( ! array_intersect(
		array( 'administrator', 'editor', 'author', 'contributor' ),
		(array) $current_user->roles
	) ) {
		$customer_email = $current_user->user_email;
	}
}

if ( '' === $customer_email || ! is_email( $customer_email ) ) {
	$render_email_form();
	return;
}

global $wpdb;
$packages_enabled = $wpdb->get_var(
	$wpdb->prepare(
		"SELECT setting_value FROM {$wpdb->prefix}bookings_settings WHERE setting_key = %s LIMIT 1",
		'packages_enabled'
	)
);

if ( '1' !== (string) $packages_enabled ) {
	?>
	<div class="bookit-my-packages">
		<div class="bookit-my-packages__empty">
			<p><?php esc_html_e( 'Package sessions are not currently available.', 'bookit-booking-system' ); ?></p>
		</div>
	</div>
	<?php
	return;
}

$request_url = rest_url( 'bookit/v1/wizard/my-packages' ) . '?' . http_build_query(
	array(
		'customer_email' => $customer_email,
	)
);
$response    = wp_remote_get( esc_url_raw( $request_url ) );

if ( is_wp_error( $response ) || 200 !== (int) wp_remote_retrieve_response_code( $response ) ) {
	?>
	<div class="bookit-my-packages">
		<div class="bookit-my-packages__empty">
			<p><?php esc_html_e( 'We could not load your packages right now. Please try again.', 'bookit-booking-system' ); ?></p>
		</div>
	</div>
	<?php
	return;
}

$packages = json_decode( wp_remote_retrieve_body( $response ), true );
if ( ! is_array( $packages ) ) {
	$packages = array();
}
?>

<div class="bookit-my-packages">
	<h2 class="bookit-my-packages__title"><?php esc_html_e( 'My Packages', 'bookit-booking-system' ); ?></h2>

	<?php foreach ( $packages as $package ) : ?>
		<div class="bookit-package-card" data-package-id="<?php echo esc_attr( $package['id'] ?? 0 ); ?>">
			<div class="bookit-package-card__header">
				<span class="bookit-package-card__name">
					<?php echo esc_html( $package['package_type_name'] ?? '' ); ?>
				</span>
				<span class="bookit-package-status bookit-package-status--active">
					<?php esc_html_e( 'Active', 'bookit-booking-system' ); ?>
				</span>
			</div>

			<div class="bookit-package-card__body">
				<div class="bookit-package-card__sessions">
					<?php
					printf(
						/* translators: 1: sessions remaining, 2: sessions total */
						esc_html__( '%1$s of %2$s sessions remaining', 'bookit-booking-system' ),
						esc_html( (string) ( $package['sessions_remaining'] ?? 0 ) ),
						esc_html( (string) ( $package['sessions_total'] ?? 0 ) )
					);
					?>
				</div>
				<div class="bookit-package-card__expiry">
					<?php if ( ! empty( $package['expires_at'] ) ) : ?>
						<?php
						printf(
							/* translators: %s: expiry date */
							esc_html__( 'Expires: %s', 'bookit-booking-system' ),
							esc_html( date_i18n( get_option( 'date_format' ), strtotime( (string) $package['expires_at'] ) ) )
						);
						?>
					<?php else : ?>
						<?php esc_html_e( 'No expiry', 'bookit-booking-system' ); ?>
					<?php endif; ?>
				</div>
			</div>

			<div class="bookit-package-card__footer">
				<button
					type="button"
					class="bookit-toggle-history"
					data-package-id="<?php echo esc_attr( $package['id'] ?? 0 ); ?>"
					data-customer-email="<?php echo esc_attr( $customer_email ); ?>"
					aria-expanded="false"
				>
					<?php esc_html_e( 'Show history', 'bookit-booking-system' ); ?>
				</button>
			</div>

			<div class="bookit-redemption-history" id="bookit-history-<?php echo esc_attr( $package['id'] ?? 0 ); ?>" hidden>
				<p class="bookit-redemption-history__loading"><?php esc_html_e( 'Loading...', 'bookit-booking-system' ); ?></p>
			</div>
		</div>
	<?php endforeach; ?>

	<?php if ( empty( $packages ) ) : ?>
		<div class="bookit-my-packages__empty">
			<p><?php esc_html_e( 'No packages found for this email address.', 'bookit-booking-system' ); ?></p>
		</div>
	<?php endif; ?>
</div>

<?php if ( ! empty( $packages ) ) : ?>
	<script>
	(function() {
		document.querySelectorAll('.bookit-toggle-history').forEach(function(btn) {
			btn.addEventListener('click', function() {
				var packageId = this.dataset.packageId;
				var email = this.dataset.customerEmail;
				var historyEl = document.getElementById('bookit-history-' + packageId);
				var expanded = this.getAttribute('aria-expanded') === 'true';

				if (expanded) {
					historyEl.hidden = true;
					this.setAttribute('aria-expanded', 'false');
					this.textContent = 'Show history';
					return;
				}

				historyEl.hidden = false;
				this.setAttribute('aria-expanded', 'true');
				this.textContent = 'Hide history';

				if (historyEl.dataset.loaded) {
					return;
				}

				var url = bookitMyPackages.restUrl +
					'?customer_email=' + encodeURIComponent(email) +
					'&customer_package_id=' + encodeURIComponent(packageId);

				fetch(url, {
					headers: { 'X-WP-Nonce': bookitMyPackages.nonce }
				})
					.then(function(r) { return r.json(); })
					.then(function(items) {
						historyEl.dataset.loaded = '1';
						if (!items.length) {
							historyEl.innerHTML = '<p class="bookit-redemption-history__empty">No redemptions yet.</p>';
							return;
						}
						var html = '<ul class="bookit-redemption-list">';
						items.forEach(function(item) {
							html += '<li class="bookit-redemption-item">' +
								'<span class="bookit-redemption-item__date">' + escHtml(item.redeemed_at) + '</span>' +
								'<span class="bookit-redemption-item__service">' + escHtml(item.service_name) + '</span>' +
								'<span class="bookit-redemption-item__staff">' + escHtml(item.staff_name) + '</span>' +
								'</li>';
						});
						html += '</ul>';
						historyEl.innerHTML = html;
					})
					.catch(function() {
						historyEl.innerHTML = '<p class="bookit-redemption-history__error">Could not load history.</p>';
					});
			});
		});

		function escHtml(str) {
			var d = document.createElement('div');
			d.appendChild(document.createTextNode(str || ''));
			return d.innerHTML;
		}
	}());
	</script>
<?php endif; ?>
