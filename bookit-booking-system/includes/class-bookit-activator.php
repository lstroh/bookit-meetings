<?php
/**
 * Fired during plugin activation.
 *
 * @package    Bookit_Booking_System
 * @subpackage Bookit_Booking_System/includes
 */

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
	die;
}

/**
 * Fired during plugin activation.
 */
class Bookit_Activator {

	/**
	 * Activation tasks.
	 *
	 * - Create database tables (handled in Tasks 2 & 3)
	 * - Set default options
	 * - Check system requirements
	 * - Create log directory
	 *
	 * @return void
	 */
	public static function activate() {
		// Check PHP version.
		if ( version_compare( PHP_VERSION, '8.0', '<' ) ) {
			if ( defined( 'BOOKIT_PLUGIN_FILE' ) ) {
				deactivate_plugins( plugin_basename( BOOKIT_PLUGIN_FILE ) );
			}

			wp_die(
				esc_html__( 'Booking System requires PHP 8.0 or higher.', 'bookit-booking-system' )
			);
		}

		// Check WordPress version.
		global $wp_version;
		if ( version_compare( $wp_version, '6.0', '<' ) ) {
			if ( defined( 'BOOKIT_PLUGIN_FILE' ) ) {
				deactivate_plugins( plugin_basename( BOOKIT_PLUGIN_FILE ) );
			}

			wp_die(
				esc_html__( 'Booking System requires WordPress 6.0 or higher.', 'bookit-booking-system' )
			);
		}

		// Set plugin version option.
		update_option( 'bookit_version', BOOKIT_VERSION );

		// Set default settings.
		$default_settings = array(
			'timezone'              => 'Europe/London',
			'currency'              => 'GBP',
			'date_format'           => 'd/m/Y',
			'time_format'           => 'H:i',
			'booking_buffer_before' => 0,
			'booking_buffer_after'  => 0,
			'min_booking_notice'    => 60, // 1 hour in minutes.
			'max_booking_advance'   => 90, // 90 days.
		);

		add_option( 'bookit_settings', $default_settings );

		// Add default setting for approval requirement.
		if ( false === get_option( 'bookit_require_approval' ) ) {
			add_option( 'bookit_require_approval', false );
		}

		// Create database tables (Part 1: Tables 1-5).
		require_once BOOKIT_PLUGIN_DIR . 'includes/class-bookit-database.php';
		Bookit_Database::create_tables();

		require_once BOOKIT_PLUGIN_DIR . 'includes/class-bookit-migration-runner.php';
		require_once BOOKIT_PLUGIN_DIR . 'includes/functions-migration.php';
		Bookit_Migration_Runner::create_migrations_table();
		Bookit_Migration_Runner::mark_as_run( 'migration-add-staff-working-hours', 'bookit-booking-system' );
		Bookit_Migration_Runner::mark_as_run( 'migration-add-status-log', 'bookit-booking-system' );
		Bookit_Migration_Runner::run_pending();

		// Keep explicit legacy migration calls for fresh installs and backward compatibility.
		require_once BOOKIT_PLUGIN_DIR . 'database/migrations/migration-add-staff-working-hours.php';
		$staff_working_hours_migration = new Bookit_Migration_Add_Staff_Working_Hours();
		$staff_working_hours_migration->up();

		// Run migration for status log table (Sprint 4A, Task 1).
		require_once BOOKIT_PLUGIN_DIR . 'database/migrations/migration-add-status-log.php';
		$status_log_migration = new Bookit_Migration_Add_Status_Log();
		$status_log_migration->up();

		// Schedule log cleanup (daily at 3 AM)
		if ( ! wp_next_scheduled( 'bookit_cleanup_logs' ) ) {
			wp_schedule_event( strtotime( '03:00:00' ), 'daily', 'bookit_cleanup_logs' );
		}

		// Schedule abandoned session cleanup (daily at 3:30 AM)
		require_once BOOKIT_PLUGIN_DIR . 'includes/cron/class-session-cleanup.php';
		Bookit_Session_Cleanup::register_cron();

		// Schedule idempotency cleanup (daily at 3:00 AM) - Sprint 2, Task 6.
		require_once BOOKIT_PLUGIN_DIR . 'includes/cron/class-idempotency-cleanup.php';
		Bookit_Idempotency_Cleanup::register_cron();

		// Schedule audit retention cleanup (daily at 4:00 AM).
		require_once BOOKIT_PLUGIN_DIR . 'includes/cron/class-bookit-audit-retention.php';
		Bookit_Audit_Retention::register_cron();

		require_once BOOKIT_PLUGIN_DIR . 'includes/cron/class-bookit-package-expiry.php';
		Bookit_Package_Expiry::register_cron();

		require_once BOOKIT_PLUGIN_DIR . 'includes/cron/class-bookit-staff-digest-daily.php';
		Bookit_Staff_Digest_Daily::register_cron();

		require_once BOOKIT_PLUGIN_DIR . 'includes/cron/class-bookit-staff-digest-weekly.php';
		Bookit_Staff_Digest_Weekly::register_cron();

		require_once BOOKIT_PLUGIN_DIR . 'includes/cron/class-bookit-staff-schedule-daily.php';
		Bookit_Staff_Schedule_Daily::register_cron();

		// Initialize logger (creates log directory in best location)
		require_once BOOKIT_PLUGIN_DIR . 'includes/class-bookit-logger.php';
		Bookit_Logger::init();

		// Migrate existing logs if needed
		Bookit_Logger::migrate_logs_if_needed();

		// Test logging system
		global $wp_version;
		if ( Bookit_Logger::test_logging() ) {
			Bookit_Logger::info( 'Plugin activated successfully', array(
				'version'       => BOOKIT_VERSION,
				'php_version'   => PHP_VERSION,
				'wp_version'    => $wp_version,
				'log_directory' => Bookit_Logger::get_log_directory(),
				'is_secure'     => Bookit_Logger::is_secure_location() ? 'YES (outside web root)' : 'NO (inside uploads)',
			) );
		} else {
			// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
			error_log( '[Bookit Booking System] WARNING: Log directory not writable' );
		}

		$my_packages_page = get_page_by_path( 'my-packages' );
		if ( ! $my_packages_page ) {
			wp_insert_post(
				array(
					'post_title'   => 'My Packages',
					'post_name'    => 'my-packages',
					'post_content' => '[bookit_my_packages]',
					'post_status'  => 'publish',
					'post_type'    => 'page',
				)
			);
		}

		// Create V2 wizard page on activation.
		$wizard_v2_page = get_page_by_path( 'book-v2' );
		if ( ! $wizard_v2_page ) {
			wp_insert_post(
				array(
					'post_title'   => 'Book Online',
					'post_name'    => 'book-v2',
					'post_content' => '[bookit_wizard_v2]',
					'post_status'  => 'publish',
					'post_type'    => 'page',
				)
			);
		}

		// Create V2 confirmation page on activation.
		$confirmed_v2_page = get_page_by_path( 'booking-confirmed-v2' );
		if ( ! $confirmed_v2_page ) {
			wp_insert_post(
				array(
					'post_title'   => 'Booking Confirmed',
					'post_name'    => 'booking-confirmed-v2',
					'post_content' => '[bookit_booking_confirmed_v2]',
					'post_status'  => 'publish',
					'post_type'    => 'page',
				)
			);
		}

		if ( ! get_page_by_path( 'bookit-cancel' ) ) {
			wp_insert_post(
				array(
					'post_title'   => 'Cancel Booking',
					'post_name'    => 'bookit-cancel',
					'post_content' => '[bookit_cancel_booking]',
					'post_status'  => 'publish',
					'post_type'    => 'page',
				)
			);
		}

		if ( ! get_page_by_path( 'bookit-reschedule' ) ) {
			wp_insert_post(
				array(
					'post_title'   => 'Reschedule Booking',
					'post_name'    => 'bookit-reschedule',
					'post_content' => '[bookit_reschedule_booking]',
					'post_status'  => 'publish',
					'post_type'    => 'page',
				)
			);
		}

		if ( ! get_page_by_path( 'bookit-email-changed' ) ) {
			wp_insert_post(
				array(
					'post_title'   => 'Email Updated',
					'post_name'    => 'bookit-email-changed',
					'post_content' => '[bookit_email_changed]',
					'post_status'  => 'publish',
					'post_type'    => 'page',
				)
			);
		}
		global $wpdb;  // Declare global first

		// Seed dashboard branding defaults if missing.
		$branding_defaults = array(
			'branding_logo_url'           => array(
				'value' => '',
				'type'  => 'string',
			),
			'branding_primary_colour'     => array(
				'value' => '#4F46E5',
				'type'  => 'string',
			),
			'branding_business_name'      => array(
				'value' => '',
				'type'  => 'string',
			),
			'branding_powered_by_visible' => array(
				'value' => '1',
				'type'  => 'boolean',
			),
		);

		foreach ( $branding_defaults as $setting_key => $setting_data ) {
			$exists = $wpdb->get_var(
				$wpdb->prepare(
					"SELECT id FROM {$wpdb->prefix}bookings_settings WHERE setting_key = %s",
					$setting_key
				)
			);

			if ( $exists ) {
				continue;
			}

			$wpdb->insert(
				$wpdb->prefix . 'bookings_settings',
				array(
					'setting_key'   => $setting_key,
					'setting_value' => $setting_data['value'],
					'setting_type'  => $setting_data['type'],
				),
				array( '%s', '%s', '%s' )
			);
		}

		// Create email templates table.
		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		$charset_collate = $wpdb->get_charset_collate();

		$sql = "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}bookings_email_templates (
			id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
			template_key VARCHAR(50) NOT NULL UNIQUE,
			subject VARCHAR(255) NOT NULL,
			body TEXT NOT NULL,
			enabled TINYINT(1) DEFAULT 1,
			created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
			updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
			INDEX idx_template_key (template_key)
		) $charset_collate;";

		dbDelta( $sql );

		// Seed default email templates if table is empty.
		$template_count = $wpdb->get_var(
			"SELECT COUNT(*) FROM {$wpdb->prefix}bookings_email_templates"
		);

		if ( 0 == $template_count ) {
			$default_templates = array(
				array(
					'template_key' => 'booking_confirmation',
					'subject'      => 'Booking Confirmed - {service_name}',
					'body'         => "Hi {customer_name},\n\nYour booking is confirmed!\n\n**Booking Details:**\nService: {service_name}\nDate: {date}\nTime: {time}\nStaff: {staff_name}\nLocation: {business_address}\n\nIf you need to make changes:\n- Reschedule: {reschedule_link}\n- Cancel: {cancel_link}\n\nThank you,\n{business_name}\n{business_phone}",
					'enabled'      => 1,
				),
				array(
					'template_key' => 'booking_reminder',
					'subject'      => 'Reminder: {service_name} tomorrow at {time}',
					'body'         => "Hi {customer_name},\n\nThis is a reminder about your booking tomorrow.\n\n**Booking Details:**\nService: {service_name}\nDate: {date}\nTime: {time}\nStaff: {staff_name}\nLocation: {business_address}\n\nWe look forward to seeing you!\n\nIf you need to make changes:\n- Reschedule: {reschedule_link}\n- Cancel: {cancel_link}\n\nSee you soon,\n{business_name}\n{business_phone}",
					'enabled'      => 1,
				),
				array(
					'template_key' => 'booking_cancelled',
					'subject'      => 'Booking Cancelled - {service_name}',
					'body'         => "Hi {customer_name},\n\nYour booking has been cancelled.\n\n**Cancelled Booking:**\nService: {service_name}\nDate: {date}\nTime: {time}\n\nIf this was a mistake or you'd like to rebook, please contact us or visit our website.\n\nThank you,\n{business_name}\n{business_phone}",
					'enabled'      => 1,
				),
				array(
					'template_key' => 'admin_new_booking',
					'subject'      => 'New Booking: {customer_name} - {service_name}',
					'body'         => "New booking received!\n\n**Customer:**\n{customer_name}\n{customer_email}\n{customer_phone}\n\n**Booking Details:**\nService: {service_name}\nDate: {date}\nTime: {time}\nStaff: {staff_name}\nDuration: {duration} minutes\n\n**Payment:**\nTotal: £{total_price}\nDeposit Paid: £{deposit_paid}\n\nView in dashboard: {dashboard_link}",
					'enabled'      => 1,
				),
				array(
					'template_key' => 'staff_new_booking',
					'subject'      => 'New Booking Assigned: {customer_name}',
					'body'         => "Hi {staff_name},\n\nYou have a new booking!\n\n**Customer:**\n{customer_name}\n{customer_phone}\n\n**Booking Details:**\nService: {service_name}\nDate: {date}\nTime: {time}\nDuration: {duration} minutes\n\nView in dashboard: {dashboard_link}",
					'enabled'      => 1,
				),
			);

			foreach ( $default_templates as $template ) {
				$wpdb->insert(
					$wpdb->prefix . 'bookings_email_templates',
					$template,
					array( '%s', '%s', '%s', '%d' )
				);
			}
		}

		// Flush rewrite rules (for dashboard endpoints).
		flush_rewrite_rules();
	}
}
