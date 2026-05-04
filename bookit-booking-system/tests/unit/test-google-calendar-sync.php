<?php
/**
 * Tests for Bookit_Google_Calendar and bookit_enqueue_calendar_sync().
 *
 * @package    Bookit_Booking_System
 * @subpackage Tests
 */

/**
 * OAuth refresh without HTTP (simulates successful refresh).
 */
class Bookit_Google_Calendar_OauthRefresh_TestDouble extends Bookit_Google_Calendar {

	/**
	 * @param \Google\Client $client              Client.
	 * @param string         $plain_refresh_token Refresh token.
	 * @return array
	 */
	protected static function oauth_refresh_with_client( \Google\Client $client, string $plain_refresh_token ): array {
		$creds = array(
			'access_token'  => 'fresh_access_token_value',
			'expires_in'    => 3600,
			'created'       => time(),
			'refresh_token' => $plain_refresh_token,
		);
		$client->setAccessToken( $creds );
		return $creds;
	}
}

/**
 * OAuth refresh returns error (invalid_grant).
 */
class Bookit_Google_Calendar_OauthFail_TestDouble extends Bookit_Google_Calendar {

	/**
	 * @param \Google\Client $client              Client.
	 * @param string         $plain_refresh_token Refresh token.
	 * @return array
	 */
	protected static function oauth_refresh_with_client( \Google\Client $client, string $plain_refresh_token ): array {
		return array(
			'error'             => 'invalid_grant',
			'error_description' => 'Token revoked',
		);
	}
}

/**
 * insert() returns a fixed event id without HTTP.
 */
class Bookit_Google_Calendar_Create_TestDouble extends Bookit_Google_Calendar {

	/**
	 * @param \Google\Service\Calendar        $service Service.
	 * @param \Google\Service\Calendar\Event $event   Event.
	 * @return \Google\Service\Calendar\Event
	 */
	protected static function insert_primary_calendar_event(
		\Google\Service\Calendar $service,
		\Google\Service\Calendar\Event $event
	): \Google\Service\Calendar\Event {
		$created = new \Google\Service\Calendar\Event();
		$created->setId( 'google_evt_test_123' );
		return $created;
	}
}

/**
 * insert() throws to simulate API failure.
 */
class Bookit_Google_Calendar_CreateThrows_TestDouble extends Bookit_Google_Calendar {

	/**
	 * @param \Google\Service\Calendar        $service Service.
	 * @param \Google\Service\Calendar\Event $event   Event.
	 * @return \Google\Service\Calendar\Event
	 */
	protected static function insert_primary_calendar_event(
		\Google\Service\Calendar $service,
		\Google\Service\Calendar\Event $event
	): \Google\Service\Calendar\Event {
		throw new \RuntimeException( 'Google API unavailable' );
	}
}

/**
 * Tracks create_event calls for update fallback tests.
 */
class Bookit_Google_Calendar_Update_TestDouble extends Bookit_Google_Calendar {

	/**
	 * @var int
	 */
	public static int $create_event_calls = 0;

	/**
	 * @param int   $booking_id Booking ID.
	 * @param array $booking    Booking row.
	 * @return string|null
	 */
	public static function create_event( int $booking_id, array $booking ): ?string {
		self::$create_event_calls++;
		return 'evt_from_create_fallback';
	}
}

/**
 * Tracks delete_event for process_sync_job routing.
 */
class Bookit_Google_Calendar_ProcessJob_TestDouble extends Bookit_Google_Calendar {

	/**
	 * @var int
	 */
	public static int $delete_event_calls = 0;

	/**
	 * @param int $booking_id Booking ID.
	 * @return void
	 */
	public static function delete_event( int $booking_id ): void {
		self::$delete_event_calls++;
	}
}

/**
 * delete() is a no-op; parent clears DB after "success".
 */
class Bookit_Google_Calendar_DeleteNoop_TestDouble extends Bookit_Google_Calendar {

	/**
	 * @param \Google\Service\Calendar $service  Service.
	 * @param string                   $event_id Event ID.
	 * @return void
	 */
	protected static function delete_primary_calendar_event( \Google\Service\Calendar $service, string $event_id ): void {
		// Intentionally empty — simulate successful API delete without HTTP.
	}
}

/**
 * @covers Bookit_Google_Calendar
 * @covers bookit_enqueue_calendar_sync
 */
class Test_Google_Calendar_Sync extends WP_UnitTestCase {

	/**
	 * Set up.
	 */
	public function setUp(): void {
		parent::setUp();

		bookit_test_truncate_tables(
			array(
				'bookings_audit_log',
				'bookings',
				'bookings_staff_services',
				'bookings_services',
				'bookings_staff',
				'bookings_customers',
				'bookings_settings',
			)
		);

		Bookit_Google_Calendar::set_test_client( null );
		Bookit_Google_Calendar_Update_TestDouble::$create_event_calls   = 0;
		Bookit_Google_Calendar_ProcessJob_TestDouble::$delete_event_calls = 0;
	}

	/**
	 * Tear down.
	 */
	public function tearDown(): void {
		Bookit_Google_Calendar::set_test_client( null );
		Bookit_Google_Calendar_Update_TestDouble::$create_event_calls   = 0;
		Bookit_Google_Calendar_ProcessJob_TestDouble::$delete_event_calls = 0;

		bookit_test_truncate_tables(
			array(
				'bookings_audit_log',
				'bookings',
				'bookings_staff_services',
				'bookings_services',
				'bookings_staff',
				'bookings_customers',
				'bookings_settings',
			)
		);

		parent::tearDown();
	}

	/**
	 * @covers Bookit_Google_Calendar::get_client_for_staff
	 */
	public function test_get_client_returns_null_when_staff_not_connected(): void {
		$this->seed_google_oauth_settings();

		$staff_id = $this->create_test_staff();
		$this->set_staff_google_tokens(
			$staff_id,
			0,
			'a',
			'r',
			gmdate( 'Y-m-d H:i:s', time() + 3600 )
		);

		$this->assertNull( Bookit_Google_Calendar::get_client_for_staff( $staff_id ) );
	}

	/**
	 * @covers Bookit_Google_Calendar::get_client_for_staff
	 */
	public function test_token_refresh_updates_db_when_expired(): void {
		global $wpdb;

		$this->seed_google_oauth_settings();

		$plain_access  = 'old_access';
		$plain_refresh = 'stable_refresh';

		$staff_id = $this->create_test_staff();
		$this->set_staff_google_tokens(
			$staff_id,
			1,
			$plain_access,
			$plain_refresh,
			'2000-01-01 00:00:00'
		);

		$client = Bookit_Google_Calendar_OauthRefresh_TestDouble::get_client_for_staff( $staff_id );
		$this->assertInstanceOf( \Google\Client::class, $client );

		$row = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT google_oauth_access_token, google_oauth_token_expiry FROM {$wpdb->prefix}bookings_staff WHERE id = %d",
				$staff_id
			),
			ARRAY_A
		);

		$this->assertSame( 'fresh_access_token_value', Bookit_Encryption::decrypt( (string) $row['google_oauth_access_token'] ) );
		$this->assertNotSame( $plain_access, Bookit_Encryption::decrypt( (string) $row['google_oauth_access_token'] ) );

		$exp_ts = strtotime( (string) $row['google_oauth_token_expiry'] );
		$this->assertGreaterThan( time(), $exp_ts );
		$this->assertLessThanOrEqual( time() + 3700, $exp_ts );
	}

	/**
	 * @covers Bookit_Google_Calendar::get_client_for_staff
	 */
	public function test_token_refresh_failure_returns_null(): void {
		global $wpdb;

		$this->seed_google_oauth_settings();

		$staff_id = $this->create_test_staff();
		$this->set_staff_google_tokens( $staff_id, 1, 'old', 'refresh', '2000-01-01 00:00:00' );

		$this->assertNull( Bookit_Google_Calendar_OauthFail_TestDouble::get_client_for_staff( $staff_id ) );

		$action = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT action FROM {$wpdb->prefix}bookings_audit_log WHERE action = %s ORDER BY id DESC LIMIT 1",
				'google_calendar.token_refresh_failed'
			)
		);
		$this->assertSame( 'google_calendar.token_refresh_failed', $action );
	}

	/**
	 * @covers Bookit_Google_Calendar::create_event
	 */
	/**
	 * @covers Bookit_Google_Calendar::build_calendar_event_from_booking
	 */
	public function test_event_summary_format(): void {
		$booking = array(
			'staff_id'          => 1,
			'date'              => '2026-06-15',
			'start_time'        => '14:30:00',
			'end_time'          => '15:30:00',
			'service_name'      => 'Deep Tissue',
			'customer_first'    => 'Sam',
			'customer_last'     => 'Rivera',
			'customer_phone'    => '07700900111',
			'booking_reference' => 'BKTEST-AB12',
			'special_requests'  => '',
			'company_name'      => 'Studio',
		);

		$event = $this->invoke_build_calendar_event_from_booking( $booking );
		$this->assertSame( 'Deep Tissue — Sam Rivera', $event->getSummary() );
	}

	/**
	 * @covers Bookit_Google_Calendar::build_calendar_event_from_booking
	 */
	public function test_event_description_omits_special_requests_when_empty(): void {
		$booking = array(
			'staff_id'          => 1,
			'date'              => '2026-06-15',
			'start_time'        => '14:30:00',
			'end_time'          => '15:30:00',
			'service_name'      => 'Cut',
			'customer_first'    => 'A',
			'customer_last'     => 'B',
			'customer_phone'    => '07700900222',
			'booking_reference' => 'BKTEST-XY99',
			'special_requests'  => '',
			'company_name'      => '',
		);

		$event = $this->invoke_build_calendar_event_from_booking( $booking );
		$desc = (string) $event->getDescription();
		$this->assertStringNotContainsString( 'Special requests:', $desc );

		$booking['special_requests'] = '   ';
		$event2 = $this->invoke_build_calendar_event_from_booking( $booking );
		$this->assertStringNotContainsString( 'Special requests:', (string) $event2->getDescription() );
	}

	/**
	 * @covers Bookit_Google_Calendar::build_calendar_event_from_booking
	 */
	public function test_event_description_includes_special_requests_when_present(): void {
		$booking = array(
			'staff_id'          => 1,
			'date'              => '2026-06-15',
			'start_time'        => '14:30:00',
			'end_time'          => '15:30:00',
			'service_name'      => 'Cut',
			'customer_first'    => 'A',
			'customer_last'     => 'B',
			'customer_phone'    => '07700900222',
			'booking_reference' => 'BKTEST-XY99',
			'special_requests'  => 'Ground floor only',
			'company_name'      => '',
		);

		$event = $this->invoke_build_calendar_event_from_booking( $booking );
		$this->assertStringContainsString( 'Special requests: Ground floor only', (string) $event->getDescription() );
	}

	/**
	 * @covers Bookit_Google_Calendar::build_calendar_event_from_booking
	 */
	public function test_event_has_15_minute_popup_reminder(): void {
		$booking = array(
			'staff_id'          => 1,
			'date'              => '2026-06-15',
			'start_time'        => '14:30:00',
			'end_time'          => '15:30:00',
			'service_name'      => 'S',
			'customer_first'    => 'X',
			'customer_last'     => 'Y',
			'customer_phone'    => '1',
			'booking_reference' => 'R',
			'special_requests'  => '',
			'company_name'      => '',
		);

		$event   = $this->invoke_build_calendar_event_from_booking( $booking );
		$reminds = $event->getReminders();
		$this->assertInstanceOf( \Google\Service\Calendar\EventReminders::class, $reminds );
		$this->assertFalse( (bool) $reminds->getUseDefault() );
		$overrides = $reminds->getOverrides();
		$this->assertIsArray( $overrides );
		$this->assertCount( 1, $overrides );
		$this->assertSame( 'popup', $overrides[0]->getMethod() );
		$this->assertSame( 15, (int) $overrides[0]->getMinutes() );
	}

	/**
	 * @covers Bookit_Google_Calendar::build_calendar_event_from_booking
	 */
	public function test_event_color_is_blue(): void {
		$booking = array(
			'staff_id'          => 1,
			'date'              => '2026-06-15',
			'start_time'        => '14:30:00',
			'end_time'          => '15:30:00',
			'service_name'      => 'S',
			'customer_first'    => 'X',
			'customer_last'     => 'Y',
			'customer_phone'    => '1',
			'booking_reference' => 'R',
			'special_requests'  => '',
			'company_name'      => '',
		);

		$event = $this->invoke_build_calendar_event_from_booking( $booking );
		$this->assertSame( '7', $event->getColorId() );
	}

	/**
	 * @covers Bookit_Google_Calendar::build_calendar_event_from_booking
	 */
	public function test_event_location_omitted_when_business_name_empty(): void {
		$booking = array(
			'staff_id'          => 1,
			'date'              => '2026-06-15',
			'start_time'        => '14:30:00',
			'end_time'          => '15:30:00',
			'service_name'      => 'S',
			'customer_first'    => 'X',
			'customer_last'     => 'Y',
			'customer_phone'    => '1',
			'booking_reference' => 'R',
			'special_requests'  => '',
			'company_name'      => '',
		);

		$event = $this->invoke_build_calendar_event_from_booking( $booking );
		$loc = $event->getLocation();
		$this->assertTrue( null === $loc || '' === $loc, 'Location must be unset when business name is empty.' );
	}

	/**
	 * @param array $booking Booking payload for calendar event builder.
	 * @return \Google\Service\Calendar\Event
	 */
	private function invoke_build_calendar_event_from_booking( array $booking ): \Google\Service\Calendar\Event {
		$ref    = new \ReflectionClass( Bookit_Google_Calendar::class );
		$method = $ref->getMethod( 'build_calendar_event_from_booking' );
		$method->setAccessible( true );
		return $method->invoke( null, $booking );
	}

	/**
	 * @covers Bookit_Google_Calendar::create_event
	 */
	public function test_create_event_returns_event_id(): void {
		global $wpdb;

		$this->seed_google_oauth_settings();

		$staff_id = $this->create_test_staff();
		$this->set_staff_google_tokens(
			$staff_id,
			1,
			'acc',
			'ref',
			gmdate( 'Y-m-d H:i:s', time() + 7200 )
		);
		$service_id  = $this->insert_service();
		$customer_id = $this->insert_customer();
		$booking_id  = $this->insert_booking( $customer_id, $service_id, $staff_id );

		$booking = array(
			'staff_id'          => $staff_id,
			'date'              => gmdate( 'Y-m-d', strtotime( '+3 days' ) ),
			'start_time'        => '10:00:00',
			'end_time'          => '11:00:00',
			'service_name'      => 'Massage',
			'customer_first'    => 'Ann',
			'customer_last'     => 'Lee',
			'customer_phone'    => '07700900123',
			'booking_reference' => 'BK-1',
			'special_requests'  => '',
			'company_name'      => 'Test Salon',
		);

		$event_id = Bookit_Google_Calendar_Create_TestDouble::create_event( $booking_id, $booking );
		$this->assertSame( 'google_evt_test_123', $event_id );

		$stored = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT google_calendar_event_id FROM {$wpdb->prefix}bookings WHERE id = %d",
				$booking_id
			)
		);
		$this->assertSame( 'google_evt_test_123', $stored );
	}

	/**
	 * @covers Bookit_Google_Calendar::create_event
	 */
	public function test_create_event_failure_returns_null_and_logs(): void {
		global $wpdb;

		$this->seed_google_oauth_settings();

		$staff_id = $this->create_test_staff();
		$this->set_staff_google_tokens(
			$staff_id,
			1,
			'acc',
			'ref',
			gmdate( 'Y-m-d H:i:s', time() + 7200 )
		);

		$booking = array(
			'staff_id'          => $staff_id,
			'date'              => gmdate( 'Y-m-d' ),
			'start_time'        => '10:00:00',
			'end_time'          => '11:00:00',
			'service_name'      => 'Cut',
			'customer_first'    => 'A',
			'customer_last'     => 'B',
			'customer_phone'    => '1',
			'booking_reference' => 'R',
			'special_requests'  => '',
			'company_name'      => 'Shop',
		);

		$this->assertNull( Bookit_Google_Calendar_CreateThrows_TestDouble::create_event( 999, $booking ) );

		$action = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT action FROM {$wpdb->prefix}bookings_audit_log WHERE action = %s ORDER BY id DESC LIMIT 1",
				'google_calendar.sync_failed'
			)
		);
		$this->assertSame( 'google_calendar.sync_failed', $action );
	}

	/**
	 * @covers Bookit_Google_Calendar::update_event
	 */
	public function test_update_event_calls_create_when_no_event_id(): void {
		$this->seed_google_oauth_settings();

		$staff_id    = $this->create_test_staff();
		$this->set_staff_google_tokens(
			$staff_id,
			1,
			'acc',
			'ref',
			gmdate( 'Y-m-d H:i:s', time() + 7200 )
		);
		$service_id  = $this->insert_service();
		$customer_id = $this->insert_customer();
		$booking_id = $this->insert_booking( $customer_id, $service_id, $staff_id );

		$booking = array(
			'staff_id'          => $staff_id,
			'date'              => gmdate( 'Y-m-d', strtotime( '+1 day' ) ),
			'start_time'        => '09:00:00',
			'end_time'          => '10:00:00',
			'service_name'      => 'Style',
			'customer_first'    => 'C',
			'customer_last'     => 'D',
			'customer_phone'    => '2',
			'booking_reference' => 'BK-2',
			'special_requests'  => '',
			'company_name'      => 'Salon',
		);

		Bookit_Google_Calendar_Update_TestDouble::update_event( $booking_id, $booking );
		$this->assertSame( 1, Bookit_Google_Calendar_Update_TestDouble::$create_event_calls );
	}

	/**
	 * @covers Bookit_Google_Calendar::delete_event
	 */
	public function test_delete_event_clears_event_id_after_success(): void {
		global $wpdb;

		$this->seed_google_oauth_settings();

		$staff_id = $this->create_test_staff();
		$this->set_staff_google_tokens(
			$staff_id,
			1,
			'acc',
			'ref',
			gmdate( 'Y-m-d H:i:s', time() + 7200 )
		);

		$service_id  = $this->insert_service();
		$customer_id = $this->insert_customer();
		$booking_id = $this->insert_booking( $customer_id, $service_id, $staff_id );

		$wpdb->update(
			$wpdb->prefix . 'bookings',
			array( 'google_calendar_event_id' => 'evt_to_delete_999' ),
			array( 'id' => $booking_id ),
			array( '%s' ),
			array( '%d' )
		);

		Bookit_Google_Calendar_DeleteNoop_TestDouble::delete_event( $booking_id );

		$stored = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT google_calendar_event_id FROM {$wpdb->prefix}bookings WHERE id = %d",
				$booking_id
			)
		);
		$this->assertNull( $stored );
	}

	/**
	 * @covers Bookit_Google_Calendar::create_event
	 */
	public function test_sync_failure_does_not_throw(): void {
		$this->seed_google_oauth_settings();

		$staff_id = $this->create_test_staff();
		$this->set_staff_google_tokens(
			$staff_id,
			1,
			'acc',
			'ref',
			gmdate( 'Y-m-d H:i:s', time() + 7200 )
		);

		$booking = array(
			'staff_id'          => $staff_id,
			'date'              => gmdate( 'Y-m-d' ),
			'start_time'        => '10:00:00',
			'end_time'          => '11:00:00',
			'service_name'      => 'S',
			'customer_first'    => 'A',
			'customer_last'     => 'B',
			'customer_phone'    => '1',
			'booking_reference' => 'R',
			'special_requests'  => '',
			'company_name'      => 'C',
		);

		try {
			Bookit_Google_Calendar_CreateThrows_TestDouble::create_event( 1001, $booking );
		} catch ( \Throwable $e ) {
			$this->fail( 'create_event must not throw: ' . $e->getMessage() );
		}
		$this->assertTrue( true );
	}

	/**
	 * @covers bookit_enqueue_calendar_sync
	 */
	public function test_enqueue_calendar_sync_schedules_action(): void {
		wp_clear_scheduled_hook( 'bookit_process_calendar_sync' );

		bookit_enqueue_calendar_sync( 'create', 1 );

		if ( function_exists( 'as_schedule_single_action' ) ) {
			$this->markTestSkipped( 'Action Scheduler is loaded; use integration coverage for as_schedule_single_action.' );
		}

		$found = false;
		$cron  = _get_cron_array();
		if ( is_array( $cron ) ) {
			foreach ( $cron as $hooks ) {
				if ( ! isset( $hooks['bookit_process_calendar_sync'] ) ) {
					continue;
				}
				foreach ( $hooks['bookit_process_calendar_sync'] as $detail ) {
					if ( isset( $detail['args'] ) && array( 'create', 1, null ) === $detail['args'] ) {
						$found = true;
						break 2;
					}
				}
			}
		}

		$this->assertTrue( $found, 'Expected WP-Cron to schedule bookit_process_calendar_sync with args create, 1.' );
	}

	/**
	 * @covers Bookit_Google_Calendar::process_sync_job
	 */
	public function test_process_sync_job_routes_to_correct_method(): void {
		Bookit_Google_Calendar_ProcessJob_TestDouble::process_sync_job( 'delete', 555 );
		$this->assertSame( 1, Bookit_Google_Calendar_ProcessJob_TestDouble::$delete_event_calls );
	}

	/**
	 * Seed Google OAuth client id/secret for get_client_for_staff.
	 *
	 * @return void
	 */
	private function seed_google_oauth_settings(): void {
		global $wpdb;

		$wpdb->insert(
			$wpdb->prefix . 'bookings_settings',
			array(
				'setting_key'   => 'google_client_id',
				'setting_value' => 'test-client-id.apps.googleusercontent.com',
				'setting_type'  => 'string',
			),
			array( '%s', '%s', '%s' )
		);
		$wpdb->insert(
			$wpdb->prefix . 'bookings_settings',
			array(
				'setting_key'   => 'google_client_secret',
				'setting_value' => 'test-client-secret',
				'setting_type'  => 'string',
			),
			array( '%s', '%s', '%s' )
		);
	}

	/**
	 * @return int
	 */
	private function create_test_staff(): int {
		global $wpdb;

		$data = array(
			'email'                    => 'staff-' . wp_generate_password( 6, false ) . '@test.com',
			'password_hash'            => password_hash( 'password123', PASSWORD_BCRYPT ),
			'first_name'               => 'Test',
			'last_name'                => 'Staff',
			'phone'                    => '07700900000',
			'photo_url'                => null,
			'bio'                      => 'Bio',
			'title'                    => 'Role',
			'role'                     => 'staff',
			'google_calendar_id'       => null,
			'is_active'                => 1,
			'display_order'            => 0,
			'notification_preferences' => null,
			'created_at'               => current_time( 'mysql' ),
			'updated_at'               => current_time( 'mysql' ),
			'deleted_at'               => null,
		);

		$wpdb->insert(
			$wpdb->prefix . 'bookings_staff',
			$data,
			array( '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%d', '%d', '%s', '%s', '%s', '%s' )
		);

		return (int) $wpdb->insert_id;
	}

	/**
	 * @param int    $staff_id     Staff ID.
	 * @param int    $connected    google_calendar_connected (0 or 1).
	 * @param string $plain_access Plain access token before encrypt.
	 * @param string $plain_refresh Plain refresh token before encrypt.
	 * @param string $expiry_mysql Token expiry datetime.
	 * @return void
	 */
	private function set_staff_google_tokens(
		int $staff_id,
		int $connected,
		string $plain_access,
		string $plain_refresh,
		string $expiry_mysql
	): void {
		global $wpdb;

		$wpdb->update(
			$wpdb->prefix . 'bookings_staff',
			array(
				'google_calendar_connected'  => $connected,
				'google_oauth_access_token'  => Bookit_Encryption::encrypt( $plain_access ),
				'google_oauth_refresh_token' => Bookit_Encryption::encrypt( $plain_refresh ),
				'google_oauth_token_expiry'  => $expiry_mysql,
				'updated_at'                 => current_time( 'mysql' ),
			),
			array( 'id' => $staff_id ),
			array( '%d', '%s', '%s', '%s', '%s' ),
			array( '%d' )
		);
	}

	/**
	 * @param array $overrides Overrides.
	 * @return int
	 */
	private function insert_service( array $overrides = array() ): int {
		global $wpdb;

		$defaults = array(
			'name'         => 'Test Service',
			'duration'     => 60,
			'price'        => 50.00,
			'deposit_type' => 'percentage',
			'deposit_amount' => 100,
			'is_active'    => 1,
			'created_at'   => current_time( 'mysql' ),
			'updated_at'   => current_time( 'mysql' ),
		);

		$data = wp_parse_args( $overrides, $defaults );

		$wpdb->insert(
			$wpdb->prefix . 'bookings_services',
			$data,
			array( '%s', '%d', '%f', '%s', '%f', '%d', '%s', '%s' )
		);

		return (int) $wpdb->insert_id;
	}

	/**
	 * @param array $overrides Overrides.
	 * @return int
	 */
	private function insert_customer( array $overrides = array() ): int {
		global $wpdb;

		$defaults = array(
			'email'      => 'customer-' . wp_generate_password( 8, false, false ) . '@example.com',
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
	 * @param int   $customer_id Customer ID.
	 * @param int   $service_id  Service ID.
	 * @param int   $staff_id    Staff ID.
	 * @param array $overrides   Overrides.
	 * @return int
	 */
	private function insert_booking( int $customer_id, int $service_id, int $staff_id, array $overrides = array() ): int {
		global $wpdb;

		$defaults = array(
			'booking_reference'       => 'BKTEST-' . wp_generate_password( 4, false, false ),
			'customer_id'               => $customer_id,
			'service_id'                => $service_id,
			'staff_id'                  => $staff_id,
			'booking_date'              => gmdate( 'Y-m-d', strtotime( '+10 days' ) ),
			'start_time'                => '10:00:00',
			'end_time'                  => '11:00:00',
			'duration'                  => 60,
			'status'                    => 'confirmed',
			'total_price'               => 50.00,
			'deposit_amount'            => 50.00,
			'deposit_paid'              => 0.00,
			'balance_due'               => 50.00,
			'full_amount_paid'          => 0,
			'payment_method'            => 'manual',
			'created_at'                => current_time( 'mysql' ),
			'updated_at'                => current_time( 'mysql' ),
			'deleted_at'                => null,
		);

		$data = wp_parse_args( $overrides, $defaults );

		$wpdb->insert(
			$wpdb->prefix . 'bookings',
			$data,
			array(
				'%s',
				'%d',
				'%d',
				'%d',
				'%s',
				'%s',
				'%s',
				'%d',
				'%s',
				'%f',
				'%f',
				'%f',
				'%f',
				'%d',
				'%s',
				'%s',
				'%s',
				'%s',
			)
		);

		return (int) $wpdb->insert_id;
	}
}
