<?php
/**
 * Audit Log REST API Controller.
 *
 * @package    Bookit_Booking_System
 * @subpackage Bookit_Booking_System/includes/api
 */

if ( ! defined( 'WPINC' ) ) {
	die;
}

/**
 * Audit log API class.
 */
class Bookit_Audit_Log_API {

	/**
	 * REST API namespace.
	 */
	const NAMESPACE = 'bookit/v1';

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
	public function register_routes(): void {
		register_rest_route(
			self::NAMESPACE,
			'/audit-log',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'get_audit_log' ),
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
					'action'    => array(
						'required'          => false,
						'type'              => 'string',
						'sanitize_callback' => 'sanitize_text_field',
					),
					'actor_id'  => array(
						'required'          => false,
						'type'              => 'integer',
						'sanitize_callback' => 'absint',
					),
					'per_page'  => array(
						'required'          => false,
						'type'              => 'integer',
						'default'           => 50,
						'sanitize_callback' => 'absint',
					),
					'page'      => array(
						'required'          => false,
						'type'              => 'integer',
						'default'           => 1,
						'sanitize_callback' => 'absint',
					),
				),
			)
		);
	}

	/**
	 * Permission callback for admin access.
	 *
	 * @return true|WP_Error
	 */
	public function check_admin_permission() {
		$user = null;

		if ( class_exists( 'Bookit_Auth' ) && method_exists( 'Bookit_Auth', 'get_current_user' ) ) {
			$user = Bookit_Auth::get_current_user();
		}

		if ( empty( $user ) && class_exists( 'Bookit_Auth' ) && method_exists( 'Bookit_Auth', 'get_current_staff' ) ) {
			$user = Bookit_Auth::get_current_staff();
		}

		$role = is_array( $user ) && isset( $user['role'] ) ? (string) $user['role'] : '';
		if ( ! $user || ! in_array( $role, array( 'bookit_admin', 'admin' ), true ) ) {
			return Bookit_Error_Registry::to_wp_error( 'E1003' );
		}

		return true;
	}

	/**
	 * GET /audit-log
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response|WP_Error
	 */
	public function get_audit_log( $request ) {
		global $wpdb;

		$date_from = sanitize_text_field( (string) $request->get_param( 'date_from' ) );
		$date_to   = sanitize_text_field( (string) $request->get_param( 'date_to' ) );
		$action    = sanitize_text_field( (string) $request->get_param( 'action' ) );
		$actor_id  = absint( $request->get_param( 'actor_id' ) );
		$per_page  = (int) $request->get_param( 'per_page' );
		$page      = (int) $request->get_param( 'page' );

		$per_page = max( 1, min( 100, $per_page > 0 ? $per_page : 50 ) );
		$page     = max( 1, $page > 0 ? $page : 1 );
		$offset   = ( $page - 1 ) * $per_page;

		$filters = array_filter(
			array(
				'date_from' => $date_from,
				'date_to'   => $date_to,
				'action'    => $action,
				'actor_id'  => $actor_id,
				'per_page'  => $per_page,
				'page'      => $page,
			),
			static function ( $value ) {
				return null !== $value && '' !== $value && 0 !== $value;
			}
		);

		$where_clauses = array( '1=1' );
		$params        = array();

		if ( ! empty( $date_from ) ) {
			$where_clauses[] = 'l.created_at >= %s';
			$params[]        = $date_from . ' 00:00:00';
		}

		if ( ! empty( $date_to ) ) {
			$where_clauses[] = 'l.created_at <= %s';
			$params[]        = $date_to . ' 23:59:59';
		}

		if ( ! empty( $action ) ) {
			$where_clauses[] = 'l.action = %s';
			$params[]        = $action;
		}

		if ( $actor_id > 0 ) {
			$where_clauses[] = 'l.actor_id = %d';
			$params[]        = $actor_id;
		}

		$where_sql = implode( ' AND ', $where_clauses );

		$base_sql = "
			FROM {$wpdb->prefix}bookings_audit_log l
			LEFT JOIN {$wpdb->prefix}bookings_staff st
				ON l.actor_id = st.id
				AND st.deleted_at IS NULL
			LEFT JOIN {$wpdb->prefix}bookings b
				ON l.object_type = 'booking'
				AND l.object_id = b.id
			WHERE {$where_sql}
		";

		$count_sql = "SELECT COUNT(*) {$base_sql}";
		if ( ! empty( $params ) ) {
			$count_sql = $wpdb->prepare( $count_sql, $params );
		}

		$total = (int) $wpdb->get_var( $count_sql );

		$data_sql = "
			SELECT
				l.id,
				l.actor_id,
				l.actor_type,
				l.actor_ip,
				l.action,
				l.object_type,
				l.object_id,
				l.old_value,
				l.new_value,
				l.notes,
				l.created_at,
				TRIM( CONCAT( COALESCE( st.first_name, '' ), ' ', COALESCE( st.last_name, '' ) ) ) AS actor_name_raw,
				CASE
					WHEN l.object_type = 'booking' THEN COALESCE( b.booking_reference, CAST( l.object_id AS CHAR ) )
					WHEN l.object_id IS NULL THEN ''
					ELSE CAST( l.object_id AS CHAR )
				END AS object_summary
			{$base_sql}
			ORDER BY l.created_at DESC, l.id DESC
			LIMIT %d OFFSET %d
		";

		$data_params   = $params;
		$data_params[] = $per_page;
		$data_params[] = $offset;
		$data_sql      = $wpdb->prepare( $data_sql, $data_params );

		$rows = $wpdb->get_results( $data_sql, ARRAY_A );

		$data = array();
		foreach ( $rows as $row ) {
			$actor_name = ! empty( $row['actor_name_raw'] ) ? $row['actor_name_raw'] : 'System';
			if ( (int) $row['actor_id'] <= 0 || 'system' === $row['actor_type'] ) {
				$actor_name = 'System';
			}

			$data[] = array(
				'id'             => (int) $row['id'],
				'actor_id'       => (int) $row['actor_id'],
				'actor_type'     => (string) $row['actor_type'],
				'actor_name'     => $actor_name,
				'actor_ip'       => ! empty( $row['actor_ip'] ) ? (string) $row['actor_ip'] : null,
				'action'         => (string) $row['action'],
				'object_type'    => (string) $row['object_type'],
				'object_id'      => ! empty( $row['object_id'] ) ? (int) $row['object_id'] : null,
				'object_summary' => (string) $row['object_summary'],
				'old_value'      => $this->decode_json_value( $row['old_value'] ),
				'new_value'      => $this->decode_json_value( $row['new_value'] ),
				'notes'          => ! empty( $row['notes'] ) ? (string) $row['notes'] : null,
				'created_at'     => (string) $row['created_at'],
			);
		}

		$total_pages = max( 1, (int) ceil( $total / $per_page ) );

		Bookit_Audit_Logger::log(
			'audit_log.viewed',
			'audit_log',
			0,
			array(
				'notes' => sprintf( 'Audit log viewed. Filters: %s', wp_json_encode( $filters ) ),
			)
		);

		return rest_ensure_response(
			array(
				'data'       => $data,
				'pagination' => array(
					'total'        => $total,
					'per_page'     => $per_page,
					'current_page' => $page,
					'total_pages'  => $total_pages,
				),
			)
		);
	}

	/**
	 * Decode JSON values from DB rows.
	 *
	 * @param mixed $value Raw database value.
	 * @return mixed
	 */
	private function decode_json_value( $value ) {
		if ( null === $value || '' === $value ) {
			return null;
		}

		$decoded = json_decode( (string) $value, true );
		if ( JSON_ERROR_NONE === json_last_error() ) {
			return $decoded;
		}

		return (string) $value;
	}
}
