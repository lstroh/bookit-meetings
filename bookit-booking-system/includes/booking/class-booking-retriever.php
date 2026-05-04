<?php
/**
 * Booking Retriever
 * Retrieves booking details with related data (customer, service, staff)
 *
 * @package Booking_System
 * @subpackage Booking
 */

class Booking_System_Booking_Retriever {

	/**
	 * Get booking by Stripe session ID
	 * Includes customer, service, and staff details via JOINs
	 *
	 * @param string $session_id Stripe checkout session ID
	 * @return array|null Booking array with all details or null if not found
	 */
	public function get_booking_by_stripe_session( $session_id ) {
		global $wpdb;

		if ( empty( $session_id ) ) {
			return null;
		}

		// Query with JOINs to get all related data in one query.
		// Schema has: special_requests, deposit_paid (DECIMAL), balance_due (DECIMAL), payment_intent_id.
		$booking = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT 
				b.id,
				b.booking_reference,
				b.booking_date,
				b.start_time,
				b.end_time,
				b.status,
				b.total_price,
				b.deposit_amount,
				b.deposit_paid,
				b.balance_due,
				b.full_amount_paid,
				b.payment_method,
				b.payment_intent_id,
				b.stripe_session_id,
				b.special_requests,
				b.cooling_off_waiver_given,
				b.cooling_off_waiver_at,
				b.magic_link_token,
				b.created_at,
				c.id AS customer_id,
				c.first_name AS customer_first_name,
				c.last_name AS customer_last_name,
				c.email AS customer_email,
				c.phone AS customer_phone,
				s.id AS service_id,
				s.name AS service_name,
				s.duration AS service_duration,
				s.price AS service_price,
				st.id AS staff_id,
				st.first_name AS staff_first_name,
				st.last_name AS staff_last_name,
				st.email AS staff_email
			FROM {$wpdb->prefix}bookings b
			LEFT JOIN {$wpdb->prefix}bookings_customers c ON b.customer_id = c.id
			LEFT JOIN {$wpdb->prefix}bookings_services s ON b.service_id = s.id
			LEFT JOIN {$wpdb->prefix}bookings_staff st ON b.staff_id = st.id
			WHERE b.stripe_session_id = %s
			LIMIT 1",
				$session_id
			),
			ARRAY_A
		);

		if ( ! $booking ) {
			return null;
		}

		// Add computed/alias fields (null-safe for LEFT JOINs)
		$booking['staff_name']    = trim( ( $booking['staff_first_name'] ?? '' ) . ' ' . ( $booking['staff_last_name'] ?? '' ) );
		$booking['customer_name'] = trim( ( $booking['customer_first_name'] ?? '' ) . ' ' . ( $booking['customer_last_name'] ?? '' ) );

		return $booking;
	}

	/**
	 * Get booking by ID
	 *
	 * @param int $booking_id
	 * @return array|null
	 */
	public function get_booking_by_id( $booking_id ) {
		global $wpdb;

		$booking = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT 
				b.id,
				b.booking_reference,
				b.customer_id,
				b.service_id,
				b.staff_id,
				b.booking_date,
				b.start_time,
				b.end_time,
				b.duration,
				b.status,
				b.total_price,
				b.deposit_amount,
				b.deposit_paid,
				b.balance_due,
				b.full_amount_paid,
				b.payment_method,
				b.payment_intent_id,
				b.stripe_session_id,
				b.special_requests,
				b.cooling_off_waiver_given,
				b.cooling_off_waiver_at,
				b.magic_link_token,
				b.created_at,
				b.updated_at,
				b.deleted_at,
				c.first_name AS customer_first_name,
				c.last_name AS customer_last_name,
				c.email AS customer_email,
				c.phone AS customer_phone,
				s.name AS service_name,
				s.duration AS service_duration,
				s.price AS service_price,
				st.first_name AS staff_first_name,
				st.last_name AS staff_last_name,
				st.email AS staff_email
			FROM {$wpdb->prefix}bookings b
			LEFT JOIN {$wpdb->prefix}bookings_customers c ON b.customer_id = c.id
			LEFT JOIN {$wpdb->prefix}bookings_services s ON b.service_id = s.id
			LEFT JOIN {$wpdb->prefix}bookings_staff st ON b.staff_id = st.id
			WHERE b.id = %d
			LIMIT 1",
				$booking_id
			),
			ARRAY_A
		);

		if ( ! $booking ) {
			return null;
		}

		// Add computed/alias fields (null-safe for LEFT JOINs)
		$booking['staff_name']    = trim( ( $booking['staff_first_name'] ?? '' ) . ' ' . ( $booking['staff_last_name'] ?? '' ) );
		$booking['customer_name'] = trim( ( $booking['customer_first_name'] ?? '' ) . ' ' . ( $booking['customer_last_name'] ?? '' ) );

		return $booking;
	}

	/**
	 * Clear booking wizard session data
	 */
	public function clear_booking_session() {
		if ( isset( $_SESSION['bookit_wizard'] ) ) {
			unset( $_SESSION['bookit_wizard'] );
		}
	}

	/**
	 * Format booking date for display
	 *
	 * @param string $date Date in YYYY-MM-DD format
	 * @return string Formatted date (e.g., "Saturday, 15 February 2026")
	 */
	public function format_date( $date ) {
		return date( 'l, j F Y', strtotime( $date ) );
	}

	/**
	 * Format booking time for display
	 *
	 * @param string $time Time in HH:MM:SS format
	 * @return string Formatted time (e.g., "2:00 PM")
	 */
	public function format_time( $time ) {
		return date( 'g:i A', strtotime( $time ) );
	}
}
