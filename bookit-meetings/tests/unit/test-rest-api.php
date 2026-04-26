<?php
/**
 * Tests for Bookit Meetings REST API (Sprint 1, Task 3).
 *
 * @package Bookit_Meetings
 */

if ( ! function_exists( 'bookit_test_table_exists' ) ) {
	/**
	 * Check whether a test database table exists.
	 *
	 * @param string $full_table_name Full table name with prefix.
	 * @return bool
	 */
	function bookit_test_table_exists( string $full_table_name ): bool {
		global $wpdb;

		$table = $wpdb->get_var(
			$wpdb->prepare(
				'SHOW TABLES LIKE %s',
				$full_table_name
			)
		);

		return $table === $full_table_name;
	}
}

if ( ! function_exists( 'bookit_test_truncate_tables' ) ) {
	/**
	 * Truncate tables in a FK-safe block for tests.
	 *
	 * @param array<int, string> $table_suffixes Table suffixes without prefix.
	 * @return void
	 */
	function bookit_test_truncate_tables( array $table_suffixes ): void {
		global $wpdb;

		$unique_suffixes = array_values( array_unique( $table_suffixes ) );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
		$wpdb->query( 'SET FOREIGN_KEY_CHECKS = 0' );
		try {
			foreach ( $unique_suffixes as $table_suffix ) {
				$full_table = $wpdb->prefix . $table_suffix;
				if ( ! bookit_test_table_exists( $full_table ) ) {
					continue;
				}

				// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.DirectDatabaseQuery.DirectQuery
				$wpdb->query( "TRUNCATE TABLE {$full_table}" );
			}
		} finally {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
			$wpdb->query( 'SET FOREIGN_KEY_CHECKS = 1' );
		}
	}
}

class Test_Bookit_Meetings_Settings_Api extends WP_UnitTestCase {
	public function setUp(): void {
		parent::setUp();

		bookit_test_truncate_tables( array( 'bookings', 'bookings_settings' ) );
		$_SESSION = array();

		$this->seed_default_settings();

		do_action( 'rest_api_init' );
	}

	public function tearDown(): void {
		bookit_test_truncate_tables( array( 'bookings', 'bookings_settings' ) );
		$_SESSION = array();

		parent::tearDown();
	}

	private function seed_default_settings(): void {
		global $wpdb;

		$settings_table = $wpdb->prefix . 'bookings_settings';
		$defaults       = array(
			'meetings_enabled'    => '0',
			'meetings_platform'   => '',
			'meetings_manual_url' => '',
		);

		foreach ( $defaults as $key => $value ) {
			$wpdb->insert(
				$settings_table,
				array(
					'setting_key'   => $key,
					'setting_value' => $value,
				),
				array( '%s', '%s' )
			);
		}
	}

	private function login_as_role( string $role ): void {
		$_SESSION['staff_id']      = 1;
		$_SESSION['staff_email']   = 'test@example.com';
		$_SESSION['staff_role']    = $role;
		$_SESSION['staff_name']    = 'Test User';
		$_SESSION['is_logged_in']  = true;
		$_SESSION['last_activity'] = time();
	}

	public function test_get_settings_route_is_registered(): void {
		$routes = rest_get_server()->get_routes();
		$this->assertIsArray( $routes );
		$this->assertArrayHasKey( '/bookit-meetings/v1/settings', $routes );
	}

	public function test_get_settings_requires_admin(): void {
		$request  = new WP_REST_Request( 'GET', '/bookit-meetings/v1/settings' );
		$response = rest_get_server()->dispatch( $request );

		$this->assertInstanceOf( WP_REST_Response::class, $response );
		$this->assertSame( 401, $response->get_status() );
	}

	public function test_get_settings_blocks_staff_role(): void {
		$this->login_as_role( 'staff' );

		$request  = new WP_REST_Request( 'GET', '/bookit-meetings/v1/settings' );
		$response = rest_get_server()->dispatch( $request );

		$this->assertInstanceOf( WP_REST_Response::class, $response );
		$this->assertSame( 403, $response->get_status() );
	}

	public function test_get_settings_returns_defaults(): void {
		$this->login_as_role( 'admin' );

		$request  = new WP_REST_Request( 'GET', '/bookit-meetings/v1/settings' );
		$response = rest_get_server()->dispatch( $request );

		$this->assertSame( 200, $response->get_status() );
		$data = $response->get_data();

		$this->assertTrue( $data['success'] );
		$this->assertIsArray( $data['data'] );
		$this->assertArrayHasKey( 'meetings_enabled', $data['data'] );
		$this->assertArrayHasKey( 'meetings_platform', $data['data'] );
		$this->assertArrayHasKey( 'meetings_manual_url', $data['data'] );

		$this->assertSame( '0', $data['data']['meetings_enabled'] );
		$this->assertSame( '', $data['data']['meetings_platform'] );
		$this->assertSame( '', $data['data']['meetings_manual_url'] );
	}

	public function test_post_settings_updates_meetings_enabled(): void {
		$this->login_as_role( 'admin' );

		$request = new WP_REST_Request( 'POST', '/bookit-meetings/v1/settings' );
		$request->set_header( 'Content-Type', 'application/json' );
		$request->set_body( wp_json_encode( array( 'meetings_enabled' => '1' ) ) );

		$response = rest_get_server()->dispatch( $request );

		$this->assertSame( 200, $response->get_status() );
		$data = $response->get_data();
		$this->assertTrue( $data['success'] );
		$this->assertSame( '1', $data['data']['meetings_enabled'] );
	}

	public function test_post_settings_rejects_invalid_platform(): void {
		$this->login_as_role( 'admin' );

		$request = new WP_REST_Request( 'POST', '/bookit-meetings/v1/settings' );
		$request->set_header( 'Content-Type', 'application/json' );
		$request->set_body( wp_json_encode( array( 'meetings_platform' => 'zoom' ) ) );

		$response = rest_get_server()->dispatch( $request );

		$this->assertSame( 422, $response->get_status() );
		$data = $response->get_data();
		$this->assertSame( 'bookit_meetings_invalid_setting', $data['code'] );
	}

	public function test_post_settings_rejects_invalid_url(): void {
		$this->login_as_role( 'admin' );

		$request = new WP_REST_Request( 'POST', '/bookit-meetings/v1/settings' );
		$request->set_header( 'Content-Type', 'application/json' );
		$request->set_body( wp_json_encode( array( 'meetings_manual_url' => 'not-a-url' ) ) );

		$response = rest_get_server()->dispatch( $request );

		$this->assertSame( 422, $response->get_status() );
		$data = $response->get_data();
		$this->assertSame( 'bookit_meetings_invalid_setting', $data['code'] );
	}

	public function test_post_settings_accepts_empty_platform(): void {
		$this->login_as_role( 'admin' );

		$request = new WP_REST_Request( 'POST', '/bookit-meetings/v1/settings' );
		$request->set_header( 'Content-Type', 'application/json' );
		$request->set_body( wp_json_encode( array( 'meetings_platform' => '' ) ) );

		$response = rest_get_server()->dispatch( $request );

		$this->assertSame( 200, $response->get_status() );
		$data = $response->get_data();
		$this->assertTrue( $data['success'] );
		$this->assertSame( '', $data['data']['meetings_platform'] );
	}
}

class Test_Bookit_Meetings_Link_Api extends WP_UnitTestCase {
	public function setUp(): void {
		parent::setUp();

		bookit_test_truncate_tables( array( 'bookings', 'bookings_settings' ) );
		$_SESSION = array();

		do_action( 'rest_api_init' );
	}

	public function tearDown(): void {
		bookit_test_truncate_tables( array( 'bookings', 'bookings_settings' ) );
		$_SESSION = array();

		parent::tearDown();
	}

	private function login_as_admin(): void {
		$_SESSION['staff_id']      = 1;
		$_SESSION['staff_email']   = 'admin@example.com';
		$_SESSION['staff_role']    = 'admin';
		$_SESSION['staff_name']    = 'Admin User';
		$_SESSION['is_logged_in']  = true;
		$_SESSION['last_activity'] = time();
	}

	private function create_test_booking( array $args = array() ): int {
		global $wpdb;

		$defaults = array(
			'customer_id'      => 0,
			'service_id'       => 0,
			'staff_id'         => 0,
			'booking_date'     => '2026-06-15',
			'start_time'       => '10:00:00',
			'end_time'         => '11:00:00',
			'duration'         => 60,
			'status'           => 'confirmed',
			'total_price'      => 50.00,
			'deposit_paid'     => 0.00,
			'balance_due'      => 50.00,
			'full_amount_paid' => 0,
			'payment_method'   => 'cash',
			'created_at'       => current_time( 'mysql' ),
			'updated_at'       => current_time( 'mysql' ),
			'deleted_at'       => null,
			'meeting_link'     => null,
		);

		$data = wp_parse_args( $args, $defaults );

		$wpdb->insert( $wpdb->prefix . 'bookings', $data );
		return (int) $wpdb->insert_id;
	}

	public function test_post_link_route_is_registered(): void {
		$routes = rest_get_server()->get_routes();
		$this->assertIsArray( $routes );
		$this->assertArrayHasKey( '/bookit-meetings/v1/bookings/(?P<id>\\d+)/link', $routes );
	}

	public function test_post_link_requires_admin(): void {
		$request = new WP_REST_Request( 'POST', '/bookit-meetings/v1/bookings/1/link' );
		$request->set_header( 'Content-Type', 'application/json' );
		$request->set_body( wp_json_encode( array( 'meeting_link' => 'https://example.com' ) ) );

		$response = rest_get_server()->dispatch( $request );

		$this->assertSame( 401, $response->get_status() );
	}

	public function test_post_link_returns_404_for_missing_booking(): void {
		$this->login_as_admin();

		$request = new WP_REST_Request( 'POST', '/bookit-meetings/v1/bookings/999/link' );
		$request->set_header( 'Content-Type', 'application/json' );
		$request->set_body( wp_json_encode( array( 'meeting_link' => 'https://example.com' ) ) );

		$response = rest_get_server()->dispatch( $request );

		$this->assertSame( 404, $response->get_status() );
	}

	public function test_post_link_sets_meeting_link(): void {
		global $wpdb;

		$booking_id = $this->create_test_booking();
		$this->login_as_admin();

		$url     = 'https://teams.microsoft.com/l/meetup-join/abc';
		$request = new WP_REST_Request( 'POST', '/bookit-meetings/v1/bookings/' . $booking_id . '/link' );
		$request->set_header( 'Content-Type', 'application/json' );
		$request->set_body( wp_json_encode( array( 'meeting_link' => $url ) ) );

		$response = rest_get_server()->dispatch( $request );
		$this->assertSame( 200, $response->get_status() );

		$data = $response->get_data();
		$this->assertTrue( $data['success'] );
		$this->assertSame( $booking_id, $data['data']['booking_id'] );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.PreparedSQL.NotPrepared
		$saved = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT meeting_link FROM {$wpdb->prefix}bookings WHERE id = %d LIMIT 1",
				$booking_id
			)
		);

		$this->assertSame( $url, (string) $saved );
	}

	public function test_post_link_clears_meeting_link(): void {
		global $wpdb;

		$booking_id = $this->create_test_booking( array( 'meeting_link' => 'https://example.com/old' ) );
		$this->login_as_admin();

		$request = new WP_REST_Request( 'POST', '/bookit-meetings/v1/bookings/' . $booking_id . '/link' );
		$request->set_header( 'Content-Type', 'application/json' );
		$request->set_body( wp_json_encode( array( 'meeting_link' => '' ) ) );

		$response = rest_get_server()->dispatch( $request );
		$this->assertSame( 200, $response->get_status() );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.PreparedSQL.NotPrepared
		$saved = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT meeting_link FROM {$wpdb->prefix}bookings WHERE id = %d LIMIT 1",
				$booking_id
			)
		);

		$this->assertNull( $saved );
	}

	public function test_post_link_rejects_invalid_url(): void {
		$booking_id = $this->create_test_booking();
		$this->login_as_admin();

		$request = new WP_REST_Request( 'POST', '/bookit-meetings/v1/bookings/' . $booking_id . '/link' );
		$request->set_header( 'Content-Type', 'application/json' );
		$request->set_body( wp_json_encode( array( 'meeting_link' => 'not-a-url' ) ) );

		$response = rest_get_server()->dispatch( $request );

		$this->assertSame( 422, $response->get_status() );
		$data = $response->get_data();
		$this->assertSame( 'bookit_meetings_invalid_setting', $data['code'] );
	}
}

