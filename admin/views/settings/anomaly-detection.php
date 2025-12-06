<?php
/**
 * Anomaly Detection Settings Page
 *
 * @package Marketing_Analytics_MCP
 * @subpackage Admin\Views\Settings
 */

// Don't allow direct access
if ( ! defined( 'WPINC' ) ) {
	die;
}

use Marketing_Analytics_MCP\Analytics\Anomaly_Detector;

// Get current settings
$anomaly_enabled       = get_option( 'marketing_analytics_mcp_anomaly_detection_enabled', false );
$anomaly_sensitivity   = get_option( 'marketing_analytics_mcp_anomaly_sensitivity', 2.0 );
$anomaly_email_enabled = get_option( 'marketing_analytics_mcp_anomaly_email_enabled', true );

// Platform-specific settings
$platforms         = array( 'clarity', 'ga4', 'gsc', 'meta', 'dataforseo' );
$platform_settings = array();
foreach ( $platforms as $platform ) {
	$platform_settings[ $platform ] = get_option( "marketing_analytics_mcp_anomaly_{$platform}_enabled", true );
}

// Get anomaly stats
$detector         = new Anomaly_Detector();
$anomaly_stats    = $detector->get_anomaly_stats( 'week' );
$recent_anomalies = $detector->get_anomaly_history( 10 );
?>

<div class="wrap">
	<h1><?php esc_html_e( 'Anomaly Detection Settings', 'marketing-analytics-chat' ); ?></h1>

	<div class="notice notice-info">
		<p>
			<?php esc_html_e( 'Automatic anomaly detection alerts you when metrics deviate significantly from their baseline, helping you catch issues early.', 'marketing-analytics-chat' ); ?>
		</p>
	</div>

	<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
		<?php wp_nonce_field( 'marketing_analytics_anomaly_settings', 'anomaly_settings_nonce' ); ?>
		<input type="hidden" name="action" value="marketing_analytics_save_anomaly_settings">

		<table class="form-table">
			<tbody>
				<tr>
					<th scope="row">
						<label for="anomaly_enabled"><?php esc_html_e( 'Enable Anomaly Detection', 'marketing-analytics-chat' ); ?></label>
					</th>
					<td>
						<label class="switch">
							<input type="checkbox" id="anomaly_enabled" name="anomaly_enabled" value="1" <?php checked( $anomaly_enabled ); ?>>
							<span class="slider round"></span>
						</label>
						<p class="description">
							<?php esc_html_e( 'Run daily anomaly checks on all analytics data.', 'marketing-analytics-chat' ); ?>
						</p>
					</td>
				</tr>

				<tr>
					<th scope="row">
						<label for="anomaly_sensitivity"><?php esc_html_e( 'Detection Sensitivity', 'marketing-analytics-chat' ); ?></label>
					</th>
					<td>
						<select id="anomaly_sensitivity" name="anomaly_sensitivity" class="regular-text">
							<option value="1" <?php selected( $anomaly_sensitivity, 1 ); ?>>
								<?php esc_html_e( 'High (1 standard deviation)', 'marketing-analytics-chat' ); ?>
							</option>
							<option value="2" <?php selected( $anomaly_sensitivity, 2 ); ?>>
								<?php esc_html_e( 'Medium (2 standard deviations)', 'marketing-analytics-chat' ); ?>
							</option>
							<option value="3" <?php selected( $anomaly_sensitivity, 3 ); ?>>
								<?php esc_html_e( 'Low (3 standard deviations)', 'marketing-analytics-chat' ); ?>
							</option>
						</select>
						<p class="description">
							<?php esc_html_e( 'Higher sensitivity detects more anomalies but may include false positives.', 'marketing-analytics-chat' ); ?>
						</p>
					</td>
				</tr>

				<tr>
					<th scope="row">
						<label for="anomaly_email_enabled"><?php esc_html_e( 'Email Notifications', 'marketing-analytics-chat' ); ?></label>
					</th>
					<td>
						<label class="switch">
							<input type="checkbox" id="anomaly_email_enabled" name="anomaly_email_enabled" value="1" <?php checked( $anomaly_email_enabled ); ?>>
							<span class="slider round"></span>
						</label>
						<p class="description">
							<?php esc_html_e( 'Send email alerts when anomalies are detected.', 'marketing-analytics-chat' ); ?>
						</p>
					</td>
				</tr>

				<tr>
					<th scope="row"><?php esc_html_e( 'Monitor Platforms', 'marketing-analytics-chat' ); ?></th>
					<td>
						<fieldset>
							<?php foreach ( $platforms as $platform ) : ?>
								<label style="display: block; margin-bottom: 10px;">
									<input type="checkbox" name="platforms[<?php echo esc_attr( $platform ); ?>]" value="1" <?php checked( $platform_settings[ $platform ] ); ?>>
									<?php echo esc_html( strtoupper( $platform ) ); ?>
								</label>
							<?php endforeach; ?>
						</fieldset>
						<p class="description">
							<?php esc_html_e( 'Select which platforms to monitor for anomalies.', 'marketing-analytics-chat' ); ?>
						</p>
					</td>
				</tr>
			</tbody>
		</table>

		<h2><?php esc_html_e( 'Anomaly Statistics', 'marketing-analytics-chat' ); ?></h2>

		<div class="anomaly-stats">
			<div class="stat-card">
				<h3><?php esc_html_e( 'This Week', 'marketing-analytics-chat' ); ?></h3>
				<p class="stat-value"><?php echo esc_html( $anomaly_stats['total'] ); ?></p>
				<p class="stat-label"><?php esc_html_e( 'Anomalies Detected', 'marketing-analytics-chat' ); ?></p>
			</div>

			<?php if ( ! empty( $anomaly_stats['by_severity'] ) ) : ?>
				<?php foreach ( $anomaly_stats['by_severity'] as $severity_data ) : ?>
					<div class="stat-card severity-<?php echo esc_attr( $severity_data['severity'] ); ?>">
						<h3><?php echo esc_html( ucfirst( $severity_data['severity'] ) ); ?></h3>
						<p class="stat-value"><?php echo esc_html( $severity_data['count'] ); ?></p>
						<p class="stat-label"><?php esc_html_e( 'Severity', 'marketing-analytics-chat' ); ?></p>
					</div>
				<?php endforeach; ?>
			<?php endif; ?>
		</div>

		<?php if ( ! empty( $recent_anomalies ) ) : ?>
			<h2><?php esc_html_e( 'Recent Anomalies', 'marketing-analytics-chat' ); ?></h2>
			<table class="wp-list-table widefat fixed striped">
				<thead>
					<tr>
						<th><?php esc_html_e( 'Date', 'marketing-analytics-chat' ); ?></th>
						<th><?php esc_html_e( 'Platform', 'marketing-analytics-chat' ); ?></th>
						<th><?php esc_html_e( 'Metric', 'marketing-analytics-chat' ); ?></th>
						<th><?php esc_html_e( 'Type', 'marketing-analytics-chat' ); ?></th>
						<th><?php esc_html_e( 'Severity', 'marketing-analytics-chat' ); ?></th>
						<th><?php esc_html_e( 'Deviation', 'marketing-analytics-chat' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php foreach ( $recent_anomalies as $anomaly ) : ?>
						<tr>
							<td><?php echo esc_html( gmdate( 'Y-m-d H:i', strtotime( $anomaly['detected_at'] ) ) ); ?></td>
							<td><?php echo esc_html( strtoupper( $anomaly['platform'] ) ); ?></td>
							<td><?php echo esc_html( $anomaly['metric'] ); ?></td>
							<td>
								<span class="anomaly-type anomaly-<?php echo esc_attr( $anomaly['type'] ); ?>">
									<?php echo esc_html( ucfirst( $anomaly['type'] ) ); ?>
								</span>
							</td>
							<td>
								<span class="anomaly-severity severity-<?php echo esc_attr( $anomaly['severity'] ); ?>">
									<?php echo esc_html( ucfirst( $anomaly['severity'] ) ); ?>
								</span>
							</td>
							<td><?php echo esc_html( number_format( abs( $anomaly['deviation'] ), 2 ) ); ?>σ</td>
						</tr>
					<?php endforeach; ?>
				</tbody>
			</table>
		<?php endif; ?>

		<p class="submit">
			<button type="submit" class="button button-primary">
				<?php esc_html_e( 'Save Settings', 'marketing-analytics-chat' ); ?>
			</button>
			<button type="button" class="button" id="run-anomaly-check">
				<?php esc_html_e( 'Run Check Now', 'marketing-analytics-chat' ); ?>
			</button>
		</p>
	</form>
</div>

<style>
.anomaly-stats {
	display: flex;
	gap: 20px;
	margin: 20px 0;
	flex-wrap: wrap;
}
.stat-card {
	background: #fff;
	border: 1px solid #ccd0d4;
	border-radius: 4px;
	padding: 20px;
	min-width: 150px;
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
.severity-critical .stat-value {
	color: #dc3232;
}
.severity-high .stat-value {
	color: #ff6900;
}
.severity-medium .stat-value {
	color: #ffb900;
}
.severity-low .stat-value {
	color: #46b450;
}
.anomaly-type {
	padding: 2px 8px;
	border-radius: 3px;
	font-size: 12px;
}
.anomaly-spike {
	background-color: #ffebe9;
	color: #dc3232;
}
.anomaly-drop {
	background-color: #e9f5ff;
	color: #0073aa;
}
.anomaly-severity {
	padding: 2px 8px;
	border-radius: 3px;
	font-size: 12px;
}
.severity-critical {
	background-color: #dc3232;
	color: #fff;
}
.severity-high {
	background-color: #ff6900;
	color: #fff;
}
.severity-medium {
	background-color: #ffb900;
	color: #23282d;
}
.severity-low {
	background-color: #46b450;
	color: #fff;
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
	$('#run-anomaly-check').on('click', function() {
		var button = $(this);
		button.prop('disabled', true).text('<?php esc_html_e( 'Running...', 'marketing-analytics-chat' ); ?>');

		$.post(ajaxurl, {
			action: 'marketing_analytics_run_anomaly_check',
			nonce: '<?php echo esc_js( wp_create_nonce( 'marketing_analytics_anomaly_check' ) ); ?>'
		}, function(response) {
			button.prop('disabled', false).text('<?php esc_html_e( 'Run Check Now', 'marketing-analytics-chat' ); ?>');

			if (response.success) {
				alert('<?php esc_html_e( 'Anomaly check completed. Found ', 'marketing-analytics-chat' ); ?>' + response.data.count + '<?php esc_html_e( ' anomalies.', 'marketing-analytics-chat' ); ?>');
				if (response.data.count > 0) {
					location.reload();
				}
			} else {
				alert('<?php esc_html_e( 'Anomaly check failed: ', 'marketing-analytics-chat' ); ?>' + response.data);
			}
		});
	});
});
</script>