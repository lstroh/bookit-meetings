<?php
/**
 * Date/Time model for booking wizard.
 *
 * @package    Bookit_Booking_System
 * @subpackage Bookit_Booking_System/includes/models
 */

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
	die;
}

/**
 * DateTime model class.
 */
class Bookit_DateTime_Model {

	/**
	 * UK bank holidays 2026 (hardcoded for Phase 1).
	 *
	 * @var array<string>
	 */
	private static $uk_bank_holidays_2026 = array(
		'2026-01-01', // New Year's Day
		'2026-04-03', // Good Friday
		'2026-04-06', // Easter Monday
		'2026-05-04', // Early May bank holiday
		'2026-05-25', // Spring bank holiday
		'2026-08-31', // Summer bank holiday
		'2026-12-25', // Christmas Day
		'2026-12-28', // Boxing Day (substitute)
	);

	/**
	 * Generate time slots for a given date (15-minute increments).
	 * Phase 1 (legacy): Return all slots 00:00-23:45. Use get_available_slots for real availability.
	 *
	 * @param string $date       Date in Y-m-d format.
	 * @param int    $service_id Service ID (for future availability check).
	 * @param int    $staff_id   Staff ID or 0 for "No Preference".
	 * @return array<int, string> Array of time slots ['09:00:00', '09:15:00', ...].
	 */
	public function generate_time_slots( $date, $service_id, $staff_id ) {
		return $this->get_available_slots( $date, $service_id, $staff_id );
	}

	/**
	 * Get available time slots for a date (real-time availability).
	 * Filters by staff working hours, existing bookings, service duration + buffers.
	 *
	 * @param string   $date               Date in Y-m-d format.
	 * @param int      $service_id         Service ID.
	 * @param int      $staff_id           Staff ID or 0 for "No Preference".
	 * @param int|null $exclude_booking_id Booking ID to exclude from conflict check (for edits).
	 * @return array<int, string> Available time slots ['09:00:00', '09:15:00', ...].
	 */
	public function get_available_slots( $date, $service_id, $staff_id, $exclude_booking_id = null ) {
		global $wpdb;

		$service = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT duration, buffer_before, buffer_after FROM {$wpdb->prefix}bookings_services WHERE id = %d AND is_active = 1",
				$service_id
			),
			ARRAY_A
		);

		if ( ! $service ) {
			return array();
		}

		$duration        = (int) $service['duration'];
		$buffer_before   = (int) ( isset( $service['buffer_before'] ) ? $service['buffer_before'] : 0 );
		$buffer_after     = (int) ( isset( $service['buffer_after'] ) ? $service['buffer_after'] : 0 );
		$total_time_needed = $buffer_before + $duration + $buffer_after;

		if ( 0 === (int) $staff_id ) {
			return $this->get_no_preference_slots( $date, $service_id, $total_time_needed );
		}

		return $this->get_staff_availability( (int) $staff_id, $date, $total_time_needed, $exclude_booking_id );
	}

	/**
	 * Get availability for "No Preference" (aggregate across all qualified staff).
	 *
	 * @param string $date               Date in Y-m-d format.
	 * @param int    $service_id         Service ID.
	 * @param int    $total_time_needed  Total minutes (buffer_before + duration + buffer_after).
	 * @return array<int, string> Available time slots.
	 */
	private function get_no_preference_slots( $date, $service_id, $total_time_needed ) {
		global $wpdb;

		$staff_ids = $wpdb->get_col(
			$wpdb->prepare(
				"SELECT DISTINCT ss.staff_id
				FROM {$wpdb->prefix}bookings_staff_services ss
				INNER JOIN {$wpdb->prefix}bookings_staff s ON ss.staff_id = s.id
				WHERE ss.service_id = %d
				  AND s.is_active = 1
				  AND ( s.deleted_at IS NULL OR s.deleted_at = '0000-00-00 00:00:00' )",
				$service_id
			)
		);

		if ( empty( $staff_ids ) || ! is_array( $staff_ids ) ) {
			return array();
		}

		$all_slots = array();
		foreach ( $staff_ids as $sid ) {
			$staff_slots = $this->get_staff_availability( (int) $sid, $date, $total_time_needed );
			$all_slots   = array_merge( $all_slots, $staff_slots );
		}

		$all_slots = array_unique( $all_slots );
		sort( $all_slots );

		return array_values( $all_slots );
	}

	/**
	 * Get availability for a single staff member.
	 *
	 * @param int      $staff_id           Staff ID.
	 * @param string   $date               Date in Y-m-d format.
	 * @param int      $total_time_needed  Total minutes needed for the slot.
	 * @param int|null $exclude_booking_id Booking ID to exclude from conflict check (for edits).
	 * @return array<int, string> Available time slots.
	 */
	private function get_staff_availability( $staff_id, $date, $total_time_needed, $exclude_booking_id = null ) {
		global $wpdb;

		Bookit_Logger::info( 'get_staff_availability: start', array(
			'staff_id'           => $staff_id,
			'date'               => $date,
			'total_time_needed'  => $total_time_needed,
		) );

		$day_of_week = (int) date( 'N', strtotime( $date ) ); // 1=Monday, 7=Sunday.
		$table       = $wpdb->prefix . 'bookings_staff_working_hours';

		// Check if staff_working_hours table exists; fallback not required if migration is run.
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		if ( $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) ) !== $table ) {
			Bookit_Logger::warning( 'get_staff_availability: staff_working_hours table does not exist', array(
				'staff_id' => $staff_id,
				'date'    => $date,
				'table'   => $table,
			) );
			return array();
		}

		// Step 1: Get working hours — check specific_date first, then day_of_week.
		$working_hours = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT start_time, end_time, is_working, break_start, break_end
				FROM {$table}
				WHERE staff_id = %d AND specific_date = %s
				ORDER BY id DESC
				LIMIT 1",
				$staff_id,
				$date
			),
			ARRAY_A
		);

		$all_slots = array();

		if ( $working_hours ) {
			Bookit_Logger::info( 'get_staff_availability: using specific_date working hours', array(
				'staff_id'    => $staff_id,
				'date'        => $date,
				'start_time'  => $working_hours['start_time'],
				'end_time'    => $working_hours['end_time'],
				'is_working'  => isset( $working_hours['is_working'] ) ? (int) $working_hours['is_working'] : 1,
			) );
			$is_working = isset( $working_hours['is_working'] ) ? (int) $working_hours['is_working'] : 1;
			if ( 1 === $is_working ) {
				$all_slots = $this->generate_slots_in_range(
					$working_hours['start_time'],
					$working_hours['end_time'],
					$total_time_needed,
					isset( $working_hours['break_start'] ) ? $working_hours['break_start'] : null,
					isset( $working_hours['break_end'] ) ? $working_hours['break_end'] : null
				);
			}
		} else {
			// Day-of-week pattern: support multiple rows (split shifts).
			$patterns = $wpdb->get_results(
				$wpdb->prepare(
					"SELECT start_time, end_time, is_working, break_start, break_end
					FROM {$table}
					WHERE staff_id = %d
					  AND day_of_week = %d
					  AND ( valid_from IS NULL OR valid_from <= %s )
					  AND ( valid_until IS NULL OR valid_until >= %s )
					ORDER BY start_time ASC",
					$staff_id,
					$day_of_week,
					$date,
					$date
				),
				ARRAY_A
			);

			if ( empty( $patterns ) ) {
				Bookit_Logger::info( 'get_staff_availability: no day-of-week patterns for date', array(
					'staff_id'     => $staff_id,
					'date'         => $date,
					'day_of_week'  => $day_of_week,
				) );
				return array();
			}

			Bookit_Logger::info( 'get_staff_availability: using day-of-week patterns', array(
				'staff_id'     => $staff_id,
				'date'         => $date,
				'day_of_week'  => $day_of_week,
				'pattern_count' => count( $patterns ),
			) );

			foreach ( $patterns as $row ) {
				$is_working = isset( $row['is_working'] ) ? (int) $row['is_working'] : 1;
				if ( 0 === $is_working ) {
					continue;
				}
				$period_slots = $this->generate_slots_in_range(
					$row['start_time'],
					$row['end_time'],
					$total_time_needed,
					isset( $row['break_start'] ) ? $row['break_start'] : null,
					isset( $row['break_end'] ) ? $row['break_end'] : null
				);
				$all_slots = array_merge( $all_slots, $period_slots );
			}
			$all_slots = array_unique( $all_slots );
			sort( $all_slots );
		}

		if ( empty( $all_slots ) ) {
			Bookit_Logger::info( 'get_staff_availability: no slots from working hours', array(
				'staff_id' => $staff_id,
				'date'    => $date,
			) );
			return array();
		}

		// Build query to get booked slots, excluding current booking if editing.
		if ( $exclude_booking_id ) {
			// Exclude the booking being edited from conflict check.
			// Flush wpdb cache to ensure we read fresh booking data.
			$wpdb->flush();
			$existing_bookings = $wpdb->get_results(
				$wpdb->prepare(
					"SELECT start_time, end_time
					FROM {$wpdb->prefix}bookings
					WHERE staff_id = %d
					  AND booking_date = %s
					  AND id != %d
					  AND status NOT IN ('cancelled')
					  AND ( deleted_at IS NULL OR deleted_at = '0000-00-00 00:00:00' )",
					$staff_id,
					$date,
					$exclude_booking_id
				),
				ARRAY_A
			);
		} else {
			// Normal query without exclusion (for new bookings).
			// Flush wpdb cache to ensure we read fresh booking data.
			$wpdb->flush();
			$existing_bookings = $wpdb->get_results(
				$wpdb->prepare(
					"SELECT start_time, end_time
					FROM {$wpdb->prefix}bookings
					WHERE staff_id = %d
					  AND booking_date = %s
					  AND status NOT IN ('cancelled')
					  AND ( deleted_at IS NULL OR deleted_at = '0000-00-00 00:00:00' )",
					$staff_id,
					$date
				),
				ARRAY_A
			);
		}

		$existing_bookings = $existing_bookings ? $existing_bookings : array();
		Bookit_Logger::info( 'get_staff_availability: existing bookings count', array(
			'staff_id'           => $staff_id,
			'date'               => $date,
			'slots_before_filter' => count( $all_slots ),
			'existing_bookings'  => count( $existing_bookings ),
		) );

		$available_slots = $this->filter_booked_slots( $all_slots, $existing_bookings, $total_time_needed, $date );

		if ( $date === gmdate( 'Y-m-d' ) ) {
			$available_slots = $this->filter_past_slots( $available_slots, $date );
		}

		Bookit_Logger::info( 'get_staff_availability: done', array(
			'staff_id'        => $staff_id,
			'date'            => $date,
			'available_count' => count( $available_slots ),
		) );

		$slots = array_values( $available_slots );

		// Allow extensions to modify available slots (e.g. recurring or class booking constraints).
		$slots = apply_filters( 'bookit_available_slots', $slots, (int) $staff_id, $date, 0 );

		return $slots;
	}

	/**
	 * Generate time slots in range (15-minute increments), respecting break.
	 *
	 * @param string      $start_time       Start time (H:i:s).
	 * @param string      $end_time         End time (H:i:s).
	 * @param int         $duration_needed  Total minutes needed per slot.
	 * @param string|null $break_start      Break start (H:i:s) or null.
	 * @param string|null $break_end        Break end (H:i:s) or null.
	 * @return array<int, string> Time slot strings.
	 */
	private function generate_slots_in_range( $start_time, $end_time, $duration_needed, $break_start = null, $break_end = null ) {
		$slots   = array();
		$current = strtotime( $start_time );
		$end     = strtotime( $end_time );
		$seconds_needed = $duration_needed * 60;

		while ( $current + $seconds_needed <= $end ) {
			$slot_start = $current;
			$slot_end   = $current + $seconds_needed;

			if ( $break_start && $break_end ) {
				$break_start_ts = strtotime( $break_start );
				$break_end_ts   = strtotime( $break_end );
				if ( ! ( $slot_end <= $break_start_ts || $slot_start >= $break_end_ts ) ) {
					$current += 15 * 60;
					continue;
				}
			}

			$slots[] = date( 'H:i:s', $current );
			$current += 15 * 60;
		}

		return $slots;
	}

	/**
	 * Filter out slots that overlap with existing bookings.
	 *
	 * @param array<int, string> $slots              Slot start times (H:i:s).
	 * @param array<int, array>   $existing_bookings  Rows with start_time, end_time.
	 * @param int                 $duration_needed   Slot length in minutes.
	 * @param string              $date              Date prefix (Y-m-d) to ensure correct timestamps for future dates.
	 * @return array<int, string> Available slots.
	 */
	private function filter_booked_slots( $slots, $existing_bookings, $duration_needed, $date = '' ) {
		$available = array();

		foreach ( $slots as $slot ) {
			$prefix     = $date ? $date . ' ' : '';
			$slot_start = strtotime( $prefix . $slot );
			$slot_end   = $slot_start + ( $duration_needed * 60 );
			$is_available = true;

			foreach ( $existing_bookings as $booking ) {
				$b_start = strtotime( $prefix . $booking['start_time'] );
				$b_end   = strtotime( $prefix . $booking['end_time'] );
				if ( ! ( $slot_end <= $b_start || $slot_start >= $b_end ) ) {
					$is_available = false;
					break;
				}
			}

			if ( $is_available ) {
				$available[] = $slot;
			}
		}

		return $available;
	}

	/**
	 * Filter out past time slots (when date is today).
	 *
	 * @param array<int, string> $slots Slot start times (H:i:s).
	 * @param string             $date Date in Y-m-d format (used to build slot timestamp).
	 * @return array<int, string> Slots after cutoff (now + 30 minutes).
	 */
	private function filter_past_slots( $slots, $date ) {
		$now    = time();
		$cutoff = $now + ( 30 * 60 );

		return array_values( array_filter( $slots, function ( $slot ) use ( $date, $cutoff ) {
			$slot_ts = strtotime( $date . ' ' . $slot );
			return $slot_ts >= $cutoff;
		} ) );
	}

	/**
	 * Check if date is a UK bank holiday.
	 * Phase 1: Hardcoded 2026 dates.
	 *
	 * @param string $date Date in Y-m-d format.
	 * @return bool True if bank holiday.
	 */
	public function is_bank_holiday( $date ) {
		return in_array( $date, self::$uk_bank_holidays_2026, true );
	}

	/**
	 * Check if date is in the past.
	 *
	 * @param string $date Date in Y-m-d format.
	 * @return bool True if past date.
	 */
	public function is_past_date( $date ) {
		return strtotime( $date ) < strtotime( date( 'Y-m-d' ) );
	}

	/**
	 * Format time for display (24h → 12h with AM/PM).
	 *
	 * @param string $time Time in H:i:s format (e.g., '14:00:00').
	 * @return string Formatted time (e.g., '2:00 PM').
	 */
	public function format_time_display( $time ) {
		return date( 'g:i A', strtotime( $time ) );
	}

	/**
	 * Group time slots by period (Morning/Afternoon/Evening).
	 *
	 * @param array<int, string> $time_slots Array of time strings.
	 * @return array<string, array<int, string>> ['morning' => [...], 'afternoon' => [...], 'evening' => [...]].
	 */
	public function group_time_slots( $time_slots ) {
		$grouped = array(
			'morning'   => array(), // 00:00 - 11:59
			'afternoon' => array(), // 12:00 - 16:59
			'evening'   => array(), // 17:00 - 23:59
		);

		foreach ( $time_slots as $time ) {
			$hour = (int) date( 'H', strtotime( $time ) );

			if ( $hour < 12 ) {
				$grouped['morning'][] = $time;
			} elseif ( $hour < 17 ) {
				$grouped['afternoon'][] = $time;
			} else {
				$grouped['evening'][] = $time;
			}
		}

		return $grouped;
	}
}
