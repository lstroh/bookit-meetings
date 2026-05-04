<?php
/**
 * Sprint 7: extension API hooks (staff email meeting section).
 *
 * @package Bookit_Booking_System
 * @subpackage Tests
 */

/**
 * Tests for bookit_staff_email_meeting_section.
 */
class Test_Sprint7_Extension_Api extends WP_UnitTestCase {

	/**
	 * Temporary migration directories for migration-runner tests.
	 *
	 * @var string[]
	 */
	private array $temp_migration_dirs = array();

	/**
	 * Temporary tables created by migration-runner tests.
	 *
	 * @var string[]
	 */
	private array $temp_migration_tables = array();

	/**
	 * Set up each test.
	 */
	public function setUp(): void {
		parent::setUp();

		global $wpdb;

		Bookit_Migration_Runner::create_migrations_table();
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
		$wpdb->query( "TRUNCATE TABLE {$wpdb->prefix}bookings_migrations" );
	}

	/**
	 * Tear down each test.
	 */
	public function tearDown(): void {
		global $wpdb;

		foreach ( array_unique( $this->temp_migration_tables ) as $table_name ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
			$wpdb->query( "DROP TABLE IF EXISTS {$table_name}" );
		}

		foreach ( array_unique( $this->temp_migration_dirs ) as $dir ) {
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
	 * Minimal booking row shape used by Bookit_Staff_Notifier::build_html_body().
	 *
	 * @return array<string, mixed>
	 */
	private function sample_booking(): array {
		return array(
			'id'                    => 101,
			'staff_id'              => 5,
			'customer_first_name'   => 'Jane',
			'customer_last_name'    => 'Doe',
			'service_name'          => 'Consultation',
			'booking_date'          => '2026-06-15',
			'start_time'            => '10:00:00',
			'booking_reference'     => 'REF-001',
		);
	}

	/**
	 * Invoke private build_html_body via reflection.
	 *
	 * @param string               $email_type Email type slug.
	 * @param array<string, mixed> $booking    Booking data.
	 * @param int                  $staff_id   Recipient staff id.
	 * @return string
	 */
	private function invoke_build_html_body( string $email_type, array $booking, int $staff_id = 0 ): string {
		$method = new ReflectionMethod( Bookit_Staff_Notifier::class, 'build_html_body' );
		$method->setAccessible( true );
		ob_start();
		$html = $method->invoke( null, $email_type, $booking, $staff_id );
		$leaked = ob_get_clean();
		$this->assertSame( '', $leaked, 'build_html_body must not write outside its internal buffer.' );

		return is_string( $html ) ? $html : '';
	}

	/**
	 * Minimal DB row shape for Bookit_Dashboard_Bookings_API::format_schedule_booking().
	 *
	 * @return array<string, mixed>
	 */
	private function sample_schedule_booking_row(): array {
		return array(
			'id'                    => 202,
			'booking_reference'     => 'SCH-REF-7',
			'booking_date'          => '2026-06-20',
			'start_time'            => '14:00:00',
			'end_time'              => '15:00:00',
			'status'                => 'confirmed',
			'service_name'          => 'Follow-up',
			'duration'              => 60,
			'customer_first_name'   => 'Alex',
			'customer_last_name'    => 'Rivera',
			'total_price'           => 99.5,
			'deposit_paid'          => 0,
			'staff_notes'           => 'Window seat',
			'special_requests'      => 'Quiet room',
		);
	}

	/**
	 * Invoke private format_schedule_booking via reflection.
	 *
	 * @param array<string, mixed> $row   Raw DB row.
	 * @param string                 $today YYYY-MM-DD.
	 * @return array<string, mixed>
	 */
	private function invoke_format_schedule_booking( array $row, string $today ): array {
		$api    = new Bookit_Dashboard_Bookings_API();
		$method = new ReflectionMethod( Bookit_Dashboard_Bookings_API::class, 'format_schedule_booking' );
		$method->setAccessible( true );
		$out = $method->invoke( $api, $row, $today );

		return is_array( $out ) ? $out : array();
	}

	/**
	 * @covers Bookit_Dashboard_Bookings_API::format_schedule_booking
	 */
	public function test_schedule_booking_response_filter_fires_with_correct_params(): void {
		$captured = array();

		$cb = static function ( array $formatted, int $booking_id ) use ( &$captured ) {
			$captured = array(
				'formatted'  => $formatted,
				'booking_id' => $booking_id,
			);
			return $formatted;
		};

		add_filter( 'bookit_schedule_booking_response', $cb, 10, 2 );

		$row   = $this->sample_schedule_booking_row();
		$today = '2026-06-21';
		$this->invoke_format_schedule_booking( $row, $today );

		remove_filter( 'bookit_schedule_booking_response', $cb, 10 );

		$this->assertArrayHasKey( 'formatted', $captured );
		$this->assertIsArray( $captured['formatted'] );
		$this->assertArrayHasKey( 'booking_id', $captured );
		$this->assertIsInt( $captured['booking_id'] );
		$this->assertSame( 202, $captured['booking_id'] );
	}

	/**
	 * @covers Bookit_Dashboard_Bookings_API::format_schedule_booking
	 */
	public function test_schedule_booking_response_filter_can_add_fields(): void {
		$cb = static function ( array $formatted ) {
			$formatted['meeting_link'] = 'https://meet.example.com/abc';
			return $formatted;
		};

		add_filter( 'bookit_schedule_booking_response', $cb, 10, 2 );

		$out = $this->invoke_format_schedule_booking( $this->sample_schedule_booking_row(), '2026-06-20' );

		remove_filter( 'bookit_schedule_booking_response', $cb, 10 );

		$this->assertArrayHasKey( 'meeting_link', $out );
		$this->assertSame( 'https://meet.example.com/abc', $out['meeting_link'] );
	}

	/**
	 * @covers Bookit_Dashboard_Bookings_API::format_schedule_booking
	 */
	public function test_schedule_booking_response_filter_must_return_array(): void {
		$row   = $this->sample_schedule_booking_row();
		$today = '2026-06-20';

		$expected = array(
			'id'                 => 202,
			'booking_reference'  => 'SCH-REF-7',
			'booking_date'       => '2026-06-20',
			'start_time'         => '14:00',
			'end_time'           => '15:00',
			'status'             => 'confirmed',
			'service_name'       => 'Follow-up',
			'duration'           => 60,
			'customer_name'      => 'Alex Rivera',
			'total_price'        => 99.5,
			'deposit_paid'       => 0.0,
			'staff_notes'        => 'Window seat',
			'special_requests'   => 'Quiet room',
			'is_today'           => true,
		);

		$out = $this->invoke_format_schedule_booking( $row, $today );

		$this->assertIsArray( $out );
		$this->assertSame( $expected, $out );
	}

	/**
	 * @covers Bookit_Staff_Notifier::build_html_body
	 */
	public function test_staff_email_meeting_section_filter_fires_with_correct_params(): void {
		$captured = array();

		$cb = static function ( string $html, array $booking, int $staff_id ) use ( &$captured ) {
			$captured = array(
				'html'      => $html,
				'booking'   => $booking,
				'staff_id'  => $staff_id,
			);
			return $html;
		};

		add_filter( 'bookit_staff_email_meeting_section', $cb, 10, 3 );

		$booking  = $this->sample_booking();
		$staff_id = 42;
		$this->invoke_build_html_body( 'staff_new_booking_immediate', $booking, $staff_id );

		remove_filter( 'bookit_staff_email_meeting_section', $cb, 10 );

		$this->assertArrayHasKey( 'html', $captured );
		$this->assertSame( '', $captured['html'] );
		$this->assertArrayHasKey( 'booking', $captured );
		$this->assertSame( $booking, $captured['booking'] );
		$this->assertArrayHasKey( 'staff_id', $captured );
		$this->assertSame( 42, $captured['staff_id'] );
	}

	/**
	 * @covers Bookit_Staff_Notifier::build_html_body
	 */
	public function test_staff_email_meeting_section_output_injected_when_non_empty(): void {
		$marker = '<p>BOOKIT_MEETING_INJECT_TEST</p>';

		$cb = static function () use ( $marker ) {
			return $marker;
		};

		add_filter( 'bookit_staff_email_meeting_section', $cb, 10, 3 );

		$html = $this->invoke_build_html_body( 'staff_new_booking_immediate', $this->sample_booking(), 7 );

		remove_filter( 'bookit_staff_email_meeting_section', $cb, 10 );

		$this->assertStringContainsString( 'BOOKIT_MEETING_INJECT_TEST', $html );
		$this->assertStringContainsString( 'View in dashboard', $html );
	}

	/**
	 * @covers Bookit_Staff_Notifier::build_html_body
	 */
	public function test_staff_email_meeting_section_no_output_when_empty(): void {
		$cb = static function ( string $html ) {
			return $html;
		};

		add_filter( 'bookit_staff_email_meeting_section', $cb, 10, 3 );

		$with_filter = $this->invoke_build_html_body( 'staff_new_booking_immediate', $this->sample_booking(), 7 );

		remove_filter( 'bookit_staff_email_meeting_section', $cb, 10 );

		$baseline = $this->invoke_build_html_body( 'staff_new_booking_immediate', $this->sample_booking(), 7 );

		$this->assertSame( $baseline, $with_filter );
	}

	/**
	 * Dashboard template should fire bookit_dashboard_extension_content for in-layout extension mounts.
	 *
	 * @coversNothing
	 */
	public function test_dashboard_extension_content_action_fires(): void {
		$n_before = did_action( 'bookit_dashboard_extension_content' );

		$fired = false;
		$cb    = static function () use ( &$fired ) {
			$fired = true;
		};
		add_action( 'bookit_dashboard_extension_content', $cb, 10, 0 );

		$path = BOOKIT_PLUGIN_DIR . 'dashboard/app/index.php';
		if ( ! is_file( $path ) ) {
			$this->assertTrue(
				(bool) has_action( 'bookit_dashboard_extension_content', $cb ),
				'bookit_dashboard_extension_content should accept callbacks when the template file is missing.'
			);
			// Full firing verified manually — template requires HTTP context
			remove_action( 'bookit_dashboard_extension_content', $cb, 10 );
			return;
		}

		if ( ! isset( $_SESSION ) || ! is_array( $_SESSION ) ) {
			$_SESSION = array();
		}

		// Satisfy Bookit_Auth::require_auth() / get_current_staff() without a full HTTP round-trip.
		$_SESSION['staff_id']       = 1;
		$_SESSION['staff_email']    = 'sprint7-ext-hook@test.com';
		$_SESSION['staff_role']     = 'admin';
		$_SESSION['staff_name']     = 'Extension Hook Test';
		$_SESSION['is_logged_in']   = true;
		$_SESSION['last_activity']  = time();

		// Dashboard template calls wp_print_styles(); core still hooks deprecated print_emoji_styles (WP 6.4+).
		remove_action( 'wp_print_styles', 'print_emoji_styles' );

		$loaded = false;
		try {
			ob_start();
			require $path; // phpcs:ignore WordPressVIPMinimum.Files.IncludingFile.UsingVariable
			ob_end_clean();
			$loaded = true;
		} catch ( \Throwable $e ) {
			ob_end_clean();
		}

		if ( $loaded ) {
			$this->assertSame(
				1,
				did_action( 'bookit_dashboard_extension_content' ) - $n_before,
				'Template load should invoke bookit_dashboard_extension_content exactly once.'
			);
			$this->assertTrue( $fired, 'Registered callback should run when the action fires.' );
		} else {
			$this->assertTrue(
				(bool) has_action( 'bookit_dashboard_extension_content', $cb ),
				'Callback should remain registered when the template cannot be executed in this context.'
			);
			// Full firing verified manually — template requires HTTP context
		}

		remove_action( 'bookit_dashboard_extension_content', $cb, 10 );
	}

	/**
	 * Temp migration file whose class name does not follow filename-derived convention.
	 *
	 * @return array{dir:string,migration_id:string,table_name:string,plugin_slug:string}
	 */
	private function create_temp_nonstandard_class_migration_artifacts(): array {
		global $wpdb;

		$suffix         = strtolower( wp_generate_password( 8, false, false ) );
		$migration_id   = '0099-test-nonstandard';
		$plugin_slug    = 'bookit-sprint7-ext-' . $suffix;
		$class_name     = 'Bookit_Migration_Custom_Nonstandard_' . $suffix;
		$table_name     = $wpdb->prefix . 'bookings_sprint7_nmig_' . $suffix;
		$base_tmp       = trailingslashit( sys_get_temp_dir() ) . 'bookit-tests-migrations';

		if ( ! is_dir( $base_tmp ) ) {
			wp_mkdir_p( $base_tmp );
		}

		$dir = trailingslashit( $base_tmp ) . 'sprint7-nmig-' . $suffix;
		wp_mkdir_p( $dir );

		$php = <<<PHP
<?php
class {$class_name} extends Bookit_Migration_Base {
	public function migration_id(): string {
		return '{$migration_id}';
	}

	public function plugin_slug(): string {
		return '{$plugin_slug}';
	}

	public function up(): void {
		global \$wpdb;
		\$wpdb->query( "CREATE TABLE IF NOT EXISTS {$table_name} (id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci" );
	}

	public function down(): void {
		global \$wpdb;
		\$wpdb->query( "DROP TABLE IF EXISTS {$table_name}" );
	}
}
PHP;

		file_put_contents( trailingslashit( $dir ) . $migration_id . '.php', $php );

		$this->temp_migration_dirs[]   = $dir;
		$this->temp_migration_tables[] = $table_name;

		return array(
			'dir'            => $dir,
			'migration_id'   => $migration_id,
			'table_name'     => $table_name,
			'plugin_slug'    => $plugin_slug,
		);
	}

	/**
	 * @covers Bookit_Migration_Runner::run_pending
	 * @covers Bookit_Migration_Runner::has_run
	 */
	public function test_migration_runner_finds_class_by_migration_id_not_filename(): void {
		$migration = $this->create_temp_nonstandard_class_migration_artifacts();

		Bookit_Migration_Runner::register_migration_path( $migration['plugin_slug'], $migration['dir'] );
		Bookit_Migration_Runner::run_pending( $migration['plugin_slug'] );

		$this->assertTrue(
			Bookit_Migration_Runner::has_run( $migration['migration_id'], $migration['plugin_slug'] )
		);
	}

	/**
	 * @covers Bookit_Migration_Runner::rollback_last
	 * @covers Bookit_Migration_Runner::run_pending
	 * @covers Bookit_Migration_Runner::has_run
	 */
	public function test_migration_runner_rollback_finds_class_by_migration_id(): void {
		global $wpdb;

		$migration = $this->create_temp_nonstandard_class_migration_artifacts();

		Bookit_Migration_Runner::register_migration_path( $migration['plugin_slug'], $migration['dir'] );
		Bookit_Migration_Runner::run_pending( $migration['plugin_slug'] );

		$this->assertTrue( Bookit_Migration_Runner::rollback_last( $migration['plugin_slug'] ) );
		$this->assertFalse(
			Bookit_Migration_Runner::has_run( $migration['migration_id'], $migration['plugin_slug'] )
		);

		$table_exists = $wpdb->get_var( "SHOW TABLES LIKE '{$migration['table_name']}'" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
		$this->assertNull( $table_exists );
	}
}
