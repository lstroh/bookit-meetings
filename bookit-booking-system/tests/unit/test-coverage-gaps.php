<?php
/**
 * Targeted coverage for high-risk paths not covered elsewhere.
 *
 * Note: Stripe missing_service / invalid_email and Google OAuth invalid_grant refresh
 * are covered in tests/test-stripe-checkout.php and tests/unit/test-google-calendar-sync.php.
 *
 * @package    Bookit_Booking_System
 * @subpackage Tests
 */

/**
 * Forces post-refresh client state with an empty access token (no HTTP).
 */
class Bookit_Google_Calendar_Refresh_EmptyToken_Double extends Bookit_Google_Calendar {

	/**
	 * @param \Google\Client $client              Client.
	 * @param string         $plain_refresh_token Refresh token.
	 * @return array
	 */
	protected static function oauth_refresh_with_client( \Google\Client $client, string $plain_refresh_token ): array {
		$client->setAccessToken(
			array(
				'access_token'  => '',
				'refresh_token' => $plain_refresh_token,
			)
		);
		return array( 'access_token' => '' );
	}
}

/**
 * @covers Bookit_Encryption
 */
class Test_Encryption_Edge_Cases extends WP_UnitTestCase {

	/**
	 * @covers Bookit_Encryption::decrypt
	 */
	public function test_decrypt_returns_empty_string_for_garbage_input(): void {
		$this->assertSame( '', Bookit_Encryption::decrypt( 'not-valid-base64!!!###' ) );
	}

	/**
	 * @covers Bookit_Encryption::decrypt
	 */
	public function test_decrypt_returns_empty_string_for_truncated_blob(): void {
		$short = base64_encode( 'short' );
		$this->assertLessThan( 17, strlen( base64_decode( $short, true ) ?: '' ) );
		$this->assertSame( '', Bookit_Encryption::decrypt( $short ) );
	}

	/**
	 * @covers Bookit_Encryption::decrypt
	 */
	public function test_decrypt_returns_empty_string_for_tampered_ciphertext(): void {
		$enc = Bookit_Encryption::encrypt( 'original' );
		$tampered = substr_replace( $enc, 'XXXX', -4 );
		$this->assertSame( '', Bookit_Encryption::decrypt( $tampered ) );
	}
}

/**
 * @covers Bookit_Migration_Runner::run_pending
 */
class Test_Migration_Runner_Error_Path extends WP_UnitTestCase {

	/**
	 * @var string[]
	 */
	private array $temp_dirs = array();

	/**
	 * @var string[]
	 */
	private array $temp_tables = array();

	/**
	 * Set up.
	 */
	public function setUp(): void {
		parent::setUp();

		global $wpdb;

		Bookit_Migration_Runner::create_migrations_table();
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
		$wpdb->query( "TRUNCATE TABLE {$wpdb->prefix}bookings_migrations" );
	}

	/**
	 * Tear down.
	 */
	public function tearDown(): void {
		global $wpdb;

		foreach ( array_unique( $this->temp_tables ) as $table_name ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
			$wpdb->query( "DROP TABLE IF EXISTS {$table_name}" );
		}

		foreach ( array_unique( $this->temp_dirs ) as $dir ) {
			$files = glob( $dir . DIRECTORY_SEPARATOR . '*.php' );
			if ( is_array( $files ) ) {
				foreach ( $files as $file ) {
					if ( is_string( $file ) && file_exists( $file ) ) {
						unlink( $file );
					}
				}
			}
			if ( is_dir( $dir ) ) {
				rmdir( $dir );
			}
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
		$wpdb->query( "TRUNCATE TABLE {$wpdb->prefix}bookings_migrations" );

		parent::tearDown();
	}

	/**
	 * @covers Bookit_Migration_Runner::run_pending
	 */
	public function test_run_pending_stops_on_migration_exception_and_does_not_mark_as_run(): void {
		global $wpdb;

		$suffix       = strtolower( wp_generate_password( 8, false, false ) );
		$migration_id = '0001-temp-fail-' . $suffix;
		$class_name   = 'Bookit_Migration_' . str_replace( '-', '_', ucwords( $migration_id, '-' ) );

		$base_tmp = trailingslashit( sys_get_temp_dir() ) . 'bookit-tests-migrations';
		if ( ! is_dir( $base_tmp ) ) {
			wp_mkdir_p( $base_tmp );
		}
		$dir = trailingslashit( $base_tmp ) . $suffix;
		wp_mkdir_p( $dir );

		$php = <<<PHP
<?php
class {$class_name} extends Bookit_Migration_Base {
	public function migration_id(): string {
		return '{$migration_id}';
	}

	public function plugin_slug(): string {
		return 'bookit-test';
	}

	public function up(): void {
		throw new RuntimeException( 'deliberate failure' );
	}

	public function down(): void {
	}
}
PHP;

		file_put_contents( trailingslashit( $dir ) . $migration_id . '.php', $php );
		$this->temp_dirs[] = $dir;

		Bookit_Migration_Runner::register_migration_path( 'bookit-test', $dir );

		$ran = Bookit_Migration_Runner::run_pending( 'bookit-test' );

		$this->assertSame( array(), $ran );
		$this->assertFalse( Bookit_Migration_Runner::has_run( $migration_id, 'bookit-test' ) );

		$count = (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$wpdb->prefix}bookings_migrations WHERE migration_id = %s AND plugin_slug = %s",
				$migration_id,
				'bookit-test'
			)
		);
		$this->assertSame( 0, $count );
	}

	/**
	 * @covers Bookit_Migration_Runner::run_pending
	 */
	public function test_run_pending_stops_processing_further_migrations_after_exception(): void {
		global $wpdb;

		$suffix              = strtolower( wp_generate_password( 8, false, false ) );
		$first_migration_id  = '0001-temp-throw-' . $suffix;
		$second_migration_id = '0002-temp-ok-' . $suffix;
		$second_table_name   = $wpdb->prefix . 'bookings_temp_migration_second_' . $suffix;
		$first_class_name    = 'Bookit_Migration_' . str_replace( '-', '_', ucwords( $first_migration_id, '-' ) );
		$second_class_name   = 'Bookit_Migration_' . str_replace( '-', '_', ucwords( $second_migration_id, '-' ) );

		$base_tmp = trailingslashit( sys_get_temp_dir() ) . 'bookit-tests-migrations';
		if ( ! is_dir( $base_tmp ) ) {
			wp_mkdir_p( $base_tmp );
		}
		$dir = trailingslashit( $base_tmp ) . $suffix;
		wp_mkdir_p( $dir );

		$first_php = <<<PHP
<?php
class {$first_class_name} extends Bookit_Migration_Base {
	public function migration_id(): string {
		return '{$first_migration_id}';
	}

	public function plugin_slug(): string {
		return 'bookit-test';
	}

	public function up(): void {
		throw new RuntimeException( 'deliberate failure' );
	}

	public function down(): void {
	}
}
PHP;

		$second_php = <<<PHP
<?php
class {$second_class_name} extends Bookit_Migration_Base {
	public function migration_id(): string {
		return '{$second_migration_id}';
	}

	public function plugin_slug(): string {
		return 'bookit-test';
	}

	public function up(): void {
		global \$wpdb;
		\$wpdb->query( "CREATE TABLE IF NOT EXISTS {$second_table_name} (id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci" );
	}

	public function down(): void {
		global \$wpdb;
		\$wpdb->query( "DROP TABLE IF EXISTS {$second_table_name}" );
	}
}
PHP;

		file_put_contents( trailingslashit( $dir ) . $first_migration_id . '.php', $first_php );
		file_put_contents( trailingslashit( $dir ) . $second_migration_id . '.php', $second_php );
		$this->temp_dirs[]   = $dir;
		$this->temp_tables[] = $second_table_name;

		Bookit_Migration_Runner::register_migration_path( 'bookit-test', $dir );

		$ran = Bookit_Migration_Runner::run_pending( 'bookit-test' );

		$this->assertSame( array(), $ran );
		$this->assertFalse( Bookit_Migration_Runner::has_run( $first_migration_id, 'bookit-test' ) );
		$this->assertFalse( Bookit_Migration_Runner::has_run( $second_migration_id, 'bookit-test' ) );

		$table_exists = $wpdb->get_var( "SHOW TABLES LIKE '{$second_table_name}'" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
		$this->assertNull( $table_exists );
	}
}

/**
 * Missing-field validation for Stripe checkout (service_id / invalid_email covered in Test_Stripe_Checkout).
 *
 * @covers Booking_System_Stripe_Checkout::create_checkout_session
 */
class Test_Stripe_Checkout_Validation extends WP_UnitTestCase {

	/**
	 * @var Booking_System_Stripe_Checkout
	 */
	private $stripe_checkout;

	/**
	 * @var int
	 */
	private int $test_service_id = 0;

	/**
	 * @var int
	 */
	private int $test_staff_id = 0;

	/**
	 * @var int
	 */
	private int $mock_filter_priority = 999;

	/**
	 * @var callable|null
	 */
	private $mock_mode_callback;

	/**
	 * @var callable|null
	 */
	private $mock_session_callback;

	/**
	 * Set up.
	 */
	public function setUp(): void {
		parent::setUp();

		bookit_test_truncate_tables(
			array(
				'bookings_staff',
				'bookings_services',
			)
		);

		$autoload = dirname( __DIR__, 2 ) . '/vendor/autoload.php';
		if ( file_exists( $autoload ) ) {
			require_once $autoload;
		}

		$checkout_class = dirname( __DIR__, 2 ) . '/includes/payment/class-stripe-checkout.php';
		require_once $checkout_class;

		$this->stripe_checkout = new Booking_System_Stripe_Checkout();
		$this->insert_service_and_staff();
		$this->seed_stripe_settings();

		$this->mock_mode_callback = static function () {
			return 'mock';
		};
		$this->mock_session_callback = static function ( $session_data ) {
			return (object) array(
				'id'           => 'cs_test_should_not_run',
				'amount_total' => 5000,
				'currency'     => 'gbp',
				'url'          => 'https://example.test/checkout',
			);
		};
		add_filter( 'bookit_stripe_api_mode', $this->mock_mode_callback, $this->mock_filter_priority );
		add_filter( 'bookit_mock_stripe_session', $this->mock_session_callback, $this->mock_filter_priority );
		add_filter( 'bookit_log_deposit_edge_cases', '__return_false' );
	}

	/**
	 * Tear down.
	 */
	public function tearDown(): void {
		global $wpdb;

		if ( isset( $this->mock_mode_callback ) ) {
			remove_filter( 'bookit_stripe_api_mode', $this->mock_mode_callback, $this->mock_filter_priority );
		}
		if ( isset( $this->mock_session_callback ) ) {
			remove_filter( 'bookit_mock_stripe_session', $this->mock_session_callback, $this->mock_filter_priority );
		}
		remove_filter( 'bookit_log_deposit_edge_cases', '__return_false' );

		$p = $wpdb->prefix;
		if ( $this->test_staff_id > 0 ) {
			$wpdb->delete( $p . 'bookings_staff', array( 'id' => $this->test_staff_id ), array( '%d' ) );
		}
		if ( $this->test_service_id > 0 ) {
			$wpdb->delete( $p . 'bookings_services', array( 'id' => $this->test_service_id ), array( '%d' ) );
		}

		foreach ( array( 'stripe_test_mode', 'stripe_secret_key', 'stripe_publishable_key' ) as $key ) {
			$wpdb->delete( $p . 'bookings_settings', array( 'setting_key' => $key ), array( '%s' ) );
		}

		parent::tearDown();
	}

	/**
	 * @return void
	 */
	private function insert_service_and_staff(): void {
		global $wpdb;

		$services_table = $wpdb->prefix . 'bookings_services';
		$staff_table    = $wpdb->prefix . 'bookings_staff';

		$wpdb->insert(
			$services_table,
			array(
				'name'           => 'Coverage Gap Service',
				'description'    => null,
				'duration'       => 60,
				'price'          => 50.00,
				'deposit_type'   => 'percentage',
				'deposit_amount' => 100,
				'buffer_before'  => 0,
				'buffer_after'   => 0,
				'is_active'      => 1,
				'display_order'  => 0,
				'created_at'     => current_time( 'mysql' ),
				'updated_at'     => current_time( 'mysql' ),
				'deleted_at'     => null,
			),
			array( '%s', '%s', '%d', '%f', '%s', '%f', '%d', '%d', '%d', '%d', '%s', '%s', '%s' )
		);
		$this->test_service_id = (int) $wpdb->insert_id;

		$wpdb->insert(
			$staff_table,
			array(
				'first_name'         => 'Test',
				'last_name'          => 'Staff',
				'email'              => 'coverage-staff@test.com',
				'password_hash'      => password_hash( 'test', PASSWORD_BCRYPT ),
				'phone'              => null,
				'photo_url'          => null,
				'bio'                => null,
				'title'              => null,
				'role'               => 'staff',
				'google_calendar_id' => null,
				'is_active'          => 1,
				'display_order'      => 0,
				'created_at'         => current_time( 'mysql' ),
				'updated_at'         => current_time( 'mysql' ),
				'deleted_at'         => null,
			),
			array( '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%d', '%d', '%s', '%s', '%s' )
		);
		$this->test_staff_id = (int) $wpdb->insert_id;
	}

	/**
	 * @return void
	 */
	private function seed_stripe_settings(): void {
		global $wpdb;

		$table = $wpdb->prefix . 'bookings_settings';
		foreach (
			array(
				'stripe_test_mode'       => array( '1', 'boolean' ),
				'stripe_secret_key'      => array( 'sk_test_coverage_gap', 'string' ),
				'stripe_publishable_key' => array( 'pk_test_coverage_gap', 'string' ),
			) as $key => $pair
		) {
			list( $val, $type ) = $pair;
			$wpdb->insert(
				$table,
				array(
					'setting_key'   => $key,
					'setting_value' => $val,
					'setting_type'  => $type,
				),
				array( '%s', '%s', '%s' )
			);
		}
	}

	/**
	 * @covers Booking_System_Stripe_Checkout::create_checkout_session
	 */
	public function test_create_checkout_session_returns_error_for_missing_staff_id(): void {
		$session_data = array(
			'service_id'          => $this->test_service_id,
			'date'                => '2026-12-15',
			'time'                => '14:00:00',
			'customer_email'      => 'client@example.com',
			'customer_first_name' => 'Jane',
			'customer_last_name'  => 'Doe',
		);

		$result = $this->stripe_checkout->create_checkout_session( $session_data );

		$this->assertInstanceOf( WP_Error::class, $result );
		$this->assertSame( 'missing_staff', $result->get_error_code() );
	}

	/**
	 * @covers Booking_System_Stripe_Checkout::create_checkout_session
	 */
	public function test_create_checkout_session_returns_error_for_missing_required_field(): void {
		$session_data = array(
			'service_id'         => $this->test_service_id,
			'staff_id'           => $this->test_staff_id,
			'date'               => '2026-12-15',
			'time'               => '14:00:00',
			'customer_email'     => 'client@example.com',
			'customer_last_name' => 'Doe',
		);

		$result = $this->stripe_checkout->create_checkout_session( $session_data );

		$this->assertInstanceOf( WP_Error::class, $result );
		$this->assertSame( 'missing_field', $result->get_error_code() );
	}
}

/**
 * Token refresh edge case: missing access_token after refresh (invalid_grant path is in test-google-calendar-sync.php).
 *
 * @covers Bookit_Google_Calendar::get_client_for_staff
 */
class Test_Google_Calendar_Token_Refresh extends WP_UnitTestCase {

	/**
	 * Set up.
	 */
	public function setUp(): void {
		parent::setUp();

		bookit_test_truncate_tables(
			array(
				'bookings_audit_log',
				'bookings_staff',
				'bookings_settings',
			)
		);

		Bookit_Google_Calendar::set_test_client( null );
	}

	/**
	 * Tear down.
	 */
	public function tearDown(): void {
		Bookit_Google_Calendar::set_test_client( null );

		bookit_test_truncate_tables(
			array(
				'bookings_audit_log',
				'bookings_staff',
				'bookings_settings',
			)
		);

		parent::tearDown();
	}

	/**
	 * @covers Bookit_Google_Calendar::get_client_for_staff
	 */
	public function test_get_client_for_staff_returns_null_when_refresh_returns_no_access_token(): void {
		global $wpdb;

		$this->seed_google_oauth_settings();

		$staff_id = $this->create_test_staff();
		$this->set_staff_google_tokens(
			$staff_id,
			1,
			'fake-access',
			'fake-refresh',
			'2000-01-01 00:00:00'
		);

		$result = Bookit_Google_Calendar_Refresh_EmptyToken_Double::get_client_for_staff( $staff_id );
		$this->assertNull( $result );

		$logged = (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$wpdb->prefix}bookings_audit_log WHERE action = %s AND object_id = %d",
				'google_calendar.token_refresh_failed',
				$staff_id
			)
		);
		$this->assertSame( 1, $logged );
	}

	/**
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
	 * @param int    $staff_id      Staff ID.
	 * @param int    $connected     Connected flag.
	 * @param string $plain_access  Plain access token.
	 * @param string $plain_refresh Plain refresh token.
	 * @param string $expiry_mysql  Expiry.
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
}
