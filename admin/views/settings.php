<?php
/**
 * Settings Page Template
 *
 * @package Marketing_Analytics_MCP
 */

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Handle form submission
if ( isset( $_POST['save_settings'] ) && check_admin_referer( 'marketing_analytics_mcp_save_settings', 'settings_nonce' ) ) {
	$new_settings = array();

	// AI Chat Settings
	$new_settings['ai_provider']     = sanitize_text_field( $_POST['ai_provider'] ?? 'claude' );
	$new_settings['claude_api_key']  = sanitize_text_field( $_POST['claude_api_key'] ?? '' );
	$new_settings['claude_model']    = sanitize_text_field( $_POST['claude_model'] ?? 'claude-sonnet-4-20250514' );
	$new_settings['ai_temperature']  = floatval( $_POST['ai_temperature'] ?? 0.7 );
	$new_settings['ai_max_tokens']   = absint( $_POST['ai_max_tokens'] ?? 4096 );

	// Tool Categories
	$enabled_categories = isset( $_POST['enabled_tool_categories'] ) && is_array( $_POST['enabled_tool_categories'] )
		? array_map( 'sanitize_text_field', $_POST['enabled_tool_categories'] )
		: array( 'all' );
	$new_settings['enabled_tool_categories'] = $enabled_categories;

	// Cache Settings
	$new_settings['cache_ttl_clarity'] = absint( $_POST['cache_ttl_clarity'] ?? 60 ) * 60;
	$new_settings['cache_ttl_ga4']     = absint( $_POST['cache_ttl_ga4'] ?? 30 ) * 60;
	$new_settings['cache_ttl_gsc']     = absint( $_POST['cache_ttl_gsc'] ?? 1440 ) * 60;

	// Debug Settings
	$new_settings['debug_mode'] = isset( $_POST['debug_mode'] ) ? 1 : 0;

	update_option( 'marketing_analytics_mcp_settings', $new_settings );

	echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__( 'Settings saved successfully.', 'marketing-analytics-mcp' ) . '</p></div>';
}

$settings = get_option( 'marketing_analytics_mcp_settings', array() );
?>

<div class="wrap marketing-analytics-mcp-settings">
	<h1><?php echo esc_html( get_admin_page_title() ); ?></h1>

	<form method="post" action="">
		<?php wp_nonce_field( 'marketing_analytics_mcp_save_settings', 'settings_nonce' ); ?>

		<h2><?php esc_html_e( 'AI Chat Settings', 'marketing-analytics-mcp' ); ?></h2>

		<table class="form-table">
			<tr>
				<th scope="row">
					<label for="ai_provider"><?php esc_html_e( 'AI Provider', 'marketing-analytics-mcp' ); ?></label>
				</th>
				<td>
					<select id="ai_provider" name="ai_provider">
						<option value="claude" <?php selected( isset( $settings['ai_provider'] ) ? $settings['ai_provider'] : 'claude', 'claude' ); ?>>
							<?php esc_html_e( 'Claude (Anthropic)', 'marketing-analytics-mcp' ); ?>
						</option>
						<option value="openai" <?php selected( isset( $settings['ai_provider'] ) ? $settings['ai_provider'] : '', 'openai' ); ?> disabled>
							<?php esc_html_e( 'OpenAI (Coming Soon)', 'marketing-analytics-mcp' ); ?>
						</option>
					</select>
					<p class="description"><?php esc_html_e( 'Select the AI provider for chat responses.', 'marketing-analytics-mcp' ); ?></p>
				</td>
			</tr>
			<tr>
				<th scope="row">
					<label for="claude_api_key"><?php esc_html_e( 'Claude API Key', 'marketing-analytics-mcp' ); ?></label>
				</th>
				<td>
					<input type="password" id="claude_api_key" name="claude_api_key"
						value="<?php echo esc_attr( isset( $settings['claude_api_key'] ) ? $settings['claude_api_key'] : '' ); ?>"
						class="regular-text" placeholder="sk-ant-..." />
					<p class="description">
						<?php
						echo sprintf(
							/* translators: %s: URL to Anthropic API keys page */
							__( 'Get your API key from <a href="%s" target="_blank">Anthropic Console</a>.', 'marketing-analytics-mcp' ),
							'https://console.anthropic.com/settings/keys'
						);
						?>
					</p>
				</td>
			</tr>
			<tr>
				<th scope="row">
					<label for="claude_model"><?php esc_html_e( 'Claude Model', 'marketing-analytics-mcp' ); ?></label>
				</th>
				<td>
					<select id="claude_model" name="claude_model">
						<option value="claude-sonnet-4-5-20250929" <?php selected( isset( $settings['claude_model'] ) ? $settings['claude_model'] : 'claude-sonnet-4-20250514', 'claude-sonnet-4-5-20250929' ); ?>>
							<?php esc_html_e( 'Claude Sonnet 4.5 (Latest & Best)', 'marketing-analytics-mcp' ); ?>
						</option>
						<option value="claude-sonnet-4-20250514" <?php selected( isset( $settings['claude_model'] ) ? $settings['claude_model'] : 'claude-sonnet-4-20250514', 'claude-sonnet-4-20250514' ); ?>>
							<?php esc_html_e( 'Claude Sonnet 4 (Recommended)', 'marketing-analytics-mcp' ); ?>
						</option>
						<option value="claude-opus-4-1-20250805" <?php selected( isset( $settings['claude_model'] ) ? $settings['claude_model'] : '', 'claude-opus-4-1-20250805' ); ?>>
							<?php esc_html_e( 'Claude Opus 4.1 (Most Capable)', 'marketing-analytics-mcp' ); ?>
						</option>
						<option value="claude-opus-4-20250514" <?php selected( isset( $settings['claude_model'] ) ? $settings['claude_model'] : '', 'claude-opus-4-20250514' ); ?>>
							<?php esc_html_e( 'Claude Opus 4', 'marketing-analytics-mcp' ); ?>
						</option>
						<option value="claude-haiku-4-5-20251001" <?php selected( isset( $settings['claude_model'] ) ? $settings['claude_model'] : '', 'claude-haiku-4-5-20251001' ); ?>>
							<?php esc_html_e( 'Claude Haiku 4.5 (Fastest)', 'marketing-analytics-mcp' ); ?>
						</option>
						<option value="claude-3-haiku-20240307" <?php selected( isset( $settings['claude_model'] ) ? $settings['claude_model'] : '', 'claude-3-haiku-20240307' ); ?>>
							<?php esc_html_e( 'Claude 3 Haiku (Legacy)', 'marketing-analytics-mcp' ); ?>
						</option>
					</select>
					<p class="description"><?php esc_html_e( 'Select the Claude model to use for chat responses.', 'marketing-analytics-mcp' ); ?></p>
				</td>
			</tr>
			<tr>
				<th scope="row">
					<label for="ai_temperature"><?php esc_html_e( 'Temperature', 'marketing-analytics-mcp' ); ?></label>
				</th>
				<td>
					<input type="number" id="ai_temperature" name="ai_temperature"
						value="<?php echo esc_attr( isset( $settings['ai_temperature'] ) ? $settings['ai_temperature'] : '0.7' ); ?>"
						min="0" max="1" step="0.1" />
					<p class="description"><?php esc_html_e( 'Controls randomness (0 = focused, 1 = creative). Default: 0.7', 'marketing-analytics-mcp' ); ?></p>
				</td>
			</tr>
			<tr>
				<th scope="row">
					<label for="ai_max_tokens"><?php esc_html_e( 'Max Tokens', 'marketing-analytics-mcp' ); ?></label>
				</th>
				<td>
					<input type="number" id="ai_max_tokens" name="ai_max_tokens"
						value="<?php echo esc_attr( isset( $settings['ai_max_tokens'] ) ? $settings['ai_max_tokens'] : '4096' ); ?>"
						min="256" max="8192" step="256" />
					<p class="description"><?php esc_html_e( 'Maximum response length. Default: 4096', 'marketing-analytics-mcp' ); ?></p>
				</td>
			</tr>
		</table>

		<h2><?php esc_html_e( 'AI Chat Tool Selection', 'marketing-analytics-mcp' ); ?></h2>

		<table class="form-table">
			<tr>
				<th scope="row">
					<label><?php esc_html_e( 'Enabled Tool Categories', 'marketing-analytics-mcp' ); ?></label>
				</th>
				<td>
					<?php
					$enabled_categories = $settings['enabled_tool_categories'] ?? array( 'all' );
					$is_all_enabled     = in_array( 'all', $enabled_categories, true );
					?>
					<fieldset>
						<legend class="screen-reader-text"><span><?php esc_html_e( 'Enabled Tool Categories', 'marketing-analytics-mcp' ); ?></span></legend>
						<label>
							<input type="checkbox" name="enabled_tool_categories[]" value="all"
								<?php checked( $is_all_enabled ); ?>
								id="tool_category_all" />
							<strong><?php esc_html_e( 'All Tools', 'marketing-analytics-mcp' ); ?></strong>
							<span class="description"><?php esc_html_e( '(Recommended - AI can use any available tool)', 'marketing-analytics-mcp' ); ?></span>
						</label>
						<br/>
						<label>
							<input type="checkbox" name="enabled_tool_categories[]" value="clarity"
								<?php checked( in_array( 'clarity', $enabled_categories, true ) || $is_all_enabled ); ?>
								<?php disabled( $is_all_enabled ); ?> />
							<?php esc_html_e( 'Microsoft Clarity Tools', 'marketing-analytics-mcp' ); ?>
						</label>
						<br/>
						<label>
							<input type="checkbox" name="enabled_tool_categories[]" value="ga4"
								<?php checked( in_array( 'ga4', $enabled_categories, true ) || $is_all_enabled ); ?>
								<?php disabled( $is_all_enabled ); ?> />
							<?php esc_html_e( 'Google Analytics 4 Tools', 'marketing-analytics-mcp' ); ?>
						</label>
						<br/>
						<label>
							<input type="checkbox" name="enabled_tool_categories[]" value="gsc"
								<?php checked( in_array( 'gsc', $enabled_categories, true ) || $is_all_enabled ); ?>
								<?php disabled( $is_all_enabled ); ?> />
							<?php esc_html_e( 'Google Search Console Tools', 'marketing-analytics-mcp' ); ?>
						</label>
					</fieldset>
					<p class="description">
						<?php
						esc_html_e(
							'Select which tool categories to send to the AI. Fewer tools = lower token costs per request. The AI will only see and use tools from enabled categories. Token usage with tool count is displayed below each AI response.',
							'marketing-analytics-mcp'
						);
						?>
					</p>
				</td>
			</tr>
		</table>

		<h2><?php esc_html_e( 'Cache Settings', 'marketing-analytics-mcp' ); ?></h2>

		<table class="form-table">
			<tr>
				<th scope="row">
					<label for="cache_ttl_clarity"><?php esc_html_e( 'Clarity Cache Duration', 'marketing-analytics-mcp' ); ?></label>
				</th>
				<td>
					<input type="number" id="cache_ttl_clarity" name="cache_ttl_clarity"
						value="<?php echo esc_attr( isset( $settings['cache_ttl_clarity'] ) ? $settings['cache_ttl_clarity'] / 60 : 60 ); ?>"
						min="5" max="1440" /> <?php esc_html_e( 'minutes', 'marketing-analytics-mcp' ); ?>
					<p class="description"><?php esc_html_e( 'Default: 60 minutes. Clarity has a rate limit of 10 requests per day.', 'marketing-analytics-mcp' ); ?></p>
				</td>
			</tr>
			<tr>
				<th scope="row">
					<label for="cache_ttl_ga4"><?php esc_html_e( 'GA4 Cache Duration', 'marketing-analytics-mcp' ); ?></label>
				</th>
				<td>
					<input type="number" id="cache_ttl_ga4" name="cache_ttl_ga4"
						value="<?php echo esc_attr( isset( $settings['cache_ttl_ga4'] ) ? $settings['cache_ttl_ga4'] / 60 : 30 ); ?>"
						min="5" max="1440" /> <?php esc_html_e( 'minutes', 'marketing-analytics-mcp' ); ?>
					<p class="description"><?php esc_html_e( 'Default: 30 minutes. Balance between freshness and API quota.', 'marketing-analytics-mcp' ); ?></p>
				</td>
			</tr>
			<tr>
				<th scope="row">
					<label for="cache_ttl_gsc"><?php esc_html_e( 'Search Console Cache Duration', 'marketing-analytics-mcp' ); ?></label>
				</th>
				<td>
					<input type="number" id="cache_ttl_gsc" name="cache_ttl_gsc"
						value="<?php echo esc_attr( isset( $settings['cache_ttl_gsc'] ) ? $settings['cache_ttl_gsc'] / 60 : 1440 ); ?>"
						min="60" max="2880" /> <?php esc_html_e( 'minutes', 'marketing-analytics-mcp' ); ?>
					<p class="description"><?php esc_html_e( 'Default: 1440 minutes (24 hours). GSC data has a 2-3 day delay.', 'marketing-analytics-mcp' ); ?></p>
				</td>
			</tr>
		</table>

		<h2><?php esc_html_e( 'Debug Settings', 'marketing-analytics-mcp' ); ?></h2>

		<table class="form-table">
			<tr>
				<th scope="row">
					<label for="debug_mode"><?php esc_html_e( 'Debug Mode', 'marketing-analytics-mcp' ); ?></label>
				</th>
				<td>
					<label>
						<input type="checkbox" id="debug_mode" name="debug_mode" value="1"
							<?php checked( isset( $settings['debug_mode'] ) && $settings['debug_mode'] ); ?> />
						<?php esc_html_e( 'Enable debug logging', 'marketing-analytics-mcp' ); ?>
					</label>
					<p class="description"><?php esc_html_e( 'Log API requests and responses (credentials are never logged).', 'marketing-analytics-mcp' ); ?></p>
				</td>
			</tr>
		</table>

		<h2><?php esc_html_e( 'Cache Management', 'marketing-analytics-mcp' ); ?></h2>

		<table class="form-table">
			<tr>
				<th scope="row">
					<?php esc_html_e( 'Clear All Caches', 'marketing-analytics-mcp' ); ?>
				</th>
				<td>
					<button type="button" class="button button-secondary clear-all-caches">
						<?php esc_html_e( 'Clear All Cached Data', 'marketing-analytics-mcp' ); ?>
					</button>
					<p class="description"><?php esc_html_e( 'Remove all cached API responses. Fresh data will be fetched on next request.', 'marketing-analytics-mcp' ); ?></p>
				</td>
			</tr>
		</table>

		<p class="submit">
			<input type="submit" name="save_settings" class="button button-primary" value="<?php esc_attr_e( 'Save Settings', 'marketing-analytics-mcp' ); ?>" />
		</p>
	</form>
</div>

<script>
(function($) {
	'use strict';

	$(document).ready(function() {
		// Handle "All Tools" checkbox
		$('#tool_category_all').on('change', function() {
			var isChecked = $(this).is(':checked');
			var $otherCheckboxes = $('input[name="enabled_tool_categories[]"]').not('#tool_category_all');

			if (isChecked) {
				$otherCheckboxes.prop('checked', true).prop('disabled', true);
			} else {
				$otherCheckboxes.prop('disabled', false);
			}
		});

		// Trigger on page load to set initial state
		$('#tool_category_all').trigger('change');
	});
})(jQuery);
</script>
