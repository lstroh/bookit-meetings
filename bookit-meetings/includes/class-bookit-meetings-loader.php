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
		// ── REST API (Task 3) ────────────────────────────────────────────────
		// ── Link generation (Task 4) ─────────────────────────────────────────
		// ── Customer surfaces (Task 5) ───────────────────────────────────────
		// ── Staff notification email (Task 6) ─────────────────────────────────
	}
}

