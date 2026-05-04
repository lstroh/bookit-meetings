<?php
/**
 * Meetings extension loader.
 *
 * @package Bookit_Meetings
 */

if ( ! defined( 'WPINC' ) ) {
	die;
}

class Bookit_Meetings_Loader {
	/**
	 * Register hooks for the extension.
	 *
	 * @return void
	 */
	public function init(): void {
		// Sidebar nav item (Sprint 1, Task 1).
		if ( function_exists( 'bookit_register_nav_item' ) ) {
			bookit_register_nav_item(
				array(
					'label'      => 'Meetings',
					'route'      => '/bookit-dashboard/app/meetings',
					'icon'       => 'video',
					'position'   => 60,
					'capability' => 'bookit_manage_all',
					'slug'       => 'bookit-meetings',
				)
			);
		}

		// ── Migrations (Task 2) ──────────────────────────────────────────────
		bookit_register_migration_path(
			'bookit-meetings',
			BOOKIT_MEETINGS_PLUGIN_DIR . 'database/migrations/'
		);

		// ── Dashboard assets + JS data + booking response (Sprint 2, Task 1) ──
		require_once BOOKIT_MEETINGS_PLUGIN_DIR . 'includes/class-bookit-meetings-assets.php';
		$assets = new Bookit_Meetings_Assets();
		add_action( 'bookit_dashboard_loaded', array( $assets, 'enqueue_dashboard_assets' ) );
		add_action(
			'bookit_dashboard_extension_content',
			static function (): void {
				$uri = $_SERVER['REQUEST_URI'] ?? '';
				if ( strpos( $uri, '/bookit-dashboard/app/meetings' ) === false ) {
					return;
				}
				echo '<div id="bookit-meetings-app"></div>';
			}
		);
		add_filter( 'bookit_dashboard_js_data', array( $assets, 'add_dashboard_js_data' ) );
		add_filter( 'bookit_booking_response', array( $assets, 'add_meeting_link_to_booking_response' ), 10, 2 );

		// ── REST API (Task 3) ────────────────────────────────────────────────
		require_once BOOKIT_MEETINGS_PLUGIN_DIR . 'api/class-meetings-api.php';
		add_action( 'rest_api_init', array( new Bookit_Meetings_Api(), 'register_routes' ) );
		// ── Link generation (Task 4) ─────────────────────────────────────────
		require_once BOOKIT_MEETINGS_PLUGIN_DIR . 'includes/class-bookit-meetings-link-generator.php';
		$link_generator = new Bookit_Meetings_Link_Generator();
		add_action( 'bookit_after_booking_confirmed', array( $link_generator, 'handle_booking_confirmed' ), 10, 2 );
		add_action( 'bookit_after_booking_created', array( $link_generator, 'handle_booking_created' ), 10, 2 );
		// ── Customer surfaces (Task 5) ───────────────────────────────────────
		require_once BOOKIT_MEETINGS_PLUGIN_DIR . 'includes/class-bookit-meetings-customer-surfaces.php';
		$customer_surfaces = new Bookit_Meetings_Customer_Surfaces();
		add_filter( 'bookit_confirmation_meeting_section', array( $customer_surfaces, 'confirmation_page_section' ), 10, 2 );
		add_filter( 'bookit_email_meeting_section', array( $customer_surfaces, 'confirmation_email_section' ), 10, 2 );
		// ── Staff notification email (Task 6) ─────────────────────────────────
	}
}

