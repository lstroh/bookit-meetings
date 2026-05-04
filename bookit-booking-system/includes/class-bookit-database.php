<?php
/**
 * Database setup and management.
 *
 * @package    Bookit_Booking_System
 * @subpackage Bookit_Booking_System/includes
 */

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
	die;
}

/**
 * Database setup and management class.
 */
class Bookit_Database {

	/**
	 * Current database version.
	 *
	 * @var string
	 */
	const DB_VERSION = '1.0.3';


	/**
	 * Create all database tables.
	 *
	 * Uses dbDelta() function for safe table creation/updates.
	 *
	 * @return void
	 */
	public static function create_tables() {
		global $wpdb;

		$charset_collate = $wpdb->get_charset_collate();
		$table_prefix    = $wpdb->prefix;

		// Get current database version.
		$installed_version = get_option( 'bookit_db_version', '0' );

		Bookit_Logger::info(
			'Database tables creation started',
			array(
				'db_version' => self::DB_VERSION,
			)
		);

		// Only create tables if not already at current version.
		if ( version_compare( $installed_version, self::DB_VERSION, '<' ) ) {
			// Load WordPress upgrade functions.
			require_once ABSPATH . 'wp-admin/includes/upgrade.php';

			// Create tables.
			self::create_services_table( $table_prefix, $charset_collate );
			self::create_categories_table( $table_prefix, $charset_collate );
			self::create_service_categories_table( $table_prefix, $charset_collate );
			self::create_staff_table( $table_prefix, $charset_collate );
			self::create_staff_services_table( $table_prefix, $charset_collate );

			// Part 2: Tables 6-10
			self::create_customers_table( $table_prefix, $charset_collate );
			self::create_bookings_table( $table_prefix, $charset_collate );
			self::create_payments_table( $table_prefix, $charset_collate );
			self::create_settings_table( $table_prefix, $charset_collate );

			// Sprint 2: Idempotency table (Task 6).
			self::create_idempotency_table( $table_prefix, $charset_collate );

			// Update database version.
			update_option( 'bookit_db_version', self::DB_VERSION );

			Bookit_Logger::info(
				'Database tables created successfully',
				array(
					'tables_created' => 10,
				)
			);
		} else {
			Bookit_Logger::info(
				'Database already at current version, skipping table creation',
				array(
					'current_version' => $installed_version,
				)
			);
		}
	}

	/**
	 * Create wp_bookings_services table.
	 *
	 * @param string $table_prefix    WordPress table prefix.
	 * @param string $charset_collate Database charset collation.
	 * @return void
	 */
	private static function create_services_table( $table_prefix, $charset_collate ) {
		$table_name = $table_prefix . 'bookings_services';

		// NOTE: dbDelta() is picky about index formatting/spaces.
		$sql = "CREATE TABLE $table_name (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			name VARCHAR(255) NOT NULL,
			description TEXT NULL,
			duration INT UNSIGNED NOT NULL COMMENT 'Duration in minutes',
			price DECIMAL(10,2) NOT NULL DEFAULT 0.00,
			deposit_amount DECIMAL(10,2) NULL DEFAULT NULL COMMENT 'Optional deposit amount',
			deposit_type ENUM('fixed','percentage') DEFAULT 'fixed',
			buffer_before INT UNSIGNED DEFAULT 0 COMMENT 'Buffer time before appointment (minutes)',
			buffer_after INT UNSIGNED DEFAULT 0 COMMENT 'Buffer time after appointment (minutes)',
			is_active TINYINT(1) DEFAULT 1,
			display_order INT DEFAULT 0,
			created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
			updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
			deleted_at DATETIME NULL DEFAULT NULL COMMENT 'Soft delete timestamp',
			PRIMARY KEY  (id),
			KEY idx_is_active  (is_active),
			KEY idx_deleted_at  (deleted_at),
			KEY idx_display_order  (display_order)
		) $charset_collate;";

		dbDelta( $sql );
	}

	/**
	 * Create wp_bookings_categories table.
	 *
	 * @param string $table_prefix    WordPress table prefix.
	 * @param string $charset_collate Database charset collation.
	 * @return void
	 */
	private static function create_categories_table( $table_prefix, $charset_collate ) {
		$table_name = $table_prefix . 'bookings_categories';

		$sql = "CREATE TABLE $table_name (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			name VARCHAR(255) NOT NULL,
			description TEXT NULL,
			display_order INT DEFAULT 0,
			is_active TINYINT(1) DEFAULT 1,
			created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
			updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
			deleted_at DATETIME NULL DEFAULT NULL,
			PRIMARY KEY  (id),
			KEY idx_is_active  (is_active),
			KEY idx_deleted_at  (deleted_at),
			KEY idx_display_order  (display_order)
		) $charset_collate;";

		dbDelta( $sql );
	}

	/**
	 * Create wp_bookings_service_categories table (junction table).
	 *
	 * @param string $table_prefix    WordPress table prefix.
	 * @param string $charset_collate Database charset collation.
	 * @return void
	 */
	private static function create_service_categories_table( $table_prefix, $charset_collate ) {
		$table_name = $table_prefix . 'bookings_service_categories';

		$sql = "CREATE TABLE $table_name (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			service_id BIGINT UNSIGNED NOT NULL,
			category_id BIGINT UNSIGNED NOT NULL,
			created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
			PRIMARY KEY  (id),
			UNIQUE KEY unique_service_category  (service_id, category_id),
			KEY idx_service_id  (service_id),
			KEY idx_category_id  (category_id)
		) $charset_collate;";

		dbDelta( $sql );
	}

	/**
	 * Create wp_bookings_staff table.
	 *
	 * @param string $table_prefix    WordPress table prefix.
	 * @param string $charset_collate Database charset collation.
	 * @return void
	 */
	private static function create_staff_table( $table_prefix, $charset_collate ) {
		$table_name = $table_prefix . 'bookings_staff';

		$sql = "CREATE TABLE $table_name (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			email VARCHAR(255) NOT NULL,
			password_hash VARCHAR(255) NOT NULL,
			first_name VARCHAR(100) NOT NULL,
			last_name VARCHAR(100) NOT NULL,
			phone VARCHAR(20) NULL,
			photo_url VARCHAR(500) NULL COMMENT 'URL to uploaded staff photo',
			bio TEXT NULL COMMENT 'Short bio shown to customers (e.g., \"10+ years experience\")',
			title VARCHAR(100) NULL COMMENT 'Job title shown to customers (e.g., \"Senior Stylist\")',
			role ENUM('staff','admin') DEFAULT 'staff',
			google_calendar_id VARCHAR(255) NULL COMMENT 'For calendar sync',
			is_active TINYINT(1) DEFAULT 1,
			display_order INT DEFAULT 0,
			created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
			updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
			deleted_at DATETIME NULL DEFAULT NULL,
			PRIMARY KEY  (id),
			UNIQUE KEY unique_email  (email),
			KEY idx_role  (role),
			KEY idx_is_active  (is_active),
			KEY idx_deleted_at  (deleted_at)
		) $charset_collate;";

		dbDelta( $sql );
	}

	/**
	 * Create wp_bookings_staff_services table (junction table).
	 *
	 * @param string $table_prefix    WordPress table prefix.
	 * @param string $charset_collate Database charset collation.
	 * @return void
	 */
	private static function create_staff_services_table( $table_prefix, $charset_collate ) {
		$table_name = $table_prefix . 'bookings_staff_services';

		$sql = "CREATE TABLE $table_name (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			staff_id BIGINT UNSIGNED NOT NULL,
			service_id BIGINT UNSIGNED NOT NULL,
			custom_price DECIMAL(10,2) NULL COMMENT 'Staff-specific price override. If NULL, use service base price. Example: Service base = £35, Senior staff = £45, Junior = £30',
			created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
			PRIMARY KEY  (id),
			UNIQUE KEY unique_staff_service  (staff_id, service_id),
			KEY idx_staff_id  (staff_id),
			KEY idx_service_id  (service_id)
		) $charset_collate;";

		dbDelta( $sql );
	}

	/**
	 * Create wp_bookings_customers table.
	 *
	 * @param string $table_prefix    WordPress table prefix.
	 * @param string $charset_collate Database charset collation.
	 */
	private static function create_customers_table( $table_prefix, $charset_collate ) {
		$table_name = $table_prefix . 'bookings_customers';

		$sql = "CREATE TABLE $table_name (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			email VARCHAR(255) NOT NULL,
			first_name VARCHAR(100) NOT NULL,
			last_name VARCHAR(100) NOT NULL,
			phone VARCHAR(20) NOT NULL,
			marketing_consent TINYINT(1) DEFAULT 0 COMMENT 'GDPR marketing consent',
			marketing_consent_date DATETIME NULL COMMENT 'When consent was given',
			notes TEXT NULL COMMENT 'Internal staff notes about customer',
			created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
			updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
			deleted_at DATETIME NULL DEFAULT NULL,
			PRIMARY KEY (id),
			UNIQUE KEY unique_email (email),
			KEY idx_deleted_at (deleted_at),
			KEY idx_phone (phone)
		) $charset_collate;";

		dbDelta( $sql );
	}

	/**
	 * Create wp_bookings table (MAIN BOOKINGS TABLE).
	 *
	 * CRITICAL: Includes UNIQUE constraint on (staff_id, booking_date, start_time)
	 * to prevent double-booking at database level (Gap #1 resolution).
	 *
	 * @param string $table_prefix    WordPress table prefix.
	 * @param string $charset_collate Database charset collation.
	 */
	private static function create_bookings_table( $table_prefix, $charset_collate ) {
		$table_name = $table_prefix . 'bookings';

		$sql = "CREATE TABLE $table_name (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			booking_reference VARCHAR(12) NULL COMMENT 'Human-readable booking reference (BKYYMM-XXXX)',
			customer_id BIGINT UNSIGNED NOT NULL,
			service_id BIGINT UNSIGNED NOT NULL,
			staff_id BIGINT UNSIGNED NOT NULL,
			booking_date DATE NOT NULL,
			start_time TIME NULL DEFAULT NULL,
			end_time TIME NULL DEFAULT NULL,
			cancelled_start_time TIME NULL DEFAULT NULL,
			cancelled_end_time TIME NULL DEFAULT NULL,
			duration INT UNSIGNED NOT NULL COMMENT 'Duration in minutes (cached from service)',
			status ENUM('pending','pending_payment','confirmed','completed','cancelled','no_show') NOT NULL DEFAULT 'pending_payment',
			total_price DECIMAL(10,2) NOT NULL,
			deposit_amount DECIMAL(10,2) NULL DEFAULT NULL COMMENT 'Service deposit config amount',
			deposit_paid DECIMAL(10,2) DEFAULT 0.00 COMMENT 'Actual amount paid as deposit',
			balance_due DECIMAL(10,2) DEFAULT 0.00 COMMENT 'Remaining balance to pay',
			full_amount_paid TINYINT(1) DEFAULT 0,
			payment_method VARCHAR(50) NULL COMMENT 'stripe, paypal, cash, card',
			payment_intent_id VARCHAR(255) NULL COMMENT 'Stripe PaymentIntent ID',
			stripe_session_id VARCHAR(255) NULL DEFAULT NULL COMMENT 'Stripe Checkout session ID for lookup after payment',
			special_requests TEXT NULL COMMENT 'Special requests from customer during booking',
			staff_notes TEXT NULL COMMENT 'Internal staff notes',
			cancellation_reason TEXT NULL,
			cancelled_at DATETIME NULL,
			cancelled_by VARCHAR(50) NULL COMMENT 'customer, staff, system',
			google_calendar_event_id VARCHAR(255) NULL COMMENT 'For calendar sync',
			created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
			updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
			lock_version VARCHAR(32) NULL,
			deleted_at DATETIME NULL DEFAULT NULL,
			PRIMARY KEY (id),
			UNIQUE KEY unique_booking_slot (staff_id, booking_date, start_time),
			UNIQUE KEY uq_booking_reference (booking_reference),
			KEY idx_customer_id (customer_id),
			KEY idx_service_id (service_id),
			KEY idx_staff_id (staff_id),
			KEY idx_booking_date (booking_date),
			KEY idx_status (status),
			KEY idx_deleted_at (deleted_at),
			KEY idx_date_time (booking_date, start_time),
			KEY idx_payment_intent (payment_intent_id)
		) $charset_collate;";

		dbDelta( $sql );
	}

	/**
	 * Create wp_bookings_payments table.
	 *
	 * @param string $table_prefix    WordPress table prefix.
	 * @param string $charset_collate Database charset collation.
	 */
	private static function create_payments_table( $table_prefix, $charset_collate ) {
		$table_name = $table_prefix . 'bookings_payments';

		$sql = "CREATE TABLE $table_name (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			booking_id BIGINT UNSIGNED NOT NULL,
			customer_id BIGINT UNSIGNED NOT NULL,
			amount DECIMAL(10,2) NOT NULL,
			payment_type ENUM('deposit','full_payment','balance_payment','refund') DEFAULT 'full_payment',
			payment_method VARCHAR(50) NOT NULL COMMENT 'stripe, paypal, cash, card',
			payment_status ENUM('pending','completed','failed','refunded','partially_refunded') DEFAULT 'pending',
			stripe_payment_intent_id VARCHAR(255) NULL COMMENT 'Stripe PaymentIntent ID',
			stripe_charge_id VARCHAR(255) NULL COMMENT 'Stripe Charge ID',
			paypal_order_id VARCHAR(255) NULL COMMENT 'PayPal Order ID',
			paypal_capture_id VARCHAR(255) NULL COMMENT 'PayPal Capture ID',
			refund_amount DECIMAL(10,2) NULL DEFAULT NULL,
			refund_reason TEXT NULL,
			refunded_at DATETIME NULL,
			transaction_date DATETIME NOT NULL,
			created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
			updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
			PRIMARY KEY (id),
			KEY idx_booking_id (booking_id),
			KEY idx_customer_id (customer_id),
			KEY idx_payment_status (payment_status),
			KEY idx_transaction_date (transaction_date),
			KEY idx_stripe_payment_intent (stripe_payment_intent_id),
			KEY idx_paypal_order (paypal_order_id)
		) $charset_collate;";

		dbDelta( $sql );
	}

	/**
	 * @deprecated Superseded by wp_bookings_staff_working_hours
	 * (migration-add-staff-working-hours.php). Table removed via
	 * migration 0011-drop-working-hours-table.php. Method retained
	 * to avoid breaking any subclasses.
	 *
	 * Create wp_bookings_working_hours table.
	 *
	 * @param string $table_prefix    WordPress table prefix.
	 * @param string $charset_collate Database charset collation.
	 */
	private static function create_working_hours_table( $table_prefix, $charset_collate ) {
		$table_name = $table_prefix . 'bookings_working_hours';

		$sql = "CREATE TABLE $table_name (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			staff_id BIGINT UNSIGNED NOT NULL,
			day_of_week TINYINT UNSIGNED NOT NULL COMMENT '0=Sunday, 6=Saturday',
			start_time TIME NOT NULL,
			end_time TIME NOT NULL,
			is_active TINYINT(1) DEFAULT 1,
			created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
			updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
			PRIMARY KEY (id),
			KEY idx_staff_id (staff_id),
			KEY idx_day_of_week (day_of_week),
			KEY idx_is_active (is_active)
		) $charset_collate;";

		dbDelta( $sql );
	}

	/**
	 * Create wp_bookings_settings table (key-value store).
	 *
	 * @param string $table_prefix    WordPress table prefix.
	 * @param string $charset_collate Database charset collation.
	 */
	private static function create_settings_table( $table_prefix, $charset_collate ) {
		$table_name = $table_prefix . 'bookings_settings';

		$sql = "CREATE TABLE $table_name (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			setting_key VARCHAR(100) NOT NULL,
			setting_value LONGTEXT NULL,
			setting_type ENUM('string','integer','boolean','json') DEFAULT 'string',
			autoload TINYINT(1) DEFAULT 1 COMMENT 'Load on plugin init like wp_options',
			created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
			updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
			PRIMARY KEY (id),
			UNIQUE KEY unique_setting_key (setting_key),
			KEY idx_autoload (autoload)
		) $charset_collate;";

		dbDelta( $sql );
	}

	/**
	 * Create wp_bookings_idempotency table.
	 *
	 * Tracks idempotency keys to prevent duplicate operations
	 * (Stripe checkouts, emails, webhooks).
	 *
	 * Sprint 2, Task 6.
	 *
	 * @param string $table_prefix    WordPress table prefix.
	 * @param string $charset_collate Database charset collation.
	 * @return void
	 */
	private static function create_idempotency_table( $table_prefix, $charset_collate ) {
		$table_name = $table_prefix . 'bookings_idempotency';

		$sql = "CREATE TABLE $table_name (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			idempotency_key VARCHAR(255) NOT NULL,
			operation_type VARCHAR(50) NOT NULL,
			request_hash VARCHAR(64) NOT NULL,
			response_data TEXT NULL,
			status VARCHAR(20) NOT NULL DEFAULT 'processing',
			created_at DATETIME NOT NULL,
			completed_at DATETIME NULL,
			expires_at DATETIME NOT NULL,
			PRIMARY KEY  (id),
			UNIQUE KEY unique_key (idempotency_key),
			KEY idx_expires (expires_at),
			KEY idx_status (status),
			KEY idx_operation_type (operation_type)
		) $charset_collate;";

		dbDelta( $sql );
	}

	/**
	 * Drop all plugin tables.
	 *
	 * WARNING: This deletes all data. Only call from uninstall.php.
	 *
	 * @return void
	 */
	public static function drop_tables() {
		global $wpdb;

		$table_prefix = $wpdb->prefix;

		$tables = array(
			// Sprint 2 tables.
			$table_prefix . 'bookings_idempotency',
			// Part 2 tables (drop first due to dependencies).
			$table_prefix . 'bookings_payments',
			$table_prefix . 'bookings',
			$table_prefix . 'bookings_staff_working_hours',
			$table_prefix . 'bookings_customers',
			$table_prefix . 'bookings_settings',
			// Part 1 tables (existing).
			$table_prefix . 'bookings_staff_services',
			$table_prefix . 'bookings_service_categories',
			$table_prefix . 'bookings_staff',
			$table_prefix . 'bookings_categories',
			$table_prefix . 'bookings_services',
		);

		foreach ( $tables as $table ) {
			// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.DirectDatabaseQuery.DirectQuery
			$wpdb->query( "DROP TABLE IF EXISTS {$table}" );
		}
	}
}
