<?php
/**
 * Admin Interface Handler
 *
 * @package Marketing_Analytics_MCP
 */

namespace Marketing_Analytics_MCP\Admin;

/**
 * Handles admin menu and pages
 */
class Admin {

	/**
	 * Add admin menu pages
	 */
	public function add_admin_menu() {
		// Main menu page - Dashboard
		add_menu_page(
			__( 'Marketing Analytics', 'marketing-analytics-mcp' ),
			__( 'Marketing Analytics', 'marketing-analytics-mcp' ),
			'manage_options',
			'marketing-analytics-mcp',
			array( $this, 'render_dashboard_page' ),
			'dashicons-chart-line',
			30
		);

		// Dashboard submenu (same as main)
		add_submenu_page(
			'marketing-analytics-mcp',
			__( 'Dashboard', 'marketing-analytics-mcp' ),
			__( 'Dashboard', 'marketing-analytics-mcp' ),
			'manage_options',
			'marketing-analytics-mcp',
			array( $this, 'render_dashboard_page' )
		);

		// AI Chat page
		add_submenu_page(
			'marketing-analytics-mcp',
			__( 'AI Assistant', 'marketing-analytics-mcp' ),
			__( 'AI Assistant', 'marketing-analytics-mcp' ),
			'manage_options',
			'marketing-analytics-mcp-chat',
			array( $this, 'render_chat_page' )
		);

		// Connections page
		add_submenu_page(
			'marketing-analytics-mcp',
			__( 'Connections', 'marketing-analytics-mcp' ),
			__( 'Connections', 'marketing-analytics-mcp' ),
			'manage_options',
			'marketing-analytics-mcp-connections',
			array( $this, 'render_connections_page' )
		);

		// Settings page
		add_submenu_page(
			'marketing-analytics-mcp',
			__( 'Settings', 'marketing-analytics-mcp' ),
			__( 'Settings', 'marketing-analytics-mcp' ),
			'manage_options',
			'marketing-analytics-mcp-settings',
			array( $this, 'render_settings_page' )
		);
	}

	/**
	 * Enqueue admin styles
	 *
	 * @param string $hook Current admin page hook.
	 */
	public function enqueue_styles( $hook ) {
		// Only load on our admin pages
		if ( strpos( $hook, 'marketing-analytics-mcp' ) === false ) {
			return;
		}

		wp_enqueue_style(
			'marketing-analytics-mcp-admin',
			MARKETING_ANALYTICS_MCP_URL . 'admin/css/admin-styles.css',
			array(),
			MARKETING_ANALYTICS_MCP_VERSION
		);

		// Enqueue chat interface styles on chat page
		if ( strpos( $hook, 'marketing-analytics-mcp-chat' ) !== false ) {
			wp_enqueue_style(
				'marketing-analytics-mcp-chat',
				MARKETING_ANALYTICS_MCP_URL . 'admin/css/chat-interface.css',
				array(),
				MARKETING_ANALYTICS_MCP_VERSION
			);
		}
	}

	/**
	 * Enqueue admin scripts
	 *
	 * @param string $hook Current admin page hook.
	 */
	public function enqueue_scripts( $hook ) {
		// Only load on our admin pages
		if ( strpos( $hook, 'marketing-analytics-mcp' ) === false ) {
			return;
		}

		wp_enqueue_script(
			'marketing-analytics-mcp-admin',
			MARKETING_ANALYTICS_MCP_URL . 'admin/js/admin-scripts.js',
			array( 'jquery' ),
			MARKETING_ANALYTICS_MCP_VERSION,
			true
		);

		// Localize script with data
		wp_localize_script(
			'marketing-analytics-mcp-admin',
			'marketingAnalyticsMCP',
			array(
				'ajaxUrl' => admin_url( 'admin-ajax.php' ),
				'nonce'   => wp_create_nonce( 'marketing-analytics-mcp-admin' ),
				'strings' => array(
					'testing'   => __( 'Testing connection...', 'marketing-analytics-mcp' ),
					'success'   => __( 'Connection successful!', 'marketing-analytics-mcp' ),
					'error'     => __( 'Connection failed', 'marketing-analytics-mcp' ),
					'saveError' => __( 'Error saving settings', 'marketing-analytics-mcp' ),
				),
			)
		);

		// Enqueue chat interface script on chat page
		if ( strpos( $hook, 'marketing-analytics-mcp-chat' ) !== false ) {
			wp_enqueue_script(
				'marketing-analytics-mcp-chat',
				MARKETING_ANALYTICS_MCP_URL . 'admin/js/chat-interface.js',
				array( 'jquery' ),
				MARKETING_ANALYTICS_MCP_VERSION,
				true
			);
		}
	}

	/**
	 * Render dashboard page
	 */
	public function render_dashboard_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		require_once MARKETING_ANALYTICS_MCP_PATH . 'admin/views/dashboard.php';
	}

	/**
	 * Render connections page
	 */
	public function render_connections_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		require_once MARKETING_ANALYTICS_MCP_PATH . 'admin/views/connections.php';
	}

	/**
	 * Render chat page
	 */
	public function render_chat_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		// Check if chat feature is available
		if ( ! class_exists( 'Marketing_Analytics_MCP\Chat\Chat_Manager' ) ) {
			echo '<div class="wrap"><h1>' . esc_html__( 'AI Assistant', 'marketing-analytics-mcp' ) . '</h1>';
			echo '<div class="notice notice-error"><p>' . esc_html__( 'Chat feature is not available. Please run "composer dump-autoload" in the plugin directory.', 'marketing-analytics-mcp' ) . '</p></div>';
			echo '</div>';
			return;
		}

		require_once MARKETING_ANALYTICS_MCP_PATH . 'admin/views/chat.php';
	}

	/**
	 * Render settings page
	 */
	public function render_settings_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		require_once MARKETING_ANALYTICS_MCP_PATH . 'admin/views/settings.php';
	}
}
