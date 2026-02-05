<?php
/**
 * Logger helper functions.
 *
 * @package Marketing_Analytics_MCP
 */

namespace Marketing_Analytics_MCP\Utils;

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
