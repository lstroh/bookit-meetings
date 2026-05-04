<?php
/**
 * Admin menu structure.
 *
 * @package    Bookit_Booking_System
 * @subpackage Bookit_Booking_System/admin
 */

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
	die;
}

/**
 * Admin menu class.
 */
class Bookit_Admin_Menu {

	/**
	 * Register admin menu.
	 */
	public function register_menu() {
		// Main menu page
		add_menu_page(
			__( 'Booking System', 'bookit-booking-system' ),           // Page title
			__( 'Booking System', 'bookit-booking-system' ),           // Menu title
			'manage_options',                                   // Capability
			'bookit-booking-system',                                   // Menu slug
			array( $this, 'render_bookings_page' ),            // Callback
			'dashicons-calendar-alt',                          // Icon
			30                                                  // Position
		);

		// Bookings submenu
		add_submenu_page(
			'bookit-booking-system',
			__( 'Bookings', 'bookit-booking-system' ),
			__( 'Bookings', 'bookit-booking-system' ),
			'manage_options',
			'bookit-booking-system',
			array( $this, 'render_bookings_page' )
		);

		add_submenu_page(
			'bookit-booking-system',
			__( 'Calendar View', 'bookit-booking-system' ),
			__( 'Calendar View', 'bookit-booking-system' ),
			'manage_options',
			'bookit-calendar',
			array( $this, 'render_calendar_page' )
		);

		add_submenu_page(
			'bookit-booking-system',
			__( 'Add New Booking', 'bookit-booking-system' ),
			__( 'Add New', 'bookit-booking-system' ),
			'manage_options',
			'bookit-add-new',
			array( $this, 'render_add_booking_page' )
		);

		// Services submenu
		add_submenu_page(
			'bookit-booking-system',
			__( 'Services', 'bookit-booking-system' ),
			__( 'Services', 'bookit-booking-system' ),
			'manage_options',
			'bookit-services',
			array( $this, 'render_services_page' )
		);

		add_submenu_page(
			'bookit-booking-system',
			__( 'Service Categories', 'bookit-booking-system' ),
			__( 'Categories', 'bookit-booking-system' ),
			'manage_options',
			'bookit-service-categories',
			array( $this, 'render_categories_page' )
		);

		add_submenu_page(
			'bookit-booking-system',
			__( 'Add New Service', 'bookit-booking-system' ),
			__( 'Add New', 'bookit-booking-system' ),
			'manage_options',
			'bookit-add-service',
			array( $this, 'render_add_service_page' )
		);

		// Staff submenu
		add_submenu_page(
			'bookit-booking-system',
			__( 'Staff', 'bookit-booking-system' ),
			__( 'Staff', 'bookit-booking-system' ),
			'manage_options',
			'bookit-staff',
			array( $this, 'render_staff_page' )
		);

		add_submenu_page(
			'bookit-booking-system',
			__( 'Add New Staff', 'bookit-booking-system' ),
			__( 'Add New', 'bookit-booking-system' ),
			'manage_options',
			'bookit-add-staff',
			array( $this, 'render_add_staff_page' )
		);

		// Customers submenu
		add_submenu_page(
			'bookit-booking-system',
			__( 'Customers', 'bookit-booking-system' ),
			__( 'Customers', 'bookit-booking-system' ),
			'manage_options',
			'bookit-customers',
			array( $this, 'render_customers_page' )
		);

		add_submenu_page(
			'bookit-booking-system',
			__( 'Export Customers', 'bookit-booking-system' ),
			__( 'Export', 'bookit-booking-system' ),
			'manage_options',
			'bookit-export-customers',
			array( $this, 'render_export_page' )
		);

		// Payment Settings submenu
		add_submenu_page(
			'bookit-booking-system',
			__( 'Payment Settings', 'bookit-booking-system' ),
			__( 'Payment Settings', 'bookit-booking-system' ),
			'manage_options',
			'bookit-payment-settings',
			array( $this, 'render_payment_settings_page' )
		);

		// Settings submenu
		add_submenu_page(
			'bookit-booking-system',
			__( 'Settings', 'bookit-booking-system' ),
			__( 'Settings', 'bookit-booking-system' ),
			'manage_options',
			'bookit-settings',
			array( $this, 'render_settings_page' )
		);
	}

	/**
	 * Render bookings page.
	 */
	public function render_bookings_page() {
		require_once BOOKIT_PLUGIN_DIR . 'admin/pages/bookings.php';
	}

	/**
	 * Render calendar page.
	 */
	public function render_calendar_page() {
		require_once BOOKIT_PLUGIN_DIR . 'admin/pages/calendar.php';
	}

	/**
	 * Render add booking page.
	 */
	public function render_add_booking_page() {
		require_once BOOKIT_PLUGIN_DIR . 'admin/pages/add-booking.php';
	}

	/**
	 * Render services page.
	 */
	public function render_services_page() {
		require_once BOOKIT_PLUGIN_DIR . 'admin/pages/services.php';
	}

	/**
	 * Render categories page.
	 */
	public function render_categories_page() {
		require_once BOOKIT_PLUGIN_DIR . 'admin/pages/categories.php';
	}

	/**
	 * Render add service page.
	 */
	public function render_add_service_page() {
		require_once BOOKIT_PLUGIN_DIR . 'admin/pages/add-service.php';
	}

	/**
	 * Render staff page.
	 */
	public function render_staff_page() {
		require_once BOOKIT_PLUGIN_DIR . 'admin/pages/staff.php';
	}

	/**
	 * Render add staff page.
	 */
	public function render_add_staff_page() {
		require_once BOOKIT_PLUGIN_DIR . 'admin/pages/add-staff.php';
	}

	/**
	 * Render customers page.
	 */
	public function render_customers_page() {
		require_once BOOKIT_PLUGIN_DIR . 'admin/pages/customers.php';
	}

	/**
	 * Render export page.
	 */
	public function render_export_page() {
		require_once BOOKIT_PLUGIN_DIR . 'admin/pages/export.php';
	}

	/**
	 * Render payment settings page.
	 */
	public function render_payment_settings_page() {
		require_once BOOKIT_PLUGIN_DIR . 'admin/pages/payment-settings.php';
	}

	/**
	 * Render settings page.
	 */
	public function render_settings_page() {
		require_once BOOKIT_PLUGIN_DIR . 'admin/pages/settings.php';
	}
}
