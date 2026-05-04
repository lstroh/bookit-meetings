<?php
/**
 * Notification helper functions.
 *
 * @package Bookit_Booking_System
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Enqueue an email for deferred processing.
 *
 * @param string $email_type     Email type slug.
 * @param array  $recipient      Recipient payload.
 * @param string $subject        Email subject.
 * @param string $html_body      Email body.
 * @param int    $booking_id     Optional booking ID.
 * @param array  $params         Optional provider params.
 * @param int    $delay_seconds  Delay before processing.
 * @return int|false
 */
function bookit_enqueue_email(
	string $email_type,
	array $recipient,
	string $subject,
	string $html_body,
	int $booking_id = 0,
	array $params = array(),
	int $delay_seconds = 0
): int|false {
	$scheduled_at = $delay_seconds > 0
		? gmdate( 'Y-m-d H:i:s', time() + $delay_seconds )
		: gmdate( 'Y-m-d H:i:s' );

	$queue_id = Bookit_Email_Queue::insert(
		array(
			'booking_id'      => 0 === $booking_id ? null : $booking_id,
			'email_type'      => $email_type,
			'recipient_email' => (string) ( $recipient['email'] ?? '' ),
			'recipient_name'  => (string) ( $recipient['name'] ?? '' ),
			'subject'         => $subject,
			'html_body'       => $html_body,
			'params'          => wp_json_encode( $params ),
			'scheduled_at'    => $scheduled_at,
		)
	);

	if ( false === $queue_id ) {
		return false;
	}

	$timestamp = time() + ( $delay_seconds > 0 ? $delay_seconds : 1 );

	if ( function_exists( 'as_schedule_single_action' ) ) {
		as_schedule_single_action(
			$timestamp,
			'bookit_process_email_queue',
			array( 'queue_id' => $queue_id ),
			'bookit-notifications'
		);
	} else {
		wp_schedule_single_event( $timestamp, 'bookit_process_email_queue', array( $queue_id ) );
	}

	return $queue_id;
}

/**
 * Enqueue a Google Calendar sync job for async processing.
 *
 * @param string   $operation           'create', 'update', or 'delete'.
 * @param int      $booking_id          Booking ID.
 * @param int|null $calendar_staff_id   Staff ID whose Google OAuth should run the job (fallback admin when assigned staff has no calendar). Null lets the processor use the booking’s staff_id only.
 * @return void
 */
function bookit_enqueue_calendar_sync( string $operation, int $booking_id, ?int $calendar_staff_id = null ): void {
	$booking_id = absint( $booking_id );
	if ( $booking_id < 1 ) {
		return;
	}

	if ( function_exists( 'as_schedule_single_action' ) ) {
		as_schedule_single_action(
			time() + 1,
			'bookit_process_calendar_sync',
			array( $operation, $booking_id, $calendar_staff_id ),
			'bookit-calendar'
		);
	} else {
		wp_schedule_single_event(
			time() + 1,
			'bookit_process_calendar_sync',
			array( $operation, $booking_id, $calendar_staff_id )
		);
	}

	/**
	 * Fires after a calendar sync job is queued (including Action Scheduler and WP-Cron).
	 *
	 * @param string   $operation           Operation name.
	 * @param int      $booking_id          Booking ID.
	 * @param int|null $calendar_staff_id   OAuth staff override, if any.
	 */
	do_action( 'bookit_calendar_sync_enqueued', $operation, $booking_id, $calendar_staff_id );
}
