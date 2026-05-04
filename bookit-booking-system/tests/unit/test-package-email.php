<?php
/**
 * Tests for package redemption details in customer confirmation email.
 *
 * @package    Bookit_Booking_System
 * @subpackage Tests
 */

/**
 * Test package-aware customer confirmation email content.
 */
class Test_Package_Email extends WP_UnitTestCase {

	/**
	 * Email sender instance.
	 *
	 * @var Booking_System_Email_Sender
	 */
	private $email_sender;

	/**
	 * Set up each test.
	 */
	public function setUp(): void {
		parent::setUp();

		require_once dirname( __DIR__, 2 ) . '/includes/email/class-email-sender.php';
		$this->email_sender = new Booking_System_Email_Sender();
		$this->ensure_package_types_table_exists();
		$this->ensure_customer_packages_table_exists();

		bookit_test_truncate_tables(
			array(
				'bookings',
				'bookings_customer_packages',
				'bookings_package_types',
				'bookings_customers',
				'bookings_staff',
				'bookings_services',
			)
		);
	}

	/**
	 * Tear down each test.
	 */
	public function tearDown(): void {
		bookit_test_truncate_tables(
			array(
				'bookings',
				'bookings_customer_packages',
				'bookings_package_types',
				'bookings_customers',
				'bookings_staff',
				'bookings_services',
			)
		);

		parent::tearDown();
	}

	/**
	 * @covers Booking_System_Email_Sender::send_customer_confirmation
	 * @covers Booking_System_Email_Sender::generate_customer_email
	 */
	public function test_package_booking_confirmation_includes_sessions_remaining() {
		$service_id  = $this->insert_service();
		$staff_id    = $this->insert_staff();
		$customer_id = $this->insert_customer( 'package-email@test.com' );

		$package_type_id = $this->insert_package_type( 'Wellness Pack' );
		$expires_at      = gmdate( 'Y-m-d H:i:s', strtotime( '+20 days' ) );
		$customer_package_id = $this->insert_customer_package(
			$customer_id,
			$package_type_id,
			7,
			10,
			$expires_at
		);

		$booking = $this->build_booking_payload(
			$service_id,
			$staff_id,
			$customer_id,
			array(
				'payment_method'      => 'package_redemption',
				'customer_package_id' => $customer_package_id,
				'customer_email'      => 'package-email@test.com',
			)
		);

		$result = $this->send_customer_email_and_capture( $booking, $captured_body );

		$this->assertTrue( $result );
		$this->assertStringContainsString( 'Sessions remaining on your Wellness Pack package: 7 of 10', $captured_body );
		$this->assertStringContainsString( 'Your package expires on:', $captured_body );
	}

	/**
	 * @covers Booking_System_Email_Sender::send_customer_confirmation
	 * @covers Booking_System_Email_Sender::generate_customer_email
	 */
	public function test_non_package_booking_email_unchanged() {
		$service_id  = $this->insert_service();
		$staff_id    = $this->insert_staff();
		$customer_id = $this->insert_customer( 'standard-email@test.com' );

		$booking = $this->build_booking_payload(
			$service_id,
			$staff_id,
			$customer_id,
			array(
				'payment_method'      => 'stripe',
				'customer_package_id' => null,
				'customer_email'      => 'standard-email@test.com',
			)
		);

		$result = $this->send_customer_email_and_capture( $booking, $captured_body );

		$this->assertTrue( $result );
		$this->assertStringNotContainsString( 'Sessions remaining', $captured_body );
	}

	/**
	 * @covers Booking_System_Email_Sender::send_customer_confirmation
	 * @covers Booking_System_Email_Sender::generate_customer_email
	 */
	public function test_graceful_fallback_when_package_not_found() {
		$service_id  = $this->insert_service();
		$staff_id    = $this->insert_staff();
		$customer_id = $this->insert_customer( 'missing-package@test.com' );

		$booking = $this->build_booking_payload(
			$service_id,
			$staff_id,
			$customer_id,
			array(
				'payment_method'      => 'package_redemption',
				'customer_package_id' => 999999,
				'customer_email'      => 'missing-package@test.com',
			)
		);

		$result = $this->send_customer_email_and_capture( $booking, $captured_body );

		$this->assertTrue( $result );
		$this->assertStringNotContainsString( 'Sessions remaining', $captured_body );
	}

	/**
	 * Send customer confirmation email and capture generated body.
	 *
	 * @param array  $booking Booking payload.
	 * @param string $captured_body Captured body output.
	 * @return bool|WP_Error
	 */
	private function send_customer_email_and_capture( array $booking, &$captured_body ) {
		$captured_body = (string) $this->email_sender->generate_customer_email( $booking );
		return true;
	}

	/**
	 * Build booking payload expected by email sender.
	 *
	 * @param int   $service_id Service ID.
	 * @param int   $staff_id Staff ID.
	 * @param int   $customer_id Customer ID.
	 * @param array $overrides Booking overrides.
	 * @return array
	 */
	private function build_booking_payload( $service_id, $staff_id, $customer_id, $overrides = array() ) {
		global $wpdb;

		$service_name = (string) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT name FROM {$wpdb->prefix}bookings_services WHERE id = %d",
				$service_id
			)
		);
		$staff_name = (string) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT CONCAT(first_name, ' ', last_name) FROM {$wpdb->prefix}bookings_staff WHERE id = %d",
				$staff_id
			)
		);
		$customer_first_name = (string) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT first_name FROM {$wpdb->prefix}bookings_customers WHERE id = %d",
				$customer_id
			)
		);

		$defaults = array(
			'customer_email'       => 'customer@test.com',
			'customer_first_name'  => $customer_first_name,
			'customer_name'        => 'Test Customer',
			'service_name'         => $service_name,
			'booking_date'         => wp_date( 'Y-m-d', strtotime( '+30 days' ), wp_timezone() ),
			'start_time'           => '10:00:00',
			'staff_name'           => $staff_name,
			'total_price'          => 50.00,
			'deposit_paid'         => 50.00,
			'balance_due'          => 0.00,
			'payment_method'       => 'stripe',
			'special_requests'     => '',
			'customer_package_id'  => null,
		);

		return wp_parse_args( $overrides, $defaults );
	}

	/**
	 * Insert test service row.
	 *
	 * @return int
	 */
	private function insert_service() {
		global $wpdb;

		$wpdb->insert(
			$wpdb->prefix . 'bookings_services',
			array(
				'name'           => 'Package Email Service',
				'duration'       => 60,
				'price'          => 50.00,
				'deposit_type'   => 'none',
				'deposit_amount' => 0.00,
				'is_active'      => 1,
				'created_at'     => current_time( 'mysql' ),
				'updated_at'     => current_time( 'mysql' ),
			),
			array( '%s', '%d', '%f', '%s', '%f', '%d', '%s', '%s' )
		);

		return (int) $wpdb->insert_id;
	}

	/**
	 * Insert test staff row.
	 *
	 * @return int
	 */
	private function insert_staff() {
		global $wpdb;

		$wpdb->insert(
			$wpdb->prefix . 'bookings_staff',
			array(
				'email'         => 'staff-' . wp_generate_password( 6, false ) . '@test.com',
				'password_hash' => wp_hash_password( 'password123' ),
				'first_name'    => 'Test',
				'last_name'     => 'Staff',
				'is_active'     => 1,
				'created_at'    => current_time( 'mysql' ),
				'updated_at'    => current_time( 'mysql' ),
			),
			array( '%s', '%s', '%s', '%s', '%d', '%s', '%s' )
		);

		return (int) $wpdb->insert_id;
	}

	/**
	 * Insert test customer row.
	 *
	 * @param string $email Customer email.
	 * @return int
	 */
	private function insert_customer( $email ) {
		global $wpdb;

		$wpdb->insert(
			$wpdb->prefix . 'bookings_customers',
			array(
				'email'      => $email,
				'first_name' => 'Package',
				'last_name'  => 'Customer',
				'phone'      => '07700900111',
				'created_at' => current_time( 'mysql' ),
				'updated_at' => current_time( 'mysql' ),
			),
			array( '%s', '%s', '%s', '%s', '%s', '%s' )
		);

		return (int) $wpdb->insert_id;
	}

	/**
	 * Insert package type row.
	 *
	 * @param string $name Package type name.
	 * @return int
	 */
	private function insert_package_type( $name ) {
		global $wpdb;

		$wpdb->insert(
			$wpdb->prefix . 'bookings_package_types',
			array(
				'name'                   => $name,
				'description'            => 'Package for email tests',
				'sessions_count'         => 10,
				'price_mode'             => 'fixed',
				'fixed_price'            => 100.00,
				'discount_percentage'    => null,
				'expiry_enabled'         => 1,
				'expiry_days'            => 30,
				'applicable_service_ids' => null,
				'is_active'              => 1,
				'created_at'             => current_time( 'mysql' ),
				'updated_at'             => current_time( 'mysql' ),
			),
			array( '%s', '%s', '%d', '%s', '%f', '%f', '%d', '%d', '%s', '%d', '%s', '%s' )
		);

		return (int) $wpdb->insert_id;
	}

	/**
	 * Insert customer package row.
	 *
	 * @param int         $customer_id Customer ID.
	 * @param int         $package_type_id Package type ID.
	 * @param int         $sessions_remaining Sessions remaining.
	 * @param int         $sessions_total Sessions total.
	 * @param string|null $expires_at Optional expiry.
	 * @return int
	 */
	private function insert_customer_package( $customer_id, $package_type_id, $sessions_remaining, $sessions_total, $expires_at = null ) {
		global $wpdb;

		$wpdb->insert(
			$wpdb->prefix . 'bookings_customer_packages',
			array(
				'customer_id'        => (int) $customer_id,
				'package_type_id'    => (int) $package_type_id,
				'sessions_total'     => (int) $sessions_total,
				'sessions_remaining' => (int) $sessions_remaining,
				'purchase_price'     => 100.00,
				'purchased_at'       => current_time( 'mysql' ),
				'expires_at'         => $expires_at,
				'status'             => 'active',
				'payment_method'     => 'manual',
				'payment_reference'  => null,
				'notes'              => null,
				'created_at'         => current_time( 'mysql' ),
				'updated_at'         => current_time( 'mysql' ),
			),
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
}
