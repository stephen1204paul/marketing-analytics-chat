<?php
/**
 * Notifications Settings Page
 *
 * @package Marketing_Analytics_MCP
 * @subpackage Admin\Views\Settings
 */

// Don't allow direct access
if ( ! defined( 'WPINC' ) ) {
	die;
}

use Marketing_Analytics_MCP\Notifications\Notification_Manager;

// Get current settings
// Slack settings
$slack_enabled  = get_option( 'marketing_analytics_slack_enabled', false );
$slack_webhook  = get_option( 'marketing_analytics_slack_webhook_url', '' );
$slack_channel  = get_option( 'marketing_analytics_slack_channel', '#marketing' );
$slack_bot_name = get_option( 'marketing_analytics_slack_bot_name', 'Marketing Analytics Bot' );

// WhatsApp settings
$whatsapp_enabled    = get_option( 'marketing_analytics_whatsapp_enabled', false );
$twilio_sid          = get_option( 'marketing_analytics_twilio_account_sid', '' );
$twilio_token        = get_option( 'marketing_analytics_twilio_auth_token', '' );
$twilio_number       = get_option( 'marketing_analytics_twilio_whatsapp_number', '' );
$whatsapp_recipients = get_option( 'marketing_analytics_whatsapp_recipients', array() );

// Schedule settings
$daily_enabled  = get_option( 'marketing_analytics_daily_summary_enabled', false );
$daily_time     = get_option( 'marketing_analytics_daily_summary_time', '09:00' );
$weekly_enabled = get_option( 'marketing_analytics_weekly_report_enabled', false );
$weekly_day     = get_option( 'marketing_analytics_weekly_report_day', 'monday' );
$weekly_time    = get_option( 'marketing_analytics_weekly_report_time', '09:00' );

// Get notification history
$notification_manager = new Notification_Manager();
$notification_history = $notification_manager->get_notification_history( 10 );
?>

<div class="wrap">
	<h1><?php esc_html_e( 'Notification Settings', 'marketing-analytics-chat' ); ?></h1>

	<div class="notice notice-info">
		<p>
			<?php esc_html_e( 'Configure automated notifications via Slack and WhatsApp to stay updated on your marketing analytics.', 'marketing-analytics-chat' ); ?>
		</p>
	</div>

	<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
		<?php wp_nonce_field( 'marketing_analytics_notification_settings', 'notification_settings_nonce' ); ?>
		<input type="hidden" name="action" value="marketing_analytics_save_notification_settings">

		<!-- Slack Settings -->
		<h2><?php esc_html_e( 'Slack Integration', 'marketing-analytics-chat' ); ?></h2>
		<table class="form-table">
			<tbody>
				<tr>
					<th scope="row">
						<label for="slack_enabled"><?php esc_html_e( 'Enable Slack', 'marketing-analytics-chat' ); ?></label>
					</th>
					<td>
						<label class="switch">
							<input type="checkbox" id="slack_enabled" name="slack_enabled" value="1" <?php checked( $slack_enabled ); ?>>
							<span class="slider round"></span>
						</label>
						<p class="description">
							<?php esc_html_e( 'Enable Slack notifications for analytics reports and alerts.', 'marketing-analytics-chat' ); ?>
						</p>
					</td>
				</tr>

				<tr>
					<th scope="row">
						<label for="slack_webhook"><?php esc_html_e( 'Webhook URL', 'marketing-analytics-chat' ); ?></label>
					</th>
					<td>
						<input type="url" id="slack_webhook" name="slack_webhook" class="large-text" value="<?php echo esc_attr( $slack_webhook ); ?>">
						<p class="description">
							<?php
							printf(
								/* translators: %s: Slack webhook documentation URL */
								esc_html__( 'Get your webhook URL from %s', 'marketing-analytics-chat' ),
								'<a href="https://api.slack.com/incoming-webhooks" target="_blank">Slack Incoming Webhooks</a>'
							);
							?>
						</p>
					</td>
				</tr>

				<tr>
					<th scope="row">
						<label for="slack_channel"><?php esc_html_e( 'Default Channel', 'marketing-analytics-chat' ); ?></label>
					</th>
					<td>
						<input type="text" id="slack_channel" name="slack_channel" class="regular-text" value="<?php echo esc_attr( $slack_channel ); ?>">
						<p class="description">
							<?php esc_html_e( 'Default Slack channel for notifications (e.g., #marketing)', 'marketing-analytics-chat' ); ?>
						</p>
					</td>
				</tr>

				<tr>
					<th scope="row">
						<label for="slack_bot_name"><?php esc_html_e( 'Bot Name', 'marketing-analytics-chat' ); ?></label>
					</th>
					<td>
						<input type="text" id="slack_bot_name" name="slack_bot_name" class="regular-text" value="<?php echo esc_attr( $slack_bot_name ); ?>">
						<p class="description">
							<?php esc_html_e( 'Name displayed for the bot in Slack', 'marketing-analytics-chat' ); ?>
						</p>
					</td>
				</tr>
			</tbody>
		</table>

		<!-- WhatsApp Settings -->
		<h2><?php esc_html_e( 'WhatsApp Integration (via Twilio)', 'marketing-analytics-chat' ); ?></h2>
		<table class="form-table">
			<tbody>
				<tr>
					<th scope="row">
						<label for="whatsapp_enabled"><?php esc_html_e( 'Enable WhatsApp', 'marketing-analytics-chat' ); ?></label>
					</th>
					<td>
						<label class="switch">
							<input type="checkbox" id="whatsapp_enabled" name="whatsapp_enabled" value="1" <?php checked( $whatsapp_enabled ); ?>>
							<span class="slider round"></span>
						</label>
						<p class="description">
							<?php esc_html_e( 'Enable WhatsApp notifications via Twilio.', 'marketing-analytics-chat' ); ?>
						</p>
					</td>
				</tr>

				<tr>
					<th scope="row">
						<label for="twilio_sid"><?php esc_html_e( 'Twilio Account SID', 'marketing-analytics-chat' ); ?></label>
					</th>
					<td>
						<input type="text" id="twilio_sid" name="twilio_sid" class="regular-text" value="<?php echo esc_attr( $twilio_sid ); ?>">
						<p class="description">
							<?php esc_html_e( 'Your Twilio Account SID from the Twilio Console', 'marketing-analytics-chat' ); ?>
						</p>
					</td>
				</tr>

				<tr>
					<th scope="row">
						<label for="twilio_token"><?php esc_html_e( 'Twilio Auth Token', 'marketing-analytics-chat' ); ?></label>
					</th>
					<td>
						<input type="password" id="twilio_token" name="twilio_token" class="regular-text" value="<?php echo esc_attr( $twilio_token ? '********' : '' ); ?>">
						<p class="description">
							<?php esc_html_e( 'Your Twilio Auth Token (kept encrypted)', 'marketing-analytics-chat' ); ?>
						</p>
					</td>
				</tr>

				<tr>
					<th scope="row">
						<label for="twilio_number"><?php esc_html_e( 'WhatsApp Number', 'marketing-analytics-chat' ); ?></label>
					</th>
					<td>
						<input type="text" id="twilio_number" name="twilio_number" class="regular-text" value="<?php echo esc_attr( $twilio_number ); ?>" placeholder="+14155238886">
						<p class="description">
							<?php esc_html_e( 'Your Twilio WhatsApp number (format: +1234567890)', 'marketing-analytics-chat' ); ?>
						</p>
					</td>
				</tr>

				<tr>
					<th scope="row">
						<label for="whatsapp_recipients"><?php esc_html_e( 'Recipients', 'marketing-analytics-chat' ); ?></label>
					</th>
					<td>
						<textarea id="whatsapp_recipients" name="whatsapp_recipients" class="large-text" rows="3"><?php echo esc_textarea( implode( "\n", $whatsapp_recipients ) ); ?></textarea>
						<p class="description">
							<?php esc_html_e( 'WhatsApp numbers to receive notifications (one per line, format: +1234567890)', 'marketing-analytics-chat' ); ?>
						</p>
					</td>
				</tr>
			</tbody>
		</table>

		<!-- Schedule Settings -->
		<h2><?php esc_html_e( 'Scheduled Reports', 'marketing-analytics-chat' ); ?></h2>
		<table class="form-table">
			<tbody>
				<tr>
					<th scope="row">
						<label for="daily_enabled"><?php esc_html_e( 'Daily Summary', 'marketing-analytics-chat' ); ?></label>
					</th>
					<td>
						<label class="switch">
							<input type="checkbox" id="daily_enabled" name="daily_enabled" value="1" <?php checked( $daily_enabled ); ?>>
							<span class="slider round"></span>
						</label>
						<input type="time" name="daily_time" value="<?php echo esc_attr( $daily_time ); ?>" style="margin-left: 20px;">
						<p class="description">
							<?php esc_html_e( 'Send daily analytics summary at specified time.', 'marketing-analytics-chat' ); ?>
						</p>
					</td>
				</tr>

				<tr>
					<th scope="row">
						<label for="weekly_enabled"><?php esc_html_e( 'Weekly Report', 'marketing-analytics-chat' ); ?></label>
					</th>
					<td>
						<label class="switch">
							<input type="checkbox" id="weekly_enabled" name="weekly_enabled" value="1" <?php checked( $weekly_enabled ); ?>>
							<span class="slider round"></span>
						</label>
						<select name="weekly_day" style="margin-left: 20px;">
							<option value="monday" <?php selected( $weekly_day, 'monday' ); ?>><?php esc_html_e( 'Monday', 'marketing-analytics-chat' ); ?></option>
							<option value="tuesday" <?php selected( $weekly_day, 'tuesday' ); ?>><?php esc_html_e( 'Tuesday', 'marketing-analytics-chat' ); ?></option>
							<option value="wednesday" <?php selected( $weekly_day, 'wednesday' ); ?>><?php esc_html_e( 'Wednesday', 'marketing-analytics-chat' ); ?></option>
							<option value="thursday" <?php selected( $weekly_day, 'thursday' ); ?>><?php esc_html_e( 'Thursday', 'marketing-analytics-chat' ); ?></option>
							<option value="friday" <?php selected( $weekly_day, 'friday' ); ?>><?php esc_html_e( 'Friday', 'marketing-analytics-chat' ); ?></option>
							<option value="saturday" <?php selected( $weekly_day, 'saturday' ); ?>><?php esc_html_e( 'Saturday', 'marketing-analytics-chat' ); ?></option>
							<option value="sunday" <?php selected( $weekly_day, 'sunday' ); ?>><?php esc_html_e( 'Sunday', 'marketing-analytics-chat' ); ?></option>
						</select>
						<input type="time" name="weekly_time" value="<?php echo esc_attr( $weekly_time ); ?>">
						<p class="description">
							<?php esc_html_e( 'Send weekly analytics report on specified day and time.', 'marketing-analytics-chat' ); ?>
						</p>
					</td>
				</tr>
			</tbody>
		</table>

		<?php if ( ! empty( $notification_history ) ) : ?>
			<h2><?php esc_html_e( 'Recent Notifications', 'marketing-analytics-chat' ); ?></h2>
			<table class="wp-list-table widefat fixed striped">
				<thead>
					<tr>
						<th><?php esc_html_e( 'Date', 'marketing-analytics-chat' ); ?></th>
						<th><?php esc_html_e( 'Channel', 'marketing-analytics-chat' ); ?></th>
						<th><?php esc_html_e( 'Message', 'marketing-analytics-chat' ); ?></th>
						<th><?php esc_html_e( 'Status', 'marketing-analytics-chat' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php foreach ( $notification_history as $notification ) : ?>
						<tr>
							<td><?php echo esc_html( gmdate( 'Y-m-d H:i', strtotime( $notification['sent_at'] ) ) ); ?></td>
							<td><?php echo esc_html( ucfirst( $notification['channel'] ) ); ?></td>
							<td><?php echo esc_html( $notification['message'] ); ?></td>
							<td>
								<?php if ( $notification['success'] ) : ?>
									<span style="color: #46b450;">✓ <?php esc_html_e( 'Sent', 'marketing-analytics-chat' ); ?></span>
								<?php else : ?>
									<span style="color: #dc3232;">✗ <?php esc_html_e( 'Failed', 'marketing-analytics-chat' ); ?></span>
								<?php endif; ?>
							</td>
						</tr>
					<?php endforeach; ?>
				</tbody>
			</table>
		<?php endif; ?>

		<p class="submit">
			<button type="submit" class="button button-primary">
				<?php esc_html_e( 'Save Settings', 'marketing-analytics-chat' ); ?>
			</button>
			<button type="button" class="button" id="test-slack">
				<?php esc_html_e( 'Test Slack', 'marketing-analytics-chat' ); ?>
			</button>
			<button type="button" class="button" id="test-whatsapp">
				<?php esc_html_e( 'Test WhatsApp', 'marketing-analytics-chat' ); ?>
			</button>
		</p>
	</form>
</div>

<style>
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
	$('#test-slack').on('click', function() {
		var button = $(this);
		button.prop('disabled', true).text('<?php esc_html_e( 'Testing...', 'marketing-analytics-chat' ); ?>');

		$.post(ajaxurl, {
			action: 'marketing_analytics_test_slack',
			nonce: '<?php echo esc_js( wp_create_nonce( 'marketing_analytics_test_notification' ) ); ?>'
		}, function(response) {
			button.prop('disabled', false).text('<?php esc_html_e( 'Test Slack', 'marketing-analytics-chat' ); ?>');

			if (response.success) {
				alert('<?php esc_html_e( 'Slack test message sent successfully!', 'marketing-analytics-chat' ); ?>');
			} else {
				alert('<?php esc_html_e( 'Slack test failed: ', 'marketing-analytics-chat' ); ?>' + response.data);
			}
		});
	});

	$('#test-whatsapp').on('click', function() {
		var button = $(this);
		button.prop('disabled', true).text('<?php esc_html_e( 'Testing...', 'marketing-analytics-chat' ); ?>');

		$.post(ajaxurl, {
			action: 'marketing_analytics_test_whatsapp',
			nonce: '<?php echo esc_js( wp_create_nonce( 'marketing_analytics_test_notification' ) ); ?>'
		}, function(response) {
			button.prop('disabled', false).text('<?php esc_html_e( 'Test WhatsApp', 'marketing-analytics-chat' ); ?>');

			if (response.success) {
				alert('<?php esc_html_e( 'WhatsApp test message sent successfully!', 'marketing-analytics-chat' ); ?>');
			} else {
				alert('<?php esc_html_e( 'WhatsApp test failed: ', 'marketing-analytics-chat' ); ?>' + response.data);
			}
		});
	});
});
</script>