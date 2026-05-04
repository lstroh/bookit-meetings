<?php
/**
 * Booking Creator
 * Creates booking and customer records from payment data
 *
 * @package    Bookit_Booking_System
 * @subpackage Bookit_Booking_System/includes/booking
 */

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
	die;
}

/**
 * Booking Creator class.
 */
class Booking_System_Booking_Creator {

	/**
	 * Whether to write messages to error_log (disabled during unit tests).
	 *
	 * @return bool
	 */
	private static function should_log() {
		return ! defined( 'WP_TESTS_TABLE_PREFIX' ) && function_exists( 'error_log' );
	}

	/**
	 * Create booking from payment data
	 *
	 * @param array $data Booking data.
	 * @return int|WP_Error Booking ID or error.
	 */
	public function create_booking( $data ) {
		global $wpdb;

		$validation = $this->validate_booking_data( $data );
		if ( is_wp_error( $validation ) ) {
			return $validation;
		}

		$service = $this->get_service( $data['service_id'] );
		if ( ! $service ) {
			return new WP_Error( 'invalid_service', 'Service not found' );
		}

		$staff = $this->get_staff( $data['staff_id'] );
		if ( ! $staff ) {
			return new WP_Error( 'invalid_staff', 'Staff member not found' );
		}

		$start_time = $this->normalize_time( $data['booking_time'] );
		$duration   = isset( $service['duration'] ) ? (int) $service['duration'] : 60;
		$end_time   = $this->calculate_end_time( $start_time, $duration );

		$customer_id = $this->get_or_create_customer(
			array(
				'first_name' => $data['customer_first_name'],
				'last_name'  => $data['customer_last_name'],
				'email'      => $data['customer_email'],
				'phone'      => isset( $data['customer_phone'] ) ? $data['customer_phone'] : '',
			)
		);

		if ( is_wp_error( $customer_id ) ) {
			return $customer_id;
		}

		$conflict = $this->check_booking_conflict(
			$data['staff_id'],
			$data['booking_date'],
			$start_time,
			$end_time
		);

		if ( $conflict ) {
			return new WP_Error(
				'slot_unavailable',
				'This time slot is no longer available'
			);
		}

		$total_price    = isset( $service['price'] ) ? (float) $service['price'] : 0;
		$amount_paid    = isset( $data['amount_paid'] ) ? (float) $data['amount_paid'] : 0;
		$deposit_amount = $amount_paid;
		$balance_due    = max( 0, $total_price - $amount_paid );
		$created_at     = current_time( 'mysql' );
		$skip_waiver    = ! empty( $data['skip_waiver'] ) && (bool) $data['skip_waiver'];

		if ( ! $skip_waiver ) {
			$waiver_value    = isset( $data['cooling_off_waiver'] ) ? absint( $data['cooling_off_waiver'] ) : 0;
			$requires_waiver = bookit_booking_requires_waiver( (string) $data['booking_date'] );

			if ( $requires_waiver && 1 !== $waiver_value ) {
				return new WP_Error(
					'cooling_off_waiver_required',
					'Cooling-off waiver is required for bookings within 14 days.'
				);
			}

			$waiver_given = ( $requires_waiver && 1 === $waiver_value ) ? 1 : 0;
			$waiver_at    = $waiver_given ? current_time( 'mysql', true ) : null;
		} else {
			$waiver_given = 0;
			$waiver_at    = null;
		}

		// Pay on arrival bookings start as pending_payment; paid bookings are confirmed immediately.
		$status = ( isset( $data['payment_method'] ) && 'pay_on_arrival' === $data['payment_method'] )
			? 'pending_payment'
			: 'confirmed';

		$booking_data = array(
			'customer_id'       => $customer_id,
			'service_id'        => $data['service_id'],
			'staff_id'          => $data['staff_id'],
			'booking_date'      => $data['booking_date'],
			'start_time'        => $start_time,
			'end_time'          => $end_time,
			'duration'          => $duration,
			'status'            => $status,
			'total_price'       => $total_price,
			'deposit_amount'    => $deposit_amount,
			'deposit_paid'      => $amount_paid,
			'balance_due'       => $balance_due,
			'payment_method'    => $data['payment_method'],
			'payment_intent_id' => isset( $data['payment_intent_id'] ) ? $data['payment_intent_id'] : null,
			'stripe_session_id' => isset( $data['stripe_session_id'] ) ? $data['stripe_session_id'] : null,
			'customer_package_id' => ! empty( $data['customer_package_id'] ) ? absint( $data['customer_package_id'] ) : null,
			'special_requests'  => isset( $data['special_requests'] ) ? $data['special_requests'] : '',
			'cooling_off_waiver_given' => $waiver_given,
			'cooling_off_waiver_at' => $waiver_at,
			'created_at'        => $created_at,
			'updated_at'        => current_time( 'mysql' ),
		);

		$format = array(
			'%d', // customer_id
			'%d', // service_id
			'%d', // staff_id
			'%s', // booking_date
			'%s', // start_time
			'%s', // end_time
			'%d', // duration
			'%s', // status
			'%f', // total_price
			'%f', // deposit_amount
			'%f', // deposit_paid (DECIMAL)
			'%f', // balance_due (DECIMAL)
			'%s', // payment_method
			'%s', // payment_intent_id
			'%s', // stripe_session_id
			'%d', // customer_package_id
			'%s', // special_requests
			'%d', // cooling_off_waiver_given
			'%s', // cooling_off_waiver_at
			'%s', // created_at
			'%s', // updated_at
		);

		if ( ! $this->bookings_table_has_customer_package_id() ) {
			unset( $booking_data['customer_package_id'] );
			unset( $format[15] );
			$format = array_values( $format );
		}
		$inserted = $wpdb->insert(
			$wpdb->prefix . 'bookings',
			$booking_data,
			$format
		);

		if ( ! $inserted ) {
			if ( self::should_log() ) {
				error_log( 'Booking Creator: Database insert failed - ' . $wpdb->last_error );
			}
			return new WP_Error( 'database_error', 'Failed to create booking' );
		}

		$booking_id = (int) $wpdb->insert_id;

		// Generate and store a user-facing booking reference.
		$reference = Bookit_Reference_Generator::generate_unique( $booking_id, $created_at );
		$wpdb->update(
			$wpdb->prefix . 'bookings',
			array( 'booking_reference' => $reference ),
			array( 'id' => $booking_id ),
			array( '%s' ),
			array( '%d' )
		);

		$lock_version = Bookit_Reference_Generator::generate_lock_version(
			$booking_id,
			$created_at
		);
		$wpdb->update(
			$wpdb->prefix . 'bookings',
			array( 'lock_version' => $lock_version ),
			array( 'id' => $booking_id ),
			array( '%s' ),
			array( '%d' )
		);

		// Generate and store a magic link token for customer self-service links.
		$magic_link_token = wp_generate_password( 32, false, false );
		$wpdb->update(
			$wpdb->prefix . 'bookings',
			array( 'magic_link_token' => $magic_link_token ),
			array( 'id' => $booking_id ),
			array( '%s' ),
			array( '%d' )
		);

		// Also create a payment record for tracking purposes.
		$payment_intent_id = isset( $data['payment_intent_id'] ) ? $data['payment_intent_id'] : '';
		if ( $amount_paid > 0 ) {
			$wpdb->insert(
				$wpdb->prefix . 'bookings_payments',
				array(
					'booking_id'               => $booking_id,
					'customer_id'              => $customer_id,
					'amount'                   => $amount_paid,
					'payment_type'             => 'deposit',
					'payment_method'           => $data['payment_method'],
					'payment_status'           => 'completed',
					'stripe_payment_intent_id' => $payment_intent_id,
					'transaction_date'         => current_time( 'mysql' ),
					'created_at'               => current_time( 'mysql' ),
					'updated_at'               => current_time( 'mysql' ),
				),
				array( '%d', '%d', '%f', '%s', '%s', '%s', '%s', '%s', '%s', '%s' )
			);
		}

		if ( self::should_log() ) {
			error_log(
				sprintf(
					'Booking Creator: Created booking #%d (customer: %s, service: %s, date: %s %s)',
					$booking_id,
					$data['customer_email'],
					isset( $service['name'] ) ? $service['name'] : '',
					$data['booking_date'],
					$start_time
				)
			);
		}

		return $booking_id;
	}

	/**
	 * Validate booking data
	 *
	 * @param array $data Booking data.
	 * @return bool|WP_Error
	 */
	private function validate_booking_data( $data ) {
		$required_fields = array(
			'service_id',
			'staff_id',
			'booking_date',
			'booking_time',
			'customer_email',
			'customer_first_name',
			'customer_last_name',
			'payment_method',
			'amount_paid',
		);

		foreach ( $required_fields as $field ) {
			if ( ! isset( $data[ $field ] ) || $data[ $field ] === '' ) {
				return new WP_Error(
					'missing_field',
					sprintf( 'Missing required field: %s', $field )
				);
			}
		}

		if ( ! is_email( $data['customer_email'] ) ) {
			return new WP_Error( 'invalid_email', 'Invalid customer email' );
		}

		if ( ! $this->is_valid_date( $data['booking_date'] ) ) {
			return new WP_Error( 'invalid_date', 'Invalid booking date format' );
		}

		if ( ! $this->is_valid_time( $data['booking_time'] ) ) {
			return new WP_Error( 'invalid_time', 'Invalid booking time format' );
		}

		return true;
	}

	/**
	 * Get or create customer record
	 *
	 * @param array $data Customer data.
	 * @return int|WP_Error Customer ID or error.
	 */
	private function get_or_create_customer( $data ) {
		global $wpdb;

		$table = $wpdb->prefix . 'bookings_customers';
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

		$phone = isset( $data['phone'] ) ? $data['phone'] : '';
		$inserted = $wpdb->insert(
			$table,
			array(
				'first_name' => $data['first_name'],
				'last_name'  => $data['last_name'],
				'email'      => $data['email'],
				'phone'      => $phone,
				'created_at' => current_time( 'mysql' ),
				'updated_at' => current_time( 'mysql' ),
			),
			array( '%s', '%s', '%s', '%s', '%s', '%s' )
		);

		if ( ! $inserted ) {
			if ( self::should_log() ) {
				error_log( 'Booking Creator: Customer insert failed - ' . $wpdb->last_error );
			}
			return new WP_Error( 'database_error', 'Failed to create customer' );
		}

		$customer_id = (int) $wpdb->insert_id;

		// Notify extensions after a customer is created during booking creation flows.
		do_action( 'bookit_after_customer_created', $customer_id, $data );

		return $customer_id;
	}

	/**
	 * Calculate end time from start time and duration
	 *
	 * @param string $start_time       Time in HH:MM:SS format.
	 * @param int    $duration_minutes Duration in minutes.
	 * @return string End time in HH:MM:SS format.
	 */
	private function calculate_end_time( $start_time, $duration_minutes ) {
		$start = strtotime( $start_time );
		$end   = $start + ( $duration_minutes * 60 );
		return gmdate( 'H:i:s', $end );
	}

	/**
	 * Check for booking conflicts (double booking prevention)
	 *
	 * @param int    $staff_id   Staff ID.
	 * @param string $date       Booking date.
	 * @param string $start_time Start time.
	 * @param string $end_time   End time.
	 * @return bool True if conflict exists.
	 */
	private function check_booking_conflict( $staff_id, $date, $start_time, $end_time ) {
		global $wpdb;

		$table = $wpdb->prefix . 'bookings';
		$conflict = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$table}
				WHERE staff_id = %d
				AND booking_date = %s
				AND status != 'cancelled'
				AND (
					( start_time < %s AND end_time > %s )
					OR ( start_time < %s AND end_time > %s )
					OR ( start_time >= %s AND end_time <= %s )
				)",
				$staff_id,
				$date,
				$end_time,
				$start_time,
				$end_time,
				$start_time,
				$start_time,
				$end_time
			)
		);

		return (int) $conflict > 0;
	}

	/**
	 * Get service from database
	 *
	 * @param int $service_id Service ID.
	 * @return array|null Service row or null.
	 */
	private function get_service( $service_id ) {
		global $wpdb;
		$row = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$wpdb->prefix}bookings_services WHERE id = %d",
				$service_id
			),
			ARRAY_A
		);
		return $row ? $row : null;
	}

	/**
	 * Get staff from database
	 *
	 * @param int $staff_id Staff ID.
	 * @return array|null Staff row or null.
	 */
	private function get_staff( $staff_id ) {
		global $wpdb;
		$row = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$wpdb->prefix}bookings_staff WHERE id = %d",
				$staff_id
			),
			ARRAY_A
		);
		return $row ? $row : null;
	}

	/**
	 * Validate date format (YYYY-MM-DD)
	 *
	 * @param string $date Date string.
	 * @return bool
	 */
	private function is_valid_date( $date ) {
		$d = DateTime::createFromFormat( 'Y-m-d', $date );
		return $d && $d->format( 'Y-m-d' ) === $date;
	}

	/**
	 * Validate time format (HH:MM:SS or HH:MM)
	 *
	 * @param string $time Time string.
	 * @return bool
	 */
	private function is_valid_time( $time ) {
		if ( preg_match( '/^([01][0-9]|2[0-3]):([0-5][0-9]):([0-5][0-9])$/', $time ) ) {
			return true;
		}
		if ( preg_match( '/^([01][0-9]|2[0-3]):([0-5][0-9])$/', $time ) ) {
			return true;
		}
		return false;
	}

	/**
	 * Normalize time to HH:MM:SS for database storage
	 *
	 * @param string $time Time string (HH:MM or HH:MM:SS).
	 * @return string Time in HH:MM:SS format.
	 */
	private function normalize_time( $time ) {
		$ts = strtotime( $time );
		return $ts !== false ? gmdate( 'H:i:s', $ts ) : $time;
	}

	/**
	 * Check if bookings table has customer_package_id column.
	 *
	 * @return bool
	 */
	private function bookings_table_has_customer_package_id() {
		global $wpdb;
		$table_name = $wpdb->prefix . 'bookings';
		$column     = $wpdb->get_var( "SHOW COLUMNS FROM {$table_name} LIKE 'customer_package_id'" ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared

		return ! empty( $column );
	}
}
