<?php
/**
 * Credential Manager
 *
 * Handles secure storage and retrieval of API credentials for analytics platforms.
 *
 * @package Marketing_Analytics_MCP
 */

namespace Marketing_Analytics_MCP\Credentials;

use Marketing_Analytics_MCP\Utils\Logger;

/**
 * Manages encrypted credentials for analytics platforms
 */
class Credential_Manager {

	/**
	 * Option name prefix for storing credentials
	 */
	const OPTION_PREFIX = 'marketing_analytics_mcp_credentials_';

	/**
	 * Supported platforms
	 */
	const SUPPORTED_PLATFORMS = array( 'clarity', 'ga4', 'gsc' );

	/**
	 * Save credentials for a platform
	 *
	 * @param string $platform Platform identifier (clarity, ga4, gsc).
	 * @param array  $credentials Credential data to store.
	 * @return bool True on success, false on failure.
	 */
	public function save_credentials( $platform, $credentials ) {
		if ( ! $this->is_valid_platform( $platform ) ) {
			return false;
		}

		if ( empty( $credentials ) || ! is_array( $credentials ) ) {
			return false;
		}

		// Apply filter to allow modification before encryption
		$credentials = apply_filters( 'marketing_analytics_mcp_encrypt_credentials', $credentials, $platform );

		// Encrypt the credentials
		try {
			$encrypted = Encryption::encrypt( $credentials, $platform );
		} catch ( \Exception $e ) {
			Logger::debug( 'Failed to encrypt credentials for ' . $platform . ': ' . $e->getMessage() );
			return false;
		}

		// Store encrypted credentials
		$option_name = $this->get_option_name( $platform );
		return update_option( $option_name, $encrypted, false ); // Don't autoload
	}

	/**
	 * Get credentials for a platform
	 *
	 * @param string $platform Platform identifier (clarity, ga4, gsc).
	 * @return array|null Decrypted credentials or null if not found/failed.
	 */
	public function get_credentials( $platform ) {
		if ( ! $this->is_valid_platform( $platform ) ) {
			return null;
		}

		$option_name = $this->get_option_name( $platform );
		$encrypted   = get_option( $option_name );

		if ( empty( $encrypted ) ) {
			return null;
		}

		// Decrypt the credentials (returns array directly)
		try {
			$credentials = Encryption::decrypt( $encrypted, $platform );

			if ( $credentials === false || ! is_array( $credentials ) ) {
				Logger::debug( 'Failed to decrypt credentials for ' . $platform );
				return null;
			}

			return $credentials;
		} catch ( \Exception $e ) {
			Logger::debug( 'Failed to decrypt credentials for ' . $platform . ': ' . $e->getMessage() );
			return null;
		}
	}

	/**
	 * Delete credentials for a platform
	 *
	 * @param string $platform Platform identifier (clarity, ga4, gsc).
	 * @return bool True on success, false on failure.
	 */
	public function delete_credentials( $platform ) {
		if ( ! $this->is_valid_platform( $platform ) ) {
			return false;
		}

		$option_name = $this->get_option_name( $platform );
		return delete_option( $option_name );
	}

	/**
	 * Check if credentials exist for a platform
	 *
	 * @param string $platform Platform identifier (clarity, ga4, gsc).
	 * @return bool True if credentials exist, false otherwise.
	 */
	public function has_credentials( $platform ) {
		if ( ! $this->is_valid_platform( $platform ) ) {
			return false;
		}

		$credentials = $this->get_credentials( $platform );
		return ! empty( $credentials );
	}

	/**
	 * Get connection status for all platforms
	 *
	 * @return array Array of platform => has_credentials status.
	 */
	public function get_all_statuses() {
		$statuses = array();

		foreach ( self::SUPPORTED_PLATFORMS as $platform ) {
			$statuses[ $platform ] = $this->has_credentials( $platform );
		}

		return $statuses;
	}

	/**
	 * Update specific credential field for a platform
	 *
	 * @param string $platform Platform identifier.
	 * @param string $field Field name to update.
	 * @param mixed  $value New value for the field.
	 * @return bool True on success, false on failure.
	 */
	public function update_credential_field( $platform, $field, $value ) {
		$credentials = $this->get_credentials( $platform );

		if ( null === $credentials ) {
			$credentials = array();
		}

		$credentials[ $field ] = $value;

		return $this->save_credentials( $platform, $credentials );
	}

	/**
	 * Get a specific credential field
	 *
	 * @param string $platform Platform identifier.
	 * @param string $field Field name to retrieve.
	 * @param mixed  $fallback Default value if field doesn't exist.
	 * @return mixed Field value or default.
	 */
	public function get_credential_field( $platform, $field, $fallback = null ) {
		$credentials = $this->get_credentials( $platform );

		if ( null === $credentials || ! isset( $credentials[ $field ] ) ) {
			return $fallback;
		}

		return $credentials[ $field ];
	}

	/**
	 * Validate platform identifier
	 *
	 * @param string $platform Platform identifier to validate.
	 * @return bool True if valid, false otherwise.
	 */
	private function is_valid_platform( $platform ) {
		return in_array( $platform, self::SUPPORTED_PLATFORMS, true );
	}

	/**
	 * Get option name for a platform
	 *
	 * @param string $platform Platform identifier.
	 * @return string WordPress option name.
	 */
	private function get_option_name( $platform ) {
		return self::OPTION_PREFIX . $platform;
	}

	/**
	 * Clear all credentials (use with caution)
	 *
	 * @return bool True if all cleared successfully.
	 */
	public function clear_all_credentials() {
		$success = true;

		foreach ( self::SUPPORTED_PLATFORMS as $platform ) {
			if ( ! $this->delete_credentials( $platform ) ) {
				$success = false;
			}
		}

		return $success;
	}

	/**
	 * Export credentials for backup (still encrypted)
	 *
	 * @return array Array of platform => encrypted_credentials.
	 */
	public function export_encrypted() {
		$export = array();

		foreach ( self::SUPPORTED_PLATFORMS as $platform ) {
			$option_name = $this->get_option_name( $platform );
			$encrypted   = get_option( $option_name );

			if ( ! empty( $encrypted ) ) {
				$export[ $platform ] = $encrypted;
			}
		}

		return $export;
	}

	/**
	 * Import credentials from backup (encrypted format)
	 *
	 * @param array $import Array of platform => encrypted_credentials.
	 * @return bool True if import successful.
	 */
	public function import_encrypted( $import ) {
		if ( ! is_array( $import ) ) {
			return false;
		}

		$success = true;

		foreach ( $import as $platform => $encrypted ) {
			if ( ! $this->is_valid_platform( $platform ) ) {
				continue;
			}

			$option_name = $this->get_option_name( $platform );
			if ( ! update_option( $option_name, $encrypted, false ) ) {
				$success = false;
			}
		}

		return $success;
	}
}
