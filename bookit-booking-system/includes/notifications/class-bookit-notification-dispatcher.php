<?php
/**
 * Notification dispatch and queue processing.
 *
 * @package Bookit_Booking_System
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Bookit_Notification_Dispatcher {

	/**
	 * Convenience wrapper around bookit_enqueue_email().
	 *
	 * @param string $email_type Email type.
	 * @param array  $recipient Recipient payload.
	 * @param string $subject Subject.
	 * @param string $html_body HTML body.
	 * @param int    $booking_id Booking ID.
	 * @param array  $params Provider params.
	 * @param int    $delay_seconds Delay in seconds.
	 * @return int|false
	 */
	public static function enqueue_email(
		string $email_type,
		array $recipient,
		string $subject,
		string $html_body,
		int $booking_id = 0,
		array $params = array(),
		int $delay_seconds = 0
	): int|false {
		return bookit_enqueue_email( $email_type, $recipient, $subject, $html_body, $booking_id, $params, $delay_seconds );
	}

	/**
	 * Process a single queue item.
	 *
	 * @param int $queue_id Queue ID.
	 * @return void
	 */
	public static function process_email_queue_item( int $queue_id ): void {
		Bookit_Email_Queue::rescue_stuck_processing();

		$row = Bookit_Email_Queue::get_row( $queue_id );
		if ( null === $row || 'pending' !== (string) $row['status'] ) {
			return;
		}

		// Per-minute rate limiter.
		$rate_key   = 'bookit_email_rate_' . gmdate( 'YmdHi' );
		$rate_count = (int) get_transient( $rate_key );
		$rate_cap   = (int) self::get_setting( 'email_rate_limit_per_minute', 30 );

		if ( $rate_count >= $rate_cap ) {
			// Cap reached, push to start of next minute without consuming a retry.
			$next_minute_ts = (int) ( ceil( time() / 60 ) * 60 );
			Bookit_Email_Queue::update_status(
				$queue_id,
				'pending',
				array(
					'scheduled_at' => gmdate( 'Y-m-d H:i:s', $next_minute_ts ),
				)
			);
			self::schedule_queue_processing( $queue_id, $next_minute_ts );
			return;
		}
		set_transient( $rate_key, $rate_count + 1, 90 );

		Bookit_Email_Queue::update_status( $queue_id, 'processing' );

		$provider  = self::resolve_email_provider();
		$recipient = array(
			'email' => (string) $row['recipient_email'],
			'name'  => (string) $row['recipient_name'],
		);
		$params    = json_decode( (string) ( $row['params'] ?? '[]' ), true );
		if ( ! is_array( $params ) ) {
			$params = array();
		}
		$params['email_type'] = (string) ( $row['email_type'] ?? '' );

		$result = $provider->send(
			$recipient,
			(string) $row['subject'],
			(string) $row['html_body'],
			$params
		);

		if ( true === $result ) {
			Bookit_Email_Queue::update_status(
				$queue_id,
				'sent',
				array(
					'sent_at' => gmdate( 'Y-m-d H:i:s' ),
				)
			);
			return;
		}

		if ( is_wp_error( $result ) ) {
			self::handle_send_failure( $queue_id, $row, $result );
		}
	}

	/**
	 * Handle queue send failure with retry/backoff semantics.
	 *
	 * @param int      $queue_id Queue ID.
	 * @param array    $row      Queue row.
	 * @param WP_Error $error    Failure error.
	 * @return void
	 */
	private static function handle_send_failure( int $queue_id, array $row, \WP_Error $error ): void {
		$now = time();

		if ( 'brevo_rate_limited' === $error->get_error_code() ) {
			$next_ts = $now + 60;
			Bookit_Email_Queue::update_status(
				$queue_id,
				'pending',
				array(
					'scheduled_at' => gmdate( 'Y-m-d H:i:s', $next_ts ),
				)
			);
			self::schedule_queue_processing( $queue_id, $next_ts );
			return;
		}

		$current_attempts = isset( $row['attempts'] ) ? (int) $row['attempts'] : 0;
		$max_attempts     = isset( $row['max_attempts'] ) ? (int) $row['max_attempts'] : 3;
		$attempts         = $current_attempts + 1;

		if ( $attempts < $max_attempts ) {
			$delay_map = array(
				1 => 300,
				2 => 1800,
				3 => 7200,
			);
			$delay   = $delay_map[ $attempts ] ?? 7200;
			$next_ts = $now + $delay;

			Bookit_Email_Queue::update_status(
				$queue_id,
				'pending',
				array(
					'attempts'     => $attempts,
					'scheduled_at' => gmdate( 'Y-m-d H:i:s', $next_ts ),
					'last_error'   => $error->get_error_message(),
				)
			);
			self::schedule_queue_processing( $queue_id, $next_ts );
			return;
		}

		Bookit_Email_Queue::update_status(
			$queue_id,
			'failed',
			array(
				'attempts'   => $attempts,
				'last_error' => $error->get_error_message(),
			)
		);

		do_action(
			'bookit_email_permanently_failed',
			$queue_id,
			(int) ( $row['booking_id'] ?? 0 ),
			(string) ( $row['email_type'] ?? '' ),
			$error->get_error_message()
		);
	}

	/**
	 * Resolve active email provider from settings.
	 *
	 * @return Bookit_Email_Provider_Interface
	 */
	public static function resolve_email_provider(): Bookit_Email_Provider_Interface {
		$provider_slug = (string) self::get_setting( 'email_provider', '' );

		if ( 'brevo' === $provider_slug ) {
			$brevo_provider = new Bookit_Brevo_Email_Provider();
			if ( $brevo_provider->is_configured() ) {
				return $brevo_provider;
			}
		}

		return new Bookit_WP_Mail_Fallback_Provider();
	}

	/**
	 * Resolve active SMS provider from settings.
	 *
	 * @return Bookit_SMS_Provider_Interface|null
	 */
	public static function resolve_sms_provider(): ?Bookit_SMS_Provider_Interface {
		$provider_slug = (string) self::get_setting( 'sms_provider', '' );

		if ( 'brevo' === $provider_slug ) {
			return new Bookit_Brevo_SMS_Provider();
		}

		return null;
	}

	/**
	 * Schedule queue item processing via Action Scheduler or WP-Cron.
	 *
	 * @param int $queue_id Queue ID.
	 * @param int $timestamp Timestamp to schedule.
	 * @return void
	 */
	private static function schedule_queue_processing( int $queue_id, int $timestamp ): void {
		if ( function_exists( 'as_schedule_single_action' ) ) {
			as_schedule_single_action(
				$timestamp,
				'bookit_process_email_queue',
				array( 'queue_id' => $queue_id ),
				'bookit-notifications'
			);
			return;
		}

		wp_schedule_single_event( $timestamp, 'bookit_process_email_queue', array( $queue_id ) );
	}

	/**
	 * Read a single value from wp_bookings_settings.
	 *
	 * @param string $key     Setting key.
	 * @param mixed  $default Default value if not found.
	 * @return mixed
	 */
	private static function get_setting( string $key, mixed $default = '' ): mixed {
		global $wpdb;
		$value = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT setting_value FROM {$wpdb->prefix}bookings_settings WHERE setting_key = %s LIMIT 1",
				$key
			)
		);
		return ( null !== $value && '' !== $value ) ? $value : $default;
	}
}
