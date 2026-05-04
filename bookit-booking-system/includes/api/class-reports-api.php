<?php
/**
 * Reports REST API Controller
 *
 * Handles all dashboard reports endpoints.
 * Admin-only access for all endpoints.
 *
 * @package    Bookit_Booking_System
 * @subpackage Bookit_Booking_System/includes/api
 */

if ( ! defined( 'WPINC' ) ) {
	die;
}

class Bookit_Reports_API {

	const NAMESPACE = 'bookit/v1';
	// Security audit note: all queries that include external input use $wpdb->prepare().
	// Static queries that contain no external input are intentionally left unprepared.

	public function __construct() {
		add_action( 'rest_api_init', array( $this, 'register_routes' ) );
	}

	public function register_routes() {
		// Overview report.
		register_rest_route(
			self::NAMESPACE,
			'/dashboard/reports/overview',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'get_overview' ),
				'permission_callback' => array( $this, 'check_admin_permission' ),
			)
		);

		// Revenue report.
		register_rest_route(
			self::NAMESPACE,
			'/dashboard/reports/revenue',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'get_revenue_report' ),
				'permission_callback' => array( $this, 'check_admin_permission' ),
				'args'                => array(
					'date_from' => array(
						'required'          => false,
						'validate_callback' => function ( $param ) {
							if ( empty( $param ) ) {
								return true;
							}
							$timezone = new DateTimeZone( 'Europe/London' );
							$date     = DateTimeImmutable::createFromFormat( '!Y-m-d', (string) $param, $timezone );
							return $date && $date->format( 'Y-m-d' ) === $param;
						},
						'sanitize_callback' => 'sanitize_text_field',
					),
					'date_to'   => array(
						'required'          => false,
						'validate_callback' => function ( $param ) {
							if ( empty( $param ) ) {
								return true;
							}
							$timezone = new DateTimeZone( 'Europe/London' );
							$date     = DateTimeImmutable::createFromFormat( '!Y-m-d', (string) $param, $timezone );
							return $date && $date->format( 'Y-m-d' ) === $param;
						},
						'sanitize_callback' => 'sanitize_text_field',
					),
				),
			)
		);

		// Booking analytics report.
		register_rest_route(
			self::NAMESPACE,
			'/dashboard/reports/analytics',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'get_booking_analytics' ),
				'permission_callback' => array( $this, 'check_admin_permission' ),
				'args'                => array(
					'date_from' => array(
						'required'          => false,
						'validate_callback' => function ( $param ) {
							if ( empty( $param ) ) {
								return true;
							}
							$timezone = new DateTimeZone( 'Europe/London' );
							$date     = DateTimeImmutable::createFromFormat( '!Y-m-d', (string) $param, $timezone );
							return $date && $date->format( 'Y-m-d' ) === $param;
						},
						'sanitize_callback' => 'sanitize_text_field',
					),
					'date_to'   => array(
						'required'          => false,
						'validate_callback' => function ( $param ) {
							if ( empty( $param ) ) {
								return true;
							}
							$timezone = new DateTimeZone( 'Europe/London' );
							$date     = DateTimeImmutable::createFromFormat( '!Y-m-d', (string) $param, $timezone );
							return $date && $date->format( 'Y-m-d' ) === $param;
						},
						'sanitize_callback' => 'sanitize_text_field',
					),
				),
			)
		);

		// Staff performance report.
		register_rest_route(
			self::NAMESPACE,
			'/dashboard/reports/staff',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'get_staff_performance' ),
				'permission_callback' => array( $this, 'check_admin_permission' ),
				'args'                => array(
					'date_from' => array(
						'required'          => false,
						'type'              => 'string',
						'validate_callback' => function ( $param ) {
							if ( empty( $param ) ) {
								return true;
							}
							$timezone = new DateTimeZone( 'Europe/London' );
							$date     = DateTimeImmutable::createFromFormat( '!Y-m-d', (string) $param, $timezone );
							return $date && $date->format( 'Y-m-d' ) === $param;
						},
						'sanitize_callback' => 'sanitize_text_field',
					),
					'date_to'   => array(
						'required'          => false,
						'type'              => 'string',
						'validate_callback' => function ( $param ) {
							if ( empty( $param ) ) {
								return true;
							}
							$timezone = new DateTimeZone( 'Europe/London' );
							$date     = DateTimeImmutable::createFromFormat( '!Y-m-d', (string) $param, $timezone );
							return $date && $date->format( 'Y-m-d' ) === $param;
						},
						'sanitize_callback' => 'sanitize_text_field',
					),
				),
			)
		);

		// Staff detail report.
		register_rest_route(
			self::NAMESPACE,
			'/dashboard/reports/staff/(?P<staff_id>\d+)',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'get_staff_detail' ),
				'permission_callback' => array( $this, 'check_admin_permission' ),
				'args'                => array(
					'staff_id'  => array(
						'required'          => true,
						'type'              => 'integer',
						'validate_callback' => function ( $param ) {
							return is_numeric( $param ) && (int) $param > 0;
						},
						'sanitize_callback' => 'absint',
					),
					'date_from' => array(
						'required'          => false,
						'type'              => 'string',
						'validate_callback' => function ( $param ) {
							if ( empty( $param ) ) {
								return true;
							}
							$timezone = new DateTimeZone( 'Europe/London' );
							$date     = DateTimeImmutable::createFromFormat( '!Y-m-d', (string) $param, $timezone );
							return $date && $date->format( 'Y-m-d' ) === $param;
						},
						'sanitize_callback' => 'sanitize_text_field',
					),
					'date_to'   => array(
						'required'          => false,
						'type'              => 'string',
						'validate_callback' => function ( $param ) {
							if ( empty( $param ) ) {
								return true;
							}
							$timezone = new DateTimeZone( 'Europe/London' );
							$date     = DateTimeImmutable::createFromFormat( '!Y-m-d', (string) $param, $timezone );
							return $date && $date->format( 'Y-m-d' ) === $param;
						},
						'sanitize_callback' => 'sanitize_text_field',
					),
				),
			)
		);

		// Revenue CSV export.
		register_rest_route(
			self::NAMESPACE,
			'/dashboard/reports/revenue/export',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'export_revenue_csv' ),
				'permission_callback' => array( $this, 'check_admin_permission' ),
				'args'                => array(
					'date_from' => array(
						'required'          => false,
						'type'              => 'string',
						'sanitize_callback' => 'sanitize_text_field',
					),
					'date_to'   => array(
						'required'          => false,
						'type'              => 'string',
						'sanitize_callback' => 'sanitize_text_field',
					),
				),
			)
		);
	}

	/**
	 * GET /dashboard/reports/staff
	 *
	 * Returns staff performance metrics for selected date range.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response|WP_Error
	 */
	public function get_staff_performance( $request ) {
		global $wpdb;

		$date_range = $this->parse_report_date_range( $request );
		if ( is_wp_error( $date_range ) ) {
			return $date_range;
		}

		$date_from = $date_range['date_from'];
		$date_to   = $date_range['date_to'];

		$staff_list = $wpdb->get_results(
			"SELECT id, first_name, last_name, title, photo_url, created_at
			FROM {$wpdb->prefix}bookings_staff
			WHERE deleted_at IS NULL AND is_active = 1
			ORDER BY first_name ASC",
			ARRAY_A
		);

		$period_metrics_by_staff  = $this->get_staff_period_metrics_bulk( $date_from, $date_to );
		$all_time_totals_by_staff = $this->get_staff_all_time_totals_bulk();

		$staff_rows = array();

		foreach ( $staff_list as $staff ) {
			$staff_id = (int) $staff['id'];

			$period_metrics  = isset( $period_metrics_by_staff[ $staff_id ] ) ? $period_metrics_by_staff[ $staff_id ] : array(
				'bookings'          => 0,
				'completed'         => 0,
				'no_shows'          => 0,
				'no_show_rate'      => 0.0,
				'revenue'           => 0.0,
				'avg_booking_value' => 0.0,
			);
			$all_time_totals = isset( $all_time_totals_by_staff[ $staff_id ] ) ? $all_time_totals_by_staff[ $staff_id ] : array(
				'total_bookings_alltime' => 0,
				'total_revenue_alltime'  => 0.0,
			);

			$staff_rows[] = array(
				'id'                     => $staff_id,
				'name'                   => trim( $staff['first_name'] . ' ' . $staff['last_name'] ),
				'title'                  => isset( $staff['title'] ) ? (string) $staff['title'] : '',
				'photo_url'              => isset( $staff['photo_url'] ) ? (string) $staff['photo_url'] : '',
				'member_since'           => isset( $staff['created_at'] ) ? substr( (string) $staff['created_at'], 0, 10 ) : '',
				'bookings'               => $period_metrics['bookings'],
				'completed'              => $period_metrics['completed'],
				'no_shows'               => $period_metrics['no_shows'],
				'no_show_rate'           => $period_metrics['no_show_rate'],
				'revenue'                => $period_metrics['revenue'],
				'avg_booking_value'      => $period_metrics['avg_booking_value'],
				'total_bookings_alltime' => $all_time_totals['total_bookings_alltime'],
				'total_revenue_alltime'  => $all_time_totals['total_revenue_alltime'],
			);
		}

		return rest_ensure_response(
			array(
				'success'   => true,
				'date_from' => $date_from,
				'date_to'   => $date_to,
				'staff'     => $staff_rows,
			)
		);
	}

	/**
	 * Get period metrics for all staff in one pass.
	 *
	 * @param string $date_from Start date.
	 * @param string $date_to End date.
	 * @return array<int, array<string, int|float>>
	 */
	private function get_staff_period_metrics_bulk( $date_from, $date_to ) {
		global $wpdb;

		$counts_rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT
					staff_id,
					SUM(CASE WHEN status != 'cancelled' THEN 1 ELSE 0 END) AS bookings,
					SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) AS completed,
					SUM(CASE WHEN status = 'no_show' THEN 1 ELSE 0 END) AS no_shows
				FROM {$wpdb->prefix}bookings
				WHERE booking_date BETWEEN %s AND %s
					AND deleted_at IS NULL
				GROUP BY staff_id",
				$date_from,
				$date_to
			),
			ARRAY_A
		);

		$revenue_rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT
					b.staff_id,
					COALESCE(SUM(p.amount), 0) AS revenue
				FROM {$wpdb->prefix}bookings_payments p
				INNER JOIN {$wpdb->prefix}bookings b ON b.id = p.booking_id
				WHERE p.payment_status = 'completed'
					AND p.payment_type != 'refund'
					AND b.booking_date BETWEEN %s AND %s
					AND b.deleted_at IS NULL
				GROUP BY b.staff_id",
				$date_from,
				$date_to
			),
			ARRAY_A
		);

		$metrics_by_staff = array();

		foreach ( $counts_rows as $row ) {
			$staff_id                    = (int) $row['staff_id'];
			$bookings                    = (int) $row['bookings'];
			$completed                   = (int) $row['completed'];
			$no_shows                    = (int) $row['no_shows'];
			$metrics_by_staff[ $staff_id ] = array(
				'bookings'          => $bookings,
				'completed'         => $completed,
				'no_shows'          => $no_shows,
				'no_show_rate'      => $bookings > 0 ? round( ( $no_shows / $bookings ) * 100, 1 ) : 0.0,
				'revenue'           => 0.0,
				'avg_booking_value' => 0.0,
			);
		}

		foreach ( $revenue_rows as $row ) {
			$staff_id = (int) $row['staff_id'];
			$revenue  = (float) $row['revenue'];

			if ( ! isset( $metrics_by_staff[ $staff_id ] ) ) {
				$metrics_by_staff[ $staff_id ] = array(
					'bookings'          => 0,
					'completed'         => 0,
					'no_shows'          => 0,
					'no_show_rate'      => 0.0,
					'revenue'           => 0.0,
					'avg_booking_value' => 0.0,
				);
			}

			$metrics_by_staff[ $staff_id ]['revenue'] = $revenue;
			$completed                                = (int) $metrics_by_staff[ $staff_id ]['completed'];
			$metrics_by_staff[ $staff_id ]['avg_booking_value'] = $completed > 0 ? round( $revenue / $completed, 2 ) : 0.0;
		}

		return $metrics_by_staff;
	}

	/**
	 * Get all-time totals for all staff in one pass.
	 *
	 * @return array<int, array<string, int|float>>
	 */
	private function get_staff_all_time_totals_bulk() {
		global $wpdb;

		$bookings_rows = $wpdb->get_results(
			"SELECT
				staff_id,
				COUNT(*) AS total_bookings_alltime
			FROM {$wpdb->prefix}bookings
			WHERE deleted_at IS NULL
				AND status != 'cancelled'
			GROUP BY staff_id",
			ARRAY_A
		);

		$revenue_rows = $wpdb->get_results(
			"SELECT
				b.staff_id,
				COALESCE(SUM(p.amount), 0) AS total_revenue_alltime
			FROM {$wpdb->prefix}bookings_payments p
			INNER JOIN {$wpdb->prefix}bookings b ON b.id = p.booking_id
			WHERE p.payment_status = 'completed'
				AND p.payment_type != 'refund'
				AND b.deleted_at IS NULL
			GROUP BY b.staff_id",
			ARRAY_A
		);

		$totals_by_staff = array();

		foreach ( $bookings_rows as $row ) {
			$staff_id                   = (int) $row['staff_id'];
			$totals_by_staff[ $staff_id ] = array(
				'total_bookings_alltime' => (int) $row['total_bookings_alltime'],
				'total_revenue_alltime'  => 0.0,
			);
		}

		foreach ( $revenue_rows as $row ) {
			$staff_id = (int) $row['staff_id'];
			if ( ! isset( $totals_by_staff[ $staff_id ] ) ) {
				$totals_by_staff[ $staff_id ] = array(
					'total_bookings_alltime' => 0,
					'total_revenue_alltime'  => 0.0,
				);
			}

			$totals_by_staff[ $staff_id ]['total_revenue_alltime'] = (float) $row['total_revenue_alltime'];
		}

		return $totals_by_staff;
	}

	/**
	 * GET /dashboard/reports/staff/{id}
	 *
	 * Returns detailed staff metrics for selected date range.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response|WP_Error
	 */
	public function get_staff_detail( $request ) {
		global $wpdb;

		$staff_id = absint( $request->get_param( 'staff_id' ) );
		if ( $staff_id <= 0 ) {
			return new WP_Error(
				'invalid_staff_id',
				__( 'A valid staff ID is required.', 'bookit-booking-system' ),
				array( 'status' => 400 )
			);
		}

		$date_range = $this->parse_report_date_range( $request );
		if ( is_wp_error( $date_range ) ) {
			return $date_range;
		}

		$date_from = $date_range['date_from'];
		$date_to   = $date_range['date_to'];

		$staff = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT id, first_name, last_name, title, bio, photo_url, created_at
				FROM {$wpdb->prefix}bookings_staff
				WHERE id = %d AND deleted_at IS NULL",
				$staff_id
			),
			ARRAY_A
		);

		if ( ! $staff ) {
			return new WP_Error(
				'staff_not_found',
				__( 'Staff member not found.', 'bookit-booking-system' ),
				array( 'status' => 404 )
			);
		}

		$period_metrics  = $this->get_staff_period_metrics( $staff_id, $date_from, $date_to );
		$all_time_totals = $this->get_staff_all_time_totals( $staff_id );

		$by_service = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT
					MAX(s.name) AS service_name,
					COUNT(DISTINCT b.id) AS booking_count,
					COALESCE(SUM(p.amount), 0) AS revenue
				FROM {$wpdb->prefix}bookings b
				INNER JOIN {$wpdb->prefix}bookings_services s ON s.id = b.service_id
				LEFT JOIN {$wpdb->prefix}bookings_payments p
					ON p.booking_id = b.id AND p.payment_status = 'completed' AND p.payment_type != 'refund'
				WHERE b.staff_id = %d
					AND b.booking_date BETWEEN %s AND %s
					AND b.deleted_at IS NULL
					AND b.status != 'cancelled'
				GROUP BY b.service_id
				ORDER BY booking_count DESC",
				$staff_id,
				$date_from,
				$date_to
			),
			ARRAY_A
		);

		$weekly_trend_rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT
					YEAR(booking_date) AS yr,
					WEEK(booking_date, 1) AS wk,
					MIN(booking_date) AS week_start,
					COUNT(*) AS booking_count
				FROM {$wpdb->prefix}bookings
				WHERE staff_id = %d
					AND booking_date BETWEEN %s AND %s
					AND deleted_at IS NULL
					AND status != 'cancelled'
				GROUP BY YEAR(booking_date), WEEK(booking_date, 1)
				ORDER BY yr ASC, wk ASC",
				$staff_id,
				$date_from,
				$date_to
			),
			ARRAY_A
		);

		$tz           = new DateTimeZone( 'Europe/London' );
		$weekly_trend = array();
		foreach ( $weekly_trend_rows as $row ) {
			$week_start = DateTimeImmutable::createFromFormat( '!Y-m-d', (string) $row['week_start'], $tz );
			$week_label = $week_start ? sprintf( __( 'Week of %s', 'bookit-booking-system' ), $week_start->format( 'd/m' ) ) : sprintf( __( 'Week of %s', 'bookit-booking-system' ), (string) $row['week_start'] );

			$weekly_trend[] = array(
				'week_label'    => $week_label,
				'booking_count' => (int) $row['booking_count'],
			);
		}

		$today = ( new DateTimeImmutable( 'now', $tz ) )->format( 'Y-m-d' );
		$time_off_rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT id, specific_date, start_time, end_time, is_working, notes
				FROM {$wpdb->prefix}bookings_staff_working_hours
				WHERE staff_id = %d
					AND specific_date IS NOT NULL
					AND specific_date >= %s
					AND is_working = 0
				ORDER BY specific_date ASC
				LIMIT 20",
				$staff_id,
				$today
			),
			ARRAY_A
		);

		$formatted_by_service = array_map(
			function ( $row ) {
				return array(
					'service_name'  => isset( $row['service_name'] ) ? (string) $row['service_name'] : '',
					'booking_count' => (int) $row['booking_count'],
					'revenue'       => (float) $row['revenue'],
				);
			},
			$by_service
		);

		$formatted_time_off = array_map(
			function ( $row ) {
				return array(
					'id'            => (int) $row['id'],
					'specific_date' => isset( $row['specific_date'] ) ? (string) $row['specific_date'] : '',
					'start_time'    => isset( $row['start_time'] ) ? (string) $row['start_time'] : '',
					'end_time'      => isset( $row['end_time'] ) ? (string) $row['end_time'] : '',
					'notes'         => isset( $row['notes'] ) ? (string) $row['notes'] : '',
				);
			},
			$time_off_rows
		);

		$staff_payload = array(
			'id'                     => (int) $staff['id'],
			'name'                   => trim( $staff['first_name'] . ' ' . $staff['last_name'] ),
			'title'                  => isset( $staff['title'] ) ? (string) $staff['title'] : '',
			'bio'                    => isset( $staff['bio'] ) ? (string) $staff['bio'] : '',
			'photo_url'              => isset( $staff['photo_url'] ) ? (string) $staff['photo_url'] : '',
			'member_since'           => isset( $staff['created_at'] ) ? substr( (string) $staff['created_at'], 0, 10 ) : '',
			'bookings'               => $period_metrics['bookings'],
			'completed'              => $period_metrics['completed'],
			'no_shows'               => $period_metrics['no_shows'],
			'no_show_rate'           => $period_metrics['no_show_rate'],
			'revenue'                => $period_metrics['revenue'],
			'avg_booking_value'      => $period_metrics['avg_booking_value'],
			'total_bookings_alltime' => $all_time_totals['total_bookings_alltime'],
			'total_revenue_alltime'  => $all_time_totals['total_revenue_alltime'],
			'by_service'             => $formatted_by_service,
			'weekly_trend'           => $weekly_trend,
			'time_off'               => $formatted_time_off,
		);

		return rest_ensure_response(
			array(
				'success'   => true,
				'date_from' => $date_from,
				'date_to'   => $date_to,
				'staff'     => $staff_payload,
			)
		);
	}

	/**
	 * Check if user has admin permission.
	 * Only admins can manage services.
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
	 * GET /dashboard/reports/overview
	 *
	 * Returns summary metrics for three periods:
	 * this_week, this_month, all_time.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response
	 */
	public function get_overview( $request ) {
		$tz  = new DateTimeZone( 'Europe/London' );
		$now = new DateTimeImmutable( 'now', $tz );

		// This week: Monday to Sunday.
		$week_start = $now->modify( 'monday this week' )->format( 'Y-m-d' );
		$week_end   = $now->modify( 'sunday this week' )->format( 'Y-m-d' );

		// This month: first to last day.
		$month_start = $now->format( 'Y-m-01' );
		$month_end   = $now->format( 'Y-m-t' );

		$periods = array(
			'this_week'  => array( $week_start, $week_end ),
			'this_month' => array( $month_start, $month_end ),
		);

		$result = array();

		foreach ( $periods as $key => $dates ) {
			$result[ $key ] = $this->get_period_metrics( $dates[0], $dates[1] );
		}

		// All time — no date filter.
		$result['all_time'] = $this->get_period_metrics( null, null );

		// Revenue trend for bar chart.
		$result['revenue_trend'] = array(
			'this_week'  => $this->get_daily_revenue( $week_start, $week_end ),
			'this_month' => $this->get_weekly_revenue( $month_start, $month_end ),
		);

		return rest_ensure_response(
			array(
				'success' => true,
				'data'    => $result,
			)
		);
	}

	/**
	 * GET /dashboard/reports/revenue
	 *
	 * Returns revenue report data for selected date range.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response
	 */
	public function get_revenue_report( $request ) {
		global $wpdb;

		$tz  = new DateTimeZone( 'Europe/London' );
		$now = new DateTimeImmutable( 'now', $tz );

		$date_from_param = $request->get_param( 'date_from' );
		$date_to_param   = $request->get_param( 'date_to' );

		$date_from = ! empty( $date_from_param ) ? sanitize_text_field( $date_from_param ) : $now->format( 'Y-m-01' );
		$date_to   = ! empty( $date_to_param ) ? sanitize_text_field( $date_to_param ) : $now->format( 'Y-m-d' );

		$total_revenue = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COALESCE(SUM(p.amount), 0)
				FROM {$wpdb->prefix}bookings_payments p
				INNER JOIN {$wpdb->prefix}bookings b ON b.id = p.booking_id
				WHERE p.payment_status = 'completed'
					AND p.payment_type != 'refund'
					AND b.booking_date BETWEEN %s AND %s
					AND b.deleted_at IS NULL",
				$date_from,
				$date_to
			)
		);

		$deposits = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COALESCE(SUM(p.amount), 0)
				FROM {$wpdb->prefix}bookings_payments p
				INNER JOIN {$wpdb->prefix}bookings b ON b.id = p.booking_id
				WHERE p.payment_status = 'completed'
					AND p.payment_type = 'deposit'
					AND b.booking_date BETWEEN %s AND %s
					AND b.deleted_at IS NULL",
				$date_from,
				$date_to
			)
		);

		$balance_payments = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COALESCE(SUM(p.amount), 0)
				FROM {$wpdb->prefix}bookings_payments p
				INNER JOIN {$wpdb->prefix}bookings b ON b.id = p.booking_id
				WHERE p.payment_status = 'completed'
					AND p.payment_type = 'full_payment'
					AND b.booking_date BETWEEN %s AND %s
					AND b.deleted_at IS NULL",
				$date_from,
				$date_to
			)
		);

		$refunds = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COALESCE(SUM(p.refund_amount), 0)
				FROM {$wpdb->prefix}bookings_payments p
				INNER JOIN {$wpdb->prefix}bookings b ON b.id = p.booking_id
				WHERE p.payment_status IN ('refunded', 'partially_refunded')
					AND b.booking_date BETWEEN %s AND %s
					AND b.deleted_at IS NULL",
				$date_from,
				$date_to
			)
		);

		$net_revenue = (float) $total_revenue - (float) $refunds;

		$by_service = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT
					b.service_id,
					MAX(s.name) AS service_name,
					COUNT(DISTINCT b.id) AS booking_count,
					COALESCE(SUM(p.amount), 0) AS total_revenue,
					CASE WHEN COUNT(DISTINCT b.id) > 0
						THEN COALESCE(SUM(p.amount), 0) / COUNT(DISTINCT b.id)
						ELSE 0 END AS avg_price
				FROM {$wpdb->prefix}bookings b
				INNER JOIN {$wpdb->prefix}bookings_services s ON s.id = b.service_id
				LEFT JOIN {$wpdb->prefix}bookings_payments p
					ON p.booking_id = b.id
					AND p.payment_status = 'completed'
					AND p.payment_type != 'refund'
				WHERE b.booking_date BETWEEN %s AND %s
					AND b.deleted_at IS NULL
					AND b.status != 'cancelled'
				GROUP BY b.service_id
				ORDER BY total_revenue DESC",
				$date_from,
				$date_to
			),
			ARRAY_A
		);

		$by_staff = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT
					CONCAT(st.first_name, ' ', st.last_name) AS staff_name,
					COUNT(DISTINCT b.id) AS booking_count,
					COALESCE(SUM(p.amount), 0) AS total_revenue,
					CASE WHEN COUNT(DISTINCT b.id) > 0
						THEN COALESCE(SUM(p.amount), 0) / COUNT(DISTINCT b.id)
						ELSE 0 END AS avg_per_booking
				FROM {$wpdb->prefix}bookings b
				INNER JOIN {$wpdb->prefix}bookings_staff st ON st.id = b.staff_id
				LEFT JOIN {$wpdb->prefix}bookings_payments p
					ON p.booking_id = b.id AND p.payment_status = 'completed' AND p.payment_type != 'refund'
				WHERE b.booking_date BETWEEN %s AND %s
					AND b.deleted_at IS NULL
					AND b.status != 'cancelled'
				GROUP BY b.staff_id, st.first_name, st.last_name
				ORDER BY total_revenue DESC",
				$date_from,
				$date_to
			),
			ARRAY_A
		);

		$by_method = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT
					p.payment_method,
					COUNT(DISTINCT b.id) AS booking_count,
					COALESCE(SUM(p.amount), 0) AS total_revenue
				FROM {$wpdb->prefix}bookings_payments p
				INNER JOIN {$wpdb->prefix}bookings b ON b.id = p.booking_id
				WHERE p.payment_status = 'completed'
					AND p.payment_type != 'refund'
					AND b.booking_date BETWEEN %s AND %s
					AND b.deleted_at IS NULL
				GROUP BY p.payment_method
				ORDER BY total_revenue DESC",
				$date_from,
				$date_to
			),
			ARRAY_A
		);

		$formatted_by_service = array_map(
			function ( $row ) {
				return array(
					'service_id'     => (int) $row['service_id'],
					'service_name'   => isset( $row['service_name'] ) ? (string) $row['service_name'] : '',
					'booking_count'  => (int) $row['booking_count'],
					'total_revenue'  => (float) $row['total_revenue'],
					'avg_price'      => (float) $row['avg_price'],
				);
			},
			$by_service
		);

		$formatted_by_staff = array_map(
			function ( $row ) {
				return array(
					'staff_name'       => isset( $row['staff_name'] ) ? (string) $row['staff_name'] : '',
					'booking_count'    => (int) $row['booking_count'],
					'total_revenue'    => (float) $row['total_revenue'],
					'avg_per_booking'  => (float) $row['avg_per_booking'],
				);
			},
			$by_staff
		);

		$formatted_by_method = array_map(
			function ( $row ) {
				return array(
					'payment_method' => isset( $row['payment_method'] ) ? (string) $row['payment_method'] : '',
					'booking_count'  => (int) $row['booking_count'],
					'total_revenue'  => (float) $row['total_revenue'],
				);
			},
			$by_method
		);

		$revenue_trend = $this->get_daily_revenue( $date_from, $date_to );
		$today         = $now->format( 'Y-m-d' );

		return rest_ensure_response(
			array(
				'success'           => true,
				'date_from'         => $date_from,
				'date_to'           => $date_to,
				'is_today_in_range' => ( $today >= $date_from && $today <= $date_to ),
				'summary'           => array(
					'total_revenue'    => (float) $total_revenue,
					'deposits'         => (float) $deposits,
					'balance_payments' => (float) $balance_payments,
					'refunds'          => (float) $refunds,
					'net_revenue'      => (float) $net_revenue,
				),
				'by_service'        => $formatted_by_service,
				'by_staff'          => $formatted_by_staff,
				'by_payment_method' => $formatted_by_method,
				'revenue_trend'     => $revenue_trend,
			)
		);
	}

	/**
	 * GET /dashboard/reports/analytics
	 *
	 * Returns booking analytics for selected date range.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response|WP_Error
	 */
	public function get_booking_analytics( $request ) {
		global $wpdb;

		$tz  = new DateTimeZone( 'Europe/London' );
		$now = new DateTimeImmutable( 'now', $tz );

		$date_from_param = $request->get_param( 'date_from' );
		$date_to_param   = $request->get_param( 'date_to' );

		$date_from = ! empty( $date_from_param ) ? sanitize_text_field( $date_from_param ) : $now->modify( '-30 days' )->format( 'Y-m-d' );
		$date_to   = ! empty( $date_to_param ) ? sanitize_text_field( $date_to_param ) : $now->format( 'Y-m-d' );

		if ( $date_from > $date_to ) {
			return new WP_Error(
				'invalid_date_range',
				__( 'Start date must be on or before end date.', 'bookit-booking-system' ),
				array( 'status' => 400 )
			);
		}

		$bookings_table = $wpdb->prefix . 'bookings';

		$total = (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$bookings_table}
				WHERE booking_date BETWEEN %s AND %s AND deleted_at IS NULL",
				$date_from,
				$date_to
			)
		);

		$completed = (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$bookings_table}
				WHERE status = 'completed' AND booking_date BETWEEN %s AND %s AND deleted_at IS NULL",
				$date_from,
				$date_to
			)
		);

		$cancelled = (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$bookings_table}
				WHERE status = 'cancelled' AND booking_date BETWEEN %s AND %s AND deleted_at IS NULL",
				$date_from,
				$date_to
			)
		);

		$no_show = (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$bookings_table}
				WHERE status = 'no_show' AND booking_date BETWEEN %s AND %s AND deleted_at IS NULL",
				$date_from,
				$date_to
			)
		);

		$completion_rate   = $total > 0 ? round( ( $completed / $total ) * 100, 1 ) : 0.0;
		$cancellation_rate = $total > 0 ? round( ( $cancelled / $total ) * 100, 1 ) : 0.0;
		$no_show_rate      = $total > 0 ? round( ( $no_show / $total ) * 100, 1 ) : 0.0;

		$by_dow = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT
					DAYOFWEEK(booking_date) AS dow_mysql,
					COUNT(*) AS booking_count
				FROM {$bookings_table}
				WHERE booking_date BETWEEN %s AND %s
					AND deleted_at IS NULL
					AND status NOT IN ('cancelled')
				GROUP BY DAYOFWEEK(booking_date)
				ORDER BY DAYOFWEEK(booking_date) ASC",
				$date_from,
				$date_to
			),
			ARRAY_A
		);

		$day_labels = array( 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun' );
		$dow_to_uk  = array(
			2 => 0,
			3 => 1,
			4 => 2,
			5 => 3,
			6 => 4,
			7 => 5,
			1 => 6,
		);
		$dow_data   = array_fill( 0, 7, 0 );

		foreach ( $by_dow as $row ) {
			$dow_mysql = (int) $row['dow_mysql'];
			if ( isset( $dow_to_uk[ $dow_mysql ] ) ) {
				$dow_data[ $dow_to_uk[ $dow_mysql ] ] = (int) $row['booking_count'];
			}
		}

		$by_hour = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT
					HOUR(start_time) AS hour,
					COUNT(*) AS booking_count
				FROM {$bookings_table}
				WHERE booking_date BETWEEN %s AND %s
					AND deleted_at IS NULL
					AND status NOT IN ('cancelled')
				GROUP BY HOUR(start_time)
				ORDER BY HOUR(start_time) ASC",
				$date_from,
				$date_to
			),
			ARRAY_A
		);

		$hour_labels = array();
		$hour_data   = array();
		for ( $hour = 7; $hour <= 21; $hour++ ) {
			$hour_labels[] = sprintf( '%02d:00', $hour );
			$hour_data[]   = 0;
		}

		foreach ( $by_hour as $row ) {
			$hour = (int) $row['hour'];
			if ( $hour >= 7 && $hour <= 21 ) {
				$hour_data[ $hour - 7 ] = (int) $row['booking_count'];
			}
		}

		$heatmap_raw = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT
					DAYOFWEEK(booking_date) AS dow_mysql,
					HOUR(start_time) AS hour,
					COUNT(*) AS booking_count
				FROM {$bookings_table}
				WHERE booking_date BETWEEN %s AND %s
					AND deleted_at IS NULL
					AND status NOT IN ('cancelled')
				GROUP BY DAYOFWEEK(booking_date), HOUR(start_time)",
				$date_from,
				$date_to
			),
			ARRAY_A
		);

		$heatmap_lookup = array();
		foreach ( $heatmap_raw as $row ) {
			$dow_mysql = (int) $row['dow_mysql'];
			$hour      = (int) $row['hour'];
			if ( isset( $dow_to_uk[ $dow_mysql ] ) && $hour >= 7 && $hour <= 21 ) {
				$day_key                    = $day_labels[ $dow_to_uk[ $dow_mysql ] ];
				$hour_key                   = sprintf( '%02d:00', $hour );
				$heatmap_lookup[ $day_key . '|' . $hour_key ] = (int) $row['booking_count'];
			}
		}

		$heatmap = array();
		foreach ( $day_labels as $day_label ) {
			for ( $hour = 7; $hour <= 21; $hour++ ) {
				$hour_key  = sprintf( '%02d:00', $hour );
				$lookup_key = $day_label . '|' . $hour_key;
				$heatmap[] = array(
					'day'   => $day_label,
					'hour'  => $hour_key,
					'count' => isset( $heatmap_lookup[ $lookup_key ] ) ? (int) $heatmap_lookup[ $lookup_key ] : 0,
				);
			}
		}

		$lead_times = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT
					DATEDIFF(booking_date, DATE(created_at)) AS lead_days
				FROM {$bookings_table}
				WHERE booking_date BETWEEN %s AND %s
					AND deleted_at IS NULL
					AND status NOT IN ('cancelled')
					AND DATEDIFF(booking_date, DATE(created_at)) >= 0",
				$date_from,
				$date_to
			),
			ARRAY_A
		);

		$buckets = array(
			'same_day'            => 0,
			'one_to_three'        => 0,
			'four_to_seven'       => 0,
			'eight_to_fourteen'   => 0,
			'fifteen_plus'        => 0,
		);
		$total_lead = 0;
		$sum_lead   = 0;

		foreach ( $lead_times as $row ) {
			$lead_days = (int) $row['lead_days'];
			++$total_lead;
			$sum_lead += $lead_days;

			if ( 0 === $lead_days ) {
				++$buckets['same_day'];
			} elseif ( $lead_days <= 3 ) {
				++$buckets['one_to_three'];
			} elseif ( $lead_days <= 7 ) {
				++$buckets['four_to_seven'];
			} elseif ( $lead_days <= 14 ) {
				++$buckets['eight_to_fourteen'];
			} else {
				++$buckets['fifteen_plus'];
			}
		}

		$avg_lead_days = $total_lead > 0 ? round( $sum_lead / $total_lead, 1 ) : 0.0;

		$daily_count_rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT
					DATE(booking_date) AS date,
					COUNT(*) AS booking_count
				FROM {$bookings_table}
				WHERE booking_date BETWEEN %s AND %s
					AND deleted_at IS NULL
					AND status NOT IN ('cancelled')
				GROUP BY DATE(booking_date)
				ORDER BY DATE(booking_date) ASC",
				$date_from,
				$date_to
			),
			ARRAY_A
		);

		$count_by_date = array();
		foreach ( $daily_count_rows as $row ) {
			$count_by_date[ $row['date'] ] = (int) $row['booking_count'];
		}

		$start     = new DateTimeImmutable( $date_from, $tz );
		$end       = new DateTimeImmutable( $date_to, $tz );
		$end_plus  = $end->modify( '+1 day' );
		$interval  = new DateInterval( 'P1D' );
		$period    = new DatePeriod( $start, $interval, $end_plus );

		$daily_trend = array();
		foreach ( $period as $date_obj ) {
			$date_key      = $date_obj->format( 'Y-m-d' );
			$daily_trend[] = array(
				'date'  => $date_key,
				'count' => isset( $count_by_date[ $date_key ] ) ? (int) $count_by_date[ $date_key ] : 0,
			);
		}

		return rest_ensure_response(
			array(
				'success'        => true,
				'date_from'      => $date_from,
				'date_to'        => $date_to,
				'summary'        => array(
					'total_bookings'    => $total,
					'completed'         => $completed,
					'cancelled'         => $cancelled,
					'no_show'           => $no_show,
					'completion_rate'   => (float) $completion_rate,
					'cancellation_rate' => (float) $cancellation_rate,
					'no_show_rate'      => (float) $no_show_rate,
					'avg_lead_days'     => (float) $avg_lead_days,
				),
				'by_day_of_week' => array(
					'labels' => $day_labels,
					'data'   => $dow_data,
				),
				'by_hour'        => array(
					'labels' => $hour_labels,
					'data'   => $hour_data,
				),
				'heatmap'        => $heatmap,
				'lead_time'      => array(
					'avg_days' => (float) $avg_lead_days,
					'buckets'  => $buckets,
				),
				'daily_trend'    => $daily_trend,
			)
		);
	}

	/**
	 * GET /dashboard/reports/revenue/export
	 *
	 * Export revenue report as CSV.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response|WP_Error
	 */
	public function export_revenue_csv( $request ) {
		global $wpdb;

		$tz  = new DateTimeZone( 'Europe/London' );
		$now = new DateTimeImmutable( 'now', $tz );

		$date_from = $request->get_param( 'date_from' );
		$date_to   = $request->get_param( 'date_to' );

		if ( empty( $date_from ) ) {
			$date_from = $now->format( 'Y-m-01' );
		}
		if ( empty( $date_to ) ) {
			$date_to = $now->format( 'Y-m-d' );
		}

		// --- Summary metrics ---
		$total_revenue = (float) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COALESCE(SUM(p.amount), 0)
				FROM {$wpdb->prefix}bookings_payments p
				INNER JOIN {$wpdb->prefix}bookings b ON b.id = p.booking_id
				WHERE p.payment_status = 'completed'
					AND p.payment_type != 'refund'
					AND b.booking_date BETWEEN %s AND %s
					AND b.deleted_at IS NULL",
				$date_from,
				$date_to
			)
		);

		$deposits = (float) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COALESCE(SUM(p.amount), 0)
				FROM {$wpdb->prefix}bookings_payments p
				INNER JOIN {$wpdb->prefix}bookings b ON b.id = p.booking_id
				WHERE p.payment_status = 'completed'
					AND p.payment_type = 'deposit'
					AND b.booking_date BETWEEN %s AND %s
					AND b.deleted_at IS NULL",
				$date_from,
				$date_to
			)
		);

		$balance_payments = (float) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COALESCE(SUM(p.amount), 0)
				FROM {$wpdb->prefix}bookings_payments p
				INNER JOIN {$wpdb->prefix}bookings b ON b.id = p.booking_id
				WHERE p.payment_status = 'completed'
					AND p.payment_type = 'full_payment'
					AND b.booking_date BETWEEN %s AND %s
					AND b.deleted_at IS NULL",
				$date_from,
				$date_to
			)
		);

		$refunds = (float) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COALESCE(SUM(p.refund_amount), 0)
				FROM {$wpdb->prefix}bookings_payments p
				INNER JOIN {$wpdb->prefix}bookings b ON b.id = p.booking_id
				WHERE p.payment_status IN ('refunded', 'partially_refunded')
					AND b.booking_date BETWEEN %s AND %s
					AND b.deleted_at IS NULL",
				$date_from,
				$date_to
			)
		);

		$net_revenue = $total_revenue - $refunds;

		// --- By service ---
		$by_service = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT
					b.service_id,
					MAX(s.name) AS service_name,
					COUNT(DISTINCT b.id) AS booking_count,
					COALESCE(SUM(p.amount), 0) AS total_revenue,
					CASE WHEN COUNT(DISTINCT b.id) > 0
						THEN COALESCE(SUM(p.amount), 0) / COUNT(DISTINCT b.id)
						ELSE 0 END AS avg_price
				FROM {$wpdb->prefix}bookings b
				INNER JOIN {$wpdb->prefix}bookings_services s ON s.id = b.service_id
				LEFT JOIN {$wpdb->prefix}bookings_payments p
					ON p.booking_id = b.id
					AND p.payment_status = 'completed'
					AND p.payment_type != 'refund'
				WHERE b.booking_date BETWEEN %s AND %s
					AND b.deleted_at IS NULL
					AND b.status != 'cancelled'
				GROUP BY b.service_id
				ORDER BY total_revenue DESC",
				$date_from,
				$date_to
			),
			ARRAY_A
		);

		// --- By staff ---
		$by_staff = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT
					CONCAT(st.first_name, ' ', st.last_name) AS staff_name,
					COUNT(DISTINCT b.id) AS booking_count,
					COALESCE(SUM(p.amount), 0) AS total_revenue,
					CASE WHEN COUNT(DISTINCT b.id) > 0
						THEN COALESCE(SUM(p.amount), 0) / COUNT(DISTINCT b.id)
						ELSE 0 END AS avg_per_booking
				FROM {$wpdb->prefix}bookings b
				INNER JOIN {$wpdb->prefix}bookings_staff st ON st.id = b.staff_id
				LEFT JOIN {$wpdb->prefix}bookings_payments p
					ON p.booking_id = b.id
					AND p.payment_status = 'completed'
					AND p.payment_type != 'refund'
				WHERE b.booking_date BETWEEN %s AND %s
					AND b.deleted_at IS NULL
					AND b.status != 'cancelled'
				GROUP BY b.staff_id
				ORDER BY total_revenue DESC",
				$date_from,
				$date_to
			),
			ARRAY_A
		);

		// --- Build CSV using fputcsv into memory stream ---
		// fputcsv handles all quoting automatically - do NOT manually add quotes.
		$stream = fopen( 'php://temp', 'r+' );

		// Summary section.
		fputcsv( $stream, array( 'Date From', 'Date To', 'Total Revenue', 'Deposits', 'Balance Payments', 'Refunds', 'Net Revenue' ) );
		fputcsv(
			$stream,
			array(
				$date_from,
				$date_to,
				number_format( $total_revenue, 2, '.', '' ),
				number_format( $deposits, 2, '.', '' ),
				number_format( $balance_payments, 2, '.', '' ),
				number_format( $refunds, 2, '.', '' ),
				number_format( $net_revenue, 2, '.', '' ),
			)
		);

		// Blank line.
		fputcsv( $stream, array( '' ) );

		// Service section.
		fputcsv( $stream, array( 'Service Name', 'Bookings', 'Total Revenue', 'Avg Price' ) );
		foreach ( $by_service as $row ) {
			fputcsv(
				$stream,
				array(
					$row['service_name'],
					(int) $row['booking_count'],
					number_format( (float) $row['total_revenue'], 2, '.', '' ),
					number_format( (float) $row['avg_price'], 2, '.', '' ),
				)
			);
		}

		// Blank line.
		fputcsv( $stream, array( '' ) );

		// Staff section.
		fputcsv( $stream, array( 'Staff Member', 'Bookings', 'Total Revenue', 'Avg per Booking' ) );
		foreach ( $by_staff as $row ) {
			fputcsv(
				$stream,
				array(
					$row['staff_name'],
					(int) $row['booking_count'],
					number_format( (float) $row['total_revenue'], 2, '.', '' ),
					number_format( (float) $row['avg_per_booking'], 2, '.', '' ),
				)
			);
		}

		// Read stream into string.
		rewind( $stream );
		$csv_string = stream_get_contents( $stream );
		fclose( $stream );

		// Output CSV directly, bypassing WP REST JSON encoding.
		$filename = 'revenue-report-' . $date_from . '-to-' . $date_to . '.csv';

		// Add a WordPress action to send headers and output before REST API responds.
		add_filter(
			'rest_pre_serve_request',
			function( $served ) use ( $csv_string, $filename ) {
				if ( ! $served ) {
					// Skip headers and output during PHPUnit test runs to prevent
					// "Cannot modify header information" warnings and stdout pollution.
					if ( defined( 'WP_TESTS_PHPUNIT_POLYFILLS_PATH' ) || defined( 'WP_TESTS_DIR' ) ) {
						return true;
					}
					header( 'Content-Type: text/csv; charset=utf-8' );
					header( 'Content-Disposition: attachment; filename="' . $filename . '"' );
					header( 'Cache-Control: no-cache, no-store, must-revalidate' );
					header( 'Content-Length: ' . strlen( $csv_string ) );
					echo $csv_string; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
				}
				return true; // Returning true tells WP REST not to send its own response.
			}
		);

		// Return a minimal WP_REST_Response - it won't be sent because rest_pre_serve_request returns true.
		return new WP_REST_Response( null, 200 );
	}

	/**
	 * Parse and validate date range for reports.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return array|WP_Error
	 */
	private function parse_report_date_range( $request ) {
		$tz  = new DateTimeZone( 'Europe/London' );
		$now = new DateTimeImmutable( 'now', $tz );

		$date_from_param = $request->get_param( 'date_from' );
		$date_to_param   = $request->get_param( 'date_to' );

		$date_from = ! empty( $date_from_param ) ? sanitize_text_field( $date_from_param ) : $now->format( 'Y-m-01' );
		$date_to   = ! empty( $date_to_param ) ? sanitize_text_field( $date_to_param ) : $now->format( 'Y-m-d' );

		if ( ! $this->is_valid_ymd_date( $date_from, $tz ) || ! $this->is_valid_ymd_date( $date_to, $tz ) ) {
			return new WP_Error(
				'invalid_date_format',
				__( 'Dates must use the YYYY-MM-DD format.', 'bookit-booking-system' ),
				array( 'status' => 400 )
			);
		}

		if ( $date_from > $date_to ) {
			return new WP_Error(
				'invalid_date_range',
				__( 'Start date must be on or before end date.', 'bookit-booking-system' ),
				array( 'status' => 400 )
			);
		}

		return array(
			'date_from' => $date_from,
			'date_to'   => $date_to,
		);
	}

	/**
	 * Validate YYYY-MM-DD date value.
	 *
	 * @param string       $date Date string.
	 * @param DateTimeZone $tz Timezone.
	 * @return bool
	 */
	private function is_valid_ymd_date( $date, $tz ) {
		if ( empty( $date ) ) {
			return false;
		}

		$parsed = DateTimeImmutable::createFromFormat( '!Y-m-d', (string) $date, $tz );
		return $parsed && $parsed->format( 'Y-m-d' ) === $date;
	}

	/**
	 * Get period metrics for a single staff member.
	 *
	 * @param int    $staff_id Staff ID.
	 * @param string $date_from Start date.
	 * @param string $date_to End date.
	 * @return array
	 */
	private function get_staff_period_metrics( $staff_id, $date_from, $date_to ) {
		global $wpdb;

		$bookings = (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$wpdb->prefix}bookings
				WHERE staff_id = %d AND status != 'cancelled'
					AND booking_date BETWEEN %s AND %s AND deleted_at IS NULL",
				$staff_id,
				$date_from,
				$date_to
			)
		);

		$completed = (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$wpdb->prefix}bookings
				WHERE staff_id = %d AND status = 'completed'
					AND booking_date BETWEEN %s AND %s AND deleted_at IS NULL",
				$staff_id,
				$date_from,
				$date_to
			)
		);

		$no_shows = (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$wpdb->prefix}bookings
				WHERE staff_id = %d AND status = 'no_show'
					AND booking_date BETWEEN %s AND %s AND deleted_at IS NULL",
				$staff_id,
				$date_from,
				$date_to
			)
		);

		$revenue = (float) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COALESCE(SUM(p.amount), 0)
				FROM {$wpdb->prefix}bookings_payments p
				INNER JOIN {$wpdb->prefix}bookings b ON b.id = p.booking_id
				WHERE b.staff_id = %d
					AND p.payment_status = 'completed'
					AND p.payment_type != 'refund'
					AND b.booking_date BETWEEN %s AND %s
					AND b.deleted_at IS NULL",
				$staff_id,
				$date_from,
				$date_to
			)
		);

		$no_show_rate      = $bookings > 0 ? round( ( $no_shows / $bookings ) * 100, 1 ) : 0.0;
		$avg_booking_value = $completed > 0 ? round( (float) $revenue / $completed, 2 ) : 0.0;

		return array(
			'bookings'          => $bookings,
			'completed'         => $completed,
			'no_shows'          => $no_shows,
			'no_show_rate'      => (float) $no_show_rate,
			'revenue'           => (float) $revenue,
			'avg_booking_value' => (float) $avg_booking_value,
		);
	}

	/**
	 * Get all-time totals for a single staff member.
	 *
	 * @param int $staff_id Staff ID.
	 * @return array
	 */
	private function get_staff_all_time_totals( $staff_id ) {
		global $wpdb;

		$total_bookings_alltime = (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$wpdb->prefix}bookings
				WHERE staff_id = %d AND deleted_at IS NULL AND status != 'cancelled'",
				$staff_id
			)
		);

		$total_revenue_alltime = (float) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COALESCE(SUM(p.amount), 0)
				FROM {$wpdb->prefix}bookings_payments p
				INNER JOIN {$wpdb->prefix}bookings b ON b.id = p.booking_id
				WHERE b.staff_id = %d
					AND p.payment_status = 'completed'
					AND p.payment_type != 'refund'
					AND b.deleted_at IS NULL",
				$staff_id
			)
		);

		return array(
			'total_bookings_alltime' => $total_bookings_alltime,
			'total_revenue_alltime'  => $total_revenue_alltime,
		);
	}

	/**
	 * Get summary metrics for a date range.
	 *
	 * @param string|null $date_from Start date YYYY-MM-DD.
	 * @param string|null $date_to End date YYYY-MM-DD.
	 * @return array
	 */
	private function get_period_metrics( $date_from, $date_to ) {
		global $wpdb;

		$has_dates = ( null !== $date_from && null !== $date_to );

		// Total bookings (exclude cancelled).
		if ( $has_dates ) {
			$total_bookings = (int) $wpdb->get_var(
				$wpdb->prepare(
					"SELECT COUNT(*) FROM {$wpdb->prefix}bookings
					WHERE status != 'cancelled'
					AND deleted_at IS NULL
					AND booking_date BETWEEN %s AND %s",
					$date_from,
					$date_to
				)
			);
		} else {
			$total_bookings = (int) $wpdb->get_var(
				"SELECT COUNT(*) FROM {$wpdb->prefix}bookings
				WHERE status != 'cancelled'
				AND deleted_at IS NULL"
			);
		}

		// Total revenue from completed payments.
		if ( $has_dates ) {
			$total_revenue = (float) $wpdb->get_var(
				$wpdb->prepare(
					"SELECT COALESCE(SUM(p.amount), 0)
					FROM {$wpdb->prefix}bookings_payments p
					INNER JOIN {$wpdb->prefix}bookings b ON b.id = p.booking_id
					WHERE p.payment_status = 'completed'
					AND b.deleted_at IS NULL
					AND b.booking_date BETWEEN %s AND %s",
					$date_from,
					$date_to
				)
			);
		} else {
			$total_revenue = (float) $wpdb->get_var(
				"SELECT COALESCE(SUM(p.amount), 0)
				FROM {$wpdb->prefix}bookings_payments p
				INNER JOIN {$wpdb->prefix}bookings b ON b.id = p.booking_id
				WHERE p.payment_status = 'completed'
				AND b.deleted_at IS NULL"
			);
		}

		// No-show count.
		if ( $has_dates ) {
			$no_show_count = (int) $wpdb->get_var(
				$wpdb->prepare(
					"SELECT COUNT(*) FROM {$wpdb->prefix}bookings
					WHERE status = 'no_show'
					AND deleted_at IS NULL
					AND booking_date BETWEEN %s AND %s",
					$date_from,
					$date_to
				)
			);
		} else {
			$no_show_count = (int) $wpdb->get_var(
				"SELECT COUNT(*) FROM {$wpdb->prefix}bookings
				WHERE status = 'no_show'
				AND deleted_at IS NULL"
			);
		}

		// Denominator for no-show rate.
		if ( $has_dates ) {
			$total_for_rate = (int) $wpdb->get_var(
				$wpdb->prepare(
					"SELECT COUNT(*) FROM {$wpdb->prefix}bookings
					WHERE status IN ('completed', 'no_show', 'confirmed')
					AND deleted_at IS NULL
					AND booking_date BETWEEN %s AND %s",
					$date_from,
					$date_to
				)
			);
		} else {
			$total_for_rate = (int) $wpdb->get_var(
				"SELECT COUNT(*) FROM {$wpdb->prefix}bookings
				WHERE status IN ('completed', 'no_show', 'confirmed')
				AND deleted_at IS NULL"
			);
		}

		// Cancellation count.
		if ( $has_dates ) {
			$cancellation_count = (int) $wpdb->get_var(
				$wpdb->prepare(
					"SELECT COUNT(*) FROM {$wpdb->prefix}bookings
					WHERE status = 'cancelled'
					AND deleted_at IS NULL
					AND booking_date BETWEEN %s AND %s",
					$date_from,
					$date_to
				)
			);
		} else {
			$cancellation_count = (int) $wpdb->get_var(
				"SELECT COUNT(*) FROM {$wpdb->prefix}bookings
				WHERE status = 'cancelled'
				AND deleted_at IS NULL"
			);
		}

		// Calculate rates.
		$no_show_rate = $total_for_rate > 0
			? round( ( $no_show_count / $total_for_rate ) * 100, 1 )
			: 0.0;

		$cancellation_denom = $total_bookings + $cancellation_count;
		$cancellation_rate  = $cancellation_denom > 0
			? round( ( $cancellation_count / $cancellation_denom ) * 100, 1 )
			: 0.0;

		return array(
			'total_bookings'    => $total_bookings,
			'total_revenue'     => $total_revenue,
			'no_show_rate'      => $no_show_rate,
			'cancellation_rate' => $cancellation_rate,
		);
	}

	/**
	 * Get daily revenue for a date range.
	 *
	 * @param string $date_from Start date YYYY-MM-DD.
	 * @param string $date_to End date YYYY-MM-DD.
	 * @return array
	 */
	private function get_daily_revenue( $date_from, $date_to ) {
		global $wpdb;

		$bookings_table = $wpdb->prefix . 'bookings';
		$payments_table = $wpdb->prefix . 'bookings_payments';

		$query = "
			SELECT DATE(b.booking_date) AS date,
				COALESCE(SUM(p.amount), 0) AS revenue
			FROM {$bookings_table} b
			LEFT JOIN {$payments_table} p
				ON p.booking_id = b.id AND p.payment_status = 'completed'
			WHERE b.booking_date BETWEEN %s AND %s
				AND b.deleted_at IS NULL
				AND b.status != 'cancelled'
			GROUP BY DATE(b.booking_date)
			ORDER BY DATE(b.booking_date) ASC
		";

		$rows = $wpdb->get_results(
			$wpdb->prepare(
				$query,
				$date_from,
				$date_to
			),
			ARRAY_A
		);

		$revenue_by_date = array();
		foreach ( $rows as $row ) {
			$revenue_by_date[ $row['date'] ] = (float) $row['revenue'];
		}

		$tz        = new DateTimeZone( 'Europe/London' );
		$start     = new DateTimeImmutable( $date_from, $tz );
		$end       = new DateTimeImmutable( $date_to, $tz );
		$end_plus  = $end->modify( '+1 day' );
		$interval  = new DateInterval( 'P1D' );
		$period    = new DatePeriod( $start, $interval, $end_plus );
		$formatted = array();

		foreach ( $period as $date_obj ) {
			$date_key    = $date_obj->format( 'Y-m-d' );
			$formatted[] = array(
				'date'    => $date_key,
				'revenue' => isset( $revenue_by_date[ $date_key ] ) ? (float) $revenue_by_date[ $date_key ] : 0.0,
			);
		}

		return $formatted;
	}

	/**
	 * Get weekly revenue buckets for a month range.
	 *
	 * @param string $date_from Start date YYYY-MM-DD.
	 * @param string $date_to End date YYYY-MM-DD.
	 * @return array
	 */
	private function get_weekly_revenue( $date_from, $date_to ) {
		global $wpdb;

		$bookings_table = $wpdb->prefix . 'bookings';
		$payments_table = $wpdb->prefix . 'bookings_payments';

		$query = "
			SELECT WEEK(b.booking_date, 1) AS week_num,
				MIN(b.booking_date) AS week_start,
				COALESCE(SUM(p.amount), 0) AS revenue
			FROM {$bookings_table} b
			LEFT JOIN {$payments_table} p
				ON p.booking_id = b.id AND p.payment_status = 'completed'
			WHERE b.booking_date BETWEEN %s AND %s
				AND b.deleted_at IS NULL
				AND b.status != 'cancelled'
			GROUP BY WEEK(b.booking_date, 1)
			ORDER BY week_num ASC
		";

		$rows = $wpdb->get_results(
			$wpdb->prepare(
				$query,
				$date_from,
				$date_to
			),
			ARRAY_A
		);

		$formatted = array();
		$index     = 1;

		foreach ( $rows as $row ) {
			$formatted[] = array(
				'week_label' => 'Week ' . $index,
				'revenue'    => (float) $row['revenue'],
			);
			++$index;
		}

		return $formatted;
	}
}
