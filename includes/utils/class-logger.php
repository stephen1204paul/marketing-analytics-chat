<?php
/**
 * Logger utility for Marketing Analytics Chat
 *
 * Centralizes logging and respects debug mode settings.
 *
 * @package Marketing_Analytics_MCP
 */

namespace Marketing_Analytics_MCP\Utils;

/**
 * Logger class for debug and error logging.
 */
class Logger {

	/**
	 * Log a debug message (only when WP_DEBUG is enabled).
	 *
	 * @param string $message The message to log.
	 * @return void
	 */
	public static function debug( $message ) {
		if ( ! self::is_debug_enabled() ) {
			return;
		}

		if ( is_array( $message ) || is_object( $message ) ) {
			$message = print_r( $message, true ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_print_r
		}

		error_log( '[Marketing Analytics Chat] ' . $message ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
	}

	/**
	 * Log an error message (always logged regardless of debug mode).
	 *
	 * @param string $message The error message to log.
	 * @return void
	 */
	public static function error( $message ) {
		if ( is_array( $message ) || is_object( $message ) ) {
			$message = print_r( $message, true ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_print_r
		}

		error_log( '[Marketing Analytics Chat ERROR] ' . $message ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
	}

	/**
	 * Log a warning message (only when WP_DEBUG is enabled).
	 *
	 * @param string $message The warning message to log.
	 * @return void
	 */
	public static function warning( $message ) {
		if ( ! self::is_debug_enabled() ) {
			return;
		}

		if ( is_array( $message ) || is_object( $message ) ) {
			$message = print_r( $message, true ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_print_r
		}

		error_log( '[Marketing Analytics Chat WARNING] ' . $message ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
	}

	/**
	 * Check if debug logging is enabled.
	 *
	 * @return bool True if debug logging is enabled.
	 */
	public static function is_debug_enabled() {
		// Check plugin-specific debug setting first
		$plugin_debug = get_option( 'marketing_analytics_mcp_debug_mode', false );
		if ( $plugin_debug ) {
			return true;
		}

		// Fall back to WP_DEBUG constant
		return defined( 'WP_DEBUG' ) && WP_DEBUG;
	}
}

/**
 * Global helper function for debug logging.
 *
 * @param string $message The message to log.
 * @return void
 */
function mcp_log_debug( $message ) {
	Logger::debug( $message );
}

/**
 * Global helper function for error logging.
 *
 * @param string $message The error message to log.
 * @return void
 */
function mcp_log_error( $message ) {
	Logger::error( $message );
}

/**
 * Debug-only error_log wrapper.
 * Only logs when WP_DEBUG is enabled.
 *
 * @param string $message The message to log.
 * @return void
 */
function mcp_debug_log( $message ) {
	if ( ! defined( 'WP_DEBUG' ) || ! WP_DEBUG ) {
		return;
	}
	error_log( $message ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
}
