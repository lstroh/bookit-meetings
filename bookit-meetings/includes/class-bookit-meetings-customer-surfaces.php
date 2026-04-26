<?php
/**
 * Customer-facing meeting surfaces.
 *
 * @package Bookit_Meetings
 */

if ( ! defined( 'WPINC' ) ) {
	die;
}

class Bookit_Meetings_Customer_Surfaces {
	/**
	 * Filter callback for confirmation page meeting section.
	 *
	 * @param string $html Existing HTML (default '').
	 * @param array  $booking Booking array.
	 * @return string
	 */
	public function confirmation_page_section( string $html, array $booking ): string {
		$meetings_enabled  = $this->get_setting( 'meetings_enabled', '0' );
		$meetings_platform = $this->get_setting( 'meetings_platform', '' );

		if ( '1' !== $meetings_enabled ) {
			return $html;
		}

		$allowed_platforms = array( 'whatsapp', 'teams', 'generic' );
		if ( '' === $meetings_platform || ! in_array( $meetings_platform, $allowed_platforms, true ) ) {
			return $html;
		}

		if ( 'whatsapp' === $meetings_platform ) {
			return '<div class="bookit-meeting-section bookit-meeting-whatsapp"><p>Your host will initiate a WhatsApp call/video at your appointment time.</p></div>';
		}

		$meeting_link = $booking['meeting_link'] ?? null;
		if ( null === $meeting_link || '' === (string) $meeting_link ) {
			return $html;
		}

		$meeting_link_esc = esc_url( (string) $meeting_link );
		$button_text      = esc_html__( 'Join Meeting', 'bookit-meetings' );

		return '<div class="bookit-meeting-section bookit-meeting-link"><a href="' . $meeting_link_esc . '" class="bookit-meeting-btn" target="_blank" rel="noopener noreferrer">' . $button_text . '</a></div>';
	}

	/**
	 * Filter callback for confirmation email meeting section.
	 *
	 * @param string $html Existing HTML (default '').
	 * @param array  $booking Booking array.
	 * @return string
	 */
	public function confirmation_email_section( string $html, array $booking ): string {
		$meetings_enabled  = $this->get_setting( 'meetings_enabled', '0' );
		$meetings_platform = $this->get_setting( 'meetings_platform', '' );

		if ( '1' !== $meetings_enabled ) {
			return $html;
		}

		$allowed_platforms = array( 'whatsapp', 'teams', 'generic' );
		if ( '' === $meetings_platform || ! in_array( $meetings_platform, $allowed_platforms, true ) ) {
			return $html;
		}

		if ( 'whatsapp' === $meetings_platform ) {
			return '<tr><td style="padding: 12px 0; font-family: Arial, sans-serif; font-size: 14px; color: #333333;">Your host will initiate a WhatsApp call/video at your appointment time.</td></tr>';
		}

		$meeting_link = $booking['meeting_link'] ?? null;
		if ( null === $meeting_link || '' === (string) $meeting_link ) {
			return $html;
		}

		$meeting_link_url     = esc_url( (string) $meeting_link );
		$meeting_link_display = esc_html( (string) $meeting_link );

		return '<tr><td style="padding: 12px 0; font-family: Arial, sans-serif; font-size: 14px; color: #333333;"><strong>Meeting link:</strong> <a href="' . $meeting_link_url . '" style="color: #4f46e5; text-decoration: none;">' . $meeting_link_display . '</a></td></tr>';
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
}

