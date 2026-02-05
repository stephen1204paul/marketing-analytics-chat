<?php
/**
 * Meta Business Suite connection settings view
 *
 * @package Marketing_Analytics_MCP
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use Marketing_Analytics_MCP\Credentials\Credential_Manager;
use Marketing_Analytics_MCP\API_Clients\Meta_Client;

$credential_manager = new Credential_Manager();
$has_credentials    = $credential_manager->has_credentials( 'meta' );
$is_connected       = false;
$page_name          = '';
$instagram_name     = '';

if ( $has_credentials ) {
	$meta_client  = new Meta_Client();
	$is_connected = $meta_client->test_connection();

	// Get configured page and Instagram account names
	$page_id      = get_option( 'marketing_analytics_mcp_meta_page_id' );
	$instagram_id = get_option( 'marketing_analytics_mcp_meta_instagram_id' );

	if ( $page_id ) {
		$page_name = get_option( 'marketing_analytics_mcp_meta_page_name', $page_id );
	}
	if ( $instagram_id ) {
		$instagram_name = get_option( 'marketing_analytics_mcp_meta_instagram_name', $instagram_id );
	}
}

// OAuth callback URL
$redirect_uri = admin_url( 'admin.php?page=marketing-analytics-connections&oauth_callback=meta' );

?>

<div class="connection-panel meta-panel">
	<h3><?php esc_html_e( 'Meta Business Suite (Facebook & Instagram)', 'marketing-analytics-chat' ); ?></h3>

	<?php if ( $is_connected ) : ?>
		<div class="notice notice-success inline">
			<p>
				<span class="dashicons dashicons-yes"></span>
				<?php esc_html_e( 'Connected to Meta Business Suite', 'marketing-analytics-chat' ); ?>
			</p>
				<?php if ( $page_name ) : ?>
					<p>
						<?php
						/* translators: %s: Facebook page name */
						echo esc_html( sprintf( __( 'Facebook Page: %s', 'marketing-analytics-chat' ), $page_name ) );
						?>
					</p>
				<?php endif; ?>
				<?php if ( $instagram_name ) : ?>
					<p>
						<?php
						/* translators: %s: Instagram account name */
						echo esc_html( sprintf( __( 'Instagram Account: %s', 'marketing-analytics-chat' ), $instagram_name ) );
						?>
					</p>
				<?php endif; ?>
		</div>
	<?php elseif ( $has_credentials ) : ?>
		<div class="notice notice-warning inline">
			<p>
				<span class="dashicons dashicons-warning"></span>
				<?php esc_html_e( 'Connection failed. Please reconnect your Meta account.', 'marketing-analytics-chat' ); ?>
			</p>
		</div>
	<?php else : ?>
		<div class="notice notice-info inline">
			<p>
				<span class="dashicons dashicons-info"></span>
				<?php esc_html_e( 'Connect your Meta Business account to track Facebook and Instagram analytics.', 'marketing-analytics-chat' ); ?>
			</p>
		</div>
	<?php endif; ?>

	<div class="connection-wizard" data-platform="meta">
		<!-- Step 1: App Configuration -->
		<div class="wizard-step" data-step="1">
			<h4><?php esc_html_e( 'Step 1: Facebook App Configuration', 'marketing-analytics-chat' ); ?></h4>
			<p><?php esc_html_e( 'Enter your Facebook App credentials. You can create an app at', 'marketing-analytics-chat' ); ?>
				<a href="https://developers.facebook.com/apps/" target="_blank">Facebook for Developers</a>.</p>

			<form id="meta-app-config-form">
				<table class="form-table">
					<tr>
						<th scope="row">
							<label for="meta-app-id"><?php esc_html_e( 'App ID', 'marketing-analytics-chat' ); ?></label>
						</th>
						<td>
							<input type="text" id="meta-app-id" name="app_id" class="regular-text"
								value="<?php echo esc_attr( get_option( 'marketing_analytics_mcp_meta_app_id' ) ); ?>" />
							<p class="description"><?php esc_html_e( 'Your Facebook App ID', 'marketing-analytics-chat' ); ?></p>
						</td>
					</tr>
					<tr>
						<th scope="row">
							<label for="meta-app-secret"><?php esc_html_e( 'App Secret', 'marketing-analytics-chat' ); ?></label>
						</th>
						<td>
							<input type="password" id="meta-app-secret" name="app_secret" class="regular-text"
								value="<?php echo esc_attr( get_option( 'marketing_analytics_mcp_meta_app_secret' ) ); ?>" />
							<p class="description"><?php esc_html_e( 'Your Facebook App Secret', 'marketing-analytics-chat' ); ?></p>
						</td>
					</tr>
				</table>

				<p class="submit">
					<button type="button" class="button button-primary" id="meta-save-app-config">
						<?php esc_html_e( 'Save & Continue', 'marketing-analytics-chat' ); ?>
					</button>
				</p>
			</form>
		</div>

		<!-- Step 2: OAuth Authorization -->
		<div class="wizard-step" data-step="2" style="display: none;">
			<h4><?php esc_html_e( 'Step 2: Authorize Access', 'marketing-analytics-chat' ); ?></h4>
			<p><?php esc_html_e( 'Click the button below to authorize access to your Facebook Pages and Instagram Business accounts.', 'marketing-analytics-chat' ); ?></p>

			<div class="oauth-info">
				<p><strong><?php esc_html_e( 'Required Permissions:', 'marketing-analytics-chat' ); ?></strong></p>
				<ul>
					<li><?php esc_html_e( 'Access to Facebook Pages you manage', 'marketing-analytics-chat' ); ?></li>
					<li><?php esc_html_e( 'Read insights and engagement data', 'marketing-analytics-chat' ); ?></li>
					<li><?php esc_html_e( 'Access to Instagram Business accounts', 'marketing-analytics-chat' ); ?></li>
				</ul>

				<p><strong><?php esc_html_e( 'Redirect URI for your app:', 'marketing-analytics-chat' ); ?></strong></p>
				<code><?php echo esc_html( $redirect_uri ); ?></code>
			</div>

			<p class="submit">
				<button type="button" class="button button-primary" id="meta-authorize-btn">
					<span class="dashicons dashicons-facebook-alt"></span>
					<?php esc_html_e( 'Connect with Facebook', 'marketing-analytics-chat' ); ?>
				</button>
			</p>
		</div>

		<!-- Step 3: Select Accounts -->
		<div class="wizard-step" data-step="3" style="display: none;">
			<h4><?php esc_html_e( 'Step 3: Select Accounts', 'marketing-analytics-chat' ); ?></h4>
			<p><?php esc_html_e( 'Select the Facebook Page and Instagram Business account to connect.', 'marketing-analytics-chat' ); ?></p>

			<div id="meta-accounts-selection">
				<div class="account-section">
					<h5><?php esc_html_e( 'Facebook Pages', 'marketing-analytics-chat' ); ?></h5>
					<div id="facebook-pages-list" class="accounts-list">
						<p class="loading"><span class="spinner is-active"></span> <?php esc_html_e( 'Loading pages...', 'marketing-analytics-chat' ); ?></p>
					</div>
				</div>

				<div class="account-section">
					<h5><?php esc_html_e( 'Instagram Business Account', 'marketing-analytics-chat' ); ?></h5>
					<div id="instagram-accounts-list" class="accounts-list">
						<p class="description"><?php esc_html_e( 'Instagram account will be loaded after selecting a Facebook Page.', 'marketing-analytics-chat' ); ?></p>
					</div>
				</div>
			</div>

			<p class="submit">
				<button type="button" class="button button-primary" id="meta-complete-setup" disabled>
					<?php esc_html_e( 'Complete Setup', 'marketing-analytics-chat' ); ?>
				</button>
			</p>
		</div>
	</div>

	<?php if ( $is_connected ) : ?>
		<div class="connection-actions">
			<button type="button" class="button" id="meta-test-connection">
				<?php esc_html_e( 'Test Connection', 'marketing-analytics-chat' ); ?>
			</button>
			<button type="button" class="button button-secondary" id="meta-reconnect">
				<?php esc_html_e( 'Reconnect', 'marketing-analytics-chat' ); ?>
			</button>
			<button type="button" class="button button-link-delete" id="meta-disconnect">
				<?php esc_html_e( 'Disconnect', 'marketing-analytics-chat' ); ?>
			</button>
		</div>

		<div id="meta-test-results" style="display: none;"></div>
	<?php endif; ?>
</div>

<script>
jQuery(document).ready(function($) {
	// Save app configuration
	$('#meta-save-app-config').on('click', function() {
		const appId = $('#meta-app-id').val();
		const appSecret = $('#meta-app-secret').val();

		if (!appId || !appSecret) {
			alert('<?php echo esc_js( __( 'Please enter both App ID and App Secret', 'marketing-analytics-chat' ) ); ?>');
			return;
		}

		$.post(ajaxurl, {
			action: 'marketing_analytics_save_meta_app_config',
			app_id: appId,
			app_secret: appSecret,
			nonce: '<?php echo esc_js( wp_create_nonce( 'marketing_analytics_ajax' ) ); ?>'
		}, function(response) {
			if (response.success) {
				$('.wizard-step[data-step="1"]').hide();
				$('.wizard-step[data-step="2"]').show();
			} else {
				alert(response.data.message || '<?php echo esc_js( __( 'Failed to save configuration', 'marketing-analytics-chat' ) ); ?>');
			}
		});
	});

	// OAuth authorization
	$('#meta-authorize-btn').on('click', function() {
		const appId = '<?php echo esc_js( get_option( 'marketing_analytics_mcp_meta_app_id' ) ); ?>';
		if (!appId) {
			alert('<?php echo esc_js( __( 'Please configure App ID first', 'marketing-analytics-chat' ) ); ?>');
			return;
		}

		const redirectUri = '<?php echo esc_js( $redirect_uri ); ?>';
		const scopes = 'pages_show_list,pages_read_engagement,pages_read_user_content,instagram_basic,instagram_manage_insights';
		const state = '<?php echo esc_js( wp_create_nonce( 'meta_oauth' ) ); ?>';

		const authUrl = `https://www.facebook.com/v21.0/dialog/oauth?client_id=${appId}&redirect_uri=${encodeURIComponent(redirectUri)}&scope=${scopes}&state=${state}`;

		// Open OAuth window
		const authWindow = window.open(authUrl, 'MetaAuth', 'width=600,height=600');

		// Check for completion
		const checkInterval = setInterval(function() {
			if (authWindow.closed) {
				clearInterval(checkInterval);
				// Check if authorization was successful
				$.post(ajaxurl, {
					action: 'marketing_analytics_check_meta_auth',
					nonce: '<?php echo esc_js( wp_create_nonce( 'marketing_analytics_ajax' ) ); ?>'
				}, function(response) {
					if (response.success) {
						$('.wizard-step[data-step="2"]').hide();
						$('.wizard-step[data-step="3"]').show();
						loadFacebookPages();
					}
				});
			}
		}, 1000);
	});

	// Load Facebook pages
	function loadFacebookPages() {
		$.post(ajaxurl, {
			action: 'marketing_analytics_list_facebook_pages',
			nonce: '<?php echo esc_js( wp_create_nonce( 'marketing_analytics_ajax' ) ); ?>'
		}, function(response) {
			if (response.success && response.data.pages) {
				let html = '<select id="facebook-page-select" class="regular-text">';
				html += '<option value=""><?php echo esc_js( __( 'Select a page...', 'marketing-analytics-chat' ) ); ?></option>';
				response.data.pages.forEach(function(page) {
					html += `<option value="${page.id}" data-name="${page.name}">${page.name}</option>`;
				});
				html += '</select>';
				$('#facebook-pages-list').html(html);

				// Load Instagram account when page is selected
				$('#facebook-page-select').on('change', function() {
					const pageId = $(this).val();
					const pageName = $(this).find(':selected').data('name');
					if (pageId) {
						loadInstagramAccount(pageId);
						$('#meta-complete-setup').prop('disabled', false);
					} else {
						$('#instagram-accounts-list').html('<p class="description"><?php echo esc_js( __( 'Select a Facebook Page first.', 'marketing-analytics-chat' ) ); ?></p>');
						$('#meta-complete-setup').prop('disabled', true);
					}
				});
			} else {
				$('#facebook-pages-list').html('<p class="error"><?php echo esc_js( __( 'No pages found or error loading pages.', 'marketing-analytics-chat' ) ); ?></p>');
			}
		});
	}

	// Load Instagram account for selected page
	function loadInstagramAccount(pageId) {
		$('#instagram-accounts-list').html('<p class="loading"><span class="spinner is-active"></span> <?php echo esc_js( __( 'Loading Instagram account...', 'marketing-analytics-chat' ) ); ?></p>');

		$.post(ajaxurl, {
			action: 'marketing_analytics_get_instagram_account',
			page_id: pageId,
			nonce: '<?php echo esc_js( wp_create_nonce( 'marketing_analytics_ajax' ) ); ?>'
		}, function(response) {
			if (response.success && response.data.instagram_id) {
				const html = `<p><strong>${response.data.instagram_name || response.data.instagram_id}</strong><input type="hidden" id="instagram-account-id" value="${response.data.instagram_id}" /></p>`;
				$('#instagram-accounts-list').html(html);
			} else {
				$('#instagram-accounts-list').html('<p class="notice"><?php echo esc_js( __( 'No Instagram Business account connected to this page.', 'marketing-analytics-chat' ) ); ?></p>');
			}
		});
	}

	// Complete setup
	$('#meta-complete-setup').on('click', function() {
		const pageId = $('#facebook-page-select').val();
		const pageName = $('#facebook-page-select').find(':selected').data('name');
		const instagramId = $('#instagram-account-id').val();

		if (!pageId) {
			alert('<?php echo esc_js( __( 'Please select a Facebook Page', 'marketing-analytics-chat' ) ); ?>');
			return;
		}

		$.post(ajaxurl, {
			action: 'marketing_analytics_save_meta_accounts',
			page_id: pageId,
			page_name: pageName,
			instagram_id: instagramId,
			nonce: '<?php echo esc_js( wp_create_nonce( 'marketing_analytics_ajax' ) ); ?>'
		}, function(response) {
			if (response.success) {
				alert('<?php echo esc_js( __( 'Meta Business Suite connected successfully!', 'marketing-analytics-chat' ) ); ?>');
				location.reload();
			} else {
				alert(response.data.message || '<?php echo esc_js( __( 'Failed to save configuration', 'marketing-analytics-chat' ) ); ?>');
			}
		});
	});

	// Test connection
	$('#meta-test-connection').on('click', function() {
		const $button = $(this);
		const $results = $('#meta-test-results');

		$button.prop('disabled', true).text('<?php echo esc_js( __( 'Testing...', 'marketing-analytics-chat' ) ); ?>');

		$.post(ajaxurl, {
			action: 'marketing_analytics_test_connection',
			platform: 'meta',
			nonce: '<?php echo esc_js( wp_create_nonce( 'marketing_analytics_ajax' ) ); ?>'
		}, function(response) {
			if (response.success) {
				$results.html('<div class="notice notice-success"><p><?php echo esc_js( __( 'Connection successful!', 'marketing-analytics-chat' ) ); ?></p></div>').show();
			} else {
				$results.html('<div class="notice notice-error"><p>' + (response.data.message || '<?php echo esc_js( __( 'Connection failed', 'marketing-analytics-chat' ) ); ?>') + '</p></div>').show();
			}
		}).always(function() {
			$button.prop('disabled', false).text('<?php echo esc_js( __( 'Test Connection', 'marketing-analytics-chat' ) ); ?>');
		});
	});

	// Reconnect
	$('#meta-reconnect').on('click', function() {
		$('.connection-wizard').show();
		$('.wizard-step').hide();
		$('.wizard-step[data-step="1"]').show();
	});

	// Disconnect
	$('#meta-disconnect').on('click', function() {
		if (!confirm('<?php echo esc_js( __( 'Are you sure you want to disconnect Meta Business Suite?', 'marketing-analytics-chat' ) ); ?>')) {
			return;
		}

		$.post(ajaxurl, {
			action: 'marketing_analytics_disconnect',
			platform: 'meta',
			nonce: '<?php echo esc_js( wp_create_nonce( 'marketing_analytics_ajax' ) ); ?>'
		}, function(response) {
			if (response.success) {
				location.reload();
			} else {
				alert(response.data.message || '<?php echo esc_js( __( 'Failed to disconnect', 'marketing-analytics-chat' ) ); ?>');
			}
		});
	});
});
</script>
