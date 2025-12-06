<?php
/**
 * Credential Encryption Handler
 *
 * @package Marketing_Analytics_MCP
 */

namespace Marketing_Analytics_MCP\Credentials;

use Marketing_Analytics_MCP\Utils\Logger;

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
			Logger::debug( 'Encryption key not found, generating new key' );
			$new_key = base64_encode( random_bytes( SODIUM_CRYPTO_SECRETBOX_KEYBYTES ) );

			// Use add_option which will fail if the option already exists (race condition protection)
			$added = add_option( self::KEY_OPTION, $new_key, '', false );

			if ( $added ) {
				Logger::debug( 'New encryption key generated and stored' );
				$key = $new_key;
			} else {
				// Another process already created the key, fetch it
				Logger::debug( 'Key was already created by another process, fetching it' );
				$key = get_option( self::KEY_OPTION );

				// Double-check that we got a key
				if ( ! $key ) {
					throw new \RuntimeException( 'Failed to generate or retrieve encryption key' );
				}
			}
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
			Logger::debug( sprintf( 'Starting encryption for platform: %s', $platform ) );

			if ( ! extension_loaded( 'sodium' ) ) {
				Logger::error( 'Sodium extension not loaded' );
				return false;
			}

			$key       = self::get_key();
			$nonce     = random_bytes( SODIUM_CRYPTO_SECRETBOX_NONCEBYTES );
			$plaintext = wp_json_encode( $credentials );

			Logger::debug( sprintf( 'Encrypting credentials (length: %d bytes)', strlen( $plaintext ) ) );

			$ciphertext = sodium_crypto_secretbox( $plaintext, $nonce, $key );
			$encrypted  = base64_encode( $nonce . $ciphertext );

			// Clean up
			sodium_memzero( $plaintext );
			sodium_memzero( $key );

			Logger::debug( sprintf( 'Encryption successful for %s (encrypted length: %d bytes)', $platform, strlen( $encrypted ) ) );

			return $encrypted;

		} catch ( \Exception $e ) {
			Logger::error( sprintf( 'Encryption FAILED for %s: %s', $platform, $e->getMessage() ) );
			Logger::debug( sprintf( 'Encryption error trace: %s', $e->getTraceAsString() ) );
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
			Logger::debug( sprintf( 'Starting decryption for platform: %s', $platform ) );

			if ( ! extension_loaded( 'sodium' ) ) {
				Logger::error( 'Sodium extension not loaded' );
				return false;
			}

			$key     = self::get_key();
			$decoded = base64_decode( $encrypted );

			if ( $decoded === false ) {
				Logger::error( sprintf( 'Base64 decode failed for %s', $platform ) );
				return false;
			}

			$nonce      = mb_substr( $decoded, 0, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES, '8bit' );
			$ciphertext = mb_substr( $decoded, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES, null, '8bit' );

			Logger::debug( sprintf( 'Decrypting credentials (ciphertext length: %d bytes)', strlen( $ciphertext ) ) );

			$plaintext = sodium_crypto_secretbox_open( $ciphertext, $nonce, $key );

			// Clean up
			sodium_memzero( $key );

			if ( $plaintext === false ) {
				Logger::error( sprintf( 'Decryption failed for %s (invalid key or corrupted data)', $platform ) );
				return false;
			}

			$credentials = json_decode( $plaintext, true );
			sodium_memzero( $plaintext );

			if ( $credentials === null ) {
				Logger::error( sprintf( 'JSON decode failed for %s', $platform ) );
				return false;
			}

			Logger::debug( sprintf( 'Decryption successful for %s', $platform ) );

			return $credentials;

		} catch ( \Exception $e ) {
			Logger::error( sprintf( 'Decryption FAILED for %s: %s', $platform, $e->getMessage() ) );
			Logger::debug( sprintf( 'Decryption error trace: %s', $e->getTraceAsString() ) );
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
		Logger::debug( sprintf( 'Saving credentials for platform: %s', $platform ) );

		$encrypted = self::encrypt( $credentials, $platform );

		if ( $encrypted === false ) {
			Logger::error( sprintf( 'Failed to encrypt credentials for %s', $platform ) );
			return false;
		}

		$option_name = 'marketing_analytics_mcp_credentials_' . $platform;
		$result      = update_option( $option_name, $encrypted, false );

		if ( $result ) {
			Logger::debug( sprintf( 'Credentials saved successfully for %s', $platform ) );
		} else {
			Logger::error( sprintf( 'Failed to save credentials to database for %s', $platform ) );
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
		Logger::debug( sprintf( 'Retrieving credentials for platform: %s', $platform ) );

		$option_name = 'marketing_analytics_mcp_credentials_' . $platform;
		$encrypted   = get_option( $option_name );

		if ( ! $encrypted ) {
			Logger::debug( sprintf( 'No credentials found in database for %s', $platform ) );
			return false;
		}

		Logger::debug( sprintf( 'Found encrypted credentials for %s (length: %d bytes)', $platform, strlen( $encrypted ) ) );

		return self::decrypt( $encrypted, $platform );
	}

	/**
	 * Delete credentials
	 *
	 * @param string $platform The platform identifier.
	 * @return bool Success status
	 */
	public static function delete_credentials( $platform ) {
		Logger::debug( sprintf( 'Deleting credentials for platform: %s', $platform ) );

		$option_name = 'marketing_analytics_mcp_credentials_' . $platform;
		$result      = delete_option( $option_name );

		if ( $result ) {
			Logger::debug( sprintf( 'Credentials deleted successfully for %s', $platform ) );
		} else {
			Logger::debug( sprintf( 'No credentials to delete for %s (or delete failed)', $platform ) );
		}

		return $result;
	}
}
