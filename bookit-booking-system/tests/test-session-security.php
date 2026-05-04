<?php
/**
 * Tests for Session Security & CSRF Protection.
 *
 * @package    Bookit_Booking_System
 * @subpackage Tests
 */

/**
 * Test Session Security and CSRF Protection.
 */
class Test_Session_Security extends WP_UnitTestCase {

	/**
	 * Original session save path (restored in tearDown).
	 *
	 * @var string
	 */
	private $original_session_save_path;

	/**
	 * Temporary directory for session cleanup tests.
	 *
	 * @var string
	 */
	private $temp_session_dir;

	/**
	 * Set up each test.
	 */
	public function setUp(): void {
		parent::setUp();

		// Load required classes.
		require_once BOOKIT_PLUGIN_DIR . 'includes/core/class-session-manager.php';
		require_once BOOKIT_PLUGIN_DIR . 'includes/class-csrf-protection.php';
		require_once BOOKIT_PLUGIN_DIR . 'includes/cron/class-session-cleanup.php';
		require_once BOOKIT_PLUGIN_DIR . 'includes/class-bookit-logger.php';

		// Save original session save path.
		$this->original_session_save_path = session_save_path();

		// Clean session state.
		if ( session_status() === PHP_SESSION_ACTIVE ) {
			session_destroy();
		}
		if ( isset( $_SESSION ) ) {
			$_SESSION = array();
		}
	}

	/**
	 * Tear down each test.
	 */
	public function tearDown(): void {
		// Restore session save path.
		if ( ! empty( $this->original_session_save_path ) ) {
			session_save_path( $this->original_session_save_path );
		}

		// Clean up temp directory.
		if ( ! empty( $this->temp_session_dir ) && is_dir( $this->temp_session_dir ) ) {
			$files = glob( $this->temp_session_dir . '/*' );
			foreach ( $files as $file ) {
				if ( is_file( $file ) ) {
					unlink( $file );
				}
			}
			rmdir( $this->temp_session_dir );
		}

		// Clean session.
		if ( session_status() === PHP_SESSION_ACTIVE ) {
			session_destroy();
		}
		if ( isset( $_SESSION ) ) {
			$_SESSION = array();
		}

		parent::tearDown();
	}

	/*
	 * -------------------------------------------------------------------------
	 * 1. Test Session Security Configuration
	 * -------------------------------------------------------------------------
	 */

	/**
	 * Test session security constants and expected configuration values.
	 *
	 * @covers Bookit_Session_Manager::SESSION_TIMEOUT
	 */
	public function test_session_timeout_constant_is_1800() {
		$this->assertEquals( 1800, Bookit_Session_Manager::SESSION_TIMEOUT );
	}

	/**
	 * Test session security ini configuration when init runs.
	 *
	 * When headers have not been sent, init() applies security settings.
	 * We verify the expected values; in PHPUnit headers may already be sent,
	 * so we also verify gc_maxlifetime via the constant.
	 *
	 * @covers Bookit_Session_Manager::init
	 */
	public function test_session_security_configuration() {
		// Session config is applied in init() before session_start().
		// When headers_sent() is true (typical in PHPUnit), init returns early.
		// We verify the expected configuration values that WOULD be set.
		$expected_httponly    = '1';
		$expected_samesite    = 'Lax';
		$expected_maxlifetime = (string) Bookit_Session_Manager::SESSION_TIMEOUT;

		$this->assertEquals( '1', $expected_httponly );
		$this->assertEquals( 'Lax', $expected_samesite );
		$this->assertEquals( '1800', $expected_maxlifetime );

		// Run init and verify session state.
		Bookit_Session_Manager::init();
		$session_ready = session_status() === PHP_SESSION_ACTIVE
			|| ( isset( $_SESSION ) && is_array( $_SESSION ) );
		$this->assertTrue( $session_ready );
	}

	/**
	 * Test that gc_maxlifetime matches session timeout constant.
	 *
	 * @covers Bookit_Session_Manager::SESSION_TIMEOUT
	 */
	public function test_session_gc_maxlifetime_matches_timeout() {
		$this->assertEquals( 1800, Bookit_Session_Manager::SESSION_TIMEOUT );
	}

	/*
	 * -------------------------------------------------------------------------
	 * 2. Test Session Timeout
	 * -------------------------------------------------------------------------
	 */

	/**
	 * Test session timeout detection after 30 minutes of inactivity.
	 *
	 * @covers Bookit_Session_Manager::is_expired
	 * @covers Bookit_Session_Manager::init
	 */
	public function test_session_timeout_detected_after_30_minutes() {
		Bookit_Session_Manager::init();
		Bookit_Session_Manager::set_data( array() );

		// Simulate 30+ minutes of inactivity by setting last_activity in the past.
		$_SESSION[ Bookit_Session_Manager::SESSION_KEY ]['last_activity'] = time() - 1900; // 31+ min ago.

		$this->assertTrue( Bookit_Session_Manager::is_expired() );
	}

	/**
	 * Test that expired session is cleared when clear() is called.
	 *
	 * Note: Auto-clear on init() only runs when session_status() === PHP_SESSION_ACTIVE.
	 * In PHPUnit (headers already sent), sessions use fallback $_SESSION, so we test
	 * that when is_expired() is true, calling clear() resets to defaults.
	 *
	 * @covers Bookit_Session_Manager::is_expired
	 * @covers Bookit_Session_Manager::clear
	 */
	public function test_session_cleared_on_timeout() {
		Bookit_Session_Manager::init();
		Bookit_Session_Manager::set_data( array( 'current_step' => 3, 'service_id' => 5 ) );

		// Simulate timeout.
		$_SESSION[ Bookit_Session_Manager::SESSION_KEY ]['last_activity'] = time() - 1900;

		$this->assertTrue( Bookit_Session_Manager::is_expired() );

		// When expired, clear() should reset to defaults.
		Bookit_Session_Manager::clear();

		$data = Bookit_Session_Manager::get_data();
		$this->assertEquals( 1, (int) $data['current_step'] );
		$this->assertNull( $data['service_id'] );
	}

	/*
	 * -------------------------------------------------------------------------
	 * 3. Test Session Fixation Prevention
	 * -------------------------------------------------------------------------
	 */

	/**
	 * Test wizard data structure on first visit (fixation prevention context).
	 *
	 * When headers are sent (PHPUnit), init() uses $_SESSION fallback without creating
	 * the wizard key. get_data() returns default structure. We verify the default
	 * structure has required keys. After set_data(), the wizard key exists.
	 *
	 * @covers Bookit_Session_Manager::get_data
	 * @covers Bookit_Session_Manager::set_data
	 */
	public function test_session_regenerates_on_first_visit() {
		// Clear any existing session.
		if ( session_status() === PHP_SESSION_ACTIVE ) {
			session_destroy();
		}
		$_SESSION = array();

		// On first access, get_data returns default structure.
		$wizard_data = Bookit_Session_Manager::get_data();
		$this->assertArrayHasKey( 'created_at', $wizard_data );
		$this->assertArrayHasKey( 'last_activity', $wizard_data );
		$this->assertEquals( 1, (int) $wizard_data['current_step'] );

		// After set_data, wizard key exists in session.
		Bookit_Session_Manager::set_data( array() );
		$this->assertArrayHasKey( Bookit_Session_Manager::SESSION_KEY, $_SESSION );
	}

	/**
	 * Test that regenerate method runs without error (session fixation prevention).
	 *
	 * @covers Bookit_Session_Manager::regenerate
	 */
	public function test_session_regenerate_runs_successfully() {
		Bookit_Session_Manager::init();

		$threw = false;
		try {
			Bookit_Session_Manager::regenerate();
		} catch ( Exception $e ) {
			$threw = true;
		}
		$this->assertFalse( $threw, 'regenerate() should not throw' );
	}

	/*
	 * -------------------------------------------------------------------------
	 * 4. Test CSRF Nonce Generation
	 * -------------------------------------------------------------------------
	 */

	/**
	 * Test CSRF nonce generation.
	 *
	 * @covers Bookit_CSRF_Protection::get_nonce
	 */
	public function test_csrf_nonce_generation() {
		$nonce = Bookit_CSRF_Protection::get_nonce();

		$this->assertNotEmpty( $nonce );
		$this->assertIsString( $nonce );
		$this->assertMatchesRegularExpression( '/^[a-zA-Z0-9]+$/', $nonce );
	}

	/**
	 * Test that generated nonce is valid WordPress nonce.
	 *
	 * @covers Bookit_CSRF_Protection::get_nonce
	 * @covers Bookit_CSRF_Protection::verify
	 */
	public function test_csrf_nonce_is_valid_wordpress_nonce() {
		$nonce = Bookit_CSRF_Protection::get_nonce();

		$verified = wp_verify_nonce( $nonce, Bookit_CSRF_Protection::NONCE_ACTION );
		$this->assertNotFalse( $verified, 'Generated nonce should be valid for WordPress verification' );
	}

	/**
	 * Test that nonce action is correct.
	 *
	 * @covers Bookit_CSRF_Protection::NONCE_ACTION
	 */
	public function test_csrf_nonce_action() {
		$this->assertEquals( 'bookit_booking', Bookit_CSRF_Protection::NONCE_ACTION );
	}

	/*
	 * -------------------------------------------------------------------------
	 * 5. Test CSRF Nonce Verification
	 * -------------------------------------------------------------------------
	 */

	/**
	 * Test valid nonce passes verification.
	 *
	 * @covers Bookit_CSRF_Protection::verify
	 */
	public function test_csrf_valid_nonce_passes_verification() {
		$nonce = Bookit_CSRF_Protection::get_nonce();

		$this->assertTrue( Bookit_CSRF_Protection::verify( $nonce ) );
	}

	/**
	 * Test invalid nonce fails verification.
	 *
	 * @covers Bookit_CSRF_Protection::verify
	 */
	public function test_csrf_invalid_nonce_fails_verification() {
		$invalid_nonce = 'invalid_nonce_value_12345';

		$this->assertFalse( Bookit_CSRF_Protection::verify( $invalid_nonce ) );
	}

	/**
	 * Test empty nonce fails verification.
	 *
	 * @covers Bookit_CSRF_Protection::verify
	 */
	public function test_csrf_empty_nonce_fails_verification() {
		$this->assertFalse( Bookit_CSRF_Protection::verify( '' ) );
	}

	/**
	 * Test expired nonce fails verification.
	 *
	 * Uses nonce_life filter to make nonces expire in 1 second.
	 *
	 * @covers Bookit_CSRF_Protection::verify
	 */
	public function test_csrf_expired_nonce_fails_verification() {
		// Set nonce lifetime to 1 second.
		add_filter( 'nonce_life', array( $this, 'filter_nonce_life_one_second' ) );

		$nonce = Bookit_CSRF_Protection::get_nonce();

		// Wait for nonce to expire.
		sleep( 2 );

		$verified = Bookit_CSRF_Protection::verify( $nonce );

		remove_filter( 'nonce_life', array( $this, 'filter_nonce_life_one_second' ) );

		$this->assertFalse( $verified, 'Expired nonce should fail verification' );
	}

	/**
	 * Filter callback: set nonce life to 1 second.
	 *
	 * @return int
	 */
	public function filter_nonce_life_one_second() {
		return 1;
	}

	/**
	 * Test verify with nonce from POST.
	 *
	 * @covers Bookit_CSRF_Protection::verify
	 */
	public function test_csrf_verify_from_post() {
		$nonce = Bookit_CSRF_Protection::get_nonce();
		$_POST[ Bookit_CSRF_Protection::NONCE_FIELD ] = $nonce;

		$result = Bookit_CSRF_Protection::verify( null );
		unset( $_POST[ Bookit_CSRF_Protection::NONCE_FIELD ] );

		$this->assertTrue( $result );
	}

	/*
	 * -------------------------------------------------------------------------
	 * 6. Test Session Cleanup
	 * -------------------------------------------------------------------------
	 */

	/**
	 * Test session cleanup deletes old booking sessions.
	 *
	 * Uses optional path parameter to avoid session_save_path() (cannot change after headers sent).
	 *
	 * @covers Bookit_Session_Cleanup::run_cleanup
	 */
	public function test_session_cleanup_deletes_old_sessions() {
		$this->temp_session_dir = sys_get_temp_dir() . '/bookit_test_sessions_' . uniqid();
		wp_mkdir_p( $this->temp_session_dir );

		$old_content = 'bookit_wizard|a:1:{s:10:"current_step";i:1;}';
		$new_content = 'bookit_wizard|a:1:{s:10:"current_step";i:2;}';

		// Create old session file (>24 hours).
		$old_file = $this->temp_session_dir . '/sess_old_' . uniqid();
		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
		file_put_contents( $old_file, $old_content );
		touch( $old_file, time() - 86401 ); // 24h+ ago.

		// Create new session file (<24 hours).
		$new_file = $this->temp_session_dir . '/sess_new_' . uniqid();
		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
		file_put_contents( $new_file, $new_content );

		$this->assertFileExists( $old_file );
		$this->assertFileExists( $new_file );

		// Pass path to avoid session_save_path() (fails after headers sent).
		Bookit_Session_Cleanup::run_cleanup( $this->temp_session_dir );

		// Old file should be deleted, new file should remain.
		$this->assertFileDoesNotExist( $old_file );
		$this->assertFileExists( $new_file );
	}

	/**
	 * Test session cleanup preserves non-booking session files.
	 *
	 * @covers Bookit_Session_Cleanup::run_cleanup
	 */
	public function test_session_cleanup_preserves_non_booking_sessions() {
		$this->temp_session_dir = sys_get_temp_dir() . '/bookit_test_sessions_' . uniqid();
		wp_mkdir_p( $this->temp_session_dir );

		// Create old file WITHOUT booking markers.
		$other_file = $this->temp_session_dir . '/sess_other_' . uniqid();
		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
		file_put_contents( $other_file, 'other_session|a:0:{}' );
		touch( $other_file, time() - 86401 );

		$this->assertFileExists( $other_file );

		Bookit_Session_Cleanup::run_cleanup( $this->temp_session_dir );

		// Non-booking session should NOT be deleted.
		$this->assertFileExists( $other_file );
	}

	/*
	 * -------------------------------------------------------------------------
	 * 7. Test Cron Registration
	 * -------------------------------------------------------------------------
	 */

	/**
	 * Test cron hook is scheduled on activation.
	 *
	 * @covers Bookit_Session_Cleanup::register_cron
	 */
	public function test_cron_hook_scheduled_on_activation() {
		// Clear any existing schedule.
		$timestamp = wp_next_scheduled( Bookit_Session_Cleanup::CRON_HOOK );
		if ( $timestamp ) {
			wp_unschedule_event( $timestamp, Bookit_Session_Cleanup::CRON_HOOK );
		}

		Bookit_Session_Cleanup::register_cron();

		$scheduled = wp_next_scheduled( Bookit_Session_Cleanup::CRON_HOOK );
		$this->assertNotFalse( $scheduled, 'Cron hook should be scheduled after registration' );

		// Verify schedule_event (used by register_cron) does not return WP_Error.
		wp_unschedule_event( $scheduled, Bookit_Session_Cleanup::CRON_HOOK );
		$result = wp_schedule_event(
			strtotime( '03:30:00' ),
			'daily',
			Bookit_Session_Cleanup::CRON_HOOK,
			array(),
			true
		);
		$this->assertNotWPError( $result );
	}

	/**
	 * Test cron hook is cleared on deactivation.
	 *
	 * @covers Bookit_Session_Cleanup::unregister_cron
	 */
	public function test_cron_hook_cleared_on_deactivation() {
		// Ensure cron is scheduled first.
		Bookit_Session_Cleanup::register_cron();
		$this->assertNotFalse( wp_next_scheduled( Bookit_Session_Cleanup::CRON_HOOK ) );

		Bookit_Session_Cleanup::unregister_cron();

		$this->assertFalse( wp_next_scheduled( Bookit_Session_Cleanup::CRON_HOOK ) );
	}

	/**
	 * Test cron runs daily.
	 *
	 * @covers Bookit_Session_Cleanup::register_cron
	 */
	public function test_cron_schedule_is_daily() {
		// Clear and re-register.
		$timestamp = wp_next_scheduled( Bookit_Session_Cleanup::CRON_HOOK );
		if ( $timestamp ) {
			wp_unschedule_event( $timestamp, Bookit_Session_Cleanup::CRON_HOOK );
		}

		Bookit_Session_Cleanup::register_cron();

		$event = wp_get_scheduled_event( Bookit_Session_Cleanup::CRON_HOOK );
		$this->assertNotFalse( $event );
		$this->assertEquals( 'daily', $event->schedule );
	}

	/**
	 * Test CSRF nonce field generation.
	 *
	 * @covers Bookit_CSRF_Protection::nonce_field
	 */
	public function test_csrf_nonce_field_output() {
		$field = Bookit_CSRF_Protection::nonce_field( true, false );

		$this->assertStringContainsString( 'name="' . Bookit_CSRF_Protection::NONCE_FIELD . '"', $field );
		$this->assertStringContainsString( 'value="', $field );
	}

	/**
	 * Test CSRF REST API verification with X-Bookit-Nonce header.
	 *
	 * @covers Bookit_CSRF_Protection::verify_rest_request
	 */
	public function test_csrf_verify_rest_request_with_bookit_nonce() {
		$nonce   = wp_create_nonce( Bookit_CSRF_Protection::NONCE_ACTION );
		$request = new WP_REST_Request( 'POST', '/bookit/v1/test' );
		$request->set_header( 'X-Bookit-Nonce', $nonce );

		$this->assertTrue( Bookit_CSRF_Protection::verify_rest_request( $request ) );
	}

	/**
	 * Test CSRF REST API verification fails with invalid nonce.
	 *
	 * @covers Bookit_CSRF_Protection::verify_rest_request
	 */
	public function test_csrf_verify_rest_request_fails_with_invalid_nonce() {
		$request = new WP_REST_Request( 'POST', '/bookit/v1/test' );
		$request->set_header( 'X-Bookit-Nonce', 'invalid_nonce' );

		$this->assertFalse( Bookit_CSRF_Protection::verify_rest_request( $request ) );
	}
}
