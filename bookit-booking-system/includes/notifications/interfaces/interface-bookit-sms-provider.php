<?php
/**
 * SMS provider interface.
 *
 * @package Bookit_Booking_System
 * @since   1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

interface Bookit_SMS_Provider_Interface {

	/**
	 * Send an SMS message.
	 *
	 * @param string $to_phone E.164 phone number, e.g. +447911123456.
	 * @param string $message  Message body.
	 *
	 * @return bool|WP_Error True on success, WP_Error on failure.
	 */
	public function send( string $to_phone, string $message ): bool|\WP_Error;

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
	 * Provider slug.
	 *
	 * @return string
	 */
	public function get_slug(): string;
}
