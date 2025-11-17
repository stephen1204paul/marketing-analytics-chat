<?php
/**
 * Credential Encryption Handler
 *
 * @package Marketing_Analytics_MCP
 */

namespace Marketing_Analytics_MCP\Credentials;

/**
 * Handles encryption and decryption of API credentials using libsodium
 */
class Encryption {

	/**
	 * Encryption key option name
	 */
	const KEY_OPTION = 'marketing_analytics_mcp_encryption_key';

	/**
	 * Get or generate encryption key
	 *
	 * @return string The encryption key
	 */
	private static function get_key() {
		$key = get_option( self::KEY_OPTION );

		if ( ! $key ) {
			error_log( '[Marketing Analytics MCP] Encryption key not found, generating new key' );
			$key = base64_encode( random_bytes( SODIUM_CRYPTO_SECRETBOX_KEYBYTES ) );
			add_option( self::KEY_OPTION, $key, '', false );
			error_log( '[Marketing Analytics MCP] New encryption key generated and stored' );
		}

		return base64_decode( $key );
	}

	/**
	 * Encrypt credentials
	 *
	 * @param array  $credentials The credentials to encrypt.
	 * @param string $platform    The platform identifier for logging.
	 * @return string|false Encrypted string or false on failure
	 */
	public static function encrypt( $credentials, $platform = 'unknown' ) {
		try {
			error_log( sprintf( '[Marketing Analytics MCP] Starting encryption for platform: %s', $platform ) );

			if ( ! extension_loaded( 'sodium' ) ) {
				error_log( '[Marketing Analytics MCP] ERROR: Sodium extension not loaded' );
				return false;
			}

			$key = self::get_key();
			$nonce = random_bytes( SODIUM_CRYPTO_SECRETBOX_NONCEBYTES );
			$plaintext = wp_json_encode( $credentials );

			error_log( sprintf( '[Marketing Analytics MCP] Encrypting credentials (length: %d bytes)', strlen( $plaintext ) ) );

			$ciphertext = sodium_crypto_secretbox( $plaintext, $nonce, $key );
			$encrypted = base64_encode( $nonce . $ciphertext );

			// Clean up
			sodium_memzero( $plaintext );
			sodium_memzero( $key );

			error_log( sprintf( '[Marketing Analytics MCP] Encryption successful for %s (encrypted length: %d bytes)', $platform, strlen( $encrypted ) ) );

			return $encrypted;

		} catch ( \Exception $e ) {
			error_log( sprintf( '[Marketing Analytics MCP] Encryption FAILED for %s: %s', $platform, $e->getMessage() ) );
			error_log( sprintf( '[Marketing Analytics MCP] Encryption error trace: %s', $e->getTraceAsString() ) );
			return false;
		}
	}

	/**
	 * Decrypt credentials
	 *
	 * @param string $encrypted The encrypted string.
	 * @param string $platform  The platform identifier for logging.
	 * @return array|false Decrypted credentials or false on failure
	 */
	public static function decrypt( $encrypted, $platform = 'unknown' ) {
		try {
			error_log( sprintf( '[Marketing Analytics MCP] Starting decryption for platform: %s', $platform ) );

			if ( ! extension_loaded( 'sodium' ) ) {
				error_log( '[Marketing Analytics MCP] ERROR: Sodium extension not loaded' );
				return false;
			}

			$key = self::get_key();
			$decoded = base64_decode( $encrypted );

			if ( $decoded === false ) {
				error_log( sprintf( '[Marketing Analytics MCP] ERROR: Base64 decode failed for %s', $platform ) );
				return false;
			}

			$nonce = mb_substr( $decoded, 0, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES, '8bit' );
			$ciphertext = mb_substr( $decoded, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES, null, '8bit' );

			error_log( sprintf( '[Marketing Analytics MCP] Decrypting credentials (ciphertext length: %d bytes)', strlen( $ciphertext ) ) );

			$plaintext = sodium_crypto_secretbox_open( $ciphertext, $nonce, $key );

			// Clean up
			sodium_memzero( $key );

			if ( $plaintext === false ) {
				error_log( sprintf( '[Marketing Analytics MCP] ERROR: Decryption failed for %s (invalid key or corrupted data)', $platform ) );
				return false;
			}

			$credentials = json_decode( $plaintext, true );
			sodium_memzero( $plaintext );

			if ( $credentials === null ) {
				error_log( sprintf( '[Marketing Analytics MCP] ERROR: JSON decode failed for %s', $platform ) );
				return false;
			}

			error_log( sprintf( '[Marketing Analytics MCP] Decryption successful for %s', $platform ) );

			return $credentials;

		} catch ( \Exception $e ) {
			error_log( sprintf( '[Marketing Analytics MCP] Decryption FAILED for %s: %s', $platform, $e->getMessage() ) );
			error_log( sprintf( '[Marketing Analytics MCP] Decryption error trace: %s', $e->getTraceAsString() ) );
			return false;
		}
	}

	/**
	 * Save encrypted credentials
	 *
	 * @param string $platform    The platform identifier.
	 * @param array  $credentials The credentials to save.
	 * @return bool Success status
	 */
	public static function save_credentials( $platform, $credentials ) {
		error_log( sprintf( '[Marketing Analytics MCP] Saving credentials for platform: %s', $platform ) );

		$encrypted = self::encrypt( $credentials, $platform );

		if ( $encrypted === false ) {
			error_log( sprintf( '[Marketing Analytics MCP] ERROR: Failed to encrypt credentials for %s', $platform ) );
			return false;
		}

		$option_name = 'marketing_analytics_mcp_credentials_' . $platform;
		$result = update_option( $option_name, $encrypted, false );

		if ( $result ) {
			error_log( sprintf( '[Marketing Analytics MCP] Credentials saved successfully for %s', $platform ) );
		} else {
			error_log( sprintf( '[Marketing Analytics MCP] ERROR: Failed to save credentials to database for %s', $platform ) );
		}

		return $result;
	}

	/**
	 * Retrieve and decrypt credentials
	 *
	 * @param string $platform The platform identifier.
	 * @return array|false Decrypted credentials or false
	 */
	public static function get_credentials( $platform ) {
		error_log( sprintf( '[Marketing Analytics MCP] Retrieving credentials for platform: %s', $platform ) );

		$option_name = 'marketing_analytics_mcp_credentials_' . $platform;
		$encrypted = get_option( $option_name );

		if ( ! $encrypted ) {
			error_log( sprintf( '[Marketing Analytics MCP] No credentials found in database for %s', $platform ) );
			return false;
		}

		error_log( sprintf( '[Marketing Analytics MCP] Found encrypted credentials for %s (length: %d bytes)', $platform, strlen( $encrypted ) ) );

		return self::decrypt( $encrypted, $platform );
	}

	/**
	 * Delete credentials
	 *
	 * @param string $platform The platform identifier.
	 * @return bool Success status
	 */
	public static function delete_credentials( $platform ) {
		error_log( sprintf( '[Marketing Analytics MCP] Deleting credentials for platform: %s', $platform ) );

		$option_name = 'marketing_analytics_mcp_credentials_' . $platform;
		$result = delete_option( $option_name );

		if ( $result ) {
			error_log( sprintf( '[Marketing Analytics MCP] Credentials deleted successfully for %s', $platform ) );
		} else {
			error_log( sprintf( '[Marketing Analytics MCP] No credentials to delete for %s (or delete failed)', $platform ) );
		}

		return $result;
	}
}
