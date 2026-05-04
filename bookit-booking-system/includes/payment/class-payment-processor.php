<?php
/**
 * Payment Processor
 * Shared booking completion paths (pay on arrival, package redemption) used by the wizard REST API.
 *
 * @package    Bookit_Booking_System
 * @subpackage Bookit_Booking_System/includes/payment
 */

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
	die;
}

/**
 * Payment processor class.
 */
class Booking_System_Payment_Processor {

	/**
	 * Whether to write messages to error_log (disabled during unit tests).
	 *
	 * @return bool
	 */
	private static function should_log() {
		return ! defined( 'WP_TESTS_TABLE_PREFIX' ) && function_exists( 'error_log' );
	}

	/**
	 * Process Pay on Arrival booking.
	 *
	 * Creates booking immediately without calling any external payment gateway.
	 * Sets deposit_paid = 0, balance_due = full price, status = pending_payment.
	 *
	 * @param array<string, mixed> $session_data Booking wizard session data.
	 * @return array{success: bool, booking_id: int, redirect_url: string}|WP_Error
	 */
	public function process_pay_on_arrival( $session_data ) {
		// Validate session data exists.
		if ( empty( $session_data ) ) {
			return new WP_Error( 'invalid_session', __( 'No booking data found', 'bookit-booking-system' ) );
		}

		// Map wizard session fields to booking creator fields.
		$booking_data = array(
			'service_id'          => isset( $session_data['service_id'] ) ? $session_data['service_id'] : '',
			'staff_id'            => isset( $session_data['staff_id'] ) ? $session_data['staff_id'] : '',
			'booking_date'        => isset( $session_data['date'] ) ? $session_data['date'] : '',
			'booking_time'        => isset( $session_data['time'] ) ? $session_data['time'] : '',
			'customer_first_name' => isset( $session_data['customer_first_name'] ) ? $session_data['customer_first_name'] : '',
			'customer_last_name'  => isset( $session_data['customer_last_name'] ) ? $session_data['customer_last_name'] : '',
			'customer_email'      => isset( $session_data['customer_email'] ) ? $session_data['customer_email'] : '',
			'customer_phone'      => isset( $session_data['customer_phone'] ) ? $session_data['customer_phone'] : '',
			'special_requests'    => isset( $session_data['customer_special_requests'] ) ? $session_data['customer_special_requests'] : '',
			'cooling_off_waiver' => isset( $session_data['cooling_off_waiver'] ) ? absint( $session_data['cooling_off_waiver'] ) : 0,
			'payment_method'      => 'pay_on_arrival',
			'payment_intent_id'   => null,
			'stripe_session_id'   => null,
			'amount_paid'         => 0,
		);

		// Allow extensions to modify wizard booking data before insertion.
		$booking_data = apply_filters( 'bookit_booking_data_before_insert', $booking_data );

		// Create booking using Booking Creator (handles customer, conflict check, DB insert).
		require_once BOOKIT_PLUGIN_DIR . 'includes/booking/class-booking-creator.php';
		$booking_creator = new Booking_System_Booking_Creator();

		$booking_id = $booking_creator->create_booking( $booking_data );

		if ( is_wp_error( $booking_id ) ) {
			if ( self::should_log() ) {
				error_log( 'Pay on Arrival: Booking creation failed - ' . $booking_id->get_error_message() );
			}
			return $booking_id;
		}

		// Issue 13: Insert payment record for pay-on-arrival bookings.
		// status = 'pending' because cash has not yet been collected by staff.
		global $wpdb;
		$customer_id = (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT customer_id FROM {$wpdb->prefix}bookings WHERE id = %d",
				$booking_id
			)
		);
		$total_price = (float) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT total_price FROM {$wpdb->prefix}bookings WHERE id = %d",
				$booking_id
			)
		);
		$wpdb->insert(
			$wpdb->prefix . 'bookings_payments',
			array(
				'booking_id'       => $booking_id,
				'customer_id'      => $customer_id,
				'amount'           => isset( $session_data['total_price'] ) ? (float) $session_data['total_price'] : $total_price,
				'payment_type'     => 'full_payment',
				'payment_method'   => 'pay_on_arrival',
				'payment_status'   => 'pending',
				'transaction_date' => current_time( 'mysql' ),
				'created_at'       => current_time( 'mysql' ),
				'updated_at'       => current_time( 'mysql' ),
			),
			array( '%d', '%d', '%f', '%s', '%s', '%s', '%s', '%s', '%s' )
		);

		// Notify extensions after a public wizard booking is created.
		do_action( 'bookit_after_booking_created', (int) $booking_id, $booking_data );

		// Retrieve full booking details for confirmation emails.
		require_once BOOKIT_PLUGIN_DIR . 'includes/booking/class-booking-retriever.php';
		$booking_retriever = new Booking_System_Booking_Retriever();
		$booking           = $booking_retriever->get_booking_by_id( $booking_id );

		if ( $booking ) {
			// Send confirmation emails (best-effort; failures do not block booking).
			$email_sender_file = BOOKIT_PLUGIN_DIR . 'includes/email/class-email-sender.php';
			if ( file_exists( $email_sender_file ) ) {
				require_once $email_sender_file;
				$email_sender = new Booking_System_Email_Sender();

				$customer_result = $email_sender->send_customer_confirmation( $booking );
				if ( is_wp_error( $customer_result ) && self::should_log() ) {
					error_log( 'Pay on Arrival: Failed to send customer email - ' . $customer_result->get_error_message() );
				}

				// Staff notification handled by Bookit_Staff_Notifier via bookit_after_booking_created hook.
			}
		} else {
			if ( self::should_log() ) {
				error_log( 'Pay on Arrival: Could not retrieve booking #' . $booking_id . ' for emails' );
			}
		}

		// Clear booking wizard session.
		if ( class_exists( 'Bookit_Session_Manager' ) ) {
			Bookit_Session_Manager::complete_booking();
		} else {
			$booking_retriever->clear_booking_session();
		}

		if ( self::should_log() ) {
			error_log(
				sprintf(
					'Pay on Arrival: Booking #%d created successfully (customer: %s, date: %s)',
					$booking_id,
					$booking_data['customer_email'],
					$booking_data['booking_date']
				)
			);
		}

		// Use V2 confirmation page if booking originated from wizard V2 (session snapshot before clear).
		$confirmed_v2_url = isset( $session_data['wizard_version'] )
			&& 'v2' === $session_data['wizard_version']
			? rtrim( get_option( 'bookit_confirmed_v2_url', home_url( '/booking-confirmed-v2/' ) ), '/' )
			: null;

		$redirect_url = $confirmed_v2_url
			? $confirmed_v2_url . '?booking_id=' . $booking_id
			: home_url( '/booking-confirmed?booking_id=' . $booking_id );

		// Return booking info for redirect.
		return array(
			'success'      => true,
			'booking_id'   => $booking_id,
			'redirect_url' => $redirect_url,
		);
	}

	/**
	 * Process package redemption booking.
	 *
	 * @param array<string, mixed> $session_data Booking wizard session data.
	 * @return array{success: bool, booking_id: int, redirect_url: string}|WP_Error
	 */
	public function process_use_package( $session_data ) {
		global $wpdb;

		if ( empty( $session_data ) ) {
			return new WP_Error( 'invalid_session', __( 'No booking data found', 'bookit-booking-system' ) );
		}

		$customer_package_id = absint( $session_data['customer_package_id'] ?? 0 );
		if ( 0 === $customer_package_id ) {
			return new WP_Error( 'missing_package_selection', __( 'No package selected.', 'bookit-booking-system' ) );
		}

		$row = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT cp.*, pt.applicable_service_ids, pt.name AS package_type_name
				FROM {$wpdb->prefix}bookings_customer_packages cp
				JOIN {$wpdb->prefix}bookings_package_types pt ON pt.id = cp.package_type_id
				WHERE cp.id = %d LIMIT 1",
				$customer_package_id
			),
			ARRAY_A
		);

		if ( ! $row ) {
			return Bookit_Error_Registry::to_wp_error( 'E5001' );
		}

		$status = isset( $row['status'] ) ? (string) $row['status'] : '';
		if ( 'active' !== $status ) {
			if ( 'exhausted' === $status ) {
				return Bookit_Error_Registry::to_wp_error( 'E5002' );
			}
			if ( 'expired' === $status ) {
				return Bookit_Error_Registry::to_wp_error( 'E5003' );
			}
			return new WP_Error(
				'package_not_active',
				__( 'This package is not active.', 'bookit-booking-system' ),
				array( 'status' => 422 )
			);
		}

		if ( (int) $row['sessions_remaining'] < 1 ) {
			return Bookit_Error_Registry::to_wp_error( 'E5002' );
		}

		if ( ! empty( $row['expires_at'] ) && strtotime( (string) $row['expires_at'] ) < time() ) {
			return Bookit_Error_Registry::to_wp_error( 'E5003' );
		}

		$service_id   = absint( $session_data['service_id'] ?? 0 );
		$applicable   = null;
		$applicable_raw = $row['applicable_service_ids'] ?? null;
		if ( ! empty( $applicable_raw ) ) {
			$decoded = json_decode( (string) $applicable_raw, true );
			if ( is_array( $decoded ) ) {
				$applicable = array_values( array_map( 'absint', $decoded ) );
			}
		}
		if ( null !== $applicable && ! in_array( $service_id, $applicable, true ) ) {
			return Bookit_Error_Registry::to_wp_error(
				'E5004',
				array(
					'service_id' => $service_id,
				)
			);
		}

		$booking_data = array(
			'service_id'          => isset( $session_data['service_id'] ) ? $session_data['service_id'] : '',
			'staff_id'            => isset( $session_data['staff_id'] ) ? $session_data['staff_id'] : '',
			'booking_date'        => isset( $session_data['date'] ) ? $session_data['date'] : '',
			'booking_time'        => isset( $session_data['time'] ) ? $session_data['time'] : '',
			'customer_first_name' => isset( $session_data['customer_first_name'] ) ? $session_data['customer_first_name'] : '',
			'customer_last_name'  => isset( $session_data['customer_last_name'] ) ? $session_data['customer_last_name'] : '',
			'customer_email'      => isset( $session_data['customer_email'] ) ? $session_data['customer_email'] : '',
			'customer_phone'      => isset( $session_data['customer_phone'] ) ? $session_data['customer_phone'] : '',
			'special_requests'    => isset( $session_data['customer_special_requests'] ) ? $session_data['customer_special_requests'] : '',
			'cooling_off_waiver'  => isset( $session_data['cooling_off_waiver'] ) ? absint( $session_data['cooling_off_waiver'] ) : 0,
			'payment_method'      => 'package_redemption',
			'payment_intent_id'   => null,
			'stripe_session_id'   => null,
			'amount_paid'         => 0,
			'customer_package_id' => $customer_package_id,
		);

		$booking_data = apply_filters( 'bookit_booking_data_before_insert', $booking_data );

		require_once BOOKIT_PLUGIN_DIR . 'includes/booking/class-booking-creator.php';
		$booking_creator = new Booking_System_Booking_Creator();
		$booking_id      = $booking_creator->create_booking( $booking_data );

		if ( is_wp_error( $booking_id ) ) {
			return $booking_id;
		}

		$wpdb->query(
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

		$wpdb->insert(
			$wpdb->prefix . 'bookings_package_redemptions',
			array(
				'customer_package_id' => $customer_package_id,
				'booking_id'          => $booking_id,
				'redeemed_at'         => current_time( 'mysql' ),
				'redeemed_by'         => 0,
				'notes'               => null,
				'created_at'          => current_time( 'mysql' ),
			),
			array( '%d', '%d', '%s', '%d', '%s', '%s' )
		);

		Bookit_Audit_Logger::log(
			'customer_package.redeemed',
			'customer_package',
			$customer_package_id,
			array(
				'booking_id'          => $booking_id,
				'sessions_remaining' => (int) $row['sessions_remaining'] - 1,
			)
		);

		do_action( 'bookit_after_booking_created', (int) $booking_id, $booking_data );

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
					error_log( 'Pay on Arrival: Failed to send customer email - ' . $customer_result->get_error_message() );
				}

				// Staff notification handled by Bookit_Staff_Notifier via bookit_after_booking_created hook.
			}
		} else {
			if ( self::should_log() ) {
				error_log( 'Pay on Arrival: Could not retrieve booking #' . $booking_id . ' for emails' );
			}
		}

		if ( class_exists( 'Bookit_Session_Manager' ) ) {
			Bookit_Session_Manager::complete_booking();
		} else {
			$booking_retriever->clear_booking_session();
		}

		$confirmed_v2_url = isset( $session_data['wizard_version'] )
			&& 'v2' === $session_data['wizard_version']
			? rtrim( get_option( 'bookit_confirmed_v2_url', home_url( '/booking-confirmed-v2/' ) ), '/' )
			: null;

		$redirect_url = $confirmed_v2_url
			? $confirmed_v2_url . '?booking_id=' . $booking_id
			: home_url( '/booking-confirmed?booking_id=' . $booking_id );

		return array(
			'success'      => true,
			'booking_id'   => $booking_id,
			'redirect_url' => $redirect_url,
		);
	}
}
