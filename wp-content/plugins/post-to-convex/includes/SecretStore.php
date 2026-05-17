<?php
/**
 * Encrypts the Convex shared secret at rest. The database holds ciphertext; wp-config salts supply the key material.
 *
 * @package PostToConvex
 */

declare( strict_types=1 );

namespace PostToConvex;

/**
 * Security check.
 */
defined( 'ABSPATH' ) || exit;

/**
 * AES-256-GCM encryption helpers for the post_to_convex_secret option.
 */
class SecretStore {

	/**
	 * Cipher algorithm.
	 *
	 * @var string
	 */
	private const CIPHER = 'aes-256-gcm';

	/**
	 * Prefix for ciphertext blobs written to the options table.
	 *
	 * @var string
	 */
	private const STORED_PREFIX = 'ptcv1:';

	/**
	 * Encrypt plaintext for storage in the options table.
	 *
	 * @param string $plaintext Raw secret.
	 * @return string Prefixed ciphertext, or empty string on failure / empty input.
	 */
	public static function encrypt( string $plaintext ): string {
		if ( ! is_string( $plaintext ) || '' === $plaintext ) {
			return '';
		}
		if ( ! function_exists( 'openssl_encrypt' ) ) {
			return '';
		}

		$key = self::key_material();
		if ( '' === $key ) {
			return '';
		}

		$iv_length = openssl_cipher_iv_length( self::CIPHER );
		if ( false === $iv_length || $iv_length < 1 ) {
			return '';
		}

		$iv  = random_bytes( $iv_length );
		$tag = '';

		$ciphertext = openssl_encrypt(
			$plaintext,
			self::CIPHER,
			$key,
			OPENSSL_RAW_DATA,
			$iv,
			$tag,
			'',
			16
		);

		if ( false === $ciphertext || '' === $tag ) {
			return '';
		}

		return self::STORED_PREFIX . base64_encode( $iv . $tag . $ciphertext ); // phpcs:ignore
	}

	/**
	 * Decrypt an option value for use at runtime (e.g. outbound requests).
	 *
	 * @param string $stored Value from get_option.
	 * @return string Plaintext secret, or empty string.
	 */
	public static function decrypt( string $stored ): string {
		if ( ! is_string( $stored ) || '' === $stored ) {
			return '';
		}

		if ( ! function_exists( 'openssl_decrypt' ) ) {
			return '';
		}

		$key = self::key_material();
		if ( '' === $key ) {
			return '';
		}

		$iv_length = openssl_cipher_iv_length( self::CIPHER );
		if ( false === $iv_length || $iv_length < 1 ) {
			return '';
		}

		$binary = base64_decode( substr( $stored, strlen( self::STORED_PREFIX ) ), true ); // phpcs:ignore
		if ( false === $binary || strlen( $binary ) < $iv_length + 16 + 1 ) {
			return '';
		}

		$iv         = substr( $binary, 0, $iv_length );
		$tag        = substr( $binary, $iv_length, 16 );
		$ciphertext = substr( $binary, $iv_length + 16 );

		$plain = openssl_decrypt(
			$ciphertext,
			self::CIPHER,
			$key,
			OPENSSL_RAW_DATA,
			$iv,
			$tag
		);

		return is_string( $plain ) ? $plain : '';
	}

	/**
	 * Convenience: decrypted secret from the options table.
	 *
	 * @return string Plaintext secret, or empty string.
	 */
	public static function get_plaintext_secret(): string {
		return self::decrypt( (string) get_option( AdminSettings::OPTION_SECRET, '' ) );
	}

	/**
	 * 32-byte key from WordPress salts (not stored in the database).
	 *
	 * @return string Binary key or empty string if salts are unavailable.
	 */
	private static function key_material(): string {
		if ( ! function_exists( 'wp_salt' ) ) {
			return '';
		}

		return hash( 'sha256', wp_salt( 'post_to_convex' ) . wp_salt( 'auth' ), true );
	}
}
