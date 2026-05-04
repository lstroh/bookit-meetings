<?php
/**
 * Email provider interface.
 *
 * All email providers must implement this interface.
 *
 * @package Bookit_Booking_System
 * @since   1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

interface Bookit_Email_Provider_Interface {

	/**
	 * Send an email.
	 *
	 * @param array  $to        Recipient: ['email' => string, 'name' => string].
	 * @param string $subject   Email subject.
	 * @param string $html_body HTML body content.
	 * @param array  $params    Optional provider-specific parameters.
	 *
	 * @return bool|WP_Error True on success, WP_Error on failure.
	 */
	public function send( array $to, string $subject, string $html_body, array $params = [] ): bool|\WP_Error;

	/**
	 * Whether this provider is configured and ready to send.
	 *
	 * @return bool
	 */
	public function is_configured(): bool;

	/**
	 * Human-readable provider name.
	 *
	 * @return string
	 */
	public function get_name(): string;

	/**
	 * Provider slug (used in settings storage).
	 *
	 * @return string
	 */
	public function get_slug(): string;
}
