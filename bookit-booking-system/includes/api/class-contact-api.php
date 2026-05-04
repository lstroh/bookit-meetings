<?php
/**
 * Contact Details API
 *
 * Handles saving customer contact information to session.
 *
 * @package    Bookit_Booking_System
 * @subpackage Bookit_Booking_System/includes/api
 */

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
	die;
}

/**
 * Contact API class.
 */
class Bookit_Contact_API {

	/**
	 * Register REST API routes.
	 *
	 * @return void
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
			'bookit/v1',
			'/contact/save',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'save_contact_details' ),
				'permission_callback' => array( $this, 'check_permission' ),
				'args'                => array(
					'first_name'        => array(
						'required'          => false,
						'type'              => 'string',
						'sanitize_callback' => 'sanitize_text_field',
					),
					'last_name'         => array(
						'required'          => false,
						'type'              => 'string',
						'sanitize_callback' => 'sanitize_text_field',
					),
					'email'             => array(
						'required'          => false,
						'type'              => 'string',
						'sanitize_callback' => 'sanitize_email',
					),
					'phone'             => array(
						'required'          => false,
						'type'              => 'string',
						'sanitize_callback' => 'sanitize_text_field',
					),
					'special_requests'  => array(
						'required'          => false,
						'type'              => 'string',
						'sanitize_callback' => 'sanitize_textarea_field',
					),
					'marketing_consent' => array(
						'required'          => false,
						'type'              => 'boolean',
						'sanitize_callback' => function( $value ) {
							return (bool) $value;
						},
					),
					'cooling_off_waiver' => array(
						'required'          => false,
						'type'              => 'integer',
						'sanitize_callback' => 'absint',
					),
				),
			)
		);
	}

	/**
	 * Check permission for contact save (CSRF nonce verification).
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return bool|WP_Error True if allowed, WP_Error on failure.
	 */
	public function check_permission( $request ) {
		require_once BOOKIT_PLUGIN_DIR . 'includes/class-csrf-protection.php';

		if ( ! Bookit_CSRF_Protection::verify_rest_request( $request ) ) {
			return Bookit_CSRF_Protection::get_rest_error();
		}

		return true;
	}

	/**
	 * Save contact details to session.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response Response object.
	 */
	public function save_contact_details( $request ) {
		$first_name        = sanitize_text_field( $request->get_param( 'first_name' ) );
		$last_name         = sanitize_text_field( $request->get_param( 'last_name' ) );
		$email             = sanitize_email( $request->get_param( 'email' ) );
		$phone             = sanitize_text_field( $request->get_param( 'phone' ) );
		$special_requests  = sanitize_textarea_field( $request->get_param( 'special_requests' ) );
		$marketing_consent = (bool) $request->get_param( 'marketing_consent' );
		$cooling_off_waiver = absint( $request->get_param( 'cooling_off_waiver' ) );

		$errors = array();

		// First name.
		if ( empty( trim( $first_name ) ) ) {
			$errors['first_name'] = __( 'Please enter your first name', 'bookit-booking-system' );
		} elseif ( strlen( $first_name ) > 100 ) {
			$errors['first_name'] = __( 'First name is too long', 'bookit-booking-system' );
		} elseif ( strlen( trim( $first_name ) ) < 2 ) {
			$errors['first_name'] = __( 'Please enter at least 2 characters', 'bookit-booking-system' );
		}

		// Last name.
		if ( empty( trim( $last_name ) ) ) {
			$errors['last_name'] = __( 'Please enter your last name', 'bookit-booking-system' );
		} elseif ( strlen( $last_name ) > 100 ) {
			$errors['last_name'] = __( 'Last name is too long', 'bookit-booking-system' );
		} elseif ( strlen( trim( $last_name ) ) < 2 ) {
			$errors['last_name'] = __( 'Please enter at least 2 characters', 'bookit-booking-system' );
		}

		// Email.
		if ( empty( trim( $email ) ) ) {
			$errors['email'] = __( 'Email address is required', 'bookit-booking-system' );
		} elseif ( strlen( $email ) > 255 ) {
			$errors['email'] = __( 'Email address is too long', 'bookit-booking-system' );
		} else {
			// Suggest common domain typos (same as frontend); if typo, treat as invalid.
			$typos = array(
				'gmial.com'  => 'gmail.com',
				'gmai.com'   => 'gmail.com',
				'yahooo.com' => 'yahoo.com',
				'hotmial.com' => 'hotmail.com',
				'outlok.com' => 'outlook.com',
			);
			$parts = explode( '@', strtolower( $email ) );
			if ( count( $parts ) === 2 && isset( $typos[ $parts[1] ] ) ) {
				$errors['email'] = sprintf(
					/* translators: %s: suggested correct email */
					__( 'Did you mean %s?', 'bookit-booking-system' ),
					$parts[0] . '@' . $typos[ $parts[1] ]
				);
			} elseif ( ! is_email( $email ) ) {
				$errors['email'] = __( 'Please enter a valid email address', 'bookit-booking-system' );
			}
		}

		// Phone: UK format.
		$phone_clean = preg_replace( '/\D/', '', $phone );
		if ( empty( trim( $phone ) ) ) {
			$errors['phone'] = __( 'Phone number is required', 'bookit-booking-system' );
		} elseif ( ! preg_match( '/^(07|01|02|03)\d{9}$/', $phone_clean ) ) {
			$errors['phone'] = __( 'Please enter a valid UK phone number (e.g., 07700 900123)', 'bookit-booking-system' );
		} else {
			$phone = $phone_clean;
		}

		// Special requests length.
		if ( strlen( $special_requests ) > 500 ) {
			$errors['special_requests'] = __( 'Special requests must be 500 characters or less', 'bookit-booking-system' );
		}

		if ( ! empty( $errors ) ) {
			return new WP_REST_Response(
				array(
					'success' => false,
					'errors'  => $errors,
				),
				400
			);
		}

		require_once BOOKIT_PLUGIN_DIR . 'includes/core/class-session-manager.php';
		Bookit_Session_Manager::init();
		$session = Bookit_Session_Manager::get_data();

		// Check prerequisites (session uses 'date' and 'time').
		if ( ! isset( $session['service_id'], $session['staff_id'], $session['date'], $session['time'] ) ) {
			return new WP_REST_Response(
				array(
					'success' => false,
					'message' => __( 'Previous steps not completed', 'bookit-booking-system' ),
				),
				400
			);
		}

		$requires_waiver = ! empty( $session['date'] ) && bookit_booking_requires_waiver( (string) $session['date'] );
		if ( $requires_waiver && 1 !== $cooling_off_waiver ) {
			return new WP_REST_Response(
				array(
					'success' => false,
					'message' => __( 'Cooling-off waiver is required for bookings within 14 days.', 'bookit-booking-system' ),
					'errors'  => array(
						'cooling_off_waiver' => __( 'Cooling-off waiver is required for bookings within 14 days.', 'bookit-booking-system' ),
					),
				),
				400
			);
		}

		$session['customer_first_name']       = trim( $first_name );
		$session['customer_last_name']        = trim( $last_name );
		$session['customer_email']            = is_email( $email ) ? $email : trim( $email );
		$session['customer_phone']             = $phone;
		$session['customer_special_requests'] = trim( $special_requests );
		$session['marketing_consent']         = $marketing_consent ? 1 : 0;
		$session['consent_date']              = $marketing_consent ? current_time( 'mysql' ) : null;
		$session['cooling_off_waiver']        = $cooling_off_waiver ? 1 : 0;

		// Advance wizard to step 5 (payment).
		$session['current_step'] = 5;

		Bookit_Session_Manager::set_data( $session );
		Bookit_Session_Manager::set( 'cooling_off_waiver', $session['cooling_off_waiver'] );

		return new WP_REST_Response(
			array(
				'success'       => true,
				'message'       => __( 'Contact details saved', 'bookit-booking-system' ),
				'redirect_url'  => home_url( '/book?step=5' ),
			),
			200
		);
	}
}
