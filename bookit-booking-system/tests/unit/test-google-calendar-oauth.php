<?php
/**
 * Google Calendar OAuth (per staff) — encryption, callback, REST, profile fields.
 *
 * @package    Bookit_Booking_System
 * @subpackage Tests
 */

/**
 * Test double: mock token exchange; id_token carries email for JWT parsing.
 */
class Bookit_Google_Calendar_Api_TestDouble extends Bookit_Google_Calendar_Api {

	/**
	 * @param \Google\Client $client Client.
	 * @param string         $code  Code.
	 * @return array
	 */
	protected static function exchange_auth_code_for_tokens( \Google\Client $client, string $code ): array {
		$payload_json = wp_json_encode(
			array(
				'email' => 'sarah@gmail.com',
				'sub'   => 'oauth-test-sub',
			)
		);
		$header_b64  = rtrim( strtr( base64_encode( '{"alg":"HS256","typ":"JWT"}' ), '+/', '-_' ), '=' );
		$payload_b64 = rtrim( strtr( base64_encode( (string) $payload_json ), '+/', '-_' ), '=' );
		$id_token    = $header_b64 . '.' . $payload_b64 . '.mock-signature';

		return array(
			'access_token'  => 'RAW_ACCESS_TOKEN_PLAIN',
			'refresh_token' => 'RAW_REFRESH_TOKEN_PLAIN',
			'expires_in'    => 3600,
			'id_token'      => $id_token,
		);
	}
}

/**
 * Token response without id_token — email extraction returns empty; flow still completes.
 */
class Bookit_Google_Calendar_Api_TestDouble_No_Id_Token extends Bookit_Google_Calendar_Api {

	/**
	 * @param \Google\Client $client Client.
	 * @param string         $code  Code.
	 * @return array
	 */
	protected static function exchange_auth_code_for_tokens( \Google\Client $client, string $code ): array {
		return array(
			'access_token'  => 'RAW_ACCESS_TOKEN_PLAIN',
			'refresh_token' => 'RAW_REFRESH_TOKEN_PLAIN',
			'expires_in'    => 3600,
		);
	}
}

/**
 * @covers Bookit_Encryption
 * @covers Bookit_Google_Calendar_Api
 * @covers Bookit_Google_Calendar_Rest_Controller
 * @covers Bookit_Dashboard_Bookings_API::get_my_profile
 */
class Test_Google_Calendar_OAuth extends WP_UnitTestCase {

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

		global $wpdb;

		$wpdb->query( "TRUNCATE TABLE {$wpdb->prefix}bookings_staff" );
		$wpdb->query( "TRUNCATE TABLE {$wpdb->prefix}bookings_settings" );

		$_SESSION = array();

		do_action( 'rest_api_init' );
	}

	/**
	 * Tear down.
	 */
	public function tearDown(): void {
		global $wpdb;

		$wpdb->query( "TRUNCATE TABLE {$wpdb->prefix}bookings_staff" );
		$wpdb->query( "TRUNCATE TABLE {$wpdb->prefix}bookings_settings" );

		$_SESSION = array();

		parent::tearDown();
	}

	/**
	 * @covers Bookit_Google_Calendar_Rest_Controller::is_authenticated
	 */
	public function test_auth_url_endpoint_requires_authentication(): void {
		$_SESSION = array();

		$request  = new WP_REST_Request( 'GET', '/' . $this->namespace . '/google-calendar/auth-url' );
		$response = rest_get_server()->dispatch( $request );

		$this->assertTrue( $response->is_error() );
		$this->assertEquals( 401, $response->get_status() );
	}

	/**
	 * Build HMAC-signed OAuth state (same algorithm as get_auth_url()).
	 *
	 * @param int $staff_id Staff ID.
	 * @param int $expires  Unix expiry time for the state.
	 * @return string Base64 state parameter.
	 */
	private function build_signed_oauth_state( int $staff_id, int $expires ): string {
		$token   = bin2hex( random_bytes( 16 ) );
		$payload = $staff_id . ':' . $token . ':' . $expires;
		$sig     = hash_hmac( 'sha256', $payload, wp_salt( 'auth' ) );

		return base64_encode( $payload . ':' . $sig );
	}

	/**
	 * @covers Bookit_Google_Calendar_Api::handle_callback
	 */
	public function test_callback_rejects_invalid_base64_state(): void {
		$this->seed_google_oauth_settings();

		$result = Bookit_Google_Calendar_Api::handle_callback( 'fake-code', '%%%not-valid-base64%%%' );
		$this->assertEquals( 0, $result );
	}

	/**
	 * @covers Bookit_Google_Calendar_Api::handle_callback
	 */
	public function test_callback_rejects_malformed_state_wrong_part_count(): void {
		$this->seed_google_oauth_settings();

		// Three colon-separated segments after decode — need exactly four.
		$state = base64_encode( '1:tok:123' );

		$result = Bookit_Google_Calendar_Api::handle_callback( 'fake-code', $state );
		$this->assertEquals( 0, $result );
	}

	/**
	 * @covers Bookit_Google_Calendar_Api::handle_callback
	 */
	public function test_callback_rejects_expired_oauth_state(): void {
		$this->seed_google_oauth_settings();

		$staff_id = $this->create_test_staff();
		$state    = $this->build_signed_oauth_state( $staff_id, time() - 1 );

		$result = Bookit_Google_Calendar_Api::handle_callback( 'fake-code', $state );
		$this->assertEquals( 0, $result );
	}

	/**
	 * @covers Bookit_Google_Calendar_Api::handle_callback
	 */
	public function test_callback_rejects_tampered_oauth_signature(): void {
		$this->seed_google_oauth_settings();

		$staff_id = $this->create_test_staff();
		$state    = $this->build_signed_oauth_state( $staff_id, time() + 600 );
		$decoded  = base64_decode( $state, true );
		$this->assertNotFalse( $decoded );
		$parts           = explode( ':', $decoded );
		$parts[3]        = 'deadbeefdeadbeefdeadbeefdeadbeefdeadbeefdeadbeefdeadbeefdeadbeef';
		$tampered_state  = base64_encode( implode( ':', $parts ) );

		$result = Bookit_Google_Calendar_Api::handle_callback( 'fake-code', $tampered_state );
		$this->assertEquals( 0, $result );
	}

	/**
	 * Valid signed state succeeds through validation (full token path mocked).
	 *
	 * @covers Bookit_Google_Calendar_Api::handle_callback
	 */
	public function test_callback_accepts_valid_signed_state(): void {
		$this->seed_google_oauth_settings();

		$staff_id = $this->create_test_staff();
		$state    = $this->build_signed_oauth_state( $staff_id, time() + 600 );

		$result = Bookit_Google_Calendar_Api_TestDouble::handle_callback( 'fake-code', $state );
		$this->assertEquals( $staff_id, $result );
	}

	/**
	 * @covers Bookit_Google_Calendar_Api::handle_callback
	 */
	public function test_callback_stores_encrypted_tokens(): void {
		$this->seed_google_oauth_settings();

		$staff_id = $this->create_test_staff();
		$state    = $this->build_signed_oauth_state( $staff_id, time() + 600 );

		$result = Bookit_Google_Calendar_Api_TestDouble::handle_callback( 'fake-code', $state );
		$this->assertEquals( $staff_id, $result );

		global $wpdb;
		$row = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT google_oauth_access_token, google_oauth_refresh_token, google_calendar_connected, google_calendar_email FROM {$wpdb->prefix}bookings_staff WHERE id = %d",
				$staff_id
			),
			ARRAY_A
		);

		$this->assertNotEmpty( $row['google_oauth_access_token'] );
		$this->assertNotEquals( 'RAW_ACCESS_TOKEN_PLAIN', $row['google_oauth_access_token'] );
		$this->assertNotEmpty( $row['google_oauth_refresh_token'] );
		$this->assertNotEquals( 'RAW_REFRESH_TOKEN_PLAIN', $row['google_oauth_refresh_token'] );
		$this->assertEquals( '1', (string) $row['google_calendar_connected'] );
		$this->assertEquals( 'sarah@gmail.com', $row['google_calendar_email'] );
	}

	/**
	 * @covers Bookit_Google_Calendar_Api::disconnect
	 */
	public function test_disconnect_clears_token_columns(): void {
		global $wpdb;

		$staff_id = $this->create_test_staff();

		$wpdb->update(
			$wpdb->prefix . 'bookings_staff',
			array(
				'google_oauth_access_token'  => 'x',
				'google_oauth_refresh_token' => 'y',
				'google_oauth_token_expiry'  => current_time( 'mysql' ),
				'google_calendar_email'      => 'keep@example.com',
				'google_calendar_connected'  => 1,
			),
			array( 'id' => $staff_id ),
			array( '%s', '%s', '%s', '%s', '%d' ),
			array( '%d' )
		);

		Bookit_Google_Calendar_Api::disconnect( $staff_id );

		$row = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT google_oauth_access_token, google_oauth_refresh_token, google_oauth_token_expiry, google_calendar_email, google_calendar_connected FROM {$wpdb->prefix}bookings_staff WHERE id = %d",
				$staff_id
			),
			ARRAY_A
		);

		$this->assertNull( $row['google_oauth_access_token'] );
		$this->assertNull( $row['google_oauth_refresh_token'] );
		$this->assertNull( $row['google_oauth_token_expiry'] );
		$this->assertNull( $row['google_calendar_email'] );
		$this->assertEquals( '0', (string) $row['google_calendar_connected'] );
	}

	/**
	 * @covers Bookit_Google_Calendar_Api::fetch_google_account_email
	 */
	public function test_email_extracted_from_id_token(): void {
		$payload_json = wp_json_encode(
			array(
				'email' => 'jwt-user@example.com',
				'sub'   => '123',
			)
		);
		$header_b64  = rtrim( strtr( base64_encode( '{"alg":"HS256","typ":"JWT"}' ), '+/', '-_' ), '=' );
		$payload_b64 = rtrim( strtr( base64_encode( (string) $payload_json ), '+/', '-_' ), '=' );
		$id_token    = $header_b64 . '.' . $payload_b64 . '.sig';

		$method = new ReflectionMethod( Bookit_Google_Calendar_Api::class, 'fetch_google_account_email' );
		$method->setAccessible( true );
		$email = $method->invoke( null, array( 'id_token' => $id_token ) );

		$this->assertSame( 'jwt-user@example.com', $email );
	}

	/**
	 * @covers Bookit_Google_Calendar_Api::fetch_google_account_email
	 */
	public function test_missing_id_token_returns_empty_string(): void {
		$method = new ReflectionMethod( Bookit_Google_Calendar_Api::class, 'fetch_google_account_email' );
		$method->setAccessible( true );
		$email = $method->invoke( null, array( 'access_token' => 'only-access' ) );

		$this->assertSame( '', $email );
	}

	/**
	 * @covers Bookit_Google_Calendar_Api::handle_callback
	 */
	public function test_could_not_fetch_email_does_not_block_connection(): void {
		$this->seed_google_oauth_settings();

		$staff_id = $this->create_test_staff();
		$state    = $this->build_signed_oauth_state( $staff_id, time() + 600 );

		$result = Bookit_Google_Calendar_Api_TestDouble_No_Id_Token::handle_callback( 'fake-code', $state );
		$this->assertEquals( $staff_id, $result );

		global $wpdb;
		$row = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT google_calendar_connected, google_calendar_email FROM {$wpdb->prefix}bookings_staff WHERE id = %d",
				$staff_id
			),
			ARRAY_A
		);

		$this->assertEquals( '1', (string) $row['google_calendar_connected'] );
		$this->assertTrue( null === $row['google_calendar_email'] || '' === $row['google_calendar_email'] );
	}

	/**
	 * @covers Bookit_Encryption::encrypt
	 * @covers Bookit_Encryption::decrypt
	 */
	public function test_encryption_round_trip(): void {
		$plain = 'secret-token-value-123';
		$enc   = Bookit_Encryption::encrypt( $plain );
		$this->assertNotSame( $plain, $enc );
		$this->assertSame( $plain, Bookit_Encryption::decrypt( $enc ) );
	}

	/**
	 * @covers Bookit_Dashboard_Bookings_API::get_my_profile
	 */
	public function test_get_profile_includes_google_calendar_fields(): void {
		global $wpdb;

		$staff_id = $this->create_test_staff();
		$wpdb->update(
			$wpdb->prefix . 'bookings_staff',
			array(
				'google_calendar_connected' => 1,
				'google_calendar_email'     => 'gcal@test.com',
			),
			array( 'id' => $staff_id ),
			array( '%d', '%s' ),
			array( '%d' )
		);

		$this->login_as( $staff_id, 'staff' );

		$request  = new WP_REST_Request( 'GET', '/' . $this->namespace . '/dashboard/profile' );
		$response = rest_get_server()->dispatch( $request );

		$this->assertEquals( 200, $response->get_status() );
		$profile = $response->get_data()['profile'];
		$this->assertArrayHasKey( 'google_calendar_connected', $profile );
		$this->assertArrayHasKey( 'google_calendar_email', $profile );
		$this->assertTrue( (bool) $profile['google_calendar_connected'] );
		$this->assertEquals( 'gcal@test.com', $profile['google_calendar_email'] );
	}

	/**
	 * Insert Google OAuth client settings.
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
	 * @param int    $staff_id Staff ID.
	 * @param string $role     Role.
	 */
	private function login_as( $staff_id, $role = 'staff' ): void {
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
	 * @param array $args Args.
	 * @return int
	 */
	private function create_test_staff( $args = array() ) {
		global $wpdb;

		$defaults = array(
			'email'                    => 'staff-' . wp_generate_password( 6, false ) . '@test.com',
			'password_hash'            => password_hash( 'password123', PASSWORD_BCRYPT ),
			'first_name'               => 'Test',
			'last_name'                => 'Staff',
			'phone'                    => '07700900000',
			'photo_url'                => null,
			'bio'                      => 'Test bio',
			'title'                    => 'Therapist',
			'role'                     => 'staff',
			'google_calendar_id'       => null,
			'is_active'                => 1,
			'display_order'            => 0,
			'notification_preferences' => null,
			'created_at'               => current_time( 'mysql' ),
			'updated_at'               => current_time( 'mysql' ),
			'deleted_at'               => null,
		);

		$data = wp_parse_args( $args, $defaults );

		$wpdb->insert(
			$wpdb->prefix . 'bookings_staff',
			$data,
			array( '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%d', '%d', '%s', '%s', '%s', '%s' )
		);
		return (int) $wpdb->insert_id;
	}
}
