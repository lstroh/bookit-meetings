<?php
/**
 * Stripe settings page: registration, sections, and form.
 *
 * @package    Bookit_Booking_System
 * @subpackage Bookit_Booking_System/admin/settings
 */

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
	die;
}

/**
 * Register Stripe settings with WordPress Settings API.
 *
 * @return void
 */
function bookit_register_stripe_settings(): void {
	$group = 'bookit_stripe_settings';

	register_setting(
		$group,
		'bookit_stripe_test_mode',
		array(
			'type'              => 'boolean',
			'default'           => true,
			'sanitize_callback' => function ( $value ) {
				return (bool) $value;
			},
		)
	);

	register_setting(
		$group,
		'bookit_stripe_test_publishable_key',
		array(
			'type'              => 'string',
			'default'           => '',
			'sanitize_callback' => function ( $value ) {
				$value = sanitize_text_field( is_string( $value ) ? $value : '' );
				$test_mode = get_option( 'bookit_stripe_test_mode', true );
				if ( $test_mode && ! empty( $value ) && ! Bookit_Stripe_Config::validate_publishable_key( $value, 'test' ) ) {
					add_settings_error(
						'bookit_stripe_test_publishable_key',
						'invalid_key',
						__( 'Test Publishable Key must start with pk_test_.', 'bookit-booking-system' ),
						'error'
					);
				}
				return $value;
			},
		)
	);

	register_setting(
		$group,
		'bookit_stripe_test_secret_key',
		array(
			'type'              => 'string',
			'default'           => '',
			'sanitize_callback' => function ( $value ) {
				$value = sanitize_text_field( is_string( $value ) ? $value : '' );
				$test_mode = get_option( 'bookit_stripe_test_mode', true );
				if ( $test_mode && ! empty( $value ) && ! Bookit_Stripe_Config::validate_secret_key( $value, 'test' ) ) {
					add_settings_error(
						'bookit_stripe_test_secret_key',
						'invalid_key',
						__( 'Test Secret Key must start with sk_test_.', 'bookit-booking-system' ),
						'error'
					);
				}
				return $value;
			},
		)
	);

	register_setting(
		$group,
		'bookit_stripe_test_webhook_secret',
		array(
			'type'              => 'string',
			'default'           => '',
			'sanitize_callback' => function ( $value ) {
				$value = sanitize_text_field( is_string( $value ) ? $value : '' );
				if ( ! empty( $value ) && ! Bookit_Stripe_Config::validate_webhook_secret( $value ) ) {
					add_settings_error(
						'bookit_stripe_test_webhook_secret',
						'invalid_key',
						__( 'Webhook Secret must start with whsec_.', 'bookit-booking-system' ),
						'error'
					);
				}
				return $value;
			},
		)
	);

	// Test Mode section.
	add_settings_section(
		'bookit_stripe_test_mode_section',
		__( 'Test Mode', 'bookit-booking-system' ),
		function () {
			echo '<p class="description">' . esc_html__( 'Use test keys for development. No real charges are made.', 'bookit-booking-system' ) . '</p>';
		},
		'bookit_stripe_settings',
		array()
	);

	add_settings_field(
		'bookit_stripe_test_mode',
		__( 'Enable Test Mode', 'bookit-booking-system' ),
		'bookit_render_stripe_test_mode_field',
		'bookit_stripe_settings',
		'bookit_stripe_test_mode_section',
		array( 'label_for' => 'bookit_stripe_test_mode' )
	);

	// Test API Keys section.
	add_settings_section(
		'bookit_stripe_test_keys_section',
		__( 'Test API Keys', 'bookit-booking-system' ),
		function () {
			$url = 'https://dashboard.stripe.com/test/apikeys';
			echo '<p class="description">';
			echo esc_html__( 'Get your test API keys from', 'bookit-booking-system' );
			echo ' <a href="' . esc_url( $url ) . '" target="_blank" rel="noopener noreferrer">' . esc_html__( 'Stripe Dashboard (Test)', 'bookit-booking-system' ) . '</a>. ';
			echo esc_html__( 'Publishable keys start with pk_test_, secret keys with sk_test_.', 'bookit-booking-system' );
			echo '</p>';
		},
		'bookit_stripe_settings',
		array()
	);

	add_settings_field(
		'bookit_stripe_test_publishable_key',
		__( 'Test Publishable Key', 'bookit-booking-system' ),
		'bookit_render_stripe_test_publishable_field',
		'bookit_stripe_settings',
		'bookit_stripe_test_keys_section',
		array( 'label_for' => 'bookit_stripe_test_publishable_key' )
	);

	add_settings_field(
		'bookit_stripe_test_secret_key',
		__( 'Test Secret Key', 'bookit-booking-system' ),
		'bookit_render_stripe_test_secret_field',
		'bookit_stripe_settings',
		'bookit_stripe_test_keys_section',
		array( 'label_for' => 'bookit_stripe_test_secret_key' )
	);

	add_settings_field(
		'bookit_stripe_test_webhook_secret',
		__( 'Test Webhook Secret', 'bookit-booking-system' ),
		'bookit_render_stripe_test_webhook_field',
		'bookit_stripe_settings',
		'bookit_stripe_test_keys_section',
		array( 'label_for' => 'bookit_stripe_test_webhook_secret' )
	);

	// Live API Keys section (disabled).
	add_settings_section(
		'bookit_stripe_live_keys_section',
		__( 'Live API Keys', 'bookit-booking-system' ),
		'bookit_render_stripe_live_keys_section_description',
		'bookit_stripe_settings',
		array()
	);

	add_settings_field(
		'bookit_stripe_live_notice',
		'',
		'bookit_render_stripe_live_disabled_fields',
		'bookit_stripe_settings',
		'bookit_stripe_live_keys_section',
		array()
	);
}

/**
 * Render Test Mode checkbox.
 *
 * @return void
 */
function bookit_render_stripe_test_mode_field(): void {
	$value = get_option( 'bookit_stripe_test_mode', true );
	?>
	<label for="bookit_stripe_test_mode">
		<input type="checkbox" name="bookit_stripe_test_mode" id="bookit_stripe_test_mode" value="1" <?php checked( $value ); ?> />
		<?php esc_html_e( 'Use test mode (recommended until you go live)', 'bookit-booking-system' ); ?>
	</label>
	<?php
}

/**
 * Render Test Publishable Key field.
 *
 * @return void
 */
function bookit_render_stripe_test_publishable_field(): void {
	$value = get_option( 'bookit_stripe_test_publishable_key', '' );
	?>
	<input type="text" name="bookit_stripe_test_publishable_key" id="bookit_stripe_test_publishable_key"
		value="<?php echo esc_attr( $value ); ?>" class="regular-text" autocomplete="off"
		placeholder="pk_test_..." />
	<?php
}

/**
 * Render Test Secret Key field (password).
 *
 * @return void
 */
function bookit_render_stripe_test_secret_field(): void {
	$value = get_option( 'bookit_stripe_test_secret_key', '' );
	?>
	<input type="password" name="bookit_stripe_test_secret_key" id="bookit_stripe_test_secret_key"
		value="<?php echo esc_attr( $value ); ?>" class="regular-text" autocomplete="off"
		placeholder="sk_test_..." />
	<?php
}

/**
 * Render Test Webhook Secret field (password).
 *
 * @return void
 */
function bookit_render_stripe_test_webhook_field(): void {
	$value = get_option( 'bookit_stripe_test_webhook_secret', '' );
	?>
	<input type="password" name="bookit_stripe_test_webhook_secret" id="bookit_stripe_test_webhook_secret"
		value="<?php echo esc_attr( $value ); ?>" class="regular-text" autocomplete="off"
		placeholder="whsec_..." />
	<p class="description"><?php esc_html_e( 'Optional. Required for webhook signature verification.', 'bookit-booking-system' ); ?></p>
	<?php
}

/**
 * Live keys section description (upgrade notice).
 *
 * @return void
 */
function bookit_render_stripe_live_keys_section_description(): void {
	echo '<div class="notice notice-warning inline"><p>';
	esc_html_e( 'Live mode will be enabled in production release.', 'bookit-booking-system' );
	echo '</p></div>';
}

/**
 * Render disabled Live key fields (placeholder).
 *
 * @return void
 */
function bookit_render_stripe_live_disabled_fields(): void {
	?>
	<div class="bookit-stripe-live-fields" style="opacity:0.6; pointer-events:none;">
		<p>
			<label for="bookit_stripe_live_publishable_key" class="screen-reader-text"><?php esc_html_e( 'Live Publishable Key', 'bookit-booking-system' ); ?></label>
			<input type="text" id="bookit_stripe_live_publishable_key" value="" class="regular-text" disabled placeholder="pk_live_..." />
		</p>
		<p>
			<label for="bookit_stripe_live_secret_key" class="screen-reader-text"><?php esc_html_e( 'Live Secret Key', 'bookit-booking-system' ); ?></label>
			<input type="password" id="bookit_stripe_live_secret_key" value="" class="regular-text" disabled placeholder="sk_live_..." />
		</p>
		<p>
			<label for="bookit_stripe_live_webhook_secret" class="screen-reader-text"><?php esc_html_e( 'Live Webhook Secret', 'bookit-booking-system' ); ?></label>
			<input type="password" id="bookit_stripe_live_webhook_secret" value="" class="regular-text" disabled placeholder="whsec_..." />
		</p>
	</div>
	<?php
}

/**
 * Render Stripe settings form (tab content).
 *
 * @return void
 */
function bookit_render_stripe_settings_form(): void {
	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}

	$group = 'bookit_stripe_settings';
	$test_mode = get_option( 'bookit_stripe_test_mode', true );
	?>

	<?php if ( $test_mode ) : ?>
		<div class="notice notice-info inline bookit-stripe-test-badge" style="margin:0 0 1em 0;">
			<p>
				<span class="dashicons dashicons-yes-alt" style="color:green;"></span>
				<strong><?php esc_html_e( 'Test mode is on.', 'bookit-booking-system' ); ?></strong>
				<?php esc_html_e( 'No real payments will be processed.', 'bookit-booking-system' ); ?>
			</p>
		</div>
	<?php endif; ?>

	<form method="post" action="options.php" id="bookit-stripe-settings-form">
		<?php
		settings_fields( $group );
		do_settings_sections( $group );
		submit_button( __( 'Save Settings', 'bookit-booking-system' ) );
		?>
	</form>

	<?php
	// Display validation/settings errors (e.g. invalid key format) from transient first.
	settings_errors();
	// Show success message after redirect from options.php (only if no errors were stored).
	$updated = isset( $_GET['settings-updated'] ) && $_GET['settings-updated'] === 'true';
	$errors  = get_settings_errors();
	if ( $updated && empty( $errors ) ) {
		echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__( 'Stripe settings saved successfully.', 'bookit-booking-system' ) . '</p></div>';
	}
}

/**
 * Add Stripe settings to allowed options for options.php form handling.
 *
 * @param array $allowed_options Allowed options by group.
 * @return array
 */
function bookit_stripe_allowed_options( array $allowed_options ): array {
	$allowed_options['bookit_stripe_settings'] = array(
		'bookit_stripe_test_mode',
		'bookit_stripe_test_publishable_key',
		'bookit_stripe_test_secret_key',
		'bookit_stripe_test_webhook_secret',
	);
	return $allowed_options;
}

add_filter( 'allowed_options', 'bookit_stripe_allowed_options' );

/**
 * Validate Stripe options when updated via options.php (runs before update_option).
 *
 * @param mixed  $value     New value.
 * @param string $option   Option name.
 * @param mixed  $old_value Old value.
 * @return mixed Value to save, or old value to reject update.
 */
function bookit_stripe_validate_option_update( $value, string $option, $old_value ) {
	$value = is_string( $value ) ? sanitize_text_field( $value ) : $value;
	$test_mode = get_option( 'bookit_stripe_test_mode', true );

	if ( $option === 'bookit_stripe_test_publishable_key' && $test_mode && ! empty( $value ) && ! Bookit_Stripe_Config::validate_publishable_key( $value, 'test' ) ) {
		add_settings_error(
			'bookit_stripe_test_publishable_key',
			'invalid_key',
			__( 'Test Publishable Key must start with pk_test_.', 'bookit-booking-system' ),
			'error'
		);
		return $old_value;
	}
	if ( $option === 'bookit_stripe_test_secret_key' && $test_mode && ! empty( $value ) && ! Bookit_Stripe_Config::validate_secret_key( $value, 'test' ) ) {
		add_settings_error(
			'bookit_stripe_test_secret_key',
			'invalid_key',
			__( 'Test Secret Key must start with sk_test_.', 'bookit-booking-system' ),
			'error'
		);
		return $old_value;
	}
	if ( $option === 'bookit_stripe_test_webhook_secret' && ! empty( $value ) && ! Bookit_Stripe_Config::validate_webhook_secret( $value ) ) {
		add_settings_error(
			'bookit_stripe_test_webhook_secret',
			'invalid_key',
			__( 'Webhook Secret must start with whsec_.', 'bookit-booking-system' ),
			'error'
		);
		return $old_value;
	}
	return $value;
}

add_filter( 'pre_update_option_bookit_stripe_test_publishable_key', 'bookit_stripe_validate_option_update', 10, 3 );
add_filter( 'pre_update_option_bookit_stripe_test_secret_key', 'bookit_stripe_validate_option_update', 10, 3 );
add_filter( 'pre_update_option_bookit_stripe_test_webhook_secret', 'bookit_stripe_validate_option_update', 10, 3 );

/**
 * Normalize test mode checkbox: unchecked = false (not sent in POST).
 *
 * @param mixed  $value     New value.
 * @param string $option   Option name.
 * @param mixed  $old_value Old value.
 * @return bool
 */
function bookit_stripe_sanitize_test_mode( $value, string $option, $old_value ): bool {
	if ( $option !== 'bookit_stripe_test_mode' ) {
		return (bool) $value;
	}
	// Checkbox unchecked = not in POST; options.php may pass null. Only explicit truthy = true.
	return ( $value === true || $value === '1' || $value === 1 );
}

add_filter( 'pre_update_option_bookit_stripe_test_mode', 'bookit_stripe_sanitize_test_mode', 10, 3 );

// Register on admin_init; if already fired (e.g. when loading this file from payment-settings page), register now.
if ( did_action( 'admin_init' ) ) {
	bookit_register_stripe_settings();
} else {
	add_action( 'admin_init', 'bookit_register_stripe_settings' );
}
