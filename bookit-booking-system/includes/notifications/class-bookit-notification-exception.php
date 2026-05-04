<?php
/**
 * Notification exception.
 *
 * Thrown internally within the notification system to carry context
 * about which queue item and email type triggered the failure.
 *
 * @package Bookit_Booking_System
 * @since   1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Bookit_Notification_Exception extends \RuntimeException {

	/**
	 * @param string          $message    Exception message.
	 * @param string          $email_type The email type that triggered this exception.
	 * @param int             $queue_id   The queue row ID.
	 * @param int             $code       Exception code.
	 * @param \Throwable|null $previous   Previous exception.
	 */
	public function __construct(
		string $message,
		private readonly string $email_type = '',
		private readonly int $queue_id = 0,
		int $code = 0,
		?\Throwable $previous = null
	) {
		parent::__construct( $message, $code, $previous );
	}

	/**
	 * The email type associated with this exception.
	 *
	 * @return string
	 */
	public function get_email_type(): string {
		return $this->email_type;
	}

	/**
	 * The queue row ID associated with this exception.
	 *
	 * @return int
	 */
	public function get_queue_id(): int {
		return $this->queue_id;
	}
}
