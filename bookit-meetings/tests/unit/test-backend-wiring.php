<?php
/**
 * Tests for Sprint 2 backend wiring (assets/data/booking response).
 *
 * @package Bookit_Meetings
 */

class Test_Bookit_Meetings_Backend_Wiring extends WP_UnitTestCase {
	public function setUp(): void {
		parent::setUp();
		bookit_test_truncate_tables( array( 'bookings_settings', 'bookings' ) );
	}

	public function tearDown(): void {
		bookit_test_truncate_tables( array( 'bookings_settings', 'bookings' ) );
		parent::tearDown();
	}

	private function insert_setting( string $key, string $value ): void {
		global $wpdb;

		$wpdb->insert(
			$wpdb->prefix . 'bookings_settings',
			array(
				'setting_key'   => $key,
				'setting_value' => $value,
			),
			array( '%s', '%s' )
		);
	}

	private function bookings_table_has_meeting_link_column(): bool {
		global $wpdb;

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
		return ( (int) $wpdb->get_var( $sql ) ) > 0;
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

	public function test_dashboard_js_data_filter_adds_meetings_enabled(): void {
		$this->insert_setting( 'meetings_enabled', '1' );

		$data = apply_filters( 'bookit_dashboard_js_data', array() );

		$this->assertIsArray( $data );
		$this->assertArrayHasKey( 'meetings_enabled', $data );
		$this->assertTrue( $data['meetings_enabled'] );
		$this->assertIsBool( $data['meetings_enabled'] );
	}

	public function test_dashboard_js_data_filter_meetings_enabled_defaults_to_false(): void {
		$data = apply_filters( 'bookit_dashboard_js_data', array() );

		$this->assertIsArray( $data );
		$this->assertArrayHasKey( 'meetings_enabled', $data );
		$this->assertFalse( $data['meetings_enabled'] );
		$this->assertIsBool( $data['meetings_enabled'] );
	}

	public function test_dashboard_js_data_filter_adds_platform(): void {
		$this->insert_setting( 'meetings_platform', 'whatsapp' );

		$data = apply_filters( 'bookit_dashboard_js_data', array() );

		$this->assertSame( 'whatsapp', $data['meetings_platform'] );
	}

	public function test_dashboard_js_data_filter_adds_manual_url(): void {
		$this->insert_setting( 'meetings_manual_url', 'https://teams.example.com/meet' );

		$data = apply_filters( 'bookit_dashboard_js_data', array() );

		$this->assertSame( 'https://teams.example.com/meet', $data['meetings_manual_url'] );
	}

	public function test_booking_response_filter_adds_meeting_link(): void {
		if ( ! $this->bookings_table_has_meeting_link_column() ) {
			$this->markTestSkipped( 'bookings.meeting_link column is missing in test DB.' );
		}

		$url        = 'https://teams.example.com/meet/abc';
		$booking_id = $this->insert_test_booking( array( 'meeting_link' => $url ) );

		$response = apply_filters(
			'bookit_booking_response',
			array( 'id' => $booking_id ),
			$booking_id
		);

		$this->assertIsArray( $response );
		$this->assertSame( $url, $response['meeting_link'] );
	}

	public function test_booking_response_filter_empty_string_when_no_link(): void {
		if ( ! $this->bookings_table_has_meeting_link_column() ) {
			$this->markTestSkipped( 'bookings.meeting_link column is missing in test DB.' );
		}

		$booking_id = $this->insert_test_booking( array( 'meeting_link' => null ) );

		$response = apply_filters(
			'bookit_booking_response',
			array( 'id' => $booking_id ),
			$booking_id
		);

		$this->assertSame( '', $response['meeting_link'] );
	}
}

