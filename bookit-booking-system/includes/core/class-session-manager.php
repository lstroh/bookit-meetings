<?php
/**
 * Session manager for booking wizard.
 *
 * @package    Bookit_Booking_System
 * @subpackage Bookit_Booking_System/includes/core
 */

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
	die;
}

/**
 * Session manager class for booking wizard.
 */
class Bookit_Session_Manager {
	// Performance audit: wizard state is kept in PHP session memory, so no per-step settings DB re-queries in this class.

	/**
	 * Session key for wizard data.
	 *
	 * @var string
	 */
	const SESSION_KEY = 'bookit_wizard';

	/**
	 * Session inactivity timeout in seconds (30 minutes).
	 *
	 * @var int
	 */
	const SESSION_TIMEOUT = 1800;

	/**
	 * Initialize session with security settings.
	 * Must be called before any session operations.
	 * Session configuration happens BEFORE session_start().
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

			// Session security configuration - MUST happen before session_start().
			@ini_set( 'session.cookie_httponly', '1' );  // HttpOnly: prevents JavaScript access.
			@ini_set( 'session.cookie_samesite', 'Lax' ); // SameSite: CSRF protection.
			@ini_set( 'session.gc_maxlifetime', (string) self::SESSION_TIMEOUT );
			@ini_set( 'session.use_only_cookies', '1' );  // No session ID in URL.
			@ini_set( 'session.cookie_secure', '1' );     // HTTPS-only cookies (when not localhost).

			// Allow HTTP on localhost for development.
			if ( self::is_localhost() ) {
				@ini_set( 'session.cookie_secure', '0' );
			}

			session_name( 'bookit_wizard_session' );
			session_start();

			// Initialize wizard data if not exists.
			if ( ! isset( $_SESSION[ self::SESSION_KEY ] ) ) {
				$_SESSION[ self::SESSION_KEY ] = self::get_default_data();
				// Session fixation prevention: regenerate ID on first visit.
				if ( session_status() === PHP_SESSION_ACTIVE ) {
					session_regenerate_id( true );
				}
			}

			// Update last activity timestamp.
			self::update_activity();
		}

		// Check inactivity timeout on every request (even if session already started).
		if ( session_status() === PHP_SESSION_ACTIVE && isset( $_SESSION[ self::SESSION_KEY ] ) ) {
			$last = (int) ( $_SESSION[ self::SESSION_KEY ]['last_activity'] ?? 0 );
			if ( $last > 0 && ( time() - $last ) > self::SESSION_TIMEOUT ) {
				self::clear();
				self::update_activity();
			}
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
	 * Get default wizard data structure.
	 *
	 * @return array Default wizard data.
	 */
	private static function get_default_data() {
		return array(
			'current_step' => 1,
			'service_id'   => null,
			'staff_id'     => null,
			'date'         => null,
			'time'         => null,
			'customer'     => array(),
			'created_at'   => time(),
			'last_activity' => time(),
		);
	}

	/**
	 * Get wizard data.
	 *
	 * @return array Wizard data.
	 */
	public static function get_data() {
		self::init();
		return isset( $_SESSION[ self::SESSION_KEY ] ) ? $_SESSION[ self::SESSION_KEY ] : self::get_default_data();
	}

	/**
	 * Get specific wizard field value.
	 *
	 * @param string $field Field name.
	 * @param mixed  $default Default value if not set.
	 * @return mixed Field value or default.
	 */
	public static function get( $field, $default = null ) {
		$data = self::get_data();
		return isset( $data[ $field ] ) ? $data[ $field ] : $default;
	}

	/**
	 * Set wizard data.
	 *
	 * @param array $data Wizard data to set.
	 * @return void
	 */
	public static function set_data( $data ) {
		self::init();
		$current_data = self::get_data();
		$_SESSION[ self::SESSION_KEY ] = array_merge( $current_data, $data );
		self::update_activity();
	}

	/**
	 * Set specific wizard field value.
	 *
	 * @param string $field Field name.
	 * @param mixed  $value Field value.
	 * @return void
	 */
	public static function set( $field, $value ) {
		self::init();
		if ( ! isset( $_SESSION[ self::SESSION_KEY ] ) ) {
			$_SESSION[ self::SESSION_KEY ] = self::get_default_data();
		}
		$_SESSION[ self::SESSION_KEY ][ $field ] = $value;
		self::update_activity();
	}

	/**
	 * Clear wizard data.
	 *
	 * @return void
	 */
	public static function clear() {
		self::init();
		$_SESSION[ self::SESSION_KEY ] = self::get_default_data();
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
		}
	}

	/**
	 * Check if session is expired (30-minute inactivity timeout).
	 *
	 * @return bool True if expired.
	 */
	public static function is_expired() {
		$last_activity = (int) self::get( 'last_activity', 0 );

		if ( $last_activity > 0 && ( time() - $last_activity > self::SESSION_TIMEOUT ) ) {
			return true;
		}

		return false;
	}

	/**
	 * Update last activity timestamp.
	 * Must only be called after init() has run (no init() here to avoid recursion).
	 *
	 * @return void
	 */
	private static function update_activity() {
		if ( session_status() !== PHP_SESSION_ACTIVE || ! isset( $_SESSION[ self::SESSION_KEY ] ) ) {
			return;
		}
		$_SESSION[ self::SESSION_KEY ]['last_activity'] = time();
	}

	/**
	 * Get time remaining until session expires (in seconds).
	 *
	 * @return int Seconds until expiry, or 0 if expired.
	 */
	public static function get_time_remaining() {
		$last_activity = (int) self::get( 'last_activity', 0 );
		$elapsed       = time() - $last_activity;
		$remaining     = self::SESSION_TIMEOUT - $elapsed;

		return max( 0, $remaining );
	}

	/**
	 * Clear session on booking completion.
	 * Call this after a booking has been successfully completed.
	 *
	 * @return void
	 */
	public static function complete_booking() {
		self::init();
		self::clear();
		$_SESSION[ self::SESSION_KEY ] = self::get_default_data();
		self::update_activity();
	}
}
