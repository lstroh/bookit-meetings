<?php
/**
 * Dashboard Timeslots REST API
 *
 * Provides available time slots for manual booking creation.
 * Separate from public API to accept explicit service_id and staff_id parameters
 * instead of reading from session.
 *
 * @package    Bookit_Booking_System
 * @subpackage Bookit_Booking_System/includes/api
 */

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
	die;
}

/**
 * Class Bookit_Dashboard_Timeslots_API
 */
class Bookit_Dashboard_Timeslots_API {

	/**
	 * REST API namespace.
	 */
	const NAMESPACE = 'bookit/v1';

	/**
	 * Constructor - Register REST routes.
	 */
	public function __construct() {
		add_action( 'rest_api_init', array( $this, 'register_routes' ) );
	}

	/**
	 * Register REST API routes.
	 *
	 * @return void
	 */
	public function register_routes() {
		register_rest_route(
			self::NAMESPACE,
			'/dashboard/timeslots',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'get_timeslots' ),
				'permission_callback' => array( $this, 'check_dashboard_permission' ),
				'args'                => array(
					'date'       => array(
						'required'          => true,
						'type'              => 'string',
						'validate_callback' => function ( $param ) {
							if ( ! is_string( $param ) || empty( $param ) ) {
								return false;
							}
							$d = DateTime::createFromFormat( 'Y-m-d', $param );
							return $d && $d->format( 'Y-m-d' ) === $param;
						},
						'sanitize_callback' => 'sanitize_text_field',
					),
					'service_id' => array(
						'required'          => true,
						'validate_callback' => function ( $param ) {
							return is_numeric( $param ) && (int) $param > 0;
						},
					),
					'staff_id'   => array(
						'required'          => true,
						'validate_callback' => function ( $param ) {
							return is_numeric( $param ) && (int) $param >= 0;
						},
					),
				),
			)
		);
	}

	/**
	 * Check if user has dashboard permission.
	 *
	 * @return bool|WP_Error
	 */
	public function check_dashboard_permission() {
		// Load auth classes if not loaded.
		if ( ! class_exists( 'Bookit_Session' ) ) {
			require_once plugin_dir_path( dirname( __FILE__ ) ) . 'class-bookit-session.php';
		}
		if ( ! class_exists( 'Bookit_Auth' ) ) {
			require_once plugin_dir_path( dirname( __FILE__ ) ) . 'class-bookit-auth.php';
		}

		// Check if logged in.
		if ( ! Bookit_Auth::is_logged_in() ) {
			return new WP_Error(
				'unauthorized',
				__( 'You must be logged in to access the dashboard.', 'bookit-booking-system' ),
				array( 'status' => 401 )
			);
		}

		return true;
	}

	/**
	 * Get available timeslots for a date, service, and staff member.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response|WP_Error
	 */
	public function get_timeslots( $request ) {
		$date       = $request->get_param( 'date' );
		$service_id = (int) $request->get_param( 'service_id' );
		$staff_id   = (int) $request->get_param( 'staff_id' );

		// Load datetime model if not loaded.
		if ( ! class_exists( 'Bookit_DateTime_Model' ) ) {
			require_once plugin_dir_path( dirname( __FILE__ ) ) . 'models/class-datetime-model.php';
		}

		$model = new Bookit_DateTime_Model();

		// Check for past dates.
		if ( $model->is_past_date( $date ) ) {
			return new WP_Error(
				'past_date',
				__( 'Cannot select a date in the past.', 'bookit-booking-system' ),
				array( 'status' => 400 )
			);
		}

		// Check for bank holidays.
		if ( $model->is_bank_holiday( $date ) ) {
			return rest_ensure_response(
				array(
					'success'   => true,
					'available' => false,
					'message'   => __( 'This date is a bank holiday and is unavailable.', 'bookit-booking-system' ),
					'slots'     => array(
						'morning'   => array(),
						'afternoon' => array(),
						'evening'   => array(),
					),
				)
			);
		}

		// Get available slots using existing model logic.
		$available_slots = $model->get_available_slots( $date, $service_id, $staff_id );

		if ( empty( $available_slots ) ) {
			return rest_ensure_response(
				array(
					'success'   => true,
					'available' => false,
					'message'   => __( 'No time slots available for this date.', 'bookit-booking-system' ),
					'slots'     => array(
						'morning'   => array(),
						'afternoon' => array(),
						'evening'   => array(),
					),
				)
			);
		}

		// Group slots by period (morning/afternoon/evening).
		$slots = $model->group_time_slots( $available_slots );

		return rest_ensure_response(
			array(
				'success'     => true,
				'available'   => true,
				'slots'       => $slots,
				'total_slots' => count( $available_slots ),
			)
		);
	}
}

new Bookit_Dashboard_Timeslots_API();
