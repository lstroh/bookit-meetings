<?php
/**
 * Staff Daily Digest Cron Job
 *
 * Drains the staff notification digest queue for staff who opted into daily digests.
 *
 * @package    Bookit_Booking_System
 * @subpackage Bookit_Booking_System/includes/cron
 */

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
	die;
}

class Bookit_Staff_Digest_Daily {

	const CRON_HOOK = 'bookit_staff_digest_daily';

	public static function init(): void {
		add_action( self::CRON_HOOK, array( __CLASS__, 'run_digest_with_tracking' ) );
	}

	public static function register_cron(): void {
		if ( wp_next_scheduled( self::CRON_HOOK ) ) {
			return;
		}
		$timezone  = get_option( 'timezone_string' ) ?: 'Europe/London';
		$send_time = self::get_setting( 'staff_digest_send_time' ) ?: '18:00';
		try {
			$dt = new DateTime( 'today ' . $send_time, new DateTimeZone( $timezone ) );
			// If today's send time has already passed, schedule for tomorrow.
			if ( $dt->getTimestamp() <= time() ) {
				$dt->modify( '+1 day' );
			}
			$timestamp = $dt->getTimestamp();
		} catch ( Exception $e ) {
			$timestamp = strtotime( 'today 18:00:00' ) + DAY_IN_SECONDS;
		}
		wp_schedule_event( $timestamp, 'daily', self::CRON_HOOK );
	}

	public static function unregister_cron(): void {
		$timestamp = wp_next_scheduled( self::CRON_HOOK );

		if ( $timestamp ) {
			wp_unschedule_event( $timestamp, self::CRON_HOOK );
		}
	}

	public static function run_digest_with_tracking(): void {
		$count = self::run_digest();

		update_option(
			'bookit_staff_digest_daily_last_run',
			gmdate( 'Y-m-d H:i:s' ),
			false
		);

		update_option(
			'bookit_staff_digest_daily_last_count',
			$count,
			false
		);
	}

	public static function run_digest(): int {
		global $wpdb;

		$staff_ids = $wpdb->get_col(
			"SELECT DISTINCT staff_id
			FROM {$wpdb->prefix}bookit_notification_digest_queue
			WHERE processed = 0"
		); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.DirectDatabaseQuery.DirectQuery

		if ( empty( $staff_ids ) ) {
			return 0;
		}

		$sent_count = 0;
		$today      = wp_date( 'j M Y', time(), wp_timezone() );

		foreach ( $staff_ids as $staff_id_raw ) {
			$staff_id = (int) $staff_id_raw;

			$staff = $wpdb->get_row(
				$wpdb->prepare(
					"SELECT id, email, first_name, last_name, notification_preferences, is_active, deleted_at
					FROM {$wpdb->prefix}bookings_staff
					WHERE id = %d",
					$staff_id
				),
				ARRAY_A
			);

			if ( ! is_array( $staff ) ) {
				continue;
			}

			if ( ! (int) $staff['is_active'] || null !== $staff['deleted_at'] ) {
				continue;
			}

			$email = (string) ( $staff['email'] ?? '' );
			if ( '' === trim( $email ) ) {
				continue;
			}

			$preferences = self::parse_staff_preferences( (string) ( $staff['notification_preferences'] ?? '' ) );

			$rows = $wpdb->get_results(
				$wpdb->prepare(
					"SELECT id, event_type, booking_id
					FROM {$wpdb->prefix}bookit_notification_digest_queue
					WHERE staff_id = %d AND processed = 0",
					$staff_id
				),
				ARRAY_A
			);

			if ( empty( $rows ) ) {
				continue;
			}

			$row_ids    = array();
			$booking_ids = array();
			$event_map  = array(); // booking_id => event_type.

			foreach ( $rows as $row ) {
				$event_type = (string) ( $row['event_type'] ?? '' );
				$frequency  = $preferences[ $event_type ] ?? 'immediate';
				if ( 'daily' !== $frequency ) {
					continue;
				}

				$row_id     = (int) ( $row['id'] ?? 0 );
				$booking_id = (int) ( $row['booking_id'] ?? 0 );

				if ( $row_id <= 0 || $booking_id <= 0 ) {
					continue;
				}

				$row_ids[]               = $row_id;
				$booking_ids[]           = $booking_id;
				$event_map[ $booking_id ] = $event_type;
			}

			$row_ids     = array_values( array_unique( array_filter( $row_ids ) ) );
			$booking_ids = array_values( array_unique( array_filter( $booking_ids ) ) );

			if ( empty( $row_ids ) || empty( $booking_ids ) ) {
				continue;
			}

			// Mark before enqueue — prevents double-send if enqueue fails.
			$placeholders = implode( ',', array_fill( 0, count( $row_ids ), '%d' ) );
			$wpdb->query(
				$wpdb->prepare(
					"UPDATE {$wpdb->prefix}bookit_notification_digest_queue
					SET processed = 1
					WHERE id IN ({$placeholders})",
					...$row_ids
				)
			); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery

			$booking_placeholders = implode( ',', array_fill( 0, count( $booking_ids ), '%d' ) );
			$bookings             = $wpdb->get_results(
				$wpdb->prepare(
					"SELECT b.*,
							s.name AS service_name,
							c.first_name AS customer_first_name,
							c.last_name AS customer_last_name
					FROM {$wpdb->prefix}bookings b
					JOIN {$wpdb->prefix}bookings_services s ON b.service_id = s.id
					JOIN {$wpdb->prefix}bookings_customers c ON b.customer_id = c.id
					WHERE b.id IN ({$booking_placeholders})
						AND b.status != 'cancelled'
						AND b.deleted_at IS NULL",
					...$booking_ids
				),
				ARRAY_A
			);

			if ( empty( $bookings ) ) {
				continue;
			}

			$grouped = array(
				'new_booking'  => array(),
				'reschedule'   => array(),
				'cancellation' => array(),
			);

			foreach ( $bookings as $booking ) {
				$booking_id = (int) ( $booking['id'] ?? 0 );
				if ( $booking_id <= 0 ) {
					continue;
				}

				$event_type = (string) ( $event_map[ $booking_id ] ?? '' );
				if ( ! isset( $grouped[ $event_type ] ) ) {
					continue;
				}

				$grouped[ $event_type ][] = $booking;
			}

			$total_items = count( $grouped['new_booking'] ) + count( $grouped['reschedule'] ) + count( $grouped['cancellation'] );
			if ( $total_items <= 0 ) {
				continue;
			}

			$recipient = array(
				'email' => sanitize_email( $email ),
				'name'  => trim( (string) ( $staff['first_name'] ?? '' ) . ' ' . (string) ( $staff['last_name'] ?? '' ) ),
			);

			$subject   = sprintf( 'Daily digest: %d booking update(s) for %s', $total_items, $today );
			$html_body = self::build_digest_body( $grouped, $total_items, 'daily' );

			Bookit_Notification_Dispatcher::enqueue_email(
				'staff_daily_digest',
				$recipient,
				$subject,
				$html_body,
				0,
				array()
			);

			++$sent_count;
		}

		return $sent_count;
	}

	private static function parse_staff_preferences( string $raw ): array {
		$defaults = array(
			'new_booking'    => 'immediate',
			'reschedule'     => 'immediate',
			'cancellation'   => 'immediate',
			'daily_schedule' => false,
		);

		if ( '' === trim( $raw ) ) {
			return $defaults;
		}

		$parsed = json_decode( $raw, true );
		return is_array( $parsed ) ? array_merge( $defaults, $parsed ) : $defaults;
	}

	private static function build_digest_body( array $grouped, int $total_items, string $frequency_label ): string {
		$site_url        = get_site_url();
		$dashboard_url   = $site_url . '/bookit-dashboard/app/bookings';
		$preferences_url = $site_url . '/bookit-dashboard/app/profile';

		$sections = array(
			'new_booking'  => 'New Bookings',
			'reschedule'   => 'Rescheduled',
			'cancellation' => 'Cancelled',
		);

		$lines   = array();
		$lines[] = '<p>Here’s your ' . esc_html( $frequency_label ) . ' digest (' . (int) $total_items . ' update(s)).</p>';

		foreach ( $sections as $event_type => $title ) {
			$items = $grouped[ $event_type ] ?? array();
			if ( empty( $items ) ) {
				continue;
			}

			$lines[] = '<h3>' . esc_html( $title ) . '</h3>';
			$lines[] = '<ul>';
			foreach ( $items as $booking ) {
				$customer = trim( (string) ( $booking['customer_first_name'] ?? '' ) . ' ' . (string) ( $booking['customer_last_name'] ?? '' ) );
				$service  = (string) ( $booking['service_name'] ?? '' );
				$date     = (string) ( $booking['booking_date'] ?? '' );
				$time     = (string) ( $booking['start_time'] ?? '' );

				$item = esc_html( $customer );
				if ( '' !== $service ) {
					$item .= ' — ' . esc_html( $service );
				}
				if ( '' !== $date ) {
					$item .= ' — ' . esc_html( $date );
				}
				if ( '' !== $time ) {
					$item .= ' ' . esc_html( substr( $time, 0, 5 ) );
				}

				$lines[] = '<li>' . $item . '</li>';
			}
			$lines[] = '</ul>';
		}

		$lines[] = '<p><a href="' . esc_url( $dashboard_url ) . '">View in dashboard</a></p>';
		$lines[] = '<p>You\'re receiving this because you\'re set to ' . esc_html( $frequency_label ) . ' digest notifications. <a href="' . esc_url( $preferences_url ) . '">Change your preferences</a></p>';

		return implode( "\n", $lines );
	}

	private static function get_setting( string $key, string $default = '' ): string {
		global $wpdb;
		$value = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT setting_value FROM {$wpdb->prefix}bookings_settings WHERE setting_key = %s LIMIT 1",
				$key
			)
		);
		return ( null !== $value && '' !== $value ) ? (string) $value : $default;
	}
}

