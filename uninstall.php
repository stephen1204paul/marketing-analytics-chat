<?php
/**
 * Plugin Uninstall Handler
 *
 * Fired when the plugin is uninstalled.
 *
 * @package Marketing_Analytics_MCP
 */

// If uninstall not called from WordPress, exit
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

/**
 * Delete all plugin data
 *
 * This includes:
 * - Plugin options
 * - Encryption key (irreversibly destroys encrypted credentials)
 * - All transient caches
 * - API rate limit counters
 */
function marketing_analytics_mcp_uninstall() {
	global $wpdb;

	// Delete main plugin options
	delete_option( 'marketing_analytics_mcp_settings' );
	delete_option( 'marketing_analytics_mcp_encryption_key' );

	// Delete encrypted credentials
	delete_option( 'marketing_analytics_mcp_credentials_clarity' );
	delete_option( 'marketing_analytics_mcp_credentials_ga4' );
	delete_option( 'marketing_analytics_mcp_credentials_gsc' );

	// Delete OAuth tokens
	delete_option( 'marketing_analytics_mcp_oauth_tokens' );

	// Delete rate limit counters
	delete_option( 'marketing_analytics_mcp_rate_limits' );

	// Delete all transients (properly escape LIKE patterns)
	$transient_pattern = $wpdb->esc_like( '_transient_marketing_analytics_mcp_' ) . '%';
	$timeout_pattern   = $wpdb->esc_like( '_transient_timeout_marketing_analytics_mcp_' ) . '%';

	// Fetch matching transients and delete via API to clear caches.
	$cache_group       = 'marketing_analytics_mcp_uninstall';
	$cache_key         = 'transient_options';
	$transient_options = wp_cache_get( $cache_key, $cache_group );

	if ( false === $transient_options ) {
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Needed to locate all matching transients for cleanup with transient cache.
		$transient_options = $wpdb->get_col(
			$wpdb->prepare(
				"SELECT option_name FROM {$wpdb->options} WHERE option_name LIKE %s OR option_name LIKE %s",
				$transient_pattern,
				$timeout_pattern
			)
		);
		wp_cache_set( $cache_key, $transient_options, $cache_group, MINUTE_IN_SECONDS );
	}

	if ( ! empty( $transient_options ) ) {
		foreach ( $transient_options as $option_name ) {
			delete_option( $option_name );
		}
	}

	// Clear any scheduled cron jobs
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

	// Note: We don't delete any custom database tables here
	// because this plugin uses wp_options for everything
}

marketing_analytics_mcp_uninstall();
