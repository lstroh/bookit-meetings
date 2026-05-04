<?php
/**
 * The core plugin class.
 *
 * @package    Bookit_Booking_System
 * @subpackage Bookit_Booking_System/includes
 */

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
	die;
}

/**
 * The core plugin class.
 */
class Bookit_Loader {

	/**
	 * The unique identifier of this plugin.
	 *
	 * @var string
	 */
	protected $plugin_name;

	/**
	 * The current version of the plugin.
	 *
	 * @var string
	 */
	protected $version;

	/**
	 * Define the core functionality of the plugin.
	 *
	 * @return void
	 */
	public function __construct() {
		$this->version     = defined( 'BOOKIT_VERSION' ) ? BOOKIT_VERSION : '1.5.1';
		$this->plugin_name = 'bookit-booking-system';

		$this->load_dependencies();
		add_action( 'plugins_loaded', array( $this, 'run_pending_migrations' ), 20 );
		$this->define_rewrite_rules();
		$this->define_admin_hooks();
		$this->define_public_hooks();
		$this->define_cron_hooks();
	}

	/**
	 * Load the required dependencies for this plugin.
	 *
	 * @return void
	 */
	private function load_dependencies() {
		// Logger (load early for use in other classes).
		require_once BOOKIT_PLUGIN_DIR . 'includes/class-bookit-logger.php';
		require_once BOOKIT_PLUGIN_DIR . 'includes/class-bookit-migration-runner.php';
		require_once BOOKIT_PLUGIN_DIR . 'includes/functions-migration.php';
		require_once BOOKIT_PLUGIN_DIR . 'includes/class-bookit-error-registry.php';
		require_once BOOKIT_PLUGIN_DIR . 'includes/config/error-codes.php';
		require_once BOOKIT_PLUGIN_DIR . 'includes/functions-cooling-off.php';

		// Database management.
		require_once BOOKIT_PLUGIN_DIR . 'includes/class-bookit-database.php';

		// Dashboard authentication/session.
		require_once BOOKIT_PLUGIN_DIR . 'includes/class-bookit-session.php';
		require_once BOOKIT_PLUGIN_DIR . 'includes/class-bookit-auth.php';
		require_once BOOKIT_PLUGIN_DIR . 'includes/class-bookit-audit-logger.php';

		// Booking wizard session manager.
		require_once BOOKIT_PLUGIN_DIR . 'includes/core/class-session-manager.php';
		require_once BOOKIT_PLUGIN_DIR . 'includes/utils/class-bookit-reference-generator.php';

		// Extension registry and helper functions.
		require_once BOOKIT_PLUGIN_DIR . 'includes/class-bookit-extension-registry.php';
		require_once BOOKIT_PLUGIN_DIR . 'includes/functions-extensions.php';

		// CSRF protection.
		require_once BOOKIT_PLUGIN_DIR . 'includes/class-csrf-protection.php';
		require_once BOOKIT_PLUGIN_DIR . 'includes/class-bookit-rate-limiter.php';

		// Admin-specific functionality.
		require_once BOOKIT_PLUGIN_DIR . 'admin/class-bookit-admin.php';

		// Public-facing functionality.
		require_once BOOKIT_PLUGIN_DIR . 'public/class-bookit-public.php';

		// Template loader (theme override support).
		require_once BOOKIT_PLUGIN_DIR . 'includes/class-bookit-template-loader.php';

		// Shortcode handler.
		require_once BOOKIT_PLUGIN_DIR . 'public/class-shortcodes.php';

		// REST API endpoints.
		require_once BOOKIT_PLUGIN_DIR . 'includes/api/class-wizard-api.php';
		
		// Service model.
		require_once BOOKIT_PLUGIN_DIR . 'includes/models/class-service-model.php';
		
		// Service API.
		require_once BOOKIT_PLUGIN_DIR . 'includes/api/class-service-api.php';
		
		// Staff model.
		require_once BOOKIT_PLUGIN_DIR . 'includes/models/class-staff-model.php';
		
		// Staff API.
		require_once BOOKIT_PLUGIN_DIR . 'includes/api/class-staff-api.php';

		// DateTime model.
		require_once BOOKIT_PLUGIN_DIR . 'includes/models/class-datetime-model.php';

		// DateTime API.
		require_once BOOKIT_PLUGIN_DIR . 'includes/api/class-datetime-api.php';

		// Contact API.
		require_once BOOKIT_PLUGIN_DIR . 'includes/api/class-contact-api.php';

		// Stripe configuration (payment).
		require_once BOOKIT_PLUGIN_DIR . 'includes/payment/class-stripe-config.php';

		// Stripe Checkout (payment).
		require_once BOOKIT_PLUGIN_DIR . 'includes/payment/class-stripe-checkout.php';

		// Payment processor (form submission, redirect to Stripe).
		require_once BOOKIT_PLUGIN_DIR . 'includes/payment/class-payment-processor.php';
		new Booking_System_Payment_Processor();

		// Webhook handling and booking creation.
		require_once BOOKIT_PLUGIN_DIR . 'includes/booking/class-booking-creator.php';
		require_once BOOKIT_PLUGIN_DIR . 'includes/api/class-stripe-webhook.php';

		// Google Calendar OAuth (per staff) — encryption + REST.
		require_once BOOKIT_PLUGIN_DIR . 'includes/utils/class-bookit-encryption.php';
		require_once BOOKIT_PLUGIN_DIR . 'includes/integrations/class-bookit-google-calendar-api.php';
		require_once BOOKIT_PLUGIN_DIR . 'includes/integrations/class-bookit-google-calendar.php';
		require_once BOOKIT_PLUGIN_DIR . 'includes/api/class-bookit-google-calendar-rest-controller.php';
		Bookit_Google_Calendar_Rest_Controller::init();

		// Dashboard Bookings API (Today's Schedule).
		require_once BOOKIT_PLUGIN_DIR . 'includes/api/class-dashboard-bookings-api.php';
		require_once BOOKIT_PLUGIN_DIR . 'includes/api/class-audit-log-api.php';
		new Bookit_Audit_Log_API();
		require_once BOOKIT_PLUGIN_DIR . 'includes/api/class-email-queue-api.php';
		new Bookit_Email_Queue_API();

		// Extensions API.
		require_once BOOKIT_PLUGIN_DIR . 'includes/api/class-extensions-api.php';
		new Bookit_Extensions_API();

		// Reports API.
		require_once BOOKIT_PLUGIN_DIR . 'includes/api/class-reports-api.php';
		new Bookit_Reports_API();

		// Customers API.
		require_once BOOKIT_PLUGIN_DIR . 'includes/api/class-customers-api.php';
		new Bookit_Customers_API();

		// Dashboard Timeslots API (manual booking wizard).
		require_once BOOKIT_PLUGIN_DIR . 'includes/api/class-dashboard-timeslots-api.php';

		// Team Calendar API (admin day/week team view).
		require_once BOOKIT_PLUGIN_DIR . 'includes/api/class-team-calendar-api.php';

		// Setup Guide API (admin onboarding status).
		require_once BOOKIT_PLUGIN_DIR . 'includes/api/class-setup-guide-api.php';
		require_once BOOKIT_PLUGIN_DIR . 'includes/api/class-package-types-api.php';
		new Bookit_Package_Types_API();
		require_once BOOKIT_PLUGIN_DIR . 'includes/api/class-customer-packages-api.php';
		new Bookit_Customer_Packages_API();
		require_once BOOKIT_PLUGIN_DIR . 'includes/api/class-available-packages-api.php';
		new Bookit_Available_Packages_API();
		require_once BOOKIT_PLUGIN_DIR . 'includes/api/class-customer-package-lookup-api.php';
		new Bookit_Customer_Package_Lookup_API();
		require_once BOOKIT_PLUGIN_DIR . 'includes/api/class-package-redemption-api.php';
		new Bookit_Package_Redemption_API();

		// Notification provider interfaces.
		require_once BOOKIT_PLUGIN_DIR . 'includes/notifications/interfaces/interface-bookit-email-provider.php';
		require_once BOOKIT_PLUGIN_DIR . 'includes/notifications/interfaces/interface-bookit-sms-provider.php';

		// Notification provider implementations.
		require_once BOOKIT_PLUGIN_DIR . 'includes/notifications/providers/class-bookit-brevo-email-provider.php';
		require_once BOOKIT_PLUGIN_DIR . 'includes/notifications/providers/class-bookit-wp-mail-fallback-provider.php';
		require_once BOOKIT_PLUGIN_DIR . 'includes/notifications/providers/class-bookit-brevo-sms-provider.php';

		// Notification queue and dispatcher.
		require_once BOOKIT_PLUGIN_DIR . 'includes/notifications/class-bookit-email-queue.php';
		require_once BOOKIT_PLUGIN_DIR . 'includes/functions-notifications.php';
		require_once BOOKIT_PLUGIN_DIR . 'includes/integrations/class-bookit-google-calendar-sync.php';
		require_once BOOKIT_PLUGIN_DIR . 'includes/notifications/class-bookit-notification-dispatcher.php';
		require_once BOOKIT_PLUGIN_DIR . 'includes/notifications/class-bookit-staff-notifier.php';
		require_once BOOKIT_PLUGIN_DIR . 'includes/cron/class-bookit-staff-digest-daily.php';
		require_once BOOKIT_PLUGIN_DIR . 'includes/cron/class-bookit-staff-digest-weekly.php';
		require_once BOOKIT_PLUGIN_DIR . 'includes/cron/class-bookit-staff-schedule-daily.php';
		require_once BOOKIT_PLUGIN_DIR . 'includes/notifications/class-bookit-notification-exception.php';

		// Booking retrieval and email (confirmation page).
		require_once BOOKIT_PLUGIN_DIR . 'includes/booking/class-booking-retriever.php';
		require_once BOOKIT_PLUGIN_DIR . 'includes/email/class-email-sender.php';

		// Session cleanup cron.
		require_once BOOKIT_PLUGIN_DIR . 'includes/cron/class-session-cleanup.php';
		require_once BOOKIT_PLUGIN_DIR . 'includes/cron/class-bookit-audit-retention.php';
	}

	/**
	 * Register custom rewrite rules for dashboard.
	 *
	 * @return void
	 */
	private function define_rewrite_rules() {
		add_action( 'init', array( $this, 'add_dashboard_rewrite_rules' ) );
		add_filter( 'query_vars', array( $this, 'add_dashboard_query_vars' ) );
		add_action( 'template_redirect', array( $this, 'dashboard_template_redirect' ) );
	}

	/**
	 * Add dashboard rewrite rules.
	 *
	 * @return void
	 */
	public function add_dashboard_rewrite_rules() {
		// Dashboard login page.
		add_rewrite_rule(
			'^bookit-dashboard/?$',
			'index.php?bookit_dashboard_page=login',
			'top'
		);

		// First admin setup page (one-time).
		add_rewrite_rule(
			'^bookit-dashboard/setup/?$',
			'index.php?bookit_dashboard_page=setup',
			'top'
		);

		// Dashboard home page.
		add_rewrite_rule(
			'^bookit-dashboard/home/?$',
			'index.php?bookit_dashboard_page=home',
			'top'
		);

		// Dashboard logout.
		add_rewrite_rule(
			'^bookit-dashboard/logout/?$',
			'index.php?bookit_dashboard_page=logout',
			'top'
		);

		// Dashboard Vue app (SPA catch-all).
		add_rewrite_rule(
			'^bookit-dashboard/app(/.*)?$',
			'index.php?bookit_dashboard_page=app',
			'top'
		);
	}

	/**
	 * Add dashboard query vars.
	 *
	 * @param array $vars Query vars.
	 * @return array Modified query vars.
	 */
	public function add_dashboard_query_vars( $vars ) {
		$vars[] = 'bookit_dashboard_page';
		return $vars;
	}

	/**
	 * Handle dashboard template redirects.
	 *
	 * @return void
	 */
	public function dashboard_template_redirect() {
		$page = get_query_var( 'bookit_dashboard_page', '' );

		if ( empty( $page ) ) {
			return;
		}

		switch ( $page ) {
			case 'setup':
				require_once BOOKIT_PLUGIN_DIR . 'dashboard/setup.php';
				exit;

			case 'login':
				require_once BOOKIT_PLUGIN_DIR . 'dashboard/index.php';
				exit;

			case 'home':
				require_once BOOKIT_PLUGIN_DIR . 'dashboard/dashboard-home.php';
				exit;

			case 'logout':
				require_once BOOKIT_PLUGIN_DIR . 'dashboard/logout.php';
				exit;

			case 'app':
				require_once BOOKIT_PLUGIN_DIR . 'dashboard/app/index.php';
				exit;

			default:
				wp_redirect( home_url( '/bookit-dashboard/' ) );
				exit;
		}
	}

	/**
	 * Register all hooks related to the admin area functionality.
	 *
	 * @return void
	 */
	private function define_admin_hooks() {
		$plugin_admin = new Bookit_Admin( $this->get_plugin_name(), $this->get_version() );

		add_action( 'admin_enqueue_scripts', array( $plugin_admin, 'enqueue_styles' ) );
		add_action( 'admin_enqueue_scripts', array( $plugin_admin, 'enqueue_scripts' ) );

		// Load admin menu class
		require_once BOOKIT_PLUGIN_DIR . 'admin/class-bookit-admin-menu.php';
		$admin_menu = new Bookit_Admin_Menu();

		// Register admin menu
		add_action( 'admin_menu', array( $admin_menu, 'register_menu' ) );

		// Stripe settings (registration, allowed_options filter, form renderer)
		require_once BOOKIT_PLUGIN_DIR . 'admin/settings/stripe-settings.php';
	}

	/**
	 * Register all hooks related to the public-facing functionality.
	 *
	 * @return void
	 */
	private function define_public_hooks() {
		$plugin_public = new Bookit_Public( $this->get_plugin_name(), $this->get_version() );

		add_action( 'wp_enqueue_scripts', array( $plugin_public, 'enqueue_styles' ) );
		add_action( 'wp_enqueue_scripts', array( $plugin_public, 'enqueue_scripts' ) );

		// Initialize session on front-end (non-admin) requests for booking wizard.
		add_action( 'init', array( $this, 'maybe_init_booking_session' ), 1 );

		// Initialize shortcode handler.
		$shortcodes = new Bookit_Shortcodes();

		// Initialize REST API.
		$wizard_api  = new Bookit_Wizard_API();
		$contact_api = new Bookit_Contact_API();
	}

	/**
	 * Register cron hooks.
	 *
	 * @return void
	 */
	private function define_cron_hooks() {
		// Log cleanup cron.
		add_action( 'bookit_cleanup_logs', array( 'Bookit_Logger', 'cleanup_old_logs' ) );

		// Abandoned session cleanup cron.
		add_action( 'bookit_cleanup_abandoned_sessions', array( 'Bookit_Session_Cleanup', 'run_cleanup' ) );

		// Idempotency cleanup cron (Sprint 2, Task 6).
		add_action( 'bookit_cleanup_expired_idempotency', array( 'Bookit_Idempotency_Cleanup', 'run_cleanup_with_tracking' ) );

		// Audit retention cleanup cron.
		add_action( 'bookit_audit_retention', array( 'Bookit_Audit_Retention', 'run' ) );

		// Email queue processor -- fired by Action Scheduler or WP-Cron.
		add_action(
			'bookit_process_email_queue',
			function( int $queue_id ) {
				Bookit_Notification_Dispatcher::process_email_queue_item( $queue_id );
			}
		);

		// Google Calendar sync processor -- fired by Action Scheduler or WP-Cron.
		add_action(
			'bookit_process_calendar_sync',
			array( 'Bookit_Google_Calendar', 'process_sync_job' ),
			10,
			3
		);

		// Cancel pending queue items when a booking is cancelled or rescheduled.
		add_action(
			'bookit_after_booking_cancelled',
			function( int $booking_id ) {
				Bookit_Email_Queue::cancel_for_booking( $booking_id );
			},
			10,
			1
		);
		// bookit_booking_rescheduled -- fired from update_booking() (dashboard) and reschedule_booking_magic_link() (magic link).
		add_action(
			'bookit_booking_rescheduled',
			function( int $booking_id ) {
				Bookit_Email_Queue::cancel_for_booking( $booking_id );
			},
			10,
			1
		);

		require_once BOOKIT_PLUGIN_DIR . 'includes/cron/class-bookit-package-expiry.php';
		Bookit_Package_Expiry::init();
		Bookit_Google_Calendar_Sync::init();
		Bookit_Staff_Notifier::init();
		Bookit_Staff_Digest_Daily::init();
		Bookit_Staff_Digest_Weekly::init();
		Bookit_Staff_Schedule_Daily::init();
	}

	/**
	 * Initialize booking session on front-end requests (not admin or cron).
	 *
	 * @return void
	 */
	public function maybe_init_booking_session() {
		if ( is_admin() || wp_doing_cron() ) {
			return;
		}
		Bookit_Session_Manager::init();
	}

	/**
	 * Run pending migrations on version upgrades.
	 *
	 * @return void
	 */
	public function run_pending_migrations(): void {
		$installed_version = get_option( 'bookit_version', '0.0.0' );

		if ( version_compare( $installed_version, BOOKIT_VERSION, '<' ) ) {
			Bookit_Migration_Runner::run_pending();
			update_option( 'bookit_version', BOOKIT_VERSION );
		}
	}

	/**
	 * Run the loader to execute all hooks with WordPress.
	 *
	 * @return void
	 */
	public function run() {
		// Hooks are registered during construction.
	}

	/**
	 * The name of the plugin.
	 *
	 * @return string The name of the plugin.
	 */
	public function get_plugin_name() {
		return $this->plugin_name;
	}

	/**
	 * Retrieve the version number of the plugin.
	 *
	 * @return string The version number of the plugin.
	 */
	public function get_version() {
		return $this->version;
	}
}
