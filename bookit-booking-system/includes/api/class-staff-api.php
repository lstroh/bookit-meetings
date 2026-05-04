<?php
/**
 * Staff Selection REST API
 *
 * @package    Bookit_Booking_System
 * @subpackage Bookit_Booking_System/includes/api
 */

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
	die;
}

/**
 * Staff API class.
 */
class Bookit_Staff_API {

	/**
	 * Constructor.
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
			'/staff/select',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'select_staff' ),
				'permission_callback' => '__return_true',
				'args'                => array(
					'staff_id' => array(
						'required'          => true,
						'validate_callback' => function( $param ) {
							return is_numeric( $param ) && $param >= 0;
						},
						'sanitize_callback' => 'absint',
					),
				),
			)
		);
	}

	/**
	 * Handle staff selection.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response|WP_Error Response object or error.
	 */
	public function select_staff( $request ) {
		$staff_id = absint( $request->get_param( 'staff_id' ) );
		Bookit_Logger::info(
			'Staff select API: request received',
			array( 'staff_id' => $staff_id )
		);

		// Verify nonce.
		$nonce = $request->get_header( 'X-WP-Nonce' );
		if ( ! wp_verify_nonce( $nonce, 'wp_rest' ) ) {
			Bookit_Logger::warning(
				'Staff select API: invalid nonce',
				array( 'staff_id' => $staff_id )
			);
			return new WP_Error(
				'invalid_nonce',
				__( 'Invalid security token', 'bookit-booking-system' ),
				array( 'status' => 403 )
			);
		}
		Bookit_Logger::info( 'Staff select API: nonce verified' );

		// Initialize session.
		Bookit_Session_Manager::init();
		Bookit_Logger::info( 'Staff select API: session initialized' );

		// Check if session expired.
		if ( Bookit_Session_Manager::is_expired() ) {
			Bookit_Logger::info( 'Staff select API: session expired, clearing' );
			Bookit_Session_Manager::clear();
		}

		// Get current wizard data.
		$wizard_data = Bookit_Session_Manager::get_data();
		Bookit_Logger::info(
			'Staff select API: wizard data loaded',
			array(
				'current_step' => isset( $wizard_data['current_step'] ) ? $wizard_data['current_step'] : null,
				'service_id'   => isset( $wizard_data['service_id'] ) ? $wizard_data['service_id'] : null,
			)
		);

		if ( empty( $wizard_data['service_id'] ) ) {
			Bookit_Logger::warning(
				'Staff select API: no service in session',
				array( 'staff_id' => $staff_id )
			);
			return new WP_Error(
				'no_service',
				__( 'Please select a service first', 'bookit-booking-system' ),
				array( 'status' => 400 )
			);
		}

		$service_id = absint( $wizard_data['service_id'] );
		require_once BOOKIT_PLUGIN_DIR . 'includes/models/class-staff-model.php';
		$staff_model = new Bookit_Staff_Model();
		Bookit_Logger::info(
			'Staff select API: staff model loaded',
			array( 'service_id' => $service_id )
		);

		// Handle "No Preference".
		if ( $staff_id === 0 ) {
			Bookit_Logger::info( 'Staff select API: handling No Preference' );
			$lowest_price = $staff_model->get_lowest_staff_price_for_service( $service_id );
			Bookit_Logger::info(
				'Staff select API: No Preference lowest price',
				array( 'lowest_price' => $lowest_price )
			);

			$wizard_data['staff_id']      = 0;
			$wizard_data['staff_name']   = 'No Preference';
			$wizard_data['staff_price']   = $lowest_price;
			$wizard_data['current_step'] = 3;
			Bookit_Session_Manager::set_data( $wizard_data );
			Bookit_Logger::info(
				'Staff select API: No Preference saved to session, advancing to step 3'
			);

			return rest_ensure_response(
				array(
					'success'   => true,
					'staff'     => array(
						'id'    => 0,
						'name'  => 'No Preference',
						'price' => $lowest_price,
					),
					'next_step' => 3,
				)
			);
		}

		// Verify staff exists and offers service.
		Bookit_Logger::info(
			'Staff select API: validating staff',
			array( 'staff_id' => $staff_id )
		);
		$staff = $staff_model->get_staff_by_id( $staff_id );
		if ( ! $staff ) {
			Bookit_Logger::warning(
				'Staff select API: staff not found',
				array( 'staff_id' => $staff_id )
			);
			return new WP_Error(
				'invalid_staff',
				__( 'Staff not found', 'bookit-booking-system' ),
				array( 'status' => 404 )
			);
		}

		$staff_for_service = $staff_model->get_staff_for_service( $service_id );
		$staff_ids         = array_column( $staff_for_service, 'id' );
		// Convert staff_ids to integers for comparison
		$staff_ids = array_map('intval', $staff_ids);
		Bookit_Logger::info(
			'Staff select API: staff offers service check',
			array(
				'staff_id'         => $staff_id,
				'service_staff_ids' => $staff_ids,
			)
		);

		if ( ! in_array( $staff_id, $staff_ids, true ) ) {
			Bookit_Logger::warning(
				'Staff select API: staff does not offer this service',
				array(
					'staff_id'   => $staff_id,
					'service_id' => $service_id,
				)
			);
			return new WP_Error(
				'staff_not_available',
				__( 'Staff does not offer this service', 'bookit-booking-system' ),
				array( 'status' => 400 )
			);
		}

		// Get staff price.
		$staff_data = array_filter(
			$staff_for_service,
			function( $s ) use ( $staff_id ) {
				return (int) $s['id'] === $staff_id;
			}
		);
		$staff_data = reset( $staff_data );
		Bookit_Logger::info(
			'Staff select API: staff price resolved',
			array(
				'staff_id' => $staff_id,
				'price'    => isset( $staff_data['price'] ) ? $staff_data['price'] : null,
			)
		);

		// Save to session.
		$wizard_data['staff_id']      = $staff_id;
		$wizard_data['staff_name']   = $staff['full_name'];
		$wizard_data['staff_price']  = $staff_data['price'];
		$wizard_data['current_step'] = 3;
		Bookit_Session_Manager::set_data( $wizard_data );
		Bookit_Logger::info(
			'Staff select API: staff saved to session',
			array(
				'staff_id'     => $staff_id,
				'staff_name'   => $staff['full_name'],
				'staff_price'  => $staff_data['price'],
				'current_step' => 3,
			)
		);

		// Regenerate session ID for security.
		Bookit_Session_Manager::regenerate();
		Bookit_Logger::info( 'Staff select API: session regenerated' );

		Bookit_Logger::info(
			'Staff select API: success, returning response',
			array(
				'staff_id'  => $staff_id,
				'next_step' => 3,
			)
		);
		return rest_ensure_response(
			array(
				'success'   => true,
				'staff'     => array(
					'id'    => $staff_id,
					'name'  => $staff['full_name'],
					'price' => $staff_data['price'],
				),
				'next_step' => 3,
			)
		);
	}
}

// Initialize.
new Bookit_Staff_API();
