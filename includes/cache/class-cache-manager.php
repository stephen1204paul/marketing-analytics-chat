<?php
/**
 * Cache Manager
 *
 * Manages caching of API responses using WordPress Transients API.
 *
 * @package Marketing_Analytics_MCP
 */

namespace Marketing_Analytics_MCP\Cache;

/**
 * Handles caching for API responses
 */
class Cache_Manager {

	/**
	 * Cache key prefix
	 */
	const CACHE_PREFIX = 'marketing_mcp_';

	/**
	 * Default TTL values for each platform (in seconds)
	 */
	const DEFAULT_TTL = array(
		'clarity' => HOUR_IN_SECONDS,        // 1 hour (rate limit: 10 req/day).
		'ga4'     => 30 * MINUTE_IN_SECONDS, // 30 minutes (near real-time but expensive).
		'gsc'     => DAY_IN_SECONDS,         // 24 hours (data has 2-3 day delay).
	);

	/**
	 * Get cached data
	 *
	 * @param string $key Cache key.
	 * @return mixed|false Cached data or false if not found/expired.
	 */
	public function get( $key ) {
		$cache_key = $this->build_cache_key( $key );
		return get_transient( $cache_key );
	}

	/**
	 * Set cached data
	 *
	 * @param string $key Cache key.
	 * @param mixed  $data Data to cache.
	 * @param int    $ttl Time to live in seconds (optional).
	 * @return bool True on success, false on failure.
	 */
	public function set( $key, $data, $ttl = null ) {
		$cache_key = $this->build_cache_key( $key );

		// If no TTL provided, use default
		if ( null === $ttl ) {
			$ttl = HOUR_IN_SECONDS;
		}

		return set_transient( $cache_key, $data, $ttl );
	}

	/**
	 * Delete cached data
	 *
	 * @param string $key Cache key.
	 * @return bool True on success, false on failure.
	 */
	public function delete( $key ) {
		$cache_key = $this->build_cache_key( $key );
		return delete_transient( $cache_key );
	}

	/**
	 * Clear all cache for a specific platform
	 *
	 * @param string $platform Platform identifier (clarity, ga4, gsc).
	 * @return int Number of cache entries cleared.
	 */
	public function clear_platform_cache( $platform ) {
		global $wpdb;

		$pattern = self::CACHE_PREFIX . $platform . '_%';

		// Delete transients
		$deleted = $wpdb->query(
			$wpdb->prepare(
				"DELETE FROM {$wpdb->options}
				WHERE option_name LIKE %s
				OR option_name LIKE %s",
				'_transient_' . $pattern,
				'_transient_timeout_' . $pattern
			)
		);

		return $deleted;
	}

	/**
	 * Clear all cache
	 *
	 * @return int Number of cache entries cleared.
	 */
	public function clear_all_cache() {
		global $wpdb;

		$pattern = self::CACHE_PREFIX . '%';

		// Delete all plugin transients
		$deleted = $wpdb->query(
			$wpdb->prepare(
				"DELETE FROM {$wpdb->options}
				WHERE option_name LIKE %s
				OR option_name LIKE %s",
				'_transient_' . $pattern,
				'_transient_timeout_' . $pattern
			)
		);

		return $deleted;
	}

	/**
	 * Generate cache key from parameters
	 *
	 * @param string $platform Platform identifier.
	 * @param string $endpoint API endpoint/method name.
	 * @param array  $params Request parameters.
	 * @return string Generated cache key.
	 */
	public function generate_key( $platform, $endpoint, $params = array() ) {
		// Sort params for consistent keys
		ksort( $params );

		// Create hash of params
		$params_hash = md5( wp_json_encode( $params ) );

		// Format: platform_endpoint_paramshash
		return sprintf(
			'%s_%s_%s',
			sanitize_key( $platform ),
			sanitize_key( $endpoint ),
			$params_hash
		);
	}

	/**
	 * Get default TTL for a platform
	 *
	 * @param string $platform Platform identifier.
	 * @return int TTL in seconds.
	 */
	public function get_default_ttl( $platform ) {
		// Apply filter to allow customization
		$ttl = apply_filters(
			'marketing_analytics_mcp_cache_ttl',
			self::DEFAULT_TTL[ $platform ] ?? HOUR_IN_SECONDS,
			$platform
		);

		return absint( $ttl );
	}

	/**
	 * Build full cache key with prefix
	 *
	 * @param string $key Cache key.
	 * @return string Full cache key.
	 */
	private function build_cache_key( $key ) {
		return self::CACHE_PREFIX . $key;
	}

	/**
	 * Get cache statistics
	 *
	 * @return array Cache statistics.
	 */
	public function get_cache_stats() {
		global $wpdb;

		$pattern = self::CACHE_PREFIX . '%';

		// Count total cached items
		$total_count = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$wpdb->options}
				WHERE option_name LIKE %s
				AND option_name NOT LIKE %s",
				'_transient_' . $pattern,
				'_transient_timeout_%'
			)
		);

		// Get cache size (approximate)
		$cache_size = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT SUM(LENGTH(option_value)) FROM {$wpdb->options}
				WHERE option_name LIKE %s
				AND option_name NOT LIKE %s",
				'_transient_' . $pattern,
				'_transient_timeout_%'
			)
		);

		// Count by platform
		$by_platform = array();
		foreach ( array( 'clarity', 'ga4', 'gsc' ) as $platform ) {
			$platform_pattern = self::CACHE_PREFIX . $platform . '_%';
			$by_platform[ $platform ] = $wpdb->get_var(
				$wpdb->prepare(
					"SELECT COUNT(*) FROM {$wpdb->options}
					WHERE option_name LIKE %s
					AND option_name NOT LIKE %s",
					'_transient_' . $platform_pattern,
					'_transient_timeout_%'
				)
			);
		}

		return array(
			'total_count'  => absint( $total_count ),
			'total_size'   => absint( $cache_size ),
			'size_human'   => size_format( $cache_size ),
			'by_platform'  => $by_platform,
		);
	}

	/**
	 * Check if a key is cached
	 *
	 * @param string $key Cache key.
	 * @return bool True if cached, false otherwise.
	 */
	public function has( $key ) {
		return false !== $this->get( $key );
	}

	/**
	 * Get or set cache (fetch from callback if not cached)
	 *
	 * @param string   $key Cache key.
	 * @param callable $callback Function to generate data if not cached.
	 * @param int      $ttl Time to live in seconds (optional).
	 * @return mixed Cached data or fresh data from callback.
	 */
	public function remember( $key, $callback, $ttl = null ) {
		$data = $this->get( $key );

		if ( false !== $data ) {
			return $data;
		}

		// Generate fresh data
		$data = call_user_func( $callback );

		// Cache it
		if ( false !== $data && null !== $data ) {
			$this->set( $key, $data, $ttl );
		}

		return $data;
	}

	/**
	 * Invalidate cache when credentials change
	 *
	 * @param string $platform Platform identifier.
	 * @return int Number of cache entries cleared.
	 */
	public function invalidate_on_credential_change( $platform ) {
		return $this->clear_platform_cache( $platform );
	}

	/**
	 * Get cache key info (for debugging)
	 *
	 * @param string $key Cache key.
	 * @return array|false Cache info or false if not found.
	 */
	public function get_cache_info( $key ) {
		global $wpdb;

		$cache_key = $this->build_cache_key( $key );
		$timeout_key = '_transient_timeout_' . $cache_key;

		$timeout = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT option_value FROM {$wpdb->options} WHERE option_name = %s",
				$timeout_key
			)
		);

		if ( ! $timeout ) {
			return false;
		}

		$data = $this->get( $key );

		if ( false === $data ) {
			return false;
		}

		$now = time();
		$expires_at = absint( $timeout );

		return array(
			'key'          => $cache_key,
			'expires_at'   => $expires_at,
			'expires_in'   => max( 0, $expires_at - $now ),
			'is_expired'   => $expires_at < $now,
			'data_size'    => strlen( maybe_serialize( $data ) ),
			'cached_at'    => date( 'Y-m-d H:i:s', $expires_at - HOUR_IN_SECONDS ),
		);
	}
}
