<?php
/**
 * Dashboard Bookings REST API Controller
 *
 * Handles dashboard-specific booking endpoints with authentication.
 *
 * @package    Bookit_Booking_System
 * @subpackage Bookit_Booking_System/includes/api
 */

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
	die;
}

/**
 * Class Bookit_Dashboard_Bookings_API
 */
class Bookit_Dashboard_Bookings_API {

	/**
	 * REST API namespace.
	 */
	const NAMESPACE = 'bookit/v1';

	/**
	 * Constructor - Register REST routes.
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
		// Today's bookings.
		register_rest_route(
			self::NAMESPACE,
			'/dashboard/bookings/today',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'get_todays_bookings' ),
				'permission_callback' => array( $this, 'check_dashboard_permission' ),
			)
		);

		// Mark booking as complete.
		register_rest_route(
			self::NAMESPACE,
			'/dashboard/bookings/(?P<id>\d+)/complete',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'mark_booking_complete' ),
				'permission_callback' => array( $this, 'check_dashboard_permission' ),
				'args'                => array(
					'id' => array(
						'required'          => true,
						'validate_callback' => function ( $param ) {
							return is_numeric( $param );
						},
					),
				),
			)
		);

		// Mark booking as no-show.
		register_rest_route(
			self::NAMESPACE,
			'/dashboard/bookings/(?P<id>\d+)/no-show',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'mark_booking_no_show' ),
				'permission_callback' => array( $this, 'check_dashboard_permission' ),
				'args'                => array(
					'id' => array(
						'required'          => true,
						'validate_callback' => function ( $param ) {
							return is_numeric( $param );
						},
					),
				),
			)
		);

		// Bulk booking actions (admin only).
		register_rest_route(
			self::NAMESPACE,
			'/dashboard/bookings/bulk-action',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'bulk_action' ),
				'permission_callback' => array( $this, 'check_admin_permission' ),
			)
		);

		// Staff personal schedule (week view).
		register_rest_route(
			self::NAMESPACE,
			'/dashboard/my-schedule',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'get_my_schedule' ),
				'permission_callback' => array( $this, 'check_dashboard_permission' ),
				'args'                => array(
					'week_start'       => array(
						'required'          => false,
						'validate_callback' => function ( $param ) {
							return empty( $param ) || preg_match( '/^\d{4}-\d{2}-\d{2}$/', $param );
						},
						'sanitize_callback' => 'sanitize_text_field',
					),
					'include_upcoming' => array(
						'required' => false,
						'default'  => true,
						'type'     => 'boolean',
					),
				),
			)
		);

		// Staff personal booking stats.
		register_rest_route(
			self::NAMESPACE,
			'/dashboard/my-stats',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'get_my_stats' ),
				'permission_callback' => array( $this, 'check_dashboard_permission' ),
			)
		);

		// Staff self-service availability blocking.
		register_rest_route(
			self::NAMESPACE,
			'/dashboard/my-availability',
			array(
				array(
					'methods'             => 'GET',
					'callback'            => array( $this, 'get_my_availability' ),
					'permission_callback' => array( $this, 'check_dashboard_permission' ),
				),
				array(
					'methods'             => 'POST',
					'callback'            => array( $this, 'create_my_availability_block' ),
					'permission_callback' => array( $this, 'check_dashboard_permission' ),
					'args'                => array(
						'date_from'  => array(
							'required'          => true,
							'type'              => 'string',
							'validate_callback' => function ( $param ) {
								return is_string( $param ) && preg_match( '/^\d{4}-\d{2}-\d{2}$/', $param );
							},
							'sanitize_callback' => 'sanitize_text_field',
						),
						'date_to'    => array(
							'required'          => true,
							'type'              => 'string',
							'validate_callback' => function ( $param ) {
								return is_string( $param ) && preg_match( '/^\d{4}-\d{2}-\d{2}$/', $param );
							},
							'sanitize_callback' => 'sanitize_text_field',
						),
						'all_day'    => array(
							'required' => true,
							'type'     => 'boolean',
						),
						'start_time' => array(
							'required'          => false,
							'type'              => 'string',
							'validate_callback' => function ( $param, $request ) {
								$all_day = rest_sanitize_boolean( $request->get_param( 'all_day' ) );
								if ( $all_day ) {
									return true;
								}
								return is_string( $param ) && preg_match( '/^\d{2}:\d{2}(:\d{2})?$/', $param );
							},
							'sanitize_callback' => 'sanitize_text_field',
						),
						'end_time'   => array(
							'required'          => false,
							'type'              => 'string',
							'validate_callback' => function ( $param, $request ) {
								$all_day = rest_sanitize_boolean( $request->get_param( 'all_day' ) );
								if ( $all_day ) {
									return true;
								}
								return is_string( $param ) && preg_match( '/^\d{2}:\d{2}(:\d{2})?$/', $param );
							},
							'sanitize_callback' => 'sanitize_text_field',
						),
						'reason'     => array(
							'required' => true,
							'type'     => 'string',
							'enum'     => array( 'vacation', 'sick_leave', 'lunch_break', 'personal', 'other' ),
						),
						'notes'      => array(
							'required'          => false,
							'type'              => 'string',
							'sanitize_callback' => 'sanitize_textarea_field',
						),
						'repeat'     => array(
							'required' => true,
							'type'     => 'string',
							'enum'     => array( 'none', 'daily', 'weekly' ),
						),
					),
				),
			)
		);

		register_rest_route(
			self::NAMESPACE,
			'/dashboard/my-availability/(?P<id>\d+)',
			array(
				'methods'             => 'DELETE',
				'callback'            => array( $this, 'delete_my_availability_block' ),
				'permission_callback' => array( $this, 'check_dashboard_permission' ),
				'args'                => array(
					'id' => array(
						'required'          => true,
						'validate_callback' => function ( $param ) {
							return is_numeric( $param );
						},
					),
				),
			)
		);

		// All bookings with filtering.
		register_rest_route(
			self::NAMESPACE,
			'/dashboard/bookings',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'get_all_bookings' ),
				'permission_callback' => array( $this, 'check_dashboard_permission' ),
				'args'                => array(
					'page'       => array(
						'default'           => 1,
						'validate_callback' => function ( $param ) {
							return is_numeric( $param ) && $param > 0;
						},
					),
					'per_page'   => array(
						'default'           => 20,
						'validate_callback' => function ( $param ) {
							return is_numeric( $param ) && $param > 0 && $param <= 100;
						},
					),
					'date_from'  => array(
						'validate_callback' => function ( $param ) {
							return empty( $param ) || preg_match( '/^\d{4}-\d{2}-\d{2}$/', $param );
						},
					),
					'date_to'    => array(
						'validate_callback' => function ( $param ) {
							return empty( $param ) || preg_match( '/^\d{4}-\d{2}-\d{2}$/', $param );
						},
					),
					'staff_id'   => array(
						'validate_callback' => function ( $param ) {
							return empty( $param ) || is_numeric( $param );
						},
					),
					'service_id' => array(
						'validate_callback' => function ( $param ) {
							return empty( $param ) || is_numeric( $param );
						},
					),
					'customer_id' => array(
						'required'          => false,
						'type'              => 'integer',
						'minimum'           => 1,
						'sanitize_callback' => 'absint',
					),
					'status'     => array(
						'validate_callback' => function ( $param ) {
							$valid_statuses = array( 'pending', 'pending_payment', 'confirmed', 'completed', 'cancelled', 'no_show' );
							return empty( $param ) || in_array( $param, $valid_statuses, true );
						},
					),
					'search'     => array(
						'sanitize_callback' => 'sanitize_text_field',
					),
					'order_by'   => array(
						'default'           => 'booking_date',
						'validate_callback' => function ( $param ) {
							$valid_columns = array( 'booking_date', 'start_time', 'status', 'created_at' );
							return in_array( $param, $valid_columns, true );
						},
					),
					'order'      => array(
						'default'           => 'DESC',
						'validate_callback' => function ( $param ) {
							return in_array( strtoupper( $param ), array( 'ASC', 'DESC' ), true );
						},
					),
				),
			)
		);

		// Get staff list.
		register_rest_route(
			self::NAMESPACE,
			'/dashboard/staff/list',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'get_staff_list' ),
				'permission_callback' => array( $this, 'check_dashboard_permission' ),
				'args'                => array(
					'search'     => array(
						'type'              => 'string',
						'sanitize_callback' => 'sanitize_text_field',
					),
					'role'       => array(
						'type'    => 'string',
						'enum'    => array( 'admin', 'staff', 'all' ),
						'default' => 'all',
					),
					'status'     => array(
						'type'    => 'string',
						'enum'    => array( 'active', 'inactive', 'all' ),
						'default' => 'all',
					),
					'service_id' => array(
						'type'              => 'integer',
						'validate_callback' => function ( $param ) {
							return is_numeric( $param );
						},
					),
				),
			)
		);

		// Get staff list for specific service (filtered by staff_services).
		register_rest_route(
			self::NAMESPACE,
			'/dashboard/staff/by-service/(?P<service_id>\d+)',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'get_staff_by_service' ),
				'permission_callback' => array( $this, 'check_dashboard_permission' ),
			)
		);

		// Get/Update/Delete single staff.
		register_rest_route(
			self::NAMESPACE,
			'/dashboard/staff/(?P<id>\d+)',
			array(
				array(
					'methods'             => 'GET',
					'callback'            => array( $this, 'get_staff_details' ),
					'permission_callback' => array( $this, 'check_admin_permission' ),
				),
				array(
					'methods'             => 'PUT',
					'callback'            => array( $this, 'update_staff' ),
					'permission_callback' => array( $this, 'check_admin_permission' ),
					'args'                => array(
						'email'               => array(
							'required'          => true,
							'type'              => 'string',
							'validate_callback' => function ( $param ) {
								return is_email( $param );
							},
						),
						'first_name'          => array(
							'required'          => true,
							'type'              => 'string',
							'sanitize_callback' => 'sanitize_text_field',
						),
						'last_name'           => array(
							'required'          => true,
							'type'              => 'string',
							'sanitize_callback' => 'sanitize_text_field',
						),
						'phone'               => array(
							'type'              => 'string',
							'sanitize_callback' => 'sanitize_text_field',
						),
						'photo_url'           => array(
							'type'              => 'string',
							'sanitize_callback' => 'esc_url_raw',
						),
						'bio'                 => array(
							'type'              => 'string',
							'sanitize_callback' => 'sanitize_textarea_field',
						),
						'title'               => array(
							'type'              => 'string',
							'sanitize_callback' => 'sanitize_text_field',
						),
						'role'                => array(
							'required' => true,
							'type'     => 'string',
							'enum'     => array( 'staff', 'admin' ),
						),
						'google_calendar_id'  => array(
							'type'              => 'string',
							'sanitize_callback' => 'sanitize_text_field',
						),
						'is_active'           => array(
							'type'    => 'boolean',
							'default' => true,
						),
						'display_order'       => array(
							'type'    => 'integer',
							'default' => 0,
						),
						'service_assignments' => array(
							'type'              => 'array',
							'default'           => array(),
							'sanitize_callback' => function ( $param ) {
								// Allow the array through, we'll validate in the method.
								return is_array( $param ) ? $param : array();
							},
						),
						'notification_preferences' => array(
							'type'              => 'object',
							'sanitize_callback' => function ( $param ) {
								return is_array( $param ) ? $param : null;
							},
						),
					),
				),
				array(
					'methods'             => 'DELETE',
					'callback'            => array( $this, 'delete_staff' ),
					'permission_callback' => array( $this, 'check_admin_permission' ),
				),
			)
		);

		// Upload staff photo.
		register_rest_route(
			self::NAMESPACE,
			'/dashboard/staff/(?P<id>\d+)/photo',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'upload_staff_photo' ),
				'permission_callback' => array( $this, 'check_dashboard_permission' ),
				'args'                => array(
					'id' => array(
						'required'          => true,
						'validate_callback' => function ( $param ) {
							return is_numeric( $param ) && (int) $param > 0;
						},
					),
				),
			)
		);

		// Create new staff.
		register_rest_route(
			self::NAMESPACE,
			'/dashboard/staff/create',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'create_staff' ),
				'permission_callback' => array( $this, 'check_admin_permission' ),
				'args'                => array(
					'email'               => array(
						'required'          => true,
						'type'              => 'string',
						'validate_callback' => function ( $param ) {
							return is_email( $param );
						},
					),
					'password'            => array(
						'required'          => true,
						'type'              => 'string',
						'validate_callback' => function ( $param ) {
							return strlen( $param ) >= 8;
						},
					),
					'first_name'          => array(
						'required'          => true,
						'type'              => 'string',
						'sanitize_callback' => 'sanitize_text_field',
					),
					'last_name'           => array(
						'required'          => true,
						'type'              => 'string',
						'sanitize_callback' => 'sanitize_text_field',
					),
					'phone'               => array(
						'type'              => 'string',
						'sanitize_callback' => 'sanitize_text_field',
					),
					'photo_url'           => array(
						'type'              => 'string',
						'sanitize_callback' => 'esc_url_raw',
					),
					'bio'                 => array(
						'type'              => 'string',
						'sanitize_callback' => 'sanitize_textarea_field',
					),
					'title'               => array(
						'type'              => 'string',
						'sanitize_callback' => 'sanitize_text_field',
					),
					'role'                => array(
						'required' => true,
						'type'     => 'string',
						'enum'     => array( 'staff', 'admin' ),
					),
					'google_calendar_id'  => array(
						'type'              => 'string',
						'sanitize_callback' => 'sanitize_text_field',
					),
					'is_active'           => array(
						'type'    => 'boolean',
						'default' => true,
					),
					'display_order'       => array(
						'type'    => 'integer',
						'default' => 0,
					),
					'service_assignments' => array(
						'type'              => 'array',
						'default'           => array(),
						'sanitize_callback' => function ( $param ) {
							// Allow the array through, we'll validate in the method.
							return is_array( $param ) ? $param : array();
						},
					),
				),
			)
		);

		// Reset staff password.
		register_rest_route(
			self::NAMESPACE,
			'/dashboard/staff/(?P<id>\d+)/reset-password',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'reset_staff_password' ),
				'permission_callback' => array( $this, 'check_admin_permission' ),
				'args'                => array(
					'new_password' => array(
						'required'          => true,
						'type'              => 'string',
						'validate_callback' => function ( $param ) {
							return strlen( $param ) >= 8;
						},
					),
					'send_email'   => array(
						'type'    => 'boolean',
						'default' => false,
					),
				),
			)
		);

		// Reorder staff.
		register_rest_route(
			self::NAMESPACE,
			'/dashboard/staff/reorder',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'reorder_staff' ),
				'permission_callback' => array( $this, 'check_admin_permission' ),
				'args'                => array(
					'staff' => array(
						'required' => true,
						'type'     => 'array',
						'items'    => array(
							'type'       => 'object',
							'properties' => array(
								'id'            => array( 'type' => 'integer' ),
								'display_order' => array( 'type' => 'integer' ),
							),
						),
					),
				),
			)
		);

		// Get services list for filter dropdown.
		register_rest_route(
			self::NAMESPACE,
			'/dashboard/services/list',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'get_services_list' ),
				'permission_callback' => array( $this, 'check_dashboard_permission' ),
				'args'                => array(
					'search'      => array(
						'type'              => 'string',
						'sanitize_callback' => 'sanitize_text_field',
					),
					'category_id' => array(
						'type'              => 'integer',
						'validate_callback' => function ( $param ) {
							return is_numeric( $param );
						},
					),
					'status'      => array(
						'type'    => 'string',
						'enum'    => array( 'active', 'inactive', 'all' ),
						'default' => 'all',
					),
					'page'        => array(
						'type'    => 'integer',
						'default' => 1,
						'minimum' => 1,
					),
					'per_page'    => array(
						'type'    => 'integer',
						'default' => 50,
						'minimum' => 1,
						'maximum' => 100,
					),
				),
			)
		);

		// Get/Update/Delete single service.
		register_rest_route(
			self::NAMESPACE,
			'/dashboard/services/(?P<id>\d+)',
			array(
				array(
					'methods'             => 'GET',
					'callback'            => array( $this, 'get_service_details' ),
					'permission_callback' => array( $this, 'check_admin_permission' ),
				),
				array(
					'methods'             => 'PUT',
					'callback'            => array( $this, 'update_service' ),
					'permission_callback' => array( $this, 'check_admin_permission' ),
					'args'                => array(
						'name'           => array(
							'required'          => true,
							'type'              => 'string',
							'sanitize_callback' => 'sanitize_text_field',
						),
						'description'    => array(
							'type'              => 'string',
							'sanitize_callback' => 'sanitize_textarea_field',
						),
						'duration'       => array(
							'required'          => true,
							'type'              => 'integer',
							'validate_callback' => function ( $param ) {
								return is_numeric( $param ) && $param > 0;
							},
						),
						'price'          => array(
							'required'          => true,
							'type'              => 'number',
							'validate_callback' => function ( $param ) {
								return is_numeric( $param ) && $param >= 0;
							},
						),
						'deposit_amount' => array(
							'type'              => 'number',
							'validate_callback' => function ( $param ) {
								return null === $param || ( is_numeric( $param ) && $param >= 0 );
							},
						),
						'deposit_type'   => array(
							'type'    => 'string',
							'enum'    => array( 'fixed', 'percentage' ),
							'default' => 'fixed',
						),
						'buffer_before'  => array(
							'type'    => 'integer',
							'default' => 0,
						),
						'buffer_after'   => array(
							'type'    => 'integer',
							'default' => 0,
						),
						'category_ids'   => array(
							'type'  => 'array',
							'items' => array(
								'type' => 'integer',
							),
						),
						'is_active'      => array(
							'type'    => 'boolean',
							'default' => true,
						),
						'display_order'  => array(
							'type'    => 'integer',
							'default' => 0,
						),
					),
				),
				array(
					'methods'             => 'DELETE',
					'callback'            => array( $this, 'delete_service' ),
					'permission_callback' => array( $this, 'check_admin_permission' ),
				),
			)
		);

		// Create new service.
		register_rest_route(
			self::NAMESPACE,
			'/dashboard/services/create',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'create_service' ),
				'permission_callback' => array( $this, 'check_admin_permission' ),
				'args'                => array(
					'name'           => array(
						'required'          => true,
						'type'              => 'string',
						'sanitize_callback' => 'sanitize_text_field',
					),
					'description'    => array(
						'type'              => 'string',
						'sanitize_callback' => 'sanitize_textarea_field',
					),
					'duration'       => array(
						'required'          => true,
						'type'              => 'integer',
						'validate_callback' => function ( $param ) {
							return is_numeric( $param ) && $param > 0;
						},
					),
					'price'          => array(
						'required'          => true,
						'type'              => 'number',
						'validate_callback' => function ( $param ) {
							return is_numeric( $param ) && $param >= 0;
						},
					),
					'deposit_amount' => array(
						'type'              => 'number',
						'validate_callback' => function ( $param ) {
							return null === $param || ( is_numeric( $param ) && $param >= 0 );
						},
					),
					'deposit_type'   => array(
						'type'    => 'string',
						'enum'    => array( 'fixed', 'percentage' ),
						'default' => 'fixed',
					),
					'buffer_before'  => array(
						'type'    => 'integer',
						'default' => 0,
					),
					'buffer_after'   => array(
						'type'    => 'integer',
						'default' => 0,
					),
					'category_ids'   => array(
						'type'  => 'array',
						'items' => array(
							'type' => 'integer',
						),
					),
					'is_active'      => array(
						'type'    => 'boolean',
						'default' => true,
					),
					'display_order'  => array(
						'type'    => 'integer',
						'default' => 0,
					),
				),
			)
		);

		// Update display order for multiple services.
		register_rest_route(
			self::NAMESPACE,
			'/dashboard/services/reorder',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'reorder_services' ),
				'permission_callback' => array( $this, 'check_admin_permission' ),
				'args'                => array(
					'services' => array(
						'required' => true,
						'type'     => 'array',
						'items'    => array(
							'type'       => 'object',
							'properties' => array(
								'id'            => array( 'type' => 'integer' ),
								'display_order' => array( 'type' => 'integer' ),
							),
						),
					),
				),
			)
		);

		// Manual booking creation.
		register_rest_route(
			self::NAMESPACE,
			'/dashboard/bookings/create',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'create_manual_booking' ),
				'permission_callback' => array( $this, 'check_dashboard_permission' ),
				'args'                => array(
					'customer_id'         => array(
						'required'          => false,
						'validate_callback' => function ( $param ) {
							return empty( $param ) || is_numeric( $param );
						},
					),
					'customer_email'      => array(
						'required'          => false,
						'sanitize_callback' => 'sanitize_email',
						'validate_callback' => function ( $param ) {
							return empty( $param ) || is_email( $param );
						},
					),
					'customer_first_name' => array(
						'sanitize_callback' => 'sanitize_text_field',
					),
					'customer_last_name'  => array(
						'sanitize_callback' => 'sanitize_text_field',
					),
					'customer_phone'      => array(
						'sanitize_callback' => 'sanitize_text_field',
					),
					'service_id'          => array(
						'required'          => true,
						'validate_callback' => function ( $param ) {
							return is_numeric( $param );
						},
					),
					'staff_id'            => array(
						'required'          => true,
						'validate_callback' => function ( $param ) {
							return is_numeric( $param );
						},
					),
					'booking_date'        => array(
						'required'          => true,
						'validate_callback' => function ( $param ) {
							return preg_match( '/^\d{4}-\d{2}-\d{2}$/', $param );
						},
					),
					'booking_time'        => array(
						'required'          => true,
						'validate_callback' => function ( $param ) {
							return preg_match( '/^\d{2}:\d{2}(:\d{2})?$/', $param );
						},
					),
					'payment_method'      => array(
						'required'          => true,
						'validate_callback' => function ( $param ) {
							$valid_methods = array( 'pay_on_arrival', 'manual', 'cash', 'card_external', 'check', 'complimentary', 'stripe' );
							return in_array( $param, $valid_methods, true );
						},
					),
					'amount_paid'         => array(
						'default'           => 0,
						'validate_callback' => function ( $param ) {
							return is_numeric( $param ) && $param >= 0;
						},
					),
					'special_requests'    => array(
						'sanitize_callback' => 'sanitize_textarea_field',
					),
					'send_confirmation'   => array(
						'default'           => true,
						'validate_callback' => function ( $param ) {
							return is_bool( $param ) || in_array( $param, array( 'true', 'false', '1', '0', 1, 0 ), true );
						},
					),
				),
			)
		);

		// Get single booking details.
		register_rest_route(
			self::NAMESPACE,
			'/dashboard/bookings/(?P<id>\d+)',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'get_booking_details' ),
				'permission_callback' => array( $this, 'check_dashboard_permission' ),
			)
		);

		// Update booking.
		register_rest_route(
			self::NAMESPACE,
			'/dashboard/bookings/(?P<id>\d+)',
			array(
				'methods'             => 'PUT',
				'callback'            => array( $this, 'update_booking' ),
				'permission_callback' => array( $this, 'check_dashboard_permission' ),
				'args'                => array(
					'service_id'        => array(
						'required'          => true,
						'validate_callback' => function ( $param ) {
							return is_numeric( $param );
						},
					),
					'staff_id'          => array(
						'required'          => true,
						'validate_callback' => function ( $param ) {
							return is_numeric( $param );
						},
					),
					'booking_date'      => array(
						'required'          => true,
						'validate_callback' => function ( $param ) {
							return preg_match( '/^\d{4}-\d{2}-\d{2}$/', $param );
						},
					),
					'booking_time'      => array(
						'required'          => true,
						'validate_callback' => function ( $param ) {
							return preg_match( '/^\d{2}:\d{2}(:\d{2})?$/', $param );
						},
					),
					'status'            => array(
						'required'          => true,
						'validate_callback' => function ( $param ) {
							$valid_statuses = array( 'pending', 'pending_payment', 'confirmed', 'completed', 'cancelled', 'no_show' );
							return in_array( $param, $valid_statuses, true );
						},
					),
					'payment_method'    => array(
						'required'          => true,
					),
					'amount_paid'       => array(
						'default'           => 0,
						'validate_callback' => function ( $param ) {
							return is_numeric( $param ) && $param >= 0;
						},
					),
					'special_requests'  => array(
						'sanitize_callback' => 'sanitize_textarea_field',
					),
					'staff_notes'       => array(
						'sanitize_callback' => 'sanitize_textarea_field',
					),
					'send_notification' => array(
						'default'           => false,
						'validate_callback' => function ( $param ) {
							return is_bool( $param ) || in_array( $param, array( 'true', 'false', '1', '0', 1, 0 ), true );
						},
					),
				),
			)
		);

		// Cancel booking.
		register_rest_route(
			self::NAMESPACE,
			'/dashboard/bookings/(?P<id>\d+)/cancel',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'cancel_booking' ),
				'permission_callback' => array( $this, 'check_dashboard_permission' ),
				'args'                => array(
					'cancellation_reason' => array(
						'sanitize_callback' => 'sanitize_textarea_field',
					),
					'send_notification'   => array(
						'default'           => true,
						'validate_callback' => function ( $param ) {
							return is_bool( $param ) || in_array( $param, array( 'true', 'false', '1', '0', 1, 0 ), true );
						},
					),
				),
			)
		);

		// Get categories list for dropdowns.
		register_rest_route(
			self::NAMESPACE,
			'/dashboard/categories/list',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'get_categories_list' ),
				'permission_callback' => array( $this, 'check_dashboard_permission' ),
				'args'                => array(
					'search'      => array(
						'type'              => 'string',
						'sanitize_callback' => 'sanitize_text_field',
					),
					'status'      => array(
						'type'    => 'string',
						'enum'    => array( 'active', 'inactive', 'all' ),
						'default' => 'all',
					),
					'include_all' => array(
						'type'    => 'boolean',
						'default' => false,
					),
				),
			)
		);

		// Get/Update/Delete single category.
		register_rest_route(
			self::NAMESPACE,
			'/dashboard/categories/(?P<id>\d+)',
			array(
				array(
					'methods'             => 'GET',
					'callback'            => array( $this, 'get_category_details' ),
					'permission_callback' => array( $this, 'check_admin_permission' ),
				),
				array(
					'methods'             => 'PUT',
					'callback'            => array( $this, 'update_category' ),
					'permission_callback' => array( $this, 'check_admin_permission' ),
					'args'                => array(
						'name'          => array(
							'required'          => true,
							'type'              => 'string',
							'sanitize_callback' => 'sanitize_text_field',
						),
						'description'   => array(
							'type'              => 'string',
							'sanitize_callback' => 'sanitize_textarea_field',
						),
						'is_active'     => array(
							'type'    => 'boolean',
							'default' => true,
						),
						'display_order' => array(
							'type'    => 'integer',
							'default' => 0,
						),
					),
				),
				array(
					'methods'             => 'DELETE',
					'callback'            => array( $this, 'delete_category' ),
					'permission_callback' => array( $this, 'check_admin_permission' ),
				),
			)
		);

		// Create new category.
		register_rest_route(
			self::NAMESPACE,
			'/dashboard/categories/create',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'create_category' ),
				'permission_callback' => array( $this, 'check_admin_permission' ),
				'args'                => array(
					'name'          => array(
						'required'          => true,
						'type'              => 'string',
						'sanitize_callback' => 'sanitize_text_field',
					),
					'description'   => array(
						'type'              => 'string',
						'sanitize_callback' => 'sanitize_textarea_field',
					),
					'is_active'     => array(
						'type'    => 'boolean',
						'default' => true,
					),
					'display_order' => array(
						'type'    => 'integer',
						'default' => 0,
					),
				),
			)
		);

		// Reorder categories.
		register_rest_route(
			self::NAMESPACE,
			'/dashboard/categories/reorder',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'reorder_categories' ),
				'permission_callback' => array( $this, 'check_admin_permission' ),
				'args'                => array(
					'categories' => array(
						'required' => true,
						'type'     => 'array',
						'items'    => array(
							'type'       => 'object',
							'properties' => array(
								'id'            => array( 'type' => 'integer' ),
								'display_order' => array( 'type' => 'integer' ),
							),
						),
					),
				),
			)
		);

		// Customer search endpoint.
		register_rest_route(
			self::NAMESPACE,
			'/dashboard/customers/search',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'search_customers' ),
				'permission_callback' => array( $this, 'check_dashboard_permission' ),
				'args'                => array(
					'search' => array(
						'required'          => true,
						'sanitize_callback' => 'sanitize_text_field',
					),
				),
			)
		);

		// Get working hours for a staff member.
		register_rest_route(
			self::NAMESPACE,
			'/dashboard/staff/(?P<staff_id>\d+)/hours',
			array(
				array(
					'methods'             => 'GET',
					'callback'            => array( $this, 'get_working_hours' ),
					'permission_callback' => array( $this, 'check_admin_permission' ),
				),
				array(
					'methods'             => 'POST',
					'callback'            => array( $this, 'save_working_hours' ),
					'permission_callback' => array( $this, 'check_admin_permission' ),
					'args'                => array(
						'schedule' => array(
							'required'          => true,
							'type'              => 'array',
							'sanitize_callback' => function ( $param ) {
								return is_array( $param ) ? $param : array();
							},
						),
					),
				),
			)
		);

		// Get/Update/Delete single working hours record.
		register_rest_route(
			self::NAMESPACE,
			'/dashboard/staff/(?P<staff_id>\d+)/hours/(?P<id>\d+)',
			array(
				array(
					'methods'             => 'PUT',
					'callback'            => array( $this, 'update_working_hours_record' ),
					'permission_callback' => array( $this, 'check_admin_permission' ),
				),
				array(
					'methods'             => 'DELETE',
					'callback'            => array( $this, 'delete_working_hours_record' ),
					'permission_callback' => array( $this, 'check_admin_permission' ),
				),
			)
		);

		// Exception management (specific dates).
		register_rest_route(
			self::NAMESPACE,
			'/dashboard/staff/(?P<staff_id>\d+)/hours/exceptions',
			array(
				array(
					'methods'             => 'GET',
					'callback'            => array( $this, 'get_exceptions' ),
					'permission_callback' => array( $this, 'check_admin_permission' ),
				),
				array(
					'methods'             => 'POST',
					'callback'            => array( $this, 'add_exception' ),
					'permission_callback' => array( $this, 'check_admin_permission' ),
					'args'                => array(
						'specific_date' => array(
							'required'          => true,
							'type'              => 'string',
							'sanitize_callback' => 'sanitize_text_field',
						),
						'is_working'    => array(
							'required' => true,
							'type'     => 'boolean',
						),
						'start_time'    => array(
							'type'              => 'string',
							'sanitize_callback' => 'sanitize_text_field',
						),
						'end_time'      => array(
							'type'              => 'string',
							'sanitize_callback' => 'sanitize_text_field',
						),
						'break_start'   => array(
							'type'              => 'string',
							'sanitize_callback' => 'sanitize_text_field',
						),
						'break_end'     => array(
							'type'              => 'string',
							'sanitize_callback' => 'sanitize_text_field',
						),
						'notes'         => array(
							'type'              => 'string',
							'sanitize_callback' => 'sanitize_textarea_field',
						),
					),
				),
			)
		);

		// Delete exception.
		register_rest_route(
			self::NAMESPACE,
			'/dashboard/staff/(?P<staff_id>\d+)/hours/exceptions/(?P<id>\d+)',
			array(
				'methods'             => 'DELETE',
				'callback'            => array( $this, 'delete_exception' ),
				'permission_callback' => array( $this, 'check_admin_permission' ),
			)
		);

		// Check for conflicts before bulk working hours operation.
		register_rest_route(
			self::NAMESPACE,
			'/dashboard/staff/bulk-hours/check-conflicts',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'check_bulk_conflicts' ),
				'permission_callback' => array( $this, 'check_admin_permission' ),
				'args'                => array(
					'staff_ids'     => array(
						'required'          => true,
						'type'              => 'array',
						'sanitize_callback' => function ( $param ) {
							return is_array( $param ) ? array_map( 'intval', $param ) : array();
						},
					),
					'specific_date' => array(
						'type'              => 'string',
						'sanitize_callback' => 'sanitize_text_field',
					),
					'day_of_week'   => array(
						'type' => 'integer',
					),
				),
			)
		);

		// Add exception to multiple staff.
		register_rest_route(
			self::NAMESPACE,
			'/dashboard/staff/bulk-hours/add-exception',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'bulk_add_exception' ),
				'permission_callback' => array( $this, 'check_admin_permission' ),
				'args'                => array(
					'staff_ids'            => array(
						'required'          => true,
						'type'              => 'array',
						'sanitize_callback' => function ( $param ) {
							return is_array( $param ) ? array_map( 'intval', $param ) : array();
						},
					),
					'specific_date'        => array(
						'required'          => true,
						'type'              => 'string',
						'sanitize_callback' => 'sanitize_text_field',
					),
					'is_working'           => array(
						'required' => true,
						'type'     => 'boolean',
					),
					'start_time'           => array(
						'type'              => 'string',
						'sanitize_callback' => 'sanitize_text_field',
					),
					'end_time'             => array(
						'type'              => 'string',
						'sanitize_callback' => 'sanitize_text_field',
					),
					'break_start'          => array(
						'type'              => 'string',
						'sanitize_callback' => 'sanitize_text_field',
					),
					'break_end'            => array(
						'type'              => 'string',
						'sanitize_callback' => 'sanitize_text_field',
					),
					'notes'                => array(
						'type'              => 'string',
						'sanitize_callback' => 'sanitize_textarea_field',
					),
					'overwrite_conflicts'  => array(
						'type'              => 'array',
						'sanitize_callback' => function ( $param ) {
							return is_array( $param ) ? array_map( 'intval', $param ) : array();
						},
					),
				),
			)
		);

		// Update schedule for multiple staff.
		register_rest_route(
			self::NAMESPACE,
			'/dashboard/staff/bulk-hours/update-schedule',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'bulk_update_schedule' ),
				'permission_callback' => array( $this, 'check_admin_permission' ),
				'args'                => array(
					'staff_ids'   => array(
						'required'          => true,
						'type'              => 'array',
						'sanitize_callback' => function ( $param ) {
							return is_array( $param ) ? array_map( 'intval', $param ) : array();
						},
					),
					'day_of_week' => array(
						'required' => true,
						'type'     => 'integer',
					),
					'updates'     => array(
						'required'          => true,
						'type'              => 'object',
						'sanitize_callback' => function ( $param ) {
							return is_array( $param ) ? $param : array();
						},
					),
				),
			)
		);

		// Get/Update current user's profile.
		register_rest_route(
			self::NAMESPACE,
			'/dashboard/profile',
			array(
				array(
					'methods'             => 'GET',
					'callback'            => array( $this, 'get_my_profile' ),
					'permission_callback' => array( $this, 'check_dashboard_permission' ),
				),
				array(
					'methods'             => 'PUT',
					'callback'            => array( $this, 'update_my_profile' ),
					'permission_callback' => array( $this, 'check_dashboard_permission' ),
					'args'                => array(
						'first_name' => array(
							'type'              => 'string',
							'sanitize_callback' => 'sanitize_text_field',
						),
						'last_name'  => array(
							'type'              => 'string',
							'sanitize_callback' => 'sanitize_text_field',
						),
						'email'      => array(
							'type'              => 'string',
							'validate_callback' => function ( $param ) {
								return is_email( $param );
							},
						),
						'phone'      => array(
							'type'              => 'string',
							'sanitize_callback' => 'sanitize_text_field',
						),
						'title'      => array(
							'type'              => 'string',
							'sanitize_callback' => 'sanitize_text_field',
						),
						'bio'        => array(
							'type'              => 'string',
							'sanitize_callback' => 'sanitize_textarea_field',
						),
						'photo_url'  => array(
							'type'              => 'string',
							'sanitize_callback' => 'esc_url_raw',
						),
					),
				),
			)
		);

		register_rest_route(
			self::NAMESPACE,
			'/dashboard/profile/notification-preferences',
			array(
				'methods'             => 'PUT',
				'callback'            => array( $this, 'update_notification_preferences' ),
				'permission_callback' => array( $this, 'check_dashboard_permission' ),
				'args'                => array(
					'new_booking'    => array(
						'type'              => 'string',
						'enum'              => array( 'immediate', 'daily', 'weekly' ),
						'sanitize_callback' => 'sanitize_text_field',
					),
					'reschedule'     => array(
						'type'              => 'string',
						'enum'              => array( 'immediate', 'daily', 'weekly' ),
						'sanitize_callback' => 'sanitize_text_field',
					),
					'cancellation'   => array(
						'type'              => 'string',
						'enum'              => array( 'immediate', 'daily', 'weekly' ),
						'sanitize_callback' => 'sanitize_text_field',
					),
					'daily_schedule' => array(
						'type' => 'boolean',
					),
				),
			)
		);

		// Change password.
		register_rest_route(
			self::NAMESPACE,
			'/dashboard/profile/change-password',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'change_password' ),
				'permission_callback' => array( $this, 'check_dashboard_permission' ),
				'args'                => array(
					'current_password' => array(
						'required' => true,
						'type'     => 'string',
					),
					'new_password'     => array(
						'required'          => true,
						'type'              => 'string',
						'validate_callback' => function ( $param ) {
							return strlen( $param ) >= 8;
						},
					),
				),
			)
		);

		// Logout.
		register_rest_route(
			self::NAMESPACE,
			'/dashboard/logout',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'logout' ),
				'permission_callback' => array( $this, 'check_dashboard_permission' ),
			)
		);

		// Verify password (for email changes).
		register_rest_route(
			self::NAMESPACE,
			'/dashboard/profile/verify-password',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'verify_password' ),
				'permission_callback' => array( $this, 'check_dashboard_permission' ),
				'args'                => array(
					'password' => array(
						'required' => true,
						'type'     => 'string',
					),
				),
			)
		);

		// Get/Update settings.
		register_rest_route(
			self::NAMESPACE,
			'/dashboard/settings',
			array(
				array(
					'methods'             => 'GET',
					'callback'            => array( $this, 'get_settings' ),
					'permission_callback' => array( $this, 'check_admin_permission' ),
					'args'                => array(
						'keys' => array(
							'type'              => 'string',
							'sanitize_callback' => 'sanitize_text_field',
						),
					),
				),
				array(
					'methods'             => 'POST',
					'callback'            => array( $this, 'update_settings' ),
					'permission_callback' => array( $this, 'check_admin_permission' ),
					'args'                => array(
						'settings' => array(
							'required'          => true,
							'type'              => 'array',
							'sanitize_callback' => function ( $param ) {
								return is_array( $param ) ? $param : array();
							},
						),
					),
				),
			)
		);

		// Get/Update dashboard branding settings.
		register_rest_route(
			self::NAMESPACE,
			'/dashboard/settings/branding',
			array(
				array(
					'methods'             => 'GET',
					'callback'            => array( $this, 'get_branding_settings' ),
					'permission_callback' => array( $this, 'check_dashboard_permission' ),
				),
				array(
					'methods'             => 'POST',
					'callback'            => array( $this, 'update_branding_settings' ),
					'permission_callback' => array( $this, 'check_admin_permission' ),
				),
				array(
					'methods'             => 'PATCH',
					'callback'            => array( $this, 'update_branding_settings' ),
					'permission_callback' => array( $this, 'check_admin_permission' ),
				),
			)
		);

		// Send test email.
		register_rest_route(
			self::NAMESPACE,
			'/dashboard/settings/test-email',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'send_test_email' ),
				'permission_callback' => array( $this, 'check_admin_permission' ),
				'args'                => array(
					'to_email' => array(
						'required'          => true,
						'type'              => 'string',
						'validate_callback' => function ( $param ) {
							return is_email( $param );
						},
					),
				),
			)
		);

		// Get all email templates.
		register_rest_route(
			self::NAMESPACE,
			'/dashboard/email-templates',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'get_email_templates' ),
				'permission_callback' => array( $this, 'check_admin_permission' ),
			)
		);

		// Update/Reset email template by key.
		register_rest_route(
			self::NAMESPACE,
			'/dashboard/email-templates/(?P<key>[a-z_]+)',
			array(
				array(
					'methods'             => 'PUT',
					'callback'            => array( $this, 'update_email_template' ),
					'permission_callback' => array( $this, 'check_admin_permission' ),
					'args'                => array(
						'subject' => array(
							'required'          => true,
							'type'              => 'string',
							'sanitize_callback' => 'sanitize_text_field',
						),
						'body'    => array(
							'required'          => true,
							'type'              => 'string',
							'sanitize_callback' => 'wp_kses_post',
						),
						'enabled' => array(
							'type' => 'boolean',
						),
					),
				),
				array(
					'methods'             => 'POST',
					'callback'            => array( $this, 'reset_email_template' ),
					'permission_callback' => array( $this, 'check_admin_permission' ),
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
		// Load auth classes if not loaded.
		if ( ! class_exists( 'Bookit_Session' ) ) {
			require_once plugin_dir_path( dirname( __FILE__ ) ) . 'class-bookit-session.php';
		}
		if ( ! class_exists( 'Bookit_Auth' ) ) {
			require_once plugin_dir_path( dirname( __FILE__ ) ) . 'class-bookit-auth.php';
		}

		// Check if logged in.
		if ( ! Bookit_Auth::is_logged_in() ) {
			return Bookit_Error_Registry::to_wp_error( 'E1002' );
		}

		return true;
	}

	/**
	 * Check if user has admin permission.
	 * Only admins can manage services.
	 *
	 * @return bool|WP_Error
	 */
	public function check_admin_permission() {
		return self::check_admin_permission_callback();
	}

	/**
	 * Static permission callback for REST routes outside this class instance.
	 *
	 * @return bool|WP_Error
	 */
	public static function check_admin_permission_callback() {
		// Load auth classes if not loaded.
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
	 * Get today's bookings.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response|WP_Error
	 */
	public function get_todays_bookings( $request ) {
		global $wpdb;

		$current_staff = Bookit_Auth::get_current_staff();
		if ( ! $current_staff ) {
			return new WP_Error(
				'unauthorized',
				__( 'Could not retrieve staff information.', 'bookit-booking-system' ),
				array( 'status' => 401 )
			);
		}

		$today = current_time( 'Y-m-d' );

		// Build query with role-based filtering.
		$query = "
			SELECT
				b.id,
				b.booking_reference,
				b.booking_date,
				b.start_time,
				b.end_time,
				b.duration,
				b.status,
				b.total_price,
				b.deposit_paid,
				b.balance_due,
				b.full_amount_paid,
				b.payment_method,
				b.special_requests,
				b.staff_notes,
				c.first_name AS customer_first_name,
				c.last_name AS customer_last_name,
				c.email AS customer_email,
				c.phone AS customer_phone,
				s.name AS service_name,
				st.first_name AS staff_first_name,
				st.last_name AS staff_last_name
			FROM {$wpdb->prefix}bookings b
			LEFT JOIN {$wpdb->prefix}bookings_customers c ON b.customer_id = c.id
			INNER JOIN {$wpdb->prefix}bookings_services s ON b.service_id = s.id
			INNER JOIN {$wpdb->prefix}bookings_staff st ON b.staff_id = st.id
			WHERE b.booking_date = %s
			AND b.deleted_at IS NULL
		";

		$params = array( $today );

		// Staff role: only see their own bookings.
		// Admin role: see all bookings.
		if ( 'staff' === $current_staff['role'] ) {
			$query   .= ' AND b.staff_id = %d';
			$params[] = $current_staff['id'];
		}

		// Order by start time.
		$query .= ' ORDER BY b.start_time ASC';

		// Execute query.
		$results = $wpdb->get_results( $wpdb->prepare( $query, $params ), ARRAY_A );

		if ( null === $results ) {
			return new WP_Error(
				'database_error',
				__( 'Failed to retrieve bookings.', 'bookit-booking-system' ),
				array( 'status' => 500 )
			);
		}

		// Format bookings for frontend.
		$bookings = array_map( array( $this, 'format_booking' ), $results );

		return rest_ensure_response(
			array(
				'success'  => true,
				'bookings' => $bookings,
				'date'     => $today,
				'count'    => count( $bookings ),
			)
		);
	}

	/**
	 * Get all bookings with filtering and pagination.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response|WP_Error
	 */
	public function get_all_bookings( $request ) {
		global $wpdb;

		$current_staff = Bookit_Auth::get_current_staff();
		if ( ! $current_staff ) {
			return new WP_Error(
				'unauthorized',
				__( 'Could not retrieve staff information.', 'bookit-booking-system' ),
				array( 'status' => 401 )
			);
		}

		// Get parameters.
		$page       = (int) $request->get_param( 'page' );
		$per_page   = (int) $request->get_param( 'per_page' );
		$date_from  = $request->get_param( 'date_from' );
		$date_to    = $request->get_param( 'date_to' );
		$staff_id   = $request->get_param( 'staff_id' );
		$service_id = $request->get_param( 'service_id' );
		$status     = $request->get_param( 'status' );
		$search     = $request->get_param( 'search' );
		$order_by   = $request->get_param( 'order_by' );
		$order      = strtoupper( $request->get_param( 'order' ) );

		// Build base query.
		// Performance audit: bookings list already resolves customer/service/staff fields via JOINs (no N+1 per-row lookups).
		$query = "
			SELECT
				b.id,
				b.customer_id,
				b.customer_package_id,
				b.booking_reference,
				b.booking_date,
				b.start_time,
				b.end_time,
				b.duration,
				b.status,
				b.total_price,
				b.deposit_paid,
				b.balance_due,
				b.full_amount_paid,
				b.payment_method,
				b.special_requests,
				b.staff_notes,
				b.created_at,
				c.first_name AS customer_first_name,
				c.last_name AS customer_last_name,
				c.email AS customer_email,
				c.phone AS customer_phone,
				s.name AS service_name,
				st.first_name AS staff_first_name,
				st.last_name AS staff_last_name,
				st.id AS staff_id
			FROM {$wpdb->prefix}bookings b
			LEFT JOIN {$wpdb->prefix}bookings_customers c ON b.customer_id = c.id
			INNER JOIN {$wpdb->prefix}bookings_services s ON b.service_id = s.id
			INNER JOIN {$wpdb->prefix}bookings_staff st ON b.staff_id = st.id
			WHERE b.deleted_at IS NULL
		";

		$params = array();

		// Role-based filtering (staff only see their bookings).
		if ( 'staff' === $current_staff['role'] ) {
			$query   .= ' AND b.staff_id = %d';
			$params[] = $current_staff['id'];
		}

		// Date range filter.
		if ( ! empty( $date_from ) ) {
			$query   .= ' AND b.booking_date >= %s';
			$params[] = $date_from;
		}
		if ( ! empty( $date_to ) ) {
			$query   .= ' AND b.booking_date <= %s';
			$params[] = $date_to;
		}

		// Staff filter (admin can filter by specific staff).
		if ( ! empty( $staff_id ) && 'admin' === $current_staff['role'] ) {
			$query   .= ' AND b.staff_id = %d';
			$params[] = (int) $staff_id;
		}

		// Service filter.
		if ( ! empty( $service_id ) ) {
			$query   .= ' AND b.service_id = %d';
			$params[] = (int) $service_id;
		}

		// Customer filter (admin only).
		if ( ! empty( $request->get_param( 'customer_id' ) ) && 'admin' === $current_staff['role'] ) {
			$query   .= ' AND b.customer_id = %d';
			$params[] = absint( $request->get_param( 'customer_id' ) );
		}

		// Status filter.
		if ( ! empty( $status ) ) {
			$query   .= ' AND b.status = %s';
			$params[] = $status;
		}

		// Search filter (customer name or email).
		if ( ! empty( $search ) ) {
			$search_param = '%' . $wpdb->esc_like( $search ) . '%';
			$query       .= " AND (
				c.first_name LIKE %s OR
				c.last_name LIKE %s OR
				c.email LIKE %s OR
				CONCAT(c.first_name, ' ', c.last_name) LIKE %s OR
				b.booking_reference LIKE %s
			)";
			$params[] = $search_param;
			$params[] = $search_param;
			$params[] = $search_param;
			$params[] = $search_param;
			$params[] = $search_param;
		}

		// Get total count before pagination.
		$count_query = "SELECT COUNT(*) FROM ({$query}) AS filtered_bookings";
		$total       = ! empty( $params )
			? $wpdb->get_var( $wpdb->prepare( $count_query, $params ) )
			: $wpdb->get_var( $count_query );

		// Add ordering.
		$valid_order_columns = array(
			'booking_date' => 'b.booking_date',
			'start_time'   => 'b.start_time',
			'status'       => 'b.status',
			'created_at'   => 'b.created_at',
		);

		$order_column = isset( $valid_order_columns[ $order_by ] )
			? $valid_order_columns[ $order_by ]
			: 'b.booking_date';

		$query .= " ORDER BY {$order_column} {$order}, b.start_time {$order}";

		// Add pagination.
		$offset   = ( $page - 1 ) * $per_page;
		$query   .= ' LIMIT %d OFFSET %d';
		$params[] = $per_page;
		$params[] = $offset;

		// Execute query.
		$results = ! empty( $params )
			? $wpdb->get_results( $wpdb->prepare( $query, $params ), ARRAY_A )
			: $wpdb->get_results( $query, ARRAY_A );

		if ( null === $results ) {
			return new WP_Error(
				'database_error',
				__( 'Failed to retrieve bookings.', 'bookit-booking-system' ),
				array( 'status' => 500 )
			);
		}

		// Format bookings for frontend.
		$bookings = array_map( array( $this, 'format_booking' ), $results );

		// Calculate pagination info.
		$total_pages = ceil( $total / $per_page );

		return rest_ensure_response(
			array(
				'success'    => true,
				'bookings'   => $bookings,
				'pagination' => array(
					'total'        => (int) $total,
					'per_page'     => $per_page,
					'current_page' => $page,
					'total_pages'  => (int) $total_pages,
					'has_next'     => $page < $total_pages,
					'has_prev'     => $page > 1,
				),
				'filters'    => array(
					'date_from'  => $date_from,
					'date_to'    => $date_to,
					'staff_id'   => $staff_id,
					'service_id' => $service_id,
					'status'     => $status,
					'search'     => $search,
				),
			)
		);
	}

	/**
	 * Get staff list with filters.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response
	 */
	public function get_staff_list( $request ) {
		global $wpdb;

		// Get query parameters.
		$search     = $request->get_param( 'search' );
		$role       = $request->get_param( 'role' );
		$status     = $request->get_param( 'status' );
		$service_id = $request->get_param( 'service_id' );

		// Build WHERE clauses.
		$where_clauses = array( 'st.deleted_at IS NULL' );
		$where_params  = array();

		// Search filter.
		if ( ! empty( $search ) ) {
			$where_clauses[] = '(st.first_name LIKE %s OR st.last_name LIKE %s OR st.email LIKE %s OR st.title LIKE %s)';
			$search_term     = '%' . $wpdb->esc_like( $search ) . '%';
			$where_params[]  = $search_term;
			$where_params[]  = $search_term;
			$where_params[]  = $search_term;
			$where_params[]  = $search_term;
		}

		// Role filter.
		if ( 'admin' === $role ) {
			$where_clauses[] = "st.role = 'admin'";
		} elseif ( 'staff' === $role ) {
			$where_clauses[] = "st.role = 'staff'";
		}

		// Status filter.
		if ( 'active' === $status ) {
			$where_clauses[] = 'st.is_active = 1';
		} elseif ( 'inactive' === $status ) {
			$where_clauses[] = 'st.is_active = 0';
		}

		// Service filter.
		if ( ! empty( $service_id ) ) {
			$where_clauses[] = 'EXISTS (
				SELECT 1 FROM ' . $wpdb->prefix . 'bookings_staff_services ss2
				WHERE ss2.staff_id = st.id
				AND ss2.service_id = %d
			)';
			$where_params[]  = (int) $service_id;
		}

		$where_sql = implode( ' AND ', $where_clauses );

		// Get staff with service count and working hours status.
		$query = "SELECT
					st.id,
					st.email,
					st.first_name,
					st.last_name,
					CONCAT(st.first_name, ' ', st.last_name) as full_name,
					st.phone,
					st.photo_url,
					st.bio,
					st.title,
					st.role,
					st.google_calendar_id,
					st.is_active,
					st.display_order,
					st.created_at,
					st.updated_at,
					COUNT(DISTINCT ss.service_id) as service_count,
					COUNT(DISTINCT wh.id) as working_hours_count,
					COUNT(DISTINCT CASE WHEN b.booking_date >= CURDATE() AND b.deleted_at IS NULL THEN b.id END) as future_bookings_count
				FROM {$wpdb->prefix}bookings_staff st
				LEFT JOIN {$wpdb->prefix}bookings_staff_services ss ON st.id = ss.staff_id
				LEFT JOIN {$wpdb->prefix}bookings_staff_working_hours wh ON st.id = wh.staff_id AND wh.is_working = 1
				LEFT JOIN {$wpdb->prefix}bookings b ON st.id = b.staff_id
				WHERE $where_sql
				GROUP BY st.id
				ORDER BY st.display_order ASC, st.first_name ASC, st.last_name ASC";

		if ( ! empty( $where_params ) ) {
			$query = $wpdb->prepare( $query, $where_params );
		}

		$staff_list = $wpdb->get_results( $query, ARRAY_A );

		// Process each staff member.
		foreach ( $staff_list as &$staff ) {
			$staff['id']                    = (int) $staff['id'];
			$staff['display_order']         = (int) $staff['display_order'];
			$staff['is_active']             = (bool) $staff['is_active'];
			$staff['service_count']         = (int) $staff['service_count'];
			$staff['working_hours_count']   = (int) $staff['working_hours_count'];
			$staff['future_bookings_count'] = (int) $staff['future_bookings_count'];
			$staff['has_working_hours']     = $staff['working_hours_count'] > 0;

			// Remove password hash from response.
			unset( $staff['password_hash'] );
		}

		return rest_ensure_response(
			array(
				'success' => true,
				'staff'   => $staff_list,
			)
		);
	}

	/**
	 * Get staff list for a specific service.
	 *
	 * Only returns staff who can provide the service (via staff_services junction table).
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response|WP_Error
	 */
	public function get_staff_by_service( $request ) {
		global $wpdb;

		$service_id = (int) $request->get_param( 'service_id' );

		$staff = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT DISTINCT
					s.id,
					CONCAT(s.first_name, ' ', s.last_name) AS name,
					s.first_name,
					s.last_name,
					ss.custom_price
				FROM {$wpdb->prefix}bookings_staff s
				INNER JOIN {$wpdb->prefix}bookings_staff_services ss ON s.id = ss.staff_id
				WHERE s.is_active = 1
				AND s.deleted_at IS NULL
				AND ss.service_id = %d
				ORDER BY s.first_name ASC",
				$service_id
			),
			ARRAY_A
		);

		return rest_ensure_response(
			array(
				'success' => true,
				'staff'   => $staff,
			)
		);
	}

	/**
	 * Get single staff details.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response|WP_Error
	 */
	public function get_staff_details( $request ) {
		global $wpdb;

		$staff_id = (int) $request->get_param( 'id' );

		// Get staff with counts.
		$staff = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT
					st.*,
					CONCAT(st.first_name, ' ', st.last_name) as full_name,
					COUNT(DISTINCT ss.service_id) as service_count,
					COUNT(DISTINCT wh.id) as working_hours_count,
					COUNT(DISTINCT CASE WHEN b.booking_date >= CURDATE() AND b.deleted_at IS NULL THEN b.id END) as future_bookings_count
				FROM {$wpdb->prefix}bookings_staff st
				LEFT JOIN {$wpdb->prefix}bookings_staff_services ss ON st.id = ss.staff_id
				LEFT JOIN {$wpdb->prefix}bookings_staff_working_hours wh ON st.id = wh.staff_id AND wh.is_working = 1
				LEFT JOIN {$wpdb->prefix}bookings b ON st.id = b.staff_id
				WHERE st.id = %d
				AND st.deleted_at IS NULL
				GROUP BY st.id",
				$staff_id
			),
			ARRAY_A
		);

		if ( ! $staff ) {
			return new WP_Error(
				'staff_not_found',
				'Staff member not found.',
				array( 'status' => 404 )
			);
		}

		// Never expose OAuth token material in API responses.
		unset( $staff['google_oauth_access_token'] );
		unset( $staff['google_oauth_refresh_token'] );
		unset( $staff['google_oauth_token_expiry'] );

		// Get service assignments with custom pricing.
		$service_assignments = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT
					ss.service_id,
					ss.custom_price,
					s.name as service_name,
					s.price as base_price
				FROM {$wpdb->prefix}bookings_staff_services ss
				INNER JOIN {$wpdb->prefix}bookings_services s ON ss.service_id = s.id
				WHERE ss.staff_id = %d
				AND s.deleted_at IS NULL
				ORDER BY s.name",
				$staff_id
			),
			ARRAY_A
		);

		// Process service assignments.
		foreach ( $service_assignments as &$assignment ) {
			$assignment['service_id']   = (int) $assignment['service_id'];
			$assignment['custom_price'] = $assignment['custom_price'] ? (float) $assignment['custom_price'] : null;
			$assignment['base_price']   = (float) $assignment['base_price'];
		}

		// Convert numeric fields.
		$staff['id']                    = (int) $staff['id'];
		$staff['display_order']         = (int) $staff['display_order'];
		$staff['is_active']             = (bool) $staff['is_active'];
		$staff['service_count']         = (int) $staff['service_count'];
		$staff['working_hours_count']   = (int) $staff['working_hours_count'];
		$staff['future_bookings_count'] = (int) $staff['future_bookings_count'];
		$staff['has_working_hours']     = $staff['working_hours_count'] > 0;
		$staff['service_assignments']   = $service_assignments;

		// Decode notification preferences with defaults.
		$pref_defaults = array(
			'new_booking'    => 'immediate',
			'reschedule'     => 'immediate',
			'cancellation'   => 'immediate',
			'daily_schedule' => false,
		);
		$raw_prefs = $staff['notification_preferences'] ?? null;
		$parsed    = ! empty( $raw_prefs ) ? json_decode( $raw_prefs, true ) : null;
		$staff['notification_preferences'] = is_array( $parsed )
			? array_merge( $pref_defaults, $parsed )
			: $pref_defaults;

		// Remove password hash.
		unset( $staff['password_hash'] );

		// Google Calendar — safe summary fields for admin staff GET (tokens never exposed).
		$staff['google_calendar_connected'] = (bool) (int) ( $staff['google_calendar_connected'] ?? 0 );
		$gcal_email                           = $staff['google_calendar_email'] ?? null;
		$staff['google_calendar_email']     = ( null !== $gcal_email && '' !== (string) $gcal_email )
			? (string) $gcal_email
			: null;

		return rest_ensure_response(
			array(
				'success' => true,
				'staff'   => $staff,
			)
		);
	}

	/**
	 * Create new staff member.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response|WP_Error
	 */
	public function create_staff( $request ) {
		global $wpdb;

		$email = $request->get_param( 'email' );

		// Check for duplicate email.
		$existing = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT id FROM {$wpdb->prefix}bookings_staff
				WHERE email = %s AND deleted_at IS NULL",
				$email
			)
		);

		if ( $existing ) {
			return new WP_Error(
				'duplicate_email',
				'A staff member with this email already exists.',
				array( 'status' => 409 )
			);
		}

		// Hash password.
		$password      = $request->get_param( 'password' );
		$password_hash = password_hash( $password, PASSWORD_DEFAULT );

		// Insert staff.
		$result = $wpdb->insert(
			$wpdb->prefix . 'bookings_staff',
			array(
				'email'              => $email,
				'password_hash'      => $password_hash,
				'first_name'         => $request->get_param( 'first_name' ),
				'last_name'          => $request->get_param( 'last_name' ),
				'phone'              => $request->get_param( 'phone' ),
				'photo_url'          => $request->get_param( 'photo_url' ),
				'bio'                => $request->get_param( 'bio' ),
				'title'              => $request->get_param( 'title' ),
				'role'               => $request->get_param( 'role' ),
				'google_calendar_id' => $request->get_param( 'google_calendar_id' ),
				'is_active'          => filter_var( $request->get_param( 'is_active' ), FILTER_VALIDATE_BOOLEAN ) ? 1 : 0,
				'display_order'      => (int) $request->get_param( 'display_order' ),
				'created_at'         => current_time( 'mysql' ),
				'updated_at'         => current_time( 'mysql' ),
			),
			array( '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%d', '%d', '%s', '%s' )
		);

		if ( false === $result ) {
			return new WP_Error(
				'creation_failed',
				'Failed to create staff member.',
				array( 'status' => 500 )
			);
		}

		$staff_id = $wpdb->insert_id;

		// Insert service assignments.
		$service_assignments = $request->get_param( 'service_assignments' );
		if ( ! empty( $service_assignments ) ) {
			foreach ( $service_assignments as $assignment ) {
				$wpdb->insert(
					$wpdb->prefix . 'bookings_staff_services',
					array(
						'staff_id'     => $staff_id,
						'service_id'   => (int) $assignment['service_id'],
						'custom_price' => isset( $assignment['custom_price'] ) ? (float) $assignment['custom_price'] : null,
						'created_at'   => current_time( 'mysql' ),
					),
					array( '%d', '%d', '%f', '%s' )
				);
			}
		}

		$staff_data = array(
			'email'               => $email,
			'first_name'          => $request->get_param( 'first_name' ),
			'last_name'           => $request->get_param( 'last_name' ),
			'phone'               => $request->get_param( 'phone' ),
			'photo_url'           => $request->get_param( 'photo_url' ),
			'bio'                 => $request->get_param( 'bio' ),
			'title'               => $request->get_param( 'title' ),
			'role'                => $request->get_param( 'role' ),
			'google_calendar_id'  => $request->get_param( 'google_calendar_id' ),
			'is_active'           => filter_var( $request->get_param( 'is_active' ), FILTER_VALIDATE_BOOLEAN ) ? 1 : 0,
			'display_order'       => (int) $request->get_param( 'display_order' ),
			'service_assignments' => $service_assignments,
		);

		Bookit_Audit_Logger::log(
			'staff.created',
			'staff',
			$staff_id,
			array(
				'new_value' => $staff_data,
			)
		);

		// Get created staff.
		$get_request = new WP_REST_Request( 'GET', self::NAMESPACE . "/dashboard/staff/{$staff_id}" );
		$get_request->set_url_params( array( 'id' => $staff_id ) );
		$staff_response = $this->get_staff_details( $get_request );

		return rest_ensure_response(
			array(
				'success' => true,
				'message' => 'Staff member created successfully.',
				'staff'   => $staff_response->data['staff'],
			)
		);
	}

	/**
	 * Update existing staff member.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response|WP_Error
	 */
	public function update_staff( $request ) {
		global $wpdb;

		$staff_id = (int) $request->get_param( 'id' );

		// Check if staff exists.
		$existing = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$wpdb->prefix}bookings_staff
				WHERE id = %d AND deleted_at IS NULL",
				$staff_id
			),
			ARRAY_A
		);

		if ( ! $existing ) {
			return new WP_Error(
				'staff_not_found',
				'Staff member not found.',
				array( 'status' => 404 )
			);
		}

		$email    = $request->get_param( 'email' );
		$old_data = $existing;
		$new_data = array(
			'email'              => $email,
			'first_name'         => $request->get_param( 'first_name' ),
			'last_name'          => $request->get_param( 'last_name' ),
			'phone'              => $request->get_param( 'phone' ),
			'photo_url'          => $request->get_param( 'photo_url' ),
			'bio'                => $request->get_param( 'bio' ),
			'title'              => $request->get_param( 'title' ),
			'role'               => $request->get_param( 'role' ),
			'google_calendar_id' => $request->get_param( 'google_calendar_id' ),
			'is_active'          => filter_var( $request->get_param( 'is_active' ), FILTER_VALIDATE_BOOLEAN ) ? 1 : 0,
			'display_order'      => (int) $request->get_param( 'display_order' ),
		);
		$formats = array( '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%d', '%d' );

		// Check for duplicate email (excluding current staff).
		$duplicate = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT id FROM {$wpdb->prefix}bookings_staff
				WHERE email = %s AND id != %d AND deleted_at IS NULL",
				$email,
				$staff_id
			)
		);

		if ( $duplicate ) {
			return new WP_Error(
				'duplicate_email',
				'A staff member with this email already exists.',
				array( 'status' => 409 )
			);
		}

		// Handle notification_preferences (admin only — staff role blocked by check_admin_permission).
		$raw_notification_prefs = $request->get_param( 'notification_preferences' );
		if ( null !== $raw_notification_prefs && is_array( $raw_notification_prefs ) ) {
			$valid_frequencies = array( 'immediate', 'daily', 'weekly' );
			$pref_defaults     = array(
				'new_booking'    => 'immediate',
				'reschedule'     => 'immediate',
				'cancellation'   => 'immediate',
				'daily_schedule' => false,
			);
			$sanitized_prefs = array(
				'new_booking'    => in_array( $raw_notification_prefs['new_booking'] ?? '', $valid_frequencies, true )
									? $raw_notification_prefs['new_booking']
									: $pref_defaults['new_booking'],
				'reschedule'     => in_array( $raw_notification_prefs['reschedule'] ?? '', $valid_frequencies, true )
									? $raw_notification_prefs['reschedule']
									: $pref_defaults['reschedule'],
				'cancellation'   => in_array( $raw_notification_prefs['cancellation'] ?? '', $valid_frequencies, true )
									? $raw_notification_prefs['cancellation']
									: $pref_defaults['cancellation'],
				'daily_schedule' => isset( $raw_notification_prefs['daily_schedule'] )
									? (bool) $raw_notification_prefs['daily_schedule']
									: $pref_defaults['daily_schedule'],
			);
			$new_data['notification_preferences'] = wp_json_encode( $sanitized_prefs );
			$formats[]                            = '%s';
		}

		$new_data['updated_at'] = current_time( 'mysql' );
		$formats[]              = '%s';

		// Update staff.
		$result = $wpdb->update(
			$wpdb->prefix . 'bookings_staff',
			$new_data,
			array( 'id' => $staff_id ),
			$formats,
			array( '%d' )
		);

		if ( false === $result ) {
			return new WP_Error(
				'update_failed',
				'Failed to update staff member.',
				array( 'status' => 500 )
			);
		}

		// Delete existing service assignments.
		$wpdb->delete(
			$wpdb->prefix . 'bookings_staff_services',
			array( 'staff_id' => $staff_id ),
			array( '%d' )
		);

		// Insert new service assignments.
		$service_assignments = $request->get_param( 'service_assignments' );
		if ( ! empty( $service_assignments ) ) {
			foreach ( $service_assignments as $assignment ) {
				$wpdb->insert(
					$wpdb->prefix . 'bookings_staff_services',
					array(
						'staff_id'     => $staff_id,
						'service_id'   => (int) $assignment['service_id'],
						'custom_price' => isset( $assignment['custom_price'] ) ? (float) $assignment['custom_price'] : null,
						'created_at'   => current_time( 'mysql' ),
					),
					array( '%d', '%d', '%f', '%s' )
				);
			}
		}

		Bookit_Audit_Logger::log(
			'staff.updated',
			'staff',
			$staff_id,
			array(
				'old_value' => $old_data,
				'new_value' => $new_data,
			)
		);

		// Get updated staff.
		$staff_response = $this->get_staff_details( $request );

		return rest_ensure_response(
			array(
				'success' => true,
				'message' => 'Staff member updated successfully.',
				'staff'   => $staff_response->data['staff'],
			)
		);
	}

	/**
	 * Upload a staff member's profile photo.
	 *
	 * Accepts multipart/form-data with a file field named 'photo'.
	 * Validates type (image only) and size (5MB max).
	 * Inserts into WordPress media library and updates photo_url.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response|WP_Error
	 */
	public function upload_staff_photo( WP_REST_Request $request ) {
		global $wpdb;

		$staff_id      = (int) $request->get_param( 'id' );
		$current_staff = Bookit_Auth::get_current_staff();

		if ( ! $current_staff ) {
			return new WP_Error(
				'unauthorized',
				'Could not retrieve staff information.',
				array( 'status' => 401 )
			);
		}

		// Staff can only upload their own photo; admin can upload for anyone.
		if ( 'staff' === $current_staff['role'] && (int) $current_staff['id'] !== $staff_id ) {
			return new WP_Error(
				'forbidden',
				'You can only upload a photo for your own account.',
				array( 'status' => 403 )
			);
		}

		// Verify the target staff member exists and is not deleted.
		$target = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT id FROM {$wpdb->prefix}bookings_staff
				 WHERE id = %d AND deleted_at IS NULL",
				$staff_id
			),
			ARRAY_A
		);

		if ( ! $target ) {
			return new WP_Error(
				'staff_not_found',
				'Staff member not found.',
				array( 'status' => 404 )
			);
		}

		// Get uploaded file from request.
		$files = $request->get_file_params();
		$file  = $files['photo'] ?? null;

		if ( ! $file || empty( $file['tmp_name'] ) ) {
			return new WP_Error(
				'no_file',
				'No file uploaded. Send an image in the "photo" field.',
				array( 'status' => 400 )
			);
		}

		// Validate mime type using finfo (server-side, not client-reported).
		$allowed_types = array( 'image/jpeg', 'image/png', 'image/gif', 'image/webp' );
		$finfo         = new finfo( FILEINFO_MIME_TYPE );
		$mime          = $finfo->file( $file['tmp_name'] );

		if ( ! in_array( $mime, $allowed_types, true ) ) {
			return new WP_Error(
				'invalid_type',
				'File must be an image (JPG, PNG, GIF, or WebP).',
				array( 'status' => 400 )
			);
		}

		// Validate file size (5MB max).
		if ( $file['size'] > 5 * 1024 * 1024 ) {
			return new WP_Error(
				'file_too_large',
				'File must be 5MB or less.',
				array( 'status' => 400 )
			);
		}

		// Load WordPress upload/media helpers.
		require_once ABSPATH . 'wp-admin/includes/file.php';
		require_once ABSPATH . 'wp-admin/includes/image.php';
		require_once ABSPATH . 'wp-admin/includes/media.php';

		// Move file to uploads directory.
		$overrides = array( 'test_form' => false );
		$upload    = wp_handle_upload( $file, $overrides );

		if ( isset( $upload['error'] ) ) {
			return new WP_Error(
				'upload_failed',
				$upload['error'],
				array( 'status' => 500 )
			);
		}

		// Register in WordPress media library.
		$attachment_id = wp_insert_attachment(
			array(
				'post_mime_type' => $upload['type'],
				'post_title'     => sanitize_file_name( $file['name'] ),
				'post_content'   => '',
				'post_status'    => 'inherit',
			),
			$upload['file']
		);

		if ( is_wp_error( $attachment_id ) ) {
			return new WP_Error(
				'attachment_failed',
				'Could not register file in media library.',
				array( 'status' => 500 )
			);
		}

		wp_update_attachment_metadata(
			$attachment_id,
			wp_generate_attachment_metadata( $attachment_id, $upload['file'] )
		);

		$url = wp_get_attachment_url( $attachment_id );

		// Update staff photo_url.
		$result = $wpdb->update(
			$wpdb->prefix . 'bookings_staff',
			array(
				'photo_url'  => $url,
				'updated_at' => current_time( 'mysql' ),
			),
			array( 'id' => $staff_id ),
			array( '%s', '%s' ),
			array( '%d' )
		);

		if ( false === $result ) {
			return new WP_Error(
				'db_update_failed',
				'File uploaded but failed to update staff record.',
				array( 'status' => 500 )
			);
		}

		Bookit_Audit_Logger::log(
			'staff.photo_uploaded',
			'staff',
			$staff_id,
			array(
				'notes' => 'Photo uploaded via dashboard',
			)
		);

		return rest_ensure_response(
			array(
				'success' => true,
				'url'     => $url,
			)
		);
	}

	/**
	 * Delete staff member (soft delete).
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response|WP_Error
	 */
	public function delete_staff( $request ) {
		global $wpdb;

		$staff_id = (int) $request->get_param( 'id' );

		// Check if staff exists.
		$existing = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT id, first_name, last_name FROM {$wpdb->prefix}bookings_staff
				WHERE id = %d AND deleted_at IS NULL",
				$staff_id
			),
			ARRAY_A
		);

		if ( ! $existing ) {
			return new WP_Error(
				'staff_not_found',
				'Staff member not found.',
				array( 'status' => 404 )
			);
		}

		// Check for future bookings.
		$future_bookings = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$wpdb->prefix}bookings
				WHERE staff_id = %d
				AND booking_date >= CURDATE()
				AND deleted_at IS NULL
				AND status NOT IN ('cancelled', 'no_show')",
				$staff_id
			)
		);

		if ( $future_bookings > 0 ) {
			return new WP_Error(
				'staff_has_bookings',
				sprintf(
					'Cannot delete %s %s because they have %d future booking(s). Please reassign or cancel these bookings first, or deactivate the staff member instead.',
					$existing['first_name'],
					$existing['last_name'],
					$future_bookings
				),
				array( 'status' => 409 )
			);
		}

		// Soft delete the staff member.
		$result = $wpdb->update(
			$wpdb->prefix . 'bookings_staff',
			array(
				'deleted_at' => current_time( 'mysql' ),
				'updated_at' => current_time( 'mysql' ),
			),
			array( 'id' => $staff_id ),
			array( '%s', '%s' ),
			array( '%d' )
		);

		if ( false === $result ) {
			return new WP_Error(
				'deletion_failed',
				'Failed to delete staff member.',
				array( 'status' => 500 )
			);
		}

		Bookit_Audit_Logger::log(
			'staff.deleted',
			'staff',
			$staff_id,
			array(
				'old_value' => $existing,
			)
		);

		return rest_ensure_response(
			array(
				'success' => true,
				'message' => 'Staff member deleted successfully.',
			)
		);
	}

	/**
	 * Reorder staff members.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response|WP_Error
	 */
	public function reorder_staff( $request ) {
		global $wpdb;

		$staff = $request->get_param( 'staff' );

		if ( empty( $staff ) ) {
			return new WP_Error(
				'invalid_data',
				'Staff array is required.',
				array( 'status' => 400 )
			);
		}

		// Update display order for each staff member.
		foreach ( $staff as $staff_data ) {
			if ( ! isset( $staff_data['id'] ) || ! isset( $staff_data['display_order'] ) ) {
				continue;
			}

			$wpdb->update(
				$wpdb->prefix . 'bookings_staff',
				array(
					'display_order' => (int) $staff_data['display_order'],
					'updated_at'    => current_time( 'mysql' ),
				),
				array( 'id' => (int) $staff_data['id'] ),
				array( '%d', '%s' ),
				array( '%d' )
			);
		}

		return rest_ensure_response(
			array(
				'success' => true,
				'message' => 'Staff reordered successfully.',
			)
		);
	}

	/**
	 * Reset staff member password.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response|WP_Error
	 */
	public function reset_staff_password( $request ) {
		global $wpdb;

		$staff_id     = (int) $request->get_param( 'id' );
		$new_password = $request->get_param( 'new_password' );
		$send_email   = filter_var( $request->get_param( 'send_email' ), FILTER_VALIDATE_BOOLEAN );

		// Check if staff exists.
		$staff = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT id, email, first_name, last_name FROM {$wpdb->prefix}bookings_staff
				WHERE id = %d AND deleted_at IS NULL",
				$staff_id
			),
			ARRAY_A
		);

		if ( ! $staff ) {
			return new WP_Error(
				'staff_not_found',
				'Staff member not found.',
				array( 'status' => 404 )
			);
		}

		// Hash new password.
		$password_hash = password_hash( $new_password, PASSWORD_DEFAULT );

		// Update password.
		$result = $wpdb->update(
			$wpdb->prefix . 'bookings_staff',
			array(
				'password_hash' => $password_hash,
				'updated_at'    => current_time( 'mysql' ),
			),
			array( 'id' => $staff_id ),
			array( '%s', '%s' ),
			array( '%d' )
		);

		if ( false === $result ) {
			return new WP_Error(
				'reset_failed',
				'Failed to reset password.',
				array( 'status' => 500 )
			);
		}

		// Send email if requested.
		if ( $send_email ) {
			$to      = $staff['email'];
			$subject = 'Your password has been reset';
			$message = sprintf(
				"Hello %s,\n\nYour password has been reset by an administrator.\n\nNew password: %s\n\nPlease log in and change your password.\n\nBooking System",
				$staff['first_name'],
				$new_password
			);

			wp_mail( $to, $subject, $message );
		}

		return rest_ensure_response(
			array(
				'success' => true,
				'message' => 'Password reset successfully.',
			)
		);
	}

	/**
	 * Get services list with filters and pagination.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response|WP_Error
	 */
	public function get_services_list( $request ) {
		global $wpdb;

		// Get query parameters.
		$search      = $request->get_param( 'search' );
		$category_id = $request->get_param( 'category_id' );
		$status      = $request->get_param( 'status' ); // 'active', 'inactive', 'all'.
		$page        = max( 1, (int) $request->get_param( 'page' ) );
		$per_page    = min( 100, max( 1, (int) $request->get_param( 'per_page' ) ) ); // Default 50, max 100.

		if ( ! $per_page ) {
			$per_page = 50;
		}

		$offset = ( $page - 1 ) * $per_page;

		// Build WHERE clauses.
		$where_clauses = array( 's.deleted_at IS NULL' );
		$where_params  = array();

		// Search filter.
		if ( ! empty( $search ) ) {
			$where_clauses[] = '(s.name LIKE %s OR s.description LIKE %s)';
			$search_term     = '%' . $wpdb->esc_like( $search ) . '%';
			$where_params[]  = $search_term;
			$where_params[]  = $search_term;
		}

		// Status filter.
		if ( 'active' === $status ) {
			$where_clauses[] = 's.is_active = 1';
		} elseif ( 'inactive' === $status ) {
			$where_clauses[] = 's.is_active = 0';
		}
		// 'all' or null = no status filter.

		// Category filter.
		if ( ! empty( $category_id ) ) {
			$where_clauses[] = 'EXISTS (
				SELECT 1 FROM ' . $wpdb->prefix . 'bookings_service_categories sc2
				WHERE sc2.service_id = s.id
				AND sc2.category_id = %d
			)';
			$where_params[]  = (int) $category_id;
		}

		$where_sql = implode( ' AND ', $where_clauses );

		// Get total count.
		$count_query = "SELECT COUNT(DISTINCT s.id)
						FROM {$wpdb->prefix}bookings_services s
						WHERE $where_sql";

		if ( ! empty( $where_params ) ) {
			$count_query = $wpdb->prepare( $count_query, $where_params );
		}

		$total = (int) $wpdb->get_var( $count_query );

		// Get services with category information.
		$query = "SELECT
					s.id,
					s.name,
					s.description,
					s.duration,
					s.price,
					s.deposit_amount,
					s.deposit_type,
					s.buffer_before,
					s.buffer_after,
					s.is_active,
					s.display_order,
					s.created_at,
					s.updated_at,
					GROUP_CONCAT(
						DISTINCT CONCAT(c.id, ':', c.name)
						ORDER BY c.name
						SEPARATOR '||'
					) as categories_data
				FROM {$wpdb->prefix}bookings_services s
				LEFT JOIN {$wpdb->prefix}bookings_service_categories sc ON s.id = sc.service_id
				LEFT JOIN {$wpdb->prefix}bookings_categories c ON sc.category_id = c.id AND c.deleted_at IS NULL
				WHERE $where_sql
				GROUP BY s.id
				ORDER BY s.display_order ASC, s.name ASC
				LIMIT %d OFFSET %d";

		$query_params = array_merge( $where_params, array( $per_page, $offset ) );
		$query        = $wpdb->prepare( $query, $query_params );

		$services = $wpdb->get_results( $query, ARRAY_A );

		// Process categories data for each service.
		foreach ( $services as &$service ) {
			$categories = array();

			if ( ! empty( $service['categories_data'] ) ) {
				$categories_raw = explode( '||', $service['categories_data'] );
				foreach ( $categories_raw as $cat_data ) {
					if ( ! empty( $cat_data ) ) {
						list( $cat_id, $cat_name ) = explode( ':', $cat_data, 2 );
						$categories[] = array(
							'id'   => (int) $cat_id,
							'name' => $cat_name,
						);
					}
				}
			}

			$service['categories'] = $categories;
			unset( $service['categories_data'] );

			// Convert numeric fields to proper types.
			$service['id']             = (int) $service['id'];
			$service['duration']       = (int) $service['duration'];
			$service['price']          = (float) $service['price'];
			$service['deposit_amount'] = $service['deposit_amount'] ? (float) $service['deposit_amount'] : null;
			$service['buffer_before']  = (int) $service['buffer_before'];
			$service['buffer_after']   = (int) $service['buffer_after'];
			$service['is_active']      = (bool) $service['is_active'];
			$service['display_order']  = (int) $service['display_order'];
		}

		// Calculate pagination.
		$total_pages = ceil( $total / $per_page );

		return rest_ensure_response(
			array(
				'success'    => true,
				'services'   => $services,
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
	 * Get single service details.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response|WP_Error
	 */
	public function get_service_details( $request ) {
		global $wpdb;

		$service_id = (int) $request->get_param( 'id' );

		// Get service with categories.
		$service = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT
					s.*,
					GROUP_CONCAT(
						DISTINCT CONCAT(c.id, ':', c.name)
						ORDER BY c.name
						SEPARATOR '||'
					) as categories_data
				FROM {$wpdb->prefix}bookings_services s
				LEFT JOIN {$wpdb->prefix}bookings_service_categories sc ON s.id = sc.service_id
				LEFT JOIN {$wpdb->prefix}bookings_categories c ON sc.category_id = c.id AND c.deleted_at IS NULL
				WHERE s.id = %d
				AND s.deleted_at IS NULL
				GROUP BY s.id",
				$service_id
			),
			ARRAY_A
		);

		if ( ! $service ) {
			return new WP_Error(
				'service_not_found',
				'Service not found.',
				array( 'status' => 404 )
			);
		}

		// Process categories.
		$categories = array();

		if ( ! empty( $service['categories_data'] ) ) {
			$categories_raw = explode( '||', $service['categories_data'] );
			foreach ( $categories_raw as $cat_data ) {
				if ( ! empty( $cat_data ) ) {
					list( $cat_id, $cat_name ) = explode( ':', $cat_data, 2 );
					$categories[] = array(
						'id'   => (int) $cat_id,
						'name' => $cat_name,
					);
				}
			}
		}

		$service['categories']   = $categories;
		$service['category_ids'] = array_column( $categories, 'id' );
		unset( $service['categories_data'] );

		// Convert numeric fields.
		$service['id']             = (int) $service['id'];
		$service['duration']       = (int) $service['duration'];
		$service['price']          = (float) $service['price'];
		$service['deposit_amount'] = $service['deposit_amount'] ? (float) $service['deposit_amount'] : null;
		$service['buffer_before']  = (int) $service['buffer_before'];
		$service['buffer_after']   = (int) $service['buffer_after'];
		$service['is_active']      = (bool) $service['is_active'];
		$service['display_order']  = (int) $service['display_order'];

		return rest_ensure_response(
			array(
				'success' => true,
				'service' => $service,
			)
		);
	}

	/**
	 * Format booking data for API response.
	 *
	 * @param array $booking Raw booking from database.
	 * @return array Formatted booking.
	 */
	private function format_booking( $booking ) {
		// Calculate if booking is starting soon (within 15 minutes).
		$current_time = current_time( 'H:i:s' );
		$start_time   = $booking['start_time'];
		$customer_first = $booking['customer_first_name'] ?? '';
		$customer_last  = $booking['customer_last_name'] ?? '';
		$customer_name  = trim( $customer_first . ' ' . $customer_last );

		if ( empty( $customer_name ) ) {
			$customer_name = __( 'Deleted Customer', 'bookit-booking-system' );
		}

		$current_timestamp = strtotime( current_time( 'Y-m-d' ) . ' ' . $current_time );

		$is_starting_soon = false;
		$has_passed       = false;

		if ( ! empty( $start_time ) ) {
			$start_timestamp = strtotime( current_time( 'Y-m-d' ) . ' ' . $start_time );
			$time_until_start = ( $start_timestamp - $current_timestamp ) / 60; // minutes.
			$is_starting_soon = $time_until_start > 0 && $time_until_start <= 15;
		}

		if ( ! empty( $booking['end_time'] ) ) {
			$has_passed = $current_timestamp > strtotime( current_time( 'Y-m-d' ) . ' ' . $booking['end_time'] );
		}

		$response = array(
			'id'               => (int) $booking['id'],
			'booking_reference' => $booking['booking_reference'] ?? '',
			'lock_version'     => $booking['lock_version'] ?? '',
			'service_id'       => isset( $booking['service_id'] ) ? (int) $booking['service_id'] : null,
			'staff_id'         => isset( $booking['staff_id'] ) ? (int) $booking['staff_id'] : null,
			'booking_date'     => $booking['booking_date'],
			'start_time'       => $booking['start_time'] ? substr( $booking['start_time'], 0, 5 ) : null, // HH:MM format.
			'end_time'         => $booking['end_time'] ? substr( $booking['end_time'], 0, 5 ) : null,
			'duration'         => (int) $booking['duration'],
			'status'           => $booking['status'],
			'total_price'      => (float) $booking['total_price'],
			'deposit_paid'     => (float) $booking['deposit_paid'],
			'balance_due'      => (float) $booking['balance_due'],
			'full_amount_paid' => (bool) $booking['full_amount_paid'],
			'payment_method'   => $booking['payment_method'],
			'special_requests' => $booking['special_requests'],
			'staff_notes'      => $booking['staff_notes'],
			'customer_name'    => $customer_name,
			'customer_id'         => (int) ( $booking['customer_id'] ?? 0 ),
			'customer_email'   => $booking['customer_email'] ?? '',
			'customer_phone'   => $booking['customer_phone'] ?? '',
			'customer_package_id' => ! empty( $booking['customer_package_id'] ) ? (int) $booking['customer_package_id'] : null,
			'service_name'     => $booking['service_name'],
			'staff_name'       => $booking['staff_first_name'] . ' ' . $booking['staff_last_name'],
			'is_starting_soon' => $is_starting_soon,
			'has_passed'       => $has_passed,
			'created_at'       => $booking['created_at'] ?? null,
			'updated_at'       => $booking['updated_at'] ?? null,
		);

		// Allow extensions to customize booking payloads returned by the dashboard API.
		$response = apply_filters( 'bookit_booking_response', $response, (int) $booking['id'] );

		return $response;
	}

	/**
	 * Mark booking as complete.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response|WP_Error
	 */
	public function mark_booking_complete( $request ) {
		global $wpdb;

		$booking_id    = (int) $request['id'];
		$current_staff = Bookit_Auth::get_current_staff();

		// Get booking to verify ownership (staff can only complete their own bookings).
		$booking = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT id, staff_id, status FROM {$wpdb->prefix}bookings WHERE id = %d AND deleted_at IS NULL",
				$booking_id
			),
			ARRAY_A
		);

		if ( ! $booking ) {
			return Bookit_Error_Registry::to_wp_error(
				'E2002',
				array( 'booking_id' => $booking_id )
			);
		}

		// Check permission: staff can only complete their own bookings.
		if ( 'staff' === $current_staff['role'] && (int) $booking['staff_id'] !== (int) $current_staff['id'] ) {
			return new WP_Error(
				'forbidden',
				__( 'You do not have permission to complete this booking.', 'bookit-booking-system' ),
				array( 'status' => 403 )
			);
		}

		// Check if already completed.
		if ( 'completed' === $booking['status'] ) {
			return new WP_Error(
				'already_completed',
				__( 'This booking is already marked as complete.', 'bookit-booking-system' ),
				array( 'status' => 400 )
			);
		}

		// Update status to completed.
		$result = $wpdb->update(
			$wpdb->prefix . 'bookings',
			array(
				'status'     => 'completed',
				'updated_at' => current_time( 'mysql' ),
			),
			array( 'id' => $booking_id ),
			array( '%s', '%s' ),
			array( '%d' )
		);

		if ( false === $result ) {
			return new WP_Error(
				'database_error',
				__( 'Failed to update booking status.', 'bookit-booking-system' ),
				array( 'status' => 500 )
			);
		}

		// Log status change.
		$wpdb->insert(
			$wpdb->prefix . 'bookings_status_log',
			array(
				'booking_id'          => $booking_id,
				'old_status'          => $booking['status'],
				'new_status'          => 'completed',
				'changed_by_staff_id' => $current_staff['id'],
				'changed_at'          => current_time( 'mysql' ),
				'notes'               => null,
			),
			array( '%d', '%s', '%s', '%d', '%s', '%s' )
		);

		Bookit_Audit_Logger::log(
			'booking.completed',
			'booking',
			$booking_id,
			array(
				'old_value' => array( 'status' => $booking['status'] ),
				'new_value' => array( 'status' => 'completed' ),
			)
		);

		return rest_ensure_response(
			array(
				'success'    => true,
				'message'    => __( 'Booking marked as complete.', 'bookit-booking-system' ),
				'booking_id' => $booking_id,
			)
		);
	}

	/**
	 * Mark booking as no-show.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response|WP_Error
	 */
	public function mark_booking_no_show( $request ) {
		global $wpdb;

		$booking_id    = (int) $request['id'];
		$current_staff = Bookit_Auth::get_current_staff();

		$booking = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT id, staff_id, status FROM {$wpdb->prefix}bookings WHERE id = %d AND deleted_at IS NULL",
				$booking_id
			),
			ARRAY_A
		);

		if ( ! $booking ) {
			return Bookit_Error_Registry::to_wp_error(
				'E2002',
				array( 'booking_id' => $booking_id )
			);
		}

		if ( 'staff' === $current_staff['role'] && (int) $booking['staff_id'] !== (int) $current_staff['id'] ) {
			return new WP_Error(
				'forbidden',
				__( 'You do not have permission to update this booking.', 'bookit-booking-system' ),
				array( 'status' => 403 )
			);
		}

		if ( 'no_show' === $booking['status'] ) {
			return new WP_Error(
				'already_no_show',
				__( 'This booking is already marked as no-show.', 'bookit-booking-system' ),
				array( 'status' => 400 )
			);
		}

		$result = $wpdb->update(
			$wpdb->prefix . 'bookings',
			array(
				'status'     => 'no_show',
				'updated_at' => current_time( 'mysql' ),
			),
			array( 'id' => $booking_id ),
			array( '%s', '%s' ),
			array( '%d' )
		);

		if ( false === $result ) {
			return new WP_Error(
				'database_error',
				__( 'Failed to update booking status.', 'bookit-booking-system' ),
				array( 'status' => 500 )
			);
		}

		// Log status change.
		$wpdb->insert(
			$wpdb->prefix . 'bookings_status_log',
			array(
				'booking_id'          => $booking_id,
				'old_status'          => $booking['status'],
				'new_status'          => 'no_show',
				'changed_by_staff_id' => $current_staff['id'],
				'changed_at'          => current_time( 'mysql' ),
				'notes'               => null,
			),
			array( '%d', '%s', '%s', '%d', '%s', '%s' )
		);

		Bookit_Audit_Logger::log(
			'booking.no_show',
			'booking',
			$booking_id,
			array(
				'old_value' => array( 'status' => $booking['status'] ),
				'new_value' => array( 'status' => 'no_show' ),
			)
		);

		return rest_ensure_response(
			array(
				'success'    => true,
				'message'    => __( 'Booking marked as no-show.', 'bookit-booking-system' ),
				'booking_id' => $booking_id,
			)
		);
	}

	/**
	 * Apply a bulk status transition to multiple bookings.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response|WP_Error
	 */
	public function bulk_action( $request ) {
		global $wpdb;
		// Security audit note: dynamic SQL in this method uses prepared statements,
		// while writes rely on $wpdb->update()/insert() format arrays.

		$current_staff = Bookit_Auth::get_current_staff();
		if ( ! $current_staff ) {
			return new WP_Error(
				'unauthorized',
				__( 'Could not retrieve staff information.', 'bookit-booking-system' ),
				array( 'status' => 401 )
			);
		}

		$action        = sanitize_key( (string) $request->get_param( 'action' ) );
		$valid_actions = array( 'cancel', 'complete', 'no_show' );
		if ( ! in_array( $action, $valid_actions, true ) ) {
			return Bookit_Error_Registry::to_wp_error(
				'BULK_INVALID_ACTION',
				array( 'action' => $action )
			);
		}

		$booking_ids = $request->get_param( 'booking_ids' );
		if ( ! is_array( $booking_ids ) || empty( $booking_ids ) ) {
			return Bookit_Error_Registry::to_wp_error( 'BULK_EMPTY_IDS' );
		}

		$booking_ids = array_values(
			array_unique(
				array_filter(
					array_map( 'absint', $booking_ids ),
					function ( $id ) {
						return $id > 0;
					}
				)
			)
		);

		if ( empty( $booking_ids ) ) {
			return Bookit_Error_Registry::to_wp_error( 'BULK_EMPTY_IDS' );
		}

		if ( count( $booking_ids ) > 100 ) {
			return Bookit_Error_Registry::to_wp_error(
				'BULK_TOO_MANY_IDS',
				array( 'count' => count( $booking_ids ) )
			);
		}

		$target_status   = '';
		$audit_action    = '';
		$allowed_statuses = array();

		if ( 'cancel' === $action ) {
			$target_status    = 'cancelled';
			$audit_action     = 'booking_bulk_cancelled';
			$allowed_statuses = array( 'pending', 'confirmed' );
		} elseif ( 'complete' === $action ) {
			$target_status    = 'completed';
			$audit_action     = 'booking_bulk_completed';
			$allowed_statuses = array( 'confirmed' );
		} else {
			$target_status    = 'no_show';
			$audit_action     = 'booking_bulk_no_show';
			$allowed_statuses = array( 'confirmed' );
		}

		$succeeded = array();
		$failed    = array();

		foreach ( $booking_ids as $booking_id ) {
			$booking = $wpdb->get_row(
				$wpdb->prepare(
					"SELECT id, staff_id, status, deleted_at, start_time, end_time FROM {$wpdb->prefix}bookings WHERE id = %d",
					$booking_id
				),
				ARRAY_A
			);

			if ( ! $booking || ! empty( $booking['deleted_at'] ) ) {
				$failed[] = array(
					'id'     => $booking_id,
					'reason' => __( 'Booking not found.', 'bookit-booking-system' ),
				);
				continue;
			}

			$current_status = (string) $booking['status'];
			if ( ! in_array( $current_status, $allowed_statuses, true ) ) {
				$reason = __( 'Booking cannot be transitioned to the requested status.', 'bookit-booking-system' );
				if ( 'cancel' === $action ) {
					if ( 'cancelled' === $current_status ) {
						$reason = __( 'Booking is already cancelled.', 'bookit-booking-system' );
					} elseif ( 'completed' === $current_status ) {
						$reason = __( 'Completed bookings cannot be cancelled.', 'bookit-booking-system' );
					} elseif ( 'no_show' === $current_status ) {
						$reason = __( 'No-show bookings cannot be cancelled.', 'bookit-booking-system' );
					}
				} elseif ( 'complete' === $action ) {
					if ( 'completed' === $current_status ) {
						$reason = __( 'Booking is already completed.', 'bookit-booking-system' );
					} elseif ( 'cancelled' === $current_status ) {
						$reason = __( 'Cancelled bookings cannot be completed.', 'bookit-booking-system' );
					} elseif ( 'no_show' === $current_status ) {
						$reason = __( 'No-show bookings cannot be completed.', 'bookit-booking-system' );
					} elseif ( 'pending' === $current_status || 'pending_payment' === $current_status ) {
						$reason = __( 'Only confirmed bookings can be marked as complete.', 'bookit-booking-system' );
					}
				} else {
					if ( 'no_show' === $current_status ) {
						$reason = __( 'Booking is already marked as no-show.', 'bookit-booking-system' );
					} elseif ( 'cancelled' === $current_status ) {
						$reason = __( 'Cancelled bookings cannot be marked as no-show.', 'bookit-booking-system' );
					} elseif ( 'completed' === $current_status ) {
						$reason = __( 'Completed bookings cannot be marked as no-show.', 'bookit-booking-system' );
					} elseif ( 'pending' === $current_status || 'pending_payment' === $current_status ) {
						$reason = __( 'Only confirmed bookings can be marked as no-show.', 'bookit-booking-system' );
					}
				}

				$failed[] = array(
					'id'     => $booking_id,
					'reason' => $reason,
				);
				continue;
			}

			$update_data = array(
				'status'     => $target_status,
				'updated_at' => current_time( 'mysql' ),
			);
			$formats = array( '%s', '%s' );

			if ( 'cancel' === $action ) {
				$update_data['deleted_at']           = current_time( 'mysql' );
				$update_data['cancelled_start_time'] = $booking['start_time'] ?? null;
				$update_data['cancelled_end_time']   = $booking['end_time'] ?? null;
				$update_data['start_time']           = null;
				$update_data['end_time']             = null;
				$formats[]                           = '%s'; // deleted_at
				$formats[]                           = '%s'; // cancelled_start_time
				$formats[]                           = '%s'; // cancelled_end_time
				$formats[]                           = '%s'; // start_time
				$formats[]                           = '%s'; // end_time
			}

			$result = $wpdb->update(
				$wpdb->prefix . 'bookings',
				$update_data,
				array( 'id' => $booking_id ),
				$formats,
				array( '%d' )
			);

			if ( false === $result ) {
				$failed[] = array(
					'id'     => $booking_id,
					'reason' => __( 'Failed to update booking.', 'bookit-booking-system' ),
				);
				continue;
			}

			$wpdb->insert(
				$wpdb->prefix . 'bookings_status_log',
				array(
					'booking_id'          => $booking_id,
					'old_status'          => $current_status,
					'new_status'          => $target_status,
					'changed_by_staff_id' => (int) $current_staff['id'],
					'changed_at'          => current_time( 'mysql' ),
					'notes'               => null,
				),
				array( '%d', '%s', '%s', '%d', '%s', '%s' )
			);

			if ( 'cancel' === $action ) {
				do_action( 'bookit_after_booking_cancelled', $booking_id, $booking );
			} else {
				do_action(
					'bookit_after_booking_updated',
					$booking_id,
					array(
						'status' => $target_status,
					)
				);
			}

			Bookit_Audit_Logger::log(
				$audit_action,
				'booking',
				$booking_id,
				array(
					'actor_id'  => (int) $current_staff['id'],
					'old_value' => array( 'status' => $current_status ),
					'new_value' => array( 'status' => $target_status ),
				)
			);

			$succeeded[] = $booking_id;
		}

		return rest_ensure_response(
			array(
				'succeeded' => $succeeded,
				'failed'    => $failed,
			)
		);
	}

	/**
	 * Get staff personal schedule (week view with upcoming).
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response|WP_Error
	 */
	public function get_my_schedule( $request ) {
		global $wpdb;

		$current_staff = Bookit_Auth::get_current_staff();
		if ( ! $current_staff ) {
			return new WP_Error(
				'unauthorized',
				__( 'Could not retrieve staff information.', 'bookit-booking-system' ),
				array( 'status' => 401 )
			);
		}

		$tz  = new \DateTimeZone( 'Europe/London' );
		$now = new \DateTimeImmutable( 'now', $tz );

		// Determine week start (Monday).
		$week_start_param = $request->get_param( 'week_start' );
		if ( $week_start_param ) {
			$week_start = new \DateTimeImmutable( $week_start_param, $tz );
			// Normalise to Monday if the supplied date isn't one.
			$dow = (int) $week_start->format( 'N' ); // 1=Mon … 7=Sun.
			if ( 1 !== $dow ) {
				$week_start = $week_start->modify( 'monday this week' );
			}
		} else {
			$week_start = $now->modify( 'monday this week' );
		}

		$week_end        = $week_start->modify( '+6 days' ); // Sunday.
		$today           = $now->format( 'Y-m-d' );
		$week_start_str  = $week_start->format( 'Y-m-d' );
		$week_end_str    = $week_end->format( 'Y-m-d' );
		$staff_id        = (int) $current_staff['id'];

		// Query bookings for the week.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
		$week_results = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT
					b.id,
					b.booking_reference,
					b.booking_date,
					b.start_time,
					b.end_time,
					b.status,
					b.duration,
					b.total_price,
					b.deposit_paid,
					b.staff_notes,
					b.special_requests,
					s.name AS service_name,
					s.duration AS service_duration,
					c.first_name AS customer_first_name,
					c.last_name AS customer_last_name
				FROM {$wpdb->prefix}bookings b
				INNER JOIN {$wpdb->prefix}bookings_services s ON b.service_id = s.id
				INNER JOIN {$wpdb->prefix}bookings_customers c ON b.customer_id = c.id
				WHERE b.staff_id = %d
				AND b.booking_date BETWEEN %s AND %s
				AND b.deleted_at IS NULL
				ORDER BY b.booking_date ASC, b.start_time ASC",
				$staff_id,
				$week_start_str,
				$week_end_str
			),
			ARRAY_A
		);

		if ( null === $week_results ) {
			return new WP_Error(
				'database_error',
				__( 'Failed to retrieve schedule.', 'bookit-booking-system' ),
				array( 'status' => 500 )
			);
		}

		// Upcoming bookings (next 7 days after the displayed week).
		$upcoming_bookings = array();
		$include_upcoming  = rest_sanitize_boolean( $request->get_param( 'include_upcoming' ) );

		if ( $include_upcoming ) {
			$upcoming_start     = $week_end->modify( '+1 day' );
			$upcoming_end       = $week_end->modify( '+7 days' );
			$upcoming_start_str = $upcoming_start->format( 'Y-m-d' );
			$upcoming_end_str   = $upcoming_end->format( 'Y-m-d' );

			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
			$upcoming_results = $wpdb->get_results(
				$wpdb->prepare(
					"SELECT
						b.id,
						b.booking_reference,
						b.booking_date,
						b.start_time,
						b.end_time,
						b.status,
						b.duration,
						b.total_price,
						b.deposit_paid,
						b.staff_notes,
						b.special_requests,
						s.name AS service_name,
						s.duration AS service_duration,
						c.first_name AS customer_first_name,
						c.last_name AS customer_last_name
					FROM {$wpdb->prefix}bookings b
					INNER JOIN {$wpdb->prefix}bookings_services s ON b.service_id = s.id
					INNER JOIN {$wpdb->prefix}bookings_customers c ON b.customer_id = c.id
					WHERE b.staff_id = %d
					AND b.booking_date BETWEEN %s AND %s
					AND b.deleted_at IS NULL
					ORDER BY b.booking_date ASC, b.start_time ASC",
					$staff_id,
					$upcoming_start_str,
					$upcoming_end_str
				),
				ARRAY_A
			);

			if ( $upcoming_results ) {
				$upcoming_bookings = array_map(
					function ( $row ) use ( $today ) {
						return $this->format_schedule_booking( $row, $today );
					},
					$upcoming_results
				);
			}
		}

		// Group week bookings by date (include all 7 days even if empty).
		$week_bookings = array();
		$today_total   = 0;
		$week_total    = 0;
		$current_day   = $week_start;

		for ( $i = 0; $i < 7; $i++ ) {
			$date_key                    = $current_day->format( 'Y-m-d' );
			$week_bookings[ $date_key ]  = array();
			$current_day                 = $current_day->modify( '+1 day' );
		}

		foreach ( $week_results as $row ) {
			$formatted = $this->format_schedule_booking( $row, $today );
			$date_key  = $row['booking_date'];

			if ( isset( $week_bookings[ $date_key ] ) ) {
				$week_bookings[ $date_key ][] = $formatted;
			}

			++$week_total;

			if ( $date_key === $today ) {
				++$today_total;
			}
		}

		return rest_ensure_response(
			array(
				'success'            => true,
				'week_start'         => $week_start_str,
				'week_end'           => $week_end_str,
				'today'              => $today,
				'staff_name'         => $current_staff['name'],
				'week_bookings'      => $week_bookings,
				'upcoming_bookings'  => $upcoming_bookings,
				'week_total'         => $week_total,
				'today_total'        => $today_total,
			)
		);
	}

	/**
	 * Get current staff member booking stats.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response|WP_Error
	 */
	public function get_my_stats( $request ) {
		global $wpdb;

		$current_staff = Bookit_Auth::get_current_staff();
		if ( ! $current_staff ) {
			return new WP_Error(
				'unauthorized',
				__( 'Could not retrieve staff information.', 'bookit-booking-system' ),
				array( 'status' => 401 )
			);
		}

		$show_earnings = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT setting_value FROM {$wpdb->prefix}bookings_settings
				WHERE setting_key = %s",
				'show_staff_earnings'
			)
		);

		if ( ! $show_earnings || '0' === $show_earnings || 'false' === $show_earnings ) {
			return new WP_Error(
				'earnings_hidden',
				__( 'Earnings display is disabled.', 'bookit-booking-system' ),
				array( 'status' => 403 )
			);
		}

		$now_london = new DateTimeImmutable( 'now', new DateTimeZone( 'Europe/London' ) );

		$week_start = $now_london->modify( 'monday this week' )->setTime( 0, 0, 0 );
		$week_end   = $week_start->modify( '+6 days' )->setTime( 23, 59, 59 );

		$month_start = $now_london->modify( 'first day of this month' )->setTime( 0, 0, 0 );
		$month_end   = $now_london->modify( 'last day of this month' )->setTime( 23, 59, 59 );

		$week_result = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT
					COUNT(DISTINCT b.id) AS booking_count,
					COALESCE(SUM(p.amount), 0) AS revenue
				FROM {$wpdb->prefix}bookings b
				LEFT JOIN {$wpdb->prefix}bookings_payments p
					ON p.booking_id = b.id
					AND p.payment_status = 'completed'
				WHERE b.staff_id = %d
				AND b.status = 'completed'
				AND b.deleted_at IS NULL
				AND b.booking_date BETWEEN %s AND %s",
				$current_staff['id'],
				$week_start->format( 'Y-m-d' ),
				$week_end->format( 'Y-m-d' )
			),
			ARRAY_A
		);

		$month_result = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT
					COUNT(DISTINCT b.id) AS booking_count,
					COALESCE(SUM(p.amount), 0) AS revenue
				FROM {$wpdb->prefix}bookings b
				LEFT JOIN {$wpdb->prefix}bookings_payments p
					ON p.booking_id = b.id
					AND p.payment_status = 'completed'
				WHERE b.staff_id = %d
				AND b.status = 'completed'
				AND b.deleted_at IS NULL
				AND b.booking_date BETWEEN %s AND %s",
				$current_staff['id'],
				$month_start->format( 'Y-m-d' ),
				$month_end->format( 'Y-m-d' )
			),
			ARRAY_A
		);

		$all_time_result = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT
					COUNT(DISTINCT b.id) AS booking_count,
					COALESCE(SUM(p.amount), 0) AS revenue
				FROM {$wpdb->prefix}bookings b
				LEFT JOIN {$wpdb->prefix}bookings_payments p
					ON p.booking_id = b.id
					AND p.payment_status = 'completed'
				WHERE b.staff_id = %d
				AND b.status = 'completed'
				AND b.deleted_at IS NULL",
				$current_staff['id']
			),
			ARRAY_A
		);

		return rest_ensure_response(
			array(
				'success' => true,
				'stats'   => array(
					'this_week'  => array(
						'booking_count' => (int) ( $week_result['booking_count'] ?? 0 ),
						'revenue'       => (float) ( $week_result['revenue'] ?? 0 ),
						'period_label'  => 'This Week',
					),
					'this_month' => array(
						'booking_count' => (int) ( $month_result['booking_count'] ?? 0 ),
						'revenue'       => (float) ( $month_result['revenue'] ?? 0 ),
						'period_label'  => 'This Month',
					),
					'all_time'   => array(
						'booking_count' => (int) ( $all_time_result['booking_count'] ?? 0 ),
						'revenue'       => (float) ( $all_time_result['revenue'] ?? 0 ),
						'period_label'  => 'All Time',
					),
				),
			)
		);
	}

	/**
	 * Get current staff member's blocked availability records.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response|WP_Error
	 */
	public function get_my_availability( $request ) {
		global $wpdb;

		$current_staff = Bookit_Auth::get_current_staff();
		if ( ! $current_staff ) {
			return new WP_Error(
				'unauthorized',
				__( 'Could not retrieve staff information.', 'bookit-booking-system' ),
				array( 'status' => 401 )
			);
		}

		$blocks = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT
					id,
					specific_date,
					start_time,
					end_time,
					is_working,
					notes,
					created_at
				FROM {$wpdb->prefix}bookings_staff_working_hours
				WHERE staff_id = %d
				AND specific_date IS NOT NULL
				AND is_working = 0
				ORDER BY specific_date ASC",
				(int) $current_staff['id']
			),
			ARRAY_A
		);

		$formatted_blocks = array_map(
			function ( $row ) {
				$parsed_notes = $this->parse_my_availability_notes( $row['notes'] );
				$is_all_day   = ( '00:00:00' === $row['start_time'] && '23:59:00' === $row['end_time'] );

				return array(
					'id'            => (int) $row['id'],
					'specific_date' => $row['specific_date'],
					'start_time'    => $is_all_day ? null : $row['start_time'],
					'end_time'      => $is_all_day ? null : $row['end_time'],
					'is_working'    => (bool) $row['is_working'],
					'is_all_day'    => $is_all_day,
					'reason'        => $parsed_notes['reason'],
					'notes'         => $parsed_notes['notes'],
					'created_at'    => $row['created_at'],
				);
			},
			$blocks
		);

		return rest_ensure_response(
			array(
				'success' => true,
				'blocks'  => $formatted_blocks,
			)
		);
	}

	/**
	 * Create one or more self-service availability blocks for current staff.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response|WP_Error
	 */
	public function create_my_availability_block( $request ) {
		global $wpdb;

		$current_staff = Bookit_Auth::get_current_staff();
		if ( ! $current_staff ) {
			return new WP_Error(
				'unauthorized',
				__( 'Could not retrieve staff information.', 'bookit-booking-system' ),
				array( 'status' => 401 )
			);
		}

		$date_from = sanitize_text_field( $request->get_param( 'date_from' ) );
		$date_to   = sanitize_text_field( $request->get_param( 'date_to' ) );
		$all_day   = rest_sanitize_boolean( $request->get_param( 'all_day' ) );
		$reason    = sanitize_text_field( $request->get_param( 'reason' ) );
		$notes     = sanitize_textarea_field( (string) $request->get_param( 'notes' ) );
		$repeat    = sanitize_text_field( $request->get_param( 'repeat' ) );

		$tz         = new DateTimeZone( 'Europe/London' );
		$today      = new DateTimeImmutable( 'now', $tz );
		$today_date = $today->format( 'Y-m-d' );

		if ( $date_from > $date_to ) {
			return new WP_Error(
				'invalid_date_range',
				__( 'Start date must be before or equal to end date.', 'bookit-booking-system' ),
				array( 'status' => 400 )
			);
		}

		if ( $date_from < $today_date ) {
			return new WP_Error(
				'invalid_start_date',
				__( 'You cannot block time off in the past.', 'bookit-booking-system' ),
				array( 'status' => 400 )
			);
		}

		if ( ! in_array( $repeat, array( 'none', 'daily', 'weekly' ), true ) ) {
			return new WP_Error(
				'invalid_repeat',
				__( 'Invalid repeat setting.', 'bookit-booking-system' ),
				array( 'status' => 400 )
			);
		}

		$start_time = '00:00:00';
		$end_time   = '23:59:00';

		if ( ! $all_day ) {
			$start_time = $this->normalize_time_with_seconds( $request->get_param( 'start_time' ) );
			$end_time   = $this->normalize_time_with_seconds( $request->get_param( 'end_time' ) );

			if ( empty( $start_time ) || empty( $end_time ) || strtotime( $start_time ) >= strtotime( $end_time ) ) {
				return new WP_Error(
					'invalid_time_range',
					__( 'Start time must be before end time.', 'bookit-booking-system' ),
					array( 'status' => 400 )
				);
			}
		}

		// Check booking conflicts before inserting rows.
		if ( $all_day ) {
			$conflict_count = (int) $wpdb->get_var(
				$wpdb->prepare(
					"SELECT COUNT(*) FROM {$wpdb->prefix}bookings
					WHERE staff_id = %d
					AND booking_date BETWEEN %s AND %s
					AND status = 'confirmed'
					AND deleted_at IS NULL",
					(int) $current_staff['id'],
					$date_from,
					$date_to
				)
			);
		} else {
			$conflict_count = (int) $wpdb->get_var(
				$wpdb->prepare(
					"SELECT COUNT(*) FROM {$wpdb->prefix}bookings
					WHERE staff_id = %d
					AND booking_date BETWEEN %s AND %s
					AND start_time < %s
					AND end_time > %s
					AND status = 'confirmed'
					AND deleted_at IS NULL",
					(int) $current_staff['id'],
					$date_from,
					$date_to,
					$end_time,
					$start_time
				)
			);
		}

		if ( $conflict_count > 0 ) {
			return new WP_Error(
				'booking_conflict',
				sprintf(
					_n(
						'You have %d confirmed booking during this time. Please reschedule it before blocking this time off.',
						'You have %d confirmed bookings during this time. Please reschedule them before blocking this time off.',
						$conflict_count,
						'bookit-booking-system'
					),
					$conflict_count
				),
				array( 'status' => 409 )
			);
		}

		$from_date = new DateTimeImmutable( $date_from, $tz );
		$to_date   = new DateTimeImmutable( $date_to, $tz );
		$dates     = array();

		if ( 'weekly' === $repeat ) {
			for ( $week = 0; $week < 8; $week++ ) {
				$dates[] = $from_date->modify( '+' . $week . ' weeks' )->format( 'Y-m-d' );
			}
		} else {
			$current = $from_date;
			while ( $current <= $to_date ) {
				$dates[] = $current->format( 'Y-m-d' );
				$current = $current->add( new DateInterval( 'P1D' ) );
			}
		}

		$notes_value    = $this->build_my_availability_notes( $reason, $notes );
		$repeat_weekly  = ( 'weekly' === $repeat ) ? 1 : 0;
		$created_count  = 0;
		$skipped_count  = 0;
		$skipped_dates  = array();

		foreach ( $dates as $date_string ) {
			$existing = $wpdb->get_var(
				$wpdb->prepare(
					"SELECT id FROM {$wpdb->prefix}bookings_staff_working_hours
					WHERE staff_id = %d
					AND specific_date = %s",
					(int) $current_staff['id'],
					$date_string
				)
			);

			if ( $existing ) {
				++$skipped_count;
				$skipped_dates[] = $date_string;
				continue;
			}

			$result = $wpdb->insert(
				$wpdb->prefix . 'bookings_staff_working_hours',
				array(
					'staff_id'      => (int) $current_staff['id'],
					'specific_date' => $date_string,
					'day_of_week'   => null,
					'start_time'    => $start_time,
					'end_time'      => $end_time,
					'is_working'    => 0,
					'repeat_weekly' => $repeat_weekly,
					'notes'         => $notes_value,
				),
				array( '%d', '%s', '%s', '%s', '%s', '%d', '%d', '%s' )
			);

			if ( false !== $result ) {
				++$created_count;
			}
		}

		return rest_ensure_response(
			array(
				'success'       => true,
				'message'       => __( 'Time off blocked successfully.', 'bookit-booking-system' ),
				'created'       => $created_count,
				'skipped'       => $skipped_count,
				'skipped_dates' => $skipped_dates,
			)
		);
	}

	/**
	 * Delete a self-service availability block owned by current staff.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response|WP_Error
	 */
	public function delete_my_availability_block( $request ) {
		global $wpdb;

		$current_staff = Bookit_Auth::get_current_staff();
		if ( ! $current_staff ) {
			return new WP_Error(
				'unauthorized',
				__( 'Could not retrieve staff information.', 'bookit-booking-system' ),
				array( 'status' => 401 )
			);
		}

		$id  = (int) $request['id'];
		$row = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT id, staff_id FROM {$wpdb->prefix}bookings_staff_working_hours
				WHERE id = %d AND is_working = 0 AND specific_date IS NOT NULL",
				$id
			),
			ARRAY_A
		);

		if ( ! $row ) {
			return new WP_Error(
				'not_found',
				__( 'Time-off block not found.', 'bookit-booking-system' ),
				array( 'status' => 404 )
			);
		}

		if ( (int) $row['staff_id'] !== (int) $current_staff['id'] ) {
			return new WP_Error(
				'forbidden',
				__( 'You cannot delete another staff member\'s time-off block.', 'bookit-booking-system' ),
				array( 'status' => 403 )
			);
		}

		$deleted = $wpdb->delete(
			$wpdb->prefix . 'bookings_staff_working_hours',
			array( 'id' => $id ),
			array( '%d' )
		);

		if ( false === $deleted ) {
			return new WP_Error(
				'delete_failed',
				__( 'Failed to remove time-off block.', 'bookit-booking-system' ),
				array( 'status' => 500 )
			);
		}

		return rest_ensure_response(
			array(
				'success' => true,
				'message' => __( 'Time-off block removed.', 'bookit-booking-system' ),
			)
		);
	}

	/**
	 * Normalize a time value to H:i:s.
	 *
	 * @param string|null $time Time string.
	 * @return string|null
	 */
	private function normalize_time_with_seconds( $time ) {
		$time = sanitize_text_field( (string) $time );
		if ( empty( $time ) || ! preg_match( '/^\d{2}:\d{2}(:\d{2})?$/', $time ) ) {
			return null;
		}

		if ( strlen( $time ) === 5 ) {
			return $time . ':00';
		}

		return $time;
	}

	/**
	 * Build serialized notes string for staff time-off blocks.
	 *
	 * @param string $reason Reason enum value.
	 * @param string $notes  Free-text notes.
	 * @return string
	 */
	private function build_my_availability_notes( $reason, $notes ) {
		$reason = sanitize_text_field( $reason );
		$notes  = sanitize_textarea_field( $notes );
		$notes  = str_replace( '|', ' ', $notes );

		return 'reason:' . $reason . '|notes:' . $notes;
	}

	/**
	 * Parse serialized notes string for staff time-off blocks.
	 *
	 * @param string|null $raw_notes Serialized notes payload.
	 * @return array
	 */
	private function parse_my_availability_notes( $raw_notes ) {
		$raw_notes = (string) $raw_notes;
		$reason    = 'other';
		$notes     = '';

		if ( 0 === strpos( $raw_notes, 'reason:' ) && false !== strpos( $raw_notes, '|notes:' ) ) {
			$parts = explode( '|notes:', $raw_notes, 2 );
			if ( 2 === count( $parts ) ) {
				$reason = str_replace( 'reason:', '', $parts[0] );
				$notes  = $parts[1];
			}
		} else {
			$notes = $raw_notes;
		}

		$allowed_reasons = array( 'vacation', 'sick_leave', 'lunch_break', 'personal', 'other' );
		if ( ! in_array( $reason, $allowed_reasons, true ) ) {
			$reason = 'other';
		}

		return array(
			'reason' => sanitize_text_field( $reason ),
			'notes'  => sanitize_textarea_field( $notes ),
		);
	}

	/**
	 * Format a single booking row for the schedule response.
	 *
	 * @param array  $row   Raw DB row.
	 * @param string $today Today's date in YYYY-MM-DD format.
	 * @return array Formatted booking.
	 */
	private function format_schedule_booking( $row, $today ) {
		$formatted = array(
			'id'               => (int) $row['id'],
			'booking_reference' => $row['booking_reference'] ?? '',
			'booking_date'     => $row['booking_date'],
			'start_time'       => substr( $row['start_time'], 0, 5 ),
			'end_time'         => substr( $row['end_time'], 0, 5 ),
			'status'           => $row['status'],
			'service_name'     => $row['service_name'],
			'duration'         => (int) $row['duration'],
			'customer_name'    => $row['customer_first_name'] . ' ' . $row['customer_last_name'],
			'total_price'      => (float) $row['total_price'],
			'deposit_paid'     => (float) $row['deposit_paid'],
			'staff_notes'      => $row['staff_notes'],
			'special_requests' => $row['special_requests'],
			'is_today'         => $row['booking_date'] === $today,
		);

		$formatted = apply_filters(
			'bookit_schedule_booking_response',
			$formatted,
			(int) $row['id']
		);

		return $formatted;
	}

	/**
	 * Get single booking details.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response|WP_Error
	 */
	public function get_booking_details( $request ) {
		global $wpdb;

		$booking_id = (int) $request->get_param( 'id' );

		$current_staff = Bookit_Auth::get_current_staff();
		if ( ! $current_staff ) {
			return new WP_Error(
				'unauthorized',
				'Could not retrieve staff information.',
				array( 'status' => 401 )
			);
		}

		// Get booking with all related data.
		$booking = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT
					b.*,
					c.id as customer_id,
					c.first_name AS customer_first_name,
					c.last_name AS customer_last_name,
					c.email AS customer_email,
					c.phone AS customer_phone,
					s.id as service_id,
					s.name AS service_name,
					s.duration as service_duration,
					s.price as service_price,
					st.id as staff_id,
					st.first_name AS staff_first_name,
					st.last_name AS staff_last_name,
					CONCAT(st.first_name, ' ', st.last_name) as staff_name
				FROM {$wpdb->prefix}bookings b
				INNER JOIN {$wpdb->prefix}bookings_customers c ON b.customer_id = c.id
				INNER JOIN {$wpdb->prefix}bookings_services s ON b.service_id = s.id
				INNER JOIN {$wpdb->prefix}bookings_staff st ON b.staff_id = st.id
				WHERE b.id = %d
				AND b.deleted_at IS NULL",
				$booking_id
			),
			ARRAY_A
		);

		if ( ! $booking ) {
			return Bookit_Error_Registry::to_wp_error(
				'E2002',
				array( 'booking_id' => $booking_id )
			);
		}

		// Permission check: staff can only view their own bookings.
		if ( 'staff' === $current_staff['role'] && (int) $booking['staff_id'] !== (int) $current_staff['id'] ) {
			return new WP_Error(
				'forbidden',
				'You do not have permission to view this booking.',
				array( 'status' => 403 )
			);
		}

		// Format booking for response.
		$formatted = $this->format_booking( $booking );

		return rest_ensure_response(
			array(
				'success' => true,
				'booking' => $formatted,
			)
		);
	}

	/**
	 * Allowed booking status transitions (from current status → targets).
	 *
	 * @return array<string, string[]>
	 */
	private static function get_allowed_transitions(): array {
		return array(
			'pending'         => array( 'pending_payment', 'confirmed', 'cancelled' ),
			'pending_payment' => array( 'confirmed', 'cancelled' ),
			'confirmed'       => array( 'completed', 'cancelled', 'no_show' ),
			'completed'       => array(),
			'cancelled'       => array(),
			'no_show'         => array(),
		);
	}

	/**
	 * Update existing booking.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response|WP_Error
	 */
	public function update_booking( $request ) {
		global $wpdb;

		$booking_id = (int) $request->get_param( 'id' );
		$client_lock_version = sanitize_text_field(
			(string) $request->get_param( 'lock_version' )
		);

		if ( ! empty( $client_lock_version ) ) {
			$db_lock_version = $wpdb->get_var(
				$wpdb->prepare(
					"SELECT lock_version FROM {$wpdb->prefix}bookings WHERE id = %d",
					$booking_id
				)
			);

			if ( null === $db_lock_version ) {
				return Bookit_Error_Registry::to_wp_error(
					'E2002',
					array( 'booking_id' => $booking_id )
				);
			}

			if ( (string) $db_lock_version !== $client_lock_version ) {
				return Bookit_Error_Registry::to_wp_error(
					'E2004',
					array( 'booking_id' => $booking_id )
				);
			}
		}

		$current_staff = Bookit_Auth::get_current_staff();
		if ( ! $current_staff ) {
			return new WP_Error(
				'unauthorized',
				'Could not retrieve staff information.',
				array( 'status' => 401 )
			);
		}

		// Get existing booking.
		$existing = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$wpdb->prefix}bookings WHERE id = %d AND deleted_at IS NULL",
				$booking_id
			),
			ARRAY_A
		);

		if ( ! $existing ) {
			return Bookit_Error_Registry::to_wp_error(
				'E2002',
				array( 'booking_id' => $booking_id )
			);
		}

		$old_status = $existing['status'];

		// Permission check: staff can only edit their own bookings.
		if ( 'staff' === $current_staff['role'] && (int) $existing['staff_id'] !== (int) $current_staff['id'] ) {
			return new WP_Error(
				'forbidden',
				'You do not have permission to edit this booking.',
				array( 'status' => 403 )
			);
		}

		// Get new values.
		$new_service_id = (int) $request->get_param( 'service_id' );
		$new_staff_id   = (int) $request->get_param( 'staff_id' );
		$new_date       = $request->get_param( 'booking_date' );
		$new_time       = $request->get_param( 'booking_time' );
		$new_status     = $request->get_param( 'status' );

		// State transition enforcement (Issue 7).
		if ( null !== $new_status && '' !== $new_status && $new_status !== $old_status ) {
			$allowed = self::get_allowed_transitions()[ $old_status ] ?? array();
			if ( ! in_array( $new_status, $allowed, true ) ) {
				Bookit_Audit_Logger::log(
					'booking.invalid_transition',
					'booking',
					$booking_id,
					array(
						'old_status' => $old_status,
						'new_status' => $new_status,
					)
				);
				return Bookit_Error_Registry::to_wp_error(
					'E2005',
					array(
						'booking_id' => $booking_id,
						'old_status' => $old_status,
						'new_status' => $new_status,
					)
				);
			}
		}

		// Check if date/time/staff/service changed - need to verify availability.
		$datetime_changed =
			$existing['booking_date'] !== $new_date ||
			$existing['start_time'] !== $new_time ||
			(int) $existing['staff_id'] !== $new_staff_id ||
			(int) $existing['service_id'] !== $new_service_id;

		if ( $datetime_changed ) {
			// Load datetime model for availability check.
			if ( ! class_exists( 'Bookit_DateTime_Model' ) ) {
				require_once plugin_dir_path( dirname( __FILE__ ) ) . 'models/class-datetime-model.php';
			}
			$datetime_model = new Bookit_DateTime_Model();

			// Normalize booking_time to H:i:s for comparison.
			$time_check = $new_time;
			if ( strlen( $time_check ) === 5 ) {
				$time_check .= ':00';
			}

			// Check if new time slot is available.
			// Pass the booking ID to exclude it from conflict checking.
			$slots = $datetime_model->get_available_slots( $new_date, $new_service_id, $new_staff_id, $booking_id );

			if ( empty( $slots ) || ! in_array( $time_check, $slots, true ) ) {
				return new WP_Error(
					'time_not_available',
					'The selected time slot is not available for this staff member.',
					array( 'status' => 400 )
				);
			}
		}

		// Get service details for duration calculation.
		$service = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT duration, price FROM {$wpdb->prefix}bookings_services WHERE id = %d",
				$new_service_id
			),
			ARRAY_A
		);

		if ( ! $service ) {
			return new WP_Error(
				'service_not_found',
				'Service not found.',
				array( 'status' => 404 )
			);
		}

		// Calculate end time.
		$start_datetime = new DateTime( $new_date . ' ' . $new_time );
		$end_datetime   = clone $start_datetime;
		$end_datetime->modify( '+' . $service['duration'] . ' minutes' );

		// Calculate payment values.
		$service_price = (float) $service['price'];
		$amount_paid   = (float) $request->get_param( 'amount_paid' );

		// Update booking.
		$update_data = array(
			'service_id'       => $new_service_id,
			'staff_id'         => $new_staff_id,
			'booking_date'     => $new_date,
			'start_time'       => $new_time,
			'end_time'         => $end_datetime->format( 'H:i:s' ),
			'duration'         => (int) $service['duration'],
			'status'           => $new_status,
			'payment_method'   => $request->get_param( 'payment_method' ),
			'special_requests' => $request->get_param( 'special_requests' ),
			'staff_notes'      => $request->get_param( 'staff_notes' ),
			'updated_at'       => current_time( 'mysql' ),
			'total_price'      => $service_price,
			'deposit_paid'     => $amount_paid,
			'balance_due'      => $service_price - $amount_paid,
			'full_amount_paid' => $amount_paid >= $service_price ? 1 : 0,
		);

		$old_data = $existing;
		$new_data = $update_data;

		// Notify extensions before booking update data is persisted.
		do_action( 'bookit_before_booking_updated', $booking_id, $old_data, $new_data );

		$result = $wpdb->update(
			$wpdb->prefix . 'bookings',
			$update_data,
			array( 'id' => $booking_id ),
			array( '%d', '%d', '%s', '%s', '%s', '%d', '%s', '%s', '%s', '%s', '%s', '%f', '%f', '%f', '%d' ),
			array( '%d' )
		);

		if ( false === $result ) {
			return new WP_Error(
				'update_failed',
				'Failed to update booking.',
				array( 'status' => 500 )
			);
		}

		$new_updated_at   = current_time( 'mysql' );
		$new_lock_version = Bookit_Reference_Generator::generate_lock_version(
			$booking_id,
			$new_updated_at
		);
		$lock_update      = $wpdb->update(
			$wpdb->prefix . 'bookings',
			array( 'lock_version' => $new_lock_version ),
			array( 'id' => $booking_id ),
			array( '%s' ),
			array( '%d' )
		);

		if ( false === $lock_update ) {
			return new WP_Error(
				'update_failed',
				'Failed to update booking lock version.',
				array( 'status' => 500 )
			);
		}

		// Notify extensions after booking update has been persisted.
		do_action( 'bookit_after_booking_updated', $booking_id, $new_data );

		// Fire reassigned / rescheduled lifecycle hooks (Sprint 6A-1).
		$old_staff_id = (int) $existing['staff_id'];

		$new_time_for_compare = $new_time;
		if ( is_string( $new_time_for_compare ) && strlen( $new_time_for_compare ) === 5 ) {
			$new_time_for_compare .= ':00';
		}

		$date_changed = (string) $existing['booking_date'] !== (string) $new_date;
		$time_changed = (string) $existing['start_time'] !== (string) $new_time_for_compare;

		if ( $date_changed || $time_changed ) {
			do_action(
				'bookit_booking_rescheduled',
				$booking_id,
				$update_data
			);
		}

		if ( $old_staff_id !== $new_staff_id ) {
			do_action(
				'bookit_booking_reassigned',
				$booking_id,
				$old_staff_id,
				$new_staff_id,
				$update_data
			);
		}

		// Send notification email if requested.
		$send_notification = filter_var( $request->get_param( 'send_notification' ), FILTER_VALIDATE_BOOLEAN );

		if ( $send_notification ) {
			// Load email sender.
			if ( ! class_exists( 'Booking_System_Email_Sender' ) ) {
				require_once plugin_dir_path( dirname( __DIR__ ) ) . 'email/class-email-sender.php';
			}

			// Get full booking details for email.
			$booking = $wpdb->get_row(
				$wpdb->prepare(
					"SELECT
						b.*,
						c.first_name AS customer_first_name,
						c.last_name AS customer_last_name,
						c.email AS customer_email,
						c.phone AS customer_phone,
						s.name AS service_name,
						s.duration,
						st.first_name AS staff_first_name,
						st.last_name AS staff_last_name
					FROM {$wpdb->prefix}bookings b
					INNER JOIN {$wpdb->prefix}bookings_customers c ON b.customer_id = c.id
					INNER JOIN {$wpdb->prefix}bookings_services s ON b.service_id = s.id
					INNER JOIN {$wpdb->prefix}bookings_staff st ON b.staff_id = st.id
					WHERE b.id = %d",
					$booking_id
				),
				ARRAY_A
			);

			// Add composite name fields expected by the email sender.
			$booking['customer_name'] = $booking['customer_first_name'] . ' ' . $booking['customer_last_name'];
			$booking['staff_name']    = $booking['staff_first_name'] . ' ' . $booking['staff_last_name'];

			$email_sender = new Booking_System_Email_Sender();
			if ( $date_changed || $time_changed ) {
				$email_sender->send_customer_reschedule( $booking );
			} else {
				$email_sender->send_customer_confirmation( $booking );
			}
		}

		// Get updated booking for response.
		$updated_booking = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT
					b.*,
					c.first_name AS customer_first_name,
					c.last_name AS customer_last_name,
					c.email AS customer_email,
					c.phone AS customer_phone,
					s.name AS service_name,
					st.first_name AS staff_first_name,
					st.last_name AS staff_last_name
				FROM {$wpdb->prefix}bookings b
				INNER JOIN {$wpdb->prefix}bookings_customers c ON b.customer_id = c.id
				INNER JOIN {$wpdb->prefix}bookings_services s ON b.service_id = s.id
				INNER JOIN {$wpdb->prefix}bookings_staff st ON b.staff_id = st.id
				WHERE b.id = %d",
				$booking_id
			),
			ARRAY_A
		);

		// Log status change to audit trail (Sprint 4A).
		if ( $old_status !== $new_status ) {
			$wpdb->insert(
				$wpdb->prefix . 'bookings_status_log',
				array(
					'booking_id'          => $booking_id,
					'old_status'          => $old_status,
					'new_status'          => $new_status,
					'changed_by_staff_id' => $current_staff['id'],
					'changed_at'          => current_time( 'mysql' ),
					'notes'               => null,
				),
				array( '%d', '%s', '%s', '%d', '%s', '%s' )
			);
		}

		Bookit_Audit_Logger::log(
			'booking.updated',
			'booking',
			$booking_id,
			array(
				'old_value' => $old_data,
				'new_value' => $new_data,
				'notes'     => 'Booking updated via dashboard',
			)
		);

		return rest_ensure_response(
			array(
				'success'    => true,
				'message'    => 'Booking updated successfully.',
				'lock_version' => $new_lock_version,
				'booking'    => $this->format_booking( $updated_booking ),
				'email_sent' => $send_notification,
			)
		);
	}

	/**
	 * Cancel booking.
	 *
	 * Sets status to 'cancelled' and deleted_at timestamp.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response|WP_Error
	 */
	public function cancel_booking( $request ) {
		global $wpdb;

		$booking_id = (int) $request->get_param( 'id' );

		$current_staff = Bookit_Auth::get_current_staff();
		if ( ! $current_staff ) {
			return new WP_Error(
				'unauthorized',
				'Could not retrieve staff information.',
				array( 'status' => 401 )
			);
		}

		// Get existing booking.
		$existing = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$wpdb->prefix}bookings WHERE id = %d AND deleted_at IS NULL",
				$booking_id
			),
			ARRAY_A
		);

		if ( ! $existing ) {
			return Bookit_Error_Registry::to_wp_error(
				'E2002',
				array( 'booking_id' => $booking_id )
			);
		}

		$old_status = $existing['status'];

		// Permission check: staff can only cancel their own bookings.
		if ( 'staff' === $current_staff['role'] && (int) $existing['staff_id'] !== (int) $current_staff['id'] ) {
			return new WP_Error(
				'forbidden',
				'You do not have permission to cancel this booking.',
				array( 'status' => 403 )
			);
		}

		$cancellation_reason = $request->get_param( 'cancellation_reason' );

		// Update booking: set status to cancelled AND soft delete.
		$update_data = array(
			'status'     => 'cancelled',
			'deleted_at' => current_time( 'mysql' ),
			'updated_at' => current_time( 'mysql' ),
		);

		$format = array( '%s', '%s', '%s' );

		// Add cancellation reason to staff notes.
		if ( ! empty( $cancellation_reason ) ) {
			$existing_notes    = $existing['staff_notes'] ?? '';
			$cancellation_note = "\n\n[Cancelled " . current_time( 'Y-m-d H:i:s' ) . "]\n" . $cancellation_reason;
			$update_data['staff_notes'] = $existing_notes . $cancellation_note;
			$format[] = '%s';
		}

		$update_data['cancelled_start_time'] = $existing['start_time'];
		$update_data['cancelled_end_time']   = $existing['end_time'];
		$update_data['start_time']           = null;
		$update_data['end_time']             = null;
		$format[]                            = '%s'; // cancelled_start_time
		$format[]                            = '%s'; // cancelled_end_time
		$format[]                            = '%s'; // start_time (NULL serialised as %s in wpdb)
		$format[]                            = '%s'; // end_time

		// Notify extensions before a booking is cancelled.
		do_action( 'bookit_before_booking_cancelled', $booking_id, $existing );

		$result = $wpdb->update(
			$wpdb->prefix . 'bookings',
			$update_data,
			array( 'id' => $booking_id ),
			$format,
			array( '%d' )
		);

		if ( false === $result ) {
			return new WP_Error(
				'cancellation_failed',
				'Failed to cancel booking.',
				array( 'status' => 500 )
			);
		}

		// Notify extensions after a booking is cancelled.
		do_action( 'bookit_after_booking_cancelled', $booking_id, $existing );

		// Send cancellation email if requested.
		$send_notification = filter_var( $request->get_param( 'send_notification' ), FILTER_VALIDATE_BOOLEAN );

		if ( $send_notification ) {
			// Load email sender.
			if ( ! class_exists( 'Booking_System_Email_Sender' ) ) {
				require_once plugin_dir_path( dirname( __DIR__ ) ) . 'email/class-email-sender.php';
			}

			// Get full booking details for email.
			$booking = $wpdb->get_row(
				$wpdb->prepare(
					"SELECT
						b.*,
						c.first_name AS customer_first_name,
						c.last_name AS customer_last_name,
						c.email AS customer_email,
						c.phone AS customer_phone,
						s.name AS service_name,
						s.duration,
						st.first_name AS staff_first_name,
						st.last_name AS staff_last_name
					FROM {$wpdb->prefix}bookings b
					INNER JOIN {$wpdb->prefix}bookings_customers c ON b.customer_id = c.id
					INNER JOIN {$wpdb->prefix}bookings_services s ON b.service_id = s.id
					INNER JOIN {$wpdb->prefix}bookings_staff st ON b.staff_id = st.id
					WHERE b.id = %d",
					$booking_id
				),
				ARRAY_A
			);

			// Add composite name fields expected by the email sender.
			$booking['customer_name'] = $booking['customer_first_name'] . ' ' . $booking['customer_last_name'];
			$booking['staff_name']    = $booking['staff_first_name'] . ' ' . $booking['staff_last_name'];

			$email_sender = new Booking_System_Email_Sender();
			$email_sender->send_customer_cancellation( $booking );
		}

		Bookit_Audit_Logger::log(
			'booking.cancelled',
			'booking',
			$booking_id,
			array(
				'old_value' => array( 'status' => $old_status ),
				'new_value' => array( 'status' => 'cancelled' ),
				'notes'     => 'Booking cancelled via dashboard',
			)
		);

		return rest_ensure_response(
			array(
				'success'    => true,
				'message'    => 'Booking cancelled successfully.',
				'email_sent' => $send_notification,
			)
		);
	}

	/**
	 * Create manual booking via dashboard.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response|WP_Error
	 */
	public function create_manual_booking( $request ) {
		global $wpdb;

		// Verify staff is logged in.
		$current_staff = Bookit_Auth::get_current_staff();
		if ( ! $current_staff ) {
			return new WP_Error(
				'unauthorized',
				'Could not retrieve staff information.',
				array( 'status' => 401 )
			);
		}

		// Get or create customer.
		$customer_id = $request->get_param( 'customer_id' );

		if ( empty( $customer_id ) ) {
			// Create new customer.
			$customer_email = $request->get_param( 'customer_email' );
			$customer_first = $request->get_param( 'customer_first_name' );
			$customer_last  = $request->get_param( 'customer_last_name' );
			$customer_phone = $request->get_param( 'customer_phone' );

			if ( empty( $customer_email ) || empty( $customer_first ) || empty( $customer_last ) ) {
				return new WP_Error(
					'missing_customer_data',
					'Customer email, first name, and last name are required for new customers.',
					array( 'status' => 400 )
				);
			}

			// Check if customer already exists.
			$existing_customer = $wpdb->get_var(
				$wpdb->prepare(
					"SELECT id FROM {$wpdb->prefix}bookings_customers WHERE email = %s AND deleted_at IS NULL",
					$customer_email
				)
			);

			if ( $existing_customer ) {
				$customer_id = $existing_customer;
			} else {
				// Create new customer.
				$customer_data = array(
					'email'      => $customer_email,
					'first_name' => $customer_first,
					'last_name'  => $customer_last,
					'phone'      => $customer_phone,
					'created_at' => current_time( 'mysql' ),
					'updated_at' => current_time( 'mysql' ),
				);

				$result = $wpdb->insert(
					$wpdb->prefix . 'bookings_customers',
					$customer_data,
					array( '%s', '%s', '%s', '%s', '%s', '%s' )
				);

				if ( ! $result ) {
					return new WP_Error(
						'customer_creation_failed',
						'Failed to create customer.',
						array( 'status' => 500 )
					);
				}

				$customer_id = $wpdb->insert_id;

				// Notify extensions after a customer is created from dashboard manual booking.
				do_action( 'bookit_after_customer_created', (int) $customer_id, $customer_data );
			}
		}

		// Verify customer exists.
		$customer = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$wpdb->prefix}bookings_customers WHERE id = %d AND deleted_at IS NULL",
				$customer_id
			),
			ARRAY_A
		);

		if ( ! $customer ) {
			return new WP_Error(
				'customer_not_found',
				'Customer not found.',
				array( 'status' => 404 )
			);
		}

		// Get staff ID (handle "no preference" = 0).
		$requested_staff_id = (int) $request->get_param( 'staff_id' );
		$service_id         = (int) $request->get_param( 'service_id' );
		$booking_date       = $request->get_param( 'booking_date' );
		$booking_time       = $request->get_param( 'booking_time' );

		// If staff_id is 0 (no preference), find first available staff for this service.
		if ( 0 === $requested_staff_id ) {
			// Get all staff who can provide this service.
			$available_staff = $wpdb->get_results(
				$wpdb->prepare(
					"SELECT DISTINCT s.id
					FROM {$wpdb->prefix}bookings_staff s
					INNER JOIN {$wpdb->prefix}bookings_staff_services ss ON s.id = ss.staff_id
					WHERE s.is_active = 1
					AND s.deleted_at IS NULL
					AND ss.service_id = %d
					ORDER BY s.first_name ASC",
					$service_id
				),
				ARRAY_A
			);

			if ( empty( $available_staff ) ) {
				return new WP_Error(
					'no_staff_available',
					'No staff members can provide this service.',
					array( 'status' => 400 )
				);
			}

			// Load datetime model to check availability.
			if ( ! class_exists( 'Bookit_DateTime_Model' ) ) {
				require_once plugin_dir_path( dirname( __FILE__ ) ) . 'models/class-datetime-model.php';
			}
			$datetime_model = new Bookit_DateTime_Model();

			// Normalize booking_time to H:i:s for comparison with model output.
			$time_check = $booking_time;
			if ( strlen( $time_check ) === 5 ) {
				$time_check .= ':00';
			}

			// Find first staff with availability at this time.
			$assigned_staff_id = null;
			foreach ( $available_staff as $staff ) {
				$slots = $datetime_model->get_available_slots( $booking_date, $service_id, (int) $staff['id'] );

				if ( ! empty( $slots ) && in_array( $time_check, $slots, true ) ) {
					$assigned_staff_id = (int) $staff['id'];
					break;
				}
			}

			if ( ! $assigned_staff_id ) {
				return new WP_Error(
					'no_staff_available_at_time',
					'No staff members are available at the selected time.',
					array( 'status' => 400 )
				);
			}

			$requested_staff_id = $assigned_staff_id;
		}

		// Prepare booking data for Booking_Creator.
		$booking_data = array(
			'service_id'          => $service_id,
			'staff_id'            => $requested_staff_id,
			'booking_date'        => $booking_date,
			'booking_time'        => $booking_time,
			'customer_email'      => $customer['email'],
			'customer_first_name' => $customer['first_name'],
			'customer_last_name'  => $customer['last_name'],
			'customer_phone'      => $customer['phone'],
			'payment_method'      => $request->get_param( 'payment_method' ),
			'amount_paid'         => (float) $request->get_param( 'amount_paid' ),
			'special_requests'    => $request->get_param( 'special_requests' ),
			'skip_waiver'         => true,
		);

		// Allow extensions to modify booking data before dashboard manual booking creation.
		$booking_data = apply_filters( 'bookit_booking_data_before_insert', $booking_data );

		// Notify extensions before a booking is created from the dashboard.
		do_action( 'bookit_before_booking_created', $booking_data );

		// Load booking creator if not loaded.
		if ( ! class_exists( 'Booking_System_Booking_Creator' ) ) {
			require_once plugin_dir_path( dirname( __DIR__ ) ) . 'booking/class-booking-creator.php';
		}

		// Create booking.
		$creator = new Booking_System_Booking_Creator();
		$result  = $creator->create_booking( $booking_data );

		if ( is_wp_error( $result ) ) {
			return $result;
		}

		$booking_id = $result;
		// Booking_System_Booking_Creator::create_booking() persists booking_reference and lock_version.

		Bookit_Audit_Logger::log(
			'booking.created',
			'booking',
			$booking_id,
			array(
				'new_value' => $booking_data,
				'notes'     => sprintf( 'Booking created manually for customer ID %d', $customer_id ),
			)
		);

		// Notify extensions after a booking is created from the dashboard.
		do_action( 'bookit_after_booking_created', (int) $booking_id, $booking_data );

		// Determine initial status based on settings and payment.
		$require_approval = get_option( 'bookit_require_approval', false );
		$payment_method   = $request->get_param( 'payment_method' );

		if ( $require_approval ) {
			// When approval required, all bookings start as pending.
			$initial_status = 'pending';
		} else {
			// When no approval required, use payment-based logic.
			if ( 'pay_on_arrival' === $payment_method ) {
				$initial_status = 'confirmed';
			} elseif ( in_array( $payment_method, array( 'cash', 'card_external', 'check', 'complimentary' ), true ) ) {
				$initial_status = 'confirmed';
			} elseif ( 'stripe' === $payment_method && (float) $request->get_param( 'amount_paid' ) > 0 ) {
				$initial_status = 'confirmed';
			} else {
				$initial_status = 'pending_payment';
			}
		}

		// Update booking status.
		$wpdb->update(
			$wpdb->prefix . 'bookings',
			array( 'status' => $initial_status ),
			array( 'id' => $booking_id ),
			array( '%s' ),
			array( '%d' )
		);

		// Send confirmation emails if requested.
		$send_confirmation = filter_var( $request->get_param( 'send_confirmation' ), FILTER_VALIDATE_BOOLEAN );

		if ( $send_confirmation ) {
			// Load email sender.
			if ( ! class_exists( 'Booking_System_Email_Sender' ) ) {
				require_once plugin_dir_path( dirname( __DIR__ ) ) . 'email/class-email-sender.php';
			}

			// Get full booking details for email.
			$booking = $wpdb->get_row(
				$wpdb->prepare(
					"SELECT
						b.*,
						c.first_name AS customer_first_name,
						c.last_name AS customer_last_name,
						c.email AS customer_email,
						c.phone AS customer_phone,
						s.name AS service_name,
						s.duration,
						st.first_name AS staff_first_name,
						st.last_name AS staff_last_name
					FROM {$wpdb->prefix}bookings b
					INNER JOIN {$wpdb->prefix}bookings_customers c ON b.customer_id = c.id
					INNER JOIN {$wpdb->prefix}bookings_services s ON b.service_id = s.id
					INNER JOIN {$wpdb->prefix}bookings_staff st ON b.staff_id = st.id
					WHERE b.id = %d",
					$booking_id
				),
				ARRAY_A
			);

			// Add composite name fields expected by the email sender.
			$booking['customer_name'] = $booking['customer_first_name'] . ' ' . $booking['customer_last_name'];
			$booking['staff_name']    = $booking['staff_first_name'] . ' ' . $booking['staff_last_name'];

			$email_sender = new Booking_System_Email_Sender();
			$email_sender->send_customer_confirmation( $booking );
			// Staff notifications handled by Bookit_Staff_Notifier via bookit_after_booking_created hook (fired above).
		}

		// Get created booking for response.
		$created_booking = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT
					b.*,
					c.first_name AS customer_first_name,
					c.last_name AS customer_last_name,
					c.email AS customer_email,
					c.phone AS customer_phone,
					s.name AS service_name,
					st.first_name AS staff_first_name,
					st.last_name AS staff_last_name
				FROM {$wpdb->prefix}bookings b
				INNER JOIN {$wpdb->prefix}bookings_customers c ON b.customer_id = c.id
				INNER JOIN {$wpdb->prefix}bookings_services s ON b.service_id = s.id
				INNER JOIN {$wpdb->prefix}bookings_staff st ON b.staff_id = st.id
				WHERE b.id = %d",
				$booking_id
			),
			ARRAY_A
		);

		return rest_ensure_response(
			array(
				'success'    => true,
				'message'    => 'Booking created successfully.',
				'booking_id' => $booking_id,
				'booking'    => $this->format_booking( $created_booking ),
				'email_sent' => $send_confirmation,
			)
		);
	}

	/**
	 * Search customers by name or email.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response|WP_Error
	 */
	public function search_customers( $request ) {
		global $wpdb;

		$search = $request->get_param( 'search' );

		if ( strlen( $search ) < 2 ) {
			return rest_ensure_response(
				array(
					'success'   => true,
					'customers' => array(),
				)
			);
		}

		$search_param = '%' . $wpdb->esc_like( $search ) . '%';

		$customers = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT
					id,
					email,
					first_name,
					last_name,
					phone,
					CONCAT(first_name, ' ', last_name) AS full_name
				FROM {$wpdb->prefix}bookings_customers
				WHERE deleted_at IS NULL
				AND (
					first_name LIKE %s OR
					last_name LIKE %s OR
					email LIKE %s OR
					CONCAT(first_name, ' ', last_name) LIKE %s
				)
				ORDER BY first_name ASC, last_name ASC
				LIMIT 20",
				$search_param,
				$search_param,
				$search_param,
				$search_param
			),
			ARRAY_A
		);

		return rest_ensure_response(
			array(
				'success'   => true,
				'customers' => $customers,
			)
		);
	}

	/**
	 * Get categories list with optional filters.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response
	 */
	public function get_categories_list( $request ) {
		global $wpdb;

		// Get query parameters.
		$search      = $request->get_param( 'search' );
		$status      = $request->get_param( 'status' ); // 'active', 'inactive', 'all'.
		$include_all = $request->get_param( 'include_all' ); // Include all for dropdowns.

		// Build WHERE clauses.
		$where_clauses = array( 'deleted_at IS NULL' );
		$where_params  = array();

		// If include_all is not set, apply filters.
		if ( ! $include_all ) {
			// Search filter.
			if ( ! empty( $search ) ) {
				$where_clauses[] = '(name LIKE %s OR description LIKE %s)';
				$search_term     = '%' . $wpdb->esc_like( $search ) . '%';
				$where_params[]  = $search_term;
				$where_params[]  = $search_term;
			}

			// Status filter.
			if ( 'active' === $status ) {
				$where_clauses[] = 'is_active = 1';
			} elseif ( 'inactive' === $status ) {
				$where_clauses[] = 'is_active = 0';
			}
		} else {
			// For dropdowns, only show active categories.
			$where_clauses[] = 'is_active = 1';
		}

		$where_sql = implode( ' AND ', $where_clauses );

		// Get categories with service count.
		$query = "SELECT 
					c.id,
					c.name,
					c.description,
					c.display_order,
					c.is_active,
					c.created_at,
					c.updated_at,
					COUNT(DISTINCT sc.service_id) as service_count
				FROM {$wpdb->prefix}bookings_categories c
				LEFT JOIN {$wpdb->prefix}bookings_service_categories sc ON c.id = sc.category_id
				WHERE $where_sql
				GROUP BY c.id
				ORDER BY c.display_order ASC, c.name ASC";

		if ( ! empty( $where_params ) ) {
			$query = $wpdb->prepare( $query, $where_params );
		}

		$categories = $wpdb->get_results( $query, ARRAY_A );

		// Convert numeric fields.
		foreach ( $categories as &$category ) {
			$category['id']            = (int) $category['id'];
			$category['display_order'] = (int) $category['display_order'];
			$category['is_active']     = (bool) $category['is_active'];
			$category['service_count'] = (int) $category['service_count'];
		}

		return rest_ensure_response(
			array(
				'success'    => true,
				'categories' => $categories,
			)
		);
	}

	/**
	 * Get single category details.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response|WP_Error
	 */
	public function get_category_details( $request ) {
		global $wpdb;

		$category_id = (int) $request->get_param( 'id' );

		$category = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT c.*,
					COUNT(DISTINCT sc.service_id) as service_count
				FROM {$wpdb->prefix}bookings_categories c
				LEFT JOIN {$wpdb->prefix}bookings_service_categories sc ON c.id = sc.category_id
				WHERE c.id = %d
				AND c.deleted_at IS NULL
				GROUP BY c.id",
				$category_id
			),
			ARRAY_A
		);

		if ( ! $category ) {
			return new WP_Error(
				'category_not_found',
				'Category not found.',
				array( 'status' => 404 )
			);
		}

		// Convert numeric fields.
		$category['id']            = (int) $category['id'];
		$category['display_order'] = (int) $category['display_order'];
		$category['is_active']     = (bool) $category['is_active'];
		$category['service_count'] = (int) $category['service_count'];

		return rest_ensure_response(
			array(
				'success'  => true,
				'category' => $category,
			)
		);
	}

	/**
	 * Create new category.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response|WP_Error
	 */
	public function create_category( $request ) {
		global $wpdb;

		$name          = $request->get_param( 'name' );
		$description   = $request->get_param( 'description' );
		$is_active     = filter_var( $request->get_param( 'is_active' ), FILTER_VALIDATE_BOOLEAN );
		$display_order = (int) $request->get_param( 'display_order' );

		// Check for duplicate name.
		$existing = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT id FROM {$wpdb->prefix}bookings_categories 
				WHERE name = %s AND deleted_at IS NULL",
				$name
			)
		);

		if ( $existing ) {
			return new WP_Error(
				'duplicate_name',
				'A category with this name already exists.',
				array( 'status' => 409 )
			);
		}

		// Insert category.
		$result = $wpdb->insert(
			$wpdb->prefix . 'bookings_categories',
			array(
				'name'          => $name,
				'description'   => $description,
				'is_active'     => $is_active ? 1 : 0,
				'display_order' => $display_order,
				'created_at'    => current_time( 'mysql' ),
				'updated_at'    => current_time( 'mysql' ),
			),
			array( '%s', '%s', '%d', '%d', '%s', '%s' )
		);

		if ( false === $result ) {
			return new WP_Error(
				'creation_failed',
				'Failed to create category.',
				array( 'status' => 500 )
			);
		}

		$category_id = $wpdb->insert_id;

		// Get created category.
		$category = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$wpdb->prefix}bookings_categories WHERE id = %d",
				$category_id
			),
			ARRAY_A
		);

		$category['service_count'] = 0;

		return rest_ensure_response(
			array(
				'success'  => true,
				'message'  => 'Category created successfully.',
				'category' => $category,
			)
		);
	}

	/**
	 * Update existing category.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response|WP_Error
	 */
	public function update_category( $request ) {
		global $wpdb;

		$category_id = (int) $request->get_param( 'id' );

		// Check if category exists.
		$existing = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT id, name FROM {$wpdb->prefix}bookings_categories 
				WHERE id = %d AND deleted_at IS NULL",
				$category_id
			),
			ARRAY_A
		);

		if ( ! $existing ) {
			return new WP_Error(
				'category_not_found',
				'Category not found.',
				array( 'status' => 404 )
			);
		}

		$name          = $request->get_param( 'name' );
		$description   = $request->get_param( 'description' );
		$is_active     = filter_var( $request->get_param( 'is_active' ), FILTER_VALIDATE_BOOLEAN );
		$display_order = (int) $request->get_param( 'display_order' );

		// Check for duplicate name (excluding current category).
		$duplicate = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT id FROM {$wpdb->prefix}bookings_categories 
				WHERE name = %s AND id != %d AND deleted_at IS NULL",
				$name,
				$category_id
			)
		);

		if ( $duplicate ) {
			return new WP_Error(
				'duplicate_name',
				'A category with this name already exists.',
				array( 'status' => 409 )
			);
		}

		// Update category.
		$result = $wpdb->update(
			$wpdb->prefix . 'bookings_categories',
			array(
				'name'          => $name,
				'description'   => $description,
				'is_active'     => $is_active ? 1 : 0,
				'display_order' => $display_order,
				'updated_at'    => current_time( 'mysql' ),
			),
			array( 'id' => $category_id ),
			array( '%s', '%s', '%d', '%d', '%s' ),
			array( '%d' )
		);

		if ( false === $result ) {
			return new WP_Error(
				'update_failed',
				'Failed to update category.',
				array( 'status' => 500 )
			);
		}

		// Get updated category with service count.
		$category = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT c.*,
					COUNT(DISTINCT sc.service_id) as service_count
				FROM {$wpdb->prefix}bookings_categories c
				LEFT JOIN {$wpdb->prefix}bookings_service_categories sc ON c.id = sc.category_id
				WHERE c.id = %d
				GROUP BY c.id",
				$category_id
			),
			ARRAY_A
		);

		return rest_ensure_response(
			array(
				'success'  => true,
				'message'  => 'Category updated successfully.',
				'category' => $category,
			)
		);
	}

	/**
	 * Delete category (soft delete).
	 * Shows confirmation with service count.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response|WP_Error
	 */
	public function delete_category( $request ) {
		global $wpdb;

		$category_id = (int) $request->get_param( 'id' );

		// Check if category exists.
		$existing = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT id, name FROM {$wpdb->prefix}bookings_categories 
				WHERE id = %d AND deleted_at IS NULL",
				$category_id
			),
			ARRAY_A
		);

		if ( ! $existing ) {
			return new WP_Error(
				'category_not_found',
				'Category not found.',
				array( 'status' => 404 )
			);
		}

		// Get service count.
		$service_count = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(DISTINCT service_id) 
				FROM {$wpdb->prefix}bookings_service_categories 
				WHERE category_id = %d",
				$category_id
			)
		);

		// Soft delete the category.
		$result = $wpdb->update(
			$wpdb->prefix . 'bookings_categories',
			array(
				'deleted_at' => current_time( 'mysql' ),
				'updated_at' => current_time( 'mysql' ),
			),
			array( 'id' => $category_id ),
			array( '%s', '%s' ),
			array( '%d' )
		);

		if ( false === $result ) {
			return new WP_Error(
				'deletion_failed',
				'Failed to delete category.',
				array( 'status' => 500 )
			);
		}

		$message = 'Category deleted successfully.';
		if ( $service_count > 0 ) {
			$message .= sprintf( ' %d service(s) are no longer in this category.', $service_count );
		}

		return rest_ensure_response(
			array(
				'success'       => true,
				'message'       => $message,
				'service_count' => (int) $service_count,
			)
		);
	}

	/**
	 * Reorder categories.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response|WP_Error
	 */
	public function reorder_categories( $request ) {
		global $wpdb;

		$categories = $request->get_param( 'categories' );

		if ( empty( $categories ) ) {
			return new WP_Error(
				'invalid_data',
				'Categories array is required.',
				array( 'status' => 400 )
			);
		}

		// Update display order for each category.
		foreach ( $categories as $category_data ) {
			if ( ! isset( $category_data['id'] ) || ! isset( $category_data['display_order'] ) ) {
				continue;
			}

			$wpdb->update(
				$wpdb->prefix . 'bookings_categories',
				array(
					'display_order' => (int) $category_data['display_order'],
					'updated_at'    => current_time( 'mysql' ),
				),
				array( 'id' => (int) $category_data['id'] ),
				array( '%d', '%s' ),
				array( '%d' )
			);
		}

		return rest_ensure_response(
			array(
				'success' => true,
				'message' => 'Categories reordered successfully.',
			)
		);
	}

	/**
	 * Create new service.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response|WP_Error
	 */
	public function create_service( $request ) {
		global $wpdb;

		// Get parameters.
		$name           = $request->get_param( 'name' );
		$description    = $request->get_param( 'description' );
		$duration       = (int) $request->get_param( 'duration' );
		$price          = (float) $request->get_param( 'price' );
		$deposit_amount = $request->get_param( 'deposit_amount' );
		$deposit_type   = $request->get_param( 'deposit_type' ) ?: 'fixed';
		$buffer_before  = (int) $request->get_param( 'buffer_before' );
		$buffer_after   = (int) $request->get_param( 'buffer_after' );
		$category_ids   = $request->get_param( 'category_ids' ) ?: array();
		$is_active      = filter_var( $request->get_param( 'is_active' ), FILTER_VALIDATE_BOOLEAN );
		$display_order  = (int) $request->get_param( 'display_order' );

		// Insert service.
		$result = $wpdb->insert(
			$wpdb->prefix . 'bookings_services',
			array(
				'name'           => $name,
				'description'    => $description,
				'duration'       => $duration,
				'price'          => $price,
				'deposit_amount' => $deposit_amount,
				'deposit_type'   => $deposit_type,
				'buffer_before'  => $buffer_before,
				'buffer_after'   => $buffer_after,
				'is_active'      => $is_active ? 1 : 0,
				'display_order'  => $display_order,
				'created_at'     => current_time( 'mysql' ),
				'updated_at'     => current_time( 'mysql' ),
			),
			array( '%s', '%s', '%d', '%f', '%f', '%s', '%d', '%d', '%d', '%d', '%s', '%s' )
		);

		if ( false === $result ) {
			return new WP_Error(
				'creation_failed',
				'Failed to create service.',
				array( 'status' => 500 )
			);
		}

		$service_id = $wpdb->insert_id;

		// Insert category relationships.
		if ( ! empty( $category_ids ) ) {
			foreach ( $category_ids as $category_id ) {
				$wpdb->insert(
					$wpdb->prefix . 'bookings_service_categories',
					array(
						'service_id'  => $service_id,
						'category_id' => (int) $category_id,
						'created_at'  => current_time( 'mysql' ),
					),
					array( '%d', '%d', '%s' )
				);
			}
		}

		// Get created service with categories.
		$service = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT s.* FROM {$wpdb->prefix}bookings_services s WHERE s.id = %d",
				$service_id
			),
			ARRAY_A
		);

		// Get categories.
		$categories = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT c.id, c.name
				FROM {$wpdb->prefix}bookings_categories c
				INNER JOIN {$wpdb->prefix}bookings_service_categories sc ON c.id = sc.category_id
				WHERE sc.service_id = %d
				AND c.deleted_at IS NULL",
				$service_id
			),
			ARRAY_A
		);

		$service['categories']   = $categories;
		$service['category_ids'] = array_column( $categories, 'id' );

		return rest_ensure_response(
			array(
				'success' => true,
				'message' => 'Service created successfully.',
				'service' => $service,
			)
		);
	}

	/**
	 * Update existing service.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response|WP_Error
	 */
	public function update_service( $request ) {
		global $wpdb;

		$service_id = (int) $request->get_param( 'id' );

		// Check if service exists.
		$existing = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT id FROM {$wpdb->prefix}bookings_services WHERE id = %d AND deleted_at IS NULL",
				$service_id
			)
		);

		if ( ! $existing ) {
			return new WP_Error(
				'service_not_found',
				'Service not found.',
				array( 'status' => 404 )
			);
		}

		// Get parameters.
		$name           = $request->get_param( 'name' );
		$description    = $request->get_param( 'description' );
		$duration       = (int) $request->get_param( 'duration' );
		$price          = (float) $request->get_param( 'price' );
		$deposit_amount = $request->get_param( 'deposit_amount' );
		$deposit_type   = $request->get_param( 'deposit_type' ) ?: 'fixed';
		$buffer_before  = (int) $request->get_param( 'buffer_before' );
		$buffer_after   = (int) $request->get_param( 'buffer_after' );
		$category_ids   = $request->get_param( 'category_ids' ) ?: array();
		$is_active      = filter_var( $request->get_param( 'is_active' ), FILTER_VALIDATE_BOOLEAN );
		$display_order  = (int) $request->get_param( 'display_order' );

		// Update service.
		$result = $wpdb->update(
			$wpdb->prefix . 'bookings_services',
			array(
				'name'           => $name,
				'description'    => $description,
				'duration'       => $duration,
				'price'          => $price,
				'deposit_amount' => $deposit_amount,
				'deposit_type'   => $deposit_type,
				'buffer_before'  => $buffer_before,
				'buffer_after'   => $buffer_after,
				'is_active'      => $is_active ? 1 : 0,
				'display_order'  => $display_order,
				'updated_at'     => current_time( 'mysql' ),
			),
			array( 'id' => $service_id ),
			array( '%s', '%s', '%d', '%f', '%f', '%s', '%d', '%d', '%d', '%d', '%s' ),
			array( '%d' )
		);

		if ( false === $result ) {
			return new WP_Error(
				'update_failed',
				'Failed to update service.',
				array( 'status' => 500 )
			);
		}

		// Delete existing category relationships.
		$wpdb->delete(
			$wpdb->prefix . 'bookings_service_categories',
			array( 'service_id' => $service_id ),
			array( '%d' )
		);

		// Insert new category relationships.
		if ( ! empty( $category_ids ) ) {
			foreach ( $category_ids as $category_id ) {
				$wpdb->insert(
					$wpdb->prefix . 'bookings_service_categories',
					array(
						'service_id'  => $service_id,
						'category_id' => (int) $category_id,
						'created_at'  => current_time( 'mysql' ),
					),
					array( '%d', '%d', '%s' )
				);
			}
		}

		// Get updated service with categories.
		$service = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT s.* FROM {$wpdb->prefix}bookings_services s WHERE s.id = %d",
				$service_id
			),
			ARRAY_A
		);

		// Get categories.
		$categories = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT c.id, c.name
				FROM {$wpdb->prefix}bookings_categories c
				INNER JOIN {$wpdb->prefix}bookings_service_categories sc ON c.id = sc.category_id
				WHERE sc.service_id = %d
				AND c.deleted_at IS NULL",
				$service_id
			),
			ARRAY_A
		);

		$service['categories']   = $categories;
		$service['category_ids'] = array_column( $categories, 'id' );

		return rest_ensure_response(
			array(
				'success' => true,
				'message' => 'Service updated successfully.',
				'service' => $service,
			)
		);
	}

	/**
	 * Delete service (soft delete).
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response|WP_Error
	 */
	public function delete_service( $request ) {
		global $wpdb;

		$service_id = (int) $request->get_param( 'id' );

		// Check if service exists.
		$existing = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT id, name FROM {$wpdb->prefix}bookings_services WHERE id = %d AND deleted_at IS NULL",
				$service_id
			),
			ARRAY_A
		);

		if ( ! $existing ) {
			return new WP_Error(
				'service_not_found',
				'Service not found.',
				array( 'status' => 404 )
			);
		}

		// Check if service has future bookings.
		$future_bookings = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$wpdb->prefix}bookings
				WHERE service_id = %d
				AND booking_date >= CURDATE()
				AND deleted_at IS NULL
				AND status NOT IN ('cancelled', 'no_show')",
				$service_id
			)
		);

		if ( $future_bookings > 0 ) {
			return new WP_Error(
				'service_has_bookings',
				sprintf(
					'Cannot delete service "%s" because it has %d future booking(s). Please cancel or complete these bookings first, or deactivate the service instead.',
					$existing['name'],
					$future_bookings
				),
				array( 'status' => 409 )
			);
		}

		// Soft delete the service.
		$result = $wpdb->update(
			$wpdb->prefix . 'bookings_services',
			array(
				'deleted_at' => current_time( 'mysql' ),
				'updated_at' => current_time( 'mysql' ),
			),
			array( 'id' => $service_id ),
			array( '%s', '%s' ),
			array( '%d' )
		);

		if ( false === $result ) {
			return new WP_Error(
				'deletion_failed',
				'Failed to delete service.',
				array( 'status' => 500 )
			);
		}

		return rest_ensure_response(
			array(
				'success' => true,
				'message' => 'Service deleted successfully.',
			)
		);
	}

	/**
	 * Update display order for multiple services.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response|WP_Error
	 */
	public function reorder_services( $request ) {
		global $wpdb;

		$services = $request->get_param( 'services' );

		if ( empty( $services ) ) {
			return new WP_Error(
				'invalid_data',
				'Services array is required.',
				array( 'status' => 400 )
			);
		}

		// Update display order for each service.
		foreach ( $services as $service_data ) {
			if ( ! isset( $service_data['id'] ) || ! isset( $service_data['display_order'] ) ) {
				continue;
			}

			$wpdb->update(
				$wpdb->prefix . 'bookings_services',
				array(
					'display_order' => (int) $service_data['display_order'],
					'updated_at'    => current_time( 'mysql' ),
				),
				array( 'id' => (int) $service_data['id'] ),
				array( '%d', '%s' ),
				array( '%d' )
			);
		}

		return rest_ensure_response(
			array(
				'success' => true,
				'message' => 'Services reordered successfully.',
			)
		);
	}

	/**
	 * Get weekly working hours schedule for a staff member.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response|WP_Error
	 */
	public function get_working_hours( $request ) {
		global $wpdb;

		$staff_id = (int) $request->get_param( 'staff_id' );

		// Verify staff exists.
		$staff = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT id, first_name, last_name
				FROM {$wpdb->prefix}bookings_staff
				WHERE id = %d AND deleted_at IS NULL",
				$staff_id
			),
			ARRAY_A
		);

		if ( ! $staff ) {
			return new WP_Error(
				'staff_not_found',
				'Staff member not found.',
				array( 'status' => 404 )
			);
		}

		// Get weekly recurring schedule (day_of_week patterns).
		$weekly_schedule = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT
					id,
					day_of_week,
					start_time,
					end_time,
					is_working,
					break_start,
					break_end,
					repeat_weekly,
					valid_from,
					valid_until,
					notes
				FROM {$wpdb->prefix}bookings_staff_working_hours
				WHERE staff_id = %d
				AND day_of_week IS NOT NULL
				AND specific_date IS NULL
				ORDER BY day_of_week ASC, start_time ASC",
				$staff_id
			),
			ARRAY_A
		);

		// Build structured schedule by day (1-7).
		$schedule = array();
		for ( $day = 1; $day <= 7; $day++ ) {
			$day_rows = array_filter(
				$weekly_schedule,
				function ( $row ) use ( $day ) {
					return (int) $row['day_of_week'] === $day;
				}
			);

			if ( empty( $day_rows ) ) {
				// Day has no configuration = day off.
				$schedule[ $day ] = array(
					'day_of_week' => $day,
					'is_working'  => false,
					'records'     => array(),
				);
			} else {
				$day_rows = array_values( $day_rows );

				// Check if any record marks as working.
				$is_working = false;
				foreach ( $day_rows as $row ) {
					if ( (int) $row['is_working'] === 1 ) {
						$is_working = true;
						break;
					}
				}

				$schedule[ $day ] = array(
					'day_of_week' => $day,
					'is_working'  => $is_working,
					'records'     => array_map(
						function ( $row ) {
							return array(
								'id'            => (int) $row['id'],
								'start_time'    => $row['start_time'],
								'end_time'      => $row['end_time'],
								'is_working'    => (bool) $row['is_working'],
								'break_start'   => $row['break_start'],
								'break_end'     => $row['break_end'],
								'repeat_weekly' => (bool) $row['repeat_weekly'],
								'valid_from'    => $row['valid_from'],
								'valid_until'   => $row['valid_until'],
								'notes'         => $row['notes'],
							);
						},
						$day_rows
					),
				);
			}
		}

		return rest_ensure_response(
			array(
				'success'  => true,
				'staff'    => array(
					'id'         => (int) $staff['id'],
					'first_name' => $staff['first_name'],
					'last_name'  => $staff['last_name'],
					'full_name'  => $staff['first_name'] . ' ' . $staff['last_name'],
				),
				'schedule' => $schedule,
			)
		);
	}

	/**
	 * Save weekly working hours schedule for a staff member.
	 * Replaces all existing day_of_week records (not exceptions).
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response|WP_Error
	 */
	public function save_working_hours( $request ) {
		global $wpdb;

		$staff_id = (int) $request->get_param( 'staff_id' );
		$schedule = $request->get_param( 'schedule' );

		// Verify staff exists.
		$staff = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT id FROM {$wpdb->prefix}bookings_staff
				WHERE id = %d AND deleted_at IS NULL",
				$staff_id
			)
		);

		if ( ! $staff ) {
			return new WP_Error(
				'staff_not_found',
				'Staff member not found.',
				array( 'status' => 404 )
			);
		}

		// Delete existing weekly schedule (keep specific_date exceptions).
		$wpdb->query(
			$wpdb->prepare(
				"DELETE FROM {$wpdb->prefix}bookings_staff_working_hours
				WHERE staff_id = %d
				AND day_of_week IS NOT NULL
				AND specific_date IS NULL",
				$staff_id
			)
		);

		// Insert new schedule records.
		$inserted = 0;
		foreach ( $schedule as $day_data ) {
			$day_of_week = (int) ( $day_data['day_of_week'] ?? 0 );
			$is_working  = filter_var( $day_data['is_working'] ?? false, FILTER_VALIDATE_BOOLEAN );

			// Validate day_of_week.
			if ( $day_of_week < 1 || $day_of_week > 7 ) {
				continue;
			}

			// Skip days marked as not working (no record needed = day off).
			if ( ! $is_working ) {
				continue;
			}

			// Validate required times.
			$start_time = sanitize_text_field( $day_data['start_time'] ?? '' );
			$end_time   = sanitize_text_field( $day_data['end_time'] ?? '' );

			if ( empty( $start_time ) || empty( $end_time ) ) {
				continue;
			}

			// Validate time format (H:i or H:i:s).
			if ( ! preg_match( '/^\d{2}:\d{2}(:\d{2})?$/', $start_time ) ||
				! preg_match( '/^\d{2}:\d{2}(:\d{2})?$/', $end_time ) ) {
				continue;
			}

			// Ensure seconds included.
			if ( strlen( $start_time ) === 5 ) {
				$start_time .= ':00';
			}
			if ( strlen( $end_time ) === 5 ) {
				$end_time .= ':00';
			}

			// Validate start < end.
			if ( strtotime( $start_time ) >= strtotime( $end_time ) ) {
				continue;
			}

			// Break times.
			$break_start = ! empty( $day_data['break_start'] ) ? sanitize_text_field( $day_data['break_start'] ) : null;
			$break_end   = ! empty( $day_data['break_end'] ) ? sanitize_text_field( $day_data['break_end'] ) : null;

			// Validate break if provided.
			if ( $break_start && $break_end ) {
				if ( strlen( $break_start ) === 5 ) {
					$break_start .= ':00';
				}
				if ( strlen( $break_end ) === 5 ) {
					$break_end .= ':00';
				}
				// Break must be within working hours.
				if ( strtotime( $break_start ) <= strtotime( $start_time ) ||
					strtotime( $break_end ) >= strtotime( $end_time ) ||
					strtotime( $break_start ) >= strtotime( $break_end ) ) {
					$break_start = null;
					$break_end   = null;
				}
			} else {
				$break_start = null;
				$break_end   = null;
			}

			// Seasonal schedule.
			$valid_from  = ! empty( $day_data['valid_from'] ) ? sanitize_text_field( $day_data['valid_from'] ) : null;
			$valid_until = ! empty( $day_data['valid_until'] ) ? sanitize_text_field( $day_data['valid_until'] ) : null;

			$result = $wpdb->insert(
				$wpdb->prefix . 'bookings_staff_working_hours',
				array(
					'staff_id'      => $staff_id,
					'day_of_week'   => $day_of_week,
					'specific_date' => null,
					'start_time'    => $start_time,
					'end_time'      => $end_time,
					'is_working'    => 1,
					'break_start'   => $break_start,
					'break_end'     => $break_end,
					'repeat_weekly' => 1,
					'valid_from'    => $valid_from,
					'valid_until'   => $valid_until,
					'notes'         => null,
				),
				array( '%d', '%d', '%s', '%s', '%s', '%d', '%s', '%s', '%d', '%s', '%s', '%s' )
			);

			if ( false !== $result ) {
				$inserted++;
			}
		}

		// Return updated schedule.
		$get_request = new WP_REST_Request( 'GET' );
		$get_request->set_param( 'staff_id', $staff_id );
		$response = $this->get_working_hours( $get_request );

		return rest_ensure_response(
			array(
				'success'  => true,
				'message'  => sprintf( 'Working hours saved. %d day(s) configured.', $inserted ),
				'schedule' => $response->data['schedule'],
			)
		);
	}

	/**
	 * Get date exceptions for a staff member.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response|WP_Error
	 */
	public function get_exceptions( $request ) {
		global $wpdb;

		$staff_id = (int) $request->get_param( 'staff_id' );

		// Verify staff exists.
		$staff = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT id FROM {$wpdb->prefix}bookings_staff
				WHERE id = %d AND deleted_at IS NULL",
				$staff_id
			)
		);

		if ( ! $staff ) {
			return new WP_Error(
				'staff_not_found',
				'Staff member not found.',
				array( 'status' => 404 )
			);
		}

		// Get exceptions (specific dates).
		$exceptions = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT
					id,
					specific_date,
					start_time,
					end_time,
					is_working,
					break_start,
					break_end,
					notes,
					created_at
				FROM {$wpdb->prefix}bookings_staff_working_hours
				WHERE staff_id = %d
				AND specific_date IS NOT NULL
				AND day_of_week IS NULL
				ORDER BY specific_date ASC",
				$staff_id
			),
			ARRAY_A
		);

		// Convert types.
		foreach ( $exceptions as &$exception ) {
			$exception['id']         = (int) $exception['id'];
			$exception['is_working'] = (bool) $exception['is_working'];
		}

		return rest_ensure_response(
			array(
				'success'    => true,
				'exceptions' => $exceptions,
			)
		);
	}

	/**
	 * Add a specific date exception for a staff member.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response|WP_Error
	 */
	public function add_exception( $request ) {
		global $wpdb;

		$staff_id      = (int) $request->get_param( 'staff_id' );
		$specific_date = $request->get_param( 'specific_date' );
		$is_working    = filter_var( $request->get_param( 'is_working' ), FILTER_VALIDATE_BOOLEAN );

		// Verify staff exists.
		$staff = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT id FROM {$wpdb->prefix}bookings_staff
				WHERE id = %d AND deleted_at IS NULL",
				$staff_id
			)
		);

		if ( ! $staff ) {
			return new WP_Error(
				'staff_not_found',
				'Staff member not found.',
				array( 'status' => 404 )
			);
		}

		// Validate date format.
		if ( ! preg_match( '/^\d{4}-\d{2}-\d{2}$/', $specific_date ) ) {
			return new WP_Error(
				'invalid_date',
				'Invalid date format. Use Y-m-d.',
				array( 'status' => 400 )
			);
		}

		// Check for duplicate exception on same date.
		$existing = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT id FROM {$wpdb->prefix}bookings_staff_working_hours
				WHERE staff_id = %d
				AND specific_date = %s",
				$staff_id,
				$specific_date
			)
		);

		if ( $existing ) {
			return new WP_Error(
				'duplicate_exception',
				'An exception already exists for this date. Delete the existing one first.',
				array( 'status' => 409 )
			);
		}

		// Prepare time fields.
		$start_time  = null;
		$end_time    = null;
		$break_start = null;
		$break_end   = null;

		if ( $is_working ) {
			$start_time = sanitize_text_field( $request->get_param( 'start_time' ) ?? '' );
			$end_time   = sanitize_text_field( $request->get_param( 'end_time' ) ?? '' );

			if ( empty( $start_time ) || empty( $end_time ) ) {
				return new WP_Error(
					'missing_times',
					'Start time and end time are required when is_working is true.',
					array( 'status' => 400 )
				);
			}

			// Ensure seconds.
			if ( strlen( $start_time ) === 5 ) {
				$start_time .= ':00';
			}
			if ( strlen( $end_time ) === 5 ) {
				$end_time .= ':00';
			}

			// Break times.
			$break_start_raw = $request->get_param( 'break_start' );
			$break_end_raw   = $request->get_param( 'break_end' );

			if ( ! empty( $break_start_raw ) && ! empty( $break_end_raw ) ) {
				$break_start = sanitize_text_field( $break_start_raw );
				$break_end   = sanitize_text_field( $break_end_raw );

				if ( strlen( $break_start ) === 5 ) {
					$break_start .= ':00';
				}
				if ( strlen( $break_end ) === 5 ) {
					$break_end .= ':00';
				}
			}
		}

		// Notes.
		$notes = $request->get_param( 'notes' ) ? sanitize_textarea_field( $request->get_param( 'notes' ) ) : null;

		// Insert exception.
		$result = $wpdb->insert(
			$wpdb->prefix . 'bookings_staff_working_hours',
			array(
				'staff_id'      => $staff_id,
				'day_of_week'   => null,
				'specific_date' => $specific_date,
				'start_time'    => $is_working ? $start_time : '00:00:00',
				'end_time'      => $is_working ? $end_time : '00:00:00',
				'is_working'    => $is_working ? 1 : 0,
				'break_start'   => $break_start,
				'break_end'     => $break_end,
				'repeat_weekly' => 0,
				'valid_from'    => null,
				'valid_until'   => null,
				'notes'         => $notes,
			),
			array( '%d', '%s', '%s', '%s', '%s', '%d', '%s', '%s', '%d', '%s', '%s', '%s' )
		);

		if ( false === $result ) {
			return new WP_Error(
				'insert_failed',
				'Failed to add exception.',
				array( 'status' => 500 )
			);
		}

		$exception_id = $wpdb->insert_id;

		return rest_ensure_response(
			array(
				'success'   => true,
				'message'   => 'Exception added successfully.',
				'exception' => array(
					'id'            => $exception_id,
					'specific_date' => $specific_date,
					'is_working'    => $is_working,
					'start_time'    => $is_working ? $start_time : null,
					'end_time'      => $is_working ? $end_time : null,
					'break_start'   => $break_start,
					'break_end'     => $break_end,
					'notes'         => $notes,
				),
			)
		);
	}

	/**
	 * Delete a specific date exception.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response|WP_Error
	 */
	public function delete_exception( $request ) {
		global $wpdb;

		$staff_id     = (int) $request->get_param( 'staff_id' );
		$exception_id = (int) $request->get_param( 'id' );

		// Verify exception belongs to this staff member.
		$exception = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT id, specific_date FROM {$wpdb->prefix}bookings_staff_working_hours
				WHERE id = %d
				AND staff_id = %d
				AND specific_date IS NOT NULL",
				$exception_id,
				$staff_id
			),
			ARRAY_A
		);

		if ( ! $exception ) {
			return new WP_Error(
				'exception_not_found',
				'Exception not found.',
				array( 'status' => 404 )
			);
		}

		$result = $wpdb->delete(
			$wpdb->prefix . 'bookings_staff_working_hours',
			array( 'id' => $exception_id ),
			array( '%d' )
		);

		if ( false === $result ) {
			return new WP_Error(
				'delete_failed',
				'Failed to delete exception.',
				array( 'status' => 500 )
			);
		}

		return rest_ensure_response(
			array(
				'success' => true,
				'message' => 'Exception deleted successfully.',
			)
		);
	}

	/**
	 * Update a single working hours record.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response|WP_Error
	 */
	public function update_working_hours_record( $request ) {
		global $wpdb;

		$staff_id  = (int) $request->get_param( 'staff_id' );
		$record_id = (int) $request->get_param( 'id' );

		// Verify record belongs to staff.
		$record = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT id FROM {$wpdb->prefix}bookings_staff_working_hours
				WHERE id = %d AND staff_id = %d",
				$record_id,
				$staff_id
			)
		);

		if ( ! $record ) {
			return new WP_Error(
				'record_not_found',
				'Working hours record not found.',
				array( 'status' => 404 )
			);
		}

		// Build update data from request.
		$update_data   = array();
		$update_format = array();

		$fields = array(
			'start_time'  => '%s',
			'end_time'    => '%s',
			'break_start' => '%s',
			'break_end'   => '%s',
			'valid_from'  => '%s',
			'valid_until' => '%s',
			'notes'       => '%s',
		);

		foreach ( $fields as $field => $format ) {
			$value = $request->get_param( $field );
			if ( null !== $value ) {
				$update_data[ $field ] = sanitize_text_field( $value );
				$update_format[]       = $format;
			}
		}

		if ( null !== $request->get_param( 'is_working' ) ) {
			$update_data['is_working'] = filter_var( $request->get_param( 'is_working' ), FILTER_VALIDATE_BOOLEAN ) ? 1 : 0;
			$update_format[]           = '%d';
		}

		if ( empty( $update_data ) ) {
			return new WP_Error(
				'no_data',
				'No fields to update.',
				array( 'status' => 400 )
			);
		}

		$result = $wpdb->update(
			$wpdb->prefix . 'bookings_staff_working_hours',
			$update_data,
			array( 'id' => $record_id ),
			$update_format,
			array( '%d' )
		);

		if ( false === $result ) {
			return new WP_Error(
				'update_failed',
				'Failed to update working hours.',
				array( 'status' => 500 )
			);
		}

		return rest_ensure_response(
			array(
				'success' => true,
				'message' => 'Working hours updated successfully.',
			)
		);
	}

	/**
	 * Delete a working hours record.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response|WP_Error
	 */
	public function delete_working_hours_record( $request ) {
		global $wpdb;

		$staff_id  = (int) $request->get_param( 'staff_id' );
		$record_id = (int) $request->get_param( 'id' );

		// Verify record belongs to staff.
		$record = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT id FROM {$wpdb->prefix}bookings_staff_working_hours
				WHERE id = %d AND staff_id = %d",
				$record_id,
				$staff_id
			)
		);

		if ( ! $record ) {
			return new WP_Error(
				'record_not_found',
				'Working hours record not found.',
				array( 'status' => 404 )
			);
		}

		$result = $wpdb->delete(
			$wpdb->prefix . 'bookings_staff_working_hours',
			array( 'id' => $record_id ),
			array( '%d' )
		);

		if ( false === $result ) {
			return new WP_Error(
				'delete_failed',
				'Failed to delete working hours record.',
				array( 'status' => 500 )
			);
		}

		return rest_ensure_response(
			array(
				'success' => true,
				'message' => 'Working hours record deleted successfully.',
			)
		);
	}

	/**
	 * Check for conflicts before bulk working hours operation.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response
	 */
	public function check_bulk_conflicts( $request ) {
		global $wpdb;

		$staff_ids     = $request->get_param( 'staff_ids' );
		$specific_date = $request->get_param( 'specific_date' );

		if ( empty( $staff_ids ) ) {
			return rest_ensure_response(
				array(
					'success'   => true,
					'conflicts' => array(),
				)
			);
		}

		$conflicts = array();

		if ( $specific_date ) {
			$placeholders = implode( ',', array_fill( 0, count( $staff_ids ), '%d' ) );

			$existing = $wpdb->get_results(
				$wpdb->prepare(
					"SELECT
						h.staff_id,
						h.id as exception_id,
						h.specific_date,
						h.is_working,
						h.start_time,
						h.end_time,
						h.notes,
						s.first_name,
						s.last_name
					FROM {$wpdb->prefix}bookings_staff_working_hours h
					INNER JOIN {$wpdb->prefix}bookings_staff s ON h.staff_id = s.id
					WHERE h.staff_id IN ($placeholders)
					AND h.specific_date = %s",
					array_merge( $staff_ids, array( $specific_date ) )
				),
				ARRAY_A
			);

			foreach ( $existing as $row ) {
				$conflicts[] = array(
					'staff_id'      => (int) $row['staff_id'],
					'staff_name'    => $row['first_name'] . ' ' . $row['last_name'],
					'exception_id'  => (int) $row['exception_id'],
					'specific_date' => $row['specific_date'],
					'is_working'    => (bool) $row['is_working'],
					'start_time'    => $row['start_time'],
					'end_time'      => $row['end_time'],
					'notes'         => $row['notes'],
					'conflict_type' => 'exception',
				);
			}
		}

		return rest_ensure_response(
			array(
				'success'   => true,
				'conflicts' => $conflicts,
			)
		);
	}

	/**
	 * Add exception to multiple staff.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response|WP_Error
	 */
	public function bulk_add_exception( $request ) {
		global $wpdb;

		$staff_ids           = $request->get_param( 'staff_ids' );
		$specific_date       = $request->get_param( 'specific_date' );
		$is_working          = filter_var( $request->get_param( 'is_working' ), FILTER_VALIDATE_BOOLEAN );
		$overwrite_conflicts = $request->get_param( 'overwrite_conflicts' ) ?: array();

		if ( ! preg_match( '/^\d{4}-\d{2}-\d{2}$/', $specific_date ) ) {
			return new WP_Error(
				'invalid_date',
				'Invalid date format. Use Y-m-d.',
				array( 'status' => 400 )
			);
		}

		$start_time  = null;
		$end_time    = null;
		$break_start = null;
		$break_end   = null;
		$notes       = $request->get_param( 'notes' ) ? sanitize_textarea_field( $request->get_param( 'notes' ) ) : null;

		if ( $is_working ) {
			$start_time = sanitize_text_field( $request->get_param( 'start_time' ) ?? '' );
			$end_time   = sanitize_text_field( $request->get_param( 'end_time' ) ?? '' );

			if ( empty( $start_time ) || empty( $end_time ) ) {
				return new WP_Error(
					'missing_times',
					'Start time and end time are required when is_working is true.',
					array( 'status' => 400 )
				);
			}

			if ( strlen( $start_time ) === 5 ) {
				$start_time .= ':00';
			}
			if ( strlen( $end_time ) === 5 ) {
				$end_time .= ':00';
			}

			$break_start_raw = $request->get_param( 'break_start' );
			$break_end_raw   = $request->get_param( 'break_end' );

			if ( ! empty( $break_start_raw ) && ! empty( $break_end_raw ) ) {
				$break_start = sanitize_text_field( $break_start_raw );
				$break_end   = sanitize_text_field( $break_end_raw );

				if ( strlen( $break_start ) === 5 ) {
					$break_start .= ':00';
				}
				if ( strlen( $break_end ) === 5 ) {
					$break_end .= ':00';
				}
			}
		}

		$added   = 0;
		$skipped = 0;
		$results = array();

		foreach ( $staff_ids as $staff_id ) {
			$existing = $wpdb->get_var(
				$wpdb->prepare(
					"SELECT id FROM {$wpdb->prefix}bookings_staff_working_hours
					WHERE staff_id = %d AND specific_date = %s",
					$staff_id,
					$specific_date
				)
			);

			if ( $existing ) {
				if ( in_array( $staff_id, $overwrite_conflicts ) ) {
					$wpdb->delete(
						$wpdb->prefix . 'bookings_staff_working_hours',
						array( 'id' => $existing ),
						array( '%d' )
					);
				} else {
					$skipped++;
					$results[] = array(
						'staff_id' => $staff_id,
						'status'   => 'skipped',
						'reason'   => 'conflict',
					);
					continue;
				}
			}

			$result = $wpdb->insert(
				$wpdb->prefix . 'bookings_staff_working_hours',
				array(
					'staff_id'      => $staff_id,
					'day_of_week'   => null,
					'specific_date' => $specific_date,
					'start_time'    => $is_working ? $start_time : '00:00:00',
					'end_time'      => $is_working ? $end_time : '00:00:00',
					'is_working'    => $is_working ? 1 : 0,
					'break_start'   => $break_start,
					'break_end'     => $break_end,
					'repeat_weekly' => 0,
					'valid_from'    => null,
					'valid_until'   => null,
					'notes'         => $notes,
				),
				array( '%d', '%s', '%s', '%s', '%s', '%d', '%s', '%s', '%d', '%s', '%s', '%s' )
			);

			if ( false !== $result ) {
				$added++;
				$results[] = array(
					'staff_id' => $staff_id,
					'status'   => 'added',
				);
			} else {
				$results[] = array(
					'staff_id' => $staff_id,
					'status'   => 'failed',
				);
			}
		}

		return rest_ensure_response(
			array(
				'success' => true,
				'message' => sprintf(
					'Exception added to %d staff member(s). %d skipped due to conflicts.',
					$added,
					$skipped
				),
				'added'   => $added,
				'skipped' => $skipped,
				'results' => $results,
			)
		);
	}

	/**
	 * Update schedule for multiple staff.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response|WP_Error
	 */
	public function bulk_update_schedule( $request ) {
		global $wpdb;

		$staff_ids   = $request->get_param( 'staff_ids' );
		$day_of_week = (int) $request->get_param( 'day_of_week' );
		$updates     = $request->get_param( 'updates' );

		if ( $day_of_week < 1 || $day_of_week > 7 ) {
			return new WP_Error(
				'invalid_day',
				'Day of week must be between 1 (Monday) and 7 (Sunday).',
				array( 'status' => 400 )
			);
		}

		if ( empty( $updates ) ) {
			return new WP_Error(
				'no_updates',
				'No updates provided.',
				array( 'status' => 400 )
			);
		}

		$updated = 0;
		$results = array();

		$allowed_fields = array(
			'start_time'  => '%s',
			'end_time'    => '%s',
			'break_start' => '%s',
			'break_end'   => '%s',
			'is_working'  => '%d',
		);

		foreach ( $staff_ids as $staff_id ) {
			$existing = $wpdb->get_row(
				$wpdb->prepare(
					"SELECT * FROM {$wpdb->prefix}bookings_staff_working_hours
					WHERE staff_id = %d
					AND day_of_week = %d
					AND specific_date IS NULL
					LIMIT 1",
					$staff_id,
					$day_of_week
				),
				ARRAY_A
			);

			if ( ! $existing ) {
				$results[] = array(
					'staff_id' => $staff_id,
					'status'   => 'skipped',
					'reason'   => 'no_schedule',
				);
				continue;
			}

			$update_data   = array();
			$update_format = array();

			foreach ( $allowed_fields as $field => $format ) {
				if ( isset( $updates[ $field ] ) ) {
					$value = $updates[ $field ];

					if ( 'is_working' === $field ) {
						$update_data[ $field ] = filter_var( $value, FILTER_VALIDATE_BOOLEAN ) ? 1 : 0;
					} else {
						$value = sanitize_text_field( $value );
						if ( in_array( $field, array( 'start_time', 'end_time', 'break_start', 'break_end' ), true ) && strlen( $value ) === 5 ) {
							$value .= ':00';
						}
						$update_data[ $field ] = $value;
					}

					$update_format[] = $format;
				}
			}

			if ( empty( $update_data ) ) {
				continue;
			}

			$result = $wpdb->update(
				$wpdb->prefix . 'bookings_staff_working_hours',
				$update_data,
				array(
					'staff_id'    => $staff_id,
					'day_of_week' => $day_of_week,
				),
				$update_format,
				array( '%d', '%d' )
			);

			if ( false !== $result ) {
				$updated++;
				$results[] = array(
					'staff_id' => $staff_id,
					'status'   => 'updated',
				);
			} else {
				$results[] = array(
					'staff_id' => $staff_id,
					'status'   => 'failed',
				);
			}
		}

		return rest_ensure_response(
			array(
				'success' => true,
				'message' => sprintf( 'Schedule updated for %d staff member(s).', $updated ),
				'updated' => $updated,
				'results' => $results,
			)
		);
	}

	/**
	 * Get current user's profile.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response|WP_Error
	 */
	public function get_my_profile( $request ) {
		global $wpdb;

		$current_staff = Bookit_Auth::get_current_staff();
		if ( ! $current_staff ) {
			return new WP_Error(
				'unauthorized',
				'Could not retrieve staff information.',
				array( 'status' => 401 )
			);
		}

		$staff = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT
					id, email, first_name, last_name, phone, photo_url, bio, title, role,
					google_calendar_connected, google_calendar_email
				FROM {$wpdb->prefix}bookings_staff
				WHERE id = %d AND deleted_at IS NULL",
				$current_staff['id']
			),
			ARRAY_A
		);

		if ( ! $staff ) {
			return new WP_Error(
				'profile_not_found',
				'Profile not found.',
				array( 'status' => 404 )
			);
		}

		$staff['id']        = (int) $staff['id'];
		$staff['full_name'] = $staff['first_name'] . ' ' . $staff['last_name'];

		$staff['google_calendar_connected'] = ! empty( $staff['google_calendar_connected'] );
		$staff['google_calendar_email']       = ! empty( $staff['google_calendar_email'] )
			? (string) $staff['google_calendar_email']
			: '';

		// Decode notification preferences with defaults.
		$raw_prefs = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT notification_preferences FROM {$wpdb->prefix}bookings_staff WHERE id = %d",
				$current_staff['id']
			)
		);
		$pref_defaults = array(
			'new_booking'    => 'immediate',
			'reschedule'     => 'immediate',
			'cancellation'   => 'immediate',
			'daily_schedule' => false,
		);
		$parsed                          = ! empty( $raw_prefs ) ? json_decode( $raw_prefs, true ) : null;
		$staff['notification_preferences'] = is_array( $parsed )
			? array_merge( $pref_defaults, $parsed )
			: $pref_defaults;

		return rest_ensure_response(
			array(
				'success' => true,
				'profile' => $staff,
			)
		);
	}

	/**
	 * Update current user's profile.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response|WP_Error
	 */
	public function update_my_profile( $request ) {
		global $wpdb;

		$current_staff = Bookit_Auth::get_current_staff();
		if ( ! $current_staff ) {
			return new WP_Error(
				'unauthorized',
				'Could not retrieve staff information.',
				array( 'status' => 401 )
			);
		}

		$staff_id = $current_staff['id'];

		$update_data   = array();
		$update_format = array();

		$fields = array(
			'first_name' => '%s',
			'last_name'  => '%s',
			'email'      => '%s',
			'phone'      => '%s',
			'title'      => '%s',
			'bio'        => '%s',
			'photo_url'  => '%s',
		);

		foreach ( $fields as $field => $format ) {
			$value = $request->get_param( $field );
			if ( null !== $value ) {
				$update_data[ $field ] = $value;
				$update_format[]       = $format;
			}
		}

		if ( empty( $update_data ) ) {
			return new WP_Error(
				'no_data',
				'No fields to update.',
				array( 'status' => 400 )
			);
		}

		if ( isset( $update_data['email'] ) ) {
			$duplicate = $wpdb->get_var(
				$wpdb->prepare(
					"SELECT id FROM {$wpdb->prefix}bookings_staff
					WHERE email = %s AND id != %d AND deleted_at IS NULL",
					$update_data['email'],
					$staff_id
				)
			);

			if ( $duplicate ) {
				return new WP_Error(
					'duplicate_email',
					'This email is already in use.',
					array( 'status' => 409 )
				);
			}
		}

		$update_data['updated_at'] = current_time( 'mysql' );
		$update_format[]           = '%s';

		$result = $wpdb->update(
			$wpdb->prefix . 'bookings_staff',
			$update_data,
			array( 'id' => $staff_id ),
			$update_format,
			array( '%d' )
		);

		if ( false === $result ) {
			return new WP_Error(
				'update_failed',
				'Failed to update profile.',
				array( 'status' => 500 )
			);
		}

		$profile_response = $this->get_my_profile( $request );

		return rest_ensure_response(
			array(
				'success' => true,
				'message' => 'Profile updated successfully.',
				'profile' => $profile_response->data['profile'],
			)
		);
	}

	/**
	 * Update notification preferences for current user.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response|WP_Error
	 */
	public function update_notification_preferences( WP_REST_Request $request ): WP_REST_Response|WP_Error {
		global $wpdb;

		$current_staff = Bookit_Auth::get_current_staff();
		if ( ! $current_staff ) {
			return new WP_Error( 'unauthorized', 'Could not retrieve staff information.', array( 'status' => 401 ) );
		}

		$defaults = array(
			'new_booking'    => 'immediate',
			'reschedule'     => 'immediate',
			'cancellation'   => 'immediate',
			'daily_schedule' => false,
		);

		$valid_frequencies = array( 'immediate', 'daily', 'weekly' );

		$new_booking  = $request->get_param( 'new_booking' );
		$reschedule   = $request->get_param( 'reschedule' );
		$cancellation = $request->get_param( 'cancellation' );
		$daily_sched  = $request->get_param( 'daily_schedule' );

		$prefs = array(
			'new_booking'    => in_array( $new_booking, $valid_frequencies, true ) ? $new_booking : $defaults['new_booking'],
			'reschedule'     => in_array( $reschedule, $valid_frequencies, true ) ? $reschedule : $defaults['reschedule'],
			'cancellation'   => in_array( $cancellation, $valid_frequencies, true ) ? $cancellation : $defaults['cancellation'],
			'daily_schedule' => null !== $daily_sched ? (bool) $daily_sched : $defaults['daily_schedule'],
		);

		$result = $wpdb->update(
			$wpdb->prefix . 'bookings_staff',
			array( 'notification_preferences' => wp_json_encode( $prefs ) ),
			array( 'id' => (int) $current_staff['id'] ),
			array( '%s' ),
			array( '%d' )
		);

		if ( false === $result ) {
			return new WP_Error( 'update_failed', 'Failed to save notification preferences.', array( 'status' => 500 ) );
		}

		return rest_ensure_response(
			array(
				'success'     => true,
				'preferences' => $prefs,
			)
		);
	}

	/**
	 * Change password for current user.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response|WP_Error
	 */
	public function change_password( $request ) {
		global $wpdb;

		$current_staff = Bookit_Auth::get_current_staff();
		if ( ! $current_staff ) {
			return new WP_Error(
				'unauthorized',
				'Could not retrieve staff information.',
				array( 'status' => 401 )
			);
		}

		$staff_id         = $current_staff['id'];
		$current_password = $request->get_param( 'current_password' );
		$new_password     = $request->get_param( 'new_password' );

		$current_hash = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT password_hash FROM {$wpdb->prefix}bookings_staff WHERE id = %d",
				$staff_id
			)
		);

		if ( ! $current_hash ) {
			return new WP_Error(
				'staff_not_found',
				'Staff member not found.',
				array( 'status' => 404 )
			);
		}

		if ( ! password_verify( $current_password, $current_hash ) ) {
			return new WP_Error(
				'invalid_password',
				'Current password is incorrect.',
				array( 'status' => 401 )
			);
		}

		$new_hash = password_hash( $new_password, PASSWORD_DEFAULT );

		$result = $wpdb->update(
			$wpdb->prefix . 'bookings_staff',
			array(
				'password_hash' => $new_hash,
				'updated_at'    => current_time( 'mysql' ),
			),
			array( 'id' => $staff_id ),
			array( '%s', '%s' ),
			array( '%d' )
		);

		if ( false === $result ) {
			return new WP_Error(
				'update_failed',
				'Failed to change password.',
				array( 'status' => 500 )
			);
		}

		return rest_ensure_response(
			array(
				'success' => true,
				'message' => 'Password changed successfully.',
			)
		);
	}

	/**
	 * Verify password for current user (used before email changes).
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response|WP_Error
	 */
	public function verify_password( $request ) {
		global $wpdb;

		$current_staff = Bookit_Auth::get_current_staff();
		if ( ! $current_staff ) {
			return new WP_Error(
				'unauthorized',
				'Could not retrieve staff information.',
				array( 'status' => 401 )
			);
		}

		$password = $request->get_param( 'password' );

		$current_hash = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT password_hash FROM {$wpdb->prefix}bookings_staff WHERE id = %d",
				$current_staff['id']
			)
		);

		if ( ! $current_hash ) {
			return new WP_Error(
				'staff_not_found',
				'Staff member not found.',
				array( 'status' => 404 )
			);
		}

		if ( ! password_verify( $password, $current_hash ) ) {
			return new WP_Error(
				'invalid_password',
				'Password is incorrect.',
				array( 'status' => 403 )
			);
		}

		return rest_ensure_response(
			array(
				'success' => true,
				'message' => 'Password verified.',
			)
		);
	}

	/**
	 * Logout current user by destroying session.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response
	 */
	public function logout( $request ) {
		if ( ! class_exists( 'Bookit_Session' ) ) {
			require_once plugin_dir_path( dirname( __FILE__ ) ) . 'class-bookit-session.php';
		}

		Bookit_Session::destroy();

		return rest_ensure_response(
			array(
				'success' => true,
				'message' => 'Logged out successfully.',
			)
		);
	}

	/**
	 * Get settings by keys.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response
	 */
	public function get_settings( $request ) {
		global $wpdb;

		$keys_param     = $request->get_param( 'keys' );
		$requested_keys = array();
		$allowed_keys   = $this->get_allowed_settings_keys();

		if ( $keys_param ) {
			$requested_keys = array_values(
				array_filter(
					array_map( 'sanitize_key', array_map( 'trim', explode( ',', $keys_param ) ) )
				)
			);
			$keys         = array_values( array_intersect( $requested_keys, $allowed_keys ) );
			$placeholders = implode( ',', array_fill( 0, count( $keys ), '%s' ) );

			if ( empty( $keys ) ) {
				$settings = array();
			} else {
				$settings = $wpdb->get_results(
					$wpdb->prepare(
						"SELECT setting_key, setting_value, setting_type
						FROM {$wpdb->prefix}bookings_settings
						WHERE setting_key IN ($placeholders)",
						$keys
					),
					ARRAY_A
				);
			}
		} else {
			$settings = $wpdb->get_results(
				"SELECT setting_key, setting_value, setting_type
				FROM {$wpdb->prefix}bookings_settings",
				ARRAY_A
			);
		}

		$formatted = array();
		foreach ( $settings as $setting ) {
			$value = $setting['setting_value'];

			switch ( $setting['setting_type'] ) {
				case 'integer':
					$value = (int) $value;
					break;
				case 'boolean':
					$value = (bool) $value;
					break;
				case 'json':
					$value = json_decode( $value, true );
					break;
			}

			if ( $this->is_sensitive_setting_key( $setting['setting_key'] ) && '' !== (string) $value ) {
				$value = 'SAVED';
			}

			$formatted[ $setting['setting_key'] ] = $value;
		}

		if ( ! empty( $requested_keys ) ) {
			$default_settings = array_merge(
				$this->get_cancellation_default_settings(),
				$this->get_payment_default_settings(),
				$this->get_deposit_default_settings()
			);

			foreach ( $default_settings as $default_key => $default_value ) {
				if ( in_array( $default_key, $requested_keys, true ) && ! array_key_exists( $default_key, $formatted ) ) {
					$formatted[ $default_key ] = $default_value;
				}
			}
		}

		// Options stored in wp_options (not wp_bookings_settings).
		$wp_option_settings = array(
			'bookit_confirmed_v2_url' => function () {
				return get_option( 'bookit_confirmed_v2_url', home_url( '/booking-confirmed-v2/' ) );
			},
		);
		foreach ( $wp_option_settings as $opt_key => $getter ) {
			if ( ! in_array( $opt_key, $allowed_keys, true ) ) {
				continue;
			}
			$include = empty( $requested_keys ) || in_array( $opt_key, $requested_keys, true );
			if ( $include ) {
				$formatted[ $opt_key ] = $getter();
			}
		}

		return rest_ensure_response(
			array(
				'success'  => true,
				'settings' => $formatted,
			)
		);
	}

	/**
	 * Update settings (upsert).
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response|WP_Error
	 */
	public function update_settings( $request ) {
		global $wpdb;

		$settings = $request->get_param( 'settings' );
		if ( ! is_array( $settings ) ) {
			return new WP_Error(
				'invalid_settings',
				__( 'Settings payload must be an array.', 'bookit-booking-system' ),
				array( 'status' => 400 )
			);
		}

		$cancellation_defaults = $this->get_cancellation_default_settings();
		$cancellation_keys     = array_keys( $cancellation_defaults );
		$allowed_keys          = $this->get_allowed_settings_keys();
		$payment_keys          = $this->get_payment_setting_keys();
		$deposit_keys          = $this->get_deposit_setting_keys();
		$saved_cancellation    = array();
		$updated_payment       = false;
		$updated_deposit       = false;
		$old_rows = $wpdb->get_results(
			"SELECT setting_key, setting_value, setting_type FROM {$wpdb->prefix}bookings_settings",
			ARRAY_A
		);
		$old_settings = array();

		foreach ( $old_rows as $setting_row ) {
			$old_value = $setting_row['setting_value'];

			if ( 'integer' === $setting_row['setting_type'] ) {
				$old_value = (int) $old_value;
			} elseif ( 'boolean' === $setting_row['setting_type'] ) {
				$old_value = (bool) $old_value;
			} elseif ( 'json' === $setting_row['setting_type'] ) {
				$old_value = json_decode( $old_value, true );
			}

			$old_settings[ $setting_row['setting_key'] ] = $old_value;
		}

		foreach ( $settings as $key => $value ) {
			$key = sanitize_key( $key );
			if ( '' === $key ) {
				continue;
			}

			if ( ! in_array( $key, $allowed_keys, true ) ) {
				continue;
			}

			if ( 'bookit_confirmed_v2_url' === $key ) {
				$url = is_string( $value ) ? trim( $value ) : '';
				if ( '' === $url ) {
					delete_option( 'bookit_confirmed_v2_url' );
					continue;
				}
				$url = esc_url_raw( $url );
				if ( '' === $url || false === filter_var( $url, FILTER_VALIDATE_URL ) ) {
					return new WP_Error(
						'invalid_bookit_confirmed_v2_url',
						__( 'V2 booking confirmed URL must be a valid absolute URL.', 'bookit-booking-system' ),
						array( 'status' => 400 )
					);
				}
				update_option( 'bookit_confirmed_v2_url', $url );
				continue;
			}

			if ( $this->is_sensitive_setting_key( $key ) && is_string( $value ) && '' === trim( $value ) ) {
				$existing_sensitive = $wpdb->get_var(
					$wpdb->prepare(
						"SELECT setting_value FROM {$wpdb->prefix}bookings_settings WHERE setting_key = %s",
						$key
					)
				);

				if ( ! empty( $existing_sensitive ) ) {
					continue;
				}
			}

			if ( in_array( $key, $cancellation_keys, true ) ) {
				$value = $this->sanitize_cancellation_setting_value( $key, $value );
			}

			if ( 'packages_enabled' === $key ) {
				$value = sanitize_text_field( (string) $value );
				if ( ! in_array( $value, array( '0', '1' ), true ) ) {
					continue;
				}
			}

			$type = 'string';
			if ( is_int( $value ) ) {
				$type = 'integer';
			} elseif ( is_bool( $value ) ) {
				$type = 'boolean';
			} elseif ( is_array( $value ) ) {
				$type  = 'json';
				$value = wp_json_encode( $value );
			}

			$existing = $wpdb->get_var(
				$wpdb->prepare(
					"SELECT id FROM {$wpdb->prefix}bookings_settings WHERE setting_key = %s",
					$key
				)
			);

			if ( $existing ) {
				$wpdb->update(
					$wpdb->prefix . 'bookings_settings',
					array(
						'setting_value' => $value,
						'setting_type'  => $type,
					),
					array( 'setting_key' => $key ),
					array( '%s', '%s' ),
					array( '%s' )
				);
			} else {
				$wpdb->insert(
					$wpdb->prefix . 'bookings_settings',
					array(
						'setting_key'   => $key,
						'setting_value' => $value,
						'setting_type'  => $type,
					),
					array( '%s', '%s', '%s' )
				);
			}

			if ( in_array( $key, $cancellation_keys, true ) ) {
				$saved_cancellation[ $key ] = $value;
			}

			if ( in_array( $key, $payment_keys, true ) ) {
				$updated_payment = true;
			}

			if ( in_array( $key, $deposit_keys, true ) ) {
				$updated_deposit = true;
			}
		}

		$new_rows = $wpdb->get_results(
			"SELECT setting_key, setting_value, setting_type FROM {$wpdb->prefix}bookings_settings",
			ARRAY_A
		);
		$new_settings = array();

		foreach ( $new_rows as $setting_row ) {
			$new_value = $setting_row['setting_value'];

			if ( 'integer' === $setting_row['setting_type'] ) {
				$new_value = (int) $new_value;
			} elseif ( 'boolean' === $setting_row['setting_type'] ) {
				$new_value = (bool) $new_value;
			} elseif ( 'json' === $setting_row['setting_type'] ) {
				$new_value = json_decode( $new_value, true );
			}

			$new_settings[ $setting_row['setting_key'] ] = $new_value;
		}

		Bookit_Audit_Logger::log(
			'setting.updated',
			'setting',
			0,
			array(
				'old_value' => $old_settings,
				'new_value' => $new_settings,
				'notes'     => 'Settings saved via dashboard',
			)
		);

		if ( ! empty( $saved_cancellation ) ) {
			$current_staff = Bookit_Auth::get_current_staff();
			$staff_id      = isset( $current_staff['id'] ) ? absint( $current_staff['id'] ) : 0;

			Bookit_Audit_Logger::log(
				'cancellation_policy_updated',
				'setting',
				0,
				array(
					'actor_id'  => $staff_id,
					'staff_id'  => $staff_id,
					'new_value' => $saved_cancellation,
					'notes'     => 'Cancellation policy updated via dashboard',
				)
			);
		}

		if ( $updated_payment ) {
			$current_staff = Bookit_Auth::get_current_staff();
			$staff_id      = isset( $current_staff['id'] ) ? absint( $current_staff['id'] ) : 0;

			Bookit_Audit_Logger::log(
				'payment_settings_updated',
				'setting',
				0,
				array(
					'actor_id' => $staff_id,
					'staff_id' => $staff_id,
					'notes'    => 'Payment settings updated via dashboard',
				)
			);
		}

		if ( $updated_deposit ) {
			$current_staff = Bookit_Auth::get_current_staff();
			$staff_id      = isset( $current_staff['id'] ) ? absint( $current_staff['id'] ) : 0;

			Bookit_Audit_Logger::log(
				'deposit_settings_updated',
				'setting',
				0,
				array(
					'actor_id' => $staff_id,
					'staff_id' => $staff_id,
					'notes'    => 'Deposit settings updated via dashboard',
				)
			);
		}

		return rest_ensure_response(
			array(
				'success' => true,
				'message' => 'Settings saved successfully.',
			)
		);
	}

	/**
	 * Get dashboard branding settings.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response
	 */
	public function get_branding_settings( $request ) {
		return rest_ensure_response( $this->load_branding_settings() );
	}

	/**
	 * Update dashboard branding settings.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response|WP_Error
	 */
	public function update_branding_settings( $request ) {
		global $wpdb;

		$branding = array(
			'branding_logo_url'           => $request->get_param( 'branding_logo_url' ),
			'branding_primary_colour'     => $request->get_param( 'branding_primary_colour' ),
			'branding_business_name'      => $request->get_param( 'branding_business_name' ),
			'branding_powered_by_visible' => $request->get_param( 'branding_powered_by_visible' ),
		);

		$current = $this->load_branding_settings();

		if ( null === $branding['branding_logo_url'] ) {
			$branding['branding_logo_url'] = $current['logoUrl'];
		}
		if ( null === $branding['branding_primary_colour'] ) {
			$branding['branding_primary_colour'] = $current['primaryColour'];
		}
		if ( null === $branding['branding_business_name'] ) {
			$branding['branding_business_name'] = $current['businessName'];
		}
		if ( null === $branding['branding_powered_by_visible'] ) {
			$branding['branding_powered_by_visible'] = $current['poweredByVisible'];
		}

		$branding['branding_logo_url'] = is_string( $branding['branding_logo_url'] ) ? trim( $branding['branding_logo_url'] ) : '';
		if ( '' !== $branding['branding_logo_url'] ) {
			$branding['branding_logo_url'] = esc_url_raw( $branding['branding_logo_url'] );
			if ( empty( $branding['branding_logo_url'] ) || false === filter_var( $branding['branding_logo_url'], FILTER_VALIDATE_URL ) ) {
				return new WP_Error(
					'invalid_branding_logo_url',
					__( 'Logo URL must be empty or a valid URL.', 'bookit-booking-system' ),
					array( 'status' => 400 )
				);
			}
		}

		$branding['branding_primary_colour'] = is_string( $branding['branding_primary_colour'] ) ? trim( $branding['branding_primary_colour'] ) : '';
		if ( ! preg_match( '/^#[0-9A-Fa-f]{6}$/', $branding['branding_primary_colour'] ) ) {
			return new WP_Error(
				'invalid_branding_primary_colour',
				__( 'Primary colour must be a valid 6-digit hex value (e.g. #4F46E5).', 'bookit-booking-system' ),
				array( 'status' => 400 )
			);
		}

		$branding['branding_business_name'] = sanitize_text_field( (string) $branding['branding_business_name'] );
		if ( function_exists( 'mb_strlen' ) ) {
			$name_length = mb_strlen( $branding['branding_business_name'] );
		} else {
			$name_length = strlen( $branding['branding_business_name'] );
		}
		if ( $name_length > 100 ) {
			return new WP_Error(
				'invalid_branding_business_name',
				__( 'Business name must be 100 characters or fewer.', 'bookit-booking-system' ),
				array( 'status' => 400 )
			);
		}

		$branding['branding_powered_by_visible'] = rest_sanitize_boolean( $branding['branding_powered_by_visible'] );

		$this->upsert_setting( $wpdb, 'branding_logo_url', $branding['branding_logo_url'], 'string' );
		$this->upsert_setting( $wpdb, 'branding_primary_colour', strtoupper( $branding['branding_primary_colour'] ), 'string' );
		$this->upsert_setting( $wpdb, 'branding_business_name', $branding['branding_business_name'], 'string' );
		$this->upsert_setting( $wpdb, 'branding_powered_by_visible', $branding['branding_powered_by_visible'] ? '1' : '0', 'boolean' );

		$updated_branding = $this->load_branding_settings();

		return rest_ensure_response(
			array(
				'success'  => true,
				'message'  => __( 'Branding settings saved successfully.', 'bookit-booking-system' ),
				'branding' => $updated_branding,
			)
		);
	}

	/**
	 * Load branding settings from wp_bookings_settings.
	 *
	 * @return array
	 */
	private function load_branding_settings() {
		global $wpdb;

		$defaults = array(
			'logoUrl'          => '',
			'primaryColour'    => '#4F46E5',
			'businessName'     => '',
			'poweredByVisible' => true,
		);

		$db_keys      = array(
			'branding_logo_url',
			'branding_primary_colour',
			'branding_business_name',
			'branding_powered_by_visible',
		);
		$placeholders = implode( ',', array_fill( 0, count( $db_keys ), '%s' ) );
		$rows         = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT setting_key, setting_value, setting_type
				FROM {$wpdb->prefix}bookings_settings
				WHERE setting_key IN ($placeholders)",
				$db_keys
			),
			ARRAY_A
		);

		$settings = $defaults;

		foreach ( $rows as $row ) {
			$key = $row['setting_key'];
			if ( 'branding_powered_by_visible' === $key ) {
				$settings['poweredByVisible'] = (bool) $row['setting_value'];
			} elseif ( 'branding_primary_colour' === $key ) {
				$value                     = strtoupper( (string) $row['setting_value'] );
				$settings['primaryColour'] = preg_match( '/^#[0-9A-F]{6}$/', $value ) ? $value : '#4F46E5';
			} elseif ( 'branding_logo_url' === $key ) {
				$settings['logoUrl'] = (string) $row['setting_value'];
			} elseif ( 'branding_business_name' === $key ) {
				$settings['businessName'] = (string) $row['setting_value'];
			}
		}

		return $settings;
	}

	/**
	 * Get allowlisted setting keys.
	 *
	 * @return array
	 */
	private function get_allowed_settings_keys() {
		return array(
			'business_name',
			'business_phone',
			'business_address',
			'timezone',
			'show_staff_earnings',
			'packages_enabled',
			'smtp_enabled',
			'smtp_host',
			'smtp_port',
			'smtp_encryption',
			'smtp_username',
			'smtp_password',
			'smtp_from_name',
			'smtp_from_email',
			'email_provider',
			'brevo_api_key',
			'brevo_from_name',
			'brevo_from_email',
			'brevo_template_booking_confirmed',
			'brevo_template_booking_cancelled',
			'brevo_template_booking_rescheduled',
			'brevo_template_magic_link_cancel',
			'brevo_template_magic_link_reschedule',
			'brevo_template_business_notification',
			'brevo_template_staff_new_booking',
			'brevo_template_staff_reschedule',
			'brevo_template_staff_cancellation',
			'brevo_template_staff_reassigned_to',
			'brevo_template_staff_reassigned_away',
			'brevo_template_staff_daily_digest',
			'brevo_template_staff_weekly_digest',
			'brevo_template_staff_daily_schedule',
			'staff_digest_send_time',
			'staff_schedule_send_time',
			'staff_digest_weekly_day',
			'sms_provider',
			'brevo_sms_api_key',
			'email_rate_limit_per_minute',
			'stripe_connected',
			'stripe_account_id',
			'paypal_connected',
			'paypal_client_id',
			'stripe_publishable_key',
			'stripe_secret_key',
			'stripe_webhook_secret',
			'stripe_test_mode',
			'paypal_client_secret',
			'paypal_sandbox_mode',
			'pay_on_arrival_enabled',
			'deposit_required_default',
			'deposit_type_default',
			'deposit_amount_default',
			'deposit_minimum_percent',
			'deposit_maximum_percent',
			'deposit_applies_to',
			'deposit_required_for_pay_on_arrival',
			'deposit_refundable_within_window',
			'deposit_refundable_outside_window',
			'cancellation_window_hours',
			'within_window_refund_type',
			'within_window_refund_percent',
			'late_cancel_refund_type',
			'late_cancel_refund_percent',
			'noshow_refund_type',
			'noshow_refund_percent',
			'reschedule_policy',
			'reschedule_fee_amount',
			'cancellation_policy_text',
			'auto_refund_enabled',
			'google_client_id',
			'google_client_secret',
			'google_calendar_fallback_enabled',
			'bookit_confirmed_v2_url',
		);
	}

	/**
	 * Get payment setting keys.
	 *
	 * @return array
	 */
	private function get_payment_setting_keys() {
		return array(
			'stripe_publishable_key',
			'stripe_secret_key',
			'stripe_webhook_secret',
			'stripe_test_mode',
			'paypal_client_id',
			'paypal_client_secret',
			'paypal_sandbox_mode',
			'pay_on_arrival_enabled',
		);
	}

	/**
	 * Get deposit setting keys.
	 *
	 * @return array
	 */
	private function get_deposit_setting_keys() {
		return array(
			'deposit_required_default',
			'deposit_type_default',
			'deposit_amount_default',
			'deposit_minimum_percent',
			'deposit_maximum_percent',
			'deposit_applies_to',
			'deposit_required_for_pay_on_arrival',
			'deposit_refundable_within_window',
			'deposit_refundable_outside_window',
		);
	}

	/**
	 * Get sensitive setting keys that should be masked in responses.
	 *
	 * @return array
	 */
	private function get_sensitive_setting_keys() {
		return array(
			'stripe_secret_key',
			'stripe_webhook_secret',
			'paypal_client_secret',
			'brevo_api_key',
			'brevo_sms_api_key',
			'google_client_secret',
		);
	}

	/**
	 * Check if setting key is sensitive.
	 *
	 * @param string $key Setting key.
	 * @return bool
	 */
	private function is_sensitive_setting_key( $key ) {
		return in_array( $key, $this->get_sensitive_setting_keys(), true );
	}

	/**
	 * Get payment settings defaults.
	 *
	 * @return array
	 */
	private function get_payment_default_settings() {
		return array(
			'stripe_publishable_key'   => '',
			'stripe_secret_key'        => '',
			'stripe_webhook_secret'    => '',
			'stripe_test_mode'         => true,
			'paypal_client_id'         => '',
			'paypal_client_secret'     => '',
			'paypal_sandbox_mode'      => true,
			'pay_on_arrival_enabled'   => true,
		);
	}

	/**
	 * Get deposit setting defaults.
	 *
	 * @return array
	 */
	private function get_deposit_default_settings() {
		return array(
			'deposit_required_default'            => false,
			'deposit_type_default'                => 'percentage',
			'deposit_amount_default'              => 50,
			'deposit_minimum_percent'             => 10,
			'deposit_maximum_percent'             => 100,
			'deposit_applies_to'                  => 'all',
			'deposit_required_for_pay_on_arrival' => false,
			'deposit_refundable_within_window'    => true,
			'deposit_refundable_outside_window'   => false,
		);
	}

	/**
	 * Get cancellation policy setting defaults.
	 *
	 * @return array
	 */
	private function get_cancellation_default_settings() {
		return array(
			'cancellation_window_hours'    => 24,
			'within_window_refund_type'    => 'full',
			'within_window_refund_percent' => 100,
			'late_cancel_refund_type'      => 'none',
			'late_cancel_refund_percent'   => 0,
			'noshow_refund_type'           => 'none',
			'noshow_refund_percent'        => 0,
			'reschedule_policy'            => 'free',
			'reschedule_fee_amount'        => '0.00',
			'cancellation_policy_text'     => 'Free cancellation up to 24 hours before your appointment. Late cancellations and no-shows may forfeit their deposit.',
			'auto_refund_enabled'          => false,
		);
	}

	/**
	 * Sanitize cancellation policy settings values.
	 *
	 * @param string $key   Setting key.
	 * @param mixed  $value Setting value.
	 * @return mixed
	 */
	private function sanitize_cancellation_setting_value( $key, $value ) {
		$defaults = $this->get_cancellation_default_settings();

		switch ( $key ) {
			case 'cancellation_window_hours':
				$value = absint( $value );
				return $value > 0 ? $value : $defaults['cancellation_window_hours'];

			case 'within_window_refund_type':
			case 'late_cancel_refund_type':
			case 'noshow_refund_type':
				$value = sanitize_key( (string) $value );
				return in_array( $value, array( 'full', 'partial', 'none' ), true ) ? $value : $defaults[ $key ];

			case 'within_window_refund_percent':
			case 'late_cancel_refund_percent':
			case 'noshow_refund_percent':
				$value = is_numeric( $value ) ? (int) $value : (int) $defaults[ $key ];
				return max( 0, min( 100, $value ) );

			case 'reschedule_policy':
				$value = sanitize_key( (string) $value );
				return in_array( $value, array( 'free', 'limited', 'fee', 'not_allowed' ), true ) ? $value : $defaults['reschedule_policy'];

			case 'reschedule_fee_amount':
				$amount = is_numeric( $value ) ? (float) $value : 0.0;
				if ( $amount < 0 ) {
					$amount = 0.0;
				}
				return number_format( $amount, 2, '.', '' );

			case 'cancellation_policy_text':
				return sanitize_textarea_field( (string) $value );

			case 'auto_refund_enabled':
				return rest_sanitize_boolean( $value );

			default:
				return $value;
		}
	}

	/**
	 * Upsert a single setting in wp_bookings_settings.
	 *
	 * @param wpdb   $wpdb  WordPress database instance.
	 * @param string $key   Setting key.
	 * @param string $value Setting value.
	 * @param string $type  Setting type.
	 * @return void
	 */
	private function upsert_setting( $wpdb, $key, $value, $type ) {
		$existing = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT id FROM {$wpdb->prefix}bookings_settings WHERE setting_key = %s",
				$key
			)
		);

		if ( $existing ) {
			$wpdb->update(
				$wpdb->prefix . 'bookings_settings',
				array(
					'setting_value' => $value,
					'setting_type'  => $type,
				),
				array( 'setting_key' => $key ),
				array( '%s', '%s' ),
				array( '%s' )
			);
			return;
		}

		$wpdb->insert(
			$wpdb->prefix . 'bookings_settings',
			array(
				'setting_key'   => $key,
				'setting_value' => $value,
				'setting_type'  => $type,
			),
			array( '%s', '%s', '%s' )
		);
	}

	/**
	 * Send a test email using the resolved notification provider.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response|WP_Error
	 */
	public function send_test_email( $request ) {
		$to_email = sanitize_email( $request->get_param( 'to_email' ) );

		if ( ! is_email( $to_email ) ) {
			return new WP_Error(
				'invalid_email',
				__( 'Invalid recipient email address.', 'bookit-booking-system' ),
				array( 'status' => 400 )
			);
		}

		$provider = Bookit_Notification_Dispatcher::resolve_email_provider();

		$subject  = sprintf(
			/* translators: %s: site name */
			__( 'Test Email from %s', 'bookit-booking-system' ),
			get_bloginfo( 'name' )
		);
		$html_body = sprintf(
			'<p>%s</p><p>%s</p>',
			esc_html__( 'This is a test email sent from Bookit Booking System.', 'bookit-booking-system' ),
			esc_html( current_time( 'mysql' ) )
		);

		$result = $provider->send(
			array( 'email' => $to_email, 'name' => $to_email ),
			$subject,
			$html_body
		);

		if ( is_wp_error( $result ) ) {
			return new WP_Error(
				'test_email_failed',
				$result->get_error_message(),
				array( 'status' => 500 )
			);
		}

		return rest_ensure_response(
			array(
				'success'  => true,
				'message'  => sprintf(
					/* translators: %s: email address */
					__( 'Test email sent successfully to %s.', 'bookit-booking-system' ),
					$to_email
				),
				'provider' => $provider->get_name(),
			)
		);
	}

	/**
	 * Get all email templates.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response
	 */
	public function get_email_templates( $request ) {
		global $wpdb;

		$templates = $wpdb->get_results(
			"SELECT template_key, subject, body, enabled
			FROM {$wpdb->prefix}bookings_email_templates
			ORDER BY template_key",
			ARRAY_A
		);

		foreach ( $templates as &$template ) {
			$template['enabled'] = (bool) $template['enabled'];
		}

		return rest_ensure_response(
			array(
				'success'   => true,
				'templates' => $templates,
			)
		);
	}

	/**
	 * Update an email template by key.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response|WP_Error
	 */
	public function update_email_template( $request ) {
		global $wpdb;

		$key     = $request->get_param( 'key' );
		$subject = $request->get_param( 'subject' );
		$body    = $request->get_param( 'body' );
		$enabled = $request->get_param( 'enabled' );

		$update_data   = array(
			'subject' => $subject,
			'body'    => $body,
		);
		$update_format = array( '%s', '%s' );

		if ( null !== $enabled ) {
			$update_data['enabled'] = filter_var( $enabled, FILTER_VALIDATE_BOOLEAN ) ? 1 : 0;
			$update_format[]        = '%d';
		}

		$result = $wpdb->update(
			$wpdb->prefix . 'bookings_email_templates',
			$update_data,
			array( 'template_key' => $key ),
			$update_format,
			array( '%s' )
		);

		if ( false === $result ) {
			return new WP_Error(
				'update_failed',
				'Failed to update email template.',
				array( 'status' => 500 )
			);
		}

		return rest_ensure_response(
			array(
				'success' => true,
				'message' => 'Email template updated successfully.',
			)
		);
	}

	/**
	 * Reset an email template to its default content.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response|WP_Error
	 */
	public function reset_email_template( $request ) {
		global $wpdb;

		$key = $request->get_param( 'key' );

		$defaults = $this->get_default_email_templates();

		if ( ! isset( $defaults[ $key ] ) ) {
			return new WP_Error(
				'template_not_found',
				'Template not found.',
				array( 'status' => 404 )
			);
		}

		$result = $wpdb->update(
			$wpdb->prefix . 'bookings_email_templates',
			array(
				'subject' => $defaults[ $key ]['subject'],
				'body'    => $defaults[ $key ]['body'],
			),
			array( 'template_key' => $key ),
			array( '%s', '%s' ),
			array( '%s' )
		);

		if ( false === $result ) {
			return new WP_Error(
				'reset_failed',
				'Failed to reset email template.',
				array( 'status' => 500 )
			);
		}

		return rest_ensure_response(
			array(
				'success' => true,
				'message' => 'Email template reset to default.',
			)
		);
	}

	/**
	 * Get default email templates for reset functionality.
	 *
	 * @return array Keyed array of default templates.
	 */
	private function get_default_email_templates() {
		return array(
			'booking_confirmation' => array(
				'subject' => 'Booking Confirmed - {service_name}',
				'body'    => "Hi {customer_name},\n\nYour booking is confirmed!\n\n**Booking Details:**\nService: {service_name}\nDate: {date}\nTime: {time}\nStaff: {staff_name}\nLocation: {business_address}\n\nIf you need to make changes:\n- Reschedule: {reschedule_link}\n- Cancel: {cancel_link}\n\nThank you,\n{business_name}\n{business_phone}",
			),
			'booking_reminder'     => array(
				'subject' => 'Reminder: {service_name} tomorrow at {time}',
				'body'    => "Hi {customer_name},\n\nThis is a reminder about your booking tomorrow.\n\n**Booking Details:**\nService: {service_name}\nDate: {date}\nTime: {time}\nStaff: {staff_name}\nLocation: {business_address}\n\nWe look forward to seeing you!\n\nIf you need to make changes:\n- Reschedule: {reschedule_link}\n- Cancel: {cancel_link}\n\nSee you soon,\n{business_name}\n{business_phone}",
			),
			'booking_cancelled'    => array(
				'subject' => 'Booking Cancelled - {service_name}',
				'body'    => "Hi {customer_name},\n\nYour booking has been cancelled.\n\n**Cancelled Booking:**\nService: {service_name}\nDate: {date}\nTime: {time}\n\nIf this was a mistake or you'd like to rebook, please contact us or visit our website.\n\nThank you,\n{business_name}\n{business_phone}",
			),
			'admin_new_booking'    => array(
				'subject' => 'New Booking: {customer_name} - {service_name}',
				'body'    => "New booking received!\n\n**Customer:**\n{customer_name}\n{customer_email}\n{customer_phone}\n\n**Booking Details:**\nService: {service_name}\nDate: {date}\nTime: {time}\nStaff: {staff_name}\nDuration: {duration} minutes\n\n**Payment:**\nTotal: £{total_price}\nDeposit Paid: £{deposit_paid}\n\nView in dashboard: {dashboard_link}",
			),
			'staff_new_booking'    => array(
				'subject' => 'New Booking Assigned: {customer_name}',
				'body'    => "Hi {staff_name},\n\nYou have a new booking!\n\n**Customer:**\n{customer_name}\n{customer_phone}\n\n**Booking Details:**\nService: {service_name}\nDate: {date}\nTime: {time}\nDuration: {duration} minutes\n\nView in dashboard: {dashboard_link}",
			),
		);
	}
}

// Initialize the API.
new Bookit_Dashboard_Bookings_API();
