<?php
/**
 * PHPUnit bootstrap file for Marketing Analytics Chat plugin tests.
 *
 * @package Marketing_Analytics_MCP
 */

// Define WordPress constants for testing.
if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', '/tmp/wordpress/' );
}

if ( ! defined( 'WPINC' ) ) {
	define( 'WPINC', 'wp-includes' );
}

if ( ! defined( 'WP_DEBUG' ) ) {
	define( 'WP_DEBUG', true );
}

// Define plugin constants.
define( 'MARKETING_ANALYTICS_MCP_VERSION', '1.0.0' );
define( 'MARKETING_ANALYTICS_MCP_PATH', dirname( __DIR__ ) . '/' );
define( 'MARKETING_ANALYTICS_MCP_URL', 'http://localhost/wp-content/plugins/marketing-analytics-chat/' );
define( 'MARKETING_ANALYTICS_MCP_BASENAME', 'marketing-analytics-chat/marketing-analytics-chat.php' );

// Load Composer autoloader.
require_once dirname( __DIR__ ) . '/vendor/autoload.php';

// Mock WordPress functions for unit tests.
if ( ! function_exists( 'get_option' ) ) {
	/**
	 * Mock get_option function.
	 *
	 * @param string $option  Option name.
	 * @param mixed  $default Default value.
	 * @return mixed
	 */
	function get_option( $option, $default = false ) {
		global $mock_options;
		return isset( $mock_options[ $option ] ) ? $mock_options[ $option ] : $default;
	}
}

if ( ! function_exists( 'update_option' ) ) {
	/**
	 * Mock update_option function.
	 *
	 * @param string $option Option name.
	 * @param mixed  $value  Option value.
	 * @param bool   $autoload Whether to autoload.
	 * @return bool
	 */
	function update_option( $option, $value, $autoload = null ) {
		global $mock_options;
		$mock_options[ $option ] = $value;
		return true;
	}
}

if ( ! function_exists( 'add_option' ) ) {
	/**
	 * Mock add_option function.
	 *
	 * @param string $option Option name.
	 * @param mixed  $value  Option value.
	 * @param string $deprecated Deprecated.
	 * @param bool   $autoload Whether to autoload.
	 * @return bool
	 */
	function add_option( $option, $value = '', $deprecated = '', $autoload = 'yes' ) {
		global $mock_options;
		if ( isset( $mock_options[ $option ] ) ) {
			return false;
		}
		$mock_options[ $option ] = $value;
		return true;
	}
}

if ( ! function_exists( 'delete_option' ) ) {
	/**
	 * Mock delete_option function.
	 *
	 * @param string $option Option name.
	 * @return bool
	 */
	function delete_option( $option ) {
		global $mock_options;
		if ( isset( $mock_options[ $option ] ) ) {
			unset( $mock_options[ $option ] );
			return true;
		}
		return false;
	}
}

if ( ! function_exists( 'wp_json_encode' ) ) {
	/**
	 * Mock wp_json_encode function.
	 *
	 * @param mixed $data Data to encode.
	 * @param int   $options JSON options.
	 * @param int   $depth Max depth.
	 * @return string|false
	 */
	function wp_json_encode( $data, $options = 0, $depth = 512 ) {
		return json_encode( $data, $options, $depth );
	}
}

if ( ! function_exists( 'sanitize_text_field' ) ) {
	/**
	 * Mock sanitize_text_field function.
	 *
	 * @param string $str String to sanitize.
	 * @return string
	 */
	function sanitize_text_field( $str ) {
		return trim( strip_tags( $str ) );
	}
}

// Initialize mock options array.
$mock_options = array();
