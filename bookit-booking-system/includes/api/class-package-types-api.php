<?php
/**
 * Package Types REST API Controller.
 *
 * @package    Bookit_Booking_System
 * @subpackage Bookit_Booking_System/includes/api
 */

if ( ! defined( 'WPINC' ) ) {
	die;
}

/**
 * Package types API class.
 */
class Bookit_Package_Types_API {

	/**
	 * REST namespace.
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
	public function register_routes() {
		register_rest_route(
			self::NAMESPACE,
			'/dashboard/package-types',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'list_package_types' ),
				'permission_callback' => array( $this, 'check_admin_permission' ),
				'args'                => array(
					'active_only' => array(
						'required'          => false,
						'type'              => 'boolean',
						'default'           => false,
						'sanitize_callback' => 'rest_sanitize_boolean',
					),
				),
			)
		);

		register_rest_route(
			self::NAMESPACE,
			'/dashboard/package-types',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'create_package_type' ),
				'permission_callback' => array( $this, 'check_admin_permission' ),
				'args'                => $this->get_create_route_args(),
			)
		);

		register_rest_route(
			self::NAMESPACE,
			'/dashboard/package-types/(?P<id>\d+)',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'get_package_type' ),
				'permission_callback' => array( $this, 'check_admin_permission' ),
			)
		);

		register_rest_route(
			self::NAMESPACE,
			'/dashboard/package-types/(?P<id>\d+)',
			array(
				'methods'             => WP_REST_Server::EDITABLE,
				'callback'            => array( $this, 'update_package_type' ),
				'permission_callback' => array( $this, 'check_admin_permission' ),
				'args'                => $this->get_update_route_args(),
			)
		);

		register_rest_route(
			self::NAMESPACE,
			'/dashboard/package-types/(?P<id>\d+)/deactivate',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'deactivate_package_type' ),
				'permission_callback' => array( $this, 'check_admin_permission' ),
			)
		);
	}

	/**
	 * Permission callback for admin-only endpoints.
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
	 * GET /dashboard/package-types
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|WP_Error
	 */
	public function list_package_types( $request ) {
		global $wpdb;

		$active_only = (bool) rest_sanitize_boolean( $request->get_param( 'active_only' ) );
		$table       = $wpdb->prefix . 'bookings_package_types';

		$query = "SELECT * FROM {$table}";
		if ( $active_only ) {
			$query .= ' WHERE is_active = 1';
		}
		$query .= ' ORDER BY id ASC';

		$rows = $wpdb->get_results( $query, ARRAY_A );
		if ( null === $rows ) {
			return Bookit_Error_Registry::to_wp_error( 'E9001', array( 'db_error' => $wpdb->last_error ) );
		}

		$items = array_map( array( $this, 'format_package_type_row' ), $rows );

		return new WP_REST_Response( $items, 200 );
	}

	/**
	 * POST /dashboard/package-types
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|WP_Error
	 */
	public function create_package_type( $request ) {
		global $wpdb;

		$prepared_or_error = $this->prepare_write_payload( $request, null, true );
		if ( is_wp_error( $prepared_or_error ) ) {
			return $prepared_or_error;
		}

		$table         = $wpdb->prefix . 'bookings_package_types';
		$insert_result = $wpdb->insert(
			$table,
			$prepared_or_error,
			array( '%s', '%s', '%d', '%s', '%f', '%f', '%d', '%d', '%s', '%d', '%s', '%s' )
		);

		if ( false === $insert_result ) {
			return Bookit_Error_Registry::to_wp_error( 'E9001', array( 'db_error' => $wpdb->last_error ) );
		}

		$new_id  = (int) $wpdb->insert_id;
		$new_row = $this->fetch_package_type_row( $new_id );
		if ( is_wp_error( $new_row ) ) {
			return $new_row;
		}

		Bookit_Audit_Logger::log(
			'package_type.created',
			'package_type',
			$new_id,
			array(
				'new_value' => $new_row,
			)
		);

		return new WP_REST_Response( $new_row, 201 );
	}

	/**
	 * GET /dashboard/package-types/{id}
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|WP_Error
	 */
	public function get_package_type( $request ) {
		$package_id = absint( $request->get_param( 'id' ) );
		$row        = $this->fetch_package_type_row( $package_id );
		if ( is_wp_error( $row ) ) {
			return $row;
		}

		return new WP_REST_Response( $row, 200 );
	}

	/**
	 * PUT /dashboard/package-types/{id}
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|WP_Error
	 */
	public function update_package_type( $request ) {
		global $wpdb;

		$package_id = absint( $request->get_param( 'id' ) );
		$existing   = $this->fetch_package_type_row( $package_id );
		if ( is_wp_error( $existing ) ) {
			return $existing;
		}

		$prepared_or_error = $this->prepare_write_payload( $request, $existing, false );
		if ( is_wp_error( $prepared_or_error ) ) {
			return $prepared_or_error;
		}

		$update_data = array();
		$formats     = array();

		$update_keys = array(
			'name'                   => '%s',
			'description'            => '%s',
			'sessions_count'         => '%d',
			'price_mode'             => '%s',
			'fixed_price'            => '%f',
			'discount_percentage'    => '%f',
			'expiry_enabled'         => '%d',
			'expiry_days'            => '%d',
			'applicable_service_ids' => '%s',
			'is_active'              => '%d',
		);

		foreach ( $update_keys as $key => $format ) {
			if ( $this->request_has_param( $request, $key ) ) {
				$update_data[ $key ] = $prepared_or_error[ $key ];
				$formats[]           = $format;
			}
		}

		if ( empty( $update_data ) ) {
			return new WP_Error(
				'invalid_package_type_payload',
				__( 'At least one field must be provided for update.', 'bookit-booking-system' ),
				array( 'status' => 400 )
			);
		}

		$update_data['updated_at'] = current_time( 'mysql' );
		$formats[]                 = '%s';

		$table  = $wpdb->prefix . 'bookings_package_types';
		$result = $wpdb->update(
			$table,
			$update_data,
			array( 'id' => $package_id ),
			$formats,
			array( '%d' )
		);

		if ( false === $result ) {
			return Bookit_Error_Registry::to_wp_error( 'E9001', array( 'db_error' => $wpdb->last_error ) );
		}

		$new_row = $this->fetch_package_type_row( $package_id );
		if ( is_wp_error( $new_row ) ) {
			return $new_row;
		}

		Bookit_Audit_Logger::log(
			'package_type.updated',
			'package_type',
			$package_id,
			array(
				'old_value' => $existing,
				'new_value' => $new_row,
			)
		);

		return new WP_REST_Response( $new_row, 200 );
	}

	/**
	 * POST /dashboard/package-types/{id}/deactivate
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|WP_Error
	 */
	public function deactivate_package_type( $request ) {
		global $wpdb;

		$package_id = absint( $request->get_param( 'id' ) );
		$existing   = $this->fetch_package_type_row( $package_id );
		if ( is_wp_error( $existing ) ) {
			return $existing;
		}

		$table  = $wpdb->prefix . 'bookings_package_types';
		$result = $wpdb->update(
			$table,
			array(
				'is_active'  => 0,
				'updated_at' => current_time( 'mysql' ),
			),
			array( 'id' => $package_id ),
			array( '%d', '%s' ),
			array( '%d' )
		);

		if ( false === $result ) {
			return Bookit_Error_Registry::to_wp_error( 'E9001', array( 'db_error' => $wpdb->last_error ) );
		}

		Bookit_Audit_Logger::log(
			'package_type.deactivated',
			'package_type',
			$package_id,
			array(
				'old_value' => array(
					'is_active' => true,
				),
			)
		);

		return new WP_REST_Response(
			array(
				'success' => true,
				'id'      => $package_id,
			),
			200
		);
	}

	/**
	 * Create-route args.
	 *
	 * @return array<string,array>
	 */
	private function get_create_route_args() {
		return array(
			'name'                   => array(
				'required'          => true,
				'type'              => 'string',
				'sanitize_callback' => 'sanitize_text_field',
			),
			'description'            => array(
				'required'          => false,
				'type'              => 'string',
				'sanitize_callback' => 'sanitize_textarea_field',
			),
			'sessions_count'         => array(
				'required'          => true,
				'type'              => 'integer',
				'sanitize_callback' => 'absint',
				'validate_callback' => function ( $value ) {
					return is_numeric( $value ) && (int) $value >= 1;
				},
			),
			'price_mode'             => array(
				'required'          => true,
				'type'              => 'string',
				'sanitize_callback' => 'sanitize_text_field',
				'validate_callback' => function ( $value ) {
					return in_array( (string) $value, array( 'fixed', 'discount' ), true );
				},
			),
			'fixed_price'            => array(
				'required'          => false,
				'type'              => 'number',
				'validate_callback' => function ( $value ) {
					return null === $value || ( is_numeric( $value ) && (float) $value >= 0 );
				},
			),
			'discount_percentage'    => array(
				'required'          => false,
				'type'              => 'number',
				'validate_callback' => function ( $value ) {
					return null === $value || ( is_numeric( $value ) && (float) $value >= 0 && (float) $value <= 100 );
				},
			),
			'expiry_enabled'         => array(
				'required'          => false,
				'type'              => 'boolean',
				'default'           => false,
				'sanitize_callback' => 'rest_sanitize_boolean',
			),
			'expiry_days'            => array(
				'required'          => false,
				'type'              => 'integer',
				'sanitize_callback' => 'absint',
				'validate_callback' => function ( $value ) {
					return null === $value || ( is_numeric( $value ) && (int) $value >= 1 );
				},
			),
			'applicable_service_ids' => array(
				'required'          => false,
				'validate_callback' => array( $this, 'validate_service_ids' ),
			),
		);
	}

	/**
	 * Update-route args.
	 *
	 * @return array<string,array>
	 */
	private function get_update_route_args() {
		$args = $this->get_create_route_args();
		foreach ( $args as $key => $definition ) {
			$args[ $key ]['required'] = false;
		}
		return $args;
	}

	/**
	 * Validate service IDs array.
	 *
	 * @param mixed $value Incoming value.
	 * @return bool
	 */
	public function validate_service_ids( $value ) {
		if ( null === $value ) {
			return true;
		}

		if ( '' === $value ) {
			return true;
		}

		if ( is_string( $value ) ) {
			$value = json_decode( $value, true );
		}

		if ( ! is_array( $value ) ) {
			return false;
		}

		foreach ( $value as $service_id ) {
			if ( ! is_numeric( $service_id ) || (int) $service_id <= 0 ) {
				return false;
			}
		}

		return true;
	}

	/**
	 * Build and validate write payload.
	 *
	 * @param WP_REST_Request $request Request.
	 * @param array|null      $existing Existing row (formatted) when updating.
	 * @param bool            $is_create Whether this is create flow.
	 * @return array|WP_Error
	 */
	private function prepare_write_payload( $request, $existing, $is_create ) {
		$data = array(
			'name'                   => $is_create ? sanitize_text_field( (string) $request->get_param( 'name' ) ) : (string) $existing['name'],
			'description'            => $is_create ? sanitize_textarea_field( (string) $request->get_param( 'description' ) ) : (string) $existing['description'],
			'sessions_count'         => $is_create ? absint( $request->get_param( 'sessions_count' ) ) : (int) $existing['sessions_count'],
			'price_mode'             => $is_create ? sanitize_text_field( (string) $request->get_param( 'price_mode' ) ) : (string) $existing['price_mode'],
			'fixed_price'            => $is_create ? $request->get_param( 'fixed_price' ) : $existing['fixed_price'],
			'discount_percentage'    => $is_create ? $request->get_param( 'discount_percentage' ) : $existing['discount_percentage'],
			'expiry_enabled'         => $is_create ? (bool) rest_sanitize_boolean( $request->get_param( 'expiry_enabled' ) ) : (bool) $existing['expiry_enabled'],
			'expiry_days'            => $is_create ? $request->get_param( 'expiry_days' ) : $existing['expiry_days'],
			'applicable_service_ids' => $is_create ? $request->get_param( 'applicable_service_ids' ) : $existing['applicable_service_ids'],
			'is_active'              => $is_create ? 1 : ( (bool) $existing['is_active'] ? 1 : 0 ),
		);

		$updatable_fields = array(
			'name',
			'description',
			'sessions_count',
			'price_mode',
			'fixed_price',
			'discount_percentage',
			'expiry_enabled',
			'expiry_days',
			'applicable_service_ids',
			'is_active',
		);

		if ( ! $is_create ) {
			foreach ( $updatable_fields as $field ) {
				if ( $this->request_has_param( $request, $field ) ) {
					if ( 'name' === $field ) {
						$data['name'] = sanitize_text_field( (string) $request->get_param( 'name' ) );
					} elseif ( 'description' === $field ) {
						$data['description'] = sanitize_textarea_field( (string) $request->get_param( 'description' ) );
					} elseif ( 'sessions_count' === $field ) {
						$data['sessions_count'] = absint( $request->get_param( 'sessions_count' ) );
					} elseif ( 'price_mode' === $field ) {
						$data['price_mode'] = sanitize_text_field( (string) $request->get_param( 'price_mode' ) );
					} elseif ( 'fixed_price' === $field ) {
						$data['fixed_price'] = $request->get_param( 'fixed_price' );
					} elseif ( 'discount_percentage' === $field ) {
						$data['discount_percentage'] = $request->get_param( 'discount_percentage' );
					} elseif ( 'expiry_enabled' === $field ) {
						$data['expiry_enabled'] = (bool) rest_sanitize_boolean( $request->get_param( 'expiry_enabled' ) );
					} elseif ( 'expiry_days' === $field ) {
						$data['expiry_days'] = $request->get_param( 'expiry_days' );
					} elseif ( 'applicable_service_ids' === $field ) {
						$data['applicable_service_ids'] = $request->get_param( 'applicable_service_ids' );
					} elseif ( 'is_active' === $field ) {
						$data['is_active'] = (bool) rest_sanitize_boolean( $request->get_param( 'is_active' ) ) ? 1 : 0;
					}
				}
			}

			// Normalise price fields based on final price_mode.
			// When switching modes, the unused field must be cleared
			// regardless of what the existing row contained.
			if ( 'fixed' === $data['price_mode'] ) {
				$data['discount_percentage'] = null;
			} elseif ( 'discount' === $data['price_mode'] ) {
				$data['fixed_price'] = null;
			}
		}

		if ( '' === $data['name'] ) {
			return new WP_Error(
				'invalid_package_type_payload',
				__( 'Package name is required.', 'bookit-booking-system' ),
				array( 'status' => 400 )
			);
		}

		if ( $data['sessions_count'] < 1 ) {
			return new WP_Error(
				'invalid_package_type_payload',
				__( 'sessions_count must be at least 1.', 'bookit-booking-system' ),
				array( 'status' => 400 )
			);
		}

		if ( ! in_array( $data['price_mode'], array( 'fixed', 'discount' ), true ) ) {
			return new WP_Error(
				'invalid_package_type_payload',
				__( 'price_mode must be fixed or discount.', 'bookit-booking-system' ),
				array( 'status' => 400 )
			);
		}

		if ( 'fixed' === $data['price_mode'] ) {
			if ( null === $data['fixed_price'] || '' === $data['fixed_price'] || ! is_numeric( $data['fixed_price'] ) || (float) $data['fixed_price'] < 0 ) {
				return new WP_Error(
					'invalid_package_type_payload',
					__( 'fixed_price is required and must be >= 0 when price_mode is fixed.', 'bookit-booking-system' ),
					array( 'status' => 400 )
				);
			}

			if ( null !== $data['discount_percentage'] && '' !== $data['discount_percentage'] ) {
				return new WP_Error(
					'invalid_package_type_payload',
					__( 'discount_percentage must be null when price_mode is fixed.', 'bookit-booking-system' ),
					array( 'status' => 400 )
				);
			}
		}

		if ( 'discount' === $data['price_mode'] ) {
			if ( null === $data['discount_percentage'] || '' === $data['discount_percentage'] || ! is_numeric( $data['discount_percentage'] ) ) {
				return new WP_Error(
					'invalid_package_type_payload',
					__( 'discount_percentage is required when price_mode is discount.', 'bookit-booking-system' ),
					array( 'status' => 400 )
				);
			}

			$discount = (float) $data['discount_percentage'];
			if ( $discount < 0 || $discount > 100 ) {
				return new WP_Error(
					'invalid_package_type_payload',
					__( 'discount_percentage must be between 0 and 100.', 'bookit-booking-system' ),
					array( 'status' => 400 )
				);
			}

			if ( null !== $data['fixed_price'] && '' !== $data['fixed_price'] ) {
				return new WP_Error(
					'invalid_package_type_payload',
					__( 'fixed_price must be null when price_mode is discount.', 'bookit-booking-system' ),
					array( 'status' => 400 )
				);
			}
		}

		$data['fixed_price']         = ( 'fixed' === $data['price_mode'] ) ? (float) $data['fixed_price'] : null;
		$data['discount_percentage'] = ( 'discount' === $data['price_mode'] ) ? (float) $data['discount_percentage'] : null;

		$expiry_enabled = (bool) $data['expiry_enabled'];
		if ( $expiry_enabled ) {
			if ( null === $data['expiry_days'] || '' === $data['expiry_days'] || ! is_numeric( $data['expiry_days'] ) || (int) $data['expiry_days'] < 1 ) {
				return new WP_Error(
					'invalid_package_type_payload',
					__( 'expiry_days is required and must be >= 1 when expiry_enabled is true.', 'bookit-booking-system' ),
					array( 'status' => 400 )
				);
			}
			$data['expiry_days'] = absint( $data['expiry_days'] );
		} else {
			$data['expiry_days'] = null;
		}

		$service_ids = $data['applicable_service_ids'];
		if ( null === $service_ids || array() === $service_ids || '' === $service_ids ) {
			$data['applicable_service_ids'] = null;
		} else {
			if ( is_string( $service_ids ) ) {
				$service_ids = json_decode( $service_ids, true );
			}

			if ( ! is_array( $service_ids ) || ! $this->validate_service_ids( $service_ids ) ) {
				return new WP_Error(
					'invalid_package_type_payload',
					__( 'applicable_service_ids must be a JSON array of positive integers.', 'bookit-booking-system' ),
					array( 'status' => 400 )
				);
			}

			$data['applicable_service_ids'] = wp_json_encode(
				array_values(
					array_map(
						'absint',
						$service_ids
					)
				)
			);
		}

		$data['expiry_enabled'] = $expiry_enabled ? 1 : 0;
		$data['description']    = '' === (string) $data['description'] ? null : (string) $data['description'];
		$data['created_at']     = current_time( 'mysql' );
		$data['updated_at']     = current_time( 'mysql' );

		return $data;
	}

	/**
	 * Fetch one package type row by ID.
	 *
	 * @param int $package_id Package ID.
	 * @return array|WP_Error
	 */
	private function fetch_package_type_row( $package_id ) {
		global $wpdb;

		$table = $wpdb->prefix . 'bookings_package_types';
		$row   = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$table} WHERE id = %d LIMIT 1",
				$package_id
			),
			ARRAY_A
		);

		if ( ! $row ) {
			return Bookit_Error_Registry::to_wp_error( 'E5001' );
		}

		return $this->format_package_type_row( $row );
	}

	/**
	 * Convert DB row to API response shape.
	 *
	 * @param array $row Raw DB row.
	 * @return array
	 */
	private function format_package_type_row( $row ) {
		$service_ids = null;

		if ( isset( $row['applicable_service_ids'] ) && null !== $row['applicable_service_ids'] && '' !== $row['applicable_service_ids'] ) {
			$decoded = json_decode( (string) $row['applicable_service_ids'], true );
			if ( is_array( $decoded ) ) {
				$service_ids = array_values(
					array_map(
						'absint',
						$decoded
					)
				);
			}
		}

		return array(
			'id'                     => (int) $row['id'],
			'name'                   => (string) $row['name'],
			'description'            => isset( $row['description'] ) ? ( null === $row['description'] ? null : (string) $row['description'] ) : null,
			'sessions_count'         => (int) $row['sessions_count'],
			'price_mode'             => (string) $row['price_mode'],
			'fixed_price'            => null === $row['fixed_price'] ? null : number_format( (float) $row['fixed_price'], 2, '.', '' ),
			'discount_percentage'    => null === $row['discount_percentage'] ? null : number_format( (float) $row['discount_percentage'], 2, '.', '' ),
			'expiry_enabled'         => (bool) (int) $row['expiry_enabled'],
			'expiry_days'            => null === $row['expiry_days'] ? null : (int) $row['expiry_days'],
			'applicable_service_ids' => $service_ids,
			'is_active'              => (bool) (int) $row['is_active'],
			'created_at'             => isset( $row['created_at'] ) ? (string) $row['created_at'] : null,
			'updated_at'             => isset( $row['updated_at'] ) ? (string) $row['updated_at'] : null,
		);
	}

	/**
	 * Safe wrapper for request has_param.
	 *
	 * @param WP_REST_Request $request Request.
	 * @param string          $param_name Param name.
	 * @return bool
	 */
	private function request_has_param( $request, $param_name ) {
		if ( method_exists( $request, 'has_param' ) ) {
			return $request->has_param( $param_name );
		}

		return null !== $request->get_param( $param_name );
	}
}
