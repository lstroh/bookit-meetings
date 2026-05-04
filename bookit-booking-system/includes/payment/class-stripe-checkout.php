<?php
/**
 * Stripe Checkout Session Handler
 * Creates Stripe Checkout Sessions for booking payments.
 *
 * @package    Bookit_Booking_System
 * @subpackage Bookit_Booking_System/includes/payment
 */

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
	die;
}

/**
 * Stripe Checkout Session handler class.
 */
class Booking_System_Stripe_Checkout {

	/**
	 * Idempotency handler instance.
	 *
	 * @var Booking_System_Idempotency_Handler|null
	 */
	private $idempotency_handler = null;

	/**
	 * Current idempotency key for the operation.
	 *
	 * @var string|null
	 */
	private $current_idempotency_key = null;

	/**
	 * Create Stripe Checkout Session
	 *
	 * Uses idempotency to prevent duplicate checkout sessions.
	 * If the same session data is submitted twice, returns the cached session ID.
	 *
	 * @param array<string, mixed> $session_data Booking wizard session data.
	 * @return string|array{session_id: string, redirect_url: string}|\WP_Error Session ID, or V2 array with redirect_url, or error.
	 */
	public function create_checkout_session( $session_data ) {
		// Initialize idempotency handler.
		$idempotency_result = $this->init_idempotency( $session_data );
		if ( is_wp_error( $idempotency_result ) ) {
			return $idempotency_result;
		}

		// If we got a cached session ID (or V2 session + URL), return it immediately.
		if ( is_string( $idempotency_result ) && ! empty( $idempotency_result ) ) {
			return $idempotency_result;
		}
		if ( is_array( $idempotency_result ) && ! empty( $idempotency_result['session_id'] ) && ! empty( $idempotency_result['redirect_url'] ) ) {
			return $idempotency_result;
		}

		$validation = $this->validate_session_data( $session_data );
		if ( is_wp_error( $validation ) ) {
			$this->fail_idempotency_operation( $validation->get_error_message() );
			return $validation;
		}

		$service = $this->get_service( isset( $session_data['service_id'] ) ? (int) $session_data['service_id'] : 0 );
		if ( ! $service ) {
			$error = new WP_Error( 'missing_service', __( 'Service not found', 'bookit-booking-system' ) );
			$this->fail_idempotency_operation( $error->get_error_message() );
			return $error;
		}

		$staff = $this->get_staff( isset( $session_data['staff_id'] ) ? (int) $session_data['staff_id'] : 0 );
		if ( ! $staff ) {
			$error = new WP_Error( 'missing_staff', __( 'Staff member not found', 'bookit-booking-system' ) );
			$this->fail_idempotency_operation( $error->get_error_message() );
			return $error;
		}

		$deposit_amount = $this->calculate_deposit( $service );
		if ( is_wp_error( $deposit_amount ) ) {
			$this->fail_idempotency_operation( $deposit_amount->get_error_message() );
			return $deposit_amount;
		}
		if ( $deposit_amount <= 0 ) {
			$error = new WP_Error( 'invalid_amount', __( 'Deposit amount must be greater than zero', 'bookit-booking-system' ) );
			$this->fail_idempotency_operation( $error->get_error_message() );
			return $error;
		}

		$stripe_config = new Bookit_Stripe_Config();
		$secret_key    = $stripe_config->get_secret_key();
		if ( empty( $secret_key ) ) {
			$error = new WP_Error( 'missing_api_key', __( 'Stripe API key not configured', 'bookit-booking-system' ) );
			$this->fail_idempotency_operation( $error->get_error_message() );
			return $error;
		}

		if ( apply_filters( 'bookit_stripe_api_mode', 'live' ) === 'mock' ) {
			$mock_result = apply_filters( 'bookit_mock_stripe_session', $session_data );
			$is_v2       = isset( $session_data['wizard_version'] ) && 'v2' === $session_data['wizard_version'];
			if ( is_object( $mock_result ) && isset( $mock_result->id ) ) {
				$redirect_for_cache = isset( $mock_result->url ) ? (string) $mock_result->url : '';
				// Complete idempotency for successful mock.
				$idempotency_payload = array(
					'session_id'   => $mock_result->id,
					'amount_total' => $mock_result->amount_total ?? 0,
					'currency'     => $mock_result->currency ?? 'gbp',
					'created_at'   => gmdate( 'Y-m-d H:i:s' ),
					'mock'         => true,
				);
				if ( '' !== $redirect_for_cache ) {
					$idempotency_payload['redirect_url'] = $redirect_for_cache;
				}
				$this->complete_idempotency_operation( $idempotency_payload );
				if ( $is_v2 && '' !== $redirect_for_cache ) {
					return array(
						'session_id'   => $mock_result->id,
						'redirect_url' => $redirect_for_cache,
					);
				}
				return $mock_result->id;
			}
			if ( is_string( $mock_result ) && ! empty( $mock_result ) ) {
				// Complete idempotency for string session ID.
				$this->complete_idempotency_operation(
					array(
						'session_id' => $mock_result,
						'created_at' => gmdate( 'Y-m-d H:i:s' ),
						'mock'       => true,
					)
				);
				return $mock_result;
			}
			// Mock failed.
			$error = new WP_Error( 'mock_error', __( 'Mock session failed', 'bookit-booking-system' ) );
			$this->fail_idempotency_operation( $error->get_error_message() );
			return $error;
		}

		if ( ! class_exists( '\Stripe\Stripe' ) ) {
			$autoload = dirname( dirname( __DIR__ ) ) . '/vendor/autoload.php';
			if ( file_exists( $autoload ) ) {
				require_once $autoload;
			}
		}
		\Stripe\Stripe::setApiKey( $secret_key );

		$params = $this->build_session_params( $session_data, $service, $staff, $deposit_amount );

		$is_v2 = isset( $session_data['wizard_version'] ) && 'v2' === $session_data['wizard_version'];

		try {
			$checkout_session = \Stripe\Checkout\Session::create( $params );
			$session_id       = $checkout_session->id;

			// Mark idempotency operation as completed with session details.
			$response_payload = array(
				'session_id'   => $session_id,
				'amount_total' => $checkout_session->amount_total,
				'currency'     => $checkout_session->currency,
				'created_at'   => gmdate( 'Y-m-d H:i:s' ),
			);
			if ( $is_v2 && ! empty( $checkout_session->url ) ) {
				$response_payload['redirect_url'] = $checkout_session->url;
			}
			$this->complete_idempotency_operation( $response_payload );

			if ( $is_v2 ) {
				$url = isset( $checkout_session->url ) ? (string) $checkout_session->url : '';
				if ( '' !== $url ) {
					return array(
						'session_id'   => $session_id,
						'redirect_url' => $url,
					);
				}
			}

			return $session_id;
		} catch ( \Exception $e ) {
			$error_message = $e->getMessage();

			if ( function_exists( 'error_log' ) ) {
				error_log( 'Stripe Checkout Session Error: ' . $error_message );
			}

			// Mark idempotency operation as failed (allows retry).
			$this->fail_idempotency_operation( $error_message );

			return new WP_Error( 'stripe_error', __( 'Unable to create checkout session: ', 'bookit-booking-system' ) . $error_message );
		}
	}

	/**
	 * Create Stripe Checkout Session for purchasing a package (V2 wizard buy_{package_type_id}).
	 *
	 * @param array<string, mixed> $package_type Row from wp_bookings_package_types.
	 * @param float                $charge_amount Amount to charge in pounds (GBP).
	 * @param array<string, mixed> $session_data Wizard session (booking + customer fields for metadata).
	 * @return \Stripe\Checkout\Session|\WP_Error
	 */
	public function create_package_checkout_session( array $package_type, float $charge_amount, array $session_data ) {
		$validation = $this->validate_session_data( $session_data );
		if ( is_wp_error( $validation ) ) {
			return $validation;
		}

		$stripe_config = new Bookit_Stripe_Config();
		$secret_key    = $stripe_config->get_secret_key();
		if ( empty( $secret_key ) ) {
			return new WP_Error( 'missing_api_key', __( 'Stripe API key not configured', 'bookit-booking-system' ) );
		}

		if ( apply_filters( 'bookit_stripe_api_mode', 'live' ) === 'mock' ) {
			$mock_result = apply_filters( 'bookit_mock_stripe_package_session', null, $package_type, $charge_amount, $session_data );
			if ( null === $mock_result ) {
				$mock_result = apply_filters( 'bookit_mock_stripe_session', $session_data );
			}
			if ( is_object( $mock_result ) && isset( $mock_result->id ) ) {
				return $mock_result;
			}
			if ( is_string( $mock_result ) && ! empty( $mock_result ) ) {
				return (object) array(
					'id'  => $mock_result,
					'url' => 'https://checkout.stripe.com/c/pay/' . $mock_result,
				);
			}
			return new WP_Error( 'mock_error', __( 'Mock package session failed', 'bookit-booking-system' ) );
		}

		if ( ! class_exists( '\Stripe\Stripe' ) ) {
			$autoload = dirname( dirname( __DIR__ ) ) . '/vendor/autoload.php';
			if ( file_exists( $autoload ) ) {
				require_once $autoload;
			}
		}
		\Stripe\Stripe::setApiKey( $secret_key );

		$metadata = array(
			'flow_type'           => 'package',
			'package_type_id'     => (string) $package_type['id'],
			'package_name'        => isset( $package_type['name'] ) ? (string) $package_type['name'] : '',
			'sessions_total'      => (string) (int) ( $package_type['sessions_count'] ?? 0 ),
			'expiry_enabled'      => isset( $package_type['expiry_enabled'] ) ? (string) (int) $package_type['expiry_enabled'] : '0',
			'expiry_days'         => isset( $package_type['expiry_days'] ) && null !== $package_type['expiry_days'] ? (string) (int) $package_type['expiry_days'] : '',
			'service_id'          => (string) $session_data['service_id'],
			'staff_id'            => (string) $session_data['staff_id'],
			'booking_date'        => (string) $session_data['date'],
			'booking_time'        => (string) $session_data['time'],
			'customer_email'      => (string) $session_data['customer_email'],
			'customer_first_name' => (string) $session_data['customer_first_name'],
			'customer_last_name'  => (string) $session_data['customer_last_name'],
			'customer_phone'      => isset( $session_data['customer_phone'] ) ? (string) $session_data['customer_phone'] : '',
			'cooling_off_waiver'  => isset( $session_data['cooling_off_waiver'] ) ? (string) absint( $session_data['cooling_off_waiver'] ) : '0',
			'wizard_version'      => 'v2',
		);
		if ( ! empty( $session_data['customer_special_requests'] ) ) {
			$metadata['special_requests'] = substr( (string) $session_data['customer_special_requests'], 0, 500 );
		}

		$unit_amount_pence = (int) round( $charge_amount * 100 );

		$line_items = array(
			array(
				'price_data' => array(
					'currency'     => 'gbp',
					'product_data' => array(
						'name' => isset( $package_type['name'] ) ? (string) $package_type['name'] : __( 'Package', 'bookit-booking-system' ),
					),
					'unit_amount'  => $unit_amount_pence,
				),
				'quantity'   => 1,
			),
		);

		$v2_base     = trailingslashit(
			get_option( 'bookit_confirmed_v2_url', home_url( '/booking-confirmed-v2/' ) )
		);
		$success_url = $v2_base . '?session_id={CHECKOUT_SESSION_ID}';
		$cancel_url  = home_url( '/book-v2/' );

		$params = array(
			'payment_method_types' => array( 'card' ),
			'line_items'           => $line_items,
			'mode'                 => 'payment',
			'success_url'          => $success_url,
			'cancel_url'           => $cancel_url,
			'customer_email'       => $session_data['customer_email'],
			'metadata'             => $metadata,
		);

		return \Stripe\Checkout\Session::create( $params );
	}

	/**
	 * Initialize idempotency tracking for checkout session creation.
	 *
	 * @param array<string, mixed> $session_data Session data.
	 * @return string|true|\WP_Error Cached session ID, true to continue, or error.
	 */
	private function init_idempotency( $session_data ) {
		// Load idempotency handler if not already loaded.
		$handler_file = dirname( __DIR__ ) . '/core/class-idempotency-handler.php';
		if ( ! class_exists( 'Booking_System_Idempotency_Handler' ) && file_exists( $handler_file ) ) {
			require_once $handler_file;
		}

		// Skip idempotency if handler not available (graceful degradation).
		if ( ! class_exists( 'Booking_System_Idempotency_Handler' ) ) {
			if ( function_exists( 'error_log' ) ) {
				error_log( 'Stripe Checkout: Idempotency handler not available, proceeding without idempotency' );
			}
			return true;
		}

		$this->idempotency_handler = new Booking_System_Idempotency_Handler();

		// Generate idempotency key from session data.
		// Key is based on: service, staff, date, time, customer email (core booking identity).
		$key_data = array(
			'service_id'     => $session_data['service_id'] ?? '',
			'staff_id'       => $session_data['staff_id'] ?? '',
			'date'           => $session_data['date'] ?? '',
			'time'           => $session_data['time'] ?? '',
			'customer_email' => $session_data['customer_email'] ?? '',
		);
		$this->current_idempotency_key = 'stripe_checkout_' . hash( 'sha256', wp_json_encode( $key_data ) );

		// Start idempotency operation (or get existing).
		$operation = $this->idempotency_handler->start_operation(
			'stripe_checkout',
			$this->current_idempotency_key,
			$session_data
		);

		if ( is_wp_error( $operation ) ) {
			// Log but don't fail - allow checkout to proceed without idempotency.
			if ( function_exists( 'error_log' ) ) {
				error_log( 'Stripe Checkout: Idempotency error - ' . $operation->get_error_message() );
			}

			// If it's a data mismatch, that's a real error - return it.
			if ( 'idempotency_data_mismatch' === $operation->get_error_code() ) {
				return $operation;
			}

			// For other errors, proceed without idempotency.
			$this->idempotency_handler     = null;
			$this->current_idempotency_key = null;
			return true;
		}

		// If operation already completed, return cached session ID (or V2 session + redirect URL).
		if ( 'completed' === $operation['status'] && ! empty( $operation['response_data'] ) ) {
			$cached_data = json_decode( $operation['response_data'], true );
			if ( ! empty( $cached_data['session_id'] ) ) {
				if ( function_exists( 'error_log' ) ) {
					error_log( 'Stripe Checkout: Returning cached session ID ' . $cached_data['session_id'] );
				}
				$is_v2 = isset( $session_data['wizard_version'] ) && 'v2' === $session_data['wizard_version'];
				if ( $is_v2 && ! empty( $cached_data['redirect_url'] ) ) {
					return array(
						'session_id'   => $cached_data['session_id'],
						'redirect_url' => $cached_data['redirect_url'],
					);
				}
				return $cached_data['session_id'];
			}
		}

		// Continue with checkout session creation.
		return true;
	}

	/**
	 * Mark idempotency operation as completed.
	 *
	 * @param array<string, mixed> $response_data Response data to cache.
	 * @return void
	 */
	private function complete_idempotency_operation( $response_data ) {
		if ( null === $this->idempotency_handler || null === $this->current_idempotency_key ) {
			return;
		}

		$this->idempotency_handler->complete_operation( $this->current_idempotency_key, $response_data );

		// Clear state.
		$this->idempotency_handler     = null;
		$this->current_idempotency_key = null;
	}

	/**
	 * Mark idempotency operation as failed.
	 *
	 * @param string $error_message Error message.
	 * @return void
	 */
	private function fail_idempotency_operation( $error_message ) {
		if ( null === $this->idempotency_handler || null === $this->current_idempotency_key ) {
			return;
		}

		$this->idempotency_handler->fail_operation( $this->current_idempotency_key, $error_message );

		// Clear state.
		$this->idempotency_handler     = null;
		$this->current_idempotency_key = null;
	}

	/**
	 * Validate session data (required fields and email).
	 *
	 * @param array<string, mixed> $session_data Session data.
	 * @return true|\WP_Error
	 */
	private function validate_session_data( $session_data ) {
		$required_fields = array(
			'service_id',
			'staff_id',
			'date',
			'time',
			'customer_email',
			'customer_first_name',
			'customer_last_name',
		);

		foreach ( $required_fields as $field ) {
			if ( ! isset( $session_data[ $field ] ) || (string) $session_data[ $field ] === '' ) {
				if ( $field === 'service_id' ) {
					return new WP_Error( 'missing_service', __( 'Service not found', 'bookit-booking-system' ) );
				}
				if ( $field === 'staff_id' ) {
					return new WP_Error( 'missing_staff', __( 'Staff member not found', 'bookit-booking-system' ) );
				}
				return new WP_Error( 'missing_field', sprintf( __( 'Missing required field: %s', 'bookit-booking-system' ), $field ) );
			}
		}

		if ( ! is_email( $session_data['customer_email'] ) ) {
			return new WP_Error( 'invalid_email', __( 'Invalid email address', 'bookit-booking-system' ) );
		}

		$booking_date = isset( $session_data['date'] ) ? (string) $session_data['date'] : '';
		$waiver       = isset( $session_data['cooling_off_waiver'] ) ? absint( $session_data['cooling_off_waiver'] ) : 0;
		if ( bookit_booking_requires_waiver( $booking_date ) && 1 !== $waiver ) {
			return new WP_Error(
				'cooling_off_waiver_required',
				__( 'Cooling-off waiver is required for bookings within 14 days.', 'bookit-booking-system' )
			);
		}

		return true;
	}

	/**
	 * Get service from database.
	 *
	 * @param int $service_id Service ID.
	 * @return array<string, mixed>|false Service row or false.
	 */
	private function get_service( $service_id ) {
		if ( $service_id <= 0 ) {
			return false;
		}
		global $wpdb;
		$service = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$wpdb->prefix}bookings_services WHERE id = %d",
				$service_id
			),
			ARRAY_A
		);
		return $service ?: false;
	}

	/**
	 * Get staff member from database.
	 *
	 * @param int $staff_id Staff ID.
	 * @return array<string, mixed>|false Staff row or false.
	 */
	private function get_staff( $staff_id ) {
		if ( $staff_id <= 0 ) {
			return false;
		}
		global $wpdb;
		$staff = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$wpdb->prefix}bookings_staff WHERE id = %d",
				$staff_id
			),
			ARRAY_A
		);
		return $staff ?: false;
	}

	/**
	 * Calculate deposit amount based on service settings
	 *
	 * @param array $service Service data from database
	 * @return float|\WP_Error Deposit amount in pounds or error
	 */
	public function calculate_deposit( $service ) {
		// Validate service price
		$price = floatval( $service['price'] ?? 0 );

		if ( $price <= 0 ) {
			return new WP_Error( 'invalid_price', 'Service price must be greater than zero' );
		}

		$deposit_type  = $service['deposit_type'] ?? null;
		$deposit_amount = $service['deposit_amount'] ?? null;

		// If no deposit configuration, default to full payment
		if ( empty( $deposit_type ) || is_null( $deposit_amount ) ) {
			return $price;
		}

		switch ( $deposit_type ) {
			case 'percentage':
				$percentage = floatval( $deposit_amount );

				// Validate percentage range (0-100)
				if ( $percentage < 0 || $percentage > 100 ) {
					if ( apply_filters( 'bookit_log_deposit_edge_cases', true ) ) {
						error_log( "Invalid deposit percentage: {$percentage}. Using 100%." );
					}
					$percentage = 100;
				}

				$deposit = ( $price * $percentage ) / 100;

				// Round to 2 decimal places
				return round( $deposit, 2 );

			case 'fixed':
				$fixed = floatval( $deposit_amount );

				// Validate fixed amount is positive
				if ( $fixed < 0 ) {
					if ( apply_filters( 'bookit_log_deposit_edge_cases', true ) ) {
						error_log( "Invalid fixed deposit: {$fixed}. Using full price." );
					}
					return $price;
				}

				// Don't exceed service price
				$deposit = min( $fixed, $price );

				// Round to 2 decimal places
				return round( $deposit, 2 );

			default:
				// Unknown deposit type - log and use full payment
				if ( apply_filters( 'bookit_log_deposit_edge_cases', true ) ) {
					error_log( "Unknown deposit type: {$deposit_type}. Using full payment." );
				}
				return $price;
		}
	}

	/**
	 * Build Stripe Checkout Session parameters.
	 *
	 * @param array<string, mixed> $session_data  Session data.
	 * @param array<string, mixed> $service       Service row.
	 * @param array<string, mixed> $staff         Staff row.
	 * @param float                $deposit_amount Deposit in pounds.
	 * @return array<string, mixed> Stripe session parameters.
	 */
	private function build_session_params( $session_data, $service, $staff, $deposit_amount ) {
		$date_formatted = ! empty( $session_data['date'] ) ? gmdate( 'd/m/Y', strtotime( $session_data['date'] ) ) : '';
		$time_formatted = ! empty( $session_data['time'] ) ? gmdate( 'g:i A', strtotime( $session_data['time'] ) ) : ( $session_data['time'] ?? '' );
		$staff_first   = $staff['first_name'] ?? '';
		$staff_last    = $staff['last_name'] ?? '';
		$service_name  = $service['name'] ?? '';

		$description = sprintf(
			/* translators: 1: staff first name, 2: staff last name, 3: date, 4: time */
			__( 'with %1$s %2$s on %3$s at %4$s', 'bookit-booking-system' ),
			$staff_first,
			$staff_last,
			$date_formatted,
			$time_formatted
		);

		$metadata = array(
			'booking_temp_id'       => function_exists( 'wp_generate_uuid4' ) ? wp_generate_uuid4() : 'temp-' . uniqid(),
			'service_id'            => (string) $session_data['service_id'],
			'staff_id'              => (string) $session_data['staff_id'],
			'booking_date'          => $session_data['date'],
			'booking_time'          => $session_data['time'],
			'customer_first_name'   => $session_data['customer_first_name'],
			'customer_last_name'    => $session_data['customer_last_name'],
			'customer_email'        => $session_data['customer_email'],
			'customer_phone'        => isset( $session_data['customer_phone'] ) ? $session_data['customer_phone'] : '',
			'cooling_off_waiver'    => isset( $session_data['cooling_off_waiver'] ) ? (string) absint( $session_data['cooling_off_waiver'] ) : '0',
		);
		if ( ! empty( $session_data['customer_special_requests'] ) ) {
			$metadata['special_requests'] = substr( $session_data['customer_special_requests'], 0, 500 );
		}

		$unit_amount_pence = (int) round( $deposit_amount * 100 );

		$line_items = array(
			array(
				'price_data' => array(
					'currency'     => 'gbp',
					'product_data' => array(
						'name'        => $service_name,
						'description' => $description,
					),
					'unit_amount'  => $unit_amount_pence,
				),
				'quantity'   => 1,
			),
		);

		$success_url = home_url( '/booking-confirmed?session_id={CHECKOUT_SESSION_ID}' );
		$cancel_url  = home_url( '/book?step=5&cancelled=1' );
		if ( isset( $session_data['wizard_version'] ) && 'v2' === $session_data['wizard_version'] ) {
			$v2_base = trailingslashit(
				get_option( 'bookit_confirmed_v2_url', home_url( '/booking-confirmed-v2/' ) )
			);
			$success_url = $v2_base . '?session_id={CHECKOUT_SESSION_ID}';
			$cancel_url  = home_url( '/book-v2/' );
			$metadata['flow_type']       = 'booking';
			$metadata['wizard_version'] = 'v2';
		}

		return array(
			'payment_method_types' => array( 'card' ),
			'line_items'          => $line_items,
			'mode'                => 'payment',
			'success_url'         => $success_url,
			'cancel_url'          => $cancel_url,
			'customer_email'     => $session_data['customer_email'],
			'metadata'            => $metadata,
		);
	}
}
