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
		// ── REST API (Task 3) ────────────────────────────────────────────────
		require_once BOOKIT_MEETINGS_PLUGIN_DIR . 'api/class-meetings-api.php';
		add_action( 'rest_api_init', array( new Bookit_Meetings_Api(), 'register_routes' ) );
		// ── Link generation (Task 4) ─────────────────────────────────────────
		require_once BOOKIT_MEETINGS_PLUGIN_DIR . 'includes/class-bookit-meetings-link-generator.php';
		$link_generator = new Bookit_Meetings_Link_Generator();
		add_action( 'bookit_after_booking_confirmed', array( $link_generator, 'handle_booking_confirmed' ), 10, 2 );
		add_action( 'bookit_after_booking_created', array( $link_generator, 'handle_booking_created' ), 10, 2 );
		// ── Customer surfaces (Task 5) ───────────────────────────────────────
		// ── Staff notification email (Task 6) ─────────────────────────────────
	}
}

