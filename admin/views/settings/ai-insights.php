<?php
/**
 * AI Insights Settings Page
 *
 * @package Marketing_Analytics_MCP
 * @subpackage Admin\Views\Settings
 */

// Don't allow direct access
if ( ! defined( 'WPINC' ) ) {
	die;
}

use Marketing_Analytics_MCP\AI\Insights_Generator;

// Get current settings
$ai_enabled = get_option( 'marketing_analytics_mcp_ai_insights_enabled', false );
$ai_model   = get_option( 'marketing_analytics_mcp_ai_model', 'claude-3-sonnet' );
$ai_api_key = get_option( 'marketing_analytics_mcp_ai_api_key', '' );

// Get usage stats
$insights_generator = new Insights_Generator();
$usage_stats        = $insights_generator->get_usage_stats();
$estimated_cost     = $insights_generator->estimate_monthly_cost();
?>

<div class="wrap">
	<h1><?php esc_html_e( 'AI Insights Settings', 'marketing-analytics-chat' ); ?></h1>

	<div class="notice notice-info">
		<p>
			<?php esc_html_e( 'AI-powered insights transform your raw analytics data into actionable recommendations using advanced language models.', 'marketing-analytics-chat' ); ?>
		</p>
	</div>

	<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
		<?php wp_nonce_field( 'marketing_analytics_ai_settings', 'ai_settings_nonce' ); ?>
		<input type="hidden" name="action" value="marketing_analytics_save_ai_settings">

		<table class="form-table">
			<tbody>
				<tr>
					<th scope="row">
						<label for="ai_enabled"><?php esc_html_e( 'Enable AI Insights', 'marketing-analytics-chat' ); ?></label>
					</th>
					<td>
						<label class="switch">
							<input type="checkbox" id="ai_enabled" name="ai_enabled" value="1" <?php checked( $ai_enabled ); ?>>
							<span class="slider round"></span>
						</label>
						<p class="description">
							<?php esc_html_e( 'Enable automatic AI-powered insights generation for analytics data.', 'marketing-analytics-chat' ); ?>
						</p>
					</td>
				</tr>

				<tr>
					<th scope="row">
						<label for="ai_model"><?php esc_html_e( 'AI Model', 'marketing-analytics-chat' ); ?></label>
					</th>
					<td>
						<select id="ai_model" name="ai_model" class="regular-text">
							<option value="claude-3-sonnet" <?php selected( $ai_model, 'claude-3-sonnet' ); ?>>Claude 3 Sonnet</option>
							<option value="claude-3-opus" <?php selected( $ai_model, 'claude-3-opus' ); ?>>Claude 3 Opus (Premium)</option>
							<option value="gpt-4" <?php selected( $ai_model, 'gpt-4' ); ?>>GPT-4</option>
							<option value="gpt-4-turbo" <?php selected( $ai_model, 'gpt-4-turbo' ); ?>>GPT-4 Turbo</option>
							<option value="gpt-3.5-turbo" <?php selected( $ai_model, 'gpt-3.5-turbo' ); ?>>GPT-3.5 Turbo (Budget)</option>
						</select>
						<p class="description">
							<?php esc_html_e( 'Select the AI model to use for generating insights.', 'marketing-analytics-chat' ); ?>
						</p>
					</td>
				</tr>

				<tr>
					<th scope="row">
						<label for="ai_api_key"><?php esc_html_e( 'API Key', 'marketing-analytics-chat' ); ?></label>
					</th>
					<td>
						<input type="password" id="ai_api_key" name="ai_api_key" class="regular-text" value="<?php echo esc_attr( $ai_api_key ? '********' : '' ); ?>">
						<p class="description">
							<?php esc_html_e( 'Enter your Anthropic or OpenAI API key. Leave blank to use WordPress AI if available.', 'marketing-analytics-chat' ); ?>
						</p>
					</td>
				</tr>
			</tbody>
		</table>

		<h2><?php esc_html_e( 'Usage Statistics', 'marketing-analytics-chat' ); ?></h2>

		<div class="ai-usage-stats">
			<div class="stat-card">
				<h3><?php esc_html_e( 'This Month', 'marketing-analytics-chat' ); ?></h3>
				<p class="stat-value"><?php echo esc_html( number_format( $usage_stats['total_calls'] ) ); ?></p>
				<p class="stat-label"><?php esc_html_e( 'API Calls', 'marketing-analytics-chat' ); ?></p>
			</div>

			<div class="stat-card">
				<h3><?php esc_html_e( 'Estimated Cost', 'marketing-analytics-chat' ); ?></h3>
				<p class="stat-value">$<?php echo esc_html( number_format( $estimated_cost, 2 ) ); ?></p>
				<p class="stat-label"><?php esc_html_e( 'USD', 'marketing-analytics-chat' ); ?></p>
			</div>
		</div>

		<?php if ( ! empty( $usage_stats['by_platform'] ) ) : ?>
			<h3><?php esc_html_e( 'Usage by Platform', 'marketing-analytics-chat' ); ?></h3>
			<table class="wp-list-table widefat fixed striped">
				<thead>
					<tr>
						<th><?php esc_html_e( 'Platform', 'marketing-analytics-chat' ); ?></th>
						<th><?php esc_html_e( 'API Calls', 'marketing-analytics-chat' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php foreach ( $usage_stats['by_platform'] as $platform => $calls ) : ?>
						<tr>
							<td><?php echo esc_html( strtoupper( $platform ) ); ?></td>
							<td><?php echo esc_html( number_format( $calls ) ); ?></td>
						</tr>
					<?php endforeach; ?>
				</tbody>
			</table>
		<?php endif; ?>

		<p class="submit">
			<button type="submit" class="button button-primary">
				<?php esc_html_e( 'Save Settings', 'marketing-analytics-chat' ); ?>
			</button>
			<button type="button" class="button" id="test-ai-connection">
				<?php esc_html_e( 'Test AI Connection', 'marketing-analytics-chat' ); ?>
			</button>
		</p>
	</form>
</div>

<style>
.ai-usage-stats {
	display: flex;
	gap: 20px;
	margin: 20px 0;
}
.stat-card {
	background: #fff;
	border: 1px solid #ccd0d4;
	border-radius: 4px;
	padding: 20px;
	min-width: 200px;
}
.stat-card h3 {
	margin-top: 0;
	color: #23282d;
	font-size: 14px;
	font-weight: 600;
}
.stat-value {
	font-size: 32px;
	font-weight: 600;
	color: #0073aa;
	margin: 10px 0;
}
.stat-label {
	color: #666;
	font-size: 12px;
	text-transform: uppercase;
}
.switch {
	position: relative;
	display: inline-block;
	width: 60px;
	height: 34px;
}
.switch input {
	opacity: 0;
	width: 0;
	height: 0;
}
.slider {
	position: absolute;
	cursor: pointer;
	top: 0;
	left: 0;
	right: 0;
	bottom: 0;
	background-color: #ccc;
	transition: .4s;
}
.slider:before {
	position: absolute;
	content: "";
	height: 26px;
	width: 26px;
	left: 4px;
	bottom: 4px;
	background-color: white;
	transition: .4s;
}
input:checked + .slider {
	background-color: #2196F3;
}
input:checked + .slider:before {
	transform: translateX(26px);
}
.slider.round {
	border-radius: 34px;
}
.slider.round:before {
	border-radius: 50%;
}
</style>

<script>
jQuery(document).ready(function($) {
	$('#test-ai-connection').on('click', function() {
		var button = $(this);
		button.prop('disabled', true).text('<?php esc_html_e( 'Testing...', 'marketing-analytics-chat' ); ?>');

		$.post(ajaxurl, {
			action: 'marketing_analytics_test_ai_connection',
			nonce: '<?php echo esc_js( wp_create_nonce( 'marketing_analytics_test_ai' ) ); ?>'
		}, function(response) {
			button.prop('disabled', false).text('<?php esc_html_e( 'Test AI Connection', 'marketing-analytics-chat' ); ?>');

			if (response.success) {
				alert('<?php esc_html_e( 'AI connection successful!', 'marketing-analytics-chat' ); ?>');
			} else {
				alert('<?php esc_html_e( 'AI connection failed: ', 'marketing-analytics-chat' ); ?>' + response.data);
			}
		});
	});
});
</script>