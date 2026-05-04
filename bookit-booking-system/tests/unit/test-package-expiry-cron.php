<?php
/**
 * Tests for package expiry cron.
 *
 * @package    Bookit_Booking_System
 * @subpackage Tests
 */

/**
 * Test package expiry cron behavior.
 */
class Test_Package_Expiry_Cron extends WP_UnitTestCase {

	/**
	 * Seeded package type ID.
	 *
	 * @var int
	 */
	private $package_type_id = 0;

	/**
	 * Set up each test.
	 */
	public function setUp(): void {
		parent::setUp();

		$this->load_expiry_cron_class();
		$this->ensure_package_types_table_exists();
		$this->ensure_customer_packages_table_exists();

		bookit_test_truncate_tables(
			array(
				'bookings_customer_packages',
				'bookings_package_types',
				'bookings_customers',
				'bookings_audit_log',
			)
		);

		$this->clear_scheduled_hook( Bookit_Package_Expiry::CRON_HOOK );
		delete_option( 'bookit_package_expiry_last_run' );
		delete_option( 'bookit_package_expiry_last_count' );

		$this->package_type_id = $this->insert_package_type();
	}

	/**
	 * Tear down each test.
	 */
	public function tearDown(): void {
		bookit_test_truncate_tables(
			array(
				'bookings_customer_packages',
				'bookings_package_types',
				'bookings_customers',
				'bookings_audit_log',
			)
		);

		$this->clear_scheduled_hook( Bookit_Package_Expiry::CRON_HOOK );
		delete_option( 'bookit_package_expiry_last_run' );
		delete_option( 'bookit_package_expiry_last_count' );

		parent::tearDown();
	}

	/**
	 * @covers Bookit_Package_Expiry::run_cleanup
	 */
	public function test_expires_active_package_with_past_expiry() {
		global $wpdb;

		$customer_id = $this->insert_customer();
		$package_id  = $this->insert_customer_package(
			array(
				'customer_id'     => $customer_id,
				'status'          => 'active',
				'expires_at'      => gmdate( 'Y-m-d H:i:s', strtotime( '-1 hour' ) ),
				'package_type_id' => $this->package_type_id,
			)
		);

		$count = Bookit_Package_Expiry::run_cleanup();

		$status = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT status FROM {$wpdb->prefix}bookings_customer_packages WHERE id = %d",
				$package_id
			)
		);

		$this->assertSame( 1, $count );
		$this->assertSame( 'expired', $status );
	}

	/**
	 * @covers Bookit_Package_Expiry::run_cleanup
	 */
	public function test_does_not_expire_active_package_with_future_expiry() {
		global $wpdb;

		$customer_id = $this->insert_customer();
		$package_id  = $this->insert_customer_package(
			array(
				'customer_id'     => $customer_id,
				'status'          => 'active',
				'expires_at'      => gmdate( 'Y-m-d H:i:s', strtotime( '+1 day' ) ),
				'package_type_id' => $this->package_type_id,
			)
		);

		$count = Bookit_Package_Expiry::run_cleanup();

		$status = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT status FROM {$wpdb->prefix}bookings_customer_packages WHERE id = %d",
				$package_id
			)
		);

		$this->assertSame( 0, $count );
		$this->assertSame( 'active', $status );
	}

	/**
	 * @covers Bookit_Package_Expiry::run_cleanup
	 */
	public function test_does_not_expire_already_expired_package() {
		$customer_id = $this->insert_customer();
		$this->insert_customer_package(
			array(
				'customer_id'     => $customer_id,
				'status'          => 'expired',
				'expires_at'      => gmdate( 'Y-m-d H:i:s', strtotime( '-1 hour' ) ),
				'package_type_id' => $this->package_type_id,
			)
		);

		$count = Bookit_Package_Expiry::run_cleanup();

		$this->assertSame( 0, $count );
	}

	/**
	 * @covers Bookit_Package_Expiry::run_cleanup
	 */
	public function test_does_not_expire_package_with_null_expiry() {
		global $wpdb;

		$customer_id = $this->insert_customer();
		$package_id  = $this->insert_customer_package(
			array(
				'customer_id'     => $customer_id,
				'status'          => 'active',
				'expires_at'      => null,
				'package_type_id' => $this->package_type_id,
			)
		);

		$count = Bookit_Package_Expiry::run_cleanup();

		$status = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT status FROM {$wpdb->prefix}bookings_customer_packages WHERE id = %d",
				$package_id
			)
		);

		$this->assertSame( 0, $count );
		$this->assertSame( 'active', $status );
	}

	/**
	 * @covers Bookit_Package_Expiry::run_cleanup
	 */
	public function test_returns_correct_expired_count() {
		$customer_id = $this->insert_customer();

		for ( $i = 0; $i < 3; $i++ ) {
			$this->insert_customer_package(
				array(
					'customer_id'     => $customer_id,
					'status'          => 'active',
					'expires_at'      => gmdate( 'Y-m-d H:i:s', strtotime( '-1 hour' ) ),
					'package_type_id' => $this->package_type_id,
				)
			);
		}

		$this->insert_customer_package(
			array(
				'customer_id'     => $customer_id,
				'status'          => 'active',
				'expires_at'      => gmdate( 'Y-m-d H:i:s', strtotime( '+1 day' ) ),
				'package_type_id' => $this->package_type_id,
			)
		);
		$this->insert_customer_package(
			array(
				'customer_id'     => $customer_id,
				'status'          => 'active',
				'expires_at'      => null,
				'package_type_id' => $this->package_type_id,
			)
		);

		$count = Bookit_Package_Expiry::run_cleanup();

		$this->assertSame( 3, $count );
	}

	/**
	 * @covers Bookit_Package_Expiry::run_cleanup
	 */
	public function test_fires_audit_log_per_expired_package() {
		global $wpdb;

		$customer_id = $this->insert_customer();

		for ( $i = 0; $i < 2; $i++ ) {
			$this->insert_customer_package(
				array(
					'customer_id'     => $customer_id,
					'status'          => 'active',
					'expires_at'      => gmdate( 'Y-m-d H:i:s', strtotime( '-1 hour' ) ),
					'package_type_id' => $this->package_type_id,
				)
			);
		}

		Bookit_Package_Expiry::run_cleanup();

		$audit_count = (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$wpdb->prefix}bookings_audit_log WHERE action = %s",
				'customer_package.expired'
			)
		);

		$this->assertSame( 2, $audit_count );
	}

	/**
	 * @covers Bookit_Package_Expiry::run_cleanup
	 */
	public function test_does_not_expire_exhausted_package() {
		global $wpdb;

		$customer_id = $this->insert_customer();
		$package_id  = $this->insert_customer_package(
			array(
				'customer_id'     => $customer_id,
				'status'          => 'exhausted',
				'expires_at'      => gmdate( 'Y-m-d H:i:s', strtotime( '-1 hour' ) ),
				'package_type_id' => $this->package_type_id,
			)
		);

		$count = Bookit_Package_Expiry::run_cleanup();

		$status = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT status FROM {$wpdb->prefix}bookings_customer_packages WHERE id = %d",
				$package_id
			)
		);

		$this->assertSame( 0, $count );
		$this->assertSame( 'exhausted', $status );
	}

	/**
	 * @covers Bookit_Package_Expiry::register_cron
	 */
	public function test_register_cron_schedules_daily_event() {
		Bookit_Package_Expiry::register_cron();

		$timestamp = wp_next_scheduled( Bookit_Package_Expiry::CRON_HOOK );

		$this->assertNotFalse( $timestamp );
		$this->assertSame( 'daily', $this->get_event_schedule( Bookit_Package_Expiry::CRON_HOOK, (int) $timestamp ) );
	}

	/**
	 * @covers Bookit_Package_Expiry::unregister_cron
	 */
	public function test_unregister_cron_removes_event() {
		Bookit_Package_Expiry::register_cron();
		Bookit_Package_Expiry::unregister_cron();

		$this->assertFalse( wp_next_scheduled( Bookit_Package_Expiry::CRON_HOOK ) );
	}

	/**
	 * @covers Bookit_Package_Expiry::register_cron
	 */
	public function test_register_cron_is_idempotent() {
		Bookit_Package_Expiry::register_cron();
		Bookit_Package_Expiry::register_cron();

		$this->assertSame( 1, $this->count_scheduled_events( Bookit_Package_Expiry::CRON_HOOK ) );
	}

	/**
	 * Load cron class under test.
	 *
	 * @return void
	 */
	private function load_expiry_cron_class() {
		if ( class_exists( 'Bookit_Package_Expiry' ) ) {
			return;
		}

		$file = dirname( __DIR__, 2 ) . '/includes/cron/class-bookit-package-expiry.php';
		if ( file_exists( $file ) ) {
			require_once $file;
		}
	}

	/**
	 * Insert package type test row.
	 *
	 * @param array $overrides Field overrides.
	 * @return int
	 */
	private function insert_package_type( $overrides = array() ) {
		global $wpdb;

		$defaults = array(
			'name'                   => 'Default Package Type',
			'description'            => 'Default description',
			'sessions_count'         => 10,
			'price_mode'             => 'fixed',
			'fixed_price'            => 120.00,
			'discount_percentage'    => null,
			'expiry_enabled'         => 0,
			'expiry_days'            => null,
			'applicable_service_ids' => null,
			'is_active'              => 1,
			'created_at'             => current_time( 'mysql' ),
			'updated_at'             => current_time( 'mysql' ),
		);

		$data = wp_parse_args( $overrides, $defaults );

		$wpdb->insert(
			$wpdb->prefix . 'bookings_package_types',
			$data,
			array( '%s', '%s', '%d', '%s', '%f', '%f', '%d', '%d', '%s', '%d', '%s', '%s' )
		);

		return (int) $wpdb->insert_id;
	}

	/**
	 * Insert customer test row.
	 *
	 * @param array $overrides Field overrides.
	 * @return int
	 */
	private function insert_customer( $overrides = array() ) {
		global $wpdb;

		$defaults = array(
			'email'      => 'customer-' . wp_generate_password( 6, false ) . '@test.com',
			'first_name' => 'Test',
			'last_name'  => 'Customer',
			'phone'      => '07700900000',
			'created_at' => current_time( 'mysql' ),
			'updated_at' => current_time( 'mysql' ),
		);

		$data = wp_parse_args( $overrides, $defaults );

		$wpdb->insert(
			$wpdb->prefix . 'bookings_customers',
			$data,
			array( '%s', '%s', '%s', '%s', '%s', '%s' )
		);

		return (int) $wpdb->insert_id;
	}

	/**
	 * Insert customer package test row.
	 *
	 * @param array $overrides Field overrides.
	 * @return int
	 */
	private function insert_customer_package( $overrides = array() ) {
		global $wpdb;

		$defaults = array(
			'customer_id'        => 0,
			'package_type_id'    => $this->package_type_id,
			'sessions_total'     => 10,
			'sessions_remaining' => 10,
			'purchase_price'     => 120.00,
			'purchased_at'       => current_time( 'mysql' ),
			'expires_at'         => null,
			'status'             => 'active',
			'payment_method'     => 'manual',
			'payment_reference'  => null,
			'notes'              => null,
			'created_at'         => current_time( 'mysql' ),
			'updated_at'         => current_time( 'mysql' ),
		);

		$data = wp_parse_args( $overrides, $defaults );

		$wpdb->insert(
			$wpdb->prefix . 'bookings_customer_packages',
			$data,
			array( '%d', '%d', '%d', '%d', '%f', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s' )
		);

		return (int) $wpdb->insert_id;
	}

	/**
	 * Ensure package types table exists for this test class.
	 *
	 * @return void
	 */
	private function ensure_package_types_table_exists() {
		global $wpdb;

		$table_name = $wpdb->prefix . 'bookings_package_types';
		if ( function_exists( 'bookit_test_table_exists' ) && bookit_test_table_exists( $table_name ) ) {
			return;
		}

		$migration_file = dirname( __DIR__, 2 ) . '/database/migrations/0005-create-package-types-table.php';
		if ( file_exists( $migration_file ) ) {
			require_once $migration_file;
		}

		if ( class_exists( 'Bookit_Migration_0005_Create_Package_Types_Table' ) ) {
			$migration = new Bookit_Migration_0005_Create_Package_Types_Table();
			$migration->up();
		}
	}

	/**
	 * Ensure customer packages table exists for this test class.
	 *
	 * @return void
	 */
	private function ensure_customer_packages_table_exists() {
		global $wpdb;

		$table_name = $wpdb->prefix . 'bookings_customer_packages';
		if ( function_exists( 'bookit_test_table_exists' ) && bookit_test_table_exists( $table_name ) ) {
			return;
		}

		$charset_collate = $wpdb->get_charset_collate();
		$wpdb->query(
			"CREATE TABLE IF NOT EXISTS {$table_name} (
				id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
				customer_id BIGINT UNSIGNED NOT NULL,
				package_type_id BIGINT UNSIGNED NOT NULL,
				sessions_total INT UNSIGNED NOT NULL,
				sessions_remaining INT UNSIGNED NOT NULL,
				purchase_price DECIMAL(10,2) NULL,
				purchased_at DATETIME NULL,
				expires_at DATETIME NULL,
				status ENUM('active','exhausted','expired','cancelled') NOT NULL DEFAULT 'active',
				payment_method VARCHAR(50) NULL,
				payment_reference VARCHAR(255) NULL,
				notes TEXT NULL,
				created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
				updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
				PRIMARY KEY (id),
				KEY idx_customer_id (customer_id),
				KEY idx_package_type_id (package_type_id),
				KEY idx_status (status),
				KEY idx_expires_at (expires_at)
			) ENGINE=InnoDB {$charset_collate};"
		); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.DirectDatabaseQuery.DirectQuery
	}

	/**
	 * Clear all scheduled events for a hook.
	 *
	 * @param string $hook Hook name.
	 * @return void
	 */
	private function clear_scheduled_hook( $hook ) {
		if ( function_exists( 'wp_clear_scheduled_hook' ) ) {
			wp_clear_scheduled_hook( $hook );
			return;
		}

		$timestamp = wp_next_scheduled( $hook );
		while ( false !== $timestamp ) {
			wp_unschedule_event( $timestamp, $hook );
			$timestamp = wp_next_scheduled( $hook );
		}
	}

	/**
	 * Count scheduled events for a hook.
	 *
	 * @param string $hook Hook name.
	 * @return int
	 */
	private function count_scheduled_events( $hook ) {
		$cron  = _get_cron_array();
		$count = 0;

		if ( empty( $cron ) || ! is_array( $cron ) ) {
			return 0;
		}

		foreach ( $cron as $timestamp => $events ) {
			if ( isset( $events[ $hook ] ) && is_array( $events[ $hook ] ) ) {
				$count += count( $events[ $hook ] );
			}
		}

		return $count;
	}

	/**
	 * Get schedule name for an event.
	 *
	 * @param string $hook Hook name.
	 * @param int    $timestamp Event timestamp.
	 * @return string|null
	 */
	private function get_event_schedule( $hook, $timestamp ) {
		if ( function_exists( 'wp_get_scheduled_event' ) ) {
			$event = wp_get_scheduled_event( $hook, array(), $timestamp );
			if ( $event && isset( $event->schedule ) ) {
				return $event->schedule;
			}
		}

		$cron = _get_cron_array();
		if ( isset( $cron[ $timestamp ][ $hook ] ) ) {
			$event = reset( $cron[ $timestamp ][ $hook ] );
			if ( is_array( $event ) && isset( $event['schedule'] ) ) {
				return $event['schedule'];
			}
		}

		return null;
	}
}
