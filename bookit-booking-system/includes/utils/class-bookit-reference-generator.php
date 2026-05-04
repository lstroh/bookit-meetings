<?php
/**
 * Booking reference generator utility.
 *
 * @package    Bookit_Booking_System
 * @subpackage Bookit_Booking_System/includes/utils
 */

if ( ! defined( 'WPINC' ) ) {
	die;
}

/**
 * Generates human-readable booking references.
 */
class Bookit_Reference_Generator {

	/**
	 * Generate a booking reference in BK[YYMM]-[XXXX] format.
	 *
	 * @param int    $booking_id The booking's database ID.
	 * @param string $created_at MySQL datetime string (Y-m-d H:i:s).
	 * @return string
	 */
	public static function generate( int $booking_id, string $created_at ): string {
		$date_part = date( 'ym', strtotime( $created_at ) );
		$hash_part = strtoupper( substr( md5( $booking_id . $created_at . wp_salt() ), 0, 4 ) );

		return 'BK' . $date_part . '-' . $hash_part;
	}

	/**
	 * Generate a unique reference with collision detection.
	 *
	 * @param int    $booking_id The booking's database ID.
	 * @param string $created_at MySQL datetime string (Y-m-d H:i:s).
	 * @return string
	 */
	public static function generate_unique( int $booking_id, string $created_at ): string {
		global $wpdb;

		for ( $attempt = 0; $attempt < 5; $attempt++ ) {
			$salt      = 0 === $attempt ? '' : '_attempt_' . $attempt;
			$date_part = date( 'ym', strtotime( $created_at ) );
			$hash_part = strtoupper( substr( md5( $booking_id . $created_at . wp_salt() . $salt ), 0, 4 ) );
			$reference = 'BK' . $date_part . '-' . $hash_part;

			$exists = $wpdb->get_var(
				$wpdb->prepare(
					"SELECT COUNT(*) FROM {$wpdb->prefix}bookings WHERE booking_reference = %s AND id != %d",
					$reference,
					$booking_id
				)
			);

			if ( ! $exists ) {
				return $reference;
			}
		}

		return 'BK' . date( 'ym', strtotime( $created_at ) ) . '-' . strtoupper( substr( md5( $booking_id . microtime() ), 0, 4 ) );
	}

	/**
	 * Generate a booking lock version token.
	 *
	 * @param int    $booking_id The booking's database ID.
	 * @param string $updated_at MySQL datetime string (Y-m-d H:i:s).
	 * @return string 32-character MD5 hex string.
	 */
	public static function generate_lock_version( int $booking_id, string $updated_at ): string {
		return md5( $booking_id . $updated_at . wp_salt() );
	}
}
