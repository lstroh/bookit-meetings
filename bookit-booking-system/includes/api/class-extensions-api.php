<?php
/**
 * Extensions REST API Controller.
 *
 * @package    Bookit_Booking_System
 * @subpackage Bookit_Booking_System/includes/api
 */

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
	die;
}

/**
 * Class Bookit_Extensions_API
 */
class Bookit_Extensions_API {

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
		register_rest_route(
			self::NAMESPACE,
			'/extensions',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'get_extensions' ),
				'permission_callback' => array( $this, 'check_dashboard_permission' ),
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
			return Bookit_Error_Registry::to_wp_error( 'E1003' );
		}

		return true;
	}

	/**
	 * Get registered extensions and nav items.
	 *
	 * @return WP_REST_Response
	 */
	public function get_extensions() {
		$extensions = array_map(
			function ( $extension ) {
				return array(
					'name'          => $extension['name'] ?? '',
					'slug'          => $extension['slug'] ?? '',
					'version'       => $extension['version'] ?? '',
					'requires_core' => $extension['requires_core'] ?? '',
					'description'   => $extension['description'] ?? '',
					'author'        => $extension['author'] ?? '',
				);
			},
			Bookit_Extension_Registry::get_extensions()
		);
		$nav_items  = Bookit_Extension_Registry::get_nav_items();

		// Allow extensions to customize sidebar navigation items.
		$nav_items = apply_filters( 'bookit_sidebar_nav_items', $nav_items );

		return rest_ensure_response(
			array(
				'extensions' => $extensions,
				'nav_items'  => $nav_items,
			)
		);
	}
}
