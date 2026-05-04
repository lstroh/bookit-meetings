<?php
/**
 * Central error registry for API responses.
 *
 * @package    Bookit_Booking_System
 * @subpackage Bookit_Booking_System/includes
 */

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
	die;
}

/**
 * Error registry class.
 */
class Bookit_Error_Registry {

	/**
	 * Static registry of error definitions.
	 * Keyed by error code string.
	 *
	 * @var array<string, array>
	 */
	private static array $errors = array();

	/**
	 * Register a custom error code.
	 * Extensions must prefix their codes with their slug.
	 *
	 * @param string $code       Unique error code (e.g. 'E1001').
	 * @param array  $definition Error definition.
	 * @return void
	 */
	public static function register( string $code, array $definition ): void {
		// Do not allow overwriting existing codes.
		if ( isset( self::$errors[ $code ] ) ) {
			return;
		}

		self::$errors[ $code ] = $definition;
	}

	/**
	 * Get an error definition by code.
	 * Returns a default system error if code not found.
	 *
	 * @param string $code Error code.
	 * @return array
	 */
	public static function get( string $code ): array {
		return self::$errors[ $code ] ?? self::$errors['E9999'];
	}

	/**
	 * Create a WP_Error from a registry code.
	 *
	 * @param string $code    Error code.
	 * @param array  $context Placeholder values for substitution.
	 * @return WP_Error
	 */
	public static function to_wp_error( string $code, array $context = array() ): WP_Error {
		$definition = self::get( $code );
		$message    = $definition['user_message'];

		foreach ( $context as $key => $value ) {
			$message = str_replace( '{' . $key . '}', (string) $value, $message );
		}

		return new WP_Error(
			$code,
			$message,
			array( 'status' => $definition['http_status'] )
		);
	}

	/**
	 * Return all registered error definitions.
	 *
	 * @return array
	 */
	public static function all(): array {
		return self::$errors;
	}

	/**
	 * Register package-specific errors (E500x series).
	 *
	 * @return void
	 */
	public static function register_package_errors(): void {
		self::register(
			BOOKIT_E5001,
			array(
				'user_message' => __( 'Package not found.', 'bookit-booking-system' ),
				'log_message'  => 'Package ID {package_id} not found',
				'http_status'  => 404,
				'category'     => 'packages',
			)
		);

		self::register(
			BOOKIT_E5002,
			array(
				'user_message' => __( 'This package has no sessions remaining.', 'bookit-booking-system' ),
				'log_message'  => 'Package exhausted for customer package ID {customer_package_id}',
				'http_status'  => 422,
				'category'     => 'packages',
			)
		);

		self::register(
			BOOKIT_E5003,
			array(
				'user_message' => __( 'This package has expired.', 'bookit-booking-system' ),
				'log_message'  => 'Package expired for customer package ID {customer_package_id}',
				'http_status'  => 422,
				'category'     => 'packages',
			)
		);

		self::register(
			BOOKIT_E5004,
			array(
				'user_message' => __( 'This package cannot be used for the selected service.', 'bookit-booking-system' ),
				'log_message'  => 'Package/service mismatch. Package {customer_package_id}, service {service_id}',
				'http_status'  => 422,
				'category'     => 'packages',
			)
		);

		self::register(
			BOOKIT_E5005,
			array(
				'user_message' => __( 'Insufficient package sessions to complete this booking.', 'bookit-booking-system' ),
				'log_message'  => 'Insufficient package sessions. Package {customer_package_id}, required {required_sessions}',
				'http_status'  => 422,
				'category'     => 'packages',
			)
		);
	}
}
