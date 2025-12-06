<?php
/**
 * Google Analytics 4 Connection View
 *
 * @package Marketing_Analytics_MCP
 */

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use Marketing_Analytics_MCP\Credentials\OAuth_Handler;
use Marketing_Analytics_MCP\Credentials\Credential_Manager;

$oauth_handler      = new OAuth_Handler();
$credential_manager = new Credential_Manager();

// Check if OAuth credentials are configured
$has_oauth_creds = $oauth_handler->has_oauth_credentials();
$client_id       = $oauth_handler->get_client_id();

// Check if user is authenticated
$ga4_credentials  = $credential_manager->get_credentials( 'ga4' );
$is_authenticated = ! empty( $ga4_credentials ) && isset( $ga4_credentials['access_token'] );

// Get saved property ID
$saved_property_id = '';
if ( $is_authenticated && isset( $ga4_credentials['property_id'] ) ) {
	$saved_property_id = $ga4_credentials['property_id'];
}

// Get connection status
$settings      = get_option( 'marketing_analytics_mcp_settings', array() );
$platforms     = isset( $settings['platforms'] ) ? $settings['platforms'] : array();
$ga4_connected = isset( $platforms['ga4']['connected'] ) && $platforms['ga4']['connected'];
?>

<div class="connection-panel">
	<h3>
		<?php esc_html_e( 'Google Analytics 4 Configuration', 'marketing-analytics-chat' ); ?>
		<?php if ( $ga4_connected ) : ?>
			<span class="status-badge" style="background: #46b450; color: white; padding: 4px 12px; border-radius: 3px; font-size: 13px; margin-left: 10px; font-weight: normal;">
				<span class="dashicons dashicons-yes-alt" style="font-size: 14px; margin-top: 2px;"></span>
				<?php esc_html_e( 'Connected', 'marketing-analytics-chat' ); ?>
			</span>
		<?php else : ?>
			<span class="status-badge" style="background: #dc3232; color: white; padding: 4px 12px; border-radius: 3px; font-size: 13px; margin-left: 10px; font-weight: normal;">
				<span class="dashicons dashicons-warning" style="font-size: 14px; margin-top: 2px;"></span>
				<?php esc_html_e( 'Not Connected', 'marketing-analytics-chat' ); ?>
			</span>
		<?php endif; ?>
	</h3>
	<p><?php esc_html_e( 'Connect to Google Analytics 4 to access traffic metrics, user behavior, and conversion data.', 'marketing-analytics-chat' ); ?></p>

	<?php if ( ! $has_oauth_creds ) : ?>
		<!-- Step 1: Configure OAuth Credentials -->
		<div class="notice notice-warning">
			<p><strong><?php esc_html_e( 'Step 1: Configure Google OAuth Credentials', 'marketing-analytics-chat' ); ?></strong></p>
			<p><?php esc_html_e( 'Before you can connect to Google Analytics 4, you need to set up OAuth credentials from the Google Cloud Console.', 'marketing-analytics-chat' ); ?></p>
		</div>

		<div class="notice notice-info">
			<p><strong><?php esc_html_e( 'Required Google APIs', 'marketing-analytics-chat' ); ?></strong></p>
			<p><?php esc_html_e( 'Make sure you have enabled these APIs in your Google Cloud project:', 'marketing-analytics-chat' ); ?></p>
			<ul style="list-style: disc; margin-left: 20px;">
				<li><strong><?php esc_html_e( 'Google Analytics Data API', 'marketing-analytics-chat' ); ?></strong> - <?php esc_html_e( 'For reading analytics data', 'marketing-analytics-chat' ); ?></li>
				<li><strong><?php esc_html_e( 'Google Analytics Admin API', 'marketing-analytics-chat' ); ?></strong> - <?php esc_html_e( 'For listing your GA4 properties', 'marketing-analytics-chat' ); ?></li>
			</ul>
			<p>
				<a href="<?php echo esc_url( 'https://console.cloud.google.com/apis/library/analyticsdata.googleapis.com' ); ?>" target="_blank" class="button button-secondary">
					<?php esc_html_e( 'Enable Analytics Data API', 'marketing-analytics-chat' ); ?>
				</a>
				<a href="<?php echo esc_url( 'https://console.cloud.google.com/apis/library/analyticsadmin.googleapis.com' ); ?>" target="_blank" class="button button-secondary">
					<?php esc_html_e( 'Enable Analytics Admin API', 'marketing-analytics-chat' ); ?>
				</a>
			</p>
			<p>
				<?php
				printf(
					/* translators: %s: link to setup guide */
					esc_html__( 'Need help? See the %s for detailed instructions.', 'marketing-analytics-chat' ),
					'<a href="' . esc_url( plugin_dir_url( dirname( dirname( __FILE__ ) ) ) . '../docs/GOOGLE_OAUTH_SETUP.md' ) . '" target="_blank">' . esc_html__( 'Google OAuth Setup Guide', 'marketing-analytics-chat' ) . '</a>'
				);
				?>
			</p>
		</div>

		<form method="post" action="" id="ga4-oauth-config-form">
			<?php wp_nonce_field( 'marketing_analytics_mcp_oauth_config', 'oauth_config_nonce' ); ?>
			<input type="hidden" name="save_oauth_config" value="1" />

			<table class="form-table">
				<tr>
					<th scope="row">
						<label for="google_client_id"><?php esc_html_e( 'OAuth Client ID', 'marketing-analytics-chat' ); ?></label>
					</th>
					<td>
						<input type="text" id="google_client_id" name="google_client_id" class="large-text" value="" />
						<p class="description">
							<?php
							printf(
								/* translators: %s: link to Google Cloud Console */
								esc_html__( 'Get your OAuth credentials from the %s', 'marketing-analytics-chat' ),
								'<a href="https://console.cloud.google.com/apis/credentials" target="_blank">' . esc_html__( 'Google Cloud Console', 'marketing-analytics-chat' ) . '</a>'
							);
							?>
						</p>
					</td>
				</tr>
				<tr>
					<th scope="row">
						<label for="google_client_secret"><?php esc_html_e( 'OAuth Client Secret', 'marketing-analytics-chat' ); ?></label>
					</th>
					<td>
						<input type="password" id="google_client_secret" name="google_client_secret" class="large-text" value="" />
					</td>
				</tr>
				<tr>
					<th scope="row">
						<?php esc_html_e( 'Redirect URI', 'marketing-analytics-chat' ); ?>
					</th>
					<td>
						<code><?php echo esc_html( $oauth_handler->get_redirect_uri() ); ?></code>
						<p class="description"><?php esc_html_e( 'Add this URL as an authorized redirect URI in your Google Cloud Console OAuth configuration.', 'marketing-analytics-chat' ); ?></p>
					</td>
				</tr>
			</table>

			<p class="submit">
				<input type="submit" name="save_oauth_config" class="button button-primary" value="<?php esc_attr_e( 'Save OAuth Credentials', 'marketing-analytics-chat' ); ?>" />
			</p>
		</form>

	<?php elseif ( ! $is_authenticated ) : ?>
		<!-- Step 2: Authenticate with Google -->
		<div class="notice notice-info">
			<p><strong><?php esc_html_e( 'Step 2: Connect to Google Analytics', 'marketing-analytics-chat' ); ?></strong></p>
			<p><?php esc_html_e( 'Click the button below to authorize access to your Google Analytics 4 properties.', 'marketing-analytics-chat' ); ?></p>
		</div>

		<div class="notice notice-warning" style="border-left-color: #00a0d2;">
			<p><strong><?php esc_html_e( 'Before you connect:', 'marketing-analytics-chat' ); ?></strong></p>
			<p><?php esc_html_e( 'Ensure these APIs are enabled in your Google Cloud project, or you will see errors when selecting properties:', 'marketing-analytics-chat' ); ?></p>
			<ul style="list-style: disc; margin-left: 20px;">
				<li><strong><?php esc_html_e( 'Google Analytics Data API', 'marketing-analytics-chat' ); ?></strong></li>
				<li><strong><?php esc_html_e( 'Google Analytics Admin API', 'marketing-analytics-chat' ); ?></strong></li>
			</ul>
			<p>
				<a href="<?php echo esc_url( 'https://console.cloud.google.com/apis/library/analyticsdata.googleapis.com' ); ?>" target="_blank" class="button button-secondary button-small">
					<?php esc_html_e( 'Enable Data API', 'marketing-analytics-chat' ); ?>
				</a>
				<a href="<?php echo esc_url( 'https://console.cloud.google.com/apis/library/analyticsadmin.googleapis.com' ); ?>" target="_blank" class="button button-secondary button-small">
					<?php esc_html_e( 'Enable Admin API', 'marketing-analytics-chat' ); ?>
				</a>
			</p>
		</div>

		<p>
			<strong><?php esc_html_e( 'OAuth Client ID:', 'marketing-analytics-chat' ); ?></strong>
			<?php echo esc_html( substr( $client_id, 0, 20 ) . '...' ); ?>
			<a href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin.php?page=marketing-analytics-chat-connections&tab=ga4&reset_oauth=1' ), 'reset_oauth' ) ); ?>" style="margin-left: 10px;" onclick="return confirm('<?php echo esc_js( __( 'Are you sure you want to reset OAuth credentials? This will disconnect all Google services.', 'marketing-analytics-chat' ) ); ?>');">
				<?php esc_html_e( 'Reset OAuth Credentials', 'marketing-analytics-chat' ); ?>
			</a>
		</p>

		<p class="submit">
			<a href="<?php echo esc_url( $oauth_handler->get_auth_url( 'ga4' ) ); ?>" class="button button-primary button-large">
				<span class="dashicons dashicons-google" style="margin-top: 3px;"></span>
				<?php esc_html_e( 'Connect to Google Analytics 4', 'marketing-analytics-chat' ); ?>
			</a>
		</p>

	<?php else : ?>
		<!-- Step 3: Select Property -->
		<div class="notice notice-success">
			<p><strong><?php esc_html_e( 'Step 3: Select Your GA4 Property', 'marketing-analytics-chat' ); ?></strong></p>
			<p><?php esc_html_e( 'You are authenticated with Google. Select the GA4 property you want to use.', 'marketing-analytics-chat' ); ?></p>
		</div>

		<table class="form-table">
			<tr>
				<th scope="row">
					<label for="ga4_property"><?php esc_html_e( 'GA4 Property', 'marketing-analytics-chat' ); ?></label>
				</th>
				<td>
					<select id="ga4_property" name="ga4_property" class="regular-text">
						<option value=""><?php esc_html_e( 'Loading properties...', 'marketing-analytics-chat' ); ?></option>
					</select>
					<p class="description"><?php esc_html_e( 'Select the Google Analytics 4 property you want to connect to.', 'marketing-analytics-chat' ); ?></p>
					<div id="ga4-property-error" style="color: #dc3232; margin-top: 5px; display: none;"></div>
				</td>
			</tr>
		</table>

		<p class="submit">
			<button type="button" id="save-ga4-property" class="button button-primary">
				<?php esc_html_e( 'Save Property Selection', 'marketing-analytics-chat' ); ?>
			</button>
			<a href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin.php?page=marketing-analytics-chat-connections&tab=ga4&disconnect=1' ), 'disconnect_ga4' ) ); ?>" class="button button-secondary" onclick="return confirm('<?php echo esc_js( __( 'Are you sure you want to disconnect from Google Analytics 4?', 'marketing-analytics-chat' ) ); ?>');">
				<?php esc_html_e( 'Disconnect', 'marketing-analytics-chat' ); ?>
			</a>
		</p>

		<script>
		jQuery(document).ready(function($) {
			// Load GA4 properties
			function loadGA4Properties() {
				$('#ga4_property').html('<option value=""><?php echo esc_js( __( 'Loading properties...', 'marketing-analytics-chat' ) ); ?></option>');
				$('#ga4-property-error').hide();

				$.ajax({
					url: ajaxurl,
					type: 'POST',
					data: {
						action: 'marketing_analytics_mcp_get_ga4_properties',
						nonce: '<?php echo esc_js( wp_create_nonce( 'marketing-analytics-chat-admin' ) ); ?>'
					},
					success: function(response) {
						if (response.success && response.data.properties) {
							var html = '<option value=""><?php echo esc_js( __( '-- Select a property --', 'marketing-analytics-chat' ) ); ?></option>';
							$.each(response.data.properties, function(i, prop) {
								var selected = prop.name === '<?php echo esc_js( $saved_property_id ); ?>' ? ' selected' : '';
								html += '<option value="' + prop.name + '"' + selected + '>' + prop.displayName + ' (' + prop.name + ')</option>';
							});
							$('#ga4_property').html(html);
						} else {
							$('#ga4_property').html('<option value=""><?php echo esc_js( __( 'Failed to load properties', 'marketing-analytics-chat' ) ); ?></option>');
							$('#ga4-property-error').text(response.data && response.data.message ? response.data.message : 'Failed to load properties').show();
						}
					},
					error: function() {
						$('#ga4_property').html('<option value=""><?php echo esc_js( __( 'Error loading properties', 'marketing-analytics-chat' ) ); ?></option>');
						$('#ga4-property-error').text('<?php echo esc_js( __( 'Network error. Please try again.', 'marketing-analytics-chat' ) ); ?>').show();
					}
				});
			}

			// Load properties on page load
			loadGA4Properties();

			// Save property selection
			$('#save-ga4-property').on('click', function() {
				var propertyId = $('#ga4_property').val();
				if (!propertyId) {
					alert('<?php echo esc_js( __( 'Please select a property', 'marketing-analytics-chat' ) ); ?>');
					return;
				}

				var $btn = $(this);
				$btn.prop('disabled', true).text('<?php echo esc_js( __( 'Saving...', 'marketing-analytics-chat' ) ); ?>');

				$.ajax({
					url: ajaxurl,
					type: 'POST',
					data: {
						action: 'marketing_analytics_mcp_save_ga4_property',
						nonce: '<?php echo esc_js( wp_create_nonce( 'marketing-analytics-chat-admin' ) ); ?>',
						property_id: propertyId
					},
					success: function(response) {
						if (response.success) {
							location.reload();
						} else {
							alert(response.data && response.data.message ? response.data.message : 'Failed to save property');
							$btn.prop('disabled', false).text('<?php echo esc_js( __( 'Save Property Selection', 'marketing-analytics-chat' ) ); ?>');
						}
					},
					error: function() {
						alert('<?php echo esc_js( __( 'Network error. Please try again.', 'marketing-analytics-chat' ) ); ?>');
						$btn.prop('disabled', false).text('<?php echo esc_js( __( 'Save Property Selection', 'marketing-analytics-chat' ) ); ?>');
					}
				});
			});
		});
		</script>
	<?php endif; ?>
</div>
