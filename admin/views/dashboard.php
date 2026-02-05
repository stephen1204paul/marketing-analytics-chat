<?php
/**
 * Dashboard Page Template
 *
 * @package Marketing_Analytics_MCP
 */

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use Marketing_Analytics_MCP\Credentials\Credential_Manager;

$settings  = get_option( 'marketing_analytics_mcp_settings', array() );
$platforms = isset( $settings['platforms'] ) ? $settings['platforms'] : array();

// Check actual credential existence instead of manual flags
$credential_manager = new Credential_Manager();
$clarity_connected  = $credential_manager->has_credentials( 'clarity' );
$ga4_connected      = $credential_manager->has_credentials( 'ga4' );
$gsc_connected      = $credential_manager->has_credentials( 'gsc' );
?>

<div class="wrap marketing-analytics-chat-dashboard">
	<h1><?php echo esc_html( get_admin_page_title() ); ?></h1>

	<div class="marketing-analytics-welcome">
		<h2><?php esc_html_e( 'Welcome to Marketing Analytics Chat', 'marketing-analytics-chat' ); ?></h2>
		<p><?php esc_html_e( 'Chat with your marketing analytics data using AI. Connect Google Analytics 4, Search Console, Microsoft Clarity, and more to get instant insights.', 'marketing-analytics-chat' ); ?></p>
	</div>

	<!-- AI Assistant Quick Access -->
	<div class="marketing-analytics-quick-actions">
		<div class="quick-action-card">
			<div class="quick-action-icon">
				<span class="dashicons dashicons-format-chat"></span>
			</div>
			<div class="quick-action-content">
				<h3><?php esc_html_e( 'AI Assistant', 'marketing-analytics-chat' ); ?></h3>
				<p><?php esc_html_e( 'Start chatting with your analytics data right now', 'marketing-analytics-chat' ); ?></p>
				<a href="<?php echo esc_url( admin_url( 'admin.php?page=marketing-analytics-chat-ai-assistant' ) ); ?>" class="button button-primary">
					<?php esc_html_e( 'Open AI Chat', 'marketing-analytics-chat' ); ?>
				</a>
			</div>
		</div>
		<div class="quick-action-card">
			<div class="quick-action-icon">
				<span class="dashicons dashicons-editor-code"></span>
			</div>
			<div class="quick-action-content">
				<h3><?php esc_html_e( 'Custom Prompts', 'marketing-analytics-chat' ); ?></h3>
				<p><?php esc_html_e( 'Create reusable prompt templates for common analyses', 'marketing-analytics-chat' ); ?></p>
				<a href="<?php echo esc_url( admin_url( 'admin.php?page=marketing-analytics-chat-prompts' ) ); ?>" class="button">
					<?php esc_html_e( 'Manage Prompts', 'marketing-analytics-chat' ); ?>
				</a>
			</div>
		</div>
	</div>

	<div class="marketing-analytics-status-cards">
		<h3><?php esc_html_e( 'Platform Status', 'marketing-analytics-chat' ); ?></h3>

		<div class="status-cards-grid">
			<!-- Microsoft Clarity -->
			<div class="status-card <?php echo esc_attr( $clarity_connected ? 'connected' : 'disconnected' ); ?>">
				<div class="status-icon">
					<span class="dashicons dashicons-chart-area"></span>
				</div>
				<h4><?php esc_html_e( 'Microsoft Clarity', 'marketing-analytics-chat' ); ?></h4>
				<p class="status-label">
					<?php
					if ( $clarity_connected ) {
						echo '<span class="status-badge connected">' . esc_html__( 'Connected', 'marketing-analytics-chat' ) . '</span>';
					} else {
						echo '<span class="status-badge disconnected">' . esc_html__( 'Not Connected', 'marketing-analytics-chat' ) . '</span>';
					}
					?>
				</p>
				<p class="status-description"><?php esc_html_e( 'Session recordings and heatmaps', 'marketing-analytics-chat' ); ?></p>
				<a href="<?php echo esc_url( admin_url( 'admin.php?page=marketing-analytics-chat-connections&tab=clarity' ) ); ?>" class="button">
					<?php esc_html_e( 'Configure', 'marketing-analytics-chat' ); ?>
				</a>
			</div>

			<!-- Google Analytics 4 -->
			<div class="status-card <?php echo esc_attr( $ga4_connected ? 'connected' : 'disconnected' ); ?>">
				<div class="status-icon">
					<span class="dashicons dashicons-chart-line"></span>
				</div>
				<h4><?php esc_html_e( 'Google Analytics 4', 'marketing-analytics-chat' ); ?></h4>
				<p class="status-label">
					<?php
					if ( $ga4_connected ) {
						echo '<span class="status-badge connected">' . esc_html__( 'Connected', 'marketing-analytics-chat' ) . '</span>';
					} else {
						echo '<span class="status-badge disconnected">' . esc_html__( 'Not Connected', 'marketing-analytics-chat' ) . '</span>';
					}
					?>
				</p>
				<p class="status-description"><?php esc_html_e( 'Traffic and user behavior metrics', 'marketing-analytics-chat' ); ?></p>
				<a href="<?php echo esc_url( admin_url( 'admin.php?page=marketing-analytics-chat-connections&tab=ga4' ) ); ?>" class="button">
					<?php esc_html_e( 'Configure', 'marketing-analytics-chat' ); ?>
				</a>
			</div>

			<!-- Google Search Console -->
			<div class="status-card <?php echo esc_attr( $gsc_connected ? 'connected' : 'disconnected' ); ?>">
				<div class="status-icon">
					<span class="dashicons dashicons-search"></span>
				</div>
				<h4><?php esc_html_e( 'Google Search Console', 'marketing-analytics-chat' ); ?></h4>
				<p class="status-label">
					<?php
					if ( $gsc_connected ) {
						echo '<span class="status-badge connected">' . esc_html__( 'Connected', 'marketing-analytics-chat' ) . '</span>';
					} else {
						echo '<span class="status-badge disconnected">' . esc_html__( 'Not Connected', 'marketing-analytics-chat' ) . '</span>';
					}
					?>
				</p>
				<p class="status-description"><?php esc_html_e( 'Search performance and indexing', 'marketing-analytics-chat' ); ?></p>
				<a href="<?php echo esc_url( admin_url( 'admin.php?page=marketing-analytics-chat-connections&tab=gsc' ) ); ?>" class="button">
					<?php esc_html_e( 'Configure', 'marketing-analytics-chat' ); ?>
				</a>
			</div>
		</div>
	</div>

	<div class="marketing-analytics-getting-started">
		<h3><?php esc_html_e( 'Getting Started', 'marketing-analytics-chat' ); ?></h3>
		<ol>
			<li>
				<strong><?php esc_html_e( 'Connect Your Analytics', 'marketing-analytics-chat' ); ?></strong>
				<br>
				<?php esc_html_e( 'Connect at least one analytics platform using the ', 'marketing-analytics-chat' ); ?>
				<a href="<?php echo esc_url( admin_url( 'admin.php?page=marketing-analytics-chat-connections' ) ); ?>"><?php esc_html_e( 'Connections page', 'marketing-analytics-chat' ); ?></a>
			</li>
			<li>
				<strong><?php esc_html_e( 'Start Chatting', 'marketing-analytics-chat' ); ?></strong>
				<br>
				<?php esc_html_e( 'Open the ', 'marketing-analytics-chat' ); ?>
				<a href="<?php echo esc_url( admin_url( 'admin.php?page=marketing-analytics-chat-ai-assistant' ) ); ?>"><?php esc_html_e( 'AI Assistant', 'marketing-analytics-chat' ); ?></a>
				<?php esc_html_e( ' and ask questions about your marketing data', 'marketing-analytics-chat' ); ?>
			</li>
			<li>
				<strong><?php esc_html_e( 'Save Common Prompts', 'marketing-analytics-chat' ); ?></strong>
				<br>
				<?php esc_html_e( 'Create ', 'marketing-analytics-chat' ); ?>
				<a href="<?php echo esc_url( admin_url( 'admin.php?page=marketing-analytics-chat-prompts' ) ); ?>"><?php esc_html_e( 'custom prompts', 'marketing-analytics-chat' ); ?></a>
				<?php esc_html_e( ' for analyses you run frequently', 'marketing-analytics-chat' ); ?>
			</li>
		</ol>
	</div>

	<!-- Advanced: External AI Assistants -->
	<div class="marketing-analytics-advanced">
		<details>
			<summary><h3><?php esc_html_e( 'Advanced: Connect External AI Assistants', 'marketing-analytics-chat' ); ?></h3></summary>
			<p><?php esc_html_e( 'You can also connect external AI assistants like Claude Desktop using the MCP endpoint below:', 'marketing-analytics-chat' ); ?></p>
			<div class="mcp-endpoint-box">
				<code class="mcp-endpoint"><?php echo esc_url( rest_url( 'mcp/mcp-adapter-default-server' ) ); ?></code>
				<button type="button" class="button button-secondary copy-endpoint">
					<?php esc_html_e( 'Copy URL', 'marketing-analytics-chat' ); ?>
				</button>
			</div>
			<p class="description">
				<?php
				printf(
					/* translators: %s: link to documentation */
					esc_html__( 'Learn how to configure Claude Desktop and other MCP clients in our %s.', 'marketing-analytics-chat' ),
					'<a href="https://github.com/stephen1204paul/marketing-analytics-chat/blob/main/docs/setup-guides/" target="_blank">' . esc_html__( 'documentation', 'marketing-analytics-chat' ) . '</a>'
				);
				?>
			</p>
		</details>
	</div>
</div>
