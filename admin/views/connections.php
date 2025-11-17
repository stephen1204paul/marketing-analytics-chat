<?php
/**
 * Connections Page Template
 *
 * @package Marketing_Analytics_MCP
 */

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use Marketing_Analytics_MCP\Credentials\Encryption;
use Marketing_Analytics_MCP\Credentials\OAuth_Handler;
use Marketing_Analytics_MCP\Credentials\Credential_Manager;

$active_tab = isset( $_GET['tab'] ) ? sanitize_key( $_GET['tab'] ) : 'clarity';
$success_message = '';
$error_message = '';

// Initialize OAuth handler and Credential Manager
$oauth_handler = new OAuth_Handler();
$credential_manager = new Credential_Manager();

// Handle OAuth callback
if ( isset( $_GET['oauth_callback'] ) && isset( $_GET['code'] ) && isset( $_GET['state'] ) ) {
	$code = sanitize_text_field( wp_unslash( $_GET['code'] ) );
	$state = sanitize_text_field( wp_unslash( $_GET['state'] ) );

	$result = $oauth_handler->handle_callback( $code, $state );

	if ( $result['success'] ) {
		$success_message = $result['message'];
		$active_tab = $result['service']; // Switch to the service tab
	} else {
		$error_message = $result['message'];
	}
}

// Handle OAuth credential setup
if ( isset( $_POST['save_google_oauth'] ) && check_admin_referer( 'marketing_analytics_mcp_save_google_oauth', 'google_oauth_nonce' ) ) {
	$client_id = isset( $_POST['google_client_id'] ) ? sanitize_text_field( wp_unslash( $_POST['google_client_id'] ) ) : '';
	$client_secret = isset( $_POST['google_client_secret'] ) ? sanitize_text_field( wp_unslash( $_POST['google_client_secret'] ) ) : '';

	if ( ! empty( $client_id ) && ! empty( $client_secret ) ) {
		if ( $oauth_handler->set_oauth_credentials( $client_id, $client_secret ) ) {
			$success_message = __( 'Google OAuth credentials saved successfully!', 'marketing-analytics-mcp' );
		} else {
			$error_message = __( 'Failed to save Google OAuth credentials.', 'marketing-analytics-mcp' );
		}
	} else {
		$error_message = __( 'Please fill in both Client ID and Client Secret.', 'marketing-analytics-mcp' );
	}
}

// Handle OAuth disconnect
if ( isset( $_POST['disconnect_oauth'] ) && check_admin_referer( 'marketing_analytics_mcp_disconnect_oauth', 'disconnect_oauth_nonce' ) ) {
	$service = isset( $_POST['service'] ) ? sanitize_text_field( wp_unslash( $_POST['service'] ) ) : '';

	if ( in_array( $service, array( 'ga4', 'gsc' ), true ) ) {
		if ( $oauth_handler->revoke_access( $service ) ) {
			$success_message = sprintf(
				/* translators: %s: service name */
				__( 'Successfully disconnected from %s.', 'marketing-analytics-mcp' ),
				$service === 'ga4' ? 'Google Analytics 4' : 'Google Search Console'
			);
			$active_tab = $service;
		} else {
			$error_message = __( 'Failed to disconnect.', 'marketing-analytics-mcp' );
		}
	}
}

// Handle form submission for saving Clarity credentials
if ( isset( $_POST['save_clarity'] ) && check_admin_referer( 'marketing_analytics_mcp_save_clarity', 'clarity_nonce' ) ) {
	$api_token = isset( $_POST['clarity_api_token'] ) ? sanitize_text_field( wp_unslash( $_POST['clarity_api_token'] ) ) : '';
	$project_id = isset( $_POST['clarity_project_id'] ) ? sanitize_text_field( wp_unslash( $_POST['clarity_project_id'] ) ) : '';

	// Get existing credentials
	$existing_credentials = Encryption::get_credentials( 'clarity' );

	// If API token is empty or is the masked display value, keep the existing token
	if ( empty( $api_token ) || ( $existing_credentials && strpos( $api_token, '...' ) !== false ) ) {
		if ( $existing_credentials && isset( $existing_credentials['api_token'] ) ) {
			$api_token = $existing_credentials['api_token'];
		}
	}

	if ( ! empty( $api_token ) && ! empty( $project_id ) ) {
		$credentials = array(
			'api_token'  => $api_token,
			'project_id' => $project_id,
		);

		if ( Encryption::save_credentials( 'clarity', $credentials ) ) {
			// Update platform status to connected
			$settings = get_option( 'marketing_analytics_mcp_settings', array() );
			if ( ! isset( $settings['platforms'] ) ) {
				$settings['platforms'] = array();
			}
			if ( ! isset( $settings['platforms']['clarity'] ) ) {
				$settings['platforms']['clarity'] = array();
			}
			$settings['platforms']['clarity']['connected'] = true;
			$settings['platforms']['clarity']['enabled'] = true;
			update_option( 'marketing_analytics_mcp_settings', $settings );

			$success_message = __( 'Clarity credentials saved successfully!', 'marketing-analytics-mcp' );
		} else {
			$error_message = __( 'Failed to save Clarity credentials.', 'marketing-analytics-mcp' );
		}
	} else {
		$error_message = __( 'Please fill in all required fields.', 'marketing-analytics-mcp' );
	}
}

// Get saved credentials for display
$saved_clarity = Encryption::get_credentials( 'clarity' );
$clarity_project_id = $saved_clarity && isset( $saved_clarity['project_id'] ) ? $saved_clarity['project_id'] : '';
$clarity_has_token = $saved_clarity && isset( $saved_clarity['api_token'] ) && ! empty( $saved_clarity['api_token'] );
// Show masked token for display (first 10 chars + ... + last 10 chars)
$clarity_token_display = '';
if ( $clarity_has_token ) {
	$token = $saved_clarity['api_token'];
	if ( strlen( $token ) > 30 ) {
		$clarity_token_display = substr( $token, 0, 10 ) . '...' . substr( $token, -10 );
	} else {
		$clarity_token_display = str_repeat( '•', strlen( $token ) );
	}
}

// Get platform connection status - check actual credentials instead of manual flag
$settings = get_option( 'marketing_analytics_mcp_settings', array() );
$platforms = isset( $settings['platforms'] ) ? $settings['platforms'] : array();
$clarity_connected = $credential_manager->has_credentials( 'clarity' );
?>

<div class="wrap marketing-analytics-mcp-connections">
	<h1><?php echo esc_html( get_admin_page_title() ); ?></h1>

	<?php if ( $success_message ) : ?>
		<div class="notice notice-success is-dismissible">
			<p><?php echo esc_html( $success_message ); ?></p>
		</div>
	<?php endif; ?>

	<?php if ( $error_message ) : ?>
		<div class="notice notice-error is-dismissible">
			<p><?php echo esc_html( $error_message ); ?></p>
		</div>
	<?php endif; ?>

	<p class="description">
		<?php esc_html_e( 'Configure API credentials for your marketing analytics platforms.', 'marketing-analytics-mcp' ); ?>
	</p>

	<h2 class="nav-tab-wrapper">
		<a href="?page=marketing-analytics-mcp-connections&tab=clarity" class="nav-tab <?php echo $active_tab === 'clarity' ? 'nav-tab-active' : ''; ?>">
			<?php esc_html_e( 'Microsoft Clarity', 'marketing-analytics-mcp' ); ?>
		</a>
		<a href="?page=marketing-analytics-mcp-connections&tab=ga4" class="nav-tab <?php echo $active_tab === 'ga4' ? 'nav-tab-active' : ''; ?>">
			<?php esc_html_e( 'Google Analytics 4', 'marketing-analytics-mcp' ); ?>
		</a>
		<a href="?page=marketing-analytics-mcp-connections&tab=gsc" class="nav-tab <?php echo $active_tab === 'gsc' ? 'nav-tab-active' : ''; ?>">
			<?php esc_html_e( 'Google Search Console', 'marketing-analytics-mcp' ); ?>
		</a>
	</h2>

	<div class="tab-content">
		<?php
		switch ( $active_tab ) {
			case 'clarity':
				?>
				<div class="connection-panel">
					<h3>
						<?php esc_html_e( 'Microsoft Clarity Configuration', 'marketing-analytics-mcp' ); ?>
						<?php if ( $clarity_connected ) : ?>
							<span class="status-badge" style="background: #46b450; color: white; padding: 4px 12px; border-radius: 3px; font-size: 13px; margin-left: 10px; font-weight: normal;">
								<span class="dashicons dashicons-yes-alt" style="font-size: 14px; margin-top: 2px;"></span>
								<?php esc_html_e( 'Connected', 'marketing-analytics-mcp' ); ?>
							</span>
						<?php else : ?>
							<span class="status-badge" style="background: #dc3232; color: white; padding: 4px 12px; border-radius: 3px; font-size: 13px; margin-left: 10px; font-weight: normal;">
								<span class="dashicons dashicons-warning" style="font-size: 14px; margin-top: 2px;"></span>
								<?php esc_html_e( 'Not Connected', 'marketing-analytics-mcp' ); ?>
							</span>
						<?php endif; ?>
					</h3>
					<p><?php esc_html_e( 'Connect to Microsoft Clarity to access session recordings, heatmaps, and user behavior insights.', 'marketing-analytics-mcp' ); ?></p>

					<form method="post" action="" class="clarity-connection-form">
						<?php wp_nonce_field( 'marketing_analytics_mcp_save_clarity', 'clarity_nonce' ); ?>

						<table class="form-table">
							<tr>
								<th scope="row">
									<label for="clarity_api_token"><?php esc_html_e( 'API Token', 'marketing-analytics-mcp' ); ?></label>
								</th>
								<td>
									<input
										type="text"
										id="clarity_api_token"
										name="clarity_api_token"
										class="regular-text"
										value="<?php echo esc_attr( $clarity_token_display ); ?>"
										placeholder="<?php echo $clarity_has_token ? esc_attr__( 'Token saved (enter new token to update)', 'marketing-analytics-mcp' ) : esc_attr__( 'Enter your API token', 'marketing-analytics-mcp' ); ?>"
										<?php echo $clarity_has_token ? 'readonly onfocus="this.removeAttribute(\'readonly\'); this.value=\'\'; this.type=\'password\';"' : 'type="password"'; ?>
									/>
									<?php if ( $clarity_has_token ) : ?>
										<p class="description" style="color: #46b450;">
											<span class="dashicons dashicons-yes-alt"></span>
											<?php esc_html_e( 'API token is securely stored and encrypted. Leave blank to keep current token, or enter a new one to update.', 'marketing-analytics-mcp' ); ?>
										</p>
									<?php else : ?>
										<p class="description">
											<?php
											printf(
												/* translators: %s: link to Clarity documentation */
												esc_html__( 'Get your API token from the %s.', 'marketing-analytics-mcp' ),
												'<a href="https://learn.microsoft.com/en-us/clarity/setup-and-installation/clarity-data-export-api" target="_blank">' . esc_html__( 'Clarity Data Export API', 'marketing-analytics-mcp' ) . '</a>'
											);
											?>
										</p>
									<?php endif; ?>
								</td>
							</tr>
							<tr>
								<th scope="row">
									<label for="clarity_project_id"><?php esc_html_e( 'Project ID', 'marketing-analytics-mcp' ); ?></label>
								</th>
								<td>
									<input type="text" id="clarity_project_id" name="clarity_project_id" class="regular-text" value="<?php echo esc_attr( $clarity_project_id ); ?>" />
									<p class="description"><?php esc_html_e( 'Your Clarity project ID (for reference only - the API token identifies your project)', 'marketing-analytics-mcp' ); ?></p>
								</td>
							</tr>
						</table>

						<p class="submit">
							<button type="button" class="button button-secondary test-connection" data-platform="clarity">
								<?php esc_html_e( 'Test Connection', 'marketing-analytics-mcp' ); ?>
							</button>
							<input type="submit" name="save_clarity" class="button button-primary" value="<?php esc_attr_e( 'Save Credentials', 'marketing-analytics-mcp' ); ?>" />
						</p>
					</form>
				</div>
				<?php
				break;

			case 'ga4':
				$has_oauth_credentials = $oauth_handler->has_oauth_credentials();
				$is_connected = $credential_manager->has_credentials( 'ga4' );
				$auth_url = $has_oauth_credentials ? $oauth_handler->get_auth_url( 'ga4' ) : null;
				$current_property_id = get_option( 'marketing_analytics_mcp_ga4_property_id' );
				?>
				<div class="connection-panel">
					<h3>
						<?php esc_html_e( 'Google Analytics 4 Configuration', 'marketing-analytics-mcp' ); ?>
						<?php if ( $is_connected ) : ?>
							<span class="status-badge" style="background: #46b450; color: white; padding: 4px 12px; border-radius: 3px; font-size: 13px; margin-left: 10px; font-weight: normal;">
								<span class="dashicons dashicons-yes-alt" style="font-size: 14px; margin-top: 2px;"></span>
								<?php esc_html_e( 'Connected', 'marketing-analytics-mcp' ); ?>
							</span>
						<?php else : ?>
							<span class="status-badge" style="background: #dc3232; color: white; padding: 4px 12px; border-radius: 3px; font-size: 13px; margin-left: 10px; font-weight: normal;">
								<span class="dashicons dashicons-warning" style="font-size: 14px; margin-top: 2px;"></span>
								<?php esc_html_e( 'Not Connected', 'marketing-analytics-mcp' ); ?>
							</span>
						<?php endif; ?>
					</h3>
					<p><?php esc_html_e( 'Connect to Google Analytics 4 to access traffic metrics, user behavior, and conversion data.', 'marketing-analytics-mcp' ); ?></p>

					<?php if ( ! $has_oauth_credentials ) : ?>
						<!-- Step 1: Configure OAuth Credentials -->
						<div class="notice notice-warning">
							<p><strong><?php esc_html_e( 'Setup Required:', 'marketing-analytics-mcp' ); ?></strong> <?php esc_html_e( 'You need to configure Google OAuth credentials first.', 'marketing-analytics-mcp' ); ?></p>
						</div>

						<h4><?php esc_html_e( 'Step 1: Configure Google OAuth Credentials', 'marketing-analytics-mcp' ); ?></h4>
						<p>
							<?php
							printf(
								/* translators: %s: link to Google Cloud Console */
								esc_html__( 'Create OAuth 2.0 credentials in the %s.', 'marketing-analytics-mcp' ),
								'<a href="https://console.cloud.google.com/apis/credentials" target="_blank">' . esc_html__( 'Google Cloud Console', 'marketing-analytics-mcp' ) . '</a>'
							);
							?>
						</p>

						<form method="post" action="" style="margin-top: 20px;">
							<?php wp_nonce_field( 'marketing_analytics_mcp_save_google_oauth', 'google_oauth_nonce' ); ?>

							<table class="form-table">
								<tr>
									<th scope="row">
										<label for="google_client_id"><?php esc_html_e( 'OAuth Client ID', 'marketing-analytics-mcp' ); ?></label>
									</th>
									<td>
										<input type="text" id="google_client_id" name="google_client_id" class="regular-text" value="<?php echo esc_attr( $oauth_handler->get_client_id() ?: '' ); ?>" />
										<p class="description"><?php esc_html_e( 'Your Google OAuth 2.0 Client ID', 'marketing-analytics-mcp' ); ?></p>
									</td>
								</tr>
								<tr>
									<th scope="row">
										<label for="google_client_secret"><?php esc_html_e( 'OAuth Client Secret', 'marketing-analytics-mcp' ); ?></label>
									</th>
									<td>
										<input type="password" id="google_client_secret" name="google_client_secret" class="regular-text" placeholder="<?php esc_attr_e( 'Enter your Client Secret', 'marketing-analytics-mcp' ); ?>" />
										<p class="description"><?php esc_html_e( 'Your Google OAuth 2.0 Client Secret', 'marketing-analytics-mcp' ); ?></p>
									</td>
								</tr>
								<tr>
									<th scope="row">
										<label><?php esc_html_e( 'Redirect URI', 'marketing-analytics-mcp' ); ?></label>
									</th>
									<td>
										<code style="background: #f0f0f1; padding: 5px 10px; display: inline-block;"><?php echo esc_html( $oauth_handler->get_redirect_uri() ); ?></code>
										<p class="description"><?php esc_html_e( 'Add this as an authorized redirect URI in your Google Cloud Console OAuth configuration', 'marketing-analytics-mcp' ); ?></p>
									</td>
								</tr>
							</table>

							<p class="submit">
								<input type="submit" name="save_google_oauth" class="button button-primary" value="<?php esc_attr_e( 'Save OAuth Credentials', 'marketing-analytics-mcp' ); ?>" />
							</p>
						</form>

					<?php elseif ( ! $is_connected ) : ?>
						<!-- Step 2: Authorize with Google -->
						<div class="notice notice-info">
							<p><strong><?php esc_html_e( 'OAuth credentials configured!', 'marketing-analytics-mcp' ); ?></strong> <?php esc_html_e( 'Now authorize access to your Google Analytics account.', 'marketing-analytics-mcp' ); ?></p>
						</div>

						<h4><?php esc_html_e( 'Step 2: Authorize Google Analytics', 'marketing-analytics-mcp' ); ?></h4>
						<p><?php esc_html_e( 'Click the button below to connect your Google Analytics 4 account:', 'marketing-analytics-mcp' ); ?></p>

						<?php if ( $auth_url ) : ?>
							<p class="submit">
								<a href="<?php echo esc_url( $auth_url ); ?>" class="button button-primary button-large">
									<span class="dashicons dashicons-google" style="margin-top: 3px;"></span>
									<?php esc_html_e( 'Connect to Google Analytics', 'marketing-analytics-mcp' ); ?>
								</a>
							</p>
						<?php else : ?>
							<p class="description" style="color: #dc3232;">
								<?php esc_html_e( 'Error generating authorization URL. Please check your OAuth credentials.', 'marketing-analytics-mcp' ); ?>
							</p>
						<?php endif; ?>

					<?php else : ?>
						<!-- Connected State -->
						<div class="notice notice-success">
							<p><strong><?php esc_html_e( 'Connected!', 'marketing-analytics-mcp' ); ?></strong> <?php esc_html_e( 'Your Google Analytics 4 account is connected and ready to use.', 'marketing-analytics-mcp' ); ?></p>
						</div>

						<!-- Property Selection -->
						<h4><?php esc_html_e( 'Select GA4 Property', 'marketing-analytics-mcp' ); ?></h4>
						<p><?php esc_html_e( 'Choose which Google Analytics 4 property to query for data:', 'marketing-analytics-mcp' ); ?></p>

						<table class="form-table">
							<tr>
								<th scope="row">
									<label for="ga4_property_selector"><?php esc_html_e( 'GA4 Property', 'marketing-analytics-mcp' ); ?></label>
								</th>
								<td>
									<div id="ga4-property-loading" style="display: none;">
										<span class="spinner is-active" style="float: none; margin: 0 10px 0 0;"></span>
										<?php esc_html_e( 'Loading properties...', 'marketing-analytics-mcp' ); ?>
									</div>

									<div id="ga4-property-error" class="notice notice-error inline" style="display: none; margin: 10px 0; padding: 10px;">
										<p></p>
									</div>

									<select id="ga4_property_selector" name="ga4_property_id" class="regular-text" style="display: none;">
										<option value=""><?php esc_html_e( 'Select a property...', 'marketing-analytics-mcp' ); ?></option>
									</select>

									<?php if ( $current_property_id ) : ?>
										<p class="description" style="color: #46b450;">
											<span class="dashicons dashicons-yes-alt"></span>
											<?php
											printf(
												/* translators: %s: property ID */
												esc_html__( 'Currently selected property: %s', 'marketing-analytics-mcp' ),
												'<strong>' . esc_html( $current_property_id ) . '</strong>'
											);
											?>
										</p>
									<?php else : ?>
										<p class="description">
											<?php esc_html_e( 'No property selected yet. Please select a property from the list above.', 'marketing-analytics-mcp' ); ?>
										</p>
									<?php endif; ?>
								</td>
							</tr>
						</table>

						<p class="submit">
							<button type="button" id="load-ga4-properties" class="button button-secondary">
								<?php esc_html_e( 'Load Available Properties', 'marketing-analytics-mcp' ); ?>
							</button>
							<button type="button" id="save-ga4-property" class="button button-primary" style="display: none;">
								<?php esc_html_e( 'Save Selected Property', 'marketing-analytics-mcp' ); ?>
							</button>
						</p>

						<hr style="margin: 30px 0;" />

						<form method="post" action="" style="margin-top: 20px;">
							<?php wp_nonce_field( 'marketing_analytics_mcp_disconnect_oauth', 'disconnect_oauth_nonce' ); ?>
							<input type="hidden" name="service" value="ga4" />

							<p class="submit">
								<button type="button" class="button button-secondary test-connection" data-platform="ga4">
									<?php esc_html_e( 'Test Connection', 'marketing-analytics-mcp' ); ?>
								</button>
								<input type="submit" name="disconnect_oauth" class="button button-secondary" value="<?php esc_attr_e( 'Disconnect', 'marketing-analytics-mcp' ); ?>" onclick="return confirm('<?php esc_attr_e( 'Are you sure you want to disconnect from Google Analytics?', 'marketing-analytics-mcp' ); ?>');" />
							</p>
						</form>
					<?php endif; ?>
				</div>
				<?php
				break;

			case 'gsc':
				$has_oauth_credentials = $oauth_handler->has_oauth_credentials();
				$is_connected = $credential_manager->has_credentials( 'gsc' );
				$auth_url = $has_oauth_credentials ? $oauth_handler->get_auth_url( 'gsc' ) : null;
				$current_site_url = get_option( 'marketing_analytics_mcp_gsc_site_url' );
				?>
				<div class="connection-panel">
					<h3>
						<?php esc_html_e( 'Google Search Console Configuration', 'marketing-analytics-mcp' ); ?>
						<?php if ( $is_connected ) : ?>
							<span class="status-badge" style="background: #46b450; color: white; padding: 4px 12px; border-radius: 3px; font-size: 13px; margin-left: 10px; font-weight: normal;">
								<span class="dashicons dashicons-yes-alt" style="font-size: 14px; margin-top: 2px;"></span>
								<?php esc_html_e( 'Connected', 'marketing-analytics-mcp' ); ?>
							</span>
						<?php else : ?>
							<span class="status-badge" style="background: #dc3232; color: white; padding: 4px 12px; border-radius: 3px; font-size: 13px; margin-left: 10px; font-weight: normal;">
								<span class="dashicons dashicons-warning" style="font-size: 14px; margin-top: 2px;"></span>
								<?php esc_html_e( 'Not Connected', 'marketing-analytics-mcp' ); ?>
							</span>
						<?php endif; ?>
					</h3>
					<p><?php esc_html_e( 'Connect to Google Search Console to access search performance data, indexing status, and query analytics.', 'marketing-analytics-mcp' ); ?></p>

					<?php if ( ! $has_oauth_credentials ) : ?>
						<!-- Step 1: Configure OAuth Credentials -->
						<div class="notice notice-warning">
							<p><strong><?php esc_html_e( 'Setup Required:', 'marketing-analytics-mcp' ); ?></strong> <?php esc_html_e( 'You need to configure Google OAuth credentials first. These are the same credentials used for Google Analytics.', 'marketing-analytics-mcp' ); ?></p>
						</div>

						<h4><?php esc_html_e( 'Step 1: Configure Google OAuth Credentials', 'marketing-analytics-mcp' ); ?></h4>
						<p>
							<?php
							printf(
								/* translators: %s: link to GA4 tab */
								esc_html__( 'Please go to the %s tab to configure Google OAuth credentials first.', 'marketing-analytics-mcp' ),
								'<a href="?page=marketing-analytics-mcp-connections&tab=ga4">' . esc_html__( 'Google Analytics 4', 'marketing-analytics-mcp' ) . '</a>'
							);
							?>
						</p>

					<?php elseif ( ! $is_connected ) : ?>
						<!-- Step 2: Authorize with Google -->
						<div class="notice notice-info">
							<p><strong><?php esc_html_e( 'OAuth credentials configured!', 'marketing-analytics-mcp' ); ?></strong> <?php esc_html_e( 'Now authorize access to your Google Search Console account.', 'marketing-analytics-mcp' ); ?></p>
						</div>

						<h4><?php esc_html_e( 'Step 2: Authorize Google Search Console', 'marketing-analytics-mcp' ); ?></h4>
						<p><?php esc_html_e( 'Click the button below to connect your Google Search Console account:', 'marketing-analytics-mcp' ); ?></p>

						<?php if ( $auth_url ) : ?>
							<p class="submit">
								<a href="<?php echo esc_url( $auth_url ); ?>" class="button button-primary button-large">
									<span class="dashicons dashicons-google" style="margin-top: 3px;"></span>
									<?php esc_html_e( 'Connect to Google Search Console', 'marketing-analytics-mcp' ); ?>
								</a>
							</p>
						<?php else : ?>
							<p class="description" style="color: #dc3232;">
								<?php esc_html_e( 'Error generating authorization URL. Please check your OAuth credentials.', 'marketing-analytics-mcp' ); ?>
							</p>
						<?php endif; ?>

					<?php else : ?>
						<!-- Connected State -->
						<div class="notice notice-success">
							<p><strong><?php esc_html_e( 'Connected!', 'marketing-analytics-mcp' ); ?></strong> <?php esc_html_e( 'Your Google Search Console account is connected and ready to use.', 'marketing-analytics-mcp' ); ?></p>
						</div>

						<!-- Site Selection -->
						<h4><?php esc_html_e( 'Select Search Console Property', 'marketing-analytics-mcp' ); ?></h4>
						<p><?php esc_html_e( 'Choose which Search Console property to query for data:', 'marketing-analytics-mcp' ); ?></p>

						<table class="form-table">
							<tr>
								<th scope="row">
									<label for="gsc_site_selector"><?php esc_html_e( 'Site URL', 'marketing-analytics-mcp' ); ?></label>
								</th>
								<td>
									<div id="gsc-site-loading" style="display: none;">
										<span class="spinner is-active" style="float: none; margin: 0 10px 0 0;"></span>
										<?php esc_html_e( 'Loading sites...', 'marketing-analytics-mcp' ); ?>
									</div>

									<div id="gsc-site-error" class="notice notice-error inline" style="display: none; margin: 10px 0; padding: 10px;">
										<p></p>
									</div>

									<select id="gsc_site_selector" name="gsc_site_url" class="regular-text" style="display: none;">
										<option value=""><?php esc_html_e( 'Select a site...', 'marketing-analytics-mcp' ); ?></option>
									</select>

									<?php if ( $current_site_url ) : ?>
										<p class="description" style="color: #46b450;">
											<span class="dashicons dashicons-yes-alt"></span>
											<?php
											printf(
												/* translators: %s: site URL */
												esc_html__( 'Currently selected site: %s', 'marketing-analytics-mcp' ),
												'<strong>' . esc_html( $current_site_url ) . '</strong>'
											);
											?>
										</p>
									<?php else : ?>
										<p class="description">
											<?php esc_html_e( 'No site selected yet. Please select a site from the list above.', 'marketing-analytics-mcp' ); ?>
										</p>
									<?php endif; ?>
								</td>
							</tr>
						</table>

						<p class="submit">
							<button type="button" id="load-gsc-sites" class="button button-secondary">
								<?php esc_html_e( 'Load Available Sites', 'marketing-analytics-mcp' ); ?>
							</button>
							<button type="button" id="save-gsc-site" class="button button-primary" style="display: none;">
								<?php esc_html_e( 'Save Selected Site', 'marketing-analytics-mcp' ); ?>
							</button>
						</p>

						<hr style="margin: 30px 0;" />

						<form method="post" action="" style="margin-top: 20px;">
							<?php wp_nonce_field( 'marketing_analytics_mcp_disconnect_oauth', 'disconnect_oauth_nonce' ); ?>
							<input type="hidden" name="service" value="gsc" />

							<p class="submit">
								<button type="button" class="button button-secondary test-connection" data-platform="gsc">
									<?php esc_html_e( 'Test Connection', 'marketing-analytics-mcp' ); ?>
								</button>
								<input type="submit" name="disconnect_oauth" class="button button-secondary" value="<?php esc_attr_e( 'Disconnect', 'marketing-analytics-mcp' ); ?>" onclick="return confirm('<?php esc_attr_e( 'Are you sure you want to disconnect from Google Search Console?', 'marketing-analytics-mcp' ); ?>');" />
							</p>
						</form>
					<?php endif; ?>
				</div>
				<?php
				break;
		}
		?>
	</div>
</div>
