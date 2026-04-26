<?php
/**
 * Tests for Bookit Meetings link generation (Sprint 1, Task 4).
 *
 * @package Bookit_Meetings
 */

class Test_Bookit_Meetings_Link_Generator extends WP_UnitTestCase {
	private Bookit_Meetings_Link_Generator $generator;

	public function setUp(): void {
		parent::setUp();

		$this->ensure_meetings_schema_exists();
		bookit_test_truncate_tables( array( 'bookings_settings', 'bookings' ) );

		$this->seed_default_settings();

		require_once BOOKIT_MEETINGS_PLUGIN_DIR . 'includes/class-bookit-meetings-link-generator.php';
		$this->generator = new Bookit_Meetings_Link_Generator();
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

	private function get_meeting_link( int $booking_id ): ?string {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.PreparedSQL.NotPrepared
		return $wpdb->get_var(
			$wpdb->prepare(
				"SELECT meeting_link FROM {$wpdb->prefix}bookings WHERE id = %d",
				$booking_id
			)
		);
	}

	private function set_settings( string $platform, string $url = '', string $enabled = '1' ): void {
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

		$wpdb->update(
			$settings_table,
			array( 'setting_value' => $url ),
			array( 'setting_key' => 'meetings_manual_url' ),
			array( '%s' ),
			array( '%s' )
		);
	}

	public function test_does_nothing_when_meetings_disabled(): void {
		$this->set_settings( 'teams', 'https://teams.microsoft.com/meet/123', '0' );

		$id = $this->insert_test_booking();
		$this->generator->handle_booking_confirmed( $id, array() );

		$this->assertNull( $this->get_meeting_link( $id ) );
	}

	public function test_does_nothing_when_platform_empty(): void {
		$this->set_settings( '', '', '1' );

		$id = $this->insert_test_booking();
		$this->generator->handle_booking_confirmed( $id, array() );

		$this->assertNull( $this->get_meeting_link( $id ) );
	}

	public function test_does_nothing_for_whatsapp(): void {
		$this->set_settings( 'whatsapp', '', '1' );

		$id = $this->insert_test_booking();
		$this->generator->handle_booking_confirmed( $id, array() );

		$this->assertNull( $this->get_meeting_link( $id ) );
	}

	public function test_does_nothing_when_manual_url_empty(): void {
		$this->set_settings( 'teams', '', '1' );

		$id = $this->insert_test_booking();
		$this->generator->handle_booking_confirmed( $id, array() );

		$this->assertNull( $this->get_meeting_link( $id ) );
	}

	public function test_sets_link_for_teams(): void {
		$url = 'https://teams.microsoft.com/meet/abc';
		$this->set_settings( 'teams', $url, '1' );

		$id = $this->insert_test_booking();
		$this->generator->handle_booking_confirmed( $id, array() );

		$this->assertSame( $url, $this->get_meeting_link( $id ) );
	}

	public function test_sets_link_for_generic(): void {
		$url = 'https://meet.example.com/room';
		$this->set_settings( 'generic', $url, '1' );

		$id = $this->insert_test_booking();
		$this->generator->handle_booking_confirmed( $id, array() );

		$this->assertSame( $url, $this->get_meeting_link( $id ) );
	}

	public function test_does_not_overwrite_existing_link(): void {
		$this->set_settings( 'teams', 'https://teams.microsoft.com/new', '1' );

		$id = $this->insert_test_booking(
			array(
				'meeting_link' => 'https://existing.example.com',
			)
		);
		$this->generator->handle_booking_confirmed( $id, array() );

		$this->assertSame( 'https://existing.example.com', $this->get_meeting_link( $id ) );
	}

	public function test_does_nothing_for_soft_deleted_booking(): void {
		$url = 'https://teams.microsoft.com/meet/abc';
		$this->set_settings( 'teams', $url, '1' );

		$id = $this->insert_test_booking(
			array(
				'deleted_at' => current_time( 'mysql' ),
			)
		);
		$this->generator->handle_booking_confirmed( $id, array() );

		$this->assertNull( $this->get_meeting_link( $id ) );
	}

	public function test_handle_booking_created_sets_link(): void {
		$url = 'https://meet.example.com/room';
		$this->set_settings( 'generic', $url, '1' );

		$id = $this->insert_test_booking();
		$this->generator->handle_booking_created( $id, array() );

		$this->assertSame( $url, $this->get_meeting_link( $id ) );
	}

	public function test_handle_booking_confirmed_and_created_same_result(): void {
		$url = 'https://teams.microsoft.com/meet/xyz';
		$this->set_settings( 'teams', $url, '1' );

		$id1 = $this->insert_test_booking(
			array(
				'start_time' => '10:00:00',
				'end_time'   => '11:00:00',
			)
		);
		$id2 = $this->insert_test_booking(
			array(
				'start_time' => '12:00:00',
				'end_time'   => '13:00:00',
			)
		);

		$this->generator->handle_booking_confirmed( $id1, array() );
		$this->generator->handle_booking_created( $id2, array() );

		$this->assertSame( $url, $this->get_meeting_link( $id1 ) );
		$this->assertSame( $url, $this->get_meeting_link( $id2 ) );
	}
}

