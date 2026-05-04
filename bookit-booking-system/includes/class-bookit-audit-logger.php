<?php
/**
 * Audit logger.
 *
 * @package    Bookit_Booking_System
 * @subpackage Bookit_Booking_System/includes
 */

if ( ! defined( 'WPINC' ) ) {
	die;
}

/**
 * Records immutable audit events.
 */
class Bookit_Audit_Logger {

	/**
	 * Sensitive key fragments that must never be stored.
	 *
	 * @var string[]
	 */
	private static array $sensitive_key_fragments = array(
		'password',
		'secret',
		'api_key',
		'token',
		'card',
		'cvv',
		'stripe_secret',
		'paypal_secret',
	);

	/**
	 * Log an auditable action.
	 *
	 * Never throws — silently fails if DB unavailable, logging to error_log instead.
	 *
	 * @param string $action      Dot-notation action (e.g. 'booking.created').
	 * @param string $object_type Object type (e.g. 'booking', 'customer', 'setting').
	 * @param int    $object_id   ID of the object being acted on. 0 if not applicable.
	 * @param array  $context     Optional context.
	 * @return void
	 */
	public static function log(
		string $action,
		string $object_type,
		int $object_id = 0,
		array $context = array()
	): void {
		global $wpdb;

		try {
			if ( empty( $wpdb ) || ! isset( $wpdb->prefix ) ) {
				// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
				error_log( '[Bookit Audit Logger] Database object unavailable; skipped log insert.' );
				return;
			}

			$actor_data = self::detect_actor();
			$actor_id   = isset( $context['actor_id'] ) ? absint( $context['actor_id'] ) : $actor_data['actor_id'];
			$actor_type = isset( $context['actor_type'] ) ? sanitize_key( (string) $context['actor_type'] ) : $actor_data['actor_type'];

			if ( ! in_array( $actor_type, array( 'admin', 'staff', 'customer', 'system' ), true ) ) {
				$actor_type = 'system';
			}

			$old_value = self::prepare_value_for_storage( $context['old_value'] ?? null );
			$new_value = self::prepare_value_for_storage( $context['new_value'] ?? null );
			$notes     = isset( $context['notes'] ) ? sanitize_textarea_field( (string) $context['notes'] ) : null;
			$actor_ip  = self::detect_ip();

			$table = $wpdb->prefix . 'bookings_audit_log';

			// Avoid noisy DB errors in environments where the audit table isn't installed yet.
			$exists = $wpdb->get_var(
				$wpdb->prepare(
					"SELECT COUNT(*) FROM information_schema.TABLES
					 WHERE TABLE_SCHEMA = DATABASE()
					 AND TABLE_NAME = %s",
					$table
				)
			);
			if ( ! $exists ) {
				return;
			}

			$inserted = $wpdb->insert(
				$table,
				array(
					'actor_id'    => $actor_id,
					'actor_type'  => $actor_type,
					'actor_ip'    => $actor_ip,
					'action'      => sanitize_text_field( $action ),
					'object_type' => sanitize_text_field( $object_type ),
					'object_id'   => $object_id > 0 ? $object_id : null,
					'old_value'   => $old_value,
					'new_value'   => $new_value,
					'notes'       => $notes,
					'created_at'  => current_time( 'mysql' ),
				),
				array( '%d', '%s', '%s', '%s', '%s', '%d', '%s', '%s', '%s', '%s' )
			);

			if ( false === $inserted ) {
				// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
				error_log( '[Bookit Audit Logger] Insert failed: ' . (string) $wpdb->last_error );
			}
		} catch ( Throwable $exception ) {
			// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
			error_log( '[Bookit Audit Logger] Exception while logging audit event: ' . $exception->getMessage() );
		}
	}

	/**
	 * Detect actor based on current authenticated dashboard user.
	 *
	 * @return array{actor_id:int,actor_type:string}
	 */
	private static function detect_actor(): array {
		$user = null;

		if ( class_exists( 'Bookit_Auth' ) && method_exists( 'Bookit_Auth', 'get_current_user' ) ) {
			$user = Bookit_Auth::get_current_user();
		}

		if ( empty( $user ) && class_exists( 'Bookit_Auth' ) && method_exists( 'Bookit_Auth', 'get_current_staff' ) ) {
			$user = Bookit_Auth::get_current_staff();
		}

		if ( ! is_array( $user ) || empty( $user['id'] ) ) {
			return array(
				'actor_id'   => 0,
				'actor_type' => 'system',
			);
		}

		$role = isset( $user['role'] ) ? (string) $user['role'] : '';

		if ( in_array( $role, array( 'bookit_admin', 'admin' ), true ) ) {
			$actor_type = 'admin';
		} elseif ( in_array( $role, array( 'bookit_staff', 'staff' ), true ) ) {
			$actor_type = 'staff';
		} else {
			$actor_type = 'system';
		}

		return array(
			'actor_id'   => absint( $user['id'] ),
			'actor_type' => $actor_type,
		);
	}

	/**
	 * Detect request IP address.
	 *
	 * @return string|null
	 */
	private static function detect_ip(): ?string {
		$forwarded_for = isset( $_SERVER['HTTP_X_FORWARDED_FOR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['HTTP_X_FORWARDED_FOR'] ) ) : '';
		$remote_addr   = isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : '';

		$ip_candidates = array();

		if ( ! empty( $forwarded_for ) ) {
			$ip_candidates = array_map( 'trim', explode( ',', $forwarded_for ) );
		}

		if ( ! empty( $remote_addr ) ) {
			$ip_candidates[] = $remote_addr;
		}

		foreach ( $ip_candidates as $candidate ) {
			$validated = filter_var( $candidate, FILTER_VALIDATE_IP );
			if ( false !== $validated ) {
				return $validated;
			}
		}

		return null;
	}

	/**
	 * Prepare a context value for storage.
	 *
	 * @param mixed $value Raw value.
	 * @return string|null
	 */
	private static function prepare_value_for_storage( $value ): ?string {
		if ( null === $value ) {
			return null;
		}

		if ( is_array( $value ) ) {
			if ( empty( $value ) ) {
				return null;
			}

			$redacted = self::redact( $value );
			return wp_json_encode( $redacted );
		}

		if ( is_object( $value ) ) {
			$normalized = json_decode( wp_json_encode( $value ), true );
			if ( empty( $normalized ) || ! is_array( $normalized ) ) {
				return null;
			}

			$redacted = self::redact( $normalized );
			return wp_json_encode( $redacted );
		}

		return (string) $value;
	}

	/**
	 * Recursively remove sensitive keys from array data.
	 *
	 * @param array $data Input data.
	 * @return array
	 */
	private static function redact( array $data ): array {
		$redacted = array();

		foreach ( $data as $key => $value ) {
			$key_string = is_string( $key ) ? strtolower( $key ) : (string) $key;

			if ( self::is_sensitive_key( $key_string ) ) {
				continue;
			}

			if ( is_array( $value ) ) {
				$redacted[ $key ] = self::redact( $value );
				continue;
			}

			if ( is_object( $value ) ) {
				$nested = json_decode( wp_json_encode( $value ), true );
				$redacted[ $key ] = is_array( $nested ) ? self::redact( $nested ) : $value;
				continue;
			}

			$redacted[ $key ] = $value;
		}

		return $redacted;
	}

	/**
	 * Check if key contains a sensitive fragment.
	 *
	 * @param string $key Key name.
	 * @return bool
	 */
	private static function is_sensitive_key( string $key ): bool {
		foreach ( self::$sensitive_key_fragments as $fragment ) {
			if ( false !== strpos( $key, $fragment ) ) {
				return true;
			}
		}

		return false;
	}
}
