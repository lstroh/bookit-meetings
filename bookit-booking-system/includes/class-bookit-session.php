<?php
/**
 * Session management for dashboard authentication.
 *
 * @package    Bookit_Booking_System
 * @subpackage Bookit_Booking_System/includes
 */

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
	die;
}

/**
 * Session management class.
 */
class Bookit_Session {

	/**
	 * Initialize session with security settings.
	 *
	 * @return void
	 */
	public static function init() {
		// Only start session if not already started.
		if ( session_status() === PHP_SESSION_NONE ) {
			// Check if headers have already been sent (e.g., in test environment).
			if ( headers_sent() ) {
				// In test environment, just ensure $_SESSION is available.
				if ( ! isset( $_SESSION ) || ! is_array( $_SESSION ) ) {
					$_SESSION = array();
				}
				return;
			}

			// Session security configuration.
			// Use @ to suppress warnings in test environment where headers may already be sent.
			@ini_set( 'session.cookie_httponly', '1' ); // Prevent JavaScript access.
			@ini_set( 'session.cookie_samesite', 'Lax' ); // CSRF protection.
			@ini_set( 'session.gc_maxlifetime', '28800' ); // 8 hours.
			@ini_set( 'session.use_only_cookies', '1' ); // No session ID in URL.

			// HTTPS only in production (not localhost).
			if ( ! self::is_localhost() ) {
				@ini_set( 'session.cookie_secure', '1' );
			}

			session_name( 'bookit_dashboard_session' );
			session_start();

			Bookit_Logger::info(
				'Session started',
				array(
					'session_id' => session_id(),
				)
			);
		}
	}

	/**
	 * Check if running on localhost.
	 *
	 * @return bool True if localhost.
	 */
	private static function is_localhost() {
		$remote_addr = isset( $_SERVER['REMOTE_ADDR'] ) ? wp_unslash( $_SERVER['REMOTE_ADDR'] ) : '';
		$whitelist   = array( '127.0.0.1', '::1', 'localhost' );

		return in_array( $remote_addr, $whitelist, true );
	}

	/**
	 * Set session variable.
	 *
	 * @param string $key   Session key.
	 * @param mixed  $value Session value.
	 * @return void
	 */
	public static function set( $key, $value ) {
		self::init();
		$_SESSION[ $key ] = $value;
	}

	/**
	 * Get session variable.
	 *
	 * @param string $key     Session key.
	 * @param mixed  $default Default value if not set.
	 * @return mixed Session value or default.
	 */
	public static function get( $key, $default = null ) {
		self::init();
		return isset( $_SESSION[ $key ] ) ? $_SESSION[ $key ] : $default;
	}

	/**
	 * Check if session variable exists.
	 *
	 * @param string $key Session key.
	 * @return bool True if exists.
	 */
	public static function has( $key ) {
		self::init();
		return isset( $_SESSION[ $key ] );
	}

	/**
	 * Delete session variable.
	 *
	 * @param string $key Session key.
	 * @return void
	 */
	public static function delete( $key ) {
		self::init();
		if ( isset( $_SESSION[ $key ] ) ) {
			unset( $_SESSION[ $key ] );
		}
	}

	/**
	 * Destroy entire session.
	 *
	 * @return void
	 */
	public static function destroy() {
		self::init();
		$_SESSION = array();

		// Only perform session operations if session is active.
		if ( session_status() === PHP_SESSION_ACTIVE ) {
			// Delete session cookie.
			if ( ini_get( 'session.use_cookies' ) && ! headers_sent() ) {
				$params = session_get_cookie_params();
				setcookie(
					session_name(),
					'',
					time() - 42000,
					$params['path'],
					$params['domain'],
					$params['secure'],
					$params['httponly']
				);
			}

			session_destroy();
		}

		Bookit_Logger::info( 'Session destroyed' );
	}

	/**
	 * Regenerate session ID (prevent session fixation).
	 *
	 * @return void
	 */
	public static function regenerate() {
		self::init();
		
		// Only regenerate if session is active.
		if ( session_status() === PHP_SESSION_ACTIVE ) {
			session_regenerate_id( true );
			Bookit_Logger::info( 'Session ID regenerated' );
		}
	}

	/**
	 * Check if session is expired.
	 *
	 * @return bool True if expired.
	 */
	public static function is_expired() {
		$last_activity = (int) self::get( 'last_activity', 0 );
		$timeout       = 28800; // 8 hours in seconds.

		if ( $last_activity > 0 && ( time() - $last_activity > $timeout ) ) {
			return true;
		}

		return false;
	}

	/**
	 * Update last activity timestamp.
	 *
	 * @return void
	 */
	public static function update_activity() {
		self::set( 'last_activity', time() );
	}
}
