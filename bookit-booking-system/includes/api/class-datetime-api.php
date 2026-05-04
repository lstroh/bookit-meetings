<?php
/**
 * Date/Time Selection REST API
 *
 * @package    Bookit_Booking_System
 * @subpackage Bookit_Booking_System/includes/api
 */

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
	die;
}

/**
 * DateTime API class.
 */
class Bookit_DateTime_API {

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
		$timeslots_args = array(
			'date'       => array(
				'required'          => true,
				'type'              => 'string',
				'format'            => 'YYYY-MM-DD',
				'validate_callback' => array( $this, 'validate_date_param' ),
				'sanitize_callback' => 'sanitize_text_field',
			),
			'service_id' => array(
				'required'          => false,
				'type'              => 'integer',
				'sanitize_callback' => 'absint',
			),
			'staff_id'   => array(
				'required'          => false,
				'type'              => 'integer',
				'sanitize_callback' => 'absint',
			),
		);

		register_rest_route(
			'bookit/v1',
			'/timeslots',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'get_timeslots' ),
				'permission_callback' => '__return_true',
				'args'                => $timeslots_args,
			)
		);

		register_rest_route(
			'bookit/v1',
			'/wizard/timeslots',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'get_timeslots' ),
				'permission_callback' => '__return_true',
				'args'                => $timeslots_args,
			)
		);

		register_rest_route(
			'bookit/v1',
			'/datetime/select',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'select_datetime' ),
				'permission_callback' => array( $this, 'check_permission' ),
				'args'                => array(
					'date' => array(
						'required'          => true,
						'type'              => 'string',
						'validate_callback' => array( $this, 'validate_date_param' ),
						'sanitize_callback' => 'sanitize_text_field',
					),
					'time' => array(
						'required'          => true,
						'type'              => 'string',
						'validate_callback' => array( $this, 'validate_time_param' ),
						'sanitize_callback' => 'sanitize_text_field',
					),
				),
			)
		);
	}

	/**
	 * Check permission for datetime select.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return bool True if allowed.
	 */
	public function check_permission( $request ) {
		$nonce = $request->get_header( 'X-WP-Nonce' );
		if ( ! wp_verify_nonce( $nonce, 'wp_rest' ) ) {
			return false;
		}
		return true;
	}

	/**
	 * Validate date parameter (Y-m-d).
	 *
	 * @param string          $value   Date string.
	 * @param WP_REST_Request $request Request object.
	 * @param string          $param   Parameter name.
	 * @return bool True if valid.
	 */
	public function validate_date_param( $value, $request, $param ) {
		if ( ! is_string( $value ) || empty( $value ) ) {
			return false;
		}
		$d = DateTime::createFromFormat( 'Y-m-d', $value );
		return $d && $d->format( 'Y-m-d' ) === $value;
	}

	/**
	 * Validate time parameter (H:i or H:i:s).
	 *
	 * @param string          $value   Time string.
	 * @param WP_REST_Request $request Request object.
	 * @param string          $param   Parameter name.
	 * @return bool True if valid.
	 */
	public function validate_time_param( $value, $request, $param ) {
		if ( ! is_string( $value ) || empty( $value ) ) {
			return false;
		}
		// Accept H:i:s or H:i.
		if ( preg_match( '/^\d{1,2}:\d{2}(:\d{2})?$/', $value ) !== 1 ) {
			return false;
		}
		$parts = explode( ':', $value );
		$h     = (int) $parts[0];
		$m     = (int) $parts[1];
		return $h >= 0 && $h <= 23 && $m >= 0 && $m <= 59;
	}

	/**
	 * GET timeslots endpoint.
	 * Returns real available slots (filtered by working hours, bookings, duration + buffers).
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response|WP_Error Response or error.
	 */
	public function get_timeslots( $request ) {
		$date = $request->get_param( 'date' );

		require_once BOOKIT_PLUGIN_DIR . 'includes/models/class-datetime-model.php';
		$model = new Bookit_DateTime_Model();

		if ( $model->is_past_date( $date ) ) {
			return new WP_Error(
				'past_date',
				__( 'Cannot select a date in the past', 'bookit-booking-system' ),
				array( 'status' => 400 )
			);
		}

		if ( $model->is_bank_holiday( $date ) ) {
			return new WP_Error(
				'bank_holiday',
				__( 'This date is a bank holiday and is unavailable', 'bookit-booking-system' ),
				array( 'status' => 400 )
			);
		}

		$service_id = (int) $request->get_param( 'service_id' );
		$staff_id   = (int) $request->get_param( 'staff_id' );

		if ( $service_id <= 0 || $staff_id <= 0 ) {
			Bookit_Session_Manager::init();
			$wizard_data = Bookit_Session_Manager::get_data();
			if ( $service_id <= 0 ) {
				$service_id = isset( $wizard_data['service_id'] ) ? absint( $wizard_data['service_id'] ) : 0;
			}
			if ( $staff_id <= 0 ) {
				$staff_id = isset( $wizard_data['staff_id'] ) ? absint( $wizard_data['staff_id'] ) : 0;
			}
		}

		if ( ! $service_id ) {
			return new WP_Error(
				'no_service',
				__( 'Please select a service first', 'bookit-booking-system' ),
				array( 'status' => 400 )
			);
		}

		$available_slots = $model->get_available_slots( $date, $service_id, $staff_id );

		if ( empty( $available_slots ) ) {
			return rest_ensure_response(
				array(
					'success'   => true,
					'available' => false,
					'message'   => __( 'No time slots available for this date', 'bookit-booking-system' ),
					'slots'     => array(
						'morning'   => array(),
						'afternoon' => array(),
						'evening'   => array(),
					),
				)
			);
		}

		$slots = $model->group_time_slots( $available_slots );

		return rest_ensure_response(
			array(
				'success'      => true,
				'available'    => true,
				'slots'        => $slots,
				'total_slots'  => count( $available_slots ),
			)
		);
	}

	/**
	 * POST select datetime endpoint.
	 * Saves to session, advances to step 4.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response|WP_Error Response or error.
	 */
	public function select_datetime( $request ) {
		$date = $request->get_param( 'date' );
		$time = $request->get_param( 'time' );

		// Normalize time to H:i:s.
		$time_parts = explode( ':', $time );
		if ( count( $time_parts ) === 2 ) {
			$time .= ':00';
		}

		require_once BOOKIT_PLUGIN_DIR . 'includes/models/class-datetime-model.php';
		$model = new Bookit_DateTime_Model();

		if ( $model->is_past_date( $date ) ) {
			return new WP_Error(
				'past_date',
				__( 'Cannot select a date in the past', 'bookit-booking-system' ),
				array( 'status' => 400 )
			);
		}

		if ( $model->is_bank_holiday( $date ) ) {
			return new WP_Error(
				'bank_holiday',
				__( 'This date is a bank holiday and is unavailable', 'bookit-booking-system' ),
				array( 'status' => 400 )
			);
		}

		Bookit_Session_Manager::init();

		if ( Bookit_Session_Manager::is_expired() ) {
			Bookit_Session_Manager::clear();
		}

		$wizard_data = Bookit_Session_Manager::get_data();

		if ( empty( $wizard_data['service_id'] ) ) {
			return new WP_Error(
				'no_service',
				__( 'Please select a service first', 'bookit-booking-system' ),
				array( 'status' => 400 )
			);
		}

		$wizard_data['date']         = $date;
		$wizard_data['time']         = $time;
		$wizard_data['current_step'] = 4;
		Bookit_Session_Manager::set_data( $wizard_data );
		Bookit_Session_Manager::regenerate();

		return rest_ensure_response(
			array(
				'success'   => true,
				'message'   => __( 'Date and time saved', 'bookit-booking-system' ),
				'next_step' => 4,
			)
		);
	}
}

new Bookit_DateTime_API();
