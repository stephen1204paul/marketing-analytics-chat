<?php
/**
 * Google Search Console Connection View
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
$gsc_credentials  = $credential_manager->get_credentials( 'gsc' );
$is_authenticated = ! empty( $gsc_credentials ) && isset( $gsc_credentials['access_token'] );

// Get saved site URL
$saved_site_url = '';
if ( $is_authenticated && isset( $gsc_credentials['site_url'] ) ) {
	$saved_site_url = $gsc_credentials['site_url'];
}

// Get connection status
$settings      = get_option( 'marketing_analytics_mcp_settings', array() );
$platforms     = isset( $settings['platforms'] ) ? $settings['platforms'] : array();
$gsc_connected = isset( $platforms['gsc']['connected'] ) && $platforms['gsc']['connected'];
?>

<div class="connection-panel">
	<h3>
		<?php esc_html_e( 'Google Search Console Configuration', 'marketing-analytics-chat' ); ?>
		<?php if ( $gsc_connected ) : ?>
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
	<p><?php esc_html_e( 'Connect to Google Search Console to access search performance data, indexing status, and query analytics.', 'marketing-analytics-chat' ); ?></p>

	<?php if ( ! $has_oauth_creds ) : ?>
		<!-- Step 1: Configure OAuth Credentials (shared with GA4) -->
		<div class="notice notice-warning">
			<p><strong><?php esc_html_e( 'OAuth Credentials Required', 'marketing-analytics-chat' ); ?></strong></p>
			<p>
				<?php
				printf(
					/* translators: %s: link to GA4 tab */
					esc_html__( 'Please configure OAuth credentials in the %s tab first. The same credentials are used for both GA4 and Search Console.', 'marketing-analytics-chat' ),
					'<a href="?page=marketing-analytics-chat-connections&tab=ga4">' . esc_html__( 'Google Analytics 4', 'marketing-analytics-chat' ) . '</a>'
				);
				?>
			</p>
		</div>

	<?php elseif ( ! $is_authenticated ) : ?>
		<!-- Step 2: Authenticate with Google -->
		<div class="notice notice-info">
			<p><strong><?php esc_html_e( 'Step 2: Connect to Google Search Console', 'marketing-analytics-chat' ); ?></strong></p>
			<p><?php esc_html_e( 'Click the button below to authorize access to your Search Console properties.', 'marketing-analytics-chat' ); ?></p>
		</div>

		<p>
			<strong><?php esc_html_e( 'OAuth Client ID:', 'marketing-analytics-chat' ); ?></strong>
			<?php echo esc_html( substr( $client_id, 0, 20 ) . '...' ); ?>
			<a href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin.php?page=marketing-analytics-chat-connections&tab=ga4&reset_oauth=1' ), 'reset_oauth' ) ); ?>" style="margin-left: 10px;" onclick="return confirm('<?php echo esc_js( __( 'Are you sure you want to reset OAuth credentials? This will disconnect all Google services.', 'marketing-analytics-chat' ) ); ?>');">
				<?php esc_html_e( 'Reset OAuth Credentials', 'marketing-analytics-chat' ); ?>
			</a>
		</p>

		<p class="submit">
			<a href="<?php echo esc_url( $oauth_handler->get_auth_url( 'gsc' ) ); ?>" class="button button-primary button-large">
				<span class="dashicons dashicons-google" style="margin-top: 3px;"></span>
				<?php esc_html_e( 'Connect to Google Search Console', 'marketing-analytics-chat' ); ?>
			</a>
		</p>

	<?php else : ?>
		<!-- Step 3: Select Site -->
		<div class="notice notice-success">
			<p><strong><?php esc_html_e( 'Step 3: Select Your Search Console Property', 'marketing-analytics-chat' ); ?></strong></p>
			<p><?php esc_html_e( 'You are authenticated with Google. Select the property you want to use.', 'marketing-analytics-chat' ); ?></p>
		</div>

		<table class="form-table">
			<tr>
				<th scope="row">
					<label for="gsc_site_url"><?php esc_html_e( 'Search Console Property', 'marketing-analytics-chat' ); ?></label>
				</th>
				<td>
					<select id="gsc_site_url" name="gsc_site_url" class="regular-text">
						<option value=""><?php esc_html_e( 'Loading properties...', 'marketing-analytics-chat' ); ?></option>
					</select>
					<p class="description"><?php esc_html_e( 'Select the Search Console property you want to connect to.', 'marketing-analytics-chat' ); ?></p>
					<div id="gsc-property-error" style="color: #dc3232; margin-top: 5px; display: none;"></div>
				</td>
			</tr>
		</table>

		<p class="submit">
			<button type="button" id="save-gsc-property" class="button button-primary">
				<?php esc_html_e( 'Save Property Selection', 'marketing-analytics-chat' ); ?>
			</button>
			<a href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin.php?page=marketing-analytics-chat-connections&tab=gsc&disconnect=1' ), 'disconnect_gsc' ) ); ?>" class="button button-secondary" onclick="return confirm('<?php echo esc_js( __( 'Are you sure you want to disconnect from Google Search Console?', 'marketing-analytics-chat' ) ); ?>');">
				<?php esc_html_e( 'Disconnect', 'marketing-analytics-chat' ); ?>
			</a>
		</p>

		<script>
		jQuery(document).ready(function($) {
			// Load GSC properties
			function loadGSCProperties() {
				$('#gsc_site_url').html('<option value=""><?php echo esc_js( __( 'Loading properties...', 'marketing-analytics-chat' ) ); ?></option>');
				$('#gsc-property-error').hide();

				$.ajax({
					url: ajaxurl,
					type: 'POST',
					data: {
						action: 'marketing_analytics_mcp_get_gsc_properties',
						nonce: '<?php echo esc_js( wp_create_nonce( 'marketing-analytics-chat-admin' ) ); ?>'
					},
					success: function(response) {
						if (response.success && response.data.properties) {
							var html = '<option value=""><?php echo esc_js( __( '-- Select a property --', 'marketing-analytics-chat' ) ); ?></option>';
							$.each(response.data.properties, function(i, prop) {
								var selected = prop.siteUrl === '<?php echo esc_js( $saved_site_url ); ?>' ? ' selected' : '';
								html += '<option value="' + prop.siteUrl + '"' + selected + '>' + prop.siteUrl + '</option>';
							});
							$('#gsc_site_url').html(html);
						} else {
							$('#gsc_site_url').html('<option value=""><?php echo esc_js( __( 'Failed to load properties', 'marketing-analytics-chat' ) ); ?></option>');
							$('#gsc-property-error').text(response.data && response.data.message ? response.data.message : 'Failed to load properties').show();
						}
					},
					error: function() {
						$('#gsc_site_url').html('<option value=""><?php echo esc_js( __( 'Error loading properties', 'marketing-analytics-chat' ) ); ?></option>');
						$('#gsc-property-error').text('<?php echo esc_js( __( 'Network error. Please try again.', 'marketing-analytics-chat' ) ); ?>').show();
					}
				});
			}

			// Load properties on page load
			loadGSCProperties();

			// Save property selection
			$('#save-gsc-property').on('click', function() {
				var siteUrl = $('#gsc_site_url').val();
				if (!siteUrl) {
					alert('<?php echo esc_js( __( 'Please select a property', 'marketing-analytics-chat' ) ); ?>');
					return;
				}

				var $btn = $(this);
				$btn.prop('disabled', true).text('<?php echo esc_js( __( 'Saving...', 'marketing-analytics-chat' ) ); ?>');

				$.ajax({
					url: ajaxurl,
					type: 'POST',
					data: {
						action: 'marketing_analytics_mcp_save_gsc_property',
						nonce: '<?php echo esc_js( wp_create_nonce( 'marketing-analytics-chat-admin' ) ); ?>',
						site_url: siteUrl
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
