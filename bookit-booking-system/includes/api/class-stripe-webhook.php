<?php
/**
 * Stripe Webhook Handler
 * Receives and processes Stripe webhook events
 *
 * @package    Bookit_Booking_System
 * @subpackage Bookit_Booking_System/includes/api
 */

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
	die;
}

/**
 * Stripe Webhook Handler class.
 */
class Booking_System_Stripe_Webhook {

	/**
	 * Whether to write messages to error_log (disabled during unit tests).
	 *
	 * @return bool
	 */
	private static function should_log() {
		return ! defined( 'WP_TESTS_TABLE_PREFIX' ) && function_exists( 'error_log' );
	}

	/**
	 * Constructor - Register REST API routes
	 */
	public function __construct() {
		add_action( 'rest_api_init', array( $this, 'register_routes' ) );
	}

	/**
	 * Register webhook REST API endpoint
	 */
	public function register_routes() {
		register_rest_route(
			'bookit/v1',
			'/stripe/webhook',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'handle_webhook' ),
				'permission_callback' => '__return_true', // Webhook is public, verified by signature.
			)
		);
	}

	/**
	 * Handle incoming webhook request
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response|WP_Error
	 */
	public function handle_webhook( $request ) {
		$payload   = $request->get_body();
		$signature = $request->get_header( 'Stripe-Signature' );
		if ( empty( $signature ) ) {
			$signature = $request->get_header( 'stripe_signature' );
		}

		if ( empty( $signature ) ) {
			if ( self::should_log() ) {
				error_log( 'Stripe Webhook: Missing signature header' );
			}
			return new WP_Error(
				'missing_signature',
				'Missing Stripe signature',
				array( 'status' => 400 )
			);
		}

		$event = $this->verify_webhook_signature( $payload, $signature );

		if ( is_wp_error( $event ) ) {
			if ( self::should_log() ) {
				error_log( 'Stripe Webhook: Invalid signature - ' . $event->get_error_message() );
			}
			return $event;
		}

		if ( self::should_log() ) {
			error_log(
				sprintf(
					'Stripe Webhook: Received event %s (type: %s)',
					$event->id,
					$event->type
				)
			);
		}

		$result = $this->process_event( $event );

		if ( is_wp_error( $result ) ) {
			if ( self::should_log() ) {
				error_log( 'Stripe Webhook: Processing failed - ' . $result->get_error_message() );
			}
			return new WP_REST_Response(
				array(
					'received' => true,
					'error'    => $result->get_error_message(),
				),
				200
			);
		}

		return new WP_REST_Response(
			array(
				'received'   => true,
				'processed'  => true,
			),
			200
		);
	}

	/**
	 * Verify Stripe webhook signature
	 *
	 * @param string $payload   Raw request body.
	 * @param string $signature Stripe-Signature header value.
	 * @return object|WP_Error Stripe event object or error.
	 */
	private function verify_webhook_signature( $payload, $signature ) {
		$webhook_secret = Bookit_Stripe_Config::get_webhook_secret();

		if ( empty( $webhook_secret ) ) {
			return new WP_Error(
				'missing_webhook_secret',
				'Webhook secret not configured',
				array( 'status' => 500 )
			);
		}

		$bypass = apply_filters( 'bookit_verify_stripe_signature', null, $payload, $signature );

		if ( $bypass === false ) {
			return new WP_Error(
				'invalid_signature',
				'Invalid webhook signature',
				array( 'status' => 400 )
			);
		}

		if ( $bypass === true ) {
			$event_data = json_decode( $payload );
			if ( ! $event_data ) {
				return new WP_Error(
					'invalid_payload',
					'Invalid webhook payload',
					array( 'status' => 400 )
				);
			}
			$data_object = isset( $event_data->data->object ) ? (object) (array) $event_data->data->object : (object) array();
			return (object) array(
				'id'   => $event_data->id ?? 'test_event',
				'type' => $event_data->type ?? 'unknown',
				'data' => (object) array( 'object' => $data_object ),
			);
		}

		try {
			\Stripe\Stripe::setApiKey( Bookit_Stripe_Config::get_secret_key() );
			$event = \Stripe\Webhook::constructEvent(
				$payload,
				$signature,
				$webhook_secret
			);
			return $event;
		} catch ( \UnexpectedValueException $e ) {
			return new WP_Error(
				'invalid_payload',
				'Invalid webhook payload: ' . $e->getMessage(),
				array( 'status' => 400 )
			);
		} catch ( \Stripe\Exception\SignatureVerificationException $e ) {
			return new WP_Error(
				'invalid_signature',
				'Invalid webhook signature: ' . $e->getMessage(),
				array( 'status' => 400 )
			);
		}
	}

	/**
	 * Process webhook event based on type
	 *
	 * @param object $event Stripe event object.
	 * @return bool|WP_Error True on success, WP_Error on failure.
	 */
	private function process_event( $event ) {
		switch ( $event->type ) {
			case 'checkout.session.completed':
				return $this->handle_checkout_session_completed( $event );

			case 'charge.refunded':
				return $this->handle_charge_refunded( $event );

			case 'payment_intent.succeeded':
				if ( self::should_log() ) {
					error_log( 'Stripe Webhook: payment_intent.succeeded received (no action needed)' );
				}
				return true;

			case 'payment_intent.payment_failed':
				if ( self::should_log() ) {
					error_log( 'Stripe Webhook: payment_intent.payment_failed received' );
				}
				return true;

			default:
				if ( self::should_log() ) {
					error_log( 'Stripe Webhook: Unhandled event type: ' . $event->type );
				}
				return true;
		}
	}

	/**
	 * Route checkout.session.completed by metadata flow_type (booking vs package purchase).
	 *
	 * @param object $event Stripe event.
	 * @return bool|WP_Error
	 */
	private function handle_checkout_session_completed( $event ) {
		$session = $event->data->object;

		if ( $session->payment_status !== 'paid' ) {
			if ( self::should_log() ) {
				error_log(
					sprintf(
						'Stripe Webhook: Checkout session %s not paid (status: %s)',
						$session->id,
						$session->payment_status
					)
				);
			}
			return true;
		}

		$metadata = ( isset( $session->metadata ) && $session->metadata instanceof \Stripe\StripeObject )
			? $session->metadata->toArray()
			: (array) $session->metadata;

		$flow_type = isset( $metadata['flow_type'] ) ? (string) $metadata['flow_type'] : '';

		if ( 'package' === $flow_type ) {
			return $this->handle_package_purchase_completed( $event );
		}

		return $this->handle_booking_checkout_completed( $event );
	}

	/**
	 * Handle checkout.session.completed for a standard (booking) Stripe Checkout session.
	 *
	 * @param object $event Stripe event.
	 * @return bool|WP_Error
	 */
	private function handle_booking_checkout_completed( $event ) {
		$session = $event->data->object;

		$idempotency_key = 'stripe_webhook_' . $session->id;
		$existing        = get_transient( $idempotency_key );

		if ( $existing ) {
			if ( self::should_log() ) {
				error_log( 'Stripe Webhook: Duplicate webhook detected for session ' . $session->id );
			}
			return true;
		}

		$metadata = ( isset( $session->metadata ) && $session->metadata instanceof \Stripe\StripeObject )
			? $session->metadata->toArray()
			: (array) $session->metadata;

		$required_fields = array(
			'service_id',
			'staff_id',
			'booking_date',
			'booking_time',
			'customer_email',
			'customer_first_name',
			'customer_last_name',
		);

		foreach ( $required_fields as $field ) {
			if ( empty( $metadata[ $field ] ) ) {
				return new WP_Error(
					'missing_metadata',
					sprintf( 'Missing required metadata field: %s', $field )
				);
			}
		}

		$booking_creator = new Booking_System_Booking_Creator();

		$booking_data = array(
			'service_id'           => (int) $metadata['service_id'],
			'staff_id'             => (int) $metadata['staff_id'],
			'booking_date'         => $metadata['booking_date'],
			'booking_time'         => $metadata['booking_time'],
			'customer_first_name'  => $metadata['customer_first_name'],
			'customer_last_name'   => $metadata['customer_last_name'],
			'customer_email'       => $metadata['customer_email'],
			'customer_phone'       => $metadata['customer_phone'] ?? '',
			'special_requests'     => $metadata['special_requests'] ?? '',
			'cooling_off_waiver'  => isset( $metadata['cooling_off_waiver'] ) ? absint( $metadata['cooling_off_waiver'] ) : 0,
			'payment_method'       => 'stripe',
			'payment_intent_id'    => $session->payment_intent ?? '',
			'stripe_session_id'    => $session->id,
			'amount_paid'          => isset( $session->amount_total ) ? $session->amount_total / 100 : 0,
		);

		// Allow extensions to modify wizard booking data before insertion.
		$booking_data = apply_filters( 'bookit_booking_data_before_insert', $booking_data );

		$booking_id = $booking_creator->create_booking( $booking_data );

		if ( is_wp_error( $booking_id ) ) {
			return $booking_id;
		}

		// Notify extensions after a booking is created from Stripe checkout completion.
		do_action( 'bookit_after_booking_created', (int) $booking_id, $booking_data );

		$payment_data = array(
			'amount'            => isset( $session->amount_total ) ? (float) $session->amount_total / 100 : 0,
			'currency'          => isset( $session->currency ) ? sanitize_text_field( $session->currency ) : '',
			'payment_intent_id' => isset( $session->payment_intent ) ? sanitize_text_field( (string) $session->payment_intent ) : '',
			'method'            => 'stripe_checkout',
		);

		// Notify extensions after a payment-backed booking is completed.
		do_action( 'bookit_after_payment_completed', (int) $booking_id, $payment_data );

		Bookit_Audit_Logger::log(
			'payment.completed',
			'booking',
			(int) $booking_id,
			array(
				'new_value' => $payment_data,
				'notes'     => 'Payment confirmed via webhook',
			)
		);

		set_transient( $idempotency_key, $booking_id, 24 * HOUR_IN_SECONDS );

		$this->send_booking_confirmation_emails_after_webhook( (int) $booking_id );

		if ( self::should_log() ) {
			error_log(
				sprintf(
					'Stripe Webhook: Created booking #%d from session %s',
					$booking_id,
					$session->id
				)
			);
		}

		return true;
	}

	/**
	 * Handle checkout.session.completed when purchasing a package (buy + redeem first session).
	 *
	 * @param object $event Stripe event.
	 * @return bool|WP_Error
	 */
	private function handle_package_purchase_completed( $event ) {
		global $wpdb;

		$session = $event->data->object;

		if ( $session->payment_status !== 'paid' ) {
			return true;
		}

		$idempotency_key = 'stripe_pkg_' . $session->id;
		if ( get_transient( $idempotency_key ) ) {
			if ( self::should_log() ) {
				error_log( 'Stripe Webhook: Duplicate package webhook for session ' . $session->id );
			}
			return true;
		}

		$metadata = ( isset( $session->metadata ) && $session->metadata instanceof \Stripe\StripeObject )
			? $session->metadata->toArray()
			: (array) $session->metadata;

		$required = array(
			'package_type_id',
			'sessions_total',
			'customer_email',
			'customer_first_name',
			'customer_last_name',
			'service_id',
			'staff_id',
			'booking_date',
			'booking_time',
		);
		foreach ( $required as $field ) {
			if ( ! isset( $metadata[ $field ] ) ) {
				return new WP_Error(
					'missing_package_metadata',
					sprintf( 'Missing required package metadata field: %s', $field )
				);
			}
			if ( is_string( $metadata[ $field ] ) && '' === trim( $metadata[ $field ] ) ) {
				return new WP_Error(
					'missing_package_metadata',
					sprintf( 'Missing required package metadata field: %s', $field )
				);
			}
		}

		$sessions_total = (int) $metadata['sessions_total'];
		if ( $sessions_total < 1 ) {
			return new WP_Error(
				'missing_package_metadata',
				'Invalid sessions_total in package metadata'
			);
		}

		if ( (int) $metadata['service_id'] < 1 || (int) $metadata['staff_id'] < 1 || (int) $metadata['package_type_id'] < 1 ) {
			return new WP_Error(
				'missing_package_metadata',
				'Invalid service_id, staff_id, or package_type_id in package metadata'
			);
		}

		$amount_gbp      = isset( $session->amount_total ) ? (float) $session->amount_total / 100 : 0.0;
		$package_type_id = (int) $metadata['package_type_id'];

		$booking_id          = 0;
		$customer_package_id = 0;
		$booking_data        = array();

		$expiry_enabled = isset( $metadata['expiry_enabled'] ) ? (string) $metadata['expiry_enabled'] : '0';
		$expiry_days    = isset( $metadata['expiry_days'] ) && '' !== (string) $metadata['expiry_days'] ? (int) $metadata['expiry_days'] : 0;
		$expires_at     = null;
		if ( '1' === $expiry_enabled && $expiry_days > 0 ) {
			try {
				$dt = new \DateTime( current_time( 'mysql' ), wp_timezone() );
				$dt->modify( '+' . $expiry_days . ' days' );
				$expires_at = $dt->format( 'Y-m-d H:i:s' );
			} catch ( \Exception $e ) {
				$expires_at = null;
			}
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
		$wpdb->query( 'START TRANSACTION' );

		try {
			$customer_id = $this->webhook_find_or_create_customer(
				array(
					'first_name' => $metadata['customer_first_name'],
					'last_name'  => $metadata['customer_last_name'],
					'email'      => $metadata['customer_email'],
					'phone'      => $metadata['customer_phone'] ?? '',
				)
			);
			if ( is_wp_error( $customer_id ) ) {
				$wpdb->query( 'ROLLBACK' );
				return $customer_id;
			}

			$purchased_at = current_time( 'mysql' );
			$now          = current_time( 'mysql' );

			$inserted_pkg = $wpdb->insert(
				$wpdb->prefix . 'bookings_customer_packages',
				array(
					'customer_id'          => $customer_id,
					'package_type_id'      => $package_type_id,
					'sessions_total'       => $sessions_total,
					'sessions_remaining'   => $sessions_total,
					'purchase_price'       => $amount_gbp,
					'purchased_at'         => $purchased_at,
					'expires_at'           => $expires_at,
					'status'               => 'active',
					'payment_method'       => 'stripe',
					'payment_reference'    => $session->id,
					'notes'                => null,
					'created_at'           => $now,
					'updated_at'             => $now,
				),
				array( '%d', '%d', '%d', '%d', '%f', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s' )
			);

			if ( false === $inserted_pkg ) {
				$wpdb->query( 'ROLLBACK' );
				return new WP_Error( 'package_db_error', 'Failed to create customer package' );
			}

			$customer_package_id = (int) $wpdb->insert_id;

			require_once BOOKIT_PLUGIN_DIR . 'includes/booking/class-booking-creator.php';
			$booking_creator = new Booking_System_Booking_Creator();

			$booking_data = array(
				'service_id'           => (int) $metadata['service_id'],
				'staff_id'             => (int) $metadata['staff_id'],
				'booking_date'         => $metadata['booking_date'],
				'booking_time'         => $metadata['booking_time'],
				'customer_first_name'  => $metadata['customer_first_name'],
				'customer_last_name'   => $metadata['customer_last_name'],
				'customer_email'       => $metadata['customer_email'],
				'customer_phone'       => $metadata['customer_phone'] ?? '',
				'special_requests'     => $metadata['special_requests'] ?? '',
				'cooling_off_waiver'  => isset( $metadata['cooling_off_waiver'] ) ? absint( $metadata['cooling_off_waiver'] ) : 0,
				'payment_method'       => 'package_redemption',
				'payment_intent_id'    => isset( $session->payment_intent ) ? (string) $session->payment_intent : '',
				'stripe_session_id'    => $session->id,
				'amount_paid'          => 0,
				'customer_package_id'  => $customer_package_id,
			);
			$booking_data = apply_filters( 'bookit_booking_data_before_insert', $booking_data );

			$booking_id = $booking_creator->create_booking( $booking_data );

			if ( is_wp_error( $booking_id ) ) {
				$wpdb->query( 'ROLLBACK' );
				return $booking_id;
			}

			$booking_id = (int) $booking_id;

			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			$updated = $wpdb->query(
				$wpdb->prepare(
					"UPDATE {$wpdb->prefix}bookings_customer_packages
					SET sessions_remaining = sessions_remaining - 1,
						status = CASE WHEN sessions_remaining <= 1 THEN 'exhausted' ELSE 'active' END,
						updated_at = %s
					WHERE id = %d",
					current_time( 'mysql' ),
					$customer_package_id
				)
			);

			if ( false === $updated || (int) $updated < 1 ) {
				$wpdb->query( 'ROLLBACK' );
				return new WP_Error( 'package_db_error', 'Failed to update package sessions' );
			}

			$redemption_inserted = $wpdb->insert(
				$wpdb->prefix . 'bookings_package_redemptions',
				array(
					'customer_package_id' => $customer_package_id,
					'booking_id'          => $booking_id,
					'redeemed_at'         => current_time( 'mysql' ),
					'redeemed_by'         => 0,
					'notes'               => 'Redeemed at package purchase via Stripe',
					'created_at'          => current_time( 'mysql' ),
				),
				array( '%d', '%d', '%s', '%d', '%s', '%s' )
			);

			if ( false === $redemption_inserted ) {
				$wpdb->query( 'ROLLBACK' );
				return new WP_Error( 'package_db_error', 'Failed to insert package redemption' );
			}

			$pi = isset( $session->payment_intent ) ? sanitize_text_field( (string) $session->payment_intent ) : '';

			$pay_inserted = $wpdb->insert(
				$wpdb->prefix . 'bookings_payments',
				array(
					'booking_id'               => $booking_id,
					'customer_id'              => $customer_id,
					'amount'                   => $amount_gbp,
					'payment_type'             => 'full_payment',
					'payment_method'           => 'stripe',
					'payment_status'           => 'completed',
					'stripe_payment_intent_id' => $pi,
					'transaction_date'         => current_time( 'mysql' ),
					'created_at'               => current_time( 'mysql' ),
					'updated_at'               => current_time( 'mysql' ),
				),
				array( '%d', '%d', '%f', '%s', '%s', '%s', '%s', '%s', '%s', '%s' )
			);

			if ( false === $pay_inserted ) {
				$wpdb->query( 'ROLLBACK' );
				return new WP_Error( 'package_db_error', 'Failed to insert payment row' );
			}

			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
			$wpdb->query( 'COMMIT' );
		} catch ( \Throwable $e ) {
			$wpdb->query( 'ROLLBACK' );
			if ( self::should_log() ) {
				error_log( 'Stripe Webhook package flow: ' . $e->getMessage() );
			}
			return new WP_Error(
				'package_flow_exception',
				$e->getMessage()
			);
		}

		Bookit_Audit_Logger::log(
			'package.purchased',
			'customer_package',
			$customer_package_id,
			array(
				'notes' => 'Package purchased and first session redeemed via Stripe',
			)
		);

		do_action( 'bookit_after_booking_created', $booking_id, $booking_data );

		set_transient( $idempotency_key, $customer_package_id, 24 * HOUR_IN_SECONDS );

		$this->send_booking_confirmation_emails_after_webhook( $booking_id );

		if ( self::should_log() ) {
			error_log(
				sprintf(
					'Stripe Webhook: Package + booking #%d from session %s',
					$booking_id,
					$session->id
				)
			);
		}

		return true;
	}

	/**
	 * Send customer confirmation email after Stripe webhook creates a booking (best-effort; does not block success).
	 *
	 * Business notifications are handled by Bookit_Staff_Notifier on bookit_after_booking_created.
	 *
	 * @param int $booking_id Booking ID.
	 * @return void
	 */
	private function send_booking_confirmation_emails_after_webhook( $booking_id ) {
		$booking_id = (int) $booking_id;

		require_once BOOKIT_PLUGIN_DIR . 'includes/booking/class-booking-retriever.php';
		$booking_retriever = new Booking_System_Booking_Retriever();
		$booking           = $booking_retriever->get_booking_by_id( $booking_id );

		if ( $booking ) {
			$email_sender_file = BOOKIT_PLUGIN_DIR . 'includes/email/class-email-sender.php';
			if ( file_exists( $email_sender_file ) ) {
				require_once $email_sender_file;
				$email_sender = new Booking_System_Email_Sender();

				$customer_result = $email_sender->send_customer_confirmation( $booking );
				if ( is_wp_error( $customer_result ) && self::should_log() ) {
					error_log( 'Stripe Webhook: Failed to send customer email - ' . $customer_result->get_error_message() );
				}

				// Business notification removed Sprint 6A-8 — replaced by Bookit_Staff_Notifier
				// which sends to all admin-role staff via their preference settings.
			}
		} elseif ( self::should_log() ) {
			error_log( 'Stripe Webhook: Could not retrieve booking #' . $booking_id . ' for emails' );
		}
	}

	/**
	 * Handle charge.refunded: persist cumulative refund on booking, optional cancellation, payment row, audit.
	 *
	 * @param object $event Stripe event.
	 * @return bool|WP_Error
	 */
	private function handle_charge_refunded( $event ) {
		global $wpdb;

		$charge = $event->data->object;

		$pi_raw = $charge->payment_intent ?? null;
		if ( is_object( $pi_raw ) && isset( $pi_raw->id ) ) {
			$payment_intent_id = (string) $pi_raw->id;
		} else {
			$payment_intent_id = is_string( $pi_raw ) ? $pi_raw : (string) $pi_raw;
		}

		if ( '' === $payment_intent_id ) {
			if ( self::should_log() ) {
				error_log( 'Stripe Webhook: charge.refunded missing payment_intent; cannot match booking' );
			}
			return true;
		}

		$bookings_table = $wpdb->prefix . 'bookings';
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$booking = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT id, customer_id, total_price, refunded_amount, status
				FROM {$bookings_table}
				WHERE payment_intent_id = %s
				AND deleted_at IS NULL
				LIMIT 1",
				$payment_intent_id
			)
		);

		if ( ! $booking ) {
			if ( self::should_log() ) {
				error_log(
					sprintf(
						'Stripe Webhook: charge.refunded: no booking for PI %s',
						$payment_intent_id
					)
				);
			}
			return true;
		}

		$refund_amount_pence = isset( $charge->amount_refunded ) ? (int) $charge->amount_refunded : 0;
		$refund_amount_gbp     = $refund_amount_pence / 100;

		if ( $refund_amount_gbp <= 0 ) {
			return true;
		}

		$booking_id = (int) $booking->id;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$updated = $wpdb->query(
			$wpdb->prepare(
				"UPDATE {$bookings_table} SET refunded_amount = %f WHERE id = %d",
				$refund_amount_gbp,
				$booking_id
			)
		);

		if ( false === $updated ) {
			if ( self::should_log() ) {
				error_log(
					sprintf(
						'Stripe Webhook: charge.refunded failed to update booking #%d: %s',
						$booking_id,
						$wpdb->last_error
					)
				);
			}
			return true;
		}

		$total_price = (float) $booking->total_price;
		$is_full_refund = round( $refund_amount_gbp, 2 ) >= round( $total_price, 2 );

		if ( $is_full_refund && 'cancelled' !== $booking->status ) {
			// System cancellation from Stripe; bypasses admin status-transition guard.
			$wpdb->update(
				$bookings_table,
				array(
					'status'       => 'cancelled',
					'cancelled_at' => current_time( 'mysql' ),
					'cancelled_by' => '0',
				),
				array( 'id' => $booking_id ),
				array( '%s', '%s', '%s' ),
				array( '%d' )
			);
		}

		$pay_status = $is_full_refund ? 'refunded' : 'partially_refunded';

		$inserted = $wpdb->insert(
			$wpdb->prefix . 'bookings_payments',
			array(
				'booking_id'               => $booking_id,
				'customer_id'              => (int) $booking->customer_id,
				'amount'                   => -$refund_amount_gbp,
				'payment_type'             => 'refund',
				'payment_method'           => 'stripe',
				'payment_status'           => $pay_status,
				'stripe_payment_intent_id' => $payment_intent_id,
				'transaction_date'         => current_time( 'mysql' ),
				'created_at'               => current_time( 'mysql' ),
				'updated_at'               => current_time( 'mysql' ),
			),
			array( '%d', '%d', '%f', '%s', '%s', '%s', '%s', '%s', '%s', '%s' )
		);

		if ( false === $inserted && self::should_log() ) {
			error_log(
				sprintf(
					'Stripe Webhook: charge.refunded failed to insert payment row for booking #%d: %s',
					$booking_id,
					$wpdb->last_error
				)
			);
		}

		Bookit_Audit_Logger::log(
			'booking.refunded',
			'booking',
			$booking_id,
			array(
				'new_value' => array(
					'refunded_amount' => $refund_amount_gbp,
				),
				'notes'     => 'Refund processed via Stripe charge.refunded webhook',
			)
		);

		if ( self::should_log() ) {
			error_log(
				sprintf(
					'Stripe Webhook: charge.refunded booking #%d PI %s amount_refunded=%s (%.2f)',
					$booking_id,
					$payment_intent_id,
					(string) $refund_amount_pence,
					$refund_amount_gbp
				)
			);
		}

		return true;
	}

	/**
	 * Find or create a customer row (same data as Booking_System_Booking_Creator).
	 *
	 * @param array<string, string> $data first_name, last_name, email, phone.
	 * @return int|WP_Error
	 */
	private function webhook_find_or_create_customer( array $data ) {
		global $wpdb;

		$table    = $wpdb->prefix . 'bookings_customers';
		$existing = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$table} WHERE email = %s",
				$data['email']
			)
		);

		if ( $existing ) {
			$wpdb->update(
				$table,
				array(
					'first_name' => $data['first_name'],
					'last_name'  => $data['last_name'],
					'phone'      => $data['phone'],
					'updated_at' => current_time( 'mysql' ),
				),
				array( 'id' => $existing->id ),
				array( '%s', '%s', '%s', '%s' ),
				array( '%d' )
			);
			return (int) $existing->id;
		}

		$inserted = $wpdb->insert(
			$table,
			array(
				'first_name' => $data['first_name'],
				'last_name'  => $data['last_name'],
				'email'      => $data['email'],
				'phone'      => $data['phone'],
				'created_at' => current_time( 'mysql' ),
				'updated_at' => current_time( 'mysql' ),
			),
			array( '%s', '%s', '%s', '%s', '%s', '%s' )
		);

		if ( false === $inserted ) {
			return new WP_Error( 'database_error', 'Failed to create customer' );
		}

		return (int) $wpdb->insert_id;
	}
}

new Booking_System_Stripe_Webhook();
