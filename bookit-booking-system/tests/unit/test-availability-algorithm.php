<?php
/**
 * Availability Algorithm Unit Tests (Sprint 1, Task 5)
 *
 * Tests the complex time slot availability calculation including:
 * - Working hours integration
 * - Existing booking conflicts
 * - Buffer time calculations
 * - Break time exclusions
 * - "No Preference" staff aggregation
 * - Past slot filtering
 *
 * Target Coverage: 85%+
 *
 * @package    Bookit_Booking_System
 * @subpackage Tests
 */

/**
 * Test availability algorithm: working hours, bookings, buffers, breaks, no-preference.
 */
class Test_Availability_Algorithm extends WP_UnitTestCase {

	/**
	 * DateTime model instance.
	 *
	 * @var Bookit_DateTime_Model
	 */
	private $model;

	/**
	 * Cached customer ID for booking inserts.
	 *
	 * @var int|null
	 */
	private $customer_id;

	/**
	 * Set up each test.
	 */
	public function setUp(): void {
		parent::setUp();

		require_once dirname( __DIR__, 2 ) . '/database/migrations/migration-add-staff-working-hours.php';

		global $wpdb;

		$this->ensure_staff_working_hours_table();
		$this->truncate_availability_tables();

		$this->model = new Bookit_DateTime_Model();
	}

	/**
	 * Tear down each test.
	 */
	public function tearDown(): void {
		$this->truncate_availability_tables();
		parent::tearDown();
	}

	/**
	 * Ensure wp_bookings_staff_working_hours table exists.
	 */
	private function ensure_staff_working_hours_table() {
		global $wpdb;
		$table = $wpdb->prefix . 'bookings_staff_working_hours';
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		if ( $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) ) === $table ) {
			return;
		}
		$migration = new Bookit_Migration_Add_Staff_Working_Hours();
		$migration->up();
	}

	/**
	 * Truncate tables used by availability tests.
	 */
	private function truncate_availability_tables() {
		global $wpdb;

		bookit_test_truncate_tables(
			array(
				'bookings_package_redemptions',
				'bookings_customer_packages',
				'bookings_package_types',
				'bookings',
				'bookings_staff_services',
				'bookings_staff',
				'bookings_services',
				'bookings_customers',
			)
		);

		$p = $wpdb->prefix;
		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.DirectDatabaseQuery.DirectQuery
		$wpdb->query( "DELETE FROM {$p}bookings_staff_working_hours" );
	}

	/**
	 * Create staff working hours row (day_of_week: 1=Mon, 7=Sun).
	 *
	 * @param int         $staff_id    Staff ID.
	 * @param int|null    $day_of_week Day 1-7 or null for specific_date.
	 * @param string|null $specific_date Date Y-m-d or null.
	 * @param string      $start_time  Start time.
	 * @param string      $end_time    End time.
	 * @param int         $is_working  1 or 0.
	 * @param string|null $break_start Break start.
	 * @param string|null $break_end   Break end.
	 * @return int Insert ID.
	 */
	private function add_working_hours( $staff_id, $day_of_week, $specific_date, $start_time, $end_time, $is_working = 1, $break_start = null, $break_end = null ) {
		global $wpdb;
		$wpdb->insert(
			$wpdb->prefix . 'bookings_staff_working_hours',
			array(
				'staff_id'      => $staff_id,
				'day_of_week'   => $day_of_week,
				'specific_date' => $specific_date,
				'start_time'    => $start_time,
				'end_time'      => $end_time,
				'is_working'    => $is_working,
				'break_start'   => $break_start,
				'break_end'     => $break_end,
			),
			array( '%d', '%d', '%s', '%s', '%s', '%d', '%s', '%s' )
		);
		return (int) $wpdb->insert_id;
	}

	/**
	 * Create test service.
	 *
	 * @param array $args Override defaults.
	 * @return int Service ID.
	 */
	private function create_service( $args = array() ) {
		global $wpdb;
		$defaults = array(
			'name'           => 'Test Service',
			'description'   => '',
			'duration'      => 60,
			'price'         => 50.00,
			'deposit_amount' => null,
			'deposit_type'  => 'fixed',
			'buffer_before' => 0,
			'buffer_after'  => 0,
			'is_active'     => 1,
			'display_order' => 0,
			'created_at'     => current_time( 'mysql' ),
			'updated_at'     => current_time( 'mysql' ),
			'deleted_at'     => null,
		);
		$data = wp_parse_args( $args, $defaults );
		$wpdb->insert( $wpdb->prefix . 'bookings_services', $data, array( '%s', '%s', '%d', '%f', '%f', '%s', '%d', '%d', '%d', '%d', '%d', '%s', '%s', '%s' ) );
		return (int) $wpdb->insert_id;
	}

	/**
	 * Create test staff.
	 *
	 * @param array $args Override defaults.
	 * @return int Staff ID.
	 */
	private function create_staff( $args = array() ) {
		global $wpdb;
		$defaults = array(
			'first_name'   => 'Test',
			'last_name'    => 'Staff',
			'email'        => 'staff' . wp_rand( 1, 99999 ) . '@test.local',
			'phone'        => '',
			'photo_url'    => null,
			'bio'          => null,
			'title'        => null,
			'role'         => 'staff',
			'google_calendar_id' => null,
			'is_active'    => 1,
			'display_order' => 0,
			'created_at'   => current_time( 'mysql' ),
			'updated_at'   => current_time( 'mysql' ),
			'deleted_at'   => null,
		);
		$data = wp_parse_args( $args, $defaults );
		$wpdb->insert( $wpdb->prefix . 'bookings_staff', $data, array( '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%d', '%d', '%s', '%s', '%s' ) );
		return (int) $wpdb->insert_id;
	}

	/**
	 * Link staff to service.
	 *
	 * @param int $staff_id   Staff ID.
	 * @param int $service_id Service ID.
	 */
	private function link_staff_service( $staff_id, $service_id ) {
		global $wpdb;
		$wpdb->insert(
			$wpdb->prefix . 'bookings_staff_services',
			array( 'staff_id' => $staff_id, 'service_id' => $service_id, 'custom_price' => null, 'created_at' => current_time( 'mysql' ) ),
			array( '%d', '%d', '%s', '%s' )
		);
	}

	/**
	 * Get or create a test customer for bookings.
	 *
	 * @return int Customer ID.
	 */
	private function get_customer_id() {
		if ( $this->customer_id !== null ) {
			return $this->customer_id;
		}
		global $wpdb;
		$wpdb->insert(
			$wpdb->prefix . 'bookings_customers',
			array(
				'email'      => 'availability-test-' . wp_rand( 1, 99999 ) . '@test.local',
				'first_name' => 'Test',
				'last_name'  => 'Customer',
				'phone'      => '01234567890',
			),
			array( '%s', '%s', '%s', '%s' )
		);
		$this->customer_id = (int) $wpdb->insert_id;
		return $this->customer_id;
	}

	/**
	 * Create a booking (for blocking slots).
	 *
	 * @param int    $staff_id Staff ID.
	 * @param string $date     Y-m-d.
	 * @param string $start_time Start time.
	 * @param string $end_time   End time.
	 * @param string $status     confirmed, pending, etc.
	 * @return int Booking ID.
	 */
	private function create_booking( $staff_id, $date, $start_time, $end_time, $status = 'confirmed' ) {
		global $wpdb;
		$service_id  = $this->create_service( array( 'duration' => 60 ) );
		$customer_id = $this->get_customer_id();
		$wpdb->insert(
			$wpdb->prefix . 'bookings',
			array(
				'customer_id'   => $customer_id,
				'service_id'    => $service_id,
				'staff_id'      => $staff_id,
				'booking_date'   => $date,
				'start_time'    => $start_time,
				'end_time'      => $end_time,
				'duration'      => 60,
				'status'        => $status,
				'total_price'   => 50.00,
			),
			array( '%d', '%d', '%d', '%s', '%s', '%s', '%d', '%s', '%f' )
		);
		return (int) $wpdb->insert_id;
	}

	/**
	 * Test 1: Staff working 9am-5pm, no bookings → Returns slots 9:00-16:00 (60 min service).
	 *
	 * @covers Bookit_DateTime_Model::get_available_slots
	 * @covers Bookit_DateTime_Model::get_staff_availability
	 */
	public function test_basic_availability_no_bookings() {
		$date = '2026-05-15'; // Friday (day_of_week = 5).
		$service_id = $this->create_service( array( 'duration' => 60, 'buffer_before' => 0, 'buffer_after' => 0 ) );
		$staff_id   = $this->create_staff();
		$this->link_staff_service( $staff_id, $service_id );
		$this->add_working_hours( $staff_id, 5, null, '09:00:00', '17:00:00' );

		$slots = $this->model->get_available_slots( $date, $service_id, $staff_id );

		$this->assertNotEmpty( $slots );
		$this->assertContains( '09:00:00', $slots );
		$this->assertContains( '09:15:00', $slots );
		$this->assertContains( '16:00:00', $slots );
		$this->assertNotContains( '16:15:00', $slots ); // 16:15 + 60 min = 17:15 > 17:00.
		$this->assertNotContains( '08:45:00', $slots );
	}

	/**
	 * Test 2: Existing booking 10:00-11:00 → Slot 10:00 NOT in results.
	 *
	 * @covers Bookit_DateTime_Model::get_available_slots
	 * @covers Bookit_DateTime_Model::filter_booked_slots
	 */
	public function test_existing_booking_blocks_slot() {
		$date = '2026-05-15';
		$service_id = $this->create_service( array( 'duration' => 60 ) );
		$staff_id   = $this->create_staff();
		$this->link_staff_service( $staff_id, $service_id );
		$this->add_working_hours( $staff_id, 5, null, '09:00:00', '17:00:00' );
		$this->create_booking( $staff_id, $date, '10:00:00', '11:00:00' );

		$slots = $this->model->get_available_slots( $date, $service_id, $staff_id );

		$this->assertNotContains( '10:00:00', $slots );
		$this->assertContains( '09:00:00', $slots );
		$this->assertContains( '11:00:00', $slots );
	}

	/**
	 * Test 3: Buffer times considered (total time = buffer_before + duration + buffer_after).
	 * 60-min service, 10-min buffer_before, 15-min buffer_after → last slot 15:30 OK, 15:45 exceeds end.
	 *
	 * @covers Bookit_DateTime_Model::get_available_slots
	 * @covers Bookit_DateTime_Model::generate_slots_in_range
	 */
	public function test_buffers_considered() {
		$date = '2026-05-15';
		$service_id = $this->create_service( array( 'duration' => 60, 'buffer_before' => 10, 'buffer_after' => 15 ) );
		$staff_id   = $this->create_staff();
		$this->link_staff_service( $staff_id, $service_id );
		$this->add_working_hours( $staff_id, 5, null, '09:00:00', '17:00:00' );

		$slots = $this->model->get_available_slots( $date, $service_id, $staff_id );

		$this->assertNotEmpty( $slots );
		// Total time needed: 10 + 60 + 15 = 85 min. 15:30 slot ends 16:55 (within 17:00).
		$this->assertContains( '15:30:00', $slots, '3:30pm should be available' );
		// 15:45 slot would end 17:10 (past 17:00).
		$this->assertNotContains( '15:45:00', $slots, '3:45pm should NOT be available (buffer exceeds end time)' );
	}

	/**
	 * Test 3b: Buffer + existing booking — slot overlapping booking buffer is blocked.
	 * 60-min service, 10 buffer_before, 15 buffer_after; booking 10:00–11:00 → 9:00 slot overlaps.
	 *
	 * @covers Bookit_DateTime_Model::get_available_slots
	 * @covers Bookit_DateTime_Model::filter_booked_slots
	 */
	public function test_buffers_considered_with_booking() {
		$date = '2026-05-15';
		$service_id = $this->create_service( array( 'duration' => 60, 'buffer_before' => 10, 'buffer_after' => 15 ) );
		$staff_id   = $this->create_staff();
		$this->link_staff_service( $staff_id, $service_id );
		$this->add_working_hours( $staff_id, 5, null, '09:00:00', '17:00:00' );
		$this->create_booking( $staff_id, $date, '10:00:00', '11:00:00' );

		$slots = $this->model->get_available_slots( $date, $service_id, $staff_id );

		// Total time = 85 min. Slot 9:00 runs 9:00–10:25, overlaps 10:00–11:00.
		$this->assertNotContains( '09:00:00', $slots );
		$this->assertNotContains( '10:00:00', $slots );
		$this->assertContains( '11:00:00', $slots );
	}

	/**
	 * Test 4: Staff has break 12:00-13:00 → No slots 12:00-12:45.
	 *
	 * @covers Bookit_DateTime_Model::get_available_slots
	 * @covers Bookit_DateTime_Model::generate_slots_in_range
	 */
	public function test_break_time_excluded() {
		$date = '2026-05-15';
		$service_id = $this->create_service( array( 'duration' => 60 ) );
		$staff_id   = $this->create_staff();
		$this->link_staff_service( $staff_id, $service_id );
		$this->add_working_hours( $staff_id, 5, null, '09:00:00', '17:00:00', 1, '12:00:00', '13:00:00' );

		$slots = $this->model->get_available_slots( $date, $service_id, $staff_id );

		$this->assertNotContains( '12:00:00', $slots );
		$this->assertNotContains( '12:15:00', $slots );
		$this->assertNotContains( '12:30:00', $slots );
		$this->assertNotContains( '12:45:00', $slots );
		$this->assertContains( '13:00:00', $slots );
		$this->assertContains( '11:00:00', $slots );
	}

	/**
	 * Test 5: specific_date exception overrides day_of_week pattern.
	 *
	 * @covers Bookit_DateTime_Model::get_staff_availability
	 */
	public function test_exception_overrides_pattern() {
		$date = '2026-05-15'; // Friday.
		$service_id = $this->create_service( array( 'duration' => 60 ) );
		$staff_id   = $this->create_staff();
		$this->link_staff_service( $staff_id, $service_id );
		$this->add_working_hours( $staff_id, 5, null, '09:00:00', '12:00:00' ); // Fri pattern: 9-12.
		$this->add_working_hours( $staff_id, null, $date, '14:00:00', '18:00:00' ); // Exception: 14-18.

		$slots = $this->model->get_available_slots( $date, $service_id, $staff_id );

		// Exception should apply: only 14:00-17:00 slots.
		$this->assertContains( '14:00:00', $slots );
		$this->assertContains( '17:00:00', $slots );
		$this->assertNotContains( '09:00:00', $slots );
		$this->assertNotContains( '11:00:00', $slots );
	}

	/**
	 * Test 6: Blocked day (is_working = 0) returns no slots.
	 * Uses specific_date exception to block the whole day (vacation, sick day).
	 *
	 * @covers Bookit_DateTime_Model::get_available_slots
	 */
	public function test_blocked_day_returns_no_slots() {
		$date = '2026-05-15';
		$service_id = $this->create_service( array( 'duration' => 60 ) );
		$staff_id   = $this->create_staff();
		$this->link_staff_service( $staff_id, $service_id );
		$this->add_working_hours( $staff_id, null, $date, '00:00:00', '23:59:59', 0 );

		$slots = $this->model->get_available_slots( $date, $service_id, $staff_id );

		$this->assertEmpty( $slots, 'Should return NO slots when is_working = 0' );
	}

	/**
	 * Test 7: "No Preference" (staff_id = 0) aggregates slots from all qualified staff.
	 * 3 staff: Alice 9–5 (booked 10–11), Bob 10–6, Charlie 8–4 → union of slots.
	 *
	 * @covers Bookit_DateTime_Model::get_available_slots
	 * @covers Bookit_DateTime_Model::get_no_preference_slots
	 */
	public function test_no_preference_aggregates_staff() {
		$date = '2026-05-15';
		$service_id = $this->create_service( array( 'duration' => 60 ) );
		$staff_a = $this->create_staff( array( 'first_name' => 'Alice' ) );
		$staff_b = $this->create_staff( array( 'first_name' => 'Bob' ) );
		$staff_c = $this->create_staff( array( 'first_name' => 'Charlie' ) );
		$this->link_staff_service( $staff_a, $service_id );
		$this->link_staff_service( $staff_b, $service_id );
		$this->link_staff_service( $staff_c, $service_id );

		$this->add_working_hours( $staff_a, 5, null, '09:00:00', '17:00:00' );
		$this->create_booking( $staff_a, $date, '10:00:00', '11:00:00' );

		$this->add_working_hours( $staff_b, 5, null, '10:00:00', '18:00:00' );
		$this->add_working_hours( $staff_c, 5, null, '08:00:00', '16:00:00' );

		$slots = $this->model->get_available_slots( $date, $service_id, 0 );

		$this->assertNotEmpty( $slots );
		$this->assertContains( '09:00:00', $slots, '9:00am should show (Alice & Charlie available)' );
		$this->assertContains( '10:00:00', $slots, '10:00am should show (Bob & Charlie available)' );
		$this->assertContains( '11:00:00', $slots, '11:00am should show (all available)' );
		$this->assertContains( '15:00:00', $slots, '3:00pm should show (Alice & Bob available)' );
		$this->assertContains( '17:00:00', $slots, '5:00pm should show (Bob available until 6pm)' );
		$slots_unique = array_unique( $slots );
		$this->assertCount( count( $slots_unique ), $slots, 'Slots should be unique and sorted' );
	}

	/**
	 * Test 8: Today at 2pm → Slots before 2:30pm excluded (30-min buffer).
	 *
	 * @covers Bookit_DateTime_Model::filter_past_slots
	 */
	public function test_past_slots_filtered_for_today() {
		$today = date( 'Y-m-d' );
		$service_id = $this->create_service( array( 'duration' => 60 ) );
		$staff_id   = $this->create_staff();
		$this->link_staff_service( $staff_id, $service_id );
		$this->add_working_hours( $staff_id, (int) date( 'N', strtotime( $today ) ), null, '00:00:00', '23:59:59' );

		$slots = $this->model->get_available_slots( $today, $service_id, $staff_id );

		$this->assertIsArray( $slots );
		$cutoff_ts   = time() + ( 30 * 60 );
		$cutoff_time = date( 'H:i:s', $cutoff_ts );
		foreach ( $slots as $slot ) {
			$this->assertGreaterThanOrEqual( $cutoff_time, $slot, "Slot {$slot} should be >= cutoff {$cutoff_time} when date is today" );
		}
	}

	/**
	 * Test 9: Slot fits with buffer at end of working hours.
	 * 60-min service + 10-min buffer_after = 70 min; working until 18:00 → 16:45 OK, 17:00 exceeds.
	 *
	 * @covers Bookit_DateTime_Model::get_available_slots
	 * @covers Bookit_DateTime_Model::generate_slots_in_range
	 */
	public function test_slot_fits_with_buffer() {
		$date = '2026-05-15';
		$service_id = $this->create_service( array( 'duration' => 60, 'buffer_before' => 0, 'buffer_after' => 10 ) );
		$staff_id   = $this->create_staff();
		$this->link_staff_service( $staff_id, $service_id );
		$this->add_working_hours( $staff_id, 5, null, '09:00:00', '18:00:00' );

		$slots = $this->model->get_available_slots( $date, $service_id, $staff_id );

		// 16:45 slot: service 16:45–17:45, buffer 17:45–17:55 (within 18:00).
		$this->assertContains( '16:45:00', $slots, '4:45pm should be available (ends at 5:55pm with buffer)' );
		// 17:00 slot: service 17:00–18:00, buffer 18:00–18:10 (exceeds 18:00).
		$this->assertNotContains( '17:00:00', $slots, '5:00pm should NOT be available (buffer exceeds end time)' );
	}

	/**
	 * Test 10a: Split shift via day_of_week (9–12, 14–18) → Slots in both periods.
	 *
	 * @covers Bookit_DateTime_Model::get_available_slots
	 * @covers Bookit_DateTime_Model::get_staff_availability
	 */
	public function test_split_shift_working_hours() {
		$date = '2026-05-15';
		$service_id = $this->create_service( array( 'duration' => 60 ) );
		$staff_id   = $this->create_staff();
		$this->link_staff_service( $staff_id, $service_id );
		$this->add_working_hours( $staff_id, 5, null, '09:00:00', '12:00:00' );
		$this->add_working_hours( $staff_id, 5, null, '14:00:00', '18:00:00' );

		$slots = $this->model->get_available_slots( $date, $service_id, $staff_id );

		$this->assertContains( '09:00:00', $slots );
		$this->assertContains( '11:00:00', $slots );
		$this->assertNotContains( '12:00:00', $slots ); // 12:00 + 60 = 13:00, outside first block.
		$this->assertContains( '14:00:00', $slots );
		$this->assertContains( '17:00:00', $slots );
	}

	/**
	 * Test 10b: Split shift with specific_date afternoon only (exception overrides pattern).
	 * Pattern: 9–12; exception for date: 14–18 → only afternoon slots (exception takes priority).
	 *
	 * @covers Bookit_DateTime_Model::get_available_slots
	 * @covers Bookit_DateTime_Model::get_staff_availability
	 */
	public function test_split_shift_exception_overrides_pattern() {
		$date = '2026-05-15';
		$service_id = $this->create_service( array( 'duration' => 60 ) );
		$staff_id   = $this->create_staff();
		$this->link_staff_service( $staff_id, $service_id );
		$this->add_working_hours( $staff_id, 5, null, '09:00:00', '12:00:00' );
		$this->add_working_hours( $staff_id, null, $date, '14:00:00', '18:00:00' );

		$slots = $this->model->get_available_slots( $date, $service_id, $staff_id );

		$this->assertNotEmpty( $slots );
		$this->assertContains( '14:00:00', $slots, '2:00pm should be available' );
		$this->assertContains( '17:00:00', $slots, '5:00pm should be available (ends at 6pm)' );
		$this->assertNotContains( '09:00:00', $slots, '9:00am should NOT be available (exception overrides)' );
	}
}
