<?php
/**
 * Meetings REST API Controller.
 *
 * @package Bookit_Meetings
 */

if ( ! defined( 'WPINC' ) ) {
	die;
}

/**
 * Bookit Meetings API class.
 */
class Bookit_Meetings_Api {

	/**
	 * REST API namespace.
	 */
	const NAMESPACE = 'bookit-meetings/v1';

	/**
	 * Register routes.
	 *
	 * @return void
	 */
	public function register_routes(): void {
		register_rest_route(
			self::NAMESPACE,
			'/settings',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'get_settings' ),
				'permission_callback' => array( $this, 'check_admin_permission' ),
			)
		);

		register_rest_route(
			self::NAMESPACE,
			'/settings',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'update_settings' ),
				'permission_callback' => array( $this, 'check_admin_permission' ),
			)
		);

		register_rest_route(
			self::NAMESPACE,
			'/bookings/(?P<id>\d+)/link',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'update_booking_link' ),
				'permission_callback' => array( $this, 'check_admin_permission' ),
				'args'                => array(
					'id' => array(
						'required'          => true,
						'type'              => 'integer',
						'sanitize_callback' => 'absint',
					),
				),
			)
		);
	}

	/**
	 * Check if user has dashboard permission.
	 *
	 * Copied verbatim from core `Bookit_Extensions_API::check_dashboard_permission()`.
	 *
	 * @return bool|WP_Error
	 */
	public function check_dashboard_permission() {
		// Load auth classes if not loaded.
		if ( ! class_exists( 'Bookit_Session' ) ) {
			require_once plugin_dir_path( dirname( __FILE__ ) ) . 'class-bookit-session.php';
		}
		if ( ! class_exists( 'Bookit_Auth' ) ) {
			require_once plugin_dir_path( dirname( __FILE__ ) ) . 'class-bookit-auth.php';
		}

		// Check if logged in.
		if ( ! Bookit_Auth::is_logged_in() ) {
			return Bookit_Error_Registry::to_wp_error( 'E1003' );
		}

		return true;
	}

	/**
	 * Check if user has admin permission.
	 *
	 * Copied verbatim from core `Bookit_Customers_API::check_admin_permission()`.
	 *
	 * @return bool|WP_Error
	 */
	public function check_admin_permission() {
		// Load auth classes if not loaded.
		if ( ! class_exists( 'Bookit_Session' ) ) {
			require_once plugin_dir_path( dirname( __FILE__ ) ) . 'class-bookit-session.php';
		}
		if ( ! class_exists( 'Bookit_Auth' ) ) {
			require_once plugin_dir_path( dirname( __FILE__ ) ) . 'class-bookit-auth.php';
		}

		if ( ! Bookit_Auth::is_logged_in() ) {
			return new WP_Error(
				'unauthorized',
				'You must be logged in to access the dashboard.',
				array( 'status' => 401 )
			);
		}

		$current_staff = Bookit_Auth::get_current_staff();

		if ( ! $current_staff || 'admin' !== $current_staff['role'] ) {
			return new WP_Error(
				'forbidden',
				'Only administrators can manage services.',
				array( 'status' => 403 )
			);
		}

		return true;
	}

	/**
	 * GET /settings
	 *
	 * @return WP_REST_Response
	 */
	public function get_settings() {
		return rest_ensure_response(
			array(
				'success' => true,
				'data'    => $this->read_all_settings(),
			)
		);
	}

	/**
	 * POST /settings
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response|WP_Error
	 */
	public function update_settings( $request ) {
		global $wpdb;

		$params = $request instanceof WP_REST_Request ? (array) $request->get_json_params() : array();

		$allowed_keys = array(
			'meetings_enabled',
			'meetings_platform',
			'meetings_manual_url',
		);

		$provided = array_intersect_key( $params, array_flip( $allowed_keys ) );

		if ( array_key_exists( 'meetings_enabled', $provided ) ) {
			$value = (string) $provided['meetings_enabled'];
			if ( ! in_array( $value, array( '0', '1' ), true ) ) {
				return $this->invalid_setting_error( 'meetings_enabled must be "0" or "1".' );
			}
		}

		if ( array_key_exists( 'meetings_platform', $provided ) ) {
			$value = (string) $provided['meetings_platform'];
			if ( ! in_array( $value, array( 'whatsapp', 'teams', 'generic', '' ), true ) ) {
				return $this->invalid_setting_error( 'meetings_platform must be one of: "whatsapp", "teams", "generic", "".' );
			}
		}

		if ( array_key_exists( 'meetings_manual_url', $provided ) ) {
			$value = (string) $provided['meetings_manual_url'];
			if ( '' !== $value && false === filter_var( $value, FILTER_VALIDATE_URL ) ) {
				return $this->invalid_setting_error( 'meetings_manual_url must be a valid URL.' );
			}
		}

		foreach ( $provided as $key => $value ) {
			$key   = (string) $key;
			$value = (string) $value;

			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.PreparedSQL.NotPrepared
			$wpdb->query(
				$wpdb->prepare(
					"INSERT INTO {$wpdb->prefix}bookings_settings (setting_key, setting_value)
					VALUES (%s, %s)
					ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)",
					$key,
					$value
				)
			);
		}

		return rest_ensure_response(
			array(
				'success' => true,
				'data'    => $this->read_all_settings(),
			)
		);
	}

	/**
	 * POST /bookings/{id}/link
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response|WP_Error
	 */
	public function update_booking_link( $request ) {
		global $wpdb;

		$booking_id = absint( $request instanceof WP_REST_Request ? $request->get_param( 'id' ) : 0 );
		if ( $booking_id <= 0 ) {
			return $this->invalid_setting_error( 'id must be a positive integer.' );
		}

		$params = $request instanceof WP_REST_Request ? (array) $request->get_json_params() : array();
		if ( ! array_key_exists( 'meeting_link', $params ) ) {
			return $this->invalid_setting_error( 'meeting_link is required.' );
		}

		$meeting_link = (string) $params['meeting_link'];
		if ( '' !== $meeting_link && false === filter_var( $meeting_link, FILTER_VALIDATE_URL ) ) {
			return $this->invalid_setting_error( 'meeting_link must be a valid URL.' );
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.PreparedSQL.NotPrepared
		$exists = (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$wpdb->prefix}bookings WHERE id = %d AND deleted_at IS NULL",
				$booking_id
			)
		);

		if ( $exists <= 0 ) {
			return new WP_Error(
				'bookit_meetings_booking_not_found',
				'Booking not found.',
				array( 'status' => 404 )
			);
		}

		$wpdb->update(
			$wpdb->prefix . 'bookings',
			array( 'meeting_link' => $meeting_link ?: null ),
			array( 'id' => $booking_id ),
			array( '%s' ),
			array( '%d' )
		);

		return rest_ensure_response(
			array(
				'success' => true,
				'data'    => array(
					'booking_id'   => $booking_id,
					'meeting_link' => $meeting_link,
				),
			)
		);
	}

	/**
	 * Read settings using empty-string-safe reads.
	 *
	 * @return array<string, string>
	 */
	private function read_all_settings(): array {
		return array(
			'meetings_enabled'    => $this->read_setting_value( 'meetings_enabled', '0' ),
			'meetings_platform'   => $this->read_setting_value( 'meetings_platform', '' ),
			'meetings_manual_url' => $this->read_setting_value( 'meetings_manual_url', '' ),
		);
	}

	/**
	 * Read a single setting value via $wpdb->get_col().
	 *
	 * @param string $setting_key Setting key.
	 * @param string $default Default value when key missing.
	 * @return string
	 */
	private function read_setting_value( string $setting_key, string $default ): string {
		global $wpdb;

		$table_name = $wpdb->prefix . 'bookings_settings';
		$sql        = $wpdb->prepare(
			"SELECT setting_value FROM {$table_name} WHERE setting_key = %s LIMIT 1",
			$setting_key
		);

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.PreparedSQL.NotPrepared
		$results = $wpdb->get_col( $sql );
		if ( empty( $results ) ) {
			return $default;
		}

		return (string) $results[0];
	}

	/**
	 * Standard 422 invalid-setting error.
	 *
	 * @param string $message Error message.
	 * @return WP_Error
	 */
	private function invalid_setting_error( string $message ): WP_Error {
		return new WP_Error(
			'bookit_meetings_invalid_setting',
			$message,
			array( 'status' => 422 )
		);
	}
}

