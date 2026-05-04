<?php
/**
 * Team Calendar REST API Controller.
 *
 * Provides admin-only day/week calendar data for all staff.
 *
 * @package    Bookit_Booking_System
 * @subpackage Bookit_Booking_System/includes/api
 */

if ( ! defined( 'WPINC' ) ) {
	die;
}

/**
 * Team calendar API class.
 */
class Bookit_Team_Calendar_API {

	/**
	 * REST API namespace.
	 */
	const NAMESPACE = 'bookit/v1';

	/**
	 * Staff colour palette.
	 *
	 * @var string[]
	 */
	private const STAFF_COLOURS = array(
		'#4F46E5',
		'#0891B2',
		'#059669',
		'#D97706',
		'#DC2626',
		'#7C3AED',
		'#DB2777',
		'#0284C7',
		'#65A30D',
		'#EA580C',
	);

	/**
	 * Constructor.
	 */
	public function __construct() {
		add_action( 'rest_api_init', array( $this, 'register_routes' ) );
	}

	/**
	 * Register routes.
	 *
	 * @return void
	 */
	public function register_routes() {
		register_rest_route(
			self::NAMESPACE,
			'/team-calendar',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'get_team_calendar' ),
				'permission_callback' => array( $this, 'check_admin_permission' ),
				'args'                => array(
					'view_type' => array(
						'required'          => true,
						'type'              => 'string',
						'sanitize_callback' => 'sanitize_text_field',
					),
					'date'      => array(
						'required'          => true,
						'type'              => 'string',
						'sanitize_callback' => 'sanitize_text_field',
					),
					'staff_id'  => array(
						'required'          => false,
						'type'              => 'integer',
						'sanitize_callback' => 'absint',
					),
				),
			)
		);
	}

	/**
	 * Permission callback for admin-only endpoint.
	 *
	 * @return true|WP_Error
	 */
	public function check_admin_permission() {
		if ( ! class_exists( 'Bookit_Session' ) ) {
			require_once plugin_dir_path( dirname( __FILE__ ) ) . 'class-bookit-session.php';
		}
		if ( ! class_exists( 'Bookit_Auth' ) ) {
			require_once plugin_dir_path( dirname( __FILE__ ) ) . 'class-bookit-auth.php';
		}

		if ( ! Bookit_Auth::is_logged_in() ) {
			return Bookit_Error_Registry::to_wp_error( 'E1002' );
		}

		$current_staff = Bookit_Auth::get_current_staff();
		$role          = is_array( $current_staff ) && isset( $current_staff['role'] ) ? (string) $current_staff['role'] : '';

		if ( ! in_array( $role, array( 'admin', 'bookit_admin' ), true ) ) {
			return Bookit_Error_Registry::to_wp_error(
				'E1003',
				array(
					'required_role' => 'bookit_admin',
					'actual_role'   => $role,
				)
			);
		}

		return true;
	}

	/**
	 * GET /team-calendar
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response|WP_Error
	 */
	public function get_team_calendar( $request ) {
		global $wpdb;

		$view_type = sanitize_text_field( (string) $request->get_param( 'view_type' ) );
		$date_raw  = sanitize_text_field( (string) $request->get_param( 'date' ) );
		$staff_id  = absint( $request->get_param( 'staff_id' ) );

		if ( ! in_array( $view_type, array( 'day', 'week', 'month' ), true ) ) {
			return Bookit_Error_Registry::to_wp_error( 'E4012', array( 'field' => 'view_type' ) );
		}

		$timezone = new DateTimeZone( 'Europe/London' );
		$date_obj = DateTimeImmutable::createFromFormat( '!Y-m-d', $date_raw, $timezone );

		if ( ! $date_obj || $date_obj->format( 'Y-m-d' ) !== $date_raw ) {
			return Bookit_Error_Registry::to_wp_error( 'E4003', array( 'date' => $date_raw ) );
		}

		if ( 'week' === $view_type ) {
			$start_date = $date_obj->modify( 'monday this week' );
			$end_date   = $start_date->modify( '+6 days' );
		} elseif ( 'month' === $view_type ) {
			$start_date = $date_obj->modify( 'first day of this month' );
			$end_date   = $date_obj->modify( 'last day of this month' );
		} else {
			$start_date = $date_obj;
			$end_date   = $date_obj;
		}

		$date_start = $start_date->format( 'Y-m-d' );
		$date_end   = $end_date->format( 'Y-m-d' );
		$today      = wp_date( 'Y-m-d', null, $timezone );

		if ( $staff_id > 0 ) {
			$staff_rows = $wpdb->get_results(
				$wpdb->prepare(
					"SELECT id, first_name, last_name, photo_url
					FROM {$wpdb->prefix}bookings_staff
					WHERE deleted_at IS NULL
						AND is_active = 1
						AND id = %d
					ORDER BY display_order ASC, first_name ASC, last_name ASC",
					$staff_id
				),
				ARRAY_A
			);
		} else {
			$staff_rows = $wpdb->get_results(
				"SELECT id, first_name, last_name, photo_url
				FROM {$wpdb->prefix}bookings_staff
				WHERE deleted_at IS NULL
					AND is_active = 1
				ORDER BY display_order ASC, first_name ASC, last_name ASC",
				ARRAY_A
			);
		}

		if ( null === $staff_rows ) {
			return Bookit_Error_Registry::to_wp_error( 'E9001', array( 'db_error' => $wpdb->last_error ) );
		}

		$staff = array_map(
			function ( $row ) {
				$full_name = trim( (string) $row['first_name'] . ' ' . (string) $row['last_name'] );
				$staff_id  = (int) $row['id'];
				$colour_ix = $staff_id % count( self::STAFF_COLOURS );

				return array(
					'id'        => $staff_id,
					'full_name' => $full_name,
					'initials'  => $this->get_initials( $full_name ),
					'colour'    => self::STAFF_COLOURS[ $colour_ix ],
					'photo_url' => ! empty( $row['photo_url'] ) ? esc_url_raw( (string) $row['photo_url'] ) : null,
				);
			},
			$staff_rows
		);

		$booking_query = "SELECT
				b.id,
				b.staff_id,
				b.booking_date,
				b.start_time,
				b.end_time,
				b.status,
				b.total_price,
				s.name AS service_name,
				c.first_name AS customer_first_name,
				c.last_name AS customer_last_name,
				COALESCE(p.payment_status, 'pending') AS payment_status
			FROM {$wpdb->prefix}bookings b
			INNER JOIN {$wpdb->prefix}bookings_services s
				ON s.id = b.service_id
			LEFT JOIN {$wpdb->prefix}bookings_customers c
				ON c.id = b.customer_id
			LEFT JOIN {$wpdb->prefix}bookings_payments p
				ON p.id = (
					SELECT p2.id
					FROM {$wpdb->prefix}bookings_payments p2
					WHERE p2.booking_id = b.id
					ORDER BY p2.transaction_date DESC, p2.id DESC
					LIMIT 1
				)
			WHERE b.deleted_at IS NULL
				AND b.booking_date BETWEEN %s AND %s";

		if ( $staff_id > 0 ) {
			$booking_query .= ' AND b.staff_id = %d';
			$booking_rows   = $wpdb->get_results(
				$wpdb->prepare( $booking_query . ' ORDER BY b.booking_date ASC, b.start_time ASC', $date_start, $date_end, $staff_id ),
				ARRAY_A
			);
		} else {
			$booking_rows = $wpdb->get_results(
				$wpdb->prepare( $booking_query . ' ORDER BY b.booking_date ASC, b.start_time ASC', $date_start, $date_end ),
				ARRAY_A
			);
		}

		if ( null === $booking_rows ) {
			return Bookit_Error_Registry::to_wp_error( 'E9001', array( 'db_error' => $wpdb->last_error ) );
		}

		$time_off_query = "SELECT
				wh.staff_id,
				wh.specific_date,
				wh.start_time,
				wh.end_time,
				wh.notes
			FROM {$wpdb->prefix}bookings_staff_working_hours wh
			INNER JOIN {$wpdb->prefix}bookings_staff st
				ON st.id = wh.staff_id
				AND st.deleted_at IS NULL
				AND st.is_active = 1
			WHERE wh.is_working = 0
				AND wh.specific_date IS NOT NULL
				AND wh.specific_date BETWEEN %s AND %s";

		if ( $staff_id > 0 ) {
			$time_off_query .= ' AND wh.staff_id = %d';
			$time_off_rows   = $wpdb->get_results(
				$wpdb->prepare( $time_off_query . ' ORDER BY wh.specific_date ASC, wh.staff_id ASC', $date_start, $date_end, $staff_id ),
				ARRAY_A
			);
		} else {
			$time_off_rows = $wpdb->get_results(
				$wpdb->prepare( $time_off_query . ' ORDER BY wh.specific_date ASC, wh.staff_id ASC', $date_start, $date_end ),
				ARRAY_A
			);
		}

		if ( null === $time_off_rows ) {
			return Bookit_Error_Registry::to_wp_error( 'E9001', array( 'db_error' => $wpdb->last_error ) );
		}

		$bookings_by_day = array();
		foreach ( $booking_rows as $row ) {
			$day_key = (string) $row['booking_date'];
			if ( ! isset( $bookings_by_day[ $day_key ] ) ) {
				$bookings_by_day[ $day_key ] = array();
			}

			$bookings_by_day[ $day_key ][] = array(
				'id'             => (int) $row['id'],
				'staff_id'       => (int) $row['staff_id'],
				'customer_name'  => trim( (string) $row['customer_first_name'] . ' ' . (string) $row['customer_last_name'] ),
				'service_name'   => (string) $row['service_name'],
				'start_time'     => substr( (string) $row['start_time'], 0, 5 ),
				'end_time'       => substr( (string) $row['end_time'], 0, 5 ),
				'status'         => (string) $row['status'],
				'payment_status' => (string) $row['payment_status'],
				'total_price'    => number_format( (float) $row['total_price'], 2, '.', '' ),
			);
		}

		$time_off_by_day = array();
		foreach ( $time_off_rows as $row ) {
			$day_key = (string) $row['specific_date'];
			if ( ! isset( $time_off_by_day[ $day_key ] ) ) {
				$time_off_by_day[ $day_key ] = array();
			}

			$is_all_day = $this->is_all_day_time_off( (string) $row['start_time'], (string) $row['end_time'] );
			$label      = $this->format_time_off_label( (string) $row['notes'] );

			$time_off_by_day[ $day_key ][] = array(
				'staff_id'   => (int) $row['staff_id'],
				'label'      => $label,
				'all_day'    => $is_all_day,
				'start_time' => $is_all_day ? null : substr( (string) $row['start_time'], 0, 5 ),
				'end_time'   => $is_all_day ? null : substr( (string) $row['end_time'], 0, 5 ),
			);
		}

		$days    = array();
		$current = $start_date;
		while ( $current <= $end_date ) {
			$day_key = $current->format( 'Y-m-d' );
			$days[]  = array(
				'date'      => $day_key,
				'label'     => wp_date( 'l j F', $current->getTimestamp(), $timezone ),
				'is_today'  => $day_key === $today,
				'bookings'  => isset( $bookings_by_day[ $day_key ] ) ? $bookings_by_day[ $day_key ] : array(),
				'booking_count' => isset( $bookings_by_day[ $day_key ] ) ? count( $bookings_by_day[ $day_key ] ) : 0,
				'time_off'  => isset( $time_off_by_day[ $day_key ] ) ? $time_off_by_day[ $day_key ] : array(),
			);
			$current = $current->modify( '+1 day' );
		}

		return rest_ensure_response(
			array(
				'success'    => true,
				'view_type'  => $view_type,
				'date_start' => $date_start,
				'date_end'   => $date_end,
				'staff'      => $staff,
				'days'       => $days,
			)
		);
	}

	/**
	 * Get initials from full name.
	 *
	 * @param string $full_name Full name.
	 * @return string
	 */
	private function get_initials( $full_name ) {
		$full_name = trim( (string) $full_name );
		if ( '' === $full_name ) {
			return '??';
		}

		$parts = preg_split( '/\s+/', $full_name );
		if ( empty( $parts ) ) {
			return '??';
		}

		if ( 1 === count( $parts ) ) {
			return strtoupper( substr( (string) $parts[0], 0, 2 ) );
		}

		return strtoupper( substr( (string) $parts[0], 0, 1 ) . substr( (string) $parts[ count( $parts ) - 1 ], 0, 1 ) );
	}

	/**
	 * Check if a time-off block represents all day.
	 *
	 * @param string $start Start time.
	 * @param string $end   End time.
	 * @return bool
	 */
	private function is_all_day_time_off( $start, $end ) {
		$start = substr( $start, 0, 8 );
		$end   = substr( $end, 0, 8 );

		if ( '00:00:00' !== $start ) {
			return false;
		}

		return in_array( $end, array( '00:00:00', '23:59:00', '23:59:59' ), true );
	}

	/**
	 * Format a label for time-off entries.
	 *
	 * @param string $notes Raw notes field.
	 * @return string
	 */
	private function format_time_off_label( $notes ) {
		$notes = trim( (string) $notes );

		if ( '' === $notes ) {
			return 'Time Off';
		}

		if ( 0 === strpos( $notes, 'reason:' ) && false !== strpos( $notes, '|notes:' ) ) {
			$parts  = explode( '|notes:', $notes, 2 );
			$reason = isset( $parts[0] ) ? str_replace( 'reason:', '', $parts[0] ) : '';
			$text   = isset( $parts[1] ) ? trim( (string) $parts[1] ) : '';

			if ( '' !== $text ) {
				return $text;
			}

			$reason_labels = array(
				'vacation'    => 'Holiday',
				'sick_leave'  => 'Sick Leave',
				'lunch_break' => 'Lunch Break',
				'personal'    => 'Personal',
				'other'       => 'Time Off',
			);

			return isset( $reason_labels[ $reason ] ) ? $reason_labels[ $reason ] : 'Time Off';
		}

		return $notes;
	}
}

new Bookit_Team_Calendar_API();
