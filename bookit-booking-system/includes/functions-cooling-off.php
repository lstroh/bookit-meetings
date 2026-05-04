<?php
/**
 * Cooling-off waiver helper functions.
 *
 * @package    Bookit_Booking_System
 * @subpackage Bookit_Booking_System/includes
 */

if ( ! defined( 'WPINC' ) ) {
	die;
}

if ( ! function_exists( 'bookit_booking_requires_waiver' ) ) {
	/**
	 * Determine whether a booking requires a cooling-off waiver.
	 *
	 * A waiver is required only when the appointment date is within 14 calendar days
	 * from today in the WordPress timezone (days 0-13 inclusive). Day 14 and beyond
	 * does not require a waiver.
	 *
	 * @param string $booking_date_string Booking date in YYYY-MM-DD format.
	 * @return bool
	 */
	function bookit_booking_requires_waiver( $booking_date_string ) {
		if ( ! is_string( $booking_date_string ) || '' === $booking_date_string ) {
			return false;
		}

		$timezone = wp_timezone();
		$today    = DateTimeImmutable::createFromFormat( '!Y-m-d', wp_date( 'Y-m-d', null, $timezone ), $timezone );
		$booking  = DateTimeImmutable::createFromFormat( '!Y-m-d', $booking_date_string, $timezone );

		if ( ! $today || ! $booking ) {
			return false;
		}

		// Reject invalid parsed dates like 2026-02-31.
		if ( $booking->format( 'Y-m-d' ) !== $booking_date_string ) {
			return false;
		}

		// Past dates are outside scope for waiver display/requirement.
		if ( $booking < $today ) {
			return false;
		}

		$days_until_booking = (int) $today->diff( $booking )->format( '%a' );
		return $days_until_booking < 14;
	}
}
