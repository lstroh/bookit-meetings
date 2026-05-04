<?php
/**
 * CSRF protection for booking forms and AJAX.
 *
 * @package    Bookit_Booking_System
 * @subpackage Bookit_Booking_System/includes
 */

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
	die;
}

/**
 * CSRF protection class.
 */
class Bookit_CSRF_Protection {

	/**
	 * Nonce action for booking forms.
	 *
	 * @var string
	 */
	const NONCE_ACTION = 'bookit_booking';

	/**
	 * Nonce field name.
	 *
	 * @var string
	 */
	const NONCE_FIELD = 'bookit_booking_nonce';

	/**
	 * Generate nonce for booking forms.
	 * Uses WordPress default nonce lifetime (24 hours).
	 *
	 * @return string Nonce value.
	 */
	public static function get_nonce() {
		return wp_create_nonce( self::NONCE_ACTION );
	}

	/**
	 * Output nonce field for forms.
	 *
	 * @param bool   $referer Whether to output referer field.
	 * @param string $echo    Whether to echo (true) or return (false).
	 * @return string|void Nonce field HTML if $echo is false.
	 */
	public static function nonce_field( $referer = true, $echo = true ) {
		$field = wp_nonce_field( self::NONCE_ACTION, self::NONCE_FIELD, $referer, false );

		if ( $echo ) {
			// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Nonce field output.
			echo $field;
		} else {
			return $field;
		}
	}

	/**
	 * Verify nonce from request.
	 *
	 * @param string|null $nonce Nonce value to verify. If null, gets from POST or request header.
	 * @return bool True if valid.
	 */
	public static function verify( $nonce = null ) {
		if ( null === $nonce ) {
			$nonce = isset( $_POST[ self::NONCE_FIELD ] )
				? sanitize_text_field( wp_unslash( $_POST[ self::NONCE_FIELD ] ) )
				: '';
		}

		return false !== wp_verify_nonce( $nonce, self::NONCE_ACTION );
	}

	/**
	 * Verify nonce from REST API request.
	 * Accepts X-WP-Nonce (wp_rest) or X-Bookit-Nonce (bookit_booking).
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return bool True if valid.
	 */
	public static function verify_rest_request( $request ) {
		$nonce = $request->get_header( 'X-WP-Nonce' );
		if ( $nonce && wp_verify_nonce( $nonce, 'wp_rest' ) ) {
			return true;
		}

		$nonce = $request->get_header( 'X-Bookit-Nonce' );
		if ( $nonce && wp_verify_nonce( $nonce, self::NONCE_ACTION ) ) {
			return true;
		}

		return false;
	}

	/**
	 * Verify nonce and die with user-friendly message on failure.
	 *
	 * @param string|null $nonce Nonce value. If null, gets from POST.
	 * @return void
	 */
	public static function verify_or_die( $nonce = null ) {
		if ( self::verify( $nonce ) ) {
			return;
		}

		wp_die(
			esc_html__( 'Your session has expired or the security token is invalid. Please refresh the page and try again.', 'bookit-booking-system' ),
			esc_html__( 'Security Error', 'bookit-booking-system' ),
			array(
				'response'  => 403,
				'back_link' => true,
				'link_url'  => wp_get_referer(),
			)
		);
	}

	/**
	 * Get user-friendly error message for invalid nonce.
	 *
	 * @return string Translated error message.
	 */
	public static function get_error_message() {
		return __( 'Your session has expired. Please refresh the page and start your booking again.', 'bookit-booking-system' );
	}

	/**
	 * Return REST API error for invalid nonce.
	 *
	 * @return WP_Error Error response.
	 */
	public static function get_rest_error() {
		return new WP_Error(
			'invalid_nonce',
			self::get_error_message(),
			array( 'status' => 403 )
		);
	}
}
