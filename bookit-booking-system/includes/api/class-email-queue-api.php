<?php
/**
 * Email queue log REST API (admin-only, read-only).
 *
 * @package Bookit_Booking_System
 */

if ( ! defined( 'WPINC' ) ) {
	die;
}

/**
 * Email queue dashboard API.
 */
class Bookit_Email_Queue_API {

	/**
	 * REST API namespace.
	 */
	const NAMESPACE = 'bookit/v1';

	/**
	 * Constructor — register REST routes.
	 */
	public function __construct() {
		add_action( 'rest_api_init', array( $this, 'register_routes' ) );
	}

	/**
	 * Register REST API routes.
	 *
	 * @return void
	 */
	public function register_routes() {
		register_rest_route(
			self::NAMESPACE,
			'/dashboard/email-queue',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'get_email_queue' ),
				'permission_callback' => array( $this, 'check_dashboard_permission' ),
				'args'                => array(
					'page'     => array(
						'default'           => 1,
						'sanitize_callback' => 'absint',
					),
					'per_page' => array(
						'default'           => 25,
						'sanitize_callback' => 'absint',
					),
					'status'   => array(
						'default'           => '',
						'sanitize_callback' => 'sanitize_text_field',
					),
				),
			)
		);
	}

	/**
	 * Check if user has dashboard permission.
	 *
	 * @return bool|WP_Error
	 */
	public function check_dashboard_permission() {
		if ( ! class_exists( 'Bookit_Session' ) ) {
			require_once plugin_dir_path( dirname( __FILE__ ) ) . 'class-bookit-session.php';
		}
		if ( ! class_exists( 'Bookit_Auth' ) ) {
			require_once plugin_dir_path( dirname( __FILE__ ) ) . 'class-bookit-auth.php';
		}

		if ( ! Bookit_Auth::is_logged_in() ) {
			return Bookit_Error_Registry::to_wp_error( 'E1002' );
		}

		return true;
	}

	/**
	 * GET /dashboard/email-queue
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|WP_Error
	 */
	public function get_email_queue( $request ) {
		if ( ! class_exists( 'Bookit_Auth' ) ) {
			require_once plugin_dir_path( dirname( __FILE__ ) ) . 'class-bookit-auth.php';
		}

		$current_staff = Bookit_Auth::get_current_staff();
		$role          = is_array( $current_staff ) && isset( $current_staff['role'] ) ? (string) $current_staff['role'] : '';

		if ( ! in_array( $role, array( 'admin', 'bookit_admin' ), true ) ) {
			return new WP_Error(
				'forbidden',
				__( 'Access denied.', 'bookit-booking-system' ),
				array( 'status' => 403 )
			);
		}

		$page     = max( 1, absint( $request->get_param( 'page' ) ) );
		$per_page = min( 100, max( 1, absint( $request->get_param( 'per_page' ) ) ) );
		$status   = sanitize_text_field( (string) $request->get_param( 'status' ) );

		$allowed_statuses = array( 'pending', 'processing', 'sent', 'failed', 'cancelled' );
		if ( '' !== $status && ! in_array( $status, $allowed_statuses, true ) ) {
			return new WP_Error(
				'invalid_status',
				__( 'Invalid status filter.', 'bookit-booking-system' ),
				array( 'status' => 400 )
			);
		}

		global $wpdb;

		$table   = $wpdb->prefix . 'bookit_email_queue';
		$offset  = ( $page - 1 ) * $per_page;
		$columns = 'id, booking_id, email_type, recipient_email, status, attempts, max_attempts, scheduled_at, sent_at, last_error, created_at';

		if ( '' !== $status ) {
			// phpcs:ignore WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber
			$rows = $wpdb->get_results(
				$wpdb->prepare(
					"SELECT {$columns} FROM {$table} WHERE status = %s ORDER BY id DESC LIMIT %d OFFSET %d",
					$status,
					$per_page,
					$offset
				),
				ARRAY_A
			);

			$total = (int) $wpdb->get_var(
				$wpdb->prepare(
					"SELECT COUNT(*) FROM {$table} WHERE status = %s",
					$status
				)
			);
		} else {
			// phpcs:ignore WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber
			$rows = $wpdb->get_results(
				$wpdb->prepare(
					"SELECT {$columns} FROM {$table} ORDER BY id DESC LIMIT %d OFFSET %d",
					$per_page,
					$offset
				),
				ARRAY_A
			);

			// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
			$total = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table}" );
		}

		if ( ! is_array( $rows ) ) {
			$rows = array();
		}

		return rest_ensure_response(
			array(
				'items' => $rows,
				'total' => $total,
				'pages' => (int) ceil( $total / $per_page ),
				'page'  => $page,
			)
		);
	}
}
