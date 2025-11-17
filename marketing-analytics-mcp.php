<?php
/**
 * Plugin Name: Marketing Analytics MCP
 * Plugin URI: https://github.com/yourusername/marketing-analytics-mcp
 * Description: Exposes marketing analytics data (Microsoft Clarity, Google Analytics 4, Google Search Console) via Model Context Protocol for AI assistants.
 * Version: 1.0.0
 * Requires at least: 6.0
 * Requires PHP: 7.4
 * Author: Marketing Analytics MCP
 * Author URI: https://yourwebsite.com
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: marketing-analytics-mcp
 * Domain Path: /languages
 */

namespace Marketing_Analytics_MCP;

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
	die;
}

// Plugin version
define( 'MARKETING_ANALYTICS_MCP_VERSION', '1.0.0' );
define( 'MARKETING_ANALYTICS_MCP_PATH', plugin_dir_path( __FILE__ ) );
define( 'MARKETING_ANALYTICS_MCP_URL', plugin_dir_url( __FILE__ ) );
define( 'MARKETING_ANALYTICS_MCP_BASENAME', plugin_basename( __FILE__ ) );

// Load Composer autoloader
if ( file_exists( MARKETING_ANALYTICS_MCP_PATH . 'vendor/autoload.php' ) ) {
	require_once MARKETING_ANALYTICS_MCP_PATH . 'vendor/autoload.php';
} else {
	// Display admin notice if dependencies are missing
	add_action( 'admin_notices', function() {
		?>
		<div class="notice notice-error">
			<p>
				<strong>Marketing Analytics MCP:</strong>
				<?php esc_html_e( 'Dependencies are missing. Please run "composer install" in the plugin directory.', 'marketing-analytics-mcp' ); ?>
			</p>
		</div>
		<?php
	} );
	return;
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
	// Check if the Plugin class exists
	if ( ! class_exists( __NAMESPACE__ . '\Plugin' ) ) {
		return;
	}

	$plugin = new Plugin();
	$plugin->run();
}

// Run the plugin after plugins are loaded to ensure dependencies are available
add_action( 'plugins_loaded', __NAMESPACE__ . '\run_marketing_analytics_mcp' );
