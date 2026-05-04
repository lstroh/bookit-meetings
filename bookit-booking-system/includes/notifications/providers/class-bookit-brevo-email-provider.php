<?php
/**
 * Brevo transactional email provider.
 *
 * Uses the getbrevo/brevo-php v4 SDK to send transactional emails.
 * Request shape: \Brevo\TransactionalEmails\Requests\SendTransacEmailRequest
 * (see vendor/getbrevo/brevo-php/src/TransactionalEmails/Requests/SendTransacEmailRequest.php).
 *
 * @package Bookit_Booking_System
 * @since   1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Bookit_Brevo_Email_Provider implements Bookit_Email_Provider_Interface {

	/**
	 * {@inheritdoc}
	 */
	public function get_name(): string {
		return 'Brevo';
	}

	/**
	 * {@inheritdoc}
	 */
	public function get_slug(): string {
		return 'brevo';
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

	/**
	 * Map notification email_type to a Brevo template ID setting (wp_bookings_settings).
	 *
	 * @param string $email_type Internal email type slug.
	 * @return int Positive template ID, or 0 when unset / invalid.
	 */
	private function get_template_id_for_email_type( string $email_type ): int {
		$map = array(
			'customer_confirmation'           => 'brevo_template_booking_confirmed',
			'booking_confirmed'               => 'brevo_template_booking_confirmed',
			'booking_cancelled'               => 'brevo_template_booking_cancelled',
			'booking_rescheduled'             => 'brevo_template_booking_rescheduled',
			'magic_link_cancel'               => 'brevo_template_magic_link_cancel',
			'magic_link_reschedule'             => 'brevo_template_magic_link_reschedule',
			'business_notification'           => 'brevo_template_business_notification',
			'staff_new_booking_immediate'     => 'brevo_template_staff_new_booking',
			'staff_reschedule_immediate'      => 'brevo_template_staff_reschedule',
			'staff_cancellation_immediate'    => 'brevo_template_staff_cancellation',
			'staff_reassigned_to_immediate'   => 'brevo_template_staff_reassigned_to',
			'staff_reassigned_away_immediate' => 'brevo_template_staff_reassigned_away',
			'staff_daily_digest'              => 'brevo_template_staff_daily_digest',
			'staff_weekly_digest'             => 'brevo_template_staff_weekly_digest',
			'staff_daily_schedule'            => 'brevo_template_staff_daily_schedule',
		);

		$setting_key = $map[ $email_type ] ?? '';
		if ( empty( $setting_key ) ) {
			return 0;
		}

		$value = self::get_setting( $setting_key );
		$id    = (int) $value;
		return $id > 0 ? $id : 0;
	}

	/**
	 * {@inheritdoc}
	 *
	 * Returns true when brevo_api_key is set in wp_bookings_settings.
	 */
	public function is_configured(): bool {
		$api_key = self::get_setting( 'brevo_api_key', '' );
		return ! empty( trim( (string) $api_key ) );
	}

	/**
	 * {@inheritdoc}
	 *
	 * Sends via Brevo TransactionalEmailsClient (getbrevo/brevo-php v4).
	 *
	 * @throws nothing all exceptions are caught and returned as WP_Error.
	 */
	public function send( array $to, string $subject, string $html_body, array $params = [] ): bool|\WP_Error {
		$api_key    = self::get_setting( 'brevo_api_key', '' );
		$from_name  = self::get_setting( 'brevo_from_name', get_bloginfo( 'name' ) );
		$from_email = self::get_setting( 'brevo_from_email', get_option( 'admin_email' ) );

		if ( empty( trim( (string) $api_key ) ) ) {
			return new \WP_Error(
				'brevo_not_configured',
				__( 'Brevo API key is not configured.', 'booking-system' )
			);
		}

		$email_type = (string) ( $params['email_type'] ?? '' );

		$template_id = ! empty( $params['template_id'] )
			? (int) $params['template_id']
			: $this->get_template_id_for_email_type( $email_type );

		if ( $template_id <= 0 ) {
			$template_id = 0;
		}

		// Build template params — strip internal dispatcher keys before forwarding.
		$template_params = $params;
		unset( $template_params['email_type'] );
		unset( $template_params['template_id'] );

		$request_values = array(
			'sender' => new \Brevo\TransactionalEmails\Types\SendTransacEmailRequestSender(
				array(
					'email' => sanitize_email( (string) $from_email ),
					'name'  => sanitize_text_field( (string) $from_name ),
				)
			),
			'to'     => array(
				new \Brevo\TransactionalEmails\Types\SendTransacEmailRequestToItem(
					array(
						'email' => sanitize_email( (string) ( $to['email'] ?? '' ) ),
						'name'  => sanitize_text_field( (string) ( $to['name'] ?? '' ) ),
					)
				),
			),
		);

		if ( $template_id > 0 ) {
			$request_values['templateId'] = $template_id;
			if ( ! empty( $template_params ) ) {
				$request_values['params'] = $template_params;
			}
		} else {
			$request_values['subject']     = $subject;
			$request_values['htmlContent'] = $html_body;
		}

		$request = new \Brevo\TransactionalEmails\Requests\SendTransacEmailRequest( $request_values );

		return $this->invoke_brevo_send( (string) $api_key, $request );
	}

	/**
	 * Perform the Brevo API send (extracted for tests).
	 *
	 * @param string                                                                      $api_key API key.
	 * @param \Brevo\TransactionalEmails\Requests\SendTransacEmailRequest $request Request payload.
	 * @return bool|\WP_Error
	 */
	protected function invoke_brevo_send( string $api_key, \Brevo\TransactionalEmails\Requests\SendTransacEmailRequest $request ): bool|\WP_Error {
		try {
			$brevo = new \Brevo\Brevo( $api_key );

			$brevo->transactionalEmails->sendTransacEmail( $request );

			return true;

		} catch ( \Brevo\Exceptions\BrevoApiException $e ) {
			// Distinguish rate-limit responses so the dispatcher can retry.
			if ( 429 === $e->getCode() ) {
				return new \WP_Error(
					'brevo_rate_limited',
					__( 'Brevo rate limit reached (429). Will retry shortly.', 'booking-system' )
				);
			}

			return new \WP_Error(
				'brevo_send_failed',
				sprintf(
					/* translators: %s: exception message */
					__( 'Brevo send failed: %s', 'booking-system' ),
					$e->getMessage()
				)
			);
		} catch ( \Brevo\Exceptions\BrevoException $e ) {
			return new \WP_Error(
				'brevo_send_failed',
				sprintf(
					/* translators: %s: exception message */
					__( 'Brevo send failed: %s', 'booking-system' ),
					$e->getMessage()
				)
			);
		} catch ( \Throwable $e ) {
			return new \WP_Error(
				'brevo_send_failed',
				sprintf(
					/* translators: %s: exception message */
					__( 'Brevo send failed: %s', 'booking-system' ),
					$e->getMessage()
				)
			);
		}
	}
}
