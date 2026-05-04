<?php
/**
 * DateTime Model Tests
 *
 * Tests core date/time logic: slot generation, validation, formatting
 *
 * @package    Bookit_Booking_System
 * @subpackage Tests
 */

/**
 * Test Bookit_DateTime_Model class.
 */
class Test_DateTime_Model extends WP_UnitTestCase {

	/**
	 * DateTime model instance.
	 *
	 * @var Bookit_DateTime_Model
	 */
	private $model;

	/**
	 * Set up each test.
	 */
	public function setUp(): void {
		parent::setUp();
		$this->model = new Bookit_DateTime_Model();
	}

	/**
	 * Test 1: generate_time_slots returns array (Phase 2: real availability; empty when no working hours)
	 *
	 * @covers Bookit_DateTime_Model::generate_time_slots
	 * @covers Bookit_DateTime_Model::get_available_slots
	 */
	public function test_generate_time_slots_returns_array() {
		$date       = '2026-05-15';
		$service_id = 1;
		$staff_id   = 1;

		$slots = $this->model->generate_time_slots( $date, $service_id, $staff_id );

		$this->assertIsArray( $slots );
		// With no staff_working_hours or no service, slots are empty (Phase 2 behavior).
		// Full availability logic is tested in Test_Availability_Algorithm.
	}

	/**
	 * Test 2: get_available_slots returns empty for invalid service
	 *
	 * @covers Bookit_DateTime_Model::get_available_slots
	 */
	public function test_get_available_slots_returns_empty_for_invalid_service() {
		$slots = $this->model->get_available_slots( '2026-05-15', 99999, 1 );
		$this->assertSame( array(), $slots );
	}

	/**
	 * Test 3: is_bank_holiday returns true for 2026 UK holidays
	 *
	 * @covers Bookit_DateTime_Model::is_bank_holiday
	 */
	public function test_is_bank_holiday_detects_2026_holidays() {
		// Test each 2026 UK bank holiday
		$holidays = array(
			'2026-01-01', // New Year's Day
			'2026-04-03', // Good Friday
			'2026-04-06', // Easter Monday
			'2026-05-04', // Early May bank holiday
			'2026-05-25', // Spring bank holiday
			'2026-08-31', // Summer bank holiday
			'2026-12-25', // Christmas Day
			'2026-12-28', // Boxing Day (substitute)
		);

		foreach ( $holidays as $holiday ) {
			$result = $this->model->is_bank_holiday( $holiday );
			$this->assertTrue( $result, "Should detect {$holiday} as bank holiday" );
		}
	}

	/**
	 * Test 4: is_bank_holiday returns false for non-holidays
	 *
	 * @covers Bookit_DateTime_Model::is_bank_holiday
	 */
	public function test_is_bank_holiday_returns_false_for_normal_dates() {
		$normal_dates = array(
			'2026-01-02',
			'2026-05-15',
			'2026-07-20',
			'2026-11-10',
		);

		foreach ( $normal_dates as $date ) {
			$result = $this->model->is_bank_holiday( $date );
			$this->assertFalse( $result, "Should NOT detect {$date} as bank holiday" );
		}
	}

	/**
	 * Test 5: is_past_date returns true for dates before today
	 *
	 * @covers Bookit_DateTime_Model::is_past_date
	 */
	public function test_is_past_date_detects_past_dates() {
		$yesterday  = date( 'Y-m-d', strtotime( '-1 day' ) );
		$last_week  = date( 'Y-m-d', strtotime( '-7 days' ) );
		$last_year  = date( 'Y-m-d', strtotime( '-1 year' ) );

		$this->assertTrue( $this->model->is_past_date( $yesterday ), 'Yesterday should be past' );
		$this->assertTrue( $this->model->is_past_date( $last_week ), 'Last week should be past' );
		$this->assertTrue( $this->model->is_past_date( $last_year ), 'Last year should be past' );
	}

	/**
	 * Test 6: is_past_date returns false for today and future
	 *
	 * @covers Bookit_DateTime_Model::is_past_date
	 */
	public function test_is_past_date_returns_false_for_today_and_future() {
		$today      = date( 'Y-m-d' );
		$tomorrow   = date( 'Y-m-d', strtotime( '+1 day' ) );
		$next_month = date( 'Y-m-d', strtotime( '+1 month' ) );

		$this->assertFalse( $this->model->is_past_date( $today ), 'Today should NOT be past' );
		$this->assertFalse( $this->model->is_past_date( $tomorrow ), 'Tomorrow should NOT be past' );
		$this->assertFalse( $this->model->is_past_date( $next_month ), 'Next month should NOT be past' );
	}

	/**
	 * Test 7: format_time_display converts 24h to 12h with AM/PM
	 *
	 * @covers Bookit_DateTime_Model::format_time_display
	 */
	public function test_format_time_display_converts_24h_to_12h() {
		// Morning times
		$this->assertEquals( '12:00 AM', $this->model->format_time_display( '00:00:00' ) );
		$this->assertEquals( '9:00 AM', $this->model->format_time_display( '09:00:00' ) );
		$this->assertEquals( '11:45 AM', $this->model->format_time_display( '11:45:00' ) );

		// Afternoon/Evening times
		$this->assertEquals( '12:00 PM', $this->model->format_time_display( '12:00:00' ) );
		$this->assertEquals( '2:00 PM', $this->model->format_time_display( '14:00:00' ) );
		$this->assertEquals( '5:30 PM', $this->model->format_time_display( '17:30:00' ) );
		$this->assertEquals( '11:45 PM', $this->model->format_time_display( '23:45:00' ) );
	}

	/**
	 * Test 8: group_time_slots correctly assigns periods
	 *
	 * @covers Bookit_DateTime_Model::group_time_slots
	 */
	public function test_group_time_slots_assigns_correct_periods() {
		// Sample time slots
		$slots = array(
			'00:00:00', // Morning
			'09:00:00', // Morning
			'11:59:00', // Morning (last morning slot)
			'12:00:00', // Afternoon
			'14:00:00', // Afternoon
			'16:59:00', // Afternoon (last afternoon slot)
			'17:00:00', // Evening
			'20:00:00', // Evening
			'23:45:00', // Evening
		);

		$grouped = $this->model->group_time_slots( $slots );

		// Assert structure
		$this->assertArrayHasKey( 'morning', $grouped );
		$this->assertArrayHasKey( 'afternoon', $grouped );
		$this->assertArrayHasKey( 'evening', $grouped );

		// Assert morning slots (00:00 - 11:59)
		$this->assertContains( '00:00:00', $grouped['morning'] );
		$this->assertContains( '09:00:00', $grouped['morning'] );
		$this->assertContains( '11:59:00', $grouped['morning'] );
		$this->assertCount( 3, $grouped['morning'] );

		// Assert afternoon slots (12:00 - 16:59)
		$this->assertContains( '12:00:00', $grouped['afternoon'] );
		$this->assertContains( '14:00:00', $grouped['afternoon'] );
		$this->assertContains( '16:59:00', $grouped['afternoon'] );
		$this->assertCount( 3, $grouped['afternoon'] );

		// Assert evening slots (17:00 - 23:59)
		$this->assertContains( '17:00:00', $grouped['evening'] );
		$this->assertContains( '20:00:00', $grouped['evening'] );
		$this->assertContains( '23:45:00', $grouped['evening'] );
		$this->assertCount( 3, $grouped['evening'] );
	}

	/**
	 * Test 9: Boundary time 11:59 is morning, 12:00 is afternoon
	 *
	 * @covers Bookit_DateTime_Model::group_time_slots
	 */
	public function test_group_time_slots_handles_boundaries_correctly() {
		$slots = array( '11:59:00', '12:00:00', '16:59:00', '17:00:00' );

		$grouped = $this->model->group_time_slots( $slots );

		// 11:59 = morning
		$this->assertContains( '11:59:00', $grouped['morning'] );

		// 12:00 = afternoon
		$this->assertContains( '12:00:00', $grouped['afternoon'] );

		// 16:59 = afternoon
		$this->assertContains( '16:59:00', $grouped['afternoon'] );

		// 17:00 = evening
		$this->assertContains( '17:00:00', $grouped['evening'] );
	}

	/**
	 * Test 10: Empty slots array returns empty groups
	 *
	 * @covers Bookit_DateTime_Model::group_time_slots
	 */
	public function test_group_time_slots_handles_empty_array() {
		$grouped = $this->model->group_time_slots( array() );

		$this->assertEmpty( $grouped['morning'] );
		$this->assertEmpty( $grouped['afternoon'] );
		$this->assertEmpty( $grouped['evening'] );
	}

	/**
	 * Tear down each test.
	 */
	public function tearDown(): void {
		parent::tearDown();
	}
}
