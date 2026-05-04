<?php
/**
 * Plugin activation tests.
 *
 * @package Bookit_Booking_System
 */

use Yoast\PHPUnitPolyfills\TestCases\TestCase;

/**
 * Test plugin activation.
 */
class Test_Plugin_Activation extends TestCase {

	/**
	 * Test plugin constants are defined.
	 */
	public function test_plugin_constants_defined() {
		$this->assertTrue( defined( 'BOOKIT_VERSION' ) );
		$this->assertTrue( defined( 'BOOKIT_PLUGIN_DIR' ) );
		$this->assertTrue( defined( 'BOOKIT_PLUGIN_URL' ) );
	}

	/**
	 * Test plugin version matches expected format.
	 */
	public function test_plugin_version_format() {
		$version = BOOKIT_VERSION;
		$this->assertMatchesRegularExpression( '/^\d+\.\d+\.\d+$/', $version );
	}

	/**
	 * Test default settings are created.
	 */
	public function test_default_settings_created() {
		$settings = get_option( 'bookit_settings' );
		
		$this->assertIsArray( $settings );
		$this->assertArrayHasKey( 'timezone', $settings );
		$this->assertArrayHasKey( 'currency', $settings );
		$this->assertEquals( 'Europe/London', $settings['timezone'] );
		$this->assertEquals( 'GBP', $settings['currency'] );
	}

	/**
	 * Test database version option is set.
	 */
	public function test_database_version_option() {
		$db_version = get_option( 'bookit_db_version' );
		$this->assertNotEmpty( $db_version );
		$this->assertEquals( '1.0.3', $db_version );
	}

	/**
	 * Test log directory is created.
	 */
	public function test_log_directory_created() {
		require_once BOOKIT_PLUGIN_DIR . 'includes/class-bookit-logger.php';
		
		$log_dir = Bookit_Logger::get_log_directory();
		
		$this->assertTrue( file_exists( $log_dir ) );
		$this->assertTrue( is_dir( $log_dir ) );
		$this->assertTrue( is_writable( $log_dir ) );
	}

	/**
	 * Test log directory has protection files.
	 */
	public function test_log_directory_protection() {
		require_once BOOKIT_PLUGIN_DIR . 'includes/class-bookit-logger.php';
		
		$log_dir = Bookit_Logger::get_log_directory();
		
		$this->assertTrue( file_exists( $log_dir . '/.htaccess' ) );
		$this->assertTrue( file_exists( $log_dir . '/index.php' ) );
		$this->assertTrue( file_exists( $log_dir . '/README.txt' ) );
	}
}
