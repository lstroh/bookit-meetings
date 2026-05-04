<?php
/**
 * Google Calendar sync (events) — API calls and queue processor entry point.
 *
 * @package    Bookit_Booking_System
 * @subpackage Bookit_Booking_System/includes/integrations
 */

if ( ! defined( 'WPINC' ) ) {
	die;
}

/**
 * Calendar API operations for bookings (create / update / delete events).
 */
class Bookit_Google_Calendar {

	/**
	 * Redirect URI registered with Google (must match OAuth app + class-bookit-google-calendar-api.php).
	 */
	private const OAUTH_REDIRECT_URI = 'https://test.wimbledonsmart.co.uk/wp-json/bookit/v1/google-calendar/callback';

	/**
	 * Injected client for unit tests (skips DB and token handling).
	 *
	 * @var \Google\Client|null
	 */
	private static ?\Google\Client $test_client = null;

	/**
	 * Set a mock Google client for unit tests.
	 *
	 * @param \Google\Client|null $client Client or null to clear.
	 * @return void
	 */
	public static function set_test_client( ?\Google\Client $client ): void {
		self::$test_client = $client;
	}

	/**
	 * Build and authorize a Google Client for a staff member (with optional token refresh).
	 *
	 * @param int $staff_id Staff row ID (wp_bookings_staff.id).
	 * @return \Google\Client|null
	 */
	public static function get_client_for_staff( int $staff_id ): ?\Google\Client {
		if ( null !== self::$test_client ) {
			return self::$test_client;
		}

		$staff_id = absint( $staff_id );
		if ( $staff_id < 1 ) {
			return null;
		}

		global $wpdb;
		$table = $wpdb->prefix . 'bookings_staff';

		$row = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT google_calendar_connected, google_oauth_access_token, google_oauth_refresh_token, google_oauth_token_expiry
				FROM {$table} WHERE id = %d",
				$staff_id
			),
			ARRAY_A
		);

		if ( ! is_array( $row ) ) {
			return null;
		}

		if ( 1 !== (int) ( $row['google_calendar_connected'] ?? 0 ) ) {
			return null;
		}

		$enc_access  = isset( $row['google_oauth_access_token'] ) ? (string) $row['google_oauth_access_token'] : '';
		$enc_refresh = isset( $row['google_oauth_refresh_token'] ) ? (string) $row['google_oauth_refresh_token'] : '';
		$token_expiry = isset( $row['google_oauth_token_expiry'] ) ? (string) $row['google_oauth_token_expiry'] : '';

		if ( '' === $enc_access || '' === $enc_refresh || '' === $token_expiry ) {
			return null;
		}

		$decrypted_access  = Bookit_Encryption::decrypt( $enc_access );
		$decrypted_refresh = Bookit_Encryption::decrypt( $enc_refresh );

		if ( '' === $decrypted_access || '' === $decrypted_refresh ) {
			return null;
		}

		$client = static::create_oauth_client_from_settings();
		if ( null === $client ) {
			return null;
		}

		$client->setAccessToken(
			array(
				'access_token'  => $decrypted_access,
				'refresh_token' => $decrypted_refresh,
				'expires_in'    => 3600,
				'created'       => strtotime( $token_expiry ) - 3600,
			)
		);

		if ( $client->isAccessTokenExpired() ) {
			$response = static::oauth_refresh_with_client( $client, $decrypted_refresh );

			if ( isset( $response['error'] ) ) {
				Bookit_Audit_Logger::log(
					'google_calendar.token_refresh_failed',
					'staff',
					$staff_id,
					array(
						'notes' => isset( $response['error_description'] )
							? (string) $response['error_description']
							: (string) $response['error'],
					)
				);
				return null;
			}

			$new_token = $client->getAccessToken();
			if ( ! is_array( $new_token ) || empty( $new_token['access_token'] ) ) {
				Bookit_Audit_Logger::log(
					'google_calendar.token_refresh_failed',
					'staff',
					$staff_id,
					array( 'notes' => 'missing_access_token_after_refresh' )
				);
				return null;
			}

			$new_expiry_mysql = date( 'Y-m-d H:i:s', time() + 3600 );

			$wpdb->update(
				$table,
				array(
					'google_oauth_access_token' => Bookit_Encryption::encrypt( (string) $new_token['access_token'] ),
					'google_oauth_token_expiry'   => $new_expiry_mysql,
					'updated_at'                  => current_time( 'mysql' ),
				),
				array( 'id' => $staff_id ),
				array( '%s', '%s', '%s' ),
				array( '%d' )
			);
		}

		return $client;
	}

	/**
	 * OAuth token refresh (override in tests to avoid HTTP).
	 *
	 * @param \Google\Client $client              Configured client.
	 * @param string         $plain_refresh_token Decrypted refresh token.
	 * @return array
	 */
	protected static function oauth_refresh_with_client( \Google\Client $client, string $plain_refresh_token ): array {
		$result = $client->fetchAccessTokenWithRefreshToken( $plain_refresh_token );
		return is_array( $result ) ? $result : array();
	}

	/**
	 * Instantiate Google\Client with plugin OAuth settings.
	 *
	 * @return \Google\Client|null
	 */
	protected static function create_oauth_client_from_settings(): ?\Google\Client {
		global $wpdb;

		$client_id = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT setting_value FROM {$wpdb->prefix}bookings_settings WHERE setting_key = %s",
				'google_client_id'
			)
		);
		$secret = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT setting_value FROM {$wpdb->prefix}bookings_settings WHERE setting_key = %s",
				'google_client_secret'
			)
		);

		$client_id = is_string( $client_id ) ? trim( $client_id ) : '';
		$secret    = is_string( $secret ) ? trim( $secret ) : '';

		if ( '' === $client_id || '' === $secret ) {
			return null;
		}

		$client = new \Google\Client();
		$client->setClientId( $client_id );
		$client->setClientSecret( $secret );
		$client->setRedirectUri( self::OAUTH_REDIRECT_URI );

		return $client;
	}

	/**
	 * Read business / company display name for event location.
	 *
	 * @return string
	 */
	protected static function get_company_name_setting(): string {
		global $wpdb;

		$name = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT setting_value FROM {$wpdb->prefix}bookings_settings WHERE setting_key = %s LIMIT 1",
				'business_name'
			)
		);

		return is_string( $name ) ? trim( $name ) : '';
	}

	/**
	 * Build RFC3339 start/end for a booking in the site timezone.
	 *
	 * @param array $booking Booking payload.
	 * @return array{0:string,1:string} Start and end RFC3339 strings.
	 */
	protected static function booking_to_rfc3339_range( array $booking ): array {
		$tz_string = get_option( 'timezone_string' );
		$tz        = new \DateTimeZone( is_string( $tz_string ) && '' !== $tz_string ? $tz_string : 'UTC' );

		$date       = isset( $booking['date'] ) ? (string) $booking['date'] : '';
		$start_time = isset( $booking['start_time'] ) ? (string) $booking['start_time'] : '';
		$end_time   = isset( $booking['end_time'] ) ? (string) $booking['end_time'] : '';

		$start = ( new \DateTime( $date . ' ' . $start_time, $tz ) )->format( \DateTime::RFC3339 );
		$end   = ( new \DateTime( $date . ' ' . $end_time, $tz ) )->format( \DateTime::RFC3339 );

		return array( $start, $end );
	}

	/**
	 * Build Calendar Event object from booking payload.
	 *
	 * @param array $booking Booking payload.
	 * @return \Google\Service\Calendar\Event
	 */
	protected static function build_calendar_event_from_booking( array $booking ): \Google\Service\Calendar\Event {
		$service_name    = isset( $booking['service_name'] ) ? (string) $booking['service_name'] : '';
		$customer_first  = isset( $booking['customer_first'] ) ? (string) $booking['customer_first'] : '';
		$customer_last   = isset( $booking['customer_last'] ) ? (string) $booking['customer_last'] : '';
		$customer_phone  = isset( $booking['customer_phone'] ) ? (string) $booking['customer_phone'] : '';
		$booking_ref     = isset( $booking['booking_reference'] ) ? (string) $booking['booking_reference'] : '';
		$special         = isset( $booking['special_requests'] ) ? trim( (string) $booking['special_requests'] ) : '';
		$company_name    = isset( $booking['company_name'] ) ? (string) $booking['company_name'] : '';

		list( $start_rfc, $end_rfc ) = static::booking_to_rfc3339_range( $booking );

		$tz_string = get_option( 'timezone_string' );
		$tz_id     = is_string( $tz_string ) && '' !== $tz_string ? $tz_string : 'UTC';

		$start_dt = new \Google\Service\Calendar\EventDateTime();
		$start_dt->setDateTime( $start_rfc );
		$start_dt->setTimeZone( $tz_id );

		$end_dt = new \Google\Service\Calendar\EventDateTime();
		$end_dt->setDateTime( $end_rfc );
		$end_dt->setTimeZone( $tz_id );

		$summary = trim( $service_name . ' — ' . trim( $customer_first . ' ' . $customer_last ) );

		$desc_lines = array(
			'Booking ref: ' . $booking_ref,
			'Customer: ' . trim( $customer_first . ' ' . $customer_last ),
			'Phone: ' . $customer_phone,
		);
		if ( '' !== $special ) {
			$desc_lines[] = 'Special requests: ' . $special;
		}
		$description = implode( "\n", $desc_lines );

		$event = new \Google\Service\Calendar\Event();
		$event->setSummary( $summary );
		$event->setDescription( $description );
		$event->setStart( $start_dt );
		$event->setEnd( $end_dt );
		if ( '' !== $company_name ) {
			$event->setLocation( $company_name );
		}

		$reminder = new \Google\Service\Calendar\EventReminder();
		$reminder->setMethod( 'popup' );
		$reminder->setMinutes( 15 );

		$reminders = new \Google\Service\Calendar\EventReminders();
		$reminders->setUseDefault( false );
		$reminders->setOverrides( array( $reminder ) );

		$event->setReminders( $reminders );
		$event->setColorId( '7' );

		return $event;
	}

	/**
	 * Insert event into primary calendar (override in tests).
	 *
	 * @param \Google\Service\Calendar        $service Calendar service.
	 * @param \Google\Service\Calendar\Event $event   Event.
	 * @return \Google\Service\Calendar\Event
	 */
	protected static function insert_primary_calendar_event(
		\Google\Service\Calendar $service,
		\Google\Service\Calendar\Event $event
	): \Google\Service\Calendar\Event {
		return $service->events->insert( 'primary', $event );
	}

	/**
	 * Update event on primary calendar (override in tests).
	 *
	 * @param \Google\Service\Calendar        $service  Calendar service.
	 * @param string                          $event_id Event ID.
	 * @param \Google\Service\Calendar\Event $event    Event.
	 * @return \Google\Service\Calendar\Event
	 */
	protected static function update_primary_calendar_event(
		\Google\Service\Calendar $service,
		string $event_id,
		\Google\Service\Calendar\Event $event
	): \Google\Service\Calendar\Event {
		return $service->events->update( 'primary', $event_id, $event );
	}

	/**
	 * Delete event from primary calendar (override in tests).
	 *
	 * @param \Google\Service\Calendar $service  Service.
	 * @param string                   $event_id Event ID.
	 * @return void
	 */
	protected static function delete_primary_calendar_event( \Google\Service\Calendar $service, string $event_id ): void {
		$service->events->delete( 'primary', $event_id );
	}

	/**
	 * Create a Google Calendar event for a booking.
	 *
	 * @param int   $booking_id Booking ID.
	 * @param array $booking    Booking payload.
	 * @return string|null Event ID or null on failure.
	 */
	public static function create_event( int $booking_id, array $booking ): ?string {
		try {
			$client = static::get_client_for_staff( (int) ( $booking['staff_id'] ?? 0 ) );
			if ( null === $client ) {
				return null;
			}

			$service = new \Google\Service\Calendar( $client );
			$event   = static::build_calendar_event_from_booking( $booking );

			$created = static::insert_primary_calendar_event( $service, $event );
			$event_id = $created->getId();
			if ( empty( $event_id ) ) {
				return null;
			}

			global $wpdb;
			$wpdb->update(
				$wpdb->prefix . 'bookings',
				array( 'google_calendar_event_id' => $event_id ),
				array( 'id' => $booking_id ),
				array( '%s' ),
				array( '%d' )
			);

			return (string) $event_id;
		} catch ( \Throwable $e ) {
			Bookit_Audit_Logger::log(
				'google_calendar.sync_failed',
				'booking',
				$booking_id,
				array( 'notes' => $e->getMessage() )
			);
			return null;
		}
	}

	/**
	 * Update an existing Google Calendar event, or create if missing.
	 *
	 * @param int   $booking_id Booking ID.
	 * @param array $booking    Booking payload.
	 * @return void
	 */
	public static function update_event( int $booking_id, array $booking ): void {
		global $wpdb;

		$existing = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT google_calendar_event_id FROM {$wpdb->prefix}bookings WHERE id = %d",
				$booking_id
			)
		);

		$event_id = is_string( $existing ) ? trim( $existing ) : '';
		if ( '' === $event_id ) {
			static::create_event( $booking_id, $booking );
			return;
		}

		try {
			$client = static::get_client_for_staff( (int) ( $booking['staff_id'] ?? 0 ) );
			if ( null === $client ) {
				return;
			}

			$service = new \Google\Service\Calendar( $client );
			$event   = static::build_calendar_event_from_booking( $booking );

			static::update_primary_calendar_event( $service, $event_id, $event );
		} catch ( \Throwable $e ) {
			Bookit_Audit_Logger::log(
				'google_calendar.sync_failed',
				'booking',
				$booking_id,
				array( 'notes' => $e->getMessage() )
			);
		}
	}

	/**
	 * Delete Google Calendar event for a booking and clear stored event ID.
	 *
	 * @param int $booking_id Booking ID.
	 * @return void
	 */
	public static function delete_event( int $booking_id ): void {
		global $wpdb;

		$row = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT google_calendar_event_id, staff_id FROM {$wpdb->prefix}bookings WHERE id = %d",
				$booking_id
			),
			ARRAY_A
		);

		if ( ! is_array( $row ) ) {
			return;
		}

		$event_id = isset( $row['google_calendar_event_id'] ) ? trim( (string) $row['google_calendar_event_id'] ) : '';
		if ( '' === $event_id ) {
			return;
		}

		$staff_id = (int) ( $row['staff_id'] ?? 0 );

		try {
			$client = static::get_client_for_staff( $staff_id );
			if ( null === $client ) {
				$client = static::get_client_for_calendar_delete_fallback();
			}
			if ( null === $client ) {
				return;
			}

			$service = new \Google\Service\Calendar( $client );
			static::delete_primary_calendar_event( $service, $event_id );

			$wpdb->update(
				$wpdb->prefix . 'bookings',
				array( 'google_calendar_event_id' => null ),
				array( 'id' => $booking_id ),
				array( '%s' ),
				array( '%d' )
			);
		} catch ( \Throwable $e ) {
			Bookit_Audit_Logger::log(
				'google_calendar.sync_failed',
				'booking',
				$booking_id,
				array( 'notes' => $e->getMessage() )
			);
		}
	}

	/**
	 * Load booking + related fields for calendar sync.
	 *
	 * @param int $booking_id Booking ID.
	 * @return array|null
	 */
	protected static function load_booking_for_calendar_sync( int $booking_id ): ?array {
		global $wpdb;

		$row = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT
					b.id,
					b.staff_id,
					b.booking_date,
					b.start_time,
					b.end_time,
					b.booking_reference,
					b.special_requests,
					c.first_name AS customer_first,
					c.last_name AS customer_last,
					c.phone AS customer_phone,
					s.name AS service_name
				FROM {$wpdb->prefix}bookings b
				LEFT JOIN {$wpdb->prefix}bookings_customers c ON b.customer_id = c.id
				INNER JOIN {$wpdb->prefix}bookings_services s ON b.service_id = s.id
				WHERE b.id = %d
				AND b.deleted_at IS NULL",
				$booking_id
			),
			ARRAY_A
		);

		if ( ! is_array( $row ) ) {
			return null;
		}

		$company = static::get_company_name_setting();

		return array(
			'staff_id'           => (int) ( $row['staff_id'] ?? 0 ),
			'date'               => (string) ( $row['booking_date'] ?? '' ),
			'start_time'         => (string) ( $row['start_time'] ?? '' ),
			'end_time'           => (string) ( $row['end_time'] ?? '' ),
			'service_name'       => (string) ( $row['service_name'] ?? '' ),
			'customer_first'     => (string) ( $row['customer_first'] ?? '' ),
			'customer_last'      => (string) ( $row['customer_last'] ?? '' ),
			'customer_phone'     => (string) ( $row['customer_phone'] ?? '' ),
			'booking_reference'  => (string) ( $row['booking_reference'] ?? '' ),
			'special_requests'   => isset( $row['special_requests'] ) ? (string) $row['special_requests'] : '',
			'company_name'       => $company,
		);
	}

	/**
	 * OAuth client for delete when the assigned staff has no connection but fallback is enabled (same rules as sync enqueue).
	 *
	 * @param int $assigned_staff_id Booking staff ID (already failed direct OAuth).
	 * @return \Google\Client|null
	 */
	private static function get_client_for_calendar_delete_fallback(): ?\Google\Client {
		global $wpdb;

		$fallback_raw = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT setting_value FROM {$wpdb->prefix}bookings_settings WHERE setting_key = %s",
				'google_calendar_fallback_enabled'
			)
		);

		$s = is_string( $fallback_raw ) ? strtolower( trim( $fallback_raw ) ) : (string) (int) $fallback_raw;
		if ( ! in_array( $s, array( '1', 'true', 'yes', 'on' ), true ) ) {
			return null;
		}

		$table   = $wpdb->prefix . 'bookings_staff';
		$admin_id = $wpdb->get_var(
			"SELECT id FROM {$table}
			WHERE role = 'admin'
			AND google_calendar_connected = 1
			AND deleted_at IS NULL
			AND is_active = 1
			ORDER BY id ASC
			LIMIT 1"
		);

		if ( null === $admin_id ) {
			return null;
		}

		return static::get_client_for_staff( (int) $admin_id );
	}

	/**
	 * Action Scheduler / cron entry: run one calendar sync job.
	 *
	 * @param string   $operation           create|update|delete.
	 * @param int      $booking_id          Booking ID.
	 * @param int|null $calendar_staff_id   When set, OAuth runs as this staff (e.g. fallback admin); booking row staff_id is unchanged.
	 * @return void
	 */
	public static function process_sync_job( string $operation, int $booking_id, ?int $calendar_staff_id = null ): void {
		switch ( $operation ) {
			case 'create':
				$booking = static::load_booking_for_calendar_sync( $booking_id );
				if ( null === $booking ) {
					Bookit_Audit_Logger::log(
						'google_calendar.sync_failed',
						'booking',
						$booking_id,
						array( 'notes' => 'booking_not_found' )
					);
					return;
				}
				if ( null !== $calendar_staff_id && $calendar_staff_id > 0 ) {
					$booking['staff_id'] = $calendar_staff_id;
				}
				static::create_event( $booking_id, $booking );
				break;
			case 'update':
				$booking = static::load_booking_for_calendar_sync( $booking_id );
				if ( null === $booking ) {
					Bookit_Audit_Logger::log(
						'google_calendar.sync_failed',
						'booking',
						$booking_id,
						array( 'notes' => 'booking_not_found' )
					);
					return;
				}
				if ( null !== $calendar_staff_id && $calendar_staff_id > 0 ) {
					$booking['staff_id'] = $calendar_staff_id;
				}
				static::update_event( $booking_id, $booking );
				break;
			case 'delete':
				static::delete_event( $booking_id );
				break;
			default:
				Bookit_Audit_Logger::log(
					'google_calendar.sync_failed',
					'booking',
					$booking_id,
					array( 'notes' => 'unknown_operation: ' . $operation )
				);
		}
	}
}
