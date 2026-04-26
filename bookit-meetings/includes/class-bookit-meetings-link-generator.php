<?php
/**
 * Meetings link generator.
 *
 * @package Bookit_Meetings
 */

if ( ! defined( 'WPINC' ) ) {
	die;
}

class Bookit_Meetings_Link_Generator {
	/**
	 * Handle public wizard booking confirmation page render.
	 *
	 * @param int   $booking_id Booking ID.
	 * @param array $booking Full booking array.
	 * @return void
	 */
	public function handle_booking_confirmed( int $booking_id, array $booking ): void {
		$this->maybe_generate_link( $booking_id );
	}

	/**
	 * Handle manual dashboard booking creation.
	 *
	 * @param int   $booking_id Booking ID.
	 * @param array $booking_data Booking data that was inserted.
	 * @return void
	 */
	public function handle_booking_created( int $booking_id, array $booking_data ): void {
		$this->maybe_generate_link( $booking_id );
	}

	/**
	 * Read a single setting value via $wpdb->get_col() (empty-string safe).
	 *
	 * @param string $key Setting key.
	 * @param string $default Default if missing.
	 * @return string
	 */
	private function get_setting( string $key, string $default = '' ): string {
		global $wpdb;

		$results = $wpdb->get_col( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.PreparedSQL.NotPrepared
			$wpdb->prepare(
				"SELECT setting_value FROM {$wpdb->prefix}bookings_settings WHERE setting_key = %s LIMIT 1",
				$key
			)
		);

		return empty( $results ) ? $default : (string) $results[0];
	}

	/**
	 * Generate and persist meeting link on booking when appropriate.
	 *
	 * @param int $booking_id Booking ID.
	 * @return void
	 */
	private function maybe_generate_link( int $booking_id ): void {
		global $wpdb;

		$meetings_enabled    = $this->get_setting( 'meetings_enabled', '0' );
		$meetings_platform   = $this->get_setting( 'meetings_platform', '' );
		$meetings_manual_url = $this->get_setting( 'meetings_manual_url', '' );

		if ( '1' !== $meetings_enabled ) {
			return;
		}

		$allowed_platforms = array( 'whatsapp', 'teams', 'generic' );
		if ( '' === $meetings_platform || ! in_array( $meetings_platform, $allowed_platforms, true ) ) {
			return;
		}

		if ( 'whatsapp' === $meetings_platform ) {
			return;
		}

		if ( '' === $meetings_manual_url ) {
			return;
		}

		$booking = $wpdb->get_row( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.PreparedSQL.NotPrepared
			$wpdb->prepare(
				"SELECT id, meeting_link, deleted_at FROM {$wpdb->prefix}bookings WHERE id = %d LIMIT 1",
				$booking_id
			),
			ARRAY_A
		);

		if ( null === $booking ) {
			return;
		}

		if ( null !== $booking['deleted_at'] ) {
			return;
		}

		if ( null !== $booking['meeting_link'] && '' !== (string) $booking['meeting_link'] ) {
			return;
		}

		$wpdb->update(
			$wpdb->prefix . 'bookings',
			array( 'meeting_link' => $meetings_manual_url ),
			array( 'id' => $booking_id ),
			array( '%s' ),
			array( '%d' )
		);
	}
}

