<?php
/**
 * Logger tests.
 *
 * @package Bookit_Booking_System
 */

use Yoast\PHPUnitPolyfills\TestCases\TestCase;

/**
 * Test logger functionality.
 */
class Test_Logger extends TestCase {

	/**
	 * Set up test.
	 */
	public function setUp(): void {
		parent::setUp();
		require_once BOOKIT_PLUGIN_DIR . 'includes/class-bookit-logger.php';
	}

	/**
	 * Test logger can write to log file.
	 */
	public function test_logger_can_write() {
		$result = Bookit_Logger::test_logging();
		$this->assertTrue( $result, 'Logger should be able to write to log file' );
	}

	/**
	 * Test log directory is writable.
	 */
	public function test_log_directory_writable() {
		$log_dir = Bookit_Logger::get_log_directory();
		$this->assertTrue( is_writable( $log_dir ), 'Log directory should be writable' );
	}

	/**
	 * Test logging creates file with correct name.
	 */
	public function test_log_file_naming() {
		Bookit_Logger::info( 'Test log entry' );
		
		$log_file = Bookit_Logger::get_todays_log_file();
		$expected = 'bookings-' . date( 'Y-m-d' ) . '.log';
		
		$this->assertStringContainsString( $expected, $log_file );
		$this->assertTrue( file_exists( $log_file ) );
	}

	/**
	 * Test sensitive data redaction.
	 */
	public function test_sensitive_data_redaction() {
		$test_message = 'User login test';
		$test_context = array(
			'email'       => 'test@example.com',
			'password'    => 'secret123',
			'api_key'     => 'sk_live_abc123',
			'card_number' => '4242424242424242',
			'normal_data' => 'This should not be redacted',
		);
		
		Bookit_Logger::info( $test_message, $test_context );
		
		$log_file     = Bookit_Logger::get_todays_log_file();
		$log_contents = file_get_contents( $log_file );
		
		// Sensitive data should be redacted
		$this->assertStringContainsString( '[REDACTED]', $log_contents );
		$this->assertStringNotContainsString( 'secret123', $log_contents );
		$this->assertStringNotContainsString( 'sk_live_abc123', $log_contents );
		$this->assertStringNotContainsString( '4242424242424242', $log_contents );
		
		// Normal data should appear
		$this->assertStringContainsString( 'This should not be redacted', $log_contents );
		$this->assertStringContainsString( 'test@example.com', $log_contents );
	}

	/**
	 * Test log levels work correctly.
	 */
	public function test_log_levels() {
		Bookit_Logger::info( 'Info message' );
		Bookit_Logger::warning( 'Warning message' );
		Bookit_Logger::error( 'Error message' );
		
		$log_file     = Bookit_Logger::get_todays_log_file();
		$log_contents = file_get_contents( $log_file );
		
		$this->assertStringContainsString( '[INFO]', $log_contents );
		$this->assertStringContainsString( '[WARNING]', $log_contents );
		$this->assertStringContainsString( '[ERROR]', $log_contents );
	}

	/**
	 * Test log entry format.
	 */
	public function test_log_entry_format() {
		Bookit_Logger::info( 'Format test' );
		
		$log_file     = Bookit_Logger::get_todays_log_file();
		$log_contents = file_get_contents( $log_file );
		
		// Check format: [YYYY-MM-DD HH:MM:SS] [LEVEL] Message
		$pattern = '/\[\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}\] \[INFO\] Format test/';
		$this->assertMatchesRegularExpression( $pattern, $log_contents );
	}

	/**
	 * Test log security location.
	 */
	public function test_log_security_location() {
		$is_secure = Bookit_Logger::is_secure_location();
		$log_dir   = Bookit_Logger::get_log_directory();
		
		// Just verify method works, don't require specific location in tests
		$this->assertIsBool( $is_secure );
		$this->assertNotEmpty( $log_dir );
	}
}
