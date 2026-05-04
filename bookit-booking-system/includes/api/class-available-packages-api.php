<?php
/**
 * Available Packages API
 *
 * Public endpoint for booking wizard package selection.
 *
 * @package    Bookit_Booking_System
 * @subpackage Bookit_Booking_System/includes/api
 */

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
	die;
}

/**
 * Available packages API class.
 */
class Bookit_Available_Packages_API {

	/**
	 * Register hooks.
	 */
	public function __construct() {
		add_action( 'rest_api_init', array( $this, 'register_routes' ) );
	}

	/**
	 * Register REST routes.
	 *
	 * @return void
	 */
	public function register_routes() {
		register_rest_route(
			'bookit/v1',
			'/wizard/available-packages',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'get_available_packages' ),
				'permission_callback' => '__return_true',
				'args'                => array(
					'service_id' => array(
						'required'          => true,
						'type'              => 'integer',
						'sanitize_callback' => 'absint',
						'validate_callback' => function( $param ) {
							return is_numeric( $param ) && (int) $param >= 1;
						},
					),
				),
			)
		);
	}

	/**
	 * Get available packages for a service.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response|WP_Error
	 */
	public function get_available_packages( $request ) {
		global $wpdb;

		$ip = Bookit_Rate_Limiter::get_client_ip();
		if ( ! Bookit_Rate_Limiter::check( 'wizard_pkgs', $ip, 60, HOUR_IN_SECONDS ) ) {
			return Bookit_Rate_Limiter::handle_exceeded( 'wizard_pkgs', $ip );
		}

		$packages_enabled = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT setting_value FROM {$wpdb->prefix}bookings_settings WHERE setting_key = %s LIMIT 1",
				'packages_enabled'
			)
		);
		if ( '1' !== (string) $packages_enabled ) {
			return new WP_REST_Response( array(), 200 );
		}

		$service_id = absint( $request->get_param( 'service_id' ) );
		$table      = $wpdb->prefix . 'bookings_package_types';

		$rows = $wpdb->get_results(
			"SELECT id, name, sessions_count, price_mode, fixed_price, expiry_enabled, expiry_days, applicable_service_ids
			FROM {$table}
			WHERE is_active = 1",
			ARRAY_A
		);

		if ( null === $rows ) {
			return Bookit_Error_Registry::to_wp_error( 'E9001', array( 'db_error' => $wpdb->last_error ) );
		}

		$items = array();

		foreach ( $rows as $row ) {
			$service_ids = null;

			if ( isset( $row['applicable_service_ids'] ) && null !== $row['applicable_service_ids'] && '' !== $row['applicable_service_ids'] ) {
				$decoded = json_decode( (string) $row['applicable_service_ids'], true );
				if ( is_array( $decoded ) ) {
					$service_ids = array_values( array_map( 'absint', $decoded ) );
				}
			}

			if ( null !== $service_ids && ! in_array( $service_id, $service_ids, true ) ) {
				continue;
			}

			$items[] = $this->format_available_package_row( $row );
		}

		return new WP_REST_Response( $items, 200 );
	}

	/**
	 * Format DB row for wizard response.
	 *
	 * @param array $row Raw DB row.
	 * @return array
	 */
	private function format_available_package_row( $row ) {
		return array(
			'id'             => (int) $row['id'],
			'name'           => (string) $row['name'],
			'sessions_count' => (int) $row['sessions_count'],
			'price_mode'     => (string) $row['price_mode'],
			'fixed_price'    => null === $row['fixed_price'] ? null : number_format( (float) $row['fixed_price'], 2, '.', '' ),
			'expiry_enabled' => (bool) (int) $row['expiry_enabled'],
			'expiry_days'    => null === $row['expiry_days'] ? null : (int) $row['expiry_days'],
		);
	}
}
