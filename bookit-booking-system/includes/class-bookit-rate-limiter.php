<?php
/**
 * Rate limiter for public endpoints and forms.
 *
 * @package    Bookit_Booking_System
 * @subpackage Bookit_Booking_System/includes
 */

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
	die;
}

/**
 * Rate limiter class.
 */
class Bookit_Rate_Limiter {

	/**
	 * Transient key prefix.
	 *
	 * @var string
	 */
	const KEY_PREFIX = 'bookit_rl_';

	/**
	 * Check if request is under the configured rate limit.
	 *
	 * @param string $action         Action key (short identifier).
	 * @param string $ip             Client IP address.
	 * @param int    $limit          Maximum requests in window.
	 * @param int    $window_seconds Window size in seconds.
	 * @return bool True if allowed, false if blocked.
	 */
	public static function check( $action, $ip, $limit, $window_seconds ) {
		/*
		 * Keep action keys short. WordPress stores transient option names as
		 * "_transient_" + key in option_name (varchar 191), and transient keys are
		 * effectively limited to 172 characters.
		 */
		$key   = self::KEY_PREFIX . $action . '_' . md5( $ip );
		$count = (int) get_transient( $key );

		if ( $count >= (int) $limit ) {
			return false;
		}

		if ( 0 === $count ) {
			set_transient( $key, 1, (int) $window_seconds );
			return true;
		}

		set_transient( $key, $count + 1, (int) $window_seconds );
		return true;
	}

	/**
	 * Handle exceeded rate limit with audit log and REST response.
	 *
	 * @param string $action Action key.
	 * @param string $ip     Client IP.
	 * @return WP_REST_Response
	 */
	public static function handle_exceeded( $action, $ip ) {
		Bookit_Audit_Logger::log(
			'rate_limit_exceeded',
			'system',
			0,
			array(
				'action' => $action,
				'ip'     => $ip,
			)
		);

		return new WP_REST_Response(
			Bookit_Error_Registry::to_wp_error( 'E6001' )->get_error_data(),
			429
		);
	}

	/**
	 * Resolve client IP address.
	 *
	 * @return string
	 */
	public static function get_client_ip() {
		$remote_addr = isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : '';

		/*
		 * X-Forwarded-For can be spoofed in production unless trusted proxy headers
		 * are enforced. We still use first hop here for local/staging deployments.
		 */
		$forwarded = isset( $_SERVER['HTTP_X_FORWARDED_FOR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['HTTP_X_FORWARDED_FOR'] ) ) : '';
		if ( ! empty( $forwarded ) ) {
			$parts      = explode( ',', $forwarded );
			$forward_ip = trim( $parts[0] );
			$validated  = filter_var( $forward_ip, FILTER_VALIDATE_IP );

			if ( false !== $validated ) {
				return $validated;
			}
		}

		$validated_remote = filter_var( $remote_addr, FILTER_VALIDATE_IP );
		return false !== $validated_remote ? $validated_remote : '0.0.0.0';
	}
}
