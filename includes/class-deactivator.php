<?php
/**
 * Plugin Deactivation Handler
 *
 * @package Marketing_Analytics_MCP
 */

namespace Marketing_Analytics_MCP;

/**
 * Fired during plugin deactivation
 */
class Deactivator {

	/**
	 * Deactivate the plugin
	 *
	 * - Clear scheduled cron jobs
	 * - Clear transient caches
	 * - Flush rewrite rules
	 */
	public static function deactivate() {
		// Clear all plugin transients
		self::clear_all_caches();

		// Clear any scheduled cron jobs
		self::clear_scheduled_events();

		// Flush rewrite rules
		flush_rewrite_rules();
	}

	/**
	 * Clear all plugin caches
	 */
	private static function clear_all_caches() {
		global $wpdb;

		// Delete all transients with our prefix.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Cleanup operation during deactivation, caching not applicable.
		$wpdb->query(
			$wpdb->prepare(
				"DELETE FROM {$wpdb->options} WHERE option_name LIKE %s OR option_name LIKE %s",
				$wpdb->esc_like( '_transient_marketing_analytics_mcp_' ) . '%',
				$wpdb->esc_like( '_transient_timeout_marketing_analytics_mcp_' ) . '%'
			)
		);
	}

	/**
	 * Clear scheduled cron events
	 */
	private static function clear_scheduled_events() {
		// Clear any scheduled events (if we add cron jobs in the future)
		$scheduled_hooks = array(
			'marketing_analytics_mcp_daily_cleanup',
			'marketing_analytics_mcp_refresh_tokens',
		);

		foreach ( $scheduled_hooks as $hook ) {
			$timestamp = wp_next_scheduled( $hook );
			if ( $timestamp ) {
				wp_unschedule_event( $timestamp, $hook );
			}
		}
	}
}
