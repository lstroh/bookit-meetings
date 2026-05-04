<?php
/**
 * Tests for customer email change workflow.
 *
 * @package    Bookit_Booking_System
 * @subpackage Tests
 */

use Yoast\PHPUnitPolyfills\TestCases\TestCase;

/**
 * Test email change workflow endpoints.
 */
class Test_Email_Change_Workflow extends TestCase {

	const NAMESPACE = 'bookit/v1';

	/**
	 * @var int
	 */
	private $admin_id;

	/**
	 * @var int
	 */
	private $staff_id;

	/**
	 * @var int
	 */
	private $customer_id;

	/**
	 * @var string
	 */
	private $customer_email;

	public function setUp(): void {
		parent::setUp();

		if ( ! class_exists( 'Bookit_Customers_API' ) ) {
			require_once dirname( __DIR__, 2 ) . '/includes/api/class-customers-api.php';
		}
		new Bookit_Customers_API();

		$this->admin_id = $this->create_test_staff( array( 'role' => 'admin', 'first_name' => 'Admin' ) );
		$this->staff_id = $this->create_test_staff( array( 'role' => 'staff', 'first_name' => 'Staff' ) );

		$this->customer_email = 'customer-email-change-' . wp_generate_password( 6, false ) . '@test.com';
		$this->customer_id    = $this->create_test_customer(
			array(
				'email'      => $this->customer_email,
				'first_name' => 'Alice',
				'last_name'  => 'Customer',
			)
		);

		$_SESSION = array();
		do_action( 'rest_api_init' );

		bookit_test_truncate_tables(
			array(
				'bookit_email_queue',
				'bookings_audit_log',
			)
		);
	}

	public function tearDown(): void {
		global $wpdb;

		bookit_test_truncate_tables(
			array(
				'bookit_email_queue',
				'bookings_audit_log',
			)
		);

		$wpdb->delete( $wpdb->prefix . 'bookings_customers', array( 'id' => (int) $this->customer_id ), array( '%d' ) );
		$wpdb->delete( $wpdb->prefix . 'bookings_staff', array( 'id' => (int) $this->admin_id ), array( '%d' ) );
		$wpdb->delete( $wpdb->prefix . 'bookings_staff', array( 'id' => (int) $this->staff_id ), array( '%d' ) );

		$_SESSION = array();

		parent::tearDown();
	}

	public function test_request_email_change_sends_verification_to_new_address() {
		global $wpdb;

		$this->login_as( $this->admin_id, 'admin' );
		$new_email = 'new-' . wp_generate_password( 6, false ) . '@test.com';

		$request = new WP_REST_Request( 'POST', '/' . self::NAMESPACE . '/dashboard/customers/' . $this->customer_id . '/request-email-change' );
		$request->set_body_params(
			array(
				'new_email' => $new_email,
				'reason'    => 'Typo fix',
			)
		);
		$response = rest_get_server()->dispatch( $request );

		$this->assertSame( 200, $response->get_status() );

		$row = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT email_type, recipient_email FROM {$wpdb->prefix}bookit_email_queue WHERE recipient_email = %s ORDER BY id DESC LIMIT 1",
				$new_email
			),
			ARRAY_A
		);
		$this->assertNotEmpty( $row );
		$this->assertSame( 'email_change_verification', $row['email_type'] );
	}

	public function test_request_email_change_sends_notification_to_old_address() {
		global $wpdb;

		$this->login_as( $this->admin_id, 'admin' );
		$new_email = 'new-' . wp_generate_password( 6, false ) . '@test.com';

		$request = new WP_REST_Request( 'POST', '/' . self::NAMESPACE . '/dashboard/customers/' . $this->customer_id . '/request-email-change' );
		$request->set_body_params(
			array(
				'new_email' => $new_email,
				'reason'    => 'Customer request',
			)
		);
		$response = rest_get_server()->dispatch( $request );
		$this->assertSame( 200, $response->get_status() );

		$row = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT email_type, recipient_email FROM {$wpdb->prefix}bookit_email_queue WHERE recipient_email = %s ORDER BY id DESC LIMIT 1",
				$this->customer_email
			),
			ARRAY_A
		);
		$this->assertNotEmpty( $row );
		$this->assertSame( 'email_change_notification', $row['email_type'] );
	}

	public function test_request_email_change_rejects_duplicate_email() {
		global $wpdb;

		$existing_email = 'dup-' . wp_generate_password( 6, false ) . '@test.com';
		$other_customer = $this->create_test_customer( array( 'email' => $existing_email, 'first_name' => 'Bob' ) );

		try {
			$this->login_as( $this->admin_id, 'admin' );

			$request = new WP_REST_Request( 'POST', '/' . self::NAMESPACE . '/dashboard/customers/' . $this->customer_id . '/request-email-change' );
			$request->set_body_params(
				array(
					'new_email' => $existing_email,
					'reason'    => 'Other',
				)
			);
			$response = rest_get_server()->dispatch( $request );
			$error    = $response->as_error();

			$this->assertSame( 409, $response->get_status() );
			$this->assertInstanceOf( WP_Error::class, $error );
			$this->assertSame( 'This email is already in use', $error->get_error_message() );
		} finally {
			$wpdb->delete( $wpdb->prefix . 'bookings_customers', array( 'id' => (int) $other_customer ), array( '%d' ) );
		}
	}

	public function test_request_email_change_requires_admin_role() {
		$this->login_as( $this->staff_id, 'staff' );

		$request = new WP_REST_Request( 'POST', '/' . self::NAMESPACE . '/dashboard/customers/' . $this->customer_id . '/request-email-change' );
		$request->set_body_params(
			array(
				'new_email' => 'new-' . wp_generate_password( 6, false ) . '@test.com',
				'reason'    => 'Typo fix',
			)
		);

		$response = rest_get_server()->dispatch( $request );
		$this->assertSame( 403, $response->get_status() );
	}

	public function test_verify_email_change_updates_customer_email() {
		global $wpdb;

		$this->login_as( $this->admin_id, 'admin' );
		$new_email = 'new-' . wp_generate_password( 6, false ) . '@test.com';

		$request = new WP_REST_Request( 'POST', '/' . self::NAMESPACE . '/dashboard/customers/' . $this->customer_id . '/request-email-change' );
		$request->set_body_params(
			array(
				'new_email' => $new_email,
				'reason'    => 'Typo fix',
			)
		);
		rest_get_server()->dispatch( $request );

		$row = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT email_change_token FROM {$wpdb->prefix}bookings_customers WHERE id = %d LIMIT 1",
				$this->customer_id
			),
			ARRAY_A
		);
		$token = (string) ( $row['email_change_token'] ?? '' );
		$this->assertNotEmpty( $token );

		$verify = new WP_REST_Request( 'GET', '/' . self::NAMESPACE . '/wizard/verify-email-change' );
		$verify->set_param( 'token', $token );
		$verify->set_param( 'customer_id', $this->customer_id );
		$response = rest_get_server()->dispatch( $verify );

		$this->assertSame( 200, $response->get_status() );

		$stored = (string) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT email FROM {$wpdb->prefix}bookings_customers WHERE id = %d LIMIT 1",
				$this->customer_id
			)
		);
		$this->assertSame( $new_email, $stored );
	}

	public function test_verify_email_change_rejects_expired_token() {
		global $wpdb;

		$token = 'tok_' . wp_generate_password( 10, false, false );
		$wpdb->update(
			$wpdb->prefix . 'bookings_customers',
			array(
				'pending_email_change' => 'expired-' . wp_generate_password( 6, false ) . '@test.com',
				'email_change_token'   => $token,
				'email_change_expires' => gmdate( 'Y-m-d H:i:s', time() - 10 ),
			),
			array( 'id' => $this->customer_id )
		);

		$verify = new WP_REST_Request( 'GET', '/' . self::NAMESPACE . '/wizard/verify-email-change' );
		$verify->set_param( 'token', $token );
		$verify->set_param( 'customer_id', $this->customer_id );
		$response = rest_get_server()->dispatch( $verify );

		$this->assertSame( 400, $response->get_status() );
		$this->assertSame( 'This verification link has expired.', (string) ( $response->get_data()['message'] ?? '' ) );
	}

	public function test_verify_email_change_rejects_invalid_token() {
		global $wpdb;

		$wpdb->update(
			$wpdb->prefix . 'bookings_customers',
			array(
				'pending_email_change' => 'invalid-' . wp_generate_password( 6, false ) . '@test.com',
				'email_change_token'   => 'correct_' . wp_generate_password( 10, false, false ),
				'email_change_expires' => gmdate( 'Y-m-d H:i:s', time() + 3600 ),
			),
			array( 'id' => $this->customer_id )
		);

		$verify = new WP_REST_Request( 'GET', '/' . self::NAMESPACE . '/wizard/verify-email-change' );
		$verify->set_param( 'token', 'wrong_' . wp_generate_password( 10, false, false ) );
		$verify->set_param( 'customer_id', $this->customer_id );
		$response = rest_get_server()->dispatch( $verify );

		$this->assertSame( 400, $response->get_status() );
		$this->assertSame( 'Invalid verification link.', (string) ( $response->get_data()['message'] ?? '' ) );
	}

	public function test_verify_email_change_clears_pending_columns() {
		global $wpdb;

		$this->login_as( $this->admin_id, 'admin' );
		$new_email = 'new-' . wp_generate_password( 6, false ) . '@test.com';

		$request = new WP_REST_Request( 'POST', '/' . self::NAMESPACE . '/dashboard/customers/' . $this->customer_id . '/request-email-change' );
		$request->set_body_params(
			array(
				'new_email' => $new_email,
				'reason'    => 'Typo fix',
			)
		);
		rest_get_server()->dispatch( $request );

		$row = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT pending_email_change, email_change_token, email_change_expires FROM {$wpdb->prefix}bookings_customers WHERE id = %d LIMIT 1",
				$this->customer_id
			),
			ARRAY_A
		);
		$this->assertNotEmpty( $row['pending_email_change'] );
		$this->assertNotEmpty( $row['email_change_token'] );
		$this->assertNotEmpty( $row['email_change_expires'] );

		$verify = new WP_REST_Request( 'GET', '/' . self::NAMESPACE . '/wizard/verify-email-change' );
		$verify->set_param( 'token', (string) $row['email_change_token'] );
		$verify->set_param( 'customer_id', $this->customer_id );
		rest_get_server()->dispatch( $verify );

		$cleared = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT pending_email_change, email_change_token, email_change_expires FROM {$wpdb->prefix}bookings_customers WHERE id = %d LIMIT 1",
				$this->customer_id
			),
			ARRAY_A
		);
		$this->assertNull( $cleared['pending_email_change'] );
		$this->assertNull( $cleared['email_change_token'] );
		$this->assertNull( $cleared['email_change_expires'] );
	}

	public function test_verify_email_change_fires_audit_log() {
		global $wpdb;

		$this->login_as( $this->admin_id, 'admin' );
		$new_email = 'new-' . wp_generate_password( 6, false ) . '@test.com';

		$request = new WP_REST_Request( 'POST', '/' . self::NAMESPACE . '/dashboard/customers/' . $this->customer_id . '/request-email-change' );
		$request->set_body_params(
			array(
				'new_email' => $new_email,
				'reason'    => 'Typo fix',
			)
		);
		rest_get_server()->dispatch( $request );

		$token = (string) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT email_change_token FROM {$wpdb->prefix}bookings_customers WHERE id = %d LIMIT 1",
				$this->customer_id
			)
		);

		$verify = new WP_REST_Request( 'GET', '/' . self::NAMESPACE . '/wizard/verify-email-change' );
		$verify->set_param( 'token', $token );
		$verify->set_param( 'customer_id', $this->customer_id );
		rest_get_server()->dispatch( $verify );

		$action = (string) $wpdb->get_var(
			"SELECT action FROM {$wpdb->prefix}bookings_audit_log ORDER BY id DESC LIMIT 1"
		); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared

		$this->assertSame( 'customer.email_change_confirmed', $action );
	}

	public function test_email_change_rate_limited_after_threshold() {
		$this->login_as( $this->admin_id, 'admin' );

		for ( $i = 0; $i < 5; $i++ ) {
			$request = new WP_REST_Request( 'POST', '/' . self::NAMESPACE . '/dashboard/customers/' . $this->customer_id . '/request-email-change' );
			$request->set_body_params(
				array(
					'new_email' => 'new-' . $i . '-' . wp_generate_password( 6, false ) . '@test.com',
					'reason'    => 'Other',
				)
			);
			$response = rest_get_server()->dispatch( $request );
			$this->assertSame( 200, $response->get_status() );
		}

		$sixth = new WP_REST_Request( 'POST', '/' . self::NAMESPACE . '/dashboard/customers/' . $this->customer_id . '/request-email-change' );
		$sixth->set_body_params(
			array(
				'new_email' => 'new-6-' . wp_generate_password( 6, false ) . '@test.com',
				'reason'    => 'Other',
			)
		);
		$response = rest_get_server()->dispatch( $sixth );
		$this->assertSame( 429, $response->get_status() );
	}

	private function login_as( $staff_id, $role = 'staff' ) {
		global $wpdb;

		$staff = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT id, email, first_name, last_name, role FROM {$wpdb->prefix}bookings_staff WHERE id = %d",
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

	private function create_test_staff( $args = array() ) {
		global $wpdb;

		$defaults = array(
			'email'         => 'staff-' . wp_generate_password( 6, false ) . '@test.com',
			'password_hash' => wp_hash_password( 'password123' ),
			'first_name'    => 'Test',
			'last_name'     => 'Staff',
			'phone'         => '07700900000',
			'photo_url'     => null,
			'bio'           => 'Test bio',
			'title'         => 'Therapist',
			'role'          => 'staff',
			'is_active'     => 1,
			'display_order' => 0,
			'created_at'    => current_time( 'mysql' ),
			'updated_at'    => current_time( 'mysql' ),
			'deleted_at'    => null,
		);

		$data = wp_parse_args( $args, $defaults );

		$wpdb->insert(
			$wpdb->prefix . 'bookings_staff',
			$data
		);
		return (int) $wpdb->insert_id;
	}

	private function create_test_customer( $args = array() ) {
		global $wpdb;

		$defaults = array(
			'email'      => 'customer-' . wp_generate_password( 6, false ) . '@test.com',
			'first_name' => 'Test',
			'last_name'  => 'Customer',
			'phone'      => '07700900000',
			'created_at' => current_time( 'mysql' ),
			'updated_at' => current_time( 'mysql' ),
		);

		$data = wp_parse_args( $args, $defaults );

		$wpdb->insert(
			$wpdb->prefix . 'bookings_customers',
			$data
		);
		return (int) $wpdb->insert_id;
	}
}

