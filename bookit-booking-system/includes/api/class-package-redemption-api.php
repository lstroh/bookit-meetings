<?php
/**
 * Package Redemption REST API Controller.
 *
 * @package    Bookit_Booking_System
 * @subpackage Bookit_Booking_System/includes/api
 */

if ( ! defined( 'WPINC' ) ) {
	die;
}

/**
 * Package redemption API class.
 */
class Bookit_Package_Redemption_API {

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
			'/dashboard/package-redemptions',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'redeem_package' ),
				'permission_callback' => array( $this, 'check_admin_permission' ),
				'args'                => array(
					'customer_package_id' => array(
						'required'          => true,
						'type'              => 'integer',
						'sanitize_callback' => 'absint',
						'validate_callback' => function ( $value ) {
							return is_numeric( $value ) && (int) $value >= 1;
						},
					),
					'booking_id'          => array(
						'required'          => true,
						'type'              => 'integer',
						'sanitize_callback' => 'absint',
						'validate_callback' => function ( $value ) {
							return is_numeric( $value ) && (int) $value >= 1;
						},
					),
					'notes'               => array(
						'required'          => false,
						'type'              => 'string',
						'default'           => '',
						'sanitize_callback' => 'sanitize_textarea_field',
					),
				),
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
	 * Redeem package for an existing booking.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|WP_Error
	 */
	public function redeem_package( $request ) {
		global $wpdb;

		$customer_package_id = absint( $request->get_param( 'customer_package_id' ) );
		$booking_id          = absint( $request->get_param( 'booking_id' ) );
		$notes               = sanitize_textarea_field( (string) $request->get_param( 'notes' ) );

		$wpdb->query( 'START TRANSACTION' );

		$package = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT cp.*, pt.applicable_service_ids, pt.name AS package_type_name
				FROM {$wpdb->prefix}bookings_customer_packages cp
				JOIN {$wpdb->prefix}bookings_package_types pt ON pt.id = cp.package_type_id
				WHERE cp.id = %d
				FOR UPDATE",
				$customer_package_id
			),
			ARRAY_A
		);

		if ( ! $package ) {
			$wpdb->query( 'ROLLBACK' );
			return Bookit_Error_Registry::to_wp_error( 'E5001' );
		}

		if ( 'active' !== (string) $package['status'] ) {
			if ( 'exhausted' === (string) $package['status'] ) {
				$wpdb->query( 'ROLLBACK' );
				return Bookit_Error_Registry::to_wp_error( 'E5002' );
			}

			if ( 'expired' === (string) $package['status'] ) {
				$wpdb->query( 'ROLLBACK' );
				return Bookit_Error_Registry::to_wp_error( 'E5003' );
			}

			$wpdb->query( 'ROLLBACK' );
			return new WP_Error(
				'package_not_active',
				__( 'This package is not active.', 'bookit-booking-system' ),
				array( 'status' => 422 )
			);
		}

		if ( (int) $package['sessions_remaining'] < 1 ) {
			$wpdb->query( 'ROLLBACK' );
			return Bookit_Error_Registry::to_wp_error( 'E5002' );
		}

		if ( ! empty( $package['expires_at'] ) && strtotime( (string) $package['expires_at'] ) < time() ) {
			$wpdb->query( 'ROLLBACK' );
			return Bookit_Error_Registry::to_wp_error( 'E5003' );
		}

		$booking = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT id, service_id, customer_id, status, customer_package_id
				FROM {$wpdb->prefix}bookings
				WHERE id = %d
				FOR UPDATE",
				$booking_id
			),
			ARRAY_A
		);

		if ( ! $booking ) {
			$wpdb->query( 'ROLLBACK' );
			return new WP_Error(
				'booking_not_found',
				__( 'Booking not found.', 'bookit-booking-system' ),
				array( 'status' => 404 )
			);
		}

		if ( null !== $booking['customer_package_id'] && 0 !== (int) $booking['customer_package_id'] ) {
			$wpdb->query( 'ROLLBACK' );
			return new WP_Error(
				'booking_already_redeemed',
				__( 'This booking has already been redeemed against a package.', 'bookit-booking-system' ),
				array( 'status' => 422 )
			);
		}

		if ( in_array( (string) $booking['status'], array( 'cancelled', 'no_show' ), true ) ) {
			$wpdb->query( 'ROLLBACK' );
			return new WP_Error(
				'booking_not_redeemable',
				__( 'Cannot redeem a package against a cancelled or no-show booking.', 'bookit-booking-system' ),
				array( 'status' => 422 )
			);
		}

		if ( ! $this->package_matches_service( $package['applicable_service_ids'] ?? null, (int) $booking['service_id'] ) ) {
			$wpdb->query( 'ROLLBACK' );
			return Bookit_Error_Registry::to_wp_error(
				'E5004',
				array(
					'service_id' => (int) $booking['service_id'],
				)
			);
		}

		$updated = $wpdb->update(
			$wpdb->prefix . 'bookings',
			array(
				'customer_package_id' => $customer_package_id,
				'payment_method'      => 'package_redemption',
				'updated_at'          => current_time( 'mysql' ),
			),
			array( 'id' => $booking_id ),
			array( '%d', '%s', '%s' ),
			array( '%d' )
		);
		if ( false === $updated ) {
			$wpdb->query( 'ROLLBACK' );
			return new WP_Error(
				'db_error',
				__( 'Failed to update booking.', 'bookit-booking-system' ),
				array( 'status' => 500 )
			);
		}

		$decremented = $wpdb->query(
			$wpdb->prepare(
				"UPDATE {$wpdb->prefix}bookings_customer_packages
				SET sessions_remaining = sessions_remaining - 1,
					status = CASE WHEN sessions_remaining <= 1 THEN 'exhausted' ELSE 'active' END,
					updated_at = %s
				WHERE id = %d",
				current_time( 'mysql' ),
				$customer_package_id
			)
		);
		if ( false === $decremented ) {
			$wpdb->query( 'ROLLBACK' );
			return new WP_Error(
				'db_error',
				__( 'Failed to update package.', 'bookit-booking-system' ),
				array( 'status' => 500 )
			);
		}

		$inserted = $wpdb->insert(
			$wpdb->prefix . 'bookings_package_redemptions',
			array(
				'customer_package_id' => $customer_package_id,
				'booking_id'          => $booking_id,
				'redeemed_at'         => current_time( 'mysql' ),
				'redeemed_by'         => get_current_user_id(),
				'notes'               => $notes,
				'created_at'          => current_time( 'mysql' ),
			),
			array( '%d', '%d', '%s', '%d', '%s', '%s' )
		);
		if ( ! $inserted ) {
			$wpdb->query( 'ROLLBACK' );
			return new WP_Error(
				'db_error',
				__( 'Failed to create redemption record.', 'bookit-booking-system' ),
				array( 'status' => 500 )
			);
		}
		$redemption_id = (int) $wpdb->insert_id;

		$wpdb->query( 'COMMIT' );

		Bookit_Audit_Logger::log(
			'customer_package.redeemed',
			'customer_package',
			$customer_package_id,
			array(
				'booking_id'         => $booking_id,
				'redemption_id'      => $redemption_id,
				'sessions_remaining' => (int) $package['sessions_remaining'] - 1,
				'redeemed_by'        => get_current_user_id(),
				'notes'              => $notes,
			)
		);

		return new WP_REST_Response(
			array(
				'success'             => true,
				'redemption_id'       => $redemption_id,
				'customer_package_id' => $customer_package_id,
				'booking_id'          => $booking_id,
				'sessions_remaining'  => (int) $package['sessions_remaining'] - 1,
				'package_status'      => ( (int) $package['sessions_remaining'] - 1 <= 0 ) ? 'exhausted' : 'active',
			),
			201
		);
	}

	/**
	 * Check whether package is valid for service.
	 *
	 * @param string|null $applicable_service_ids JSON array or null.
	 * @param int         $service_id Service ID.
	 * @return bool
	 */
	private function package_matches_service( $applicable_service_ids, $service_id ) {
		if ( null === $applicable_service_ids || '' === $applicable_service_ids ) {
			return true;
		}

		$decoded = json_decode( (string) $applicable_service_ids, true );
		if ( ! is_array( $decoded ) ) {
			return true;
		}

		$service_ids = array_values( array_map( 'absint', $decoded ) );
		return in_array( (int) $service_id, $service_ids, true );
	}
}
