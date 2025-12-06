<?php
/**
 * Network Sites Management Page
 *
 * @package Marketing_Analytics_MCP
 * @subpackage Admin\Views
 */

// Don't allow direct access
if ( ! defined( 'WPINC' ) ) {
	die;
}

use Marketing_Analytics_MCP\Multisite\Network_Manager;

// Get network manager
$network_manager = new Network_Manager();

// Handle actions
$action  = isset( $_GET['action'] ) ? sanitize_text_field( wp_unslash( $_GET['action'] ) ) : '';
$site_id = isset( $_GET['site_id'] ) ? intval( $_GET['site_id'] ) : 0;

// Get all sites
$sites = $network_manager->get_sites();
?>

<div class="wrap">
	<h1 class="wp-heading-inline"><?php esc_html_e( 'Network Sites', 'marketing-analytics-chat' ); ?></h1>
	<a href="#" class="page-title-action" id="add-new-site"><?php esc_html_e( 'Add New Site', 'marketing-analytics-chat' ); ?></a>
	<hr class="wp-header-end">

	<div class="notice notice-info">
		<p>
			<?php esc_html_e( 'Manage multiple WordPress sites from a single dashboard. Monitor analytics across your entire network of sites.', 'marketing-analytics-chat' ); ?>
		</p>
	</div>

	<?php if ( ! empty( $sites ) ) : ?>
		<table class="wp-list-table widefat fixed striped">
			<thead>
				<tr>
					<th><?php esc_html_e( 'Site Name', 'marketing-analytics-chat' ); ?></th>
					<th><?php esc_html_e( 'URL', 'marketing-analytics-chat' ); ?></th>
					<th><?php esc_html_e( 'Auth Method', 'marketing-analytics-chat' ); ?></th>
					<th><?php esc_html_e( 'Status', 'marketing-analytics-chat' ); ?></th>
					<th><?php esc_html_e( 'Last Sync', 'marketing-analytics-chat' ); ?></th>
					<th><?php esc_html_e( 'Actions', 'marketing-analytics-chat' ); ?></th>
				</tr>
			</thead>
			<tbody>
				<?php foreach ( $sites as $site ) : ?>
					<tr>
						<td>
							<strong><?php echo esc_html( $site->site_name ); ?></strong>
						</td>
						<td>
							<a href="<?php echo esc_url( $site->site_url ); ?>" target="_blank">
								<?php echo esc_html( $site->site_url ); ?>
								<span class="dashicons dashicons-external" style="font-size: 14px;"></span>
							</a>
						</td>
						<td><?php echo esc_html( ucfirst( str_replace( '_', ' ', $site->auth_method ) ) ); ?></td>
						<td>
							<?php if ( $site->is_active ) : ?>
								<span style="color: #46b450;">● <?php esc_html_e( 'Active', 'marketing-analytics-chat' ); ?></span>
							<?php else : ?>
								<span style="color: #dc3232;">● <?php esc_html_e( 'Inactive', 'marketing-analytics-chat' ); ?></span>
							<?php endif; ?>
						</td>
						<td>
							<?php
							if ( $site->last_sync ) {
								echo esc_html( human_time_diff( strtotime( $site->last_sync ), current_time( 'timestamp' ) ) );
								esc_html_e( ' ago', 'marketing-analytics-chat' );
							} else {
								esc_html_e( 'Never', 'marketing-analytics-chat' );
							}
							?>
						</td>
						<td>
							<button class="button button-small sync-site" data-site-id="<?php echo esc_attr( $site->id ); ?>">
								<?php esc_html_e( 'Sync Now', 'marketing-analytics-chat' ); ?>
							</button>
							<button class="button button-small test-connection" data-site-id="<?php echo esc_attr( $site->id ); ?>">
								<?php esc_html_e( 'Test', 'marketing-analytics-chat' ); ?>
							</button>
							<button class="button button-small edit-site" data-site-id="<?php echo esc_attr( $site->id ); ?>">
								<?php esc_html_e( 'Edit', 'marketing-analytics-chat' ); ?>
							</button>
							<button class="button button-small button-link-delete remove-site" data-site-id="<?php echo esc_attr( $site->id ); ?>">
								<?php esc_html_e( 'Remove', 'marketing-analytics-chat' ); ?>
							</button>
						</td>
					</tr>
				<?php endforeach; ?>
			</tbody>
		</table>
	<?php else : ?>
		<div class="notice notice-warning">
			<p>
				<?php esc_html_e( 'No network sites configured yet. Click "Add New Site" to get started.', 'marketing-analytics-chat' ); ?>
			</p>
		</div>
	<?php endif; ?>

	<!-- Network Summary -->
	<?php if ( ! empty( $sites ) ) : ?>
		<h2><?php esc_html_e( 'Network Summary', 'marketing-analytics-chat' ); ?></h2>
		<div class="network-stats">
			<div class="stat-card">
				<h3><?php esc_html_e( 'Total Sites', 'marketing-analytics-chat' ); ?></h3>
				<p class="stat-value"><?php echo esc_html( count( $sites ) ); ?></p>
			</div>
			<div class="stat-card">
				<h3><?php esc_html_e( 'Active Sites', 'marketing-analytics-chat' ); ?></h3>
				<p class="stat-value">
					<?php
					$active_count = count(
						array_filter(
							$sites,
							function ( $s ) {
								return $s->is_active;
							}
						)
					);
					echo esc_html( $active_count );
					?>
				</p>
			</div>
		</div>
	<?php endif; ?>
</div>

<!-- Add/Edit Site Modal -->
<div id="site-modal" class="modal" style="display: none;">
	<div class="modal-content">
		<span class="close">&times;</span>
		<h2 id="modal-title"><?php esc_html_e( 'Add New Site', 'marketing-analytics-chat' ); ?></h2>

		<form id="site-form">
			<input type="hidden" id="site_id" name="site_id" value="0">
			<?php wp_nonce_field( 'marketing_analytics_site_action', 'site_nonce' ); ?>

			<table class="form-table">
				<tr>
					<th><label for="site_name"><?php esc_html_e( 'Site Name', 'marketing-analytics-chat' ); ?></label></th>
					<td>
						<input type="text" id="site_name" name="site_name" class="regular-text" required>
					</td>
				</tr>
				<tr>
					<th><label for="site_url"><?php esc_html_e( 'Site URL', 'marketing-analytics-chat' ); ?></label></th>
					<td>
						<input type="url" id="site_url" name="site_url" class="regular-text" required>
					</td>
				</tr>
				<tr>
					<th><label for="auth_method"><?php esc_html_e( 'Authentication Method', 'marketing-analytics-chat' ); ?></label></th>
					<td>
						<select id="auth_method" name="auth_method">
							<option value="api_key"><?php esc_html_e( 'API Key', 'marketing-analytics-chat' ); ?></option>
							<option value="basic_auth"><?php esc_html_e( 'Basic Authentication', 'marketing-analytics-chat' ); ?></option>
							<option value="oauth"><?php esc_html_e( 'OAuth', 'marketing-analytics-chat' ); ?></option>
						</select>
					</td>
				</tr>
				<tr id="api_key_row">
					<th><label for="api_key"><?php esc_html_e( 'API Key', 'marketing-analytics-chat' ); ?></label></th>
					<td>
						<input type="text" id="api_key" name="api_key" class="regular-text">
					</td>
				</tr>
				<tr id="basic_auth_row" style="display: none;">
					<th><?php esc_html_e( 'Basic Auth Credentials', 'marketing-analytics-chat' ); ?></th>
					<td>
						<input type="text" id="basic_username" name="basic_username" class="regular-text" placeholder="Username">
						<br><br>
						<input type="password" id="basic_password" name="basic_password" class="regular-text" placeholder="Application Password">
					</td>
				</tr>
			</table>

			<p class="submit">
				<button type="submit" class="button button-primary"><?php esc_html_e( 'Save Site', 'marketing-analytics-chat' ); ?></button>
				<button type="button" class="button cancel-modal"><?php esc_html_e( 'Cancel', 'marketing-analytics-chat' ); ?></button>
			</p>
		</form>
	</div>
</div>

<style>
.network-stats {
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
.modal {
	position: fixed;
	z-index: 100000;
	left: 0;
	top: 0;
	width: 100%;
	height: 100%;
	background-color: rgba(0,0,0,0.4);
}
.modal-content {
	background-color: #fefefe;
	margin: 5% auto;
	padding: 20px;
	border: 1px solid #888;
	width: 60%;
	max-width: 600px;
}
.close {
	color: #aaa;
	float: right;
	font-size: 28px;
	font-weight: bold;
	cursor: pointer;
}
.close:hover,
.close:focus {
	color: black;
}
</style>

<script>
jQuery(document).ready(function($) {
	var modal = $('#site-modal');

	// Add new site
	$('#add-new-site').on('click', function(e) {
		e.preventDefault();
		$('#modal-title').text('<?php esc_html_e( 'Add New Site', 'marketing-analytics-chat' ); ?>');
		$('#site_id').val(0);
		$('#site-form')[0].reset();
		modal.show();
	});

	// Close modal
	$('.close, .cancel-modal').on('click', function() {
		modal.hide();
	});

	// Auth method change
	$('#auth_method').on('change', function() {
		var method = $(this).val();
		$('#api_key_row, #basic_auth_row').hide();

		if (method === 'api_key') {
			$('#api_key_row').show();
		} else if (method === 'basic_auth') {
			$('#basic_auth_row').show();
		}
	});

	// Save site
	$('#site-form').on('submit', function(e) {
		e.preventDefault();

		var formData = $(this).serialize();
		formData += '&action=marketing_analytics_save_network_site';

		$.post(ajaxurl, formData, function(response) {
			if (response.success) {
				alert('<?php esc_html_e( 'Site saved successfully!', 'marketing-analytics-chat' ); ?>');
				location.reload();
			} else {
				alert('<?php esc_html_e( 'Error: ', 'marketing-analytics-chat' ); ?>' + response.data);
			}
		});
	});

	// Test connection
	$('.test-connection').on('click', function() {
		var button = $(this);
		var siteId = button.data('site-id');

		button.prop('disabled', true).text('<?php esc_html_e( 'Testing...', 'marketing-analytics-chat' ); ?>');

		$.post(ajaxurl, {
			action: 'marketing_analytics_test_site_connection',
			site_id: siteId,
			nonce: '<?php echo esc_js( wp_create_nonce( 'marketing_analytics_site_test' ) ); ?>'
		}, function(response) {
			button.prop('disabled', false).text('<?php esc_html_e( 'Test', 'marketing-analytics-chat' ); ?>');

			if (response.success) {
				alert('<?php esc_html_e( 'Connection successful!', 'marketing-analytics-chat' ); ?>');
			} else {
				alert('<?php esc_html_e( 'Connection failed: ', 'marketing-analytics-chat' ); ?>' + response.data);
			}
		});
	});

	// Sync site
	$('.sync-site').on('click', function() {
		var button = $(this);
		var siteId = button.data('site-id');

		button.prop('disabled', true).text('<?php esc_html_e( 'Syncing...', 'marketing-analytics-chat' ); ?>');

		$.post(ajaxurl, {
			action: 'marketing_analytics_sync_site',
			site_id: siteId,
			nonce: '<?php echo esc_js( wp_create_nonce( 'marketing_analytics_site_sync' ) ); ?>'
		}, function(response) {
			button.prop('disabled', false).text('<?php esc_html_e( 'Sync Now', 'marketing-analytics-chat' ); ?>');

			if (response.success) {
				alert('<?php esc_html_e( 'Site synced successfully!', 'marketing-analytics-chat' ); ?>');
				location.reload();
			} else {
				alert('<?php esc_html_e( 'Sync failed: ', 'marketing-analytics-chat' ); ?>' + response.data);
			}
		});
	});

	// Remove site
	$('.remove-site').on('click', function() {
		if (!confirm('<?php esc_html_e( 'Are you sure you want to remove this site?', 'marketing-analytics-chat' ); ?>')) {
			return;
		}

		var button = $(this);
		var siteId = button.data('site-id');

		$.post(ajaxurl, {
			action: 'marketing_analytics_remove_site',
			site_id: siteId,
			nonce: '<?php echo esc_js( wp_create_nonce( 'marketing_analytics_site_remove' ) ); ?>'
		}, function(response) {
			if (response.success) {
				alert('<?php esc_html_e( 'Site removed successfully!', 'marketing-analytics-chat' ); ?>');
				location.reload();
			} else {
				alert('<?php esc_html_e( 'Error: ', 'marketing-analytics-chat' ); ?>' + response.data);
			}
		});
	});
});
</script>