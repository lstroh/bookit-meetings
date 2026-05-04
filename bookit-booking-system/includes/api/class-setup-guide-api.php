<?php
/**
 * Setup Guide REST API Controller.
 *
 * Provides admin-only endpoints for setup guide status state.
 *
 * @package    Bookit_Booking_System
 * @subpackage Bookit_Booking_System/includes/api
 */

if ( ! defined( 'WPINC' ) ) {
	die;
}

/**
 * Setup guide API class.
 */
class Bookit_Setup_Guide_API {

	/**
	 * REST API namespace.
	 */
	const NAMESPACE = 'bookit/v1';

	/**
	 * User meta key for setup guide status.
	 */
	private const META_KEY = 'bookit_setup_guide_status';

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
			'/setup-guide/status',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'get_status' ),
				'permission_callback' => array( $this, 'check_admin_permission' ),
			)
		);

		register_rest_route(
			self::NAMESPACE,
			'/setup-guide/status',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'update_status' ),
				'permission_callback' => array( $this, 'check_admin_permission' ),
				'args'                => array(
					'action'       => array(
						'required'          => true,
						'type'              => 'string',
						'sanitize_callback' => 'sanitize_text_field',
					),
					'current_step' => array(
						'required'          => false,
						'type'              => 'integer',
						'sanitize_callback' => 'absint',
					),
					'step_done'    => array(
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
	 * GET /setup-guide/status
	 *
	 * @return WP_REST_Response|WP_Error
	 */
	public function get_status() {
		$staff_id = $this->get_current_staff_id();
		if ( $staff_id <= 0 ) {
			return Bookit_Error_Registry::to_wp_error( 'E1002' );
		}

		$status = $this->read_status( $staff_id );
		return rest_ensure_response( $this->format_response( $status ) );
	}

	/**
	 * POST /setup-guide/status
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response|WP_Error
	 */
	public function update_status( $request ) {
		$staff_id = $this->get_current_staff_id();
		if ( $staff_id <= 0 ) {
			return Bookit_Error_Registry::to_wp_error( 'E1002' );
		}

		$action = sanitize_text_field( (string) $request->get_param( 'action' ) );
		if ( ! in_array( $action, array( 'complete', 'dismiss', 'update_step' ), true ) ) {
			return Bookit_Error_Registry::to_wp_error( 'E4010', array( 'action' => $action ) );
		}

		$current_step_param = $request->get_param( 'current_step' );
		$step_done_param    = $request->get_param( 'step_done' );

		if ( null !== $current_step_param ) {
			$current_step = absint( $current_step_param );
			if ( $current_step < 1 || $current_step > 4 ) {
				return Bookit_Error_Registry::to_wp_error( 'E4011', array( 'field' => 'current_step' ) );
			}
		}

		if ( null !== $step_done_param ) {
			$step_done = absint( $step_done_param );
			if ( $step_done < 1 || $step_done > 4 ) {
				return Bookit_Error_Registry::to_wp_error( 'E4011', array( 'field' => 'step_done' ) );
			}
		}

		$status = $this->read_status( $staff_id );

		if ( 'complete' === $action ) {
			$status['status']       = 'completed';
			$status['completed_at'] = gmdate( 'c' );
			$status['dismissed_at'] = null;

			Bookit_Audit_Logger::log(
				'setup_guide_completed',
				'setup_guide',
				$staff_id,
				array(
					'actor_id'  => $staff_id,
					'actor_type' => 'admin',
					'new_value' => array(
						'staff_id' => $staff_id,
						'status'   => $status,
					),
				)
			);

			do_action( 'bookit_setup_guide_completed', $staff_id );
		} elseif ( 'dismiss' === $action ) {
			$status['status']       = 'dismissed';
			$status['completed_at'] = null;
			$status['dismissed_at'] = gmdate( 'c' );

			Bookit_Audit_Logger::log(
				'setup_guide_dismissed',
				'setup_guide',
				$staff_id,
				array(
					'actor_id'  => $staff_id,
					'actor_type' => 'admin',
					'new_value' => array(
						'staff_id' => $staff_id,
						'status'   => $status,
					),
				)
			);

			do_action( 'bookit_setup_guide_dismissed', $staff_id );
		} else {
			if ( null !== $current_step_param ) {
				$status['current_step'] = absint( $current_step_param );
			}

			if ( null !== $step_done_param ) {
				$step_done = absint( $step_done_param );
				if ( ! in_array( $step_done, $status['steps_completed'], true ) ) {
					$status['steps_completed'][] = $step_done;
				}
			}
		}

		$this->write_status( $staff_id, $status );

		return rest_ensure_response( $this->format_response( $status ) );
	}

	/**
	 * Get current authenticated staff ID.
	 *
	 * @return int
	 */
	private function get_current_staff_id() {
		if ( ! class_exists( 'Bookit_Auth' ) ) {
			return 0;
		}

		$current_staff = Bookit_Auth::get_current_staff();
		if ( ! is_array( $current_staff ) || empty( $current_staff['id'] ) ) {
			return 0;
		}

		return absint( $current_staff['id'] );
	}

	/**
	 * Read setup guide status from user meta.
	 *
	 * @param int $staff_id Staff ID.
	 * @return array
	 */
	private function read_status( $staff_id ) {
		$raw = get_user_meta( $staff_id, self::META_KEY, true );

		if ( empty( $raw ) || ! is_string( $raw ) ) {
			return $this->default_status();
		}

		$decoded = json_decode( $raw, true );
		if ( ! is_array( $decoded ) ) {
			return $this->default_status();
		}

		return $this->sanitize_status( $decoded );
	}

	/**
	 * Persist setup guide status to user meta as JSON.
	 *
	 * @param int   $staff_id Staff ID.
	 * @param array $status   Status payload.
	 * @return void
	 */
	private function write_status( $staff_id, array $status ) {
		$normalized = $this->sanitize_status( $status );
		update_user_meta( $staff_id, self::META_KEY, wp_json_encode( $normalized ) );
	}

	/**
	 * Build API response payload.
	 *
	 * @param array $status Status payload.
	 * @return array
	 */
	private function format_response( array $status ) {
		$status = $this->sanitize_status( $status );

		return array(
			'success'         => true,
			'status'          => $status['status'],
			'current_step'    => $status['current_step'],
			'completed_at'    => $status['completed_at'],
			'dismissed_at'    => $status['dismissed_at'],
			'steps_completed' => $status['steps_completed'],
		);
	}

	/**
	 * Normalize status payload.
	 *
	 * @param array $status Raw status payload.
	 * @return array
	 */
	private function sanitize_status( array $status ) {
		$defaults = $this->default_status();

		$normalized_status = isset( $status['status'] ) ? sanitize_text_field( (string) $status['status'] ) : $defaults['status'];
		if ( ! in_array( $normalized_status, array( 'pending', 'completed', 'dismissed' ), true ) ) {
			$normalized_status = $defaults['status'];
		}

		$current_step = isset( $status['current_step'] ) ? absint( $status['current_step'] ) : $defaults['current_step'];
		if ( $current_step < 1 || $current_step > 4 ) {
			$current_step = $defaults['current_step'];
		}

		$completed_at = null;
		if ( isset( $status['completed_at'] ) && is_string( $status['completed_at'] ) && '' !== trim( $status['completed_at'] ) ) {
			$completed_at = sanitize_text_field( $status['completed_at'] );
		}

		$dismissed_at = null;
		if ( isset( $status['dismissed_at'] ) && is_string( $status['dismissed_at'] ) && '' !== trim( $status['dismissed_at'] ) ) {
			$dismissed_at = sanitize_text_field( $status['dismissed_at'] );
		}

		$steps_completed = array();
		if ( isset( $status['steps_completed'] ) && is_array( $status['steps_completed'] ) ) {
			foreach ( $status['steps_completed'] as $step ) {
				$step = absint( $step );
				if ( $step >= 1 && $step <= 4 && ! in_array( $step, $steps_completed, true ) ) {
					$steps_completed[] = $step;
				}
			}
		}

		return array(
			'status'          => $normalized_status,
			'current_step'    => $current_step,
			'completed_at'    => $completed_at,
			'dismissed_at'    => $dismissed_at,
			'steps_completed' => $steps_completed,
		);
	}

	/**
	 * Default status payload.
	 *
	 * @return array
	 */
	private function default_status() {
		return array(
			'status'          => 'pending',
			'current_step'    => 1,
			'completed_at'    => null,
			'dismissed_at'    => null,
			'steps_completed' => array(),
		);
	}
}

new Bookit_Setup_Guide_API();
