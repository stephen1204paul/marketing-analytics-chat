<?php
/**
 * Plugin Name: Marketing Analytics Chat
 * Plugin URI: https://github.com/stephen1204paul/marketing-analytics-chat
 * Description: Chat with your marketing analytics data using AI. Connects Google Analytics 4, Search Console, Microsoft Clarity, and more.
 * Version: 0.1.1
 * Requires at least: 6.9
 * Requires PHP: 8.1
 * Requires Plugins: mcp-adapter
 * Author: Stephen Paul Samynathan
 * Author URI: https://www.specflux.com/author/stephen/
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: marketing-analytics-chat
 * Domain Path: /languages
 */

namespace Marketing_Analytics_MCP;

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
	die;
}

// Plugin version
define( 'MARKETING_ANALYTICS_MCP_VERSION', '0.1.1' );
define( 'MARKETING_ANALYTICS_MCP_PATH', plugin_dir_path( __FILE__ ) );
define( 'MARKETING_ANALYTICS_MCP_URL', plugin_dir_url( __FILE__ ) );
define( 'MARKETING_ANALYTICS_MCP_BASENAME', plugin_basename( __FILE__ ) );

// Load Composer autoloader
if ( file_exists( MARKETING_ANALYTICS_MCP_PATH . 'vendor/autoload.php' ) ) {
	require_once MARKETING_ANALYTICS_MCP_PATH . 'vendor/autoload.php';
} else {
	// Display admin notice if dependencies are missing
	add_action(
		'admin_notices',
		function () {
			?>
		<div class="notice notice-error">
			<p>
				<strong>Marketing Analytics Chat:</strong>
				<?php esc_html_e( 'Dependencies are missing. Please run "composer install" in the plugin directory.', 'marketing-analytics-chat' ); ?>
			</p>
		</div>
			<?php
		}
	);
	return;
}

/**
 * Check for required MCP Adapter plugin
 */
function check_plugin_dependencies() {
	if ( ! function_exists( 'is_plugin_active' ) ) {
		require_once ABSPATH . 'wp-admin/includes/plugin.php';
	}

	// Check if MCP Adapter plugin is active
	if ( ! is_plugin_active( 'mcp-adapter/mcp-adapter.php' ) ) {
		add_action(
			'admin_notices',
			function () {
				?>
			<div class="notice notice-error">
				<p>
					<strong><?php esc_html_e( 'Marketing Analytics Chat:', 'marketing-analytics-chat' ); ?></strong>
					<?php esc_html_e( 'This plugin requires the MCP Adapter plugin to be installed and activated.', 'marketing-analytics-chat' ); ?>
				</p>
				<p>
					<a href="https://wordpress.org/plugins/mcp-adapter/" target="_blank"><?php esc_html_e( 'Get MCP Adapter from WordPress.org', 'marketing-analytics-chat' ); ?></a>
				</p>
			</div>
				<?php
			}
		);
		return false;
	}
	return true;
}

/**
 * Activation hook
 */
function activate_marketing_analytics_mcp() {
	require_once MARKETING_ANALYTICS_MCP_PATH . 'includes/class-activator.php';
	Activator::activate();
}
register_activation_hook( __FILE__, __NAMESPACE__ . '\activate_marketing_analytics_mcp' );

/**
 * Deactivation hook
 */
function deactivate_marketing_analytics_mcp() {
	require_once MARKETING_ANALYTICS_MCP_PATH . 'includes/class-deactivator.php';
	Deactivator::deactivate();
}
register_deactivation_hook( __FILE__, __NAMESPACE__ . '\deactivate_marketing_analytics_mcp' );

/**
 * Initialize plugin
 */
function run_marketing_analytics_mcp() {
	// Check for required plugin dependencies
	if ( ! check_plugin_dependencies() ) {
		return;
	}

	// Check if the Plugin class exists
	if ( ! class_exists( __NAMESPACE__ . '\Plugin' ) ) {
		return;
	}

	$plugin = new Plugin();
	$plugin->run();
}

// Run the plugin after plugins are loaded to ensure dependencies are available
add_action( 'plugins_loaded', __NAMESPACE__ . '\run_marketing_analytics_mcp' );
