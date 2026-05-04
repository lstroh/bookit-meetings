<?php
/**
 * REST API endpoints for booking wizard.
 *
 * @package    Bookit_Booking_System
 * @subpackage Bookit_Booking_System/includes/api
 */

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
	die;
}

/**
 * Wizard API class.
 */
class Bookit_Wizard_API {

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
			'/wizard/session',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_session' ),
					'permission_callback' => '__return_true', // Public endpoint for booking wizard.
					'args'                => array(),
				),
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'update_session' ),
					'permission_callback' => array( $this, 'check_permission' ),
					'args'                => array(
						'current_step' => array(
							'required'          => false,
							'type'              => 'integer',
							'sanitize_callback' => 'absint',
							'validate_callback' => array( $this, 'validate_step' ),
						),
						'service_id'   => array(
							'required'          => false,
							'type'              => 'integer',
							'sanitize_callback' => 'absint',
						),
						'staff_id'     => array(
							'required'          => false,
							'type'              => 'integer',
							'sanitize_callback' => 'absint',
						),
						'date'         => array(
							'required'          => false,
							'type'              => 'string',
							'sanitize_callback' => 'sanitize_text_field',
						),
						'time'         => array(
							'required'          => false,
							'type'              => 'string',
							'sanitize_callback' => 'sanitize_text_field',
						),
						'customer'      => array(
							'required'          => false,
							'type'              => 'object',
							'sanitize_callback' => array( $this, 'sanitize_customer' ),
						),
						'service_name'   => array(
							'required'          => false,
							'type'              => 'string',
							'sanitize_callback' => 'sanitize_text_field',
						),
						'service_duration' => array(
							'required'          => false,
							'type'              => 'integer',
							'sanitize_callback' => 'absint',
						),
						'payment_method' => array(
							'required'          => false,
							'type'              => 'string',
							'sanitize_callback' => 'sanitize_text_field',
						),
					),
				),
			)
		);

		register_rest_route(
			'bookit/v1',
			'/wizard/complete',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'complete_booking' ),
				'permission_callback' => array( $this, 'check_permission' ),
				'args'                => array(),
			)
		);

		register_rest_route(
			'bookit/v1',
			'/wizard/cancel',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'cancel_booking_magic_link' ),
				'permission_callback' => '__return_true',
				'args'                => array(
					'booking_id' => array(
						'required'          => true,
						'validate_callback' => function ( $param ) {
							return is_numeric( $param ) && (int) $param > 0;
						},
						'sanitize_callback' => 'absint',
					),
					'token'        => array(
						'required'          => true,
						'sanitize_callback' => 'sanitize_text_field',
					),
					'reason'       => array(
						'required'          => false,
						'sanitize_callback' => 'sanitize_textarea_field',
						'default'           => '',
					),
				),
			)
		);

		register_rest_route(
			'bookit/v1',
			'/wizard/reschedule',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'reschedule_booking_magic_link' ),
				'permission_callback' => '__return_true',
				'args'                => array(
					'booking_id' => array(
						'required'          => true,
						'validate_callback' => function ( $param ) {
							return is_numeric( $param ) && (int) $param > 0;
						},
						'sanitize_callback' => 'absint',
					),
					'token'      => array(
						'required'          => true,
						'sanitize_callback' => 'sanitize_text_field',
					),
					'new_date'   => array(
						'required'          => true,
						'validate_callback' => function ( $param ) {
							return (bool) preg_match( '/^\d{4}-\d{2}-\d{2}$/', $param );
						},
						'sanitize_callback' => 'sanitize_text_field',
					),
					'new_time'   => array(
						'required'          => true,
						'validate_callback' => function ( $param ) {
							return (bool) preg_match( '/^\d{2}:\d{2}(:\d{2})?$/', $param );
						},
						'sanitize_callback' => 'sanitize_text_field',
					),
				),
			)
		);

		register_rest_route(
			'bookit/v1',
			'/wizard/ical',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'get_ical' ),
				'permission_callback' => '__return_true',
				'args'                => array(
					'booking_id' => array(
						'required'          => true,
						'validate_callback' => function ( $param ) {
							return is_numeric( $param ) && (int) $param > 0;
						},
						'sanitize_callback' => 'absint',
					),
					'token'        => array(
						'required'          => true,
						'sanitize_callback' => 'sanitize_text_field',
					),
				),
			)
		);
	}

	/**
	 * Check permission for session updates.
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
	 * Validate step number.
	 *
	 * @param int             $value Step number.
	 * @param WP_REST_Request $request Request object.
	 * @param string          $param Parameter name.
	 * @return bool True if valid.
	 */
	public function validate_step( $value, $request, $param ) {
		return $value >= 1 && $value <= 5;
	}

	/**
	 * Sanitize customer data.
	 *
	 * @param array $customer Customer data.
	 * @return array Sanitized customer data.
	 */
	public function sanitize_customer( $customer ) {
		if ( ! is_array( $customer ) ) {
			return array();
		}

		$sanitized = array();
		$allowed_fields = array( 'name', 'email', 'phone', 'notes' );

		foreach ( $allowed_fields as $field ) {
			if ( isset( $customer[ $field ] ) ) {
				$sanitized[ $field ] = sanitize_text_field( $customer[ $field ] );
			}
		}

		// Validate email if provided.
		if ( isset( $sanitized['email'] ) && ! empty( $sanitized['email'] ) ) {
			$sanitized['email'] = sanitize_email( $sanitized['email'] );
		}

		return $sanitized;
	}

	/**
	 * Get session data.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response Response object.
	 */
	public function get_session( $request ) {
		require_once BOOKIT_PLUGIN_DIR . 'includes/core/class-session-manager.php';
		Bookit_Session_Manager::init();

		// Check if session expired.
		if ( Bookit_Session_Manager::is_expired() ) {
			Bookit_Session_Manager::clear();
		}

		$data = Bookit_Session_Manager::get_data();
		$data['time_remaining'] = Bookit_Session_Manager::get_time_remaining();

		return new WP_REST_Response(
			array(
				'success' => true,
				'data'    => $data,
			),
			200
		);
	}

	/**
	 * Update session data.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response Response object.
	 */
	public function update_session( $request ) {
		require_once BOOKIT_PLUGIN_DIR . 'includes/core/class-session-manager.php';
		Bookit_Session_Manager::init();

		// Check if session expired.
		if ( Bookit_Session_Manager::is_expired() ) {
			Bookit_Session_Manager::clear();
		}

		// Get parameters.
		$params = $request->get_params();

		// Prepare update data.
		$update_data = array();

		if ( isset( $params['current_step'] ) ) {
			$update_data['current_step'] = (int) $params['current_step'];
		}

		if ( isset( $params['service_id'] ) ) {
			$update_data['service_id'] = (int) $params['service_id'];
		}

		if ( isset( $params['staff_id'] ) ) {
			$update_data['staff_id'] = (int) $params['staff_id'];
		}

		if ( isset( $params['date'] ) ) {
			$update_data['date'] = sanitize_text_field( $params['date'] );
		}

		if ( isset( $params['time'] ) ) {
			$update_data['time'] = sanitize_text_field( $params['time'] );
		}

		if ( isset( $params['service_name'] ) ) {
			$update_data['service_name'] = sanitize_text_field( $params['service_name'] );
		}

		if ( isset( $params['service_duration'] ) ) {
			$update_data['service_duration'] = absint( $params['service_duration'] );
		}

		if ( isset( $params['payment_method'] ) ) {
			$update_data['payment_method'] = sanitize_text_field( $params['payment_method'] );
		}

		// Step 4 contact fields (V2 wizard; flat session keys used by templates and complete_booking).
		if ( isset( $params['customer_first_name'] ) ) {
			$update_data['customer_first_name'] = sanitize_text_field( $params['customer_first_name'] );
		}
		if ( isset( $params['customer_last_name'] ) ) {
			$update_data['customer_last_name'] = sanitize_text_field( $params['customer_last_name'] );
		}
		if ( isset( $params['customer_email'] ) ) {
			$update_data['customer_email'] = sanitize_email( $params['customer_email'] );
		}
		if ( isset( $params['customer_phone'] ) ) {
			$update_data['customer_phone'] = sanitize_text_field( $params['customer_phone'] );
		}
		if ( isset( $params['customer_special_requests'] ) ) {
			$update_data['customer_special_requests'] = sanitize_textarea_field( $params['customer_special_requests'] );
		}
		if ( isset( $params['cooling_off_waiver'] ) ) {
			$update_data['cooling_off_waiver'] = ! empty( $params['cooling_off_waiver'] ) ? 1 : 0;
		}
		if ( isset( $params['marketing_consent'] ) ) {
			$update_data['marketing_consent'] = ! empty( $params['marketing_consent'] ) ? 1 : 0;
		}

		if ( isset( $params['customer'] ) && is_array( $params['customer'] ) ) {
			$current_customer = Bookit_Session_Manager::get( 'customer', array() );
			$update_data['customer'] = array_merge( $current_customer, $params['customer'] );
		}

		if ( isset( $update_data['service_id'] ) && (int) $update_data['service_id'] > 0 ) {
			$this->maybe_fill_service_meta_from_db( $update_data );
		}

		// Update session.
		if ( ! empty( $update_data ) ) {
			Bookit_Session_Manager::set_data( $update_data );

			// Regenerate session ID on step changes for security.
			if ( isset( $update_data['current_step'] ) ) {
				Bookit_Session_Manager::regenerate();
			}
		}

		// Return updated session data.
		$data = Bookit_Session_Manager::get_data();
		$data['time_remaining'] = Bookit_Session_Manager::get_time_remaining();

		return new WP_REST_Response(
			array(
				'success' => true,
				'data'    => $data,
			),
			200
		);
	}

	/**
	 * Fill service name/duration from DB when missing.
	 *
	 * @param array $update_data Update payload (by ref).
	 * @return void
	 */
	private function maybe_fill_service_meta_from_db( array &$update_data ) {
		if ( ! empty( $update_data['service_name'] ) && isset( $update_data['service_duration'] ) && (int) $update_data['service_duration'] > 0 ) {
			return;
		}
		global $wpdb;
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
		$row = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT name, duration FROM {$wpdb->prefix}bookings_services WHERE id = %d",
				(int) $update_data['service_id']
			),
			ARRAY_A
		);
		if ( ! $row ) {
			return;
		}
		if ( empty( $update_data['service_name'] ) ) {
			$update_data['service_name'] = $row['name'];
		}
		if ( ! isset( $update_data['service_duration'] ) || (int) $update_data['service_duration'] <= 0 ) {
			$update_data['service_duration'] = (int) $row['duration'];
		}
	}

	/**
	 * Complete booking from wizard session (pay on arrival / package); returns redirect URL.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response|WP_Error
	 */
	public function complete_booking( $request ) {
		$ip = Bookit_Rate_Limiter::get_client_ip();
		if ( ! Bookit_Rate_Limiter::check( 'wizard_book', $ip, 10, HOUR_IN_SECONDS ) ) {
			return new WP_Error(
				'rate_limit_exceeded',
				__( 'Too many requests. Please wait before trying again.', 'bookit-booking-system' ),
				array( 'status' => 429 )
			);
		}

		require_once BOOKIT_PLUGIN_DIR . 'includes/core/class-session-manager.php';
		Bookit_Session_Manager::init();

		if ( Bookit_Session_Manager::is_expired() ) {
			return new WP_Error(
				'session_expired',
				__( 'Your session has expired. Please start again.', 'bookit-booking-system' ),
				array( 'status' => 400 )
			);
		}

		$session_data   = Bookit_Session_Manager::get_data();
		$payment_method = isset( $session_data['payment_method'] )
			? sanitize_text_field( $session_data['payment_method'] )
			: '';

		if ( empty( $session_data ) ) {
			return new WP_Error(
				'invalid_session',
				__( 'No booking data found. Please start again.', 'bookit-booking-system' ),
				array( 'status' => 400 )
			);
		}

		// Step 5 stores package choice as use_package_{customer_package_id}; map for the processor.
		if ( preg_match( '/^use_package_(\d+)$/', $payment_method, $pkg_match ) ) {
			$session_data['customer_package_id'] = (int) $pkg_match[1];
			$payment_method                      = 'use_package';
		}

		// V2 wizard posts payment_method "card" for Stripe Checkout (V1 uses admin_post + session_id string).
		if ( 'card' === $payment_method && isset( $session_data['wizard_version'] ) && 'v2' === $session_data['wizard_version'] ) {
			$payment_method = 'stripe';
		}

		// V2 "Buy a package" stores payment_method as buy_{package_type_id}; route to Stripe package checkout.
		if ( isset( $session_data['wizard_version'] ) && 'v2' === $session_data['wizard_version'] && preg_match( '/^buy_(\d+)$/', $payment_method, $buy_match ) ) {
			global $wpdb;
			$package_type_id = (int) $buy_match[1];
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
			$package_type = $wpdb->get_row(
				$wpdb->prepare(
					"SELECT id, name, sessions_count, price_mode, fixed_price, discount_percentage,
						expiry_enabled, expiry_days, is_active
					FROM {$wpdb->prefix}bookings_package_types
					WHERE id = %d AND is_active = 1",
					$package_type_id
				),
				ARRAY_A
			);

			if ( ! $package_type ) {
				return Bookit_Error_Registry::to_wp_error( 'E5001' );
			}

			$charge = 0.0;
			if ( 'fixed' === ( $package_type['price_mode'] ?? '' ) ) {
				$charge = (float) $package_type['fixed_price'];
			} elseif ( 'discount' === ( $package_type['price_mode'] ?? '' ) ) {
				$service_price = null;
				if ( isset( $session_data['service_price'] ) && '' !== (string) $session_data['service_price'] ) {
					$service_price = (float) $session_data['service_price'];
				} elseif ( ! empty( $session_data['service_id'] ) ) {
					// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
					$db_price = $wpdb->get_var(
						$wpdb->prepare(
							"SELECT price FROM {$wpdb->prefix}bookings_services WHERE id = %d",
							(int) $session_data['service_id']
						)
					);
					$service_price = null !== $db_price ? (float) $db_price : null;
				}
				$disc_pct = isset( $package_type['discount_percentage'] ) ? (float) $package_type['discount_percentage'] : 0.0;
				if ( null !== $service_price && $service_price > 0 ) {
					$charge = round( $service_price * ( 1 - $disc_pct / 100 ), 2 );
				}
			}

			if ( $charge <= 0 ) {
				return Bookit_Error_Registry::to_wp_error(
					'PACKAGE_PRICE_INVALID',
					array( 'package_type_id' => $package_type_id )
				);
			}

			$session_data['wizard_version'] = 'v2';
			require_once BOOKIT_PLUGIN_DIR . 'includes/payment/class-stripe-checkout.php';
			$stripe_checkout = new Booking_System_Stripe_Checkout();
			try {
				$session = $stripe_checkout->create_package_checkout_session( $package_type, $charge, $session_data );
			} catch ( \Stripe\Exception\ApiErrorException $e ) {
				if ( function_exists( 'error_log' ) ) {
					error_log( 'Stripe package checkout ApiErrorException: ' . $e->getMessage() );
				}
				return Bookit_Error_Registry::to_wp_error(
					'E3010',
					array( 'gateway_message' => $e->getMessage() )
				);
			}

			if ( is_wp_error( $session ) ) {
				if ( 'stripe_error' === $session->get_error_code() || 'mock_error' === $session->get_error_code() ) {
					return Bookit_Error_Registry::to_wp_error(
						'E3010',
						array( 'gateway_message' => $session->get_error_message() )
					);
				}
				$err_data = $session->get_error_data();
				$status   = is_array( $err_data ) && isset( $err_data['status'] ) ? (int) $err_data['status'] : 400;
				return new WP_Error(
					$session->get_error_code(),
					$session->get_error_message(),
					array( 'status' => $status )
				);
			}

			$url = is_object( $session ) && isset( $session->url ) ? (string) $session->url : '';
			if ( '' === $url ) {
				return Bookit_Error_Registry::to_wp_error(
					'E3010',
					array( 'gateway_message' => 'Missing checkout URL' )
				);
			}

			return rest_ensure_response(
				array(
					'success'      => true,
					'redirect_url' => $url,
				)
			);
		}

		require_once BOOKIT_PLUGIN_DIR . 'includes/payment/class-payment-processor.php';
		$processor = new Booking_System_Payment_Processor();

		switch ( $payment_method ) {
			case 'pay_on_arrival':
			case 'person':
				$result = $processor->process_pay_on_arrival( $session_data );
				break;

			case 'use_package':
				$result = $processor->process_use_package( $session_data );
				break;

			case 'stripe':
				$stripe_session = $session_data;
				$stripe_session['wizard_version'] = 'v2';
				require_once BOOKIT_PLUGIN_DIR . 'includes/payment/class-stripe-checkout.php';
				$stripe_checkout = new Booking_System_Stripe_Checkout();
				try {
					$stripe_result = $stripe_checkout->create_checkout_session( $stripe_session );
				} catch ( \Stripe\Exception\ApiErrorException $e ) {
					if ( function_exists( 'error_log' ) ) {
						error_log( 'Stripe Checkout ApiErrorException: ' . $e->getMessage() );
					}
					return Bookit_Error_Registry::to_wp_error(
						'E3010',
						array( 'gateway_message' => $e->getMessage() )
					);
				}

				if ( is_wp_error( $stripe_result ) ) {
					if ( 'stripe_error' === $stripe_result->get_error_code() ) {
						if ( function_exists( 'error_log' ) ) {
							error_log( 'Stripe Checkout: ' . $stripe_result->get_error_message() );
						}
						return Bookit_Error_Registry::to_wp_error(
							'E3010',
							array( 'gateway_message' => $stripe_result->get_error_message() )
						);
					}
					$err_data = $stripe_result->get_error_data();
					$status   = is_array( $err_data ) && isset( $err_data['status'] ) ? (int) $err_data['status'] : 400;
					return new WP_Error(
						$stripe_result->get_error_code(),
						$stripe_result->get_error_message(),
						array( 'status' => $status )
					);
				}

				if ( ! is_array( $stripe_result ) || empty( $stripe_result['redirect_url'] ) ) {
					return Bookit_Error_Registry::to_wp_error(
						'E3010',
						array( 'gateway_message' => 'Missing checkout URL' )
					);
				}

				return rest_ensure_response(
					array(
						'success'      => true,
						'redirect_url' => $stripe_result['redirect_url'],
					)
				);

			case 'paypal':
				return Bookit_Error_Registry::to_wp_error( 'PAYMENT_METHOD_NOT_SUPPORTED' );

			case 'card':
				return new WP_Error(
					'payment_method_not_available',
					__( 'Online payment is not yet available. Please select Pay in Person or use a package.', 'bookit-booking-system' ),
					array( 'status' => 400 )
				);

			default:
				return new WP_Error(
					'invalid_payment_method',
					__( 'Invalid payment method.', 'bookit-booking-system' ),
					array( 'status' => 400 )
				);
		}

		if ( is_wp_error( $result ) ) {
			return new WP_Error(
				$result->get_error_code(),
				$result->get_error_message(),
				array( 'status' => 400 )
			);
		}

		return rest_ensure_response(
			array(
				'success'      => true,
				'booking_id'   => $result['booking_id'],
				'redirect_url' => $result['redirect_url'],
			)
		);
	}

	/**
	 * GET /wizard/ical — download booking as .ics (magic link token required).
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response|WP_Error
	 */
	public function get_ical( WP_REST_Request $request ) {
		$booking_id = absint( $request->get_param( 'booking_id' ) );
		$token      = sanitize_text_field( (string) $request->get_param( 'token' ) );

		$payload = $this->fetch_and_build_ical( $booking_id, $token );
		if ( is_wp_error( $payload ) ) {
			return $payload;
		}

		$ics              = $payload['ics'];
		$booking_ref_file = sanitize_file_name( (string) $payload['booking_reference'] );
		if ( '' === $booking_ref_file ) {
			$booking_ref_file = 'booking-' . (string) $booking_id;
		}
		$filename = 'booking-' . $booking_ref_file . '.ics';

		add_filter(
			'rest_pre_serve_request',
			function ( $served ) use ( $ics, $filename ) {
				if ( ! $served ) {
					if ( defined( 'WP_TESTS_PHPUNIT_POLYFILLS_PATH' ) || defined( 'WP_TESTS_DIR' ) ) {
						return true;
					}
					header( 'Content-Type: text/calendar; charset=utf-8' );
					header( 'Content-Disposition: attachment; filename="' . $filename . '"' );
					header( 'Cache-Control: no-cache, no-store, must-revalidate' );
					header( 'Content-Length: ' . strlen( $ics ) );
					echo $ics; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
				}
				return true;
			}
		);

		return new WP_REST_Response( null, 200 );
	}

	/**
	 * Build .ics body for a booking after token validation (for tests and internal use).
	 *
	 * @param int    $booking_id Booking ID.
	 * @param string $token      Magic link token.
	 * @return string|WP_Error Raw iCalendar string or error.
	 */
	protected function build_ical_content( int $booking_id, string $token ) {
		$result = $this->fetch_and_build_ical( $booking_id, $token );
		if ( is_wp_error( $result ) ) {
			return $result;
		}
		return $result['ics'];
	}

	/**
	 * Load booking, validate token, build .ics and reference for Content-Disposition.
	 *
	 * @param int    $booking_id Booking ID.
	 * @param string $token      Magic link token.
	 * @return array{ics: string, booking_reference: string}|WP_Error
	 */
	private function fetch_and_build_ical( int $booking_id, string $token ) {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$booking = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT b.id, b.booking_reference, b.booking_date, b.start_time,
					b.end_time, b.magic_link_token, b.status,
					s.name AS service_name,
					st.first_name AS staff_first_name,
					st.last_name AS staff_last_name
				FROM {$wpdb->prefix}bookings b
				LEFT JOIN {$wpdb->prefix}bookings_services s ON s.id = b.service_id
				LEFT JOIN {$wpdb->prefix}bookings_staff st ON st.id = b.staff_id
				WHERE b.id = %d AND b.deleted_at IS NULL",
				$booking_id
			)
		);

		if ( ! $booking ) {
			return Bookit_Error_Registry::to_wp_error(
				'E2002',
				array( 'booking_id' => $booking_id )
			);
		}

		if ( ! hash_equals( (string) $booking->magic_link_token, (string) $token ) ) {
			return new WP_Error(
				'invalid_token',
				__( 'Invalid or expired link.', 'bookit-booking-system' ),
				array( 'status' => 403 )
			);
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$settings_rows = $wpdb->get_results(
			"SELECT setting_key, setting_value FROM {$wpdb->prefix}bookings_settings
			WHERE setting_key IN ('business_name','business_address')",
			ARRAY_A
		);
		$settings         = array_column( $settings_rows, 'setting_value', 'setting_key' );
		$business_name    = $settings['business_name'] ?? get_bloginfo( 'name' );
		$business_address = $settings['business_address'] ?? '';

		$tz_string = get_option( 'timezone_string' );
		$tz_name   = $tz_string ? $tz_string : 'Europe/London';
		try {
			$tz = new \DateTimeZone( $tz_name );
		} catch ( \Exception $e ) {
			$tz = new \DateTimeZone( 'Europe/London' );
		}

		$start_his = $this->normalize_time_his( (string) $booking->start_time );
		$end_his   = $this->normalize_time_his( (string) $booking->end_time );

		$dt_start = new \DateTime( $booking->booking_date . ' ' . $start_his, $tz );
		$dt_end   = new \DateTime( $booking->booking_date . ' ' . $end_his, $tz );
		$dt_now   = new \DateTime( 'now', new \DateTimeZone( 'UTC' ) );

		$staff_name = trim( (string) $booking->staff_first_name . ' ' . (string) $booking->staff_last_name );
		$service_label = (string) $booking->service_name;
		$summary       = $service_label . ' with ' . $staff_name;

		$ref_for_uid = (string) $booking->booking_reference;
		if ( '' === $ref_for_uid ) {
			$ref_for_uid = 'BK-' . str_pad( (string) $booking->id, 8, '0', STR_PAD_LEFT );
		}
		$host = parse_url( home_url(), PHP_URL_HOST );
		$host = is_string( $host ) && '' !== $host ? $host : 'localhost';
		$uid  = $ref_for_uid . '@bookit.' . $host;

		$ics  = "BEGIN:VCALENDAR\r\n";
		$ics .= "VERSION:2.0\r\n";
		$ics .= "PRODID:-//Bookit Booking System//EN\r\n";
		$ics .= "CALSCALE:GREGORIAN\r\n";
		$ics .= "METHOD:PUBLISH\r\n";
		$ics .= "BEGIN:VEVENT\r\n";
		$ics .= 'UID:' . $this->ical_escape( $uid ) . "\r\n";
		$ics .= 'DTSTAMP:' . $dt_now->format( 'Ymd\THis\Z' ) . "\r\n";
		$ics .= 'DTSTART;TZID=' . $tz->getName() . ':' . $dt_start->format( 'Ymd\THis' ) . "\r\n";
		$ics .= 'DTEND;TZID=' . $tz->getName() . ':' . $dt_end->format( 'Ymd\THis' ) . "\r\n";
		$ics .= 'SUMMARY:' . $this->ical_escape( $summary ) . "\r\n";

		$cancel_url     = add_query_arg(
			array(
				'booking_id' => (int) $booking->id,
				'token'      => (string) $booking->magic_link_token,
			),
			home_url( '/bookit-cancel/' )
		);
		$reschedule_url = add_query_arg(
			array(
				'booking_id' => (int) $booking->id,
				'token'      => (string) $booking->magic_link_token,
			),
			home_url( '/bookit-reschedule/' )
		);

		$description  = 'Booking reference: ' . $ref_for_uid;
		$description .= "\n" . (string) $business_name;
		$description .= "\nCancel: " . $cancel_url;
		$description .= "\nReschedule: " . $reschedule_url;
		$ics .= 'DESCRIPTION:' . $this->ical_escape( $description ) . "\r\n";
		$ics .= 'LOCATION:' . $this->ical_escape( (string) $business_address ) . "\r\n";
		$ics .= "STATUS:CONFIRMED\r\n";
		$ics .= "END:VEVENT\r\n";
		$ics .= "END:VCALENDAR\r\n";

		$booking_ref_file = $ref_for_uid;

		return array(
			'ics'               => $ics,
			'booking_reference' => $booking_ref_file,
		);
	}

	/**
	 * Escape text for iCalendar property values (RFC 5545).
	 *
	 * @param string $text Raw text.
	 * @return string
	 */
	private function ical_escape( string $text ): string {
		$text = str_replace( '\\', '\\\\', $text );
		$text = str_replace( ';', '\\;', $text );
		$text = str_replace( ',', '\\,', $text );
		$text = str_replace( "\n", '\\n', $text );
		return $text;
	}

	/**
	 * Cancel a booking using the magic link token (no login).
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response|WP_Error
	 */
	public function cancel_booking_magic_link( WP_REST_Request $request ) {
		$ip = sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ?? '' ) );
		if ( ! Bookit_Rate_Limiter::check( 'magic_cancel', $ip, 10, HOUR_IN_SECONDS ) ) {
			return Bookit_Error_Registry::to_wp_error(
				'E6001',
				array( 'action' => 'magic_cancel' )
			);
		}

		global $wpdb;
		$booking_id = (int) $request->get_param( 'booking_id' );
		$token      = (string) $request->get_param( 'token' );
		$reason     = (string) $request->get_param( 'reason' );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$booking = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT id, status, booking_date, start_time, end_time, customer_id, magic_link_token
				FROM {$wpdb->prefix}bookings
				WHERE id = %d AND deleted_at IS NULL",
				$booking_id
			),
			ARRAY_A
		);

		if ( ! $booking ) {
			return Bookit_Error_Registry::to_wp_error(
				'E2002',
				array( 'booking_id' => $booking_id )
			);
		}

		if ( ! hash_equals( (string) $booking['magic_link_token'], $token ) ) {
			return new WP_Error(
				'invalid_token',
				__( 'Invalid or expired link.', 'bookit-booking-system' ),
				array( 'status' => 403 )
			);
		}

		$status = (string) $booking['status'];
		if ( in_array( $status, array( 'cancelled', 'completed', 'no_show' ), true ) ) {
			return Bookit_Error_Registry::to_wp_error(
				'E2003',
				array( 'booking_id' => $booking_id )
			);
		}

		$policy_error = $this->magic_link_policy_window_error(
			(string) $booking['booking_date'],
			(string) $booking['start_time']
		);
		if ( is_wp_error( $policy_error ) ) {
			return $policy_error;
		}

		$old_status = $status;

		$result = $wpdb->update(
			$wpdb->prefix . 'bookings',
			array(
				'status'                => 'cancelled',
				'cancelled_by'          => 'customer',
				'cancelled_at'          => current_time( 'mysql' ),
				'cancellation_reason'   => $reason,
				'updated_at'            => current_time( 'mysql' ),
				'deleted_at'            => current_time( 'mysql' ),
				'cancelled_start_time'  => $booking['start_time'],
				'cancelled_end_time'    => $booking['end_time'],
				'start_time'            => null,
				'end_time'              => null,
			),
			array( 'id' => $booking_id ),
			array( '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s' ),
			array( '%d' )
		);

		if ( false === $result ) {
			return new WP_Error(
				'cancellation_failed',
				__( 'Could not cancel this booking.', 'bookit-booking-system' ),
				array( 'status' => 500 )
			);
		}

		Bookit_Audit_Logger::log(
			'booking.cancelled_by_customer',
			'booking',
			$booking_id,
			array(
				'old_status'    => $old_status,
				'cancelled_via' => 'magic_link',
			)
		);

		do_action(
			'bookit_after_booking_cancelled',
			$booking_id,
			array(
				'cancelled_by' => 'customer',
				'via'          => 'magic_link',
			)
		);

		$this->enqueue_magic_link_email( 'magic_link_cancel', $booking_id );

		return rest_ensure_response(
			array(
				'success' => true,
				'message' => __( 'Your booking has been cancelled.', 'bookit-booking-system' ),
			)
		);
	}

	/**
	 * Reschedule a booking using the magic link token (no login).
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response|WP_Error
	 */
	public function reschedule_booking_magic_link( WP_REST_Request $request ) {
		$ip = sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ?? '' ) );
		if ( ! Bookit_Rate_Limiter::check( 'magic_reschedule', $ip, 10, HOUR_IN_SECONDS ) ) {
			return Bookit_Error_Registry::to_wp_error(
				'E6001',
				array( 'action' => 'magic_reschedule' )
			);
		}

		global $wpdb;
		$booking_id = (int) $request->get_param( 'booking_id' );
		$token      = (string) $request->get_param( 'token' );
		$new_date   = (string) $request->get_param( 'new_date' );
		$new_time   = (string) $request->get_param( 'new_time' );

		$new_time_norm = $this->normalize_time_his( $new_time );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$booking = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT id, status, booking_date AS old_date, start_time AS old_time, customer_id,
					magic_link_token, service_id, staff_id
				FROM {$wpdb->prefix}bookings
				WHERE id = %d AND deleted_at IS NULL",
				$booking_id
			),
			ARRAY_A
		);

		if ( ! $booking ) {
			return Bookit_Error_Registry::to_wp_error(
				'E2002',
				array( 'booking_id' => $booking_id )
			);
		}

		if ( ! hash_equals( (string) $booking['magic_link_token'], $token ) ) {
			return new WP_Error(
				'invalid_token',
				__( 'Invalid or expired link.', 'bookit-booking-system' ),
				array( 'status' => 403 )
			);
		}

		$status = (string) $booking['status'];
		if ( in_array( $status, array( 'cancelled', 'completed', 'no_show' ), true ) ) {
			return Bookit_Error_Registry::to_wp_error(
				'E2003',
				array( 'booking_id' => $booking_id )
			);
		}

		$policy_error = $this->magic_link_policy_window_error(
			(string) $booking['old_date'],
			(string) $booking['old_time']
		);
		if ( is_wp_error( $policy_error ) ) {
			return $policy_error;
		}

		$staff_id = (int) $booking['staff_id'];

		$conflict_id = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT id FROM {$wpdb->prefix}bookings
				WHERE staff_id = %d
					AND booking_date = %s
					AND start_time = %s
					AND id != %d
					AND deleted_at IS NULL
					AND status != 'cancelled'",
				$staff_id,
				$new_date,
				$new_time_norm,
				$booking_id
			)
		);

		if ( $conflict_id ) {
			return Bookit_Error_Registry::to_wp_error(
				'E2001',
				array(
					'staff_id' => $staff_id,
					'date'     => $new_date,
					'time'     => $new_time_norm,
				)
			);
		}

		$service_id = (int) $booking['service_id'];
		$duration   = (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT duration FROM {$wpdb->prefix}bookings_services WHERE id = %d",
				$service_id
			)
		);

		if ( $duration <= 0 ) {
			return new WP_Error(
				'invalid_service',
				__( 'Could not determine service duration.', 'bookit-booking-system' ),
				array( 'status' => 500 )
			);
		}

		$new_end_time = $this->add_minutes_to_time_string( $new_time_norm, $duration );

		$update_result = $wpdb->update(
			$wpdb->prefix . 'bookings',
			array(
				'booking_date' => $new_date,
				'start_time'   => $new_time_norm,
				'end_time'     => $new_end_time,
				'updated_at'   => current_time( 'mysql' ),
			),
			array( 'id' => $booking_id ),
			array( '%s', '%s', '%s', '%s' ),
			array( '%d' )
		);

		if ( false === $update_result ) {
			return new WP_Error(
				'reschedule_failed',
				__( 'Could not reschedule this booking.', 'bookit-booking-system' ),
				array( 'status' => 500 )
			);
		}

		Bookit_Audit_Logger::log(
			'booking.rescheduled_by_customer',
			'booking',
			$booking_id,
			array(
				'old_date' => (string) $booking['old_date'],
				'old_time' => (string) $booking['old_time'],
				'new_date' => $new_date,
				'new_time' => $new_time_norm,
				'via'      => 'magic_link',
			)
		);

		do_action(
			'bookit_booking_rescheduled',
			$booking_id,
			array(
				'new_date'       => $new_date,
				'new_time'       => $new_time_norm,
				'rescheduled_by' => 'customer',
				'via'            => 'magic_link',
			)
		);

		$this->enqueue_magic_link_email( 'magic_link_reschedule', $booking_id );

		return rest_ensure_response(
			array(
				'success'   => true,
				'new_date'  => $new_date,
				'new_time'  => $new_time_norm,
			)
		);
	}

	/**
	 * Hours notice required before appointment for online cancel/reschedule (settings key: cancellation_window_hours).
	 *
	 * @return int
	 */
	private function get_cancellation_notice_hours(): int {
		global $wpdb;
		// Stored key matches dashboard cancellation policy (see Bookit_Dashboard_Bookings_API settings).
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$value = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT setting_value FROM {$wpdb->prefix}bookings_settings WHERE setting_key = %s LIMIT 1",
				'cancellation_window_hours'
			)
		);
		if ( null === $value || '' === $value ) {
			return 24;
		}
		$hours = absint( $value );
		return $hours > 0 ? $hours : 24;
	}

	/**
	 * Whether the appointment is too soon for online cancel/reschedule.
	 *
	 * @param string $booking_date Y-m-d.
	 * @param string $start_time   H:i:s or H:i.
	 * @return true|WP_Error True if allowed; WP_Error if inside policy window.
	 */
	private function magic_link_policy_window_error( string $booking_date, string $start_time ) {
		$notice_hours = $this->get_cancellation_notice_hours();

		$tz_string = get_option( 'timezone_string' );
		if ( ! empty( $tz_string ) ) {
			try {
				$tz = new \DateTimeZone( $tz_string );
			} catch ( \Exception $e ) {
				$tz = wp_timezone();
			}
		} else {
			$tz = wp_timezone();
		}

		$time_for_parse = $this->normalize_time_his( $start_time );
		try {
			$appt = new \DateTimeImmutable( $booking_date . ' ' . $time_for_parse, $tz );
			$now  = new \DateTimeImmutable( 'now', $tz );
		} catch ( \Exception $e ) {
			return new WP_Error(
				'invalid_booking_datetime',
				__( 'Could not read this booking\'s date and time.', 'bookit-booking-system' ),
				array( 'status' => 500 )
			);
		}

		$seconds_remaining = $appt->getTimestamp() - $now->getTimestamp();
		$hours_remaining   = $seconds_remaining / HOUR_IN_SECONDS;

		if ( $hours_remaining < (float) $notice_hours ) {
			return new WP_Error(
				'within_cancellation_window',
				__( 'Online cancellation is not available this close to your appointment. Please contact us directly.', 'bookit-booking-system' ),
				array(
					'status'         => 422,
					'hours_required' => (int) $notice_hours,
				)
			);
		}

		return true;
	}

	/**
	 * Normalize a time string to H:i:s.
	 *
	 * @param string $time User-supplied or DB time.
	 * @return string
	 */
	private function normalize_time_his( string $time ): string {
		$time = trim( $time );
		if ( preg_match( '/^(\d{2}):(\d{2}):(\d{2})$/', $time, $m ) ) {
			return sprintf( '%02d:%02d:%02d', (int) $m[1], (int) $m[2], (int) $m[3] );
		}
		if ( preg_match( '/^(\d{2}):(\d{2})$/', $time, $m ) ) {
			return sprintf( '%02d:%02d:00', (int) $m[1], (int) $m[2] );
		}
		return '00:00:00';
	}

	/**
	 * Add minutes to a H:i:s time, returning H:i:s.
	 *
	 * @param string $time_his Start time.
	 * @param int    $minutes  Duration in minutes.
	 * @return string
	 */
	private function add_minutes_to_time_string( string $time_his, int $minutes ): string {
		$tz  = new \DateTimeZone( 'UTC' );
		$dt  = \DateTimeImmutable::createFromFormat( 'H:i:s', $time_his, $tz );
		if ( ! $dt ) {
			return $time_his;
		}
		$end = $dt->add( new \DateInterval( 'PT' . max( 0, $minutes ) . 'M' ) );
		return $end->format( 'H:i:s' );
	}

	/**
	 * Queue a customer email for magic-link cancel/reschedule.
	 *
	 * @param string $email_type magic_link_cancel|magic_link_reschedule.
	 * @param int    $booking_id Booking ID.
	 * @return void
	 */
	private function enqueue_magic_link_email( string $email_type, int $booking_id ): void {
		if ( ! class_exists( 'Bookit_Notification_Dispatcher' ) ) {
			require_once BOOKIT_PLUGIN_DIR . 'includes/notifications/class-bookit-notification-dispatcher.php';
		}

		global $wpdb;
		$row = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT c.email, c.first_name, c.last_name
				FROM {$wpdb->prefix}bookings b
				INNER JOIN {$wpdb->prefix}bookings_customers c ON b.customer_id = c.id
				WHERE b.id = %d",
				$booking_id
			),
			ARRAY_A
		);

		if ( ! $row || empty( $row['email'] ) ) {
			return;
		}

		$recipient = array(
			'email' => sanitize_email( $row['email'] ),
			'name'  => trim( (string) ( $row['first_name'] ?? '' ) . ' ' . (string) ( $row['last_name'] ?? '' ) ),
		);

		if ( 'magic_link_cancel' === $email_type ) {
			$subject   = __( 'Booking cancelled', 'bookit-booking-system' );
			$html_body = '<p>' . __( 'Your booking has been cancelled.', 'bookit-booking-system' ) . '</p>';
		} else {
			// Build a full HTML body to match the confirmation email (Sprint 6C hotfix).
			$subject   = __( 'Booking rescheduled', 'bookit-booking-system' );
			$html_body = '<p>' . __( 'Your booking has been rescheduled.', 'bookit-booking-system' ) . '</p>';

			// Load email sender if needed.
			if ( ! class_exists( 'Booking_System_Email_Sender' ) ) {
				require_once BOOKIT_PLUGIN_DIR . 'includes/email/class-email-sender.php';
			}

			// Fetch full booking details for the rich email template.
			$booking = $wpdb->get_row(
				$wpdb->prepare(
					"SELECT
						b.*,
						c.first_name AS customer_first_name,
						c.last_name  AS customer_last_name,
						c.email      AS customer_email,
						c.phone      AS customer_phone,
						s.name       AS service_name,
						s.duration,
						st.first_name AS staff_first_name,
						st.last_name  AS staff_last_name
					FROM {$wpdb->prefix}bookings b
					INNER JOIN {$wpdb->prefix}bookings_customers c ON b.customer_id = c.id
					INNER JOIN {$wpdb->prefix}bookings_services s ON b.service_id = s.id
					INNER JOIN {$wpdb->prefix}bookings_staff st ON b.staff_id = st.id
					WHERE b.id = %d",
					$booking_id
				),
				ARRAY_A
			);

			if ( is_array( $booking ) && ! empty( $booking ) ) {
				// Add composite name fields expected by the email sender.
				$booking['customer_name'] = (string) ( $booking['customer_first_name'] ?? '' ) . ' ' . (string) ( $booking['customer_last_name'] ?? '' );
				$booking['staff_name']    = (string) ( $booking['staff_first_name'] ?? '' ) . ' ' . (string) ( $booking['staff_last_name'] ?? '' );

				$email_sender = new Booking_System_Email_Sender();
				$html_body    = $email_sender->generate_customer_email( $booking );
				$subject      = sprintf(
					__( 'Booking Rescheduled — %s', 'bookit-booking-system' ),
					(string) ( $booking['service_name'] ?? '' )
				);
			}
		}

		Bookit_Notification_Dispatcher::enqueue_email(
			$email_type,
			$recipient,
			$subject,
			$html_body,
			$booking_id,
			array()
		);
	}
}
