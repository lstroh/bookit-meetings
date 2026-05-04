<?php
/**
 * Customers REST API Controller
 *
 * Handles dashboard customer database endpoints (including GDPR erasure).
 *
 * @package    Bookit_Booking_System
 * @subpackage Bookit_Booking_System/includes/api
 */

if ( ! defined( 'WPINC' ) ) {
	die;
}

class Bookit_Customers_API {

	const NAMESPACE = 'bookit/v1';

	public function __construct() {
		add_action( 'rest_api_init', array( $this, 'register_routes' ) );
	}

	public function register_routes() {
		register_rest_route(
			self::NAMESPACE,
			'/dashboard/customers',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'get_customers' ),
				'permission_callback' => array( $this, 'check_admin_permission' ),
				'args'                => array(
					'search'   => array(
						'required'          => false,
						'type'              => 'string',
						'sanitize_callback' => 'sanitize_text_field',
					),
					'status'   => array(
						'required'          => false,
						'type'              => 'string',
						'validate_callback' => function ( $param ) {
							if ( empty( $param ) ) {
								return true;
							}
							return in_array( $param, array( 'active', 'inactive', 'new' ), true );
						},
						'sanitize_callback' => 'sanitize_text_field',
					),
					'page'     => array(
						'required'          => false,
						'type'              => 'integer',
						'default'           => 1,
						'validate_callback' => function ( $param ) {
							return is_numeric( $param ) && (int) $param > 0;
						},
						'sanitize_callback' => 'absint',
					),
					'per_page' => array(
						'required'          => false,
						'type'              => 'integer',
						'default'           => 25,
						'validate_callback' => function ( $param ) {
							return is_numeric( $param ) && (int) $param > 0;
						},
						'sanitize_callback' => 'absint',
					),
				),
			)
		);

		register_rest_route(
			self::NAMESPACE,
			'/dashboard/customers/export',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'export_customers_csv' ),
				'permission_callback' => array( $this, 'check_admin_permission' ),
			)
		);

		register_rest_route(
			self::NAMESPACE,
			'/dashboard/customers/(?P<id>\d+)/export',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'export_customer_data' ),
				'permission_callback' => array( $this, 'check_admin_permission' ),
				'args'                => array(
					'id'     => array(
						'required'          => true,
						'validate_callback' => function ( $param ) {
							return is_numeric( $param );
						},
					),
					'format' => array(
						'required'          => true,
						'type'              => 'string',
						'enum'              => array( 'json', 'csv' ),
						'sanitize_callback' => 'sanitize_text_field',
					),
				),
			)
		);

		register_rest_route(
			self::NAMESPACE,
			'/dashboard/customers/(?P<id>\d+)',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'get_customer' ),
				'permission_callback' => array( $this, 'check_admin_permission' ),
			)
		);

		register_rest_route(
			self::NAMESPACE,
			'/dashboard/customers/(?P<id>\d+)',
			array(
				'methods'             => 'PUT',
				'callback'            => array( $this, 'update_customer' ),
				'permission_callback' => array( $this, 'check_admin_permission' ),
				'args'                => array(
					'first_name'        => array(
						'required'          => false,
						'type'              => 'string',
						'sanitize_callback' => 'sanitize_text_field',
					),
					'last_name'         => array(
						'required'          => false,
						'type'              => 'string',
						'sanitize_callback' => 'sanitize_text_field',
					),
					'phone'             => array(
						'required'          => false,
						'type'              => 'string',
						'sanitize_callback' => 'sanitize_text_field',
					),
					'marketing_consent' => array(
						'required'          => false,
						'type'              => 'boolean',
						'sanitize_callback' => function ( $value ) {
							return rest_sanitize_boolean( $value );
						},
					),
					'notes'             => array(
						'required'          => false,
						'type'              => 'string',
						'sanitize_callback' => 'sanitize_textarea_field',
					),
				),
			)
		);

		register_rest_route(
			self::NAMESPACE,
			'/dashboard/customers/(?P<id>\d+)',
			array(
				'methods'             => 'DELETE',
				'callback'            => array( $this, 'delete_customer' ),
				'permission_callback' => array( $this, 'check_admin_permission' ),
			)
		);

		register_rest_route(
			self::NAMESPACE,
			'/dashboard/customers/(?P<id>\d+)/request-email-change',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'request_email_change' ),
				'permission_callback' => array( $this, 'check_admin_permission' ),
				'args'                => array(
					'new_email' => array(
						'required'          => true,
						'type'              => 'string',
						'sanitize_callback' => 'sanitize_email',
					),
					'reason'    => array(
						'required'          => true,
						'type'              => 'string',
						'sanitize_callback' => 'sanitize_text_field',
					),
				),
			)
		);

		register_rest_route(
			self::NAMESPACE,
			'/wizard/verify-email-change',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'verify_email_change' ),
				'permission_callback' => '__return_true',
				'args'                => array(
					'token'       => array(
						'required'          => true,
						'type'              => 'string',
						'sanitize_callback' => 'sanitize_text_field',
					),
					'customer_id' => array(
						'required'          => true,
						'type'              => 'integer',
						'sanitize_callback' => 'absint',
					),
				),
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
	 * GET /dashboard/customers
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response|WP_Error
	 */
	public function get_customers( $request ) {
		global $wpdb;

		$search   = sanitize_text_field( (string) $request->get_param( 'search' ) );
		$status   = sanitize_text_field( (string) $request->get_param( 'status' ) );
		$page     = max( 1, absint( $request->get_param( 'page' ) ) );
		$per_page = max( 1, absint( $request->get_param( 'per_page' ) ) );
		$offset   = ( $page - 1 ) * $per_page;

		// Performance audit: customer list metrics are aggregated in SQL (no per-customer N+1 queries).
		$base_query = "
			SELECT
				c.id,
				c.first_name,
				c.last_name,
				c.email,
				c.phone,
				c.marketing_consent,
				c.created_at,
				COUNT(DISTINCT CASE WHEN b.status != 'cancelled' AND b.deleted_at IS NULL THEN b.id END) AS total_bookings,
				COALESCE(SUM(CASE WHEN p.payment_status = 'completed' THEN p.amount ELSE 0 END), 0) AS total_spent,
				MAX(CASE WHEN b.status = 'completed' THEN b.booking_date END) AS last_visit,
				COUNT(DISTINCT CASE WHEN b.status IN ('confirmed','pending_payment') AND b.booking_date >= CURDATE() AND b.deleted_at IS NULL THEN b.id END) AS upcoming_count
			FROM {$wpdb->prefix}bookings_customers c
			LEFT JOIN {$wpdb->prefix}bookings b ON b.customer_id = c.id
			LEFT JOIN {$wpdb->prefix}bookings_payments p ON p.booking_id = b.id
			WHERE c.deleted_at IS NULL
		";

		$params        = array();
		$where_clauses = array();
		$having_clause = '';

		if ( ! empty( $search ) ) {
			$like            = '%' . $wpdb->esc_like( $search ) . '%';
			$where_clauses[] = '(c.first_name LIKE %s OR c.last_name LIKE %s OR c.email LIKE %s OR c.phone LIKE %s)';
			$params[]        = $like;
			$params[]        = $like;
			$params[]        = $like;
			$params[]        = $like;
		}

		if ( ! empty( $where_clauses ) ) {
			$base_query .= ' AND ' . implode( ' AND ', $where_clauses );
		}

		if ( 'active' === $status ) {
			$having_clause = " HAVING COUNT(DISTINCT CASE WHEN b.status != 'cancelled' AND b.deleted_at IS NULL AND b.booking_date >= DATE_SUB(CURDATE(), INTERVAL 6 MONTH) THEN b.id END) > 0";
		} elseif ( 'inactive' === $status ) {
			$having_clause = " HAVING COUNT(DISTINCT CASE WHEN b.status != 'cancelled' AND b.deleted_at IS NULL AND b.booking_date >= DATE_SUB(CURDATE(), INTERVAL 6 MONTH) THEN b.id END) = 0";
		} elseif ( 'new' === $status ) {
			$having_clause = " HAVING COUNT(DISTINCT CASE WHEN b.status != 'cancelled' AND b.deleted_at IS NULL THEN b.id END) = 1";
		}

		$group_order_sql = '
			GROUP BY c.id, c.first_name, c.last_name, c.email, c.phone, c.marketing_consent, c.created_at
			' . $having_clause . '
			ORDER BY c.created_at DESC
		';

		$data_query = $base_query . ' ' . $group_order_sql . ' LIMIT %d OFFSET %d';

		$data_params   = $params;
		$data_params[] = $per_page;
		$data_params[] = $offset;
		$prepared_data = $wpdb->prepare( $data_query, $data_params );
		$rows          = $wpdb->get_results( $prepared_data, ARRAY_A );

		$count_query = 'SELECT COUNT(*) FROM (' . $base_query . ' ' . $group_order_sql . ') AS subq';
		if ( empty( $params ) ) {
			$total = (int) $wpdb->get_var( $count_query );
		} else {
			$total = (int) $wpdb->get_var( $wpdb->prepare( $count_query, $params ) );
		}

		$total_pages = max( 1, (int) ceil( $total / $per_page ) );

		$customers = array_map(
			function ( $row ) {
				$total_bookings = (int) $row['total_bookings'];
				$last_visit     = ! empty( $row['last_visit'] ) ? substr( (string) $row['last_visit'], 0, 10 ) : null;
				$status_label   = $this->determine_customer_status( $total_bookings, $last_visit );

				return array(
					'id'                => (int) $row['id'],
					'first_name'        => (string) $row['first_name'],
					'last_name'         => (string) $row['last_name'],
					'full_name'         => trim( (string) $row['first_name'] . ' ' . (string) $row['last_name'] ),
					'email'             => (string) $row['email'],
					'phone'             => (string) $row['phone'],
					'marketing_consent' => (bool) (int) $row['marketing_consent'],
					'member_since'      => isset( $row['created_at'] ) ? substr( (string) $row['created_at'], 0, 10 ) : '',
					'total_bookings'    => $total_bookings,
					'total_spent'       => (float) $row['total_spent'],
					'last_visit'        => $last_visit,
					'upcoming_count'    => (int) $row['upcoming_count'],
					'status'            => $status_label,
				);
			},
			$rows
		);

		return rest_ensure_response(
			array(
				'success'    => true,
				'customers'  => $customers,
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
	 * GET /dashboard/customers/{id}
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response|WP_Error
	 */
	public function get_customer( $request ) {
		global $wpdb;

		$customer_id = absint( $request->get_param( 'id' ) );
		if ( $customer_id <= 0 ) {
			return new WP_Error(
				'invalid_customer_id',
				__( 'A valid customer ID is required.', 'bookit-booking-system' ),
				array( 'status' => 400 )
			);
		}

		$query = "
			SELECT
				c.id,
				c.first_name,
				c.last_name,
				c.email,
				c.phone,
				c.notes,
				c.marketing_consent,
				c.marketing_consent_date,
				c.created_at,
				COUNT(DISTINCT CASE WHEN b.status != 'cancelled' AND b.deleted_at IS NULL THEN b.id END) AS total_bookings,
				COALESCE(SUM(CASE WHEN p.payment_status = 'completed' THEN p.amount ELSE 0 END), 0) AS total_spent,
				MAX(CASE WHEN b.status = 'completed' THEN b.booking_date END) AS last_visit,
				COUNT(DISTINCT CASE WHEN b.status IN ('confirmed','pending_payment') AND b.booking_date >= CURDATE() AND b.deleted_at IS NULL THEN b.id END) AS upcoming_count
			FROM {$wpdb->prefix}bookings_customers c
			LEFT JOIN {$wpdb->prefix}bookings b ON b.customer_id = c.id
			LEFT JOIN {$wpdb->prefix}bookings_payments p ON p.booking_id = b.id
			WHERE c.deleted_at IS NULL AND c.id = %d
			GROUP BY c.id, c.first_name, c.last_name, c.email, c.phone, c.notes, c.marketing_consent, c.marketing_consent_date, c.created_at
			ORDER BY c.created_at DESC
		";

		$customer = $wpdb->get_row(
			$wpdb->prepare( $query, $customer_id ),
			ARRAY_A
		);

		if ( ! $customer ) {
			return new WP_Error(
				'customer_not_found',
				__( 'Customer not found.', 'bookit-booking-system' ),
				array( 'status' => 404 )
			);
		}

		$bookings = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT
					b.id, b.booking_date, b.start_time, b.end_time, b.status,
					b.total_price, b.deposit_paid, b.balance_due, b.payment_method,
					s.name AS service_name,
					CONCAT(st.first_name, ' ', st.last_name) AS staff_name
				FROM {$wpdb->prefix}bookings b
				INNER JOIN {$wpdb->prefix}bookings_services s ON s.id = b.service_id
				INNER JOIN {$wpdb->prefix}bookings_staff st ON st.id = b.staff_id
				WHERE b.customer_id = %d AND b.deleted_at IS NULL
				ORDER BY b.booking_date DESC, b.start_time DESC
				LIMIT 20",
				$customer_id
			),
			ARRAY_A
		);

		$payments = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT
					p.id, p.amount, p.payment_method, p.payment_type,
					p.payment_status, p.transaction_date, p.booking_id
				FROM {$wpdb->prefix}bookings_payments p
				INNER JOIN {$wpdb->prefix}bookings b ON b.id = p.booking_id
				WHERE b.customer_id = %d AND b.deleted_at IS NULL
				ORDER BY p.transaction_date DESC
				LIMIT 10",
				$customer_id
			),
			ARRAY_A
		);

		$total_bookings = (int) $customer['total_bookings'];
		$last_visit     = ! empty( $customer['last_visit'] ) ? substr( (string) $customer['last_visit'], 0, 10 ) : null;

		return rest_ensure_response(
			array(
				'success'  => true,
				'customer' => array(
					'id'                     => (int) $customer['id'],
					'first_name'             => (string) $customer['first_name'],
					'last_name'              => (string) $customer['last_name'],
					'full_name'              => trim( (string) $customer['first_name'] . ' ' . (string) $customer['last_name'] ),
					'email'                  => (string) $customer['email'],
					'phone'                  => (string) $customer['phone'],
					'notes'                  => isset( $customer['notes'] ) ? (string) $customer['notes'] : '',
					'marketing_consent'      => (bool) (int) $customer['marketing_consent'],
					'marketing_consent_date' => $customer['marketing_consent_date'],
					'member_since'           => isset( $customer['created_at'] ) ? substr( (string) $customer['created_at'], 0, 10 ) : '',
					'total_bookings'         => $total_bookings,
					'total_spent'            => (float) $customer['total_spent'],
					'last_visit'             => $last_visit,
					'upcoming_count'         => (int) $customer['upcoming_count'],
					'status'                 => $this->determine_customer_status( $total_bookings, $last_visit ),
					'bookings'               => $bookings,
					'payments'               => $payments,
				),
			)
		);
	}

	/**
	 * PUT /dashboard/customers/{id}
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response|WP_Error
	 */
	public function update_customer( $request ) {
		global $wpdb;

		$customer_id = absint( $request->get_param( 'id' ) );
		if ( $customer_id <= 0 ) {
			return new WP_Error(
				'invalid_customer_id',
				__( 'A valid customer ID is required.', 'bookit-booking-system' ),
				array( 'status' => 400 )
			);
		}

		$exists = (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$wpdb->prefix}bookings_customers WHERE id = %d AND deleted_at IS NULL",
				$customer_id
			)
		);

		if ( $exists <= 0 ) {
			return new WP_Error(
				'customer_not_found',
				__( 'Customer not found.', 'bookit-booking-system' ),
				array( 'status' => 404 )
			);
		}

		$data   = array();
		$format = array();

		if ( null !== $request->get_param( 'first_name' ) ) {
			$data['first_name'] = sanitize_text_field( (string) $request->get_param( 'first_name' ) );
			$format[]           = '%s';
		}

		if ( null !== $request->get_param( 'last_name' ) ) {
			$data['last_name'] = sanitize_text_field( (string) $request->get_param( 'last_name' ) );
			$format[]          = '%s';
		}

		if ( null !== $request->get_param( 'phone' ) ) {
			$data['phone'] = sanitize_text_field( (string) $request->get_param( 'phone' ) );
			$format[]      = '%s';
		}

		if ( null !== $request->get_param( 'notes' ) ) {
			$data['notes'] = sanitize_textarea_field( (string) $request->get_param( 'notes' ) );
			$format[]      = '%s';
		}

		if ( null !== $request->get_param( 'marketing_consent' ) ) {
			$marketing_consent                  = (int) rest_sanitize_boolean( $request->get_param( 'marketing_consent' ) );
			$data['marketing_consent']          = $marketing_consent;
			$format[]                           = '%d';
			$data['marketing_consent_date']     = $marketing_consent ? current_time( 'mysql' ) : null;
			$format[]                           = $marketing_consent ? '%s' : null;
		}

		if ( empty( $data ) ) {
			return new WP_Error(
				'no_fields_to_update',
				__( 'No valid fields were provided for update.', 'bookit-booking-system' ),
				array( 'status' => 400 )
			);
		}

		$data['updated_at'] = current_time( 'mysql' );
		$format[]           = '%s';

		$updated = $wpdb->update(
			$wpdb->prefix . 'bookings_customers',
			$data,
			array( 'id' => $customer_id ),
			$format,
			array( '%d' )
		);

		if ( false === $updated ) {
			return new WP_Error(
				'customer_update_failed',
				__( 'Failed to update customer.', 'bookit-booking-system' ),
				array( 'status' => 500 )
			);
		}

		Bookit_Audit_Logger::log(
			'customer.anonymised',
			'customer',
			$customer_id,
			array(
				'notes' => 'Customer data anonymised per GDPR request',
			)
		);

		return rest_ensure_response(
			array(
				'success' => true,
				'message' => __( 'Customer updated successfully.', 'bookit-booking-system' ),
			)
		);
	}

	/**
	 * DELETE /dashboard/customers/{id}
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response|WP_Error
	 */
	public function delete_customer( $request ) {
		global $wpdb;

		$customer_id = absint( $request->get_param( 'id' ) );
		if ( $customer_id <= 0 ) {
			return new WP_Error(
				'invalid_customer_id',
				__( 'A valid customer ID is required.', 'bookit-booking-system' ),
				array( 'status' => 400 )
			);
		}

		$exists = (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$wpdb->prefix}bookings_customers WHERE id = %d AND deleted_at IS NULL",
				$customer_id
			)
		);

		if ( $exists <= 0 ) {
			return new WP_Error(
				'customer_not_found',
				__( 'Customer not found.', 'bookit-booking-system' ),
				array( 'status' => 404 )
			);
		}

		$upcoming = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$wpdb->prefix}bookings
				WHERE customer_id = %d
					AND status IN ('confirmed', 'pending_payment')
					AND booking_date >= CURDATE()
					AND deleted_at IS NULL",
				$customer_id
			)
		);

		if ( $upcoming > 0 ) {
			return new WP_Error(
				'has_upcoming_bookings',
				sprintf(
					__( 'Cannot delete customer with %d upcoming booking(s). Cancel them first.', 'bookit-booking-system' ),
					$upcoming
				),
				array( 'status' => 409 )
			);
		}

		$updated = $wpdb->update(
			$wpdb->prefix . 'bookings_customers',
			array(
				'first_name'             => 'Deleted',
				'last_name'              => 'Customer',
				'email'                  => 'deleted_' . $customer_id . '@deleted.invalid',
				'phone'                  => '',
				'marketing_consent'      => 0,
				'marketing_consent_date' => null,
				'notes'                  => null,
				'deleted_at'             => current_time( 'mysql' ),
				'updated_at'             => current_time( 'mysql' ),
			),
			array( 'id' => $customer_id ),
			array( '%s', '%s', '%s', '%s', '%d', null, null, '%s', '%s' ),
			array( '%d' )
		);

		if ( false === $updated ) {
			return new WP_Error(
				'customer_delete_failed',
				__( 'Failed to anonymise customer data.', 'bookit-booking-system' ),
				array( 'status' => 500 )
			);
		}

		return rest_ensure_response(
			array(
				'success' => true,
				'message' => __( 'Customer data has been anonymised in compliance with GDPR Article 17.', 'bookit-booking-system' ),
			)
		);
	}

	/**
	 * POST /dashboard/customers/{id}/request-email-change
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response|WP_Error
	 */
	public function request_email_change( $request ) {
		global $wpdb;

		$customer_id = absint( $request->get_param( 'id' ) );
		$new_email   = sanitize_email( (string) $request->get_param( 'new_email' ) );
		$reason      = sanitize_text_field( (string) $request->get_param( 'reason' ) );

		if ( $customer_id <= 0 ) {
			return new WP_Error( 'invalid_customer_id', __( 'A valid customer ID is required.', 'bookit-booking-system' ), array( 'status' => 400 ) );
		}

		if ( empty( $new_email ) || ! is_email( $new_email ) ) {
			return new WP_Error( 'invalid_email', __( 'A valid email address is required.', 'bookit-booking-system' ), array( 'status' => 400 ) );
		}

		if ( empty( $reason ) ) {
			return new WP_Error( 'missing_reason', __( 'A reason is required.', 'bookit-booking-system' ), array( 'status' => 400 ) );
		}

		if ( ! class_exists( 'Bookit_Rate_Limiter' ) ) {
			require_once plugin_dir_path( dirname( __FILE__ ) ) . 'class-bookit-rate-limiter.php';
		}

		if ( ! class_exists( 'Bookit_Notification_Dispatcher' ) ) {
			require_once plugin_dir_path( dirname( __FILE__ ) ) . 'notifications/class-bookit-notification-dispatcher.php';
		}

		if ( ! class_exists( 'Booking_System_Email_Sender' ) ) {
			require_once plugin_dir_path( dirname( __FILE__ ) ) . 'email/class-email-sender.php';
		}

		if ( ! class_exists( 'Bookit_Auth' ) ) {
			require_once plugin_dir_path( dirname( __FILE__ ) ) . 'class-bookit-auth.php';
		}

		$current_staff = Bookit_Auth::get_current_staff();
		$staff_id      = is_array( $current_staff ) && isset( $current_staff['id'] ) ? absint( $current_staff['id'] ) : 0;
		if ( $staff_id <= 0 ) {
			return new WP_Error( 'unauthorized', __( 'You must be logged in to access the dashboard.', 'bookit-booking-system' ), array( 'status' => 401 ) );
		}

		$rate_identifier = (string) $staff_id;
		if ( ! Bookit_Rate_Limiter::check( 'email_change_request', $rate_identifier, 5, HOUR_IN_SECONDS ) ) {
			return Bookit_Rate_Limiter::handle_exceeded( 'email_change_request', $rate_identifier );
		}

		$customer = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT id, first_name, last_name, email
				FROM {$wpdb->prefix}bookings_customers
				WHERE id = %d AND deleted_at IS NULL
				LIMIT 1",
				$customer_id
			),
			ARRAY_A
		);

		if ( ! $customer ) {
			return new WP_Error( 'customer_not_found', __( 'Customer not found.', 'bookit-booking-system' ), array( 'status' => 404 ) );
		}

		$duplicate_id = (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT id FROM {$wpdb->prefix}bookings_customers WHERE email = %s AND id != %d LIMIT 1",
				$new_email,
				$customer_id
			)
		);

		if ( $duplicate_id > 0 ) {
			return new WP_Error(
				'email_already_in_use',
				'This email is already in use',
				array( 'status' => 409 )
			);
		}

		$token   = wp_generate_password( 32, false, false );
		$expires = gmdate( 'Y-m-d H:i:s', time() + DAY_IN_SECONDS );

		$updated = $wpdb->update(
			$wpdb->prefix . 'bookings_customers',
			array(
				'pending_email_change' => $new_email,
				'email_change_token'   => $token,
				'email_change_expires' => $expires,
				'updated_at'           => current_time( 'mysql' ),
			),
			array( 'id' => $customer_id ),
			array( '%s', '%s', '%s', '%s' ),
			array( '%d' )
		);

		if ( false === $updated ) {
			return new WP_Error( 'email_change_request_failed', __( 'Failed to request email change.', 'bookit-booking-system' ), array( 'status' => 500 ) );
		}

		$email_sender  = new Booking_System_Email_Sender();
		$customer_name = trim( (string) ( $customer['first_name'] ?? '' ) . ' ' . (string) ( $customer['last_name'] ?? '' ) );

		$verification_subject = 'Please verify your new email address';
		$verification_body    = $email_sender->generate_email_change_verification_email( $customer, $token );
		Bookit_Notification_Dispatcher::enqueue_email(
			'email_change_verification',
			array(
				'email' => $new_email,
				'name'  => $customer_name,
			),
			$verification_subject,
			$verification_body
		);

		$notification_subject = 'Email change requested for your booking account';
		$notification_body    = $email_sender->generate_email_change_notification_email( $customer );
		Bookit_Notification_Dispatcher::enqueue_email(
			'email_change_notification',
			array(
				'email' => (string) ( $customer['email'] ?? '' ),
				'name'  => $customer_name,
			),
			$notification_subject,
			$notification_body
		);

		Bookit_Audit_Logger::log(
			'customer.email_change_requested',
			'admin',
			$staff_id,
			array(
				'customer_id' => $customer_id,
				'new_email'   => $new_email,
				'reason'      => $reason,
			)
		);

		return new WP_REST_Response( array( 'success' => true ), 200 );
	}

	/**
	 * GET /wizard/verify-email-change
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response
	 */
	public function verify_email_change( $request ) {
		global $wpdb;

		$token       = sanitize_text_field( (string) $request->get_param( 'token' ) );
		$customer_id = absint( $request->get_param( 'customer_id' ) );

		if ( empty( $token ) || $customer_id <= 0 ) {
			return new WP_REST_Response(
				array(
					'success' => false,
					'message' => 'Invalid verification link.',
				),
				400
			);
		}

		$customer = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT id, first_name, last_name, email, pending_email_change, email_change_token, email_change_expires
				FROM {$wpdb->prefix}bookings_customers
				WHERE id = %d AND deleted_at IS NULL
				LIMIT 1",
				$customer_id
			),
			ARRAY_A
		);

		if ( ! $customer ) {
			return new WP_REST_Response(
				array(
					'success' => false,
					'message' => 'Customer not found.',
				),
				400
			);
		}

		$stored_token = (string) ( $customer['email_change_token'] ?? '' );
		if ( empty( $stored_token ) || ! hash_equals( $stored_token, (string) $token ) ) {
			return new WP_REST_Response(
				array(
					'success' => false,
					'message' => 'Invalid verification link.',
				),
				400
			);
		}

		$expires_raw = (string) ( $customer['email_change_expires'] ?? '' );
		$expires_ts  = $expires_raw ? strtotime( $expires_raw . ' UTC' ) : false;
		if ( false === $expires_ts || $expires_ts < time() ) {
			return new WP_REST_Response(
				array(
					'success' => false,
					'message' => 'This verification link has expired.',
				),
				400
			);
		}

		$new_email = (string) ( $customer['pending_email_change'] ?? '' );
		if ( empty( $new_email ) || ! is_email( $new_email ) ) {
			return new WP_REST_Response(
				array(
					'success' => false,
					'message' => 'No pending email change found.',
				),
				400
			);
		}

		if ( ! class_exists( 'Bookit_Notification_Dispatcher' ) ) {
			require_once plugin_dir_path( dirname( __FILE__ ) ) . 'notifications/class-bookit-notification-dispatcher.php';
		}

		if ( ! class_exists( 'Booking_System_Email_Sender' ) ) {
			require_once plugin_dir_path( dirname( __FILE__ ) ) . 'email/class-email-sender.php';
		}

		$old_email = (string) ( $customer['email'] ?? '' );

		$updated = $wpdb->update(
			$wpdb->prefix . 'bookings_customers',
			array(
				'email'                => $new_email,
				'pending_email_change' => null,
				'email_change_token'   => null,
				'email_change_expires' => null,
				'updated_at'           => current_time( 'mysql' ),
			),
			array( 'id' => $customer_id ),
			array( '%s', '%s', '%s', '%s', '%s' ),
			array( '%d' )
		);

		if ( false === $updated ) {
			return new WP_REST_Response(
				array(
					'success' => false,
					'message' => 'Failed to update email address.',
				),
				400
			);
		}

		$email_sender = new Booking_System_Email_Sender();
		$subject      = 'Your booking account email has been updated';
		$html_body    = $email_sender->generate_email_change_confirmed_email( $new_email );
		$name         = trim( (string) ( $customer['first_name'] ?? '' ) . ' ' . (string) ( $customer['last_name'] ?? '' ) );

		Bookit_Notification_Dispatcher::enqueue_email(
			'email_change_confirmed',
			array(
				'email' => $old_email,
				'name'  => $name,
			),
			$subject,
			$html_body
		);

		Bookit_Notification_Dispatcher::enqueue_email(
			'email_change_confirmed',
			array(
				'email' => $new_email,
				'name'  => $name,
			),
			$subject,
			$html_body
		);

		Bookit_Audit_Logger::log(
			'customer.email_change_confirmed',
			'customer',
			$customer_id,
			array(
				'old_email' => $old_email,
				'new_email' => $new_email,
			)
		);

		$redirect_url = home_url( '/bookit-email-changed/' );
		if ( $this->is_test_environment() ) {
			return new WP_REST_Response(
				array(
					'success'  => true,
					'redirect' => $redirect_url,
				),
				200
			);
		}

		wp_redirect( $redirect_url );
		exit;
	}

	/**
	 * GET /dashboard/customers/export
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response|WP_Error
	 */
	public function export_customers_csv( $request ) { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed
		global $wpdb;

		$query = "
			SELECT
				c.id,
				c.first_name,
				c.last_name,
				c.email,
				c.phone,
				c.marketing_consent,
				c.created_at,
				COUNT(DISTINCT CASE WHEN b.status != 'cancelled' AND b.deleted_at IS NULL THEN b.id END) AS total_bookings,
				COALESCE(SUM(CASE WHEN p.payment_status = 'completed' THEN p.amount ELSE 0 END), 0) AS total_spent,
				MAX(CASE WHEN b.status = 'completed' THEN b.booking_date END) AS last_visit,
				COUNT(DISTINCT CASE WHEN b.status IN ('confirmed','pending_payment') AND b.booking_date >= CURDATE() AND b.deleted_at IS NULL THEN b.id END) AS upcoming_count
			FROM {$wpdb->prefix}bookings_customers c
			LEFT JOIN {$wpdb->prefix}bookings b ON b.customer_id = c.id
			LEFT JOIN {$wpdb->prefix}bookings_payments p ON p.booking_id = b.id
			WHERE c.deleted_at IS NULL
			GROUP BY c.id, c.first_name, c.last_name, c.email, c.phone, c.marketing_consent, c.created_at
			ORDER BY c.created_at DESC
		";

		$customers = $wpdb->get_results( $query, ARRAY_A );

		$stream = fopen( 'php://temp', 'r+' );

		fputcsv(
			$stream,
			array(
				'Customer ID',
				'First Name',
				'Last Name',
				'Email',
				'Phone',
				'Member Since',
				'Total Bookings',
				'Total Spent',
				'Last Visit',
				'Upcoming Bookings',
				'Status',
				'Marketing Consent',
			)
		);

		foreach ( $customers as $customer ) {
			$total_bookings = (int) $customer['total_bookings'];
			$last_visit     = ! empty( $customer['last_visit'] ) ? substr( (string) $customer['last_visit'], 0, 10 ) : null;
			$status         = $this->determine_customer_status( $total_bookings, $last_visit );

			$member_since = ! empty( $customer['created_at'] ) ? date( 'd/m/Y', strtotime( (string) $customer['created_at'] ) ) : '';
			$last_visit_f = $last_visit ? date( 'd/m/Y', strtotime( $last_visit ) ) : 'Never';

			fputcsv(
				$stream,
				array(
					(int) $customer['id'],
					(string) $customer['first_name'],
					(string) $customer['last_name'],
					(string) $customer['email'],
					(string) $customer['phone'],
					$member_since,
					$total_bookings,
					number_format( (float) $customer['total_spent'], 2, '.', '' ),
					$last_visit_f,
					(int) $customer['upcoming_count'],
					ucfirst( $status ),
					(bool) (int) $customer['marketing_consent'] ? 'Yes' : 'No',
				)
			);
		}

		rewind( $stream );
		$csv_string = stream_get_contents( $stream );
		fclose( $stream );

		$filename = 'customers-export-' . current_time( 'Y-m-d' ) . '.csv';

		add_filter(
			'rest_pre_serve_request',
			function( $served ) use ( $csv_string, $filename ) {
				if ( ! $served ) {
					if ( defined( 'WP_TESTS_PHPUNIT_POLYFILLS_PATH' ) || defined( 'WP_TESTS_DIR' ) ) {
						return true;
					}
					header( 'Content-Type: text/csv; charset=utf-8' );
					header( 'Content-Disposition: attachment; filename="' . $filename . '"' );
					header( 'Cache-Control: no-cache, no-store, must-revalidate' );
					header( 'Content-Length: ' . strlen( $csv_string ) );
					echo $csv_string; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
				}
				return true;
			}
		);

		return new WP_REST_Response( null, 200 );
	}

	/**
	 * GET /dashboard/customers/{id}/export
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response|WP_Error
	 */
	public function export_customer_data( $request ) {
		$customer_id = absint( $request->get_param( 'id' ) );
		$format      = sanitize_text_field( (string) $request->get_param( 'format' ) );

		$export_data = $this->build_customer_export_data( $customer_id );
		if ( is_wp_error( $export_data ) ) {
			return $export_data;
		}

		$date_token = current_time( 'Y-m-d' );
		$filename   = 'customer-' . $customer_id . '-data-export-' . $date_token;
		$mime_type  = 'application/json; charset=utf-8';
		$content    = '';

		if ( 'json' === $format ) {
			$content  = wp_json_encode( $export_data, JSON_PRETTY_PRINT );
			$filename = $filename . '.json';
		} else {
			$zip_result = $this->build_customer_export_zip( $export_data );
			if ( is_wp_error( $zip_result ) ) {
				return $zip_result;
			}

			$content   = $zip_result;
			$filename  = $filename . '.zip';
			$mime_type = 'application/zip';
		}

		$current_staff = class_exists( 'Bookit_Auth' ) ? Bookit_Auth::get_current_staff() : array();
		$actor_id      = is_array( $current_staff ) && isset( $current_staff['id'] ) ? absint( $current_staff['id'] ) : 0;

		Bookit_Audit_Logger::log(
			'customer_data_exported',
			'customer',
			$customer_id,
			array(
				'actor_id' => $actor_id,
			)
		);

		add_filter(
			'rest_pre_serve_request',
			function( $served ) use ( $content, $filename, $mime_type ) {
				if ( ! $served ) {
					if ( $this->is_test_environment() ) {
						return true;
					}
					header( 'Content-Type: ' . $mime_type );
					header( 'Content-Disposition: attachment; filename="' . $filename . '"' );
					header( 'Cache-Control: no-cache, no-store, must-revalidate' );
					header( 'Content-Length: ' . strlen( $content ) );
					echo $content; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
				}
				return true;
			}
		);

		$response = new WP_REST_Response( null, 200 );
		$response->header( 'Content-Type', $mime_type );
		$response->header( 'Content-Disposition', 'attachment; filename="' . $filename . '"' );

		if ( $this->is_test_environment() ) {
			$response->set_data( $content );
		}

		return $response;
	}

	/**
	 * Build customer export payload.
	 *
	 * @param int $customer_id Customer ID.
	 * @return array|WP_Error
	 */
	private function build_customer_export_data( $customer_id ) {
		global $wpdb;

		$customer = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT id, first_name, last_name, email, phone, marketing_consent, created_at, deleted_at
				FROM {$wpdb->prefix}bookings_customers
				WHERE id = %d AND deleted_at IS NULL",
				$customer_id
			),
			ARRAY_A
		);

		if ( ! $customer ) {
			if ( class_exists( 'Bookit_Error_Registry' ) ) {
				return Bookit_Error_Registry::to_wp_error(
					'E4013',
					array(
						'customer_id' => $customer_id,
					)
				);
			}

			return new WP_Error(
				'customer_not_found',
				__( 'Customer not found.', 'bookit-booking-system' ),
				array( 'status' => 404 )
			);
		}

		$has_waiver_at = $this->column_exists( $wpdb->prefix . 'bookings', 'waiver_at' );
		$waiver_sql    = $has_waiver_at ? 'b.waiver_at AS waiver_at' : 'NULL AS waiver_at';

		$bookings = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT
					b.id,
					b.booking_reference,
					b.booking_date,
					b.start_time,
					b.end_time,
					b.status,
					b.total_price,
					b.deposit_paid,
					b.balance_due,
					b.payment_method,
					b.special_requests,
					{$waiver_sql},
					s.name AS service_name,
					st.first_name AS staff_first_name,
					st.last_name AS staff_last_name
				FROM {$wpdb->prefix}bookings b
				LEFT JOIN {$wpdb->prefix}bookings_services s ON s.id = b.service_id
				LEFT JOIN {$wpdb->prefix}bookings_staff st ON st.id = b.staff_id
				WHERE b.customer_id = %d
				ORDER BY b.booking_date DESC, b.start_time DESC",
				$customer_id
			),
			ARRAY_A
		);

		$payments_table_exists = $this->table_exists( $wpdb->prefix . 'bookings_payments' );
		$payments              = array();

		if ( $payments_table_exists ) {
			$has_currency_column = $this->column_exists( $wpdb->prefix . 'bookings_payments', 'currency' );
			$currency_sql        = $has_currency_column ? 'p.currency AS currency' : "'GBP' AS currency";

			$payments = $wpdb->get_results(
				$wpdb->prepare(
					"SELECT
						p.booking_id,
						p.amount,
						{$currency_sql},
						p.payment_status,
						p.payment_method,
						p.payment_type,
						p.transaction_date
					FROM {$wpdb->prefix}bookings_payments p
					INNER JOIN {$wpdb->prefix}bookings b ON b.id = p.booking_id
					WHERE b.customer_id = %d
					ORDER BY p.transaction_date DESC, p.id DESC",
					$customer_id
				),
				ARRAY_A
			);
		} else {
			foreach ( $bookings as $booking ) {
				$payments[] = array(
					'booking_id'      => isset( $booking['id'] ) ? (int) $booking['id'] : 0,
					'amount'          => isset( $booking['deposit_paid'] ) ? (float) $booking['deposit_paid'] : 0.0,
					'currency'        => 'GBP',
					'payment_status'  => '',
					'payment_method'  => $booking['payment_method'] ?? '',
					'payment_type'    => isset( $booking['deposit_paid'] ) && (float) $booking['deposit_paid'] > 0 ? 'deposit' : 'full_payment',
					'transaction_date' => $booking['booking_date'] ?? '',
				);
			}
		}

		return array(
			'export_date' => current_time( 'Y-m-d' ),
			'customer'    => array(
				'id'                => (int) $customer['id'],
				'first_name'        => (string) $customer['first_name'],
				'last_name'         => (string) $customer['last_name'],
				'email'             => (string) $customer['email'],
				'phone'             => (string) $customer['phone'],
				'marketing_consent' => (bool) (int) $customer['marketing_consent'],
				'created_at'        => (string) $customer['created_at'],
				'deleted_at'        => $customer['deleted_at'],
			),
			'bookings'    => $bookings,
			'payments'    => $payments,
		);
	}

	/**
	 * Build zip file content for CSV export.
	 *
	 * @param array $export_data Export payload.
	 * @return string|WP_Error
	 */
	private function build_customer_export_zip( $export_data ) {
		$files = array(
			'personal-details.csv' => $this->rows_to_csv(
				array( $export_data['customer'] ),
				array( 'id', 'first_name', 'last_name', 'email', 'phone', 'marketing_consent', 'created_at', 'deleted_at' )
			),
			'bookings.csv'         => $this->rows_to_csv(
				$export_data['bookings'],
				array( 'id', 'booking_reference', 'booking_date', 'start_time', 'end_time', 'status', 'total_price', 'deposit_paid', 'balance_due', 'payment_method', 'special_requests', 'waiver_at', 'service_name', 'staff_first_name', 'staff_last_name' )
			),
			'payments.csv'         => $this->rows_to_csv( $export_data['payments'] ),
		);

		$temp_zip_path = wp_tempnam( 'bookit-customer-export.zip' );
		if ( ! $temp_zip_path ) {
			$temp_zip_path = tempnam( sys_get_temp_dir(), 'bookit-customer-export' );
		}

		if ( ! $temp_zip_path ) {
			return new WP_Error(
				'customer_export_zip_failed',
				__( 'Failed to create export archive.', 'bookit-booking-system' ),
				array( 'status' => 500 )
			);
		}

		if ( class_exists( 'ZipArchive' ) ) {
			$zip = new ZipArchive();
			if ( true !== $zip->open( $temp_zip_path, ZipArchive::CREATE | ZipArchive::OVERWRITE ) ) {
				return new WP_Error(
					'customer_export_zip_failed',
					__( 'Failed to create export archive.', 'bookit-booking-system' ),
					array( 'status' => 500 )
				);
			}

			foreach ( $files as $filename => $contents ) {
				$zip->addFromString( $filename, $contents );
			}
			$zip->close();
		} else {
			require_once ABSPATH . 'wp-admin/includes/class-pclzip.php';
			$pcl_zip = new PclZip( $temp_zip_path );

			$zip_entries = array();
			foreach ( $files as $filename => $contents ) {
				$zip_entries[] = array(
					PCLZIP_ATT_FILE_NAME    => $filename,
					PCLZIP_ATT_FILE_CONTENT => $contents,
				);
			}

			$result = $pcl_zip->create( $zip_entries, PCLZIP_OPT_NO_COMPRESSION );
			if ( 0 === $result ) {
				return new WP_Error(
					'customer_export_zip_failed',
					__( 'Failed to create export archive.', 'bookit-booking-system' ),
					array( 'status' => 500 )
				);
			}
		}

		$zip_content = file_get_contents( $temp_zip_path );
		@unlink( $temp_zip_path );

		if ( false === $zip_content ) {
			return new WP_Error(
				'customer_export_zip_failed',
				__( 'Failed to read export archive.', 'bookit-booking-system' ),
				array( 'status' => 500 )
			);
		}

		return $zip_content;
	}

	/**
	 * Convert rows to CSV string.
	 *
	 * @param array $rows Rows.
	 * @param array $default_headers Default headers if no rows.
	 * @return string
	 */
	private function rows_to_csv( $rows, $default_headers = array() ) {
		$stream = fopen( 'php://temp', 'r+' );
		$rows   = is_array( $rows ) ? $rows : array();

		$headers = $default_headers;
		if ( empty( $headers ) && ! empty( $rows ) && is_array( $rows[0] ) ) {
			$headers = array_keys( $rows[0] );
		}

		if ( ! empty( $headers ) ) {
			fputcsv( $stream, $headers );
		}

		foreach ( $rows as $row ) {
			if ( ! is_array( $row ) ) {
				continue;
			}

			$line = array();
			foreach ( $headers as $header_key ) {
				$value = $row[ $header_key ] ?? null;
				if ( is_array( $value ) || is_object( $value ) ) {
					$value = wp_json_encode( $value );
				}
				$line[] = $value;
			}
			fputcsv( $stream, $line );
		}

		rewind( $stream );
		$csv = stream_get_contents( $stream );
		fclose( $stream );

		return false === $csv ? '' : $csv;
	}

	/**
	 * Check if table exists.
	 *
	 * @param string $table_name Table name.
	 * @return bool
	 */
	private function table_exists( $table_name ) {
		global $wpdb;
		$exists = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table_name ) );
		return $exists === $table_name;
	}

	/**
	 * Check if table column exists.
	 *
	 * @param string $table_name Table name.
	 * @param string $column_name Column name.
	 * @return bool
	 */
	private function column_exists( $table_name, $column_name ) {
		global $wpdb;
		$column = $wpdb->get_var(
			$wpdb->prepare(
				"SHOW COLUMNS FROM `{$table_name}` LIKE %s",
				$column_name
			)
		);
		return ! empty( $column );
	}

	/**
	 * Detect if current execution is under tests.
	 *
	 * @return bool
	 */
	private function is_test_environment() {
		return defined( 'WP_TESTS_PHPUNIT_POLYFILLS_PATH' ) || defined( 'WP_TESTS_DIR' );
	}

	/**
	 * Determine customer status with required priority.
	 *
	 * @param int         $total_bookings Total bookings count.
	 * @param string|null $last_visit Last visit date (Y-m-d) or null.
	 * @return string
	 */
	private function determine_customer_status( $total_bookings, $last_visit ) {
		if ( 1 === (int) $total_bookings ) {
			return 'new';
		}

		if ( empty( $last_visit ) ) {
			return 'inactive';
		}

		$six_months_ago = gmdate( 'Y-m-d', strtotime( '-6 months' ) );
		if ( $last_visit >= $six_months_ago ) {
			return 'active';
		}

		return 'inactive';
	}
}
