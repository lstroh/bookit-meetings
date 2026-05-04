<?php
/**
 * Brevo SMS provider stub.
 *
 * Full implementation is deferred to Sprint 5 when Brevo SMS credentials
 * are available in the live environment.
 *
 * @package Bookit_Booking_System
 * @since   1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Bookit_Brevo_SMS_Provider implements Bookit_SMS_Provider_Interface {

	/**
	 * {@inheritdoc}
	 */
	public function get_name(): string {
		return 'Brevo SMS';
	}

	/**
	 * {@inheritdoc}
	 */
	public function get_slug(): string {
		return 'brevo';
	}

	/**
	 * Read a single value from wp_bookings_settings.
	 *
	 * @param string $key     Setting key.
	 * @param mixed  $default Default value if not found.
	 * @return mixed
	 */
	private static function get_setting( string $key, mixed $default = '' ): mixed {
		global $wpdb;
		$value = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT setting_value FROM {$wpdb->prefix}bookings_settings WHERE setting_key = %s LIMIT 1",
				$key
			)
		);
		return ( null !== $value && '' !== $value ) ? $value : $default;
	}

	/**
	 * {@inheritdoc}
	 *
	 * Returns true only when brevo_sms_api_key is set.
	 */
	public function is_configured(): bool {
		$key = self::get_setting( 'brevo_sms_api_key', '' );
		return ! empty( trim( (string) $key ) );
	}

	/**
	 * {@inheritdoc}
	 *
	 * Stub: logs the attempt and returns true without making any HTTP call.
	 * Full Brevo SMS implementation deferred to Sprint 5.
	 */
	public function send( string $to_phone, string $message ): bool|\WP_Error {
		error_log(
			sprintf(
				'[Bookit] Brevo SMS stub: would send to %s - "%s"',
				$to_phone,
				substr( $message, 0, 80 )
			)
		);

		return true;
	}
}
