<?php
/**
 * Admin dashboard: staff GET Google Calendar fields and admin disconnect endpoint.
 *
 * @package    Bookit_Booking_System
 * @subpackage Tests
 */

/**
 * @covers Bookit_Dashboard_Bookings_API::get_staff_details
 * @covers Bookit_Google_Calendar_Rest_Controller::admin_disconnect_staff_google_calendar
 */
class Test_Google_Calendar_Admin extends WP_UnitTestCase {

	/**
	 * REST namespace.
	 *
	 * @var string
	 */
	private $namespace = 'bookit/v1';

	/**
	 * Set up.
	 */
	public function setUp(): void {
		parent::setUp();

		bookit_test_truncate_tables(
			array(
				'bookings_staff',
			)
		);

		$_SESSION = array();

		do_action( 'rest_api_init' );
	}

	/**
	 * Tear down.
	 */
	public function tearDown(): void {
		bookit_test_truncate_tables(
			array(
				'bookings_staff',
			)
		);

		$_SESSION = array();

		parent::tearDown();
	}

	/**
	 * GET staff/{id} includes google_calendar_connected and google_calendar_email.
	 */
	public function test_get_staff_response_includes_google_calendar_fields(): void {
		global $wpdb;

		$staff_id = $this->insert_staff(
			array(
				'first_name' => 'Sarah',
				'last_name'  => 'Test',
			)
		);

		$wpdb->update(
			$wpdb->prefix . 'bookings_staff',
			array(
				'google_calendar_connected' => 1,
				'google_calendar_email'     => 'sarah@gmail.com',
			),
			array( 'id' => $staff_id ),
			array( '%d', '%s' ),
			array( '%d' )
		);

		$admin_id = $this->insert_staff( array( 'role' => 'admin' ) );
		$this->login_as( $admin_id, 'admin' );

		$request  = new WP_REST_Request( 'GET', '/' . $this->namespace . '/dashboard/staff/' . $staff_id );
		$response = rest_get_server()->dispatch( $request );

		$this->assertEquals( 200, $response->get_status() );
		$data = $response->get_data();
		$this->assertTrue( $data['success'] );
		$this->assertArrayHasKey( 'google_calendar_connected', $data['staff'] );
		$this->assertArrayHasKey( 'google_calendar_email', $data['staff'] );
		$this->assertTrue( (bool) $data['staff']['google_calendar_connected'] );
		$this->assertSame( 'sarah@gmail.com', $data['staff']['google_calendar_email'] );

		$this->assertArrayNotHasKey( 'google_oauth_access_token', $data['staff'] );
		$this->assertArrayNotHasKey( 'google_oauth_refresh_token', $data['staff'] );
		$this->assertArrayNotHasKey( 'google_oauth_token_expiry', $data['staff'] );
		$this->assertArrayNotHasKey( 'password_hash', $data['staff'] );
	}

	/**
	 * Admin POST disconnect clears OAuth fields and connected flag.
	 */
	public function test_admin_disconnect_endpoint_clears_tokens(): void {
		global $wpdb;

		$staff_id = $this->insert_staff(
			array(
				'first_name' => 'OAuth',
				'last_name'  => 'User',
			)
		);

		$wpdb->update(
			$wpdb->prefix . 'bookings_staff',
			array(
				'google_oauth_access_token'  => Bookit_Encryption::encrypt( 'access-token' ),
				'google_oauth_refresh_token' => Bookit_Encryption::encrypt( 'refresh-token' ),
				'google_oauth_token_expiry'  => gmdate( 'Y-m-d H:i:s', time() + 3600 ),
				'google_calendar_email'      => 'linked@gmail.com',
				'google_calendar_connected'  => 1,
			),
			array( 'id' => $staff_id ),
			array( '%s', '%s', '%s', '%s', '%d' ),
			array( '%d' )
		);

		$admin_id = $this->insert_staff( array( 'role' => 'admin' ) );
		$this->login_as( $admin_id, 'admin' );

		$request = new WP_REST_Request( 'POST', '/' . $this->namespace . '/dashboard/staff/' . $staff_id . '/google-calendar/disconnect' );
		$response = rest_get_server()->dispatch( $request );

		$this->assertEquals( 200, $response->get_status() );
		$this->assertTrue( $response->get_data()['success'] );

		$row = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT google_calendar_connected, google_oauth_access_token, google_oauth_refresh_token, google_oauth_token_expiry, google_calendar_email
				FROM {$wpdb->prefix}bookings_staff WHERE id = %d",
				$staff_id
			),
			ARRAY_A
		);

		$this->assertIsArray( $row );
		$this->assertSame( 0, (int) $row['google_calendar_connected'] );
		$this->assertNull( $row['google_oauth_access_token'] );
		$this->assertNull( $row['google_oauth_refresh_token'] );
		$this->assertNull( $row['google_oauth_token_expiry'] );
		$this->assertNull( $row['google_calendar_email'] );
	}

	/**
	 * Non-admin staff cannot call admin disconnect endpoint.
	 */
	public function test_staff_role_cannot_access_disconnect_endpoint(): void {
		$target_id = $this->insert_staff( array( 'first_name' => 'Target' ) );
		$other_id  = $this->insert_staff( array( 'first_name' => 'Caller', 'role' => 'staff' ) );

		$this->login_as( $other_id, 'staff' );

		$request = new WP_REST_Request( 'POST', '/' . $this->namespace . '/dashboard/staff/' . $target_id . '/google-calendar/disconnect' );
		$response = rest_get_server()->dispatch( $request );

		$this->assertTrue( $response->is_error() );
		$this->assertSame( 403, $response->get_status() );
	}

	/**
	 * Disconnect for missing staff returns 404.
	 */
	public function test_disconnect_endpoint_returns_404_for_nonexistent_staff(): void {
		$admin_id = $this->insert_staff( array( 'role' => 'admin' ) );
		$this->login_as( $admin_id, 'admin' );

		$request = new WP_REST_Request( 'POST', '/' . $this->namespace . '/dashboard/staff/999999/google-calendar/disconnect' );
		$response = rest_get_server()->dispatch( $request );

		$this->assertSame( 404, $response->get_status() );
	}

	/**
	 * Simulate Bookit dashboard login via session.
	 *
	 * @param int    $staff_id Staff row ID.
	 * @param string $role     'admin' or 'staff'.
	 */
	private function login_as( int $staff_id, string $role = 'staff' ): void {
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
	 * Insert a staff row with sensible defaults.
	 *
	 * @param array $overrides Field overrides.
	 * @return int Inserted staff id.
	 */
	private function insert_staff( array $overrides = array() ): int {
		global $wpdb;

		$defaults = array(
			'first_name'         => 'Test',
			'last_name'          => 'Staff',
			'email'              => 'staff-' . wp_generate_password( 8, false, false ) . '@example.com',
			'password_hash'      => wp_hash_password( 'x' ),
			'phone'              => '07700900000',
			'photo_url'          => null,
			'bio'                => '',
			'title'              => '',
			'role'               => 'staff',
			'google_calendar_id' => null,
			'is_active'          => 1,
			'display_order'      => 0,
			'created_at'         => current_time( 'mysql' ),
			'updated_at'         => current_time( 'mysql' ),
			'deleted_at'         => null,
		);

		$data = wp_parse_args( $overrides, $defaults );

		$formats = array();
		$insert  = array();
		foreach ( $data as $key => $value ) {
			$insert[ $key ] = $value;
			if ( in_array( $key, array( 'is_active', 'display_order' ), true ) ) {
				$formats[] = '%d';
			} else {
				$formats[] = '%s';
			}
		}

		$wpdb->insert( $wpdb->prefix . 'bookings_staff', $insert, $formats );
		return (int) $wpdb->insert_id;
	}
}
