<?php
/**
 * Tests for Settings & Email Template APIs (Sprint 3, Task 11)
 *
 * @package    Bookit_Booking_System
 * @subpackage Tests
 */

/**
 * Test Settings and Email Template API endpoints.
 */
class Test_Settings_Email_API extends WP_UnitTestCase {

	/**
	 * REST API namespace.
	 *
	 * @var string
	 */
	private $namespace = 'bookit/v1';

	/**
	 * Set up each test.
	 */
	public function setUp(): void {
		parent::setUp();

		global $wpdb;

		$wpdb->query( "TRUNCATE TABLE {$wpdb->prefix}bookings_staff" );
		$wpdb->query( "TRUNCATE TABLE {$wpdb->prefix}bookings_settings" );

		$this->ensure_email_templates_table();

		$_SESSION = array();

		do_action( 'rest_api_init' );
	}

	/**
	 * Tear down each test.
	 */
	public function tearDown(): void {
		global $wpdb;

		$wpdb->query( "TRUNCATE TABLE {$wpdb->prefix}bookings_staff" );
		$wpdb->query( "TRUNCATE TABLE {$wpdb->prefix}bookings_settings" );

		delete_option( 'bookit_confirmed_v2_url' );

		$_SESSION = array();

		parent::tearDown();
	}

	// ========== TESTS FOR: GET /dashboard/settings ==========

	/**
	 * Test get settings returns saved values.
	 *
	 * @covers Bookit_Dashboard_Bookings_API::get_settings
	 */
	public function test_get_settings_returns_saved_values() {
		global $wpdb;

		$wpdb->insert(
			$wpdb->prefix . 'bookings_settings',
			array(
				'setting_key'   => 'business_name',
				'setting_value' => 'My Salon',
				'setting_type'  => 'string',
			),
			array( '%s', '%s', '%s' )
		);

		$admin = $this->create_test_staff( array( 'role' => 'admin' ) );
		$this->login_as( $admin, 'admin' );

		$request  = new WP_REST_Request( 'GET', '/' . $this->namespace . '/dashboard/settings' );
		$response = rest_get_server()->dispatch( $request );

		$this->assertEquals( 200, $response->get_status() );

		$data = $response->get_data();
		$this->assertTrue( $data['success'] );
		$this->assertArrayHasKey( 'settings', $data );
		$this->assertEquals( 'My Salon', $data['settings']['business_name'] );
	}

	/**
	 * When no bookings_settings rows exist, GET still returns wp_option-backed defaults (e.g. V2 confirmed URL).
	 *
	 * @covers Bookit_Dashboard_Bookings_API::get_settings
	 */
	public function test_get_settings_returns_empty_when_none_exist() {
		delete_option( 'bookit_confirmed_v2_url' );

		$admin = $this->create_test_staff( array( 'role' => 'admin' ) );
		$this->login_as( $admin, 'admin' );

		$request  = new WP_REST_Request( 'GET', '/' . $this->namespace . '/dashboard/settings' );
		$response = rest_get_server()->dispatch( $request );

		$data = $response->get_data();
		$this->assertTrue( $data['success'] );
		$this->assertSame(
			home_url( '/booking-confirmed-v2/' ),
			$data['settings']['bookit_confirmed_v2_url']
		);
		$this->assertCount( 1, $data['settings'] );
	}

	/**
	 * Test get settings with keys filter.
	 *
	 * @covers Bookit_Dashboard_Bookings_API::get_settings
	 */
	public function test_get_settings_filters_by_keys() {
		global $wpdb;

		$wpdb->insert( $wpdb->prefix . 'bookings_settings', array(
			'setting_key' => 'business_name', 'setting_value' => 'My Salon', 'setting_type' => 'string',
		) );
		$wpdb->insert( $wpdb->prefix . 'bookings_settings', array(
			'setting_key' => 'business_phone', 'setting_value' => '0123456789', 'setting_type' => 'string',
		) );
		$wpdb->insert( $wpdb->prefix . 'bookings_settings', array(
			'setting_key' => 'timezone', 'setting_value' => 'Europe/London', 'setting_type' => 'string',
		) );

		$admin = $this->create_test_staff( array( 'role' => 'admin' ) );
		$this->login_as( $admin, 'admin' );

		$request = new WP_REST_Request( 'GET', '/' . $this->namespace . '/dashboard/settings' );
		$request->set_param( 'keys', 'business_name,business_phone' );

		$response = rest_get_server()->dispatch( $request );

		$data = $response->get_data();
		$this->assertArrayHasKey( 'business_name', $data['settings'] );
		$this->assertArrayHasKey( 'business_phone', $data['settings'] );
		$this->assertArrayNotHasKey( 'timezone', $data['settings'] );
	}

	// ========== TESTS FOR: POST /dashboard/settings ==========

	/**
	 * Test update settings saves correctly.
	 *
	 * @covers Bookit_Dashboard_Bookings_API::update_settings
	 */
	public function test_update_settings_saves_correctly() {
		$admin = $this->create_test_staff( array( 'role' => 'admin' ) );
		$this->login_as( $admin, 'admin' );

		$request = new WP_REST_Request( 'POST', '/' . $this->namespace . '/dashboard/settings' );
		$request->set_body_params( array(
			'settings' => array(
				'business_name'  => 'Test Salon',
				'business_phone' => '07700900000',
			),
		) );

		$response = rest_get_server()->dispatch( $request );

		$this->assertEquals( 200, $response->get_status() );
		$this->assertTrue( $response->get_data()['success'] );

		global $wpdb;
		$value = $wpdb->get_var(
			"SELECT setting_value FROM {$wpdb->prefix}bookings_settings WHERE setting_key = 'business_name'"
		);
		$this->assertEquals( 'Test Salon', $value );
	}

	/**
	 * Test update settings upserts existing keys.
	 *
	 * @covers Bookit_Dashboard_Bookings_API::update_settings
	 */
	public function test_update_settings_upserts_existing() {
		global $wpdb;

		$wpdb->insert( $wpdb->prefix . 'bookings_settings', array(
			'setting_key' => 'business_name', 'setting_value' => 'Old Name', 'setting_type' => 'string',
		) );

		$admin = $this->create_test_staff( array( 'role' => 'admin' ) );
		$this->login_as( $admin, 'admin' );

		$request = new WP_REST_Request( 'POST', '/' . $this->namespace . '/dashboard/settings' );
		$request->set_body_params( array(
			'settings' => array( 'business_name' => 'New Name' ),
		) );

		$response = rest_get_server()->dispatch( $request );

		$this->assertEquals( 200, $response->get_status() );

		$value = $wpdb->get_var(
			"SELECT setting_value FROM {$wpdb->prefix}bookings_settings WHERE setting_key = 'business_name'"
		);
		$this->assertEquals( 'New Name', $value );

		$count = $wpdb->get_var(
			"SELECT COUNT(*) FROM {$wpdb->prefix}bookings_settings WHERE setting_key = 'business_name'"
		);
		$this->assertEquals( 1, (int) $count, 'Should not create duplicate rows' );
	}

	/**
	 * Test settings require admin permission.
	 *
	 * @covers Bookit_Dashboard_Bookings_API::check_admin_permission
	 */
	public function test_settings_require_admin_permission() {
		$staff = $this->create_test_staff( array( 'role' => 'staff' ) );
		$this->login_as( $staff, 'staff' );

		$request = new WP_REST_Request( 'POST', '/' . $this->namespace . '/dashboard/settings' );
		$request->set_body_params( array(
			'settings' => array( 'business_name' => 'Test' ),
		) );

		$response = rest_get_server()->dispatch( $request );

		$this->assertTrue( $response->is_error() );
		$this->assertEquals( 403, $response->get_status() );
	}

	// ========== TESTS FOR: POST /dashboard/settings/test-email ==========

	/**
	 * Test send test email endpoint exists and is accessible.
	 *
	 * @covers Bookit_Dashboard_Bookings_API::send_test_email
	 */
	public function test_send_test_email_endpoint_exists() {
		$admin = $this->create_test_staff( array( 'role' => 'admin' ) );
		$this->login_as( $admin, 'admin' );

		$request = new WP_REST_Request( 'POST', '/' . $this->namespace . '/dashboard/settings/test-email' );
		$request->set_body_params( array(
			'to_email' => 'test@example.com',
		) );

		$response = rest_get_server()->dispatch( $request );

		$this->assertNotEquals( 404, $response->get_status(), 'Endpoint should be registered' );
	}

	// ========== TESTS FOR: GET /dashboard/email-templates ==========

	/**
	 * Test get email templates returns seeded templates.
	 *
	 * @covers Bookit_Dashboard_Bookings_API::get_email_templates
	 */
	public function test_get_email_templates_returns_templates() {
		$admin = $this->create_test_staff( array( 'role' => 'admin' ) );
		$this->login_as( $admin, 'admin' );

		$request  = new WP_REST_Request( 'GET', '/' . $this->namespace . '/dashboard/email-templates' );
		$response = rest_get_server()->dispatch( $request );

		$this->assertEquals( 200, $response->get_status() );

		$data = $response->get_data();
		$this->assertTrue( $data['success'] );
		$this->assertIsArray( $data['templates'] );
		$this->assertGreaterThan( 0, count( $data['templates'] ) );

		$keys = array_column( $data['templates'], 'template_key' );
		$this->assertContains( 'booking_confirmation', $keys );
		$this->assertContains( 'booking_reminder', $keys );
	}

	// ========== TESTS FOR: PUT /dashboard/email-templates/{key} ==========

	/**
	 * Test update email template.
	 *
	 * @covers Bookit_Dashboard_Bookings_API::update_email_template
	 */
	public function test_update_email_template() {
		$admin = $this->create_test_staff( array( 'role' => 'admin' ) );
		$this->login_as( $admin, 'admin' );

		$request = new WP_REST_Request( 'PUT', '/' . $this->namespace . '/dashboard/email-templates/booking_confirmation' );
		$request->set_body_params( array(
			'subject' => 'Custom Booking Confirmation',
			'body'    => 'Hello {customer_name}, your booking is confirmed!',
			'enabled' => true,
		) );

		$response = rest_get_server()->dispatch( $request );

		$this->assertEquals( 200, $response->get_status() );
		$this->assertTrue( $response->get_data()['success'] );

		global $wpdb;
		$subject = $wpdb->get_var(
			"SELECT subject FROM {$wpdb->prefix}bookings_email_templates WHERE template_key = 'booking_confirmation'"
		);
		$this->assertEquals( 'Custom Booking Confirmation', $subject );
	}

	// ========== TESTS FOR: POST /dashboard/email-templates/{key} (reset) ==========

	/**
	 * Test reset email template to default.
	 *
	 * @covers Bookit_Dashboard_Bookings_API::reset_email_template
	 */
	public function test_reset_email_template_to_default() {
		global $wpdb;

		$wpdb->update(
			$wpdb->prefix . 'bookings_email_templates',
			array( 'subject' => 'Custom Subject', 'body' => 'Custom Body' ),
			array( 'template_key' => 'booking_confirmation' ),
			array( '%s', '%s' ),
			array( '%s' )
		);

		$admin = $this->create_test_staff( array( 'role' => 'admin' ) );
		$this->login_as( $admin, 'admin' );

		$request  = new WP_REST_Request( 'POST', '/' . $this->namespace . '/dashboard/email-templates/booking_confirmation' );
		$response = rest_get_server()->dispatch( $request );

		$this->assertEquals( 200, $response->get_status() );
		$this->assertTrue( $response->get_data()['success'] );

		$subject = $wpdb->get_var(
			"SELECT subject FROM {$wpdb->prefix}bookings_email_templates WHERE template_key = 'booking_confirmation'"
		);
		$this->assertNotEquals( 'Custom Subject', $subject, 'Subject should be reset to default' );
		$this->assertStringContainsString( 'Booking Confirmed', $subject );
	}

	/**
	 * Test reset rejects unknown template key.
	 *
	 * @covers Bookit_Dashboard_Bookings_API::reset_email_template
	 */
	public function test_reset_rejects_unknown_template_key() {
		$admin = $this->create_test_staff( array( 'role' => 'admin' ) );
		$this->login_as( $admin, 'admin' );

		$request  = new WP_REST_Request( 'POST', '/' . $this->namespace . '/dashboard/email-templates/nonexistent_template' );
		$response = rest_get_server()->dispatch( $request );

		$this->assertTrue( $response->is_error() );
		$this->assertEquals( 404, $response->get_status() );
		$error = $response->as_error();
		$this->assertEquals( 'template_not_found', $error->get_error_code() );
	}

	// ========== TESTS FOR: Route registration ==========

	/**
	 * Test settings and email template endpoints are registered.
	 *
	 * @covers Bookit_Dashboard_Bookings_API::register_routes
	 */
	public function test_settings_email_endpoints_registered() {
		$routes = rest_get_server()->get_routes();

		$this->assertArrayHasKey( '/' . $this->namespace . '/dashboard/settings', $routes );
		$this->assertArrayHasKey( '/' . $this->namespace . '/dashboard/settings/test-email', $routes );
		$this->assertArrayHasKey( '/' . $this->namespace . '/dashboard/email-templates', $routes );
		$this->assertArrayHasKey( '/' . $this->namespace . '/dashboard/email-templates/(?P<key>[a-z_]+)', $routes );
	}

	// ========== HELPER METHODS ==========

	/**
	 * Ensure the email templates table exists and is seeded.
	 */
	private function ensure_email_templates_table() {
		global $wpdb;

		$table = $wpdb->prefix . 'bookings_email_templates';

		if ( $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) ) !== $table ) {
			require_once ABSPATH . 'wp-admin/includes/upgrade.php';
			$charset_collate = $wpdb->get_charset_collate();

			$sql = "CREATE TABLE IF NOT EXISTS $table (
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
		}

		$count = (int) $wpdb->get_var( "SELECT COUNT(*) FROM $table" );
		if ( 0 === $count ) {
			$templates = array(
				array( 'template_key' => 'booking_confirmation', 'subject' => 'Booking Confirmed - {service_name}', 'body' => 'Default confirmation body', 'enabled' => 1 ),
				array( 'template_key' => 'booking_reminder', 'subject' => 'Reminder: {service_name} tomorrow at {time}', 'body' => 'Default reminder body', 'enabled' => 1 ),
				array( 'template_key' => 'booking_cancelled', 'subject' => 'Booking Cancelled - {service_name}', 'body' => 'Default cancellation body', 'enabled' => 1 ),
				array( 'template_key' => 'admin_new_booking', 'subject' => 'New Booking: {customer_name} - {service_name}', 'body' => 'Default admin notification body', 'enabled' => 1 ),
				array( 'template_key' => 'staff_new_booking', 'subject' => 'New Booking Assigned: {customer_name}', 'body' => 'Default staff notification body', 'enabled' => 1 ),
			);

			foreach ( $templates as $template ) {
				$wpdb->insert( $table, $template, array( '%s', '%s', '%s', '%d' ) );
			}
		}

		$column_exists = $wpdb->get_results(
			"SHOW COLUMNS FROM {$wpdb->prefix}bookings_settings LIKE 'setting_type'"
		);
		if ( empty( $column_exists ) ) {
			$wpdb->query(
				"ALTER TABLE {$wpdb->prefix}bookings_settings
				ADD COLUMN setting_type ENUM('string', 'integer', 'boolean', 'json') DEFAULT 'string'
				AFTER setting_value"
			);
		}
	}

	/**
	 * Simulate Bookit dashboard login via session.
	 *
	 * @param int    $staff_id Staff row ID.
	 * @param string $role     'admin' or 'staff'.
	 */
	private function login_as( $staff_id, $role = 'staff' ) {
		global $wpdb;

		$staff = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT id, email, first_name, last_name FROM {$wpdb->prefix}bookings_staff WHERE id = %d",
				$staff_id
			),
			ARRAY_A
		);

		$_SESSION['staff_id']      = (int) $staff['id'];
		$_SESSION['staff_email']   = $staff['email'];
		$_SESSION['staff_role']    = $role;
		$_SESSION['staff_name']    = trim( $staff['first_name'] . ' ' . $staff['last_name'] );
		$_SESSION['is_logged_in']  = true;
		$_SESSION['last_activity'] = time();
	}

	/**
	 * Create test staff member.
	 *
	 * @param array $args Override defaults.
	 * @return int Staff ID.
	 */
	private function create_test_staff( $args = array() ) {
		global $wpdb;

		$defaults = array(
			'email'              => 'staff-' . wp_generate_password( 6, false ) . '@test.com',
			'password_hash'      => password_hash( 'password123', PASSWORD_BCRYPT ),
			'first_name'         => 'Test',
			'last_name'          => 'Staff',
			'phone'              => '07700900000',
			'photo_url'          => null,
			'bio'                => 'Test bio',
			'title'              => 'Therapist',
			'role'               => 'staff',
			'google_calendar_id' => null,
			'is_active'          => 1,
			'display_order'      => 0,
			'created_at'         => current_time( 'mysql' ),
			'updated_at'         => current_time( 'mysql' ),
			'deleted_at'         => null,
		);

		$data = wp_parse_args( $args, $defaults );

		$wpdb->insert(
			$wpdb->prefix . 'bookings_staff',
			$data,
			array( '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%d', '%d', '%s', '%s', '%s' )
		);
		return (int) $wpdb->insert_id;
	}
}
