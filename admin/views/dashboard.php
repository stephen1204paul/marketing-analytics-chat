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

$settings = get_option( 'marketing_analytics_mcp_settings', array() );
$platforms = isset( $settings['platforms'] ) ? $settings['platforms'] : array();

// Check actual credential existence instead of manual flags
$credential_manager = new Credential_Manager();
$clarity_connected = $credential_manager->has_credentials( 'clarity' );
$ga4_connected = $credential_manager->has_credentials( 'ga4' );
$gsc_connected = $credential_manager->has_credentials( 'gsc' );
?>

<div class="wrap marketing-analytics-mcp-dashboard">
	<h1><?php echo esc_html( get_admin_page_title() ); ?></h1>

	<div class="marketing-analytics-welcome">
		<h2><?php esc_html_e( 'Welcome to Marketing Analytics MCP', 'marketing-analytics-mcp' ); ?></h2>
		<p><?php esc_html_e( 'Connect your marketing analytics platforms to expose data via Model Context Protocol for AI assistants like Claude.', 'marketing-analytics-mcp' ); ?></p>
	</div>

	<div class="marketing-analytics-status-cards">
		<h3><?php esc_html_e( 'Platform Status', 'marketing-analytics-mcp' ); ?></h3>

		<div class="status-cards-grid">
			<!-- Microsoft Clarity -->
			<div class="status-card <?php echo $clarity_connected ? 'connected' : 'disconnected'; ?>">
				<div class="status-icon">
					<span class="dashicons dashicons-chart-area"></span>
				</div>
				<h4><?php esc_html_e( 'Microsoft Clarity', 'marketing-analytics-mcp' ); ?></h4>
				<p class="status-label">
					<?php
					if ( $clarity_connected ) {
						echo '<span class="status-badge connected">' . esc_html__( 'Connected', 'marketing-analytics-mcp' ) . '</span>';
					} else {
						echo '<span class="status-badge disconnected">' . esc_html__( 'Not Connected', 'marketing-analytics-mcp' ) . '</span>';
					}
					?>
				</p>
				<p class="status-description"><?php esc_html_e( 'Session recordings and heatmaps', 'marketing-analytics-mcp' ); ?></p>
				<a href="<?php echo esc_url( admin_url( 'admin.php?page=marketing-analytics-mcp-connections&tab=clarity' ) ); ?>" class="button">
					<?php esc_html_e( 'Configure', 'marketing-analytics-mcp' ); ?>
				</a>
			</div>

			<!-- Google Analytics 4 -->
			<div class="status-card <?php echo $ga4_connected ? 'connected' : 'disconnected'; ?>">
				<div class="status-icon">
					<span class="dashicons dashicons-chart-line"></span>
				</div>
				<h4><?php esc_html_e( 'Google Analytics 4', 'marketing-analytics-mcp' ); ?></h4>
				<p class="status-label">
					<?php
					if ( $ga4_connected ) {
						echo '<span class="status-badge connected">' . esc_html__( 'Connected', 'marketing-analytics-mcp' ) . '</span>';
					} else {
						echo '<span class="status-badge disconnected">' . esc_html__( 'Not Connected', 'marketing-analytics-mcp' ) . '</span>';
					}
					?>
				</p>
				<p class="status-description"><?php esc_html_e( 'Traffic and user behavior metrics', 'marketing-analytics-mcp' ); ?></p>
				<a href="<?php echo esc_url( admin_url( 'admin.php?page=marketing-analytics-mcp-connections&tab=ga4' ) ); ?>" class="button">
					<?php esc_html_e( 'Configure', 'marketing-analytics-mcp' ); ?>
				</a>
			</div>

			<!-- Google Search Console -->
			<div class="status-card <?php echo $gsc_connected ? 'connected' : 'disconnected'; ?>">
				<div class="status-icon">
					<span class="dashicons dashicons-search"></span>
				</div>
				<h4><?php esc_html_e( 'Google Search Console', 'marketing-analytics-mcp' ); ?></h4>
				<p class="status-label">
					<?php
					if ( $gsc_connected ) {
						echo '<span class="status-badge connected">' . esc_html__( 'Connected', 'marketing-analytics-mcp' ) . '</span>';
					} else {
						echo '<span class="status-badge disconnected">' . esc_html__( 'Not Connected', 'marketing-analytics-mcp' ) . '</span>';
					}
					?>
				</p>
				<p class="status-description"><?php esc_html_e( 'Search performance and indexing', 'marketing-analytics-mcp' ); ?></p>
				<a href="<?php echo esc_url( admin_url( 'admin.php?page=marketing-analytics-mcp-connections&tab=gsc' ) ); ?>" class="button">
					<?php esc_html_e( 'Configure', 'marketing-analytics-mcp' ); ?>
				</a>
			</div>
		</div>
	</div>

	<div class="marketing-analytics-mcp-info">
		<h3><?php esc_html_e( 'MCP Server Configuration', 'marketing-analytics-mcp' ); ?></h3>
		<p><?php esc_html_e( 'Use this endpoint URL to connect AI assistants via Model Context Protocol:', 'marketing-analytics-mcp' ); ?></p>
		<div class="mcp-endpoint-box">
			<code class="mcp-endpoint"><?php echo esc_url( rest_url( 'mcp/mcp-adapter-default-server' ) ); ?></code>
			<button type="button" class="button button-secondary copy-endpoint">
				<?php esc_html_e( 'Copy URL', 'marketing-analytics-mcp' ); ?>
			</button>
		</div>
		<p class="description">
			<?php
			printf(
				/* translators: %s: link to documentation */
				esc_html__( 'Learn how to configure Claude Desktop and other MCP clients in our %s.', 'marketing-analytics-mcp' ),
				'<a href="https://github.com/yourusername/marketing-analytics-mcp/blob/main/docs/setup-guides/" target="_blank">' . esc_html__( 'documentation', 'marketing-analytics-mcp' ) . '</a>'
			);
			?>
		</p>
	</div>

	<div class="marketing-analytics-getting-started">
		<h3><?php esc_html_e( 'Getting Started', 'marketing-analytics-mcp' ); ?></h3>
		<ol>
			<li><?php esc_html_e( 'Connect at least one analytics platform using the Connections page', 'marketing-analytics-mcp' ); ?></li>
			<li><?php esc_html_e( 'Configure your AI assistant (e.g., Claude Desktop) to use the MCP endpoint above', 'marketing-analytics-mcp' ); ?></li>
			<li><?php esc_html_e( 'Ask your AI assistant to analyze your marketing data', 'marketing-analytics-mcp' ); ?></li>
		</ol>
	</div>
</div>
