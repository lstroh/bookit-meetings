<?php
/**
 * Google Calendar OAuth (per staff) — auth URL and token storage only.
 *
 * @package    Bookit_Booking_System
 * @subpackage Bookit_Booking_System/includes/integrations
 */

if ( ! defined( 'WPINC' ) ) {
	die;
}

/**
 * OAuth helpers for Google Calendar.
 */
class Bookit_Google_Calendar_Api {

	/**
	 * Redirect URI registered with Google (must match console + token exchange).
	 */
	private const OAUTH_REDIRECT_URI = 'https://test.wimbledonsmart.co.uk/wp-json/bookit/v1/google-calendar/callback';

	/**
	 * Calendar events scope (read/write events).
	 */
	private const SCOPE_CALENDAR_EVENTS = 'https://www.googleapis.com/auth/calendar.events';

	/**
	 * Build Google OAuth consent URL for a staff member.
	 *
	 * @param int $staff_id Staff row ID.
	 * @return string Authorization URL or empty string if misconfigured.
	 */
	public static function get_auth_url( int $staff_id ): string {
		$staff_id = absint( $staff_id );
		if ( $staff_id < 1 ) {
			return '';
		}

		$client = self::create_configured_client();
		if ( ! $client ) {
			return '';
		}

		$token   = bin2hex( random_bytes( 16 ) );
		$expires = time() + 600; // 10 minutes.
		$payload = $staff_id . ':' . $token . ':' . $expires;
		$sig     = hash_hmac( 'sha256', $payload, wp_salt( 'auth' ) );
		$state   = base64_encode( $payload . ':' . $sig );

		$client->setState( $state );
		$client->setAccessType( 'offline' );
		$client->setPrompt( 'consent' );
		$client->addScope( self::get_calendar_events_scope() );
		// OpenID Connect — ensures id_token (with email) in the token response.
		$client->addScope( 'openid' );
		$client->addScope( 'email' );

		return (string) $client->createAuthUrl();
	}

	/**
	 * OAuth callback: exchange code, store encrypted tokens, set connected flag.
	 *
	 * @param string $code  Authorization code.
	 * @param string $state State parameter (base64 HMAC payload).
	 * @return int Staff ID on success, 0 on failure.
	 */
	public static function handle_callback( string $code, string $state ): int {
		$state = wp_unslash( $state );

		$decoded = base64_decode( $state, true );
		if ( false === $decoded || '' === $decoded ) {
			Bookit_Audit_Logger::log(
				'google_calendar.oauth_failed',
				'staff',
				0,
				array(
					'notes' => 'invalid_state_encoding',
				)
			);
			return 0;
		}

		$parts = explode( ':', $decoded );
		if ( count( $parts ) !== 4 ) {
			Bookit_Audit_Logger::log(
				'google_calendar.oauth_failed',
				'staff',
				0,
				array(
					'notes' => 'invalid_state_format',
				)
			);
			return 0;
		}

		$staff_id_str = $parts[0];
		$token_str    = $parts[1];
		$expires_str  = $parts[2];
		$received_sig = $parts[3];
		$staff_id     = (int) $staff_id_str;

		if ( $staff_id < 1 ) {
			Bookit_Audit_Logger::log(
				'google_calendar.oauth_failed',
				'staff',
				0,
				array(
					'notes' => 'invalid_state_staff',
				)
			);
			return 0;
		}

		if ( time() > (int) $expires_str ) {
			Bookit_Audit_Logger::log(
				'google_calendar.oauth_failed',
				'staff',
				$staff_id,
				array(
					'notes' => 'state_expired',
				)
			);
			return 0;
		}

		$payload      = $staff_id . ':' . $token_str . ':' . $expires_str;
		$expected_sig = hash_hmac( 'sha256', $payload, wp_salt( 'auth' ) );

		if ( ! hash_equals( $expected_sig, $received_sig ) ) {
			Bookit_Audit_Logger::log(
				'google_calendar.oauth_failed',
				'staff',
				$staff_id,
				array(
					'notes' => 'invalid_state_sig',
				)
			);
			return 0;
		}

		$client = self::create_configured_client();
		if ( ! $client ) {
			Bookit_Audit_Logger::log(
				'google_calendar.oauth_failed',
				'staff',
				$staff_id,
				array(
					'notes' => 'Missing Google OAuth client configuration.',
				)
			);
			return 0;
		}

		$token = static::exchange_auth_code_for_tokens( $client, $code );
		if ( isset( $token['error'] ) ) {
			Bookit_Audit_Logger::log(
				'google_calendar.oauth_failed',
				'staff',
				$staff_id,
				array(
					'notes' => isset( $token['error_description'] ) ? (string) $token['error_description'] : (string) $token['error'],
				)
			);
			return 0;
		}

		$access_token  = isset( $token['access_token'] ) ? (string) $token['access_token'] : '';
		$refresh_token = isset( $token['refresh_token'] ) ? (string) $token['refresh_token'] : '';
		$expires_in    = isset( $token['expires_in'] ) ? (int) $token['expires_in'] : 3600;
		if ( '' === $access_token ) {
			Bookit_Audit_Logger::log(
				'google_calendar.oauth_failed',
				'staff',
				$staff_id,
				array(
					'notes' => 'Token response missing access_token.',
				)
			);
			return 0;
		}

		$email = static::fetch_google_account_email( $token );

		$expiry_mysql = date( 'Y-m-d H:i:s', time() + max( 1, $expires_in ) );

		global $wpdb;
		$table = $wpdb->prefix . 'bookings_staff';

		$updated = $wpdb->update(
			$table,
			array(
				'google_oauth_access_token'  => Bookit_Encryption::encrypt( $access_token ),
				'google_oauth_refresh_token'   => '' !== $refresh_token ? Bookit_Encryption::encrypt( $refresh_token ) : null,
				'google_oauth_token_expiry'    => $expiry_mysql,
				'google_calendar_email'        => '' !== $email ? $email : null,
				'google_calendar_connected'    => 1,
				'updated_at'                   => current_time( 'mysql' ),
			),
			array( 'id' => $staff_id ),
			array( '%s', '%s', '%s', '%s', '%d', '%s' ),
			array( '%d' )
		);

		if ( false === $updated ) {
			Bookit_Audit_Logger::log(
				'google_calendar.oauth_failed',
				'staff',
				$staff_id,
				array(
					'notes' => 'Database update failed.',
				)
			);
			return 0;
		}

		Bookit_Audit_Logger::log(
			'google_calendar.connected',
			'staff',
			$staff_id,
			array()
		);

		return $staff_id;
	}

	/**
	 * Disconnect Google Calendar for a staff member.
	 *
	 * @param int $staff_id Staff ID.
	 * @return void
	 */
	public static function disconnect( int $staff_id ): void {
		$staff_id = absint( $staff_id );
		if ( $staff_id < 1 ) {
			return;
		}

		global $wpdb;
		$table = $wpdb->prefix . 'bookings_staff';

		$wpdb->update(
			$table,
			array(
				'google_oauth_access_token'  => null,
				'google_oauth_refresh_token' => null,
				'google_oauth_token_expiry'  => null,
				'google_calendar_email'      => null,
				'google_calendar_connected'  => 0,
				'updated_at'                 => current_time( 'mysql' ),
			),
			array( 'id' => $staff_id ),
			array( '%s', '%s', '%s', '%s', '%d', '%s' ),
			array( '%d' )
		);

		Bookit_Audit_Logger::log(
			'google_calendar.disconnected',
			'staff',
			$staff_id,
			array()
		);
	}

	/**
	 * Exchange authorization code for tokens (test override).
	 *
	 * @param \Google\Client $client OAuth client.
	 * @param string         $code   Auth code.
	 * @return array Token payload or error shape from Google client.
	 */
	protected static function exchange_auth_code_for_tokens( \Google\Client $client, string $code ): array {
		$result = $client->fetchAccessTokenWithAuthCode( $code );
		return is_array( $result ) ? $result : array();
	}

	/**
	 * Read Google account email from the OpenID Connect id_token (JWT) in the token response.
	 *
	 * No outbound HTTP — avoids firewalls blocking Google APIs from the server.
	 * Missing or invalid id_token/email is non-fatal; connection still succeeds with a null email.
	 *
	 * @param array $token Token array from fetchAccessTokenWithAuthCode().
	 * @return string Sanitized email or empty string.
	 */
	protected static function fetch_google_account_email( array $token ): string {
		try {
			if ( empty( $token['id_token'] ) || ! is_string( $token['id_token'] ) ) {
				return '';
			}

			$parts = explode( '.', $token['id_token'] );
			if ( count( $parts ) !== 3 ) {
				return '';
			}

			$b64 = str_replace( array( '-', '_' ), array( '+', '/' ), $parts[1] );
			$pad = strlen( $b64 ) % 4;
			if ( $pad > 0 ) {
				$b64 .= str_repeat( '=', 4 - $pad );
			}

			$payload = base64_decode( $b64, true );
			if ( false === $payload || '' === $payload ) {
				return '';
			}

			$data = json_decode( $payload, true );
			if ( ! is_array( $data ) || empty( $data['email'] ) ) {
				return '';
			}

			return sanitize_email( (string) $data['email'] );
		} catch ( \Exception $e ) {
			return '';
		}
	}

	/**
	 * Scope string for Calendar events.
	 *
	 * @return string
	 */
	private static function get_calendar_events_scope(): string {
		if ( class_exists( \Google\Service\Calendar::class ) ) {
			return \Google\Service\Calendar::CALENDAR_EVENTS;
		}
		return self::SCOPE_CALENDAR_EVENTS;
	}

	/**
	 * Create Google Client with plugin settings (or null if not configured).
	 *
	 * @return \Google\Client|null
	 */
	private static function create_configured_client(): ?\Google\Client {
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
}
