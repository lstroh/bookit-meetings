<?php
/**
 * WordPress mail fallback email provider.
 *
 * Wraps wp_mail() and is always available as the default provider when
 * no third-party API key is configured.
 *
 * @package Bookit_Booking_System
 * @since   1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Bookit_WP_Mail_Fallback_Provider implements Bookit_Email_Provider_Interface {

	/**
	 * {@inheritdoc}
	 */
	public function get_name(): string {
		return 'WordPress Mail';
	}

	/**
	 * {@inheritdoc}
	 */
	public function get_slug(): string {
		return 'wp_mail';
	}

	/**
	 * {@inheritdoc}
	 *
	 * wp_mail is always available.
	 */
	public function is_configured(): bool {
		return true;
	}

	/**
	 * {@inheritdoc}
	 *
	 * Sends via wp_mail() with HTML content-type header.
	 */
	public function send( array $to, string $subject, string $html_body, array $params = [] ): bool|\WP_Error {
		$headers = [ 'Content-Type: text/html; charset=UTF-8' ];

		$sent = wp_mail(
			sanitize_email( (string) ( $to['email'] ?? '' ) ),
			$subject,
			$html_body,
			$headers
		);

		if ( ! $sent ) {
			return new \WP_Error(
				'wp_mail_failed',
				__( 'wp_mail() returned false. Check your server mail configuration.', 'booking-system' )
			);
		}

		return true;
	}
}
