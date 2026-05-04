<?php
/**
 * Tests for notification provider interfaces and scaffold providers.
 *
 * @package    Bookit_Booking_System
 * @subpackage Tests
 */

/**
 * Test provider configuration and baseline send behavior.
 */
class Test_Provider_Interfaces extends WP_UnitTestCase {

	/**
	 * Set up each test.
	 */
	public function setUp(): void {
		parent::setUp();
		$this->clear_settings();
	}

	/**
	 * Tear down each test.
	 */
	public function tearDown(): void {
		$this->clear_settings();
		parent::tearDown();
	}

	/**
	 * @covers Bookit_Brevo_Email_Provider::is_configured
	 */
	public function test_brevo_provider_is_not_configured_without_api_key() {
		$this->set_setting( 'brevo_api_key', '' );

		$provider = new Bookit_Brevo_Email_Provider();
		$this->assertFalse( $provider->is_configured() );
	}

	/**
	 * @covers Bookit_Brevo_Email_Provider::is_configured
	 */
	public function test_brevo_provider_is_configured_with_api_key() {
		$this->set_setting( 'brevo_api_key', 'test_brevo_key' );

		$provider = new Bookit_Brevo_Email_Provider();
		$this->assertTrue( $provider->is_configured() );
	}

	/**
	 * @covers Bookit_WP_Mail_Fallback_Provider::is_configured
	 */
	public function test_wp_mail_fallback_is_always_configured() {
		$provider = new Bookit_WP_Mail_Fallback_Provider();
		$this->assertTrue( $provider->is_configured() );
	}

	/**
	 * @covers Bookit_Brevo_SMS_Provider::is_configured
	 */
	public function test_brevo_sms_is_not_configured_without_key() {
		$this->set_setting( 'brevo_sms_api_key', '' );

		$provider = new Bookit_Brevo_SMS_Provider();
		$this->assertFalse( $provider->is_configured() );
	}

	/**
	 * @covers Bookit_Brevo_SMS_Provider::send
	 */
	public function test_brevo_sms_stub_send_returns_true() {
		$provider = new Bookit_Brevo_SMS_Provider();
		$result   = $provider->send( '+447911123456', 'Test' );

		$this->assertTrue( $result );
	}

	/**
	 * @covers Bookit_Brevo_Email_Provider::get_slug
	 * @covers Bookit_WP_Mail_Fallback_Provider::get_slug
	 * @covers Bookit_Brevo_SMS_Provider::get_slug
	 */
	public function test_provider_slugs_are_correct() {
		$email_brevo = new Bookit_Brevo_Email_Provider();
		$wp_mail     = new Bookit_WP_Mail_Fallback_Provider();
		$sms_brevo   = new Bookit_Brevo_SMS_Provider();

		$this->assertSame( 'brevo', $email_brevo->get_slug() );
		$this->assertSame( 'wp_mail', $wp_mail->get_slug() );
		$this->assertSame( 'brevo', $sms_brevo->get_slug() );
	}

	/**
	 * @covers Bookit_WP_Mail_Fallback_Provider::send
	 */
	public function test_wp_mail_fallback_send_returns_wp_error_on_failure() {
		$provider = new Bookit_WP_Mail_Fallback_Provider();

		$callback = static function () {
			return false;
		};

		add_filter( 'pre_wp_mail', $callback, 10, 2 );
		$result = $provider->send(
			[
				'email' => 'customer@example.com',
				'name'  => 'Test Customer',
			],
			'Test Subject',
			'<p>Test HTML</p>'
		);
		remove_filter( 'pre_wp_mail', $callback, 10 );

		$this->assertInstanceOf( 'WP_Error', $result );
		$this->assertSame( 'wp_mail_failed', $result->get_error_code() );
	}

	/**
	 * Insert/update a single setting row in bookings_settings.
	 *
	 * @param string $key   Setting key.
	 * @param string $value Setting value.
	 * @return void
	 */
	private function set_setting( string $key, string $value ): void {
		global $wpdb;

		$settings_table = $wpdb->prefix . 'bookings_settings';

		$wpdb->query(
			$wpdb->prepare(
				"DELETE FROM {$settings_table} WHERE setting_key = %s",
				$key
			)
		);

		$wpdb->insert(
			$settings_table,
			[
				'setting_key'   => $key,
				'setting_value' => $value,
				'created_at'    => current_time( 'mysql' ),
				'updated_at'    => current_time( 'mysql' ),
			],
			[ '%s', '%s', '%s', '%s' ]
		);
	}

	/**
	 * Clear settings table rows for this test class.
	 *
	 * @return void
	 */
	private function clear_settings(): void {
		global $wpdb;

		$settings_table = $wpdb->prefix . 'bookings_settings';
		$wpdb->query( "TRUNCATE TABLE {$settings_table}" ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.DirectDatabaseQuery.DirectQuery
	}
}
