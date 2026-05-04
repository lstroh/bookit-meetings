<?php
/**
 * Service Selection API
 *
 * @package    Bookit_Booking_System
 * @subpackage Bookit_Booking_System/includes/api
 */

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
	die;
}

/**
 * Service API class.
 */
class Bookit_Service_API {

	/**
	 * Initialize API.
	 */
	public function __construct() {
		require_once BOOKIT_PLUGIN_DIR . 'includes/core/class-session-manager.php';
		add_action( 'rest_api_init', array( $this, 'register_routes' ) );
	}

	/**
	 * Register REST API routes.
	 *
	 * @return void
	 */
	public function register_routes() {
		register_rest_route(
			'bookit/v1',
			'/service/select',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'select_service' ),
				'permission_callback' => '__return_true',
				'args'                => array(
					'service_id' => array(
						'required'          => true,
						'type'              => 'integer',
						'validate_callback' => function( $param ) {
							return is_numeric( $param ) && $param > 0;
						},
						'sanitize_callback' => 'absint',
					),
				),
			)
		);
	}

	/**
	 * Handle service selection.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response|WP_Error Response object or error.
	 */
	public function select_service( $request ) {
		// Verify nonce.
		$nonce = $request->get_header( 'X-WP-Nonce' );
		if ( ! wp_verify_nonce( $nonce, 'wp_rest' ) ) {
			return new WP_Error(
				'invalid_nonce',
				__( 'Invalid security token', 'bookit-booking-system' ),
				array( 'status' => 403 )
			);
		}

		$service_id = absint( $request->get_param( 'service_id' ) );

		// Verify service exists and is active.
		require_once BOOKIT_PLUGIN_DIR . 'includes/models/class-service-model.php';
		$service_model = new Bookit_Service_Model();
		$service = $service_model->get_service_by_id( $service_id );

		if ( ! $service || $service['status'] !== 'active' ) {
			return new WP_Error(
				'invalid_service',
				__( 'Service not found or inactive', 'bookit-booking-system' ),
				array( 'status' => 404 )
			);
		}

		// Initialize session.
		Bookit_Session_Manager::init();

		// Check if session expired.
		if ( Bookit_Session_Manager::is_expired() ) {
			Bookit_Session_Manager::clear();
		}

		// Get current wizard data.
		$wizard_data = Bookit_Session_Manager::get_data();

		// Update wizard data with selected service.
		$wizard_data['service_id']     = $service_id;
		$wizard_data['service_name']   = $service['name'];
		$wizard_data['service_duration'] = $service['duration'];
		$wizard_data['service_price']  = $service['base_price'];
		$wizard_data['current_step']   = 2; // Progress to step 2.

		// Save to session.
		Bookit_Session_Manager::set_data( $wizard_data );

		// Regenerate session ID for security.
		Bookit_Session_Manager::regenerate();

		return rest_ensure_response(
			array(
				'success'   => true,
				'service'   => array(
					'id'       => $service_id,
					'name'     => $service['name'],
					'duration' => $service['duration'],
					'price'    => $service['base_price'],
				),
				'next_step' => 2,
			)
		);
	}
}

// Initialize.
new Bookit_Service_API();
