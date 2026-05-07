<?php
/**
 * Dashboard assets + JS data + booking response wiring.
 *
 * @package Bookit_Meetings
 */

if ( ! defined( 'WPINC' ) ) {
	die;
}

class Bookit_Meetings_Assets {
	/**
	 * Enqueue Meetings dashboard extension assets (Vue dist build).
	 *
	 * Must run only when `bookit_dashboard_loaded` fires.
	 *
	 * @return void
	 */
	public function enqueue_dashboard_assets(): void {
		$js_path  = BOOKIT_MEETINGS_PLUGIN_DIR . 'dashboard/dist/app.js';
		$css_path = BOOKIT_MEETINGS_PLUGIN_DIR . 'dashboard/dist/app.css';

		if ( ! file_exists( $js_path ) ) {
			if ( class_exists( 'Bookit_Logger' ) ) {
				Bookit_Logger::warning(
					'Bookit Meetings dashboard assets not found. Did you run the Vite build?',
					array(
						'path' => $js_path,
					)
				);
			}
			return;
		}

		if ( file_exists( $css_path ) ) {
			wp_enqueue_style(
				'bookit-meetings-dashboard',
				BOOKIT_MEETINGS_PLUGIN_URL . 'dashboard/dist/app.css',
				array(),
				BOOKIT_MEETINGS_VERSION
			);
		}

		$js_url = BOOKIT_MEETINGS_PLUGIN_URL . 'dashboard/dist/app.js';

		static $started = false;
		if ( $started ) {
			return;
		}
		$started = true;

		ob_start(
			function ( string $html ) use ( $js_url ): string {
				$current_path      = $_SERVER['REQUEST_URI'] ?? '';
				$is_meetings_route = str_contains( $current_path, '/bookit-dashboard/' );

				if ( ! $is_meetings_route ) {
					return $html;
				}

				$data = apply_filters( 'bookit_dashboard_js_data', array() );
				if ( ! is_array( $data ) ) {
					$data = array();
				}

				$json = wp_json_encode( $data );
				if ( false === $json ) {
					$json = '{}';
				}

				$inject  = '<script>window.bookitMeetings = ' . $json . ';</script>' . "\n";
				$inject .= '<script type="module" src="' . esc_url( $js_url ) . '"></script>' . "\n";

				return str_replace( '</body>', $inject . '</body>', $html );
			}
		);
	}

	/**
	 * Add Meetings settings to the dashboard JS payload.
	 *
	 * @param array $data Existing data.
	 * @return array
	 */
	public function add_dashboard_js_data( array $data ): array {
		$enabled = $this->get_setting_value( 'meetings_enabled', '0' );

		return array_merge(
			$data,
			array(
				'meetings_enabled'    => ( '1' === $enabled ),
				'meetings_platform'   => $this->get_setting_value( 'meetings_platform', '' ),
				'meetings_manual_url' => $this->get_setting_value( 'meetings_manual_url', '' ),
			)
		);
	}

	/**
	 * Extend booking response with meeting_link (re-read from DB).
	 *
	 * @param array $response Response payload.
	 * @param int   $booking_id Booking ID.
	 * @return array
	 */
	public function add_meeting_link_to_booking_response( array $response, int $booking_id ): array {
		global $wpdb;

		if ( $booking_id <= 0 ) {
			$response['meeting_link'] = '';
			return $response;
		}

		$results = $wpdb->get_col( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.PreparedSQL.NotPrepared
			$wpdb->prepare(
				"SELECT meeting_link FROM {$wpdb->prefix}bookings WHERE id = %d LIMIT 1",
				$booking_id
			)
		);

		$response['meeting_link'] = empty( $results ) ? '' : (string) $results[0];
		return $response;
	}

	/**
	 * Read a single setting value via $wpdb->get_col() (empty-string safe).
	 *
	 * @param string $key Setting key.
	 * @param string $default Default if missing.
	 * @return string
	 */
	private function get_setting_value( string $key, string $default ): string {
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

