<?php
/**
 * REST: Google Calendar OAuth (per staff).
 *
 * @package    Bookit_Booking_System
 * @subpackage Bookit_Booking_System/includes/api
 */

if ( ! defined( 'WPINC' ) ) {
	die;
}

/**
 * Registers bookit/v1/google-calendar/* and disconnect under dashboard profile.
 */
class Bookit_Google_Calendar_Rest_Controller {

	/**
	 * REST namespace.
	 */
	const NAMESPACE = 'bookit/v1';

	/**
	 * Bootstrap routes.
	 *
	 * @return void
	 */
	public static function init(): void {
		new self();
	}

	/**
	 * Constructor.
	 */
	public function __construct() {
		add_action( 'rest_api_init', array( $this, 'register_routes' ) );
	}

	/**
	 * Register routes.
	 *
	 * @return void
	 */
	public function register_routes(): void {
		register_rest_route(
			self::NAMESPACE,
			'/google-calendar/auth-url',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'get_auth_url' ),
				'permission_callback' => array( $this, 'is_authenticated' ),
			)
		);

		register_rest_route(
			self::NAMESPACE,
			'/google-calendar/callback',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'oauth_callback' ),
				'permission_callback' => '__return_true',
				'args'                => array(
					'code' => array(
						'required'          => false,
						'type'              => 'string',
						'sanitize_callback' => 'sanitize_text_field',
					),
					'state' => array(
						'required'          => false,
						'type'              => 'string',
						'sanitize_callback' => static function ( $param ) {
							return is_string( $param ) ? wp_unslash( $param ) : (string) $param;
						},
					),
				),
			)
		);

		register_rest_route(
			self::NAMESPACE,
			'/dashboard/profile/google-calendar/disconnect',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'disconnect' ),
				'permission_callback' => array( $this, 'is_authenticated' ),
			)
		);

		register_rest_route(
			self::NAMESPACE,
			'/dashboard/staff/(?P<id>\d+)/google-calendar/disconnect',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'admin_disconnect_staff_google_calendar' ),
				'permission_callback' => array( 'Bookit_Dashboard_Bookings_API', 'check_admin_permission_callback' ),
				'args'                => array(
					'id' => array(
						'required'          => true,
						'type'              => 'integer',
						'validate_callback' => static function ( $param ) {
							return is_numeric( $param ) && (int) $param > 0;
						},
					),
				),
			)
		);
	}

	/**
	 * Dashboard session required (same behaviour as dashboard bookings API).
	 *
	 * @return bool|WP_Error
	 */
	public function is_authenticated() {
		if ( ! class_exists( 'Bookit_Session' ) ) {
			require_once plugin_dir_path( dirname( __FILE__ ) ) . 'class-bookit-session.php';
		}
		if ( ! class_exists( 'Bookit_Auth' ) ) {
			require_once plugin_dir_path( dirname( __FILE__ ) ) . 'class-bookit-auth.php';
		}

		if ( ! Bookit_Auth::is_logged_in() ) {
			return Bookit_Error_Registry::to_wp_error( 'E1002' );
		}

		return true;
	}

	/**
	 * GET google-calendar/auth-url
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|WP_Error
	 */
	public function get_auth_url( $request ) {
		$staff = Bookit_Auth::get_current_staff();
		if ( ! $staff || empty( $staff['id'] ) ) {
			return new WP_Error(
				'unauthorized',
				__( 'Could not retrieve staff information.', 'bookit-booking-system' ),
				array( 'status' => 401 )
			);
		}

		$url = Bookit_Google_Calendar_Api::get_auth_url( (int) $staff['id'] );
		if ( '' === $url ) {
			return new WP_Error(
				'oauth_not_configured',
				__( 'Google Calendar OAuth is not configured.', 'bookit-booking-system' ),
				array( 'status' => 503 )
			);
		}

		return rest_ensure_response(
			array(
				'url' => $url,
			)
		);
	}

	/**
	 * GET google-calendar/callback — Google redirects here (no session).
	 *
	 * @param WP_REST_Request $request Request.
	 * @return void
	 */
	public function oauth_callback( $request ): void {
		$code  = (string) $request->get_param( 'code' );
		$state = (string) $request->get_param( 'state' );

		if ( '' === $code || '' === $state ) {
			wp_redirect( home_url( '/bookit-dashboard/app/profile?google_error=1' ) );
			exit;
		}

		$staff_id = Bookit_Google_Calendar_Api::handle_callback( $code, $state );

		$profile_path = '/bookit-dashboard/app/profile';
		if ( $staff_id > 0 ) {
			wp_redirect( home_url( $profile_path . '?google_connected=1' ) );
		} else {
			wp_redirect( home_url( $profile_path . '?google_error=1' ) );
		}
		exit;
	}

	/**
	 * POST dashboard/profile/google-calendar/disconnect
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|WP_Error
	 */
	public function disconnect( $request ) {
		$staff = Bookit_Auth::get_current_staff();
		if ( ! $staff || empty( $staff['id'] ) ) {
			return new WP_Error(
				'unauthorized',
				__( 'Could not retrieve staff information.', 'bookit-booking-system' ),
				array( 'status' => 401 )
			);
		}

		Bookit_Google_Calendar_Api::disconnect( (int) $staff['id'] );

		return rest_ensure_response(
			array(
				'success' => true,
			)
		);
	}

	/**
	 * POST dashboard/staff/{id}/google-calendar/disconnect — admin disconnects a staff member's Google Calendar.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|WP_Error
	 */
	public function admin_disconnect_staff_google_calendar( $request ) {
		$raw_id = $request->get_param( 'id' );
		if ( null === $raw_id || '' === $raw_id || ! is_numeric( $raw_id ) ) {
			return new WP_Error(
				'invalid_staff_id',
				__( 'A valid staff ID is required.', 'bookit-booking-system' ),
				array( 'status' => 400 )
			);
		}

		$staff_id = (int) $raw_id;
		if ( $staff_id < 1 ) {
			return new WP_Error(
				'invalid_staff_id',
				__( 'A valid staff ID is required.', 'bookit-booking-system' ),
				array( 'status' => 400 )
			);
		}

		global $wpdb;
		$exists = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT id FROM {$wpdb->prefix}bookings_staff WHERE id = %d AND deleted_at IS NULL",
				$staff_id
			)
		);

		if ( ! $exists ) {
			return new WP_Error(
				'staff_not_found',
				__( 'Staff member not found.', 'bookit-booking-system' ),
				array( 'status' => 404 )
			);
		}

		Bookit_Google_Calendar_Api::disconnect( $staff_id );

		return rest_ensure_response(
			array(
				'success' => true,
			)
		);
	}
}
