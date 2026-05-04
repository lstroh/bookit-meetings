<?php
/**
 * Tests for Bookit_Brevo_Email_Provider (Brevo PHP SDK v4).
 *
 * @package    Bookit_Booking_System
 * @subpackage Tests
 */

/**
 * Test double that captures SendTransacEmailRequest without calling the Brevo API.
 */
class Bookit_Brevo_Email_Provider_TestDouble extends Bookit_Brevo_Email_Provider {

	/**
	 * Last request passed to invoke_brevo_send.
	 *
	 * @var \Brevo\TransactionalEmails\Requests\SendTransacEmailRequest|null
	 */
	public $last_request = null;

	/**
	 * {@inheritdoc}
	 */
	protected function invoke_brevo_send( string $api_key, \Brevo\TransactionalEmails\Requests\SendTransacEmailRequest $request ): bool|\WP_Error {
		$this->last_request = $request;
		return true;
	}
}

/**
 * Test Brevo email provider configuration and send guardrails.
 */
class Test_Brevo_Email_Provider extends WP_UnitTestCase {

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
	 * @covers Bookit_Brevo_Email_Provider::send
	 */
	public function test_send_returns_wp_error_when_api_key_empty() {
		$this->set_setting( 'brevo_api_key', '' );

		$provider = new Bookit_Brevo_Email_Provider();
		$result   = $provider->send(
			[
				'email' => 'test@example.com',
				'name'  => 'Test',
			],
			'Subject',
			'<p>HTML</p>'
		);

		$this->assertInstanceOf( 'WP_Error', $result );
		$this->assertSame( 'brevo_not_configured', $result->get_error_code() );
	}

	/**
	 * @covers Bookit_Brevo_Email_Provider::is_configured
	 */
	public function test_is_configured_returns_false_when_no_api_key() {
		$this->set_setting( 'brevo_api_key', '' );

		$provider = new Bookit_Brevo_Email_Provider();
		$this->assertFalse( $provider->is_configured() );
	}

	/**
	 * @covers Bookit_Brevo_Email_Provider::is_configured
	 */
	public function test_is_configured_returns_true_when_api_key_set() {
		$this->set_setting( 'brevo_api_key', 'sk_test_brevo_key' );

		$provider = new Bookit_Brevo_Email_Provider();
		$this->assertTrue( $provider->is_configured() );
	}

	/**
	 * @covers Bookit_Brevo_Email_Provider::get_name
	 */
	public function test_get_name_returns_brevo() {
		$provider = new Bookit_Brevo_Email_Provider();
		$this->assertSame( 'Brevo', $provider->get_name() );
	}

	/**
	 * @covers Bookit_Brevo_Email_Provider::get_slug
	 */
	public function test_get_slug_returns_brevo() {
		$provider = new Bookit_Brevo_Email_Provider();
		$this->assertSame( 'brevo', $provider->get_slug() );
	}

	/**
	 * @covers Bookit_Brevo_Email_Provider::send
	 */
	public function test_brevo_provider_uses_template_id_when_set() {
		$this->set_setting( 'brevo_api_key', 'sk_test_brevo_key' );
		$this->set_setting( 'brevo_template_booking_confirmed', '42' );

		$provider = new Bookit_Brevo_Email_Provider_TestDouble();
		$result   = $provider->send(
			array(
				'email' => 'test@example.com',
				'name'  => 'Test',
			),
			'Subject Line',
			'<p>HTML body</p>',
			array( 'email_type' => 'customer_confirmation' )
		);

		$this->assertTrue( $result );
		$this->assertNotNull( $provider->last_request );
		$this->assertSame( 42, $provider->last_request->templateId );
		$this->assertNull( $provider->last_request->htmlContent );
		$this->assertNull( $provider->last_request->subject );
	}

	/**
	 * @covers Bookit_Brevo_Email_Provider::send
	 */
	public function test_brevo_provider_falls_back_to_html_when_no_template_id() {
		$this->set_setting( 'brevo_api_key', 'sk_test_brevo_key' );

		$provider = new Bookit_Brevo_Email_Provider_TestDouble();
		$result   = $provider->send(
			array(
				'email' => 'test@example.com',
				'name'  => 'Test',
			),
			'Subject Line',
			'<p>HTML body</p>',
			array( 'email_type' => 'customer_confirmation' )
		);

		$this->assertTrue( $result );
		$this->assertNotNull( $provider->last_request );
		$this->assertNull( $provider->last_request->templateId );
		$this->assertSame( '<p>HTML body</p>', $provider->last_request->htmlContent );
		$this->assertSame( 'Subject Line', $provider->last_request->subject );
	}

	/**
	 * @covers Bookit_Brevo_Email_Provider::send
	 */
	public function test_brevo_provider_passes_params_when_template_id_set() {
		$this->set_setting( 'brevo_api_key', 'sk_test_brevo_key' );
		$this->set_setting( 'brevo_template_booking_confirmed', '42' );

		$provider = new Bookit_Brevo_Email_Provider_TestDouble();
		$result   = $provider->send(
			array(
				'email' => 'test@example.com',
				'name'  => 'Test',
			),
			'Subject Line',
			'<p>HTML body</p>',
			array(
				'email_type'    => 'customer_confirmation',
				'customer_name' => 'Jane Smith',
				'service_name'  => 'Haircut',
			)
		);

		$this->assertTrue( $result );
		$this->assertNotNull( $provider->last_request );
		$this->assertSame( 42, $provider->last_request->templateId );
		$this->assertIsArray( $provider->last_request->params );
		$this->assertSame( 'Jane Smith', $provider->last_request->params['customer_name'] );
		$this->assertSame( 'Haircut', $provider->last_request->params['service_name'] );
		$this->assertArrayNotHasKey( 'email_type', $provider->last_request->params );
	}

	/**
	 * @covers Bookit_Brevo_Email_Provider::send
	 */
	public function test_brevo_provider_ignores_params_when_using_html_fallback() {
		$this->set_setting( 'brevo_api_key', 'sk_test_brevo_key' );

		$provider = new Bookit_Brevo_Email_Provider_TestDouble();
		$result   = $provider->send(
			array(
				'email' => 'test@example.com',
				'name'  => 'Test',
			),
			'Subject Line',
			'<p>HTML body</p>',
			array(
				'email_type'    => 'customer_confirmation',
				'customer_name' => 'Jane Smith',
				'service_name'  => 'Haircut',
			)
		);

		$this->assertTrue( $result );
		$this->assertNotNull( $provider->last_request );
		$this->assertNull( $provider->last_request->templateId );
		$this->assertSame( '<p>HTML body</p>', $provider->last_request->htmlContent );
		$this->assertNull( $provider->last_request->params );
	}

	/**
	 * @covers Bookit_Brevo_Email_Provider::send
	 */
	public function test_brevo_provider_maps_staff_new_booking_to_template_setting() {
		$this->set_setting( 'brevo_api_key', 'sk_test_brevo_key' );
		$this->set_setting( 'brevo_template_staff_new_booking', '99' );

		$provider = new Bookit_Brevo_Email_Provider_TestDouble();
		$result   = $provider->send(
			array(
				'email' => 'staff@example.com',
				'name'  => 'Staff',
			),
			'Subject',
			'<p>HTML</p>',
			array( 'email_type' => 'staff_new_booking_immediate' )
		);

		$this->assertTrue( $result );
		$this->assertNotNull( $provider->last_request );
		$this->assertSame( 99, $provider->last_request->templateId );
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
	 * Clear settings table rows used by these tests.
	 *
	 * @return void
	 */
	private function clear_settings(): void {
		global $wpdb;

		$settings_table = $wpdb->prefix . 'bookings_settings';
		$wpdb->query( "TRUNCATE TABLE {$settings_table}" ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.DirectDatabaseQuery.DirectQuery
	}
}
