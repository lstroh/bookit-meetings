<?php
/**
 * Tests for Bookit Meetings customer-facing surfaces (Sprint 1, Task 5).
 *
 * @package Bookit_Meetings
 */

class Test_Bookit_Meetings_Customer_Surfaces extends WP_UnitTestCase {
	private Bookit_Meetings_Customer_Surfaces $surfaces;

	public function setUp(): void {
		parent::setUp();

		$this->ensure_meetings_schema_exists();
		bookit_test_truncate_tables( array( 'bookings_settings', 'bookings' ) );

		$this->seed_default_settings();

		require_once BOOKIT_MEETINGS_PLUGIN_DIR . 'includes/class-bookit-meetings-customer-surfaces.php';
		$this->surfaces = new Bookit_Meetings_Customer_Surfaces();
	}

	public function tearDown(): void {
		bookit_test_truncate_tables( array( 'bookings_settings', 'bookings' ) );
		parent::tearDown();
	}

	private function ensure_meetings_schema_exists(): void {
		global $wpdb;

		require_once BOOKIT_MEETINGS_PLUGIN_DIR . 'database/migrations/0001-add-meetings-schema.php';
		$migration = new Bookit_Migration_Meetings_0001_Add_Meetings_Schema();

		$table_name = $wpdb->prefix . 'bookings';
		$sql        = $wpdb->prepare(
			"SELECT COUNT(*)
			FROM information_schema.COLUMNS
			WHERE TABLE_SCHEMA = %s
				AND TABLE_NAME = %s
				AND COLUMN_NAME = %s",
			DB_NAME,
			$table_name,
			'meeting_link'
		);

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.PreparedSQL.NotPrepared
		$column_exists = (int) $wpdb->get_var( $sql );

		if ( $column_exists > 0 ) {
			return;
		}

		$migration->up();
	}

	private function seed_default_settings(): void {
		global $wpdb;

		$settings_table = $wpdb->prefix . 'bookings_settings';
		$defaults       = array(
			'meetings_enabled'    => '0',
			'meetings_platform'   => '',
			'meetings_manual_url' => '',
		);

		foreach ( $defaults as $key => $value ) {
			$wpdb->insert(
				$settings_table,
				array(
					'setting_key'   => $key,
					'setting_value' => $value,
				),
				array( '%s', '%s' )
			);
		}
	}

	private function insert_test_booking( array $overrides = array() ): int {
		global $wpdb;

		$defaults = array(
			'customer_id'      => 1,
			'staff_id'         => 1,
			'service_id'       => 1,
			'booking_date'     => current_time( 'Y-m-d' ),
			'start_time'       => '10:00:00',
			'end_time'         => '11:00:00',
			'duration'         => 60,
			'status'           => 'confirmed',
			'total_price'      => 0.00,
			'deposit_paid'     => 0.00,
			'balance_due'      => 0.00,
			'full_amount_paid' => 0,
			'payment_method'   => 'pay_on_arrival',
			'created_at'       => current_time( 'mysql' ),
			'updated_at'       => current_time( 'mysql' ),
			'meeting_link'     => null,
			'deleted_at'       => null,
		);

		$data = wp_parse_args( $overrides, $defaults );

		$wpdb->insert( $wpdb->prefix . 'bookings', $data );
		return (int) $wpdb->insert_id;
	}

	private function make_booking( string $meeting_link = null ): array {
		$id = $this->insert_test_booking(
			array(
				'meeting_link' => $meeting_link,
			)
		);

		return array(
			'id'           => $id,
			'meeting_link' => null,
			'status'       => 'confirmed',
		);
	}

	private function set_settings( string $enabled, string $platform ): void {
		global $wpdb;

		$settings_table = $wpdb->prefix . 'bookings_settings';

		$wpdb->update(
			$settings_table,
			array( 'setting_value' => $enabled ),
			array( 'setting_key' => 'meetings_enabled' ),
			array( '%s' ),
			array( '%s' )
		);

		$wpdb->update(
			$settings_table,
			array( 'setting_value' => $platform ),
			array( 'setting_key' => 'meetings_platform' ),
			array( '%s' ),
			array( '%s' )
		);
	}

	public function test_page_returns_empty_when_disabled(): void {
		$this->set_settings( '0', 'teams' );

		$result = $this->surfaces->confirmation_page_section( '', $this->make_booking( 'https://teams.microsoft.com/meet/abc' ) );
		$this->assertSame( '', $result );
	}

	public function test_page_returns_empty_when_platform_empty(): void {
		$this->set_settings( '1', '' );

		$result = $this->surfaces->confirmation_page_section( '', $this->make_booking( 'https://teams.microsoft.com/meet/abc' ) );
		$this->assertSame( '', $result );
	}

	public function test_page_returns_empty_for_unknown_platform(): void {
		$this->set_settings( '1', 'zoom' );

		$result = $this->surfaces->confirmation_page_section( '', $this->make_booking( 'https://zoom.us/j/123' ) );
		$this->assertSame( '', $result );
	}

	public function test_page_returns_empty_for_teams_without_link(): void {
		$this->set_settings( '1', 'teams' );

		$result = $this->surfaces->confirmation_page_section( '', $this->make_booking() );
		$this->assertSame( '', $result );
	}

	public function test_page_returns_join_button_for_teams(): void {
		$this->set_settings( '1', 'teams' );
		$url    = 'https://teams.microsoft.com/meet/abc';
		$result = $this->surfaces->confirmation_page_section( '', $this->make_booking( $url ) );

		$this->assertStringContainsString( 'bookit-meeting-btn', $result );
		$this->assertStringContainsString( $url, $result );
	}

	public function test_page_returns_join_button_for_generic(): void {
		$this->set_settings( '1', 'generic' );
		$url    = 'https://meet.example.com/room';
		$result = $this->surfaces->confirmation_page_section( '', $this->make_booking( $url ) );

		$this->assertStringContainsString( 'bookit-meeting-btn', $result );
		$this->assertStringContainsString( $url, $result );
	}

	public function test_page_returns_whatsapp_message(): void {
		$this->set_settings( '1', 'whatsapp' );

		$result = $this->surfaces->confirmation_page_section( '', $this->make_booking() );
		$this->assertStringContainsString( 'bookit-meeting-whatsapp', $result );
		$this->assertStringContainsString( 'WhatsApp', $result );
	}

	public function test_email_returns_empty_when_disabled(): void {
		$this->set_settings( '0', 'teams' );

		$result = $this->surfaces->confirmation_email_section( '', $this->make_booking( 'https://teams.microsoft.com/meet/abc' ) );
		$this->assertSame( '', $result );
	}

	public function test_email_returns_empty_when_platform_empty(): void {
		$this->set_settings( '1', '' );

		$result = $this->surfaces->confirmation_email_section( '', $this->make_booking( 'https://teams.microsoft.com/meet/abc' ) );
		$this->assertSame( '', $result );
	}

	public function test_email_returns_link_row_for_teams(): void {
		$this->set_settings( '1', 'teams' );
		$url    = 'https://teams.microsoft.com/meet/abc';
		$result = $this->surfaces->confirmation_email_section( '', $this->make_booking( $url ) );

		$this->assertStringContainsString( 'Meeting link:', $result );
		$this->assertStringContainsString( $url, $result );
		$this->assertStringContainsString( 'style=', $result );
	}

	public function test_email_returns_link_row_for_generic(): void {
		$this->set_settings( '1', 'generic' );
		$url    = 'https://meet.example.com/room';
		$result = $this->surfaces->confirmation_email_section( '', $this->make_booking( $url ) );

		$this->assertStringContainsString( 'Meeting link:', $result );
		$this->assertStringContainsString( $url, $result );
		$this->assertStringContainsString( 'style=', $result );
	}

	public function test_email_returns_whatsapp_row(): void {
		$this->set_settings( '1', 'whatsapp' );

		$result = $this->surfaces->confirmation_email_section( '', $this->make_booking() );
		$this->assertStringContainsString( 'WhatsApp', $result );
		$this->assertStringContainsString( '<tr>', $result );
	}

	public function test_email_uses_inline_styles_not_classes(): void {
		$this->set_settings( '1', 'teams' );

		$result = $this->surfaces->confirmation_email_section( '', $this->make_booking( 'https://teams.microsoft.com/meet/abc' ) );
		$this->assertStringNotContainsString( 'class=', $result );
	}
}

