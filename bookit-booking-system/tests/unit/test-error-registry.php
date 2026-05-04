<?php
/**
 * Tests for error registry.
 *
 * @package    Bookit_Booking_System
 * @subpackage Tests
 */

/**
 * Test Bookit_Error_Registry.
 */
class Test_Bookit_Error_Registry extends WP_UnitTestCase {

	/**
	 * Set up each test.
	 */
	public function setUp(): void {
		parent::setUp();

		// Ensure core error map is loaded for expected assertions.
		require_once BOOKIT_PLUGIN_DIR . 'includes/config/error-codes.php';
	}

	/**
	 * @covers Bookit_Error_Registry::get
	 */
	public function test_registered_error_code_is_retrievable() {
		$definition = Bookit_Error_Registry::get( 'E1001' );

		$this->assertArrayHasKey( 'user_message', $definition );
		$this->assertArrayHasKey( 'log_message', $definition );
		$this->assertArrayHasKey( 'http_status', $definition );
		$this->assertArrayHasKey( 'category', $definition );
		$this->assertSame( 401, (int) $definition['http_status'] );
	}

	/**
	 * @covers Bookit_Error_Registry::get
	 */
	public function test_unknown_code_returns_fallback() {
		$definition = Bookit_Error_Registry::get( 'UNKNOWN_CODE' );

		$this->assertSame( 500, (int) $definition['http_status'] );
	}

	/**
	 * @covers Bookit_Error_Registry::to_wp_error
	 */
	public function test_to_wp_error_returns_wp_error_instance() {
		$error = Bookit_Error_Registry::to_wp_error( 'E1001' );

		$this->assertInstanceOf( 'WP_Error', $error );
	}

	/**
	 * @covers Bookit_Error_Registry::to_wp_error
	 */
	public function test_to_wp_error_sets_correct_http_status() {
		$error = Bookit_Error_Registry::to_wp_error( 'E2001' );

		$this->assertSame( 409, (int) $error->get_error_data()['status'] );
	}

	/**
	 * @covers Bookit_Error_Registry::to_wp_error
	 */
	public function test_placeholder_substitution_in_user_message() {
		$error = Bookit_Error_Registry::to_wp_error(
			'E2002',
			array( 'booking_id' => 42 )
		);

		$message = $error->get_error_message();

		$this->assertStringContainsString( '42', $message );
		$this->assertStringNotContainsString( '{booking_id}', $message );
	}

	/**
	 * @covers Bookit_Error_Registry::register
	 * @covers Bookit_Error_Registry::get
	 */
	public function test_duplicate_registration_is_ignored() {
		$code = 'TEST_DUP_001';

		Bookit_Error_Registry::register(
			$code,
			array(
				'user_message' => 'First definition',
				'log_message'  => 'First definition',
				'http_status'  => 422,
				'category'     => 'testing',
			)
		);

		Bookit_Error_Registry::register(
			$code,
			array(
				'user_message' => 'Second definition',
				'log_message'  => 'Second definition',
				'http_status'  => 500,
				'category'     => 'testing',
			)
		);

		$definition = Bookit_Error_Registry::get( $code );

		$this->assertSame( 'First definition', $definition['user_message'] );
		$this->assertSame( 422, (int) $definition['http_status'] );
	}

	/**
	 * @covers Bookit_Error_Registry::register
	 * @covers Bookit_Error_Registry::get
	 */
	public function test_extension_can_register_custom_code() {
		$code = 'MYEXT_E001';

		Bookit_Error_Registry::register(
			$code,
			array(
				'user_message' => 'Extension message',
				'log_message'  => 'Extension log message',
				'http_status'  => 418,
				'category'     => 'extension',
			)
		);

		$definition = Bookit_Error_Registry::get( $code );

		$this->assertSame( 'Extension message', $definition['user_message'] );
		$this->assertSame( 'extension', $definition['category'] );
		$this->assertSame( 418, (int) $definition['http_status'] );
	}
}
