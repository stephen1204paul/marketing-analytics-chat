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
			__( 'Marketing Analytics', 'marketing-analytics-chat' ),
			__( 'Marketing Analytics', 'marketing-analytics-chat' ),
			'manage_options',
			'marketing-analytics-chat',
			array( $this, 'render_dashboard_page' ),
			'dashicons-chart-line',
			30
		);

		// Dashboard submenu (same as main)
		add_submenu_page(
			'marketing-analytics-chat',
			__( 'Dashboard', 'marketing-analytics-chat' ),
			__( 'Dashboard', 'marketing-analytics-chat' ),
			'manage_options',
			'marketing-analytics-chat',
			array( $this, 'render_dashboard_page' )
		);

		// AI Chat page
		add_submenu_page(
			'marketing-analytics-chat',
			__( 'AI Assistant', 'marketing-analytics-chat' ),
			__( 'AI Assistant', 'marketing-analytics-chat' ),
			'manage_options',
			'marketing-analytics-chat-ai-assistant',
			array( $this, 'render_chat_page' )
		);

		// Connections page
		add_submenu_page(
			'marketing-analytics-chat',
			__( 'Connections', 'marketing-analytics-chat' ),
			__( 'Connections', 'marketing-analytics-chat' ),
			'manage_options',
			'marketing-analytics-chat-connections',
			array( $this, 'render_connections_page' )
		);

		// Custom Prompts page
		add_submenu_page(
			'marketing-analytics-chat',
			__( 'Custom Prompts', 'marketing-analytics-chat' ),
			__( 'Custom Prompts', 'marketing-analytics-chat' ),
			'manage_options',
			'marketing-analytics-chat-prompts',
			array( $this, 'render_prompts_page' )
		);

		// Settings page
		add_submenu_page(
			'marketing-analytics-chat',
			__( 'Settings', 'marketing-analytics-chat' ),
			__( 'Settings', 'marketing-analytics-chat' ),
			'manage_options',
			'marketing-analytics-chat-settings',
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
		if ( strpos( $hook, 'marketing-analytics-chat' ) === false ) {
			return;
		}

		wp_enqueue_style(
			'marketing-analytics-chat-admin',
			MARKETING_ANALYTICS_MCP_URL . 'admin/css/admin-styles.css',
			array(),
			MARKETING_ANALYTICS_MCP_VERSION
		);

		// Enqueue chat interface styles on chat page
		if ( strpos( $hook, 'marketing-analytics-chat-ai-assistant' ) !== false ) {
			wp_enqueue_style(
				'marketing-analytics-chat',
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
		if ( strpos( $hook, 'marketing-analytics-chat' ) === false ) {
			return;
		}

		wp_enqueue_script(
			'marketing-analytics-chat-admin',
			MARKETING_ANALYTICS_MCP_URL . 'admin/js/admin-scripts.js',
			array( 'jquery' ),
			MARKETING_ANALYTICS_MCP_VERSION,
			true
		);

		// Localize script with data
		wp_localize_script(
			'marketing-analytics-chat-admin',
			'marketingAnalyticsMCP',
			array(
				'ajaxUrl' => admin_url( 'admin-ajax.php' ),
				'nonce'   => wp_create_nonce( 'marketing-analytics-chat-admin' ),
				'strings' => array(
					'testing'   => __( 'Testing connection...', 'marketing-analytics-chat' ),
					'success'   => __( 'Connection successful!', 'marketing-analytics-chat' ),
					'error'     => __( 'Connection failed', 'marketing-analytics-chat' ),
					'saveError' => __( 'Error saving settings', 'marketing-analytics-chat' ),
				),
			)
		);

		// Enqueue chat interface script on chat page
		if ( strpos( $hook, 'marketing-analytics-chat-ai-assistant' ) !== false ) {
			wp_enqueue_script(
				'marketing-analytics-chat',
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
			echo '<div class="wrap"><h1>' . esc_html__( 'AI Assistant', 'marketing-analytics-chat' ) . '</h1>';
			echo '<div class="notice notice-error"><p>' . esc_html__( 'Chat feature is not available. Please run "composer dump-autoload" in the plugin directory.', 'marketing-analytics-chat' ) . '</p></div>';
			echo '</div>';
			return;
		}

		require_once MARKETING_ANALYTICS_MCP_PATH . 'admin/views/chat.php';
	}

	/**
	 * Render prompts page
	 */
	public function render_prompts_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		require_once MARKETING_ANALYTICS_MCP_PATH . 'admin/views/prompts.php';
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
