<?php
/**
 * Staff notification routing (immediate vs digest).
 *
 * @package Bookit_Booking_System
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Bookit_Staff_Notifier {

	public static function init(): void {
		add_action( 'bookit_after_booking_created', array( __CLASS__, 'on_booking_created' ), 10, 2 );
		add_action( 'bookit_booking_rescheduled', array( __CLASS__, 'on_booking_rescheduled' ), 10, 2 );
		add_action( 'bookit_after_booking_cancelled', array( __CLASS__, 'on_booking_cancelled' ), 10, 2 );
		add_action( 'bookit_booking_reassigned', array( __CLASS__, 'on_booking_reassigned' ), 10, 4 );
	}

	public static function on_booking_created( int $booking_id, array $booking_data ): void {
		$booking = self::get_full_booking( $booking_id );
		if ( null === $booking ) {
			return;
		}

		$assigned_staff_id = (int) $booking['staff_id'];
		$admin_staff       = self::get_admin_staff();

		$staff_ids = array( $assigned_staff_id );
		foreach ( $admin_staff as $admin ) {
			$staff_ids[] = (int) $admin['id'];
		}
		$staff_ids = array_unique( $staff_ids );

		foreach ( $staff_ids as $staff_id ) {
			self::notify_staff( (int) $staff_id, 'new_booking', 'staff_new_booking_immediate', $booking, $booking_data );
		}
	}

	public static function on_booking_rescheduled( int $booking_id, array $booking_data ): void {
		$booking = self::get_full_booking( $booking_id );
		if ( null === $booking ) {
			return;
		}

		$assigned_staff_id = (int) $booking['staff_id'];
		$admin_staff       = self::get_admin_staff();

		$staff_ids = array( $assigned_staff_id );
		foreach ( $admin_staff as $admin ) {
			$staff_ids[] = (int) $admin['id'];
		}
		$staff_ids = array_unique( $staff_ids );

		foreach ( $staff_ids as $staff_id ) {
			self::notify_staff( (int) $staff_id, 'reschedule', 'staff_reschedule_immediate', $booking, $booking_data );
		}
	}

	public static function on_booking_cancelled( int $booking_id, array $booking_data ): void {
		$booking = self::get_full_booking( $booking_id );
		if ( null === $booking ) {
			return;
		}

		$assigned_staff_id = (int) $booking['staff_id'];
		$admin_staff       = self::get_admin_staff();

		$staff_ids = array( $assigned_staff_id );
		foreach ( $admin_staff as $admin ) {
			$staff_ids[] = (int) $admin['id'];
		}
		$staff_ids = array_unique( $staff_ids );

		foreach ( $staff_ids as $staff_id ) {
			self::notify_staff( (int) $staff_id, 'cancellation', 'staff_cancellation_immediate', $booking, $booking_data );
		}
	}

	public static function on_booking_reassigned( int $booking_id, int $old_staff_id, int $new_staff_id, array $booking_data ): void {
		if ( $old_staff_id === $new_staff_id ) {
			return;
		}

		$booking = self::get_full_booking( $booking_id );
		if ( null === $booking ) {
			return;
		}

		$admin_staff = self::get_admin_staff();

		// New assignee + admins.
		$new_group = array( $new_staff_id );
		foreach ( $admin_staff as $admin ) {
			$new_group[] = (int) $admin['id'];
		}
		$new_group = array_unique( $new_group );

		foreach ( $new_group as $staff_id ) {
			self::notify_staff( (int) $staff_id, 'new_booking', 'staff_reassigned_to_immediate', $booking, $booking_data );
		}

		// Old assignee only.
		$old_group = array_unique( array( $old_staff_id ) );
		foreach ( $old_group as $staff_id ) {
			self::notify_staff( (int) $staff_id, 'cancellation', 'staff_reassigned_away_immediate', $booking, $booking_data );
		}
	}

	private static function notify_staff( int $staff_id, string $event_type, string $email_type, array $booking_full, array $booking_data ): void {
		global $wpdb;

		$staff = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT id, email, first_name, last_name, notification_preferences, is_active, deleted_at
				FROM {$wpdb->prefix}bookings_staff
				WHERE id = %d",
				$staff_id
			),
			ARRAY_A
		);

		if ( ! $staff ) {
			return;
		}

		if ( ! (int) $staff['is_active'] || null !== $staff['deleted_at'] ) {
			return;
		}

		if ( empty( $staff['email'] ) ) {
			Bookit_Audit_Logger::log(
				'staff_notification.skipped_no_email',
				'staff',
				$staff_id,
				array()
			);
			return;
		}

		$prefs     = self::get_staff_preferences( $staff_id );
		$frequency = $prefs[ $event_type ] ?? 'immediate';

		if ( 'immediate' === $frequency ) {
			$recipient = array(
				'email' => sanitize_email( $staff['email'] ),
				'name'  => trim( $staff['first_name'] . ' ' . $staff['last_name'] ),
			);
			$subject   = self::build_subject( $email_type, $booking_full );
			$html_body = self::build_html_body( $email_type, $booking_full, $staff_id );

			$params = array(
				'service_name'      => (string) ( $booking_full['service_name'] ?? '' ),
				'booking_date'      => (string) ( $booking_full['booking_date'] ?? '' ),
				'start_time'        => (string) ( $booking_full['start_time'] ?? '' ),
				'customer_first'    => (string) ( $booking_full['customer_first_name'] ?? '' ),
				'customer_last'     => (string) ( $booking_full['customer_last_name'] ?? '' ),
				'customer_phone'    => (string) ( $booking_full['customer_phone'] ?? '' ),
				'booking_reference' => (string) ( $booking_full['booking_reference'] ?? '' ),
				'dashboard_url'     => home_url( '/bookit-dashboard/app/bookings' ),
				'preferences_url'   => home_url( '/bookit-dashboard/app/profile' ),
			);

			Bookit_Notification_Dispatcher::enqueue_email(
				$email_type,
				$recipient,
				$subject,
				$html_body,
				(int) $booking_full['id'],
				$params
			);
		} elseif ( in_array( $frequency, array( 'daily', 'weekly' ), true ) ) {
			$digest_event = $event_type;
			self::insert_digest_queue( $staff_id, $digest_event, (int) $booking_full['id'] );
		}
	}

	private static function get_admin_staff(): array {
		global $wpdb;
		return $wpdb->get_results(
			"SELECT id, email, first_name, last_name, notification_preferences
			FROM {$wpdb->prefix}bookings_staff
			WHERE role = 'admin'
				AND is_active = 1
				AND deleted_at IS NULL
				AND email != ''",
			ARRAY_A
		);
	}

	private static function get_staff_preferences( int $staff_id ): array {
		global $wpdb;
		$raw = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT notification_preferences FROM {$wpdb->prefix}bookings_staff WHERE id = %d",
				$staff_id
			)
		);
		$defaults = array(
			'new_booking'    => 'immediate',
			'reschedule'     => 'immediate',
			'cancellation'   => 'immediate',
			'daily_schedule' => false,
		);
		if ( empty( $raw ) ) {
			return $defaults;
		}
		$parsed = json_decode( $raw, true );
		return is_array( $parsed ) ? array_merge( $defaults, $parsed ) : $defaults;
	}

	private static function get_full_booking( int $booking_id ): ?array {
		global $wpdb;
		$row = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT b.*,
						c.first_name  AS customer_first_name,
						c.last_name   AS customer_last_name,
						c.email       AS customer_email,
						c.phone       AS customer_phone,
						s.name        AS service_name,
						st.first_name AS staff_first_name,
						st.last_name  AS staff_last_name
				FROM {$wpdb->prefix}bookings b
				INNER JOIN {$wpdb->prefix}bookings_customers c  ON b.customer_id  = c.id
				INNER JOIN {$wpdb->prefix}bookings_services  s  ON b.service_id   = s.id
				INNER JOIN {$wpdb->prefix}bookings_staff     st ON b.staff_id     = st.id
				WHERE b.id = %d",
				$booking_id
			),
			ARRAY_A
		);
		return $row ?: null;
	}

	private static function build_subject( string $email_type, array $booking ): string {
		$customer = sanitize_text_field(
			trim( (string) ( $booking['customer_first_name'] ?? '' ) . ' ' . (string) ( $booking['customer_last_name'] ?? '' ) )
		);
		$service = sanitize_text_field( (string) ( $booking['service_name'] ?? 'Service' ) );
		$date    = sanitize_text_field( self::format_booking_date( (string) ( $booking['booking_date'] ?? '' ) ) );
		$time    = sanitize_text_field( self::format_booking_time( (string) ( $booking['start_time'] ?? '' ) ) );

		switch ( $email_type ) {
			case 'staff_new_booking_immediate':
				return sprintf( 'New booking: %s — %s on %s at %s', $customer, $service, $date, $time );
			case 'staff_reschedule_immediate':
				return sprintf( 'Booking rescheduled: %s — %s on %s at %s', $customer, $service, $date, $time );
			case 'staff_cancellation_immediate':
				return sprintf( 'Booking cancelled: %s — %s on %s at %s', $customer, $service, $date, $time );
			case 'staff_reassigned_to_immediate':
				return sprintf( 'Booking assigned to you: %s — %s on %s at %s', $customer, $service, $date, $time );
			case 'staff_reassigned_away_immediate':
				return sprintf( 'Booking reassigned away: %s — %s on %s at %s', $customer, $service, $date, $time );
			default:
				return sprintf( 'Booking update: %s — %s on %s at %s', $customer, $service, $date, $time );
		}
	}

	private static function build_html_body( string $email_type, array $booking, int $staff_id = 0 ): string {
		$customer = esc_html( trim( (string) ( $booking['customer_first_name'] ?? '' ) . ' ' . (string) ( $booking['customer_last_name'] ?? '' ) ) );
		$service  = esc_html( (string) ( $booking['service_name'] ?? '' ) );
		$date_raw = (string) ( $booking['booking_date'] ?? '' );
		$date     = esc_html( self::format_booking_date( $date_raw ) );
		$time     = esc_html( self::format_booking_time( (string) ( $booking['start_time'] ?? '' ) ) );

		$ref = '';
		if ( ! empty( $booking['booking_reference'] ) ) {
			$ref = (string) $booking['booking_reference'];
		} elseif ( ! empty( $booking['id'] ) ) {
			$ref = (string) $booking['id'];
		}
		$ref = esc_html( $ref );

		$site_url        = get_site_url();
		$dashboard_url   = add_query_arg( 'date', $date_raw, $site_url . '/bookit-dashboard/app/bookings' );
		$preferences_url = $site_url . '/bookit-dashboard/app/profile';

		$intro = 'There’s an update to a booking.';
		switch ( $email_type ) {
			case 'staff_new_booking_immediate':
				$intro = 'A new booking was created.';
				break;
			case 'staff_reschedule_immediate':
				$intro = 'A booking was rescheduled.';
				break;
			case 'staff_cancellation_immediate':
				$intro = 'A booking was cancelled.';
				break;
			case 'staff_reassigned_to_immediate':
				$intro = 'A booking was reassigned to you.';
				break;
			case 'staff_reassigned_away_immediate':
				$intro = 'A booking was reassigned away from you.';
				break;
		}

		ob_start();
		echo '<p>' . esc_html( $intro ) . "</p>\n";
		echo '<p><strong>Customer:</strong> ' . $customer . '<br />' .
			'<strong>Service:</strong> ' . $service . '<br />' .
			'<strong>Date:</strong> ' . $date . '<br />' .
			'<strong>Time:</strong> ' . $time . '<br />' .
			'<strong>Reference:</strong> ' . $ref . "</p>\n";

		$bookit_staff_email_meeting_html = apply_filters(
			'bookit_staff_email_meeting_section',
			'',
			$booking,
			$staff_id
		);
		if ( '' !== $bookit_staff_email_meeting_html ) {
			// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			echo wp_kses_post( $bookit_staff_email_meeting_html );
			echo "\n";
		}

		echo '<p><a href="' . esc_url( $dashboard_url ) . '">View in dashboard</a></p>' . "\n";
		echo '<p>You\'re receiving this because you\'re set to immediate notifications. <a href="' . esc_url( $preferences_url ) . '">Change your preferences</a></p>';

		$body = ob_get_clean();

		return false !== $body ? $body : '';
	}

	private static function insert_digest_queue( int $staff_id, string $event_type, int $booking_id ): void {
		global $wpdb;
		$wpdb->insert(
			$wpdb->prefix . 'bookit_notification_digest_queue',
			array(
				'staff_id'   => $staff_id,
				'event_type' => $event_type,
				'booking_id' => $booking_id,
				'processed'  => 0,
				'created_at' => current_time( 'mysql' ),
			),
			array( '%d', '%s', '%d', '%d', '%s' )
		);
	}

	private static function format_booking_date( string $date_raw ): string {
		if ( empty( $date_raw ) ) {
			return '';
		}

		$ts = strtotime( $date_raw );
		if ( false === $ts ) {
			return $date_raw;
		}

		return wp_date( 'd M Y', $ts, wp_timezone() );
	}

	private static function format_booking_time( string $time_raw ): string {
		if ( empty( $time_raw ) ) {
			return '';
		}

		$ts = strtotime( '1970-01-01 ' . $time_raw );
		if ( false === $ts ) {
			return $time_raw;
		}

		return wp_date( 'H:i', $ts, wp_timezone() );
	}
}

