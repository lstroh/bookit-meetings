<?php
/**
 * Staff Daily Schedule Cron Job
 *
 * Sends opted-in staff a summary of today's bookings each morning.
 *
 * @package    Bookit_Booking_System
 * @subpackage Bookit_Booking_System/includes/cron
 */

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
	die;
}

class Bookit_Staff_Schedule_Daily {

	const CRON_HOOK = 'bookit_staff_schedule_daily';

	public static function init(): void {
		add_action( self::CRON_HOOK, array( __CLASS__, 'run_schedule_with_tracking' ) );
	}

	public static function register_cron(): void {
		if ( wp_next_scheduled( self::CRON_HOOK ) ) {
			return;
		}
		$timezone  = get_option( 'timezone_string' ) ?: 'Europe/London';
		$send_time = self::get_setting( 'staff_schedule_send_time' ) ?: '08:00';
		try {
			$dt = new DateTime( 'today ' . $send_time, new DateTimeZone( $timezone ) );
			// If today's send time has already passed, schedule for tomorrow.
			if ( $dt->getTimestamp() <= time() ) {
				$dt->modify( '+1 day' );
			}
			$timestamp = $dt->getTimestamp();
		} catch ( Exception $e ) {
			$timestamp = strtotime( 'today 08:00:00' ) + DAY_IN_SECONDS;
		}
		wp_schedule_event( $timestamp, 'daily', self::CRON_HOOK );
	}

	public static function unregister_cron(): void {
		$timestamp = wp_next_scheduled( self::CRON_HOOK );

		if ( $timestamp ) {
			wp_unschedule_event( $timestamp, self::CRON_HOOK );
		}
	}

	public static function run_schedule_with_tracking(): void {
		$count = self::run_schedule();

		update_option(
			'bookit_staff_schedule_daily_last_run',
			gmdate( 'Y-m-d H:i:s' ),
			false
		);

		update_option(
			'bookit_staff_schedule_daily_last_count',
			$count,
			false
		);
	}

	public static function run_schedule(): int {
		global $wpdb;

		$staff_rows = $wpdb->get_results(
			"SELECT id, email, first_name, last_name, notification_preferences
			FROM {$wpdb->prefix}bookings_staff
			WHERE is_active = 1
				AND deleted_at IS NULL
				AND email != ''",
			ARRAY_A
		); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.DirectDatabaseQuery.DirectQuery

		if ( empty( $staff_rows ) ) {
			return 0;
		}

		$today_raw  = gmdate( 'Y-m-d' );
		$today_human = wp_date( 'j M Y', strtotime( $today_raw ), wp_timezone() );

		$sent_count = 0;

		foreach ( $staff_rows as $staff ) {
			$staff_id = (int) ( $staff['id'] ?? 0 );
			if ( $staff_id <= 0 ) {
				continue;
			}

			$prefs = self::parse_staff_preferences( (string) ( $staff['notification_preferences'] ?? '' ) );
			if ( true !== ( $prefs['daily_schedule'] ?? false ) ) {
				continue;
			}

			$bookings = $wpdb->get_results(
				$wpdb->prepare(
					"SELECT b.*, s.name as service_name,
						c.first_name as customer_first_name, c.last_name as customer_last_name
					FROM {$wpdb->prefix}bookings b
					JOIN {$wpdb->prefix}bookings_services s ON b.service_id = s.id
					JOIN {$wpdb->prefix}bookings_customers c ON b.customer_id = c.id
					WHERE b.staff_id = %d
						AND b.booking_date = %s
						AND b.status IN ('confirmed','pending_payment')
						AND b.deleted_at IS NULL
					ORDER BY b.start_time ASC",
					$staff_id,
					$today_raw
				),
				ARRAY_A
			);

			if ( empty( $bookings ) ) {
				continue;
			}

			$email = (string) ( $staff['email'] ?? '' );
			if ( '' === trim( $email ) ) {
				continue;
			}

			$recipient = array(
				'email' => sanitize_email( $email ),
				'name'  => trim( (string) ( $staff['first_name'] ?? '' ) . ' ' . (string) ( $staff['last_name'] ?? '' ) ),
			);

			$subject   = sprintf( 'Your schedule for today, %s', $today_human );
			$html_body = self::build_schedule_body( $bookings, $today_raw );

			Bookit_Notification_Dispatcher::enqueue_email(
				'staff_daily_schedule',
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

	private static function build_schedule_body( array $bookings, string $today_raw ): string {
		$site_url      = get_site_url();
		$dashboard_url = add_query_arg( 'date', $today_raw, $site_url . '/bookit-dashboard/app/bookings' );

		$lines   = array();
		$lines[] = '<p>Here’s your schedule for today.</p>';
		$lines[] = '<ul>';

		foreach ( $bookings as $booking ) {
			$time     = (string) ( $booking['start_time'] ?? '' );
			$time     = '' !== $time ? substr( $time, 0, 5 ) : '';
			$customer = trim( (string) ( $booking['customer_first_name'] ?? '' ) . ' ' . (string) ( $booking['customer_last_name'] ?? '' ) );
			$service  = (string) ( $booking['service_name'] ?? '' );

			$item = '';
			if ( '' !== $time ) {
				$item .= esc_html( $time ) . ' — ';
			}
			$item .= esc_html( $customer );
			if ( '' !== $service ) {
				$item .= ' — ' . esc_html( $service );
			}

			$lines[] = '<li>' . $item . '</li>';
		}

		$lines[] = '</ul>';
		$lines[] = '<p><a href="' . esc_url( $dashboard_url ) . '">View in dashboard</a></p>';

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

