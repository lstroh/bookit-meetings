<?php
/**
 * AES-256-CBC encryption helper for sensitive values.
 *
 * @package    Bookit_Booking_System
 * @subpackage Bookit_Booking_System/includes/utils
 */

if ( ! defined( 'WPINC' ) ) {
	die;
}

/**
 * Symmetric encryption using wp_salt-derived key.
 */
class Bookit_Encryption {

	/**
	 * Encrypt a string. Output is base64( IV || ciphertext ).
	 *
	 * @param string $value Plain text.
	 * @return string Base64-encoded blob.
	 */
	public static function encrypt( string $value ): string {
		$key = self::get_key();
		$iv  = openssl_random_pseudo_bytes( 16 );
		if ( false === $iv ) {
			return '';
		}
		$raw = openssl_encrypt( $value, 'AES-256-CBC', $key, OPENSSL_RAW_DATA, $iv );
		if ( false === $raw ) {
			return '';
		}
		return base64_encode( $iv . $raw );
	}

	/**
	 * Decrypt a value from encrypt(). Returns empty string on any failure.
	 *
	 * @param string $encrypted Base64 from encrypt().
	 * @return string Plain text or empty string.
	 */
	public static function decrypt( string $encrypted ): string {
		$bin = base64_decode( $encrypted, true );
		if ( false === $bin || strlen( $bin ) < 17 ) {
			return '';
		}
		$iv         = substr( $bin, 0, 16 );
		$ciphertext = substr( $bin, 16 );
		$key        = self::get_key();
		$plain      = openssl_decrypt( $ciphertext, 'AES-256-CBC', $key, OPENSSL_RAW_DATA, $iv );
		return false === $plain ? '' : $plain;
	}

	/**
	 * 32-byte key from wp_salt( 'auth' ).
	 *
	 * @return string
	 */
	private static function get_key(): string {
		return hash( 'sha256', wp_salt( 'auth' ), true );
	}
}
